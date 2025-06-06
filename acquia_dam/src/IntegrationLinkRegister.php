<?php

namespace Drupal\acquia_dam;

use Drupal\acquia_dam\Client\AcquiaDamClientFactory;
use Drupal\Core\Database\Connection;
use Drupal\Core\DestructableInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Queue\QueueFactory;

/**
 * Register and remove integration links from Acquia DAM.
 */
class IntegrationLinkRegister implements DestructableInterface {

  /**
   * List of entities to create integration links for.
   *
   * @var array
   */
  protected $integrations = [];

  /**
   * List of entities to delete associated integration links.
   *
   * @var array
   */
  protected $integrationsToDelete = [];


  /**
   * List of integration links to delete.
   *
   * @var array
   */
  protected $trackingToDelete = [];

  /**
   * DAM client factory.
   *
   * @var \Drupal\acquia_dam\Client\AcquiaDamClientFactory
   */
  protected $clientFactory;

  /**
   * Database connection service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityBundleInfo;

  /**
   * Logger channel for Acquia DAM.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $damLoggerChannel;

  /**
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * IntegrationLinkRegister constructor.
   *
   * @param \Drupal\acquia_dam\Client\AcquiaDamClientFactory $client_factory
   *   DAM client factory.
   * @param \Drupal\Core\Database\Connection $database
   *   Database connection.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $loggerChannel
   *   Logger channel instance.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityBundleInfo
   *   Entity bundle info.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   */
  public function __construct(AcquiaDamClientFactory $client_factory, Connection $database, LoggerChannelInterface $loggerChannel, EntityTypeBundleInfoInterface $entityBundleInfo, QueueFactory $queue_factory, EntityRepositoryInterface $entity_repository) {
    $this->clientFactory = $client_factory;
    $this->database = $database;
    $this->damLoggerChannel = $loggerChannel;
    $this->entityBundleInfo = $entityBundleInfo;
    $this->queueFactory = $queue_factory;
    $this->entityRepository = $entity_repository;
  }

  /**
   * Add entity and necessary info to the list for integration link creation.
   *
   * @param string $asset_id
   *   DAM asset id.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Content entity instance.
   */
  public function addIntegrationLinksList(string $asset_id, ContentEntityInterface $entity): void {
    $this->integrations[$entity->uuid()]['asset_uuids'][] = $asset_id;
    $this->integrations[$entity->uuid()]['entity'] = $entity;
  }

  /**
   * Add entity to the deletion list.
   *
   * @param string $entity_uuid
   *   Entity uuid.
   */
  public function addIntegrationToDeleteList(string $entity_uuid) {
    $this->integrationsToDelete[] = $entity_uuid;
  }

  /**
   * Add asset to tracking delete list.
   *
   * @param string $asset_uuid
   *   Asset uuid.
   * @param string $entity_uuid
   *   Entity uuid.
   */
  public function addToTrackingDeleteList(string $asset_uuid, string $entity_uuid) {
    $this->trackingToDelete[$entity_uuid][] = $asset_uuid;
  }

  /**
   * Register integration links from integrations list.
   */
  protected function registerIntegrationLinks(): void {
    $queue = $this->queueFactory->get('acquia_dam_integration_links');
    foreach ($this->integrations as $item) {
      foreach ($item['asset_uuids'] as $asset_uuid) {
        $queue->createItem([
          'op' => 'registerIntegrationLink',
          'args' => [
            $asset_uuid,
            $item['entity']->getEntityTypeId(),
            $item['entity']->uuid(),
          ],
        ]);
      }
    }

    $this->integrations = [];
  }

  /**
   * Remove integration list based on integrationsToDelete list.
   */
  protected function removeIntegrationLinks(): void {
    $queue = $this->queueFactory->get('acquia_dam_integration_links');
    foreach ($this->integrationsToDelete as $entity_uuid) {
      $queue->createItem([
        'op' => 'removeIntegrationLink',
        'args' => [$entity_uuid],
      ]);
    }
  }

  /**
   * Remove integration list based on integrationsToDelete list.
   */
  protected function removeTracking(): void {
    $queue = $this->queueFactory->get('acquia_dam_integration_links');
    foreach ($this->trackingToDelete as $entity_uuid => $asset_ids) {
      $queue->createItem([
        'op' => 'removeTrackings',
        'args' => [$entity_uuid, $asset_ids],
      ]);
    }
  }

  /**
   * Add integration link for a given entity.
   *
   * @param string $asset_id
   *   DAM asset id.
   * @param string $entity_type
   *   The entity type.
   * @param string $entity_uuid
   *   The entity UUID.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function registerIntegrationLink(string $asset_id, string $entity_type, string $entity_uuid): void {
    try {
      $entity = $this->entityRepository->loadEntityByUuid($entity_type, $entity_uuid);
      // $entity can be NULL if it was deleted before the cron runs.
      if (is_null($entity)) {
        return;
      }
      $url = $entity->toUrl()->setAbsolute()->toString();
      $client = $this->clientFactory->getSiteClient();
      $data = $client->registerIntegrationLink($asset_id, $url, $this->generateIntegrationLinkDescription($entity));
      $this->trackIntegration($asset_id, $entity->uuid(), $entity->getEntityTypeId(), $data['uuid'], $url);
    }
    catch (\Exception $exception) {
      $this->damLoggerChannel->error(sprintf(
        'Something went wrong during integration link registration for asset: %s. Error message: %s',
        $asset_id,
        $exception->getMessage()
      ));
    }
  }

  /**
   * Remove integration links for given entity.
   *
   * @param string $entity_uuid
   *   Entity uuid.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function removeIntegrationLink(string $entity_uuid): void {
    try {
      $client = $this->clientFactory->getSiteClient();

      $integration_links = $this
        ->database
        ->select('acquia_dam_integration_link_tracking', 'int')
        ->fields('int', ['integration_link_id', 'asset_uuid'])
        ->condition('entity_uuid', $entity_uuid)
        ->execute()
        ->fetchAll();

      if (empty($integration_links)) {
        $this->damLoggerChannel->warning(sprintf(
          'There are no integration links associated with entity: %s in local storage',
          $entity_uuid,
        ));
      }

      foreach ($integration_links as $integration_link) {
        $client->removeIntegrationLink($integration_link->integration_link_id);
        $this->updateAssetTrackingAggregate($integration_link->asset_uuid, -1);
      }

      $this->removeIntegrationTracking($entity_uuid);
      $this->integrationsToDelete = [];
    }
    catch (\Exception $exception) {
      $this->damLoggerChannel->error(sprintf(
        'Something went wrong during integration link removal for entity: %s integration link: %s. Error message: %s',
        $entity_uuid,
        $integration_link->integration_link_id,
        $exception->getMessage()
      ));
    }
  }

  /**
   * Remove integration links by integration link ids.
   *
   * @param string $entity_uuid
   *   Entity instance where the assets are used.
   * @param array $asset_ids
   *   Asset ids.
   *
   * @throws \Drupal\acquia_dam\Exception\DamClientException
   * @throws \Drupal\acquia_dam\Exception\DamConnectException
   * @throws \Drupal\acquia_dam\Exception\DamServerException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function removeTrackings(string $entity_uuid, array $asset_ids) {
    try {
      $client = $this->clientFactory->getSiteClient();

      $integration_links = $this
        ->database
        ->select('acquia_dam_integration_link_tracking', 'int')
        ->fields('int', ['integration_link_id', 'asset_uuid'])
        ->condition('entity_uuid', $entity_uuid)
        ->condition('asset_uuid', $asset_ids, 'IN')
        ->execute()
        ->fetchAll();

      if (empty($integration_links)) {
        $this->damLoggerChannel->warning(sprintf(
          'There are no integration links associated with entity: %s in local storage',
          $entity_uuid,
        ));
      }

      foreach ($integration_links as $integration_link) {
        $client->removeIntegrationLink($integration_link->integration_link_id);
        $this
          ->database
          ->delete('acquia_dam_integration_link_tracking')
          ->condition('integration_link_id', $integration_link->integration_link_id)
          ->execute();
      }

      $this->trackingToDelete = [];
    }
    catch (\Exception $exception) {
      $this->damLoggerChannel->error(sprintf(
        'Something went wrong during integration link removal for asset id: %s entity: %s integration link: %s. Error message: %s',
        $integration_link->asset_uuid,
        $entity_uuid,
        $integration_link->integration_link_id,
        $exception->getMessage()
      ));
    }
  }

  /**
   * Tracks integration link info in custom database table.
   *
   * @param string $asset_uuid
   *   Asset uuid.
   * @param string $entity_uuid
   *   Entity uuid.
   * @param string $entity_type
   *   Entity type id.
   * @param string $integration_id
   *   Integration link id.
   * @param string $path
   *   Path to entity.
   *
   * @throws \Exception
   */
  protected function trackIntegration(string $asset_uuid, string $entity_uuid, string $entity_type, string $integration_id, string $path) {
    // Use UPSERT to handle instances where the DAM returns an existing
    // integration link ID based on the token used and path.
    $this->database->upsert('acquia_dam_integration_link_tracking')
      ->key('integration_link_id')
      ->fields([
        'asset_uuid' => $asset_uuid,
        'entity_uuid' => $entity_uuid,
        'entity_type' => $entity_type,
        'integration_link_id' => $integration_id,
        'path' => $path,
        'created' => date('c'),
      ])
      ->execute();

    $this->updateAssetTrackingAggregate($asset_uuid, 1);
  }

  /**
   * Removes integration link tracking from custom database table.
   *
   * @param string $entity_uuid
   *   Entity uuid.
   */
  protected function removeIntegrationTracking(string $entity_uuid) {
    $this->database->delete('acquia_dam_integration_link_tracking')
      ->condition('entity_uuid', $entity_uuid)
      ->execute();
  }

  /**
   * Generates integration link description.
   *
   * Description can be 255 characters maximum.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity instance.
   *
   * @return string
   *   Description for integration links.
   */
  protected function generateIntegrationLinkDescription(ContentEntityInterface $entity): string {
    $bundle_info = $this->entityBundleInfo->getAllBundleInfo();

    // Bundle label can be 50 chars maximum.
    $bundle_label = $bundle_info[$entity->getEntityTypeId()][$entity->bundle()]['label'];
    $bundle_label = strlen($bundle_label) <= 50 ?
      $bundle_label
      : substr($bundle_label, 0, 47) . '...';

    // Entity title can be 195 chars maximum.
    $label = $entity->label();
    $label = strlen($label) <= 195 ?
      $label
      : substr($label, 0, 192) . '...';

    return sprintf('%s titled "%s"', $bundle_label, $label);
  }

  /**
   * {@inheritdoc}
   */
  public function destruct() {
    // Start with delete to avoid errors on register triggered by title change.
    if (!empty($this->integrationsToDelete)) {
      $this->removeIntegrationLinks();
    }
    if (!empty($this->integrations)) {
      $this->registerIntegrationLinks();
    }
    if (!empty($this->trackingToDelete)) {
      $this->removeTracking();
    }
  }

  /**
   * Update integration link aggregate table data.
   *
   * @param string $asset_uuid
   *   Line to update.
   * @param int $value
   *   Value to add to the useage count value.
   *
   * @throws \Exception
   */
  protected function updateAssetTrackingAggregate(string $asset_uuid, int $value): void {
    $result = $this
      ->database
      ->update('acquia_dam_integration_link_aggregate')
      ->condition('asset_uuid', $asset_uuid)
      ->expression('usage_count', "usage_count + $value")
      ->execute();

    if (!$result) {
      $this
        ->database
        ->insert('acquia_dam_integration_link_aggregate')
        ->fields([
          'asset_uuid' => $asset_uuid,
          'usage_count' => $value,
        ])
        ->execute();
    }
  }

  /**
   * Returns all entity uuids which associated with the given asset.
   *
   * @param string $asset_uuid
   *   Asset UUID.
   *
   * @return string[]
   *   Array containing entity uuids.
   */
  public function getAllEntityUuuidsIntegratingAsset(string $asset_uuid): array {
    return $this
      ->database
      ->select('acquia_dam_integration_link_tracking', 'int')
      ->fields('int', ['entity_uuid'])
      ->condition('asset_uuid', $asset_uuid)
      ->execute()
      ->fetchCol();
  }

}
