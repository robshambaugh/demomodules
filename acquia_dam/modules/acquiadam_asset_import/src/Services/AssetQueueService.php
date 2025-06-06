<?php

namespace Drupal\acquiadam_asset_import\Services;

use Drupal\acquia_dam\Client\AcquiaDamClientFactory;
use Drupal\acquia_dam\Entity\MediaSourceField;
use Drupal\acquia_dam\MediaTypeResolver;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Queue\DatabaseQueue;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * A class to add items to acquia_dam_asset_import queue.
 */
class AssetQueueService implements AssetQueueInterface {

  use StringTranslationTrait;

  /**
   * Acquia DAM client on behalf of the current user.
   *
   * @var \Drupal\acquia_dam\Client\AcquiaDamClientFactory
   */
  protected $userClientFactory;

  /**
   * The asset import queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $assetImportQueue;

  /**
   * Configuration.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $config;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Logger channel for Acquia DAM.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $damLoggerChannel;

  /**
   * The current active user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Acquia DAM media type resolver.
   *
   * @var \Drupal\acquia_dam\MediaTypeResolver
   */
  protected $mediaTypeResolver;

  /**
   * Store module-wide used constant as class attribute.
   *
   * @var string
   */
  protected $sourceFieldName;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private $database;

  /**
   * Constructs an AssetQueueService service object.
   *
   * @param \Drupal\acquia_dam\Client\AcquiaDamClientFactory $user_client_factory
   *   The Acquia DAM client factory.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   * @param \Drupal\Core\Config\ConfigFactory $config
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger_channel
   *   Logger channel instance.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current active user.
   * @param \Drupal\acquia_dam\MediaTypeResolver $media_type_resolver
   *   Acquia DAM media type resolver.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(
    AcquiaDamClientFactory $user_client_factory,
    QueueFactory $queue_factory,
    ConfigFactory $config,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelInterface $logger_channel,
    AccountInterface $current_user,
    MediaTypeResolver $media_type_resolver,
    Connection $connection,
  ) {
    $this->userClientFactory = $user_client_factory;
    $this->assetImportQueue = $queue_factory;
    $this->config = $config;
    $this->entityTypeManager = $entity_type_manager;
    $this->damLoggerChannel = $logger_channel;
    $this->currentUser = $current_user;
    $this->mediaTypeResolver = $media_type_resolver;
    $this->sourceFieldName = MediaSourceField::SOURCE_FIELD_NAME;
    $this->database = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public function addAssetsToQueue(): ?int {
    $items = 0;
    $dam_import_config = $this->config->get('acquiadam_asset_import.settings');
    // If category import is set, then start import.
    if (!empty($dam_import_config->get('categories'))) {
      $items += $this->addAssetsToQueueBySource('categories', $dam_import_config->get('categories')) ?? 0;
    }
    // If asset groups import is set, then start import.
    if (!empty($dam_import_config->get('asset_groups'))) {
      $items += $this->addAssetsToQueueBySource('asset_groups', $dam_import_config->get('asset_groups')) ?? 0;
    }

    return $items;
  }

  /**
   * Add assets to queue from Acquia DAM.
   *
   * @param string $source
   *   The filter type.
   * @param array $import_list
   *   The list of categories or asset groups to import assets from.
   *
   * @return int|null
   *   Number of items added in queue or NULL if no categories are configured.
   */
  private function addAssetsToQueueBySource(string $source, array $import_list): ?int {
    // If no categories are configured, return.
    if (empty($import_list)) {
      return NULL;
    }

    $asset_import_queue = $this->assetImportQueue->get('acquia_dam_asset_import');

    $items = $offset = 0;
    $limit = 100;
    // Start processing and queueing media item creation.
    foreach ($import_list as $source_uuid => $bundles) {
      $query = $this->buildQuery($source, $source_uuid, $bundles);
      if (!$query) {
        continue;
      }

      do {
        $response = $this->fetchAssets($query, $limit, $offset);
        // If no items are found, continue to the next source list.
        if (empty($response)) {
            continue;
        }

        $asset_ids = array_column($response['items'], 'id');
        $non_imported_assets = $this->filterAssetsToImport($asset_ids);

        $filtered_assets = array_filter(array_map(function ($asset_data) use ($bundles, $non_imported_assets) {
          return $this->filterAndFormatAsset($asset_data, $bundles, $non_imported_assets);
        }, $response['items']));

        array_walk($filtered_assets, [$asset_import_queue, 'createItem']);
        $items += count($filtered_assets);

        $offset += $limit;
      } while ($response && count($response['items']) === $limit);
    }

    return $items;
  }

  /**
   * Fetch assets from Acquia DAM based on given query and parameters.
   *
   * @param string $query
   *   Search query to fetch assets.
   * @param int $limit
   *   The limit value.
   * @param int $offset
   *   The offset value.
   *
   * @return array|null
   *   The response array or NULL on failure.
   */
  private function fetchAssets(string $query, int $limit = 100, int $offset = 0): ?array {
    try {
      $client = $this->userClientFactory->getSiteClient();
      $response = $client->search($query, '-created_date', $limit, $offset);

      // Check if the response is empty or contains no items.
      if (empty($response['items']) || $response['total_count'] === 0) {
        return NULL;
      }
      return $response;
    }
    catch (\Exception $exception) {
      $this->damLoggerChannel->warning('Unable to fetch assets of a category from Widen API. Error: %message', [
        '%message' => $exception->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Method to build search query.
   *
   * @param string $source
   *   Given source.
   * @param string $uuid
   *   The UUID of the source.
   * @param array $bundles
   *   An array of bundles to limit.
   */
  private function buildQuery(string $source, string $uuid, array $bundles = []): ?string {
    $client = $this->userClientFactory->getSiteClient();
    $query = NULL;

    switch ($source) {
      case 'categories':
        $category_name = $client->convertCategoryUuidToName($uuid);
        if ($category_name) {
          $query = "cat:($category_name)";
        }
        break;

      case 'asset_groups':
        $asset_group_name = $client->convertAssetGroupUuidToName($uuid);
        if ($asset_group_name) {
          $query = "assetgroup:($asset_group_name)";
        }
        break;

      default:
        $this->damLoggerChannel->error('Invalid source type: %source', [
            '%source' => $source,
          ]);
        return NULL;
    }

    if ($query && $bundles) {
      $filters = $this->getFilters($bundles);
      if ($filters) {
        $query .= " " . $filters;
      }
    }

    return $query;
  }

  /**
   * Filter and format asset data for queuing.
   *
   * @param array $asset_data
   *   An array of asset data.
   * @param array $bundles
   *   An array of bundles.
   * @param array|null $non_existing_assets
   *   An array of non-existing assets, NULL if otherwise.
   */
  private function filterAndFormatAsset(array $asset_data, array $bundles, ?array $non_existing_assets): ?array {
    if (!$asset_data['released_and_not_expired']) {
      return NULL;
    }

    if ($non_existing_assets && !in_array($asset_data['id'], $non_existing_assets)) {
      return NULL;
    }

    $asset_media_type = $this->mediaTypeResolver->resolve($asset_data, $bundles);
    if ($asset_media_type === NULL) {
      return NULL;
    }

    $asset_media_type_id = $asset_media_type->id();

    // Skip asset if it's already added in the queue.
    $queued_assets = $this->getQueuedAssets();
    if (in_array($asset_data['id'], $queued_assets, TRUE)) {
      return NULL;
    }

    // Return asset data for queuing.
    return [
      'target_bundle' => $asset_media_type_id,
      'file_name' => $asset_data['filename'],
      'asset_uuid' => $asset_data['id'],
      'version_id' => $asset_data['version_id'],
      'queuer_uid' => $this->currentUser->id(),
    ];
  }

  /**
   * Get uuids of already queued assets to prevent duplicates.
   */
  private function getQueuedAssets(): array {
    $items = [];
    try {
      // Fetch queued items.
      $result = $this->database->select(DatabaseQueue::TABLE_NAME, 'q')
        ->fields('q', ['data'])
        ->where('name = :name', [':name' => 'acquia_dam_asset_import'])
        ->execute()
        ->fetchCol();
    }
    catch (\Exception $e) {
      $this->damLoggerChannel->error('Failed to fetch queued items from the database. Error: %error', [
        '%error' => $e->getMessage(),
      ]);
      return $items;
    }
    $items = array_column(array_map(function ($serialized_data) {
      return unserialize($serialized_data, ['allowed_classes' => FALSE]);
    }, $result), 'asset_uuid');
    return array_filter($items);
  }

  /**
   * Returns the list of assets that are not already imported.
   *
   * @param array $asset_ids
   *   An array of asset uuids.
   */
  protected function filterAssetsToImport(array $asset_ids): ?array {
    try {
      // Query the database to get existing assets.
      $query = $this->database->select("media__" . $this->sourceFieldName, 'm')
        ->fields('m', ['acquia_dam_asset_id_asset_id'])
        ->condition('acquia_dam_asset_id_asset_id', $asset_ids, 'IN');
      $existing_uuids = $query->execute()->fetchCol();
    }
    catch (\Exception $e) {
      $this->damLoggerChannel->error('Failed to fetch existing assets from the database. Error: %error', [
        '%error' => $e->getMessage(),
      ]);
      return NULL;
    }

    // Return UUIDs that do not exist in the database.
    return array_diff($asset_ids, $existing_uuids);
  }

  /**
   * Builds and returns a filter string based on the provided media bundles.
   *
   * @param array $bundles
   *   An array of media type IDs.
   *
   * @return string
   *   A filter string for asset queries.
   */
  public function getFilters(array $bundles): string {
    $filters = [];

    foreach ($bundles as $bundle) {
      $media_type = $this->entityTypeManager->getStorage('media_type')->load($bundle);
      if ($media_type) {
        $media_source = $media_type->getSource();
        $plugin_definition = $media_source->getPluginDefinition();
        $asset_search_key = $plugin_definition['asset_search_key'] ?? null;
        $asset_search_value = $plugin_definition['asset_search_value'] ?? null;

        if ($asset_search_key && $asset_search_value) {
          $filters[$asset_search_key][] = $asset_search_value;
        }
      }
    }

    $filter_parts = [];
    foreach ($filters as $key => $values) {
      $filter_parts[] = sprintf('%s:(%s)', $key, implode(' or ', $values));
    }

    return implode(' or ', $filter_parts);
  }

}
