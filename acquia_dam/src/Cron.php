<?php

namespace Drupal\acquia_dam;

use Drupal\acquia_dam\Client\AcquiaDamClientFactory;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\CronInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;
use Drupal\media\MediaInterface;

/**
 * Acquia DAM cron implementation.
 */
class Cron implements CronInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Acquia Dam client factory.
   *
   * @var \Drupal\acquia_dam\Client\AcquiaDamClientFactory
   */
  protected $clientFactory;

  /**
   * The time.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * A queue dedicated to updating media items.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $mediaItemUpdateQueue;

  /**
   * A logger channel dedicated to updating media items.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $mediaItemUpdateLogger;

  /**
   * DAM asset update checker.
   *
   * @var \Drupal\acquia_dam\AssetUpdateChecker
   */
  protected $assetUpdateChecker;

  /**
   * Constructs a new Cron object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\acquia_dam\Client\AcquiaDamClientFactory $clientFactory
   *   The Acquia DAM client factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time.
   * @param \Drupal\Core\State\StateInterface $state
   *   State service.
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   Queue factory service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   Logger factory.
   * @param \Drupal\acquia_dam\AssetUpdateChecker $asset_update_checker
   *   DAM asset update checker.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    AcquiaDamClientFactory $clientFactory,
    TimeInterface $time,
    StateInterface $state,
    QueueFactory $queueFactory,
    LoggerChannelFactoryInterface $loggerChannelFactory,
    AssetUpdateChecker $asset_update_checker
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->clientFactory = $clientFactory;
    $this->time = $time;
    $this->state = $state;
    $this->mediaItemUpdateQueue = $queueFactory->get('acquia_dam_media_item_update');
    $this->mediaItemUpdateLogger = $loggerChannelFactory->get('acquia_dam.media_item_update');
    $this->assetUpdateChecker = $asset_update_checker;
  }

  /**
   * {@inheritdoc}
   */
  public function run(): bool {
    try {
      $this->fetchAndEnqueueAssets();
    }
    catch (\Exception $exception) {
      $this->mediaItemUpdateLogger->warning(
        sprintf(
          'Something went wrong during DAM cron run. Error: %s',
          $exception->getMessage()
      ));

      return FALSE;
    }

    return TRUE;
  }

  /**
   * Fetch outdated assets to update their media items.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\acquia_dam\Exception\DamClientException
   */
  protected function fetchAndEnqueueAssets() {
    $request_time = $this->time->getCurrentTime();
    $last_update = $this->state->get('acquia_dam.last_update_check', 0);
    $date = DrupalDateTime::createFromTimestamp($last_update, 'UTC');
    $formatted = $date->format('Y-m-d\TH:i:s\Z');
    $page = 0;
    $limit = 10;
    // Note: Updating asset versions and marking them as final
    // versions are not considered as updates.
    do {
      $page++;

      try {
        $response = $this->clientFactory->getSiteClient()
          ->search(
            "lastEditDate:[after $formatted]",
            '-created_date',
            $limit,
            ($page - 1) * $limit
          );
      }
      catch (\Exception $exception) {
        $this->mediaItemUpdateLogger->warning('Unable to get outdated asset list from API. Error: %message', [
          '%message' => $exception->getMessage(),
        ]);
      }

      // If there is nothing outdated, then quit.
      if (!isset($response['items']) || count($response['items']) < 1) {
        break;
      }

      if (!$active_assets = $this->filterActiveAssets($response['items'])) {
        continue;
      }

      foreach ($active_assets as $media_id => $asset_id) {
        $media_item = $this->entityTypeManager->getStorage('media')->load($media_id);
        assert($media_item instanceof MediaInterface);
        // There's no user in the cron command context, send the Anonymous UID.
        $this->assetUpdateChecker->checkAssets($media_item, 0);
      }
    } while ($response['total_count'] > $page * $limit);

    $this->state->set('acquia_dam.last_update_check', $request_time);
  }

  /**
   * Filters assets used in Drupal from the given list.
   *
   * @param array $items
   *   Items list which get updated today.
   *
   * @return array
   *   Outdated asset ids with media ids.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function filterActiveAssets(array $items): array {
    if (count($items) === 0) {
      return [];
    }
    $media_storage = $this->entityTypeManager->getStorage('media');
    $uuids = array_map(static fn (array $item) => $item['id'], $items);
    $existing_media_query = $media_storage
      ->getQuery()
      ->accessCheck(FALSE);
    $media_ids = $existing_media_query
      ->condition('acquia_dam_asset_id', $uuids, 'IN')
      ->execute();

    return array_map(
      static fn (MediaInterface $media) => $media->get('acquia_dam_asset_id')->asset_id,
      $media_storage->loadMultiple($media_ids)
    );
  }

}
