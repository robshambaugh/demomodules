<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\Client;

use Drupal\acquia_dam\Exception\DamClientException;
use Drupal\acquia_dam\Exception\DamConnectException;
use Drupal\acquia_dam\Exception\DamServerException;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * The client service for interacting with the DAM API.
 */
class AcquiaDamClient extends Client {

  use StringTranslationTrait;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  private $cacheBackend;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Acquia Dam Config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $acquiaDamConfig;

  /**
   * DAM logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $damLoggerChannel;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The expand parameter.
   *
   * @var string
   *   The expand parameters.
   *
   * @todo make this configurable, maybe?
   */
  protected string $expand = 'asset_properties,embeds,file_properties,metadata,metadata_info,metadata_vocabulary,security,thumbnails';

  /**
   * The source key mapping id for category and uuid for asset groups.
   *
   * @var array
   *   The source key mapping.
   *
   * @todo make this configurable, maybe?
   */
  protected array $sourceMapping = [
    'category' => 'id',
    'asset_group' => 'uuid',
  ];

  /**
   * Constructs a new Client object.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Config\ImmutableConfig $acquiaDamConfig
   *   Acquia DAM config.
   * @param array $config
   *   Client config array.
   * @param \Psr\Log\LoggerInterface $loggerChannel
   *   DAM logger channel.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(CacheBackendInterface $cache_backend, TimeInterface $time, ImmutableConfig $acquiaDamConfig, array $config, LoggerInterface $loggerChannel, MessengerInterface $messenger) {
    $this->cacheBackend = $cache_backend;
    $this->time = $time;
    $this->acquiaDamConfig = $acquiaDamConfig;
    $this->damLoggerChannel = $loggerChannel;
    $this->messenger = $messenger;

    parent::__construct($config);
  }

  /**
   * Search assets.
   *
   * @param string $query
   *   The query.
   * @param string $sort
   *   The sort.
   * @param int $limit
   *   The limit.
   * @param int $offset
   *   The offset.
   * @param string[] $expand
   *   The expands.
   *
   * @return array
   *   The results.
   *
   * @throws \Drupal\acquia_dam\Exception\DamClientException
   * @throws \Drupal\acquia_dam\Exception\DamConnectException
   * @throws \Drupal\acquia_dam\Exception\DamServerException
   */
  public function search(string $query, string $sort = '-created_date', int $limit = 100, int $offset = 0, array $expand = ['file_properties']): array {
    try {
      $query = \http_build_query([
        'query' => $query,
        'sort' => $sort,
        'limit' => $limit,
        'offset' => $offset,
        'expand' => implode(',', $expand),
      ]);
      $response = $this->get('/v2/assets/search?' . $query);
      $data = (string) $response->getBody();
    }
    catch (\Exception $e) {
      $this->throwDamException($e);
    }

    return Json::decode($data);
  }

  /**
   * Get asset data from Widen via the "Retrieve by ID" endpoint.
   *
   * @param string $asset_id
   *   The asset ID.
   * @param string $version_id
   *   (optional) The asset version ID.
   *
   * @return array|int
   *   The asset data as an array. 404 as a number if the DAM asset is deleted.
   *
   * @throws \Drupal\acquia_dam\Exception\DamClientException
   * @throws \Drupal\acquia_dam\Exception\DamServerException
   * @throws \Drupal\acquia_dam\Exception\DamConnectException
   *
   * @see https://widenv2.docs.apiary.io/#reference/assets/assets/retrieve-by-id
   */
  public function getAsset(string $asset_id, string $version_id = ''): array|int {
    $cache_key = 'asset:';
    $raw_cached_data = $this->readFromCache($cache_key . $asset_id);

    if ($raw_cached_data) {
      return Json::decode($raw_cached_data);
    }

    try {
      $response = $this->get('/v2/assets/' . $asset_id . '?expand=' . $this->expand);
    }
    catch (\Exception $e) {
      $this->throwDamException($e);
    }

    assert($response instanceof ResponseInterface);
    $raw_data = (string) $response->getBody();
    $this->writeToCache($cache_key, $raw_data, $asset_id, $version_id);

    return Json::decode($raw_data);
  }

  /**
   * Get all versions of assets.
   *
   * Get the assets all versions from Widen via
   * the "Retrieve by ID and version" endpoint.
   *
   * @param string $asset_id
   *   The asset ID.
   *
   * @return array|null
   *   The asset data, or null if not found.
   *
   * @throws \Drupal\acquia_dam\Exception\DamClientException
   * @throws \Drupal\acquia_dam\Exception\DamServerException
   * @throws \Drupal\acquia_dam\Exception\DamConnectException
   *
   * @see https://widenv2.docs.apiary.io/#reference/assets/assets/retrieve-by-id-and-version
   */
  public function getAssetVersions(string $asset_id): ?array {
    try {
      $response = $this->get('/v2/assets/' . $asset_id . '/versions?expand=' . $this->expand);
    }
    catch (\Exception $e) {
      $this->throwDamException($e);
    }

    assert($response instanceof ResponseInterface);
    $raw_data = (string) $response->getBody();

    return Json::decode($raw_data)['items'] ?: NULL;
  }

  /**
   * Gets the version list for an asset.
   *
   * @param string $id
   *   The asset ID.
   *
   * @return array
   *   The asset's version data.
   *
   * @throws \Drupal\acquia_dam\Exception\DamClientException
   * @throws \Drupal\acquia_dam\Exception\DamServerException
   * @throws \Drupal\acquia_dam\Exception\DamConnectException
   *
   * @deprecated in acquia_dam:1.0.14 and is removed from acquia_dam:1.2.0.
   * The getAssetVersionList method of V1 API will be removed, we recommend
   * using the getAssetVersions method instead with V2 API.
   * @see https://www.drupal.org/project/acquia_dam/issues/3462318
   */
  public function getAssetVersionList(string $id): array {
    @trigger_error('AcquiaDamClient::getAssetVersionList() is deprecated in acquia_dam:1.0.14 and is removed from acquia_dam:1.2.0. The getAssetVersionList method of V1 API will be removed, we recommend using the getAssetLatestVersion method instead with V2 API. See https://www.drupal.org/project/acquia_dam/issues/3462318', E_USER_DEPRECATED);
    try {
      $domain = $this->acquiaDamConfig->get('domain');
      $response = $this->get("https://$domain/api/rest/asset/uuid/$id/assetversions");
      $data = (string) $response->getBody();
    }
    catch (\Exception $e) {
      $this->throwDamException($e);
    }

    return Json::decode($data);
  }

  /**
   * Gets all assets within a given category.
   *
   * @param string $category_uuid
   *   Widen's UUID of the category itself.
   * @param string $filters
   *   The filters.
   *
   * @return array
   *   The results.
   */
  public function getAssetsInCategory(string $category_uuid, string $filters = ''): array {
    $category_name = $this->convertCategoryUuidToName($category_uuid);

    return $this->search("cat:($category_name) $filters");
  }

  /**
   * Gets all assets within a given asset group.
   *
   * @param string $asset_group_uuid
   *   Widen's UUID of the asset group itself.
   * @param string $filters
   *   The filters.
   *
   * @return array
   *   The results.
   */
  public function getAssetsInAssetGroup(string $asset_group_uuid, string $filters = '') {
    $asset_group_name = $this->convertAssetGroupUuidToName($asset_group_uuid);

    return $this->search("assetgroup:($asset_group_name) $filters");
  }

  /**
   * Sends an integration link create request to DAM.
   *
   * @param string $asset_id
   *   DAM asset id.
   * @param string $url
   *   Url to set for the integration link.
   * @param string $description
   *   Description for the integration link.
   *
   * @return array|null
   *   Response body, or null.
   *
   * @throws \Drupal\acquia_dam\Exception\DamClientException
   * @throws \Drupal\acquia_dam\Exception\DamServerException
   * @throws \Drupal\acquia_dam\Exception\DamConnectException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function registerIntegrationLink(string $asset_id, string $url, string $description): ?array {
    try {
      $domain = $this->acquiaDamConfig->get('domain');
      $response = $this->request('POST', "https://$domain/api/rest/integrationlink",
        [
          'json' => [
            'assetUuid' => $asset_id,
            'description' => $description,
            'url' => $url,
          ],
        ]
      );

      $data = (string) $response->getBody();
    }
    catch (\Exception $e) {
      $this->throwDamException($e);
    }

    return Json::decode($data);
  }

  /**
   * Removes integration link from DAM.
   *
   * @param string $intlink_uuid
   *   Integration link uuid.
   *
   * @throws \Drupal\acquia_dam\Exception\DamClientException
   * @throws \Drupal\acquia_dam\Exception\DamConnectException
   * @throws \Drupal\acquia_dam\Exception\DamServerException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function removeIntegrationLink(string $intlink_uuid): void {
    $domain = $this->acquiaDamConfig->get('domain');

    try {
      $this->request('DELETE', "https://$domain/api/rest/integrationlink/$intlink_uuid");
    }
    catch (\Exception $e) {
      $this->throwDamException($e);
    }
  }

  /**
   * Fetch the metadata according the field type.
   *
   * @param string $field_types
   *   Field type of the metadata field.
   *
   * @return array|null
   *   List of the field available under a particular field type.
   *
   * @throws \Drupal\acquia_dam\Exception\DamClientException
   * @throws \Drupal\acquia_dam\Exception\DamServerException
   * @throws \Drupal\acquia_dam\Exception\DamConnectException
   */
  public function getDisplayKeys(string $field_types): ?array {
    try {
      $response = $this->get('/v2/metadata/fields/viewable?field_types=' . $field_types);
      $data = (string) $response->getBody();
    }
    catch (\Exception $e) {
      $this->throwDamException($e);
    }

    return Json::decode($data);
  }

  /**
   * Fetch the vocabulary of a particular display key.
   *
   * @param string $display_key
   *   Display key of the field.
   *
   * @return array|null
   *   List of the available vocabulary items under the display key.
   *
   * @throws \Drupal\acquia_dam\Exception\DamClientException
   * @throws \Drupal\acquia_dam\Exception\DamServerException
   * @throws \Drupal\acquia_dam\Exception\DamConnectException
   */
  public function getDisplayKeyVocabulary(string $display_key): ?array {
    try {
      $response = $this->get("/v2/metadata/$display_key/vocabulary");
      $data = (string) $response->getBody();
    }
    catch (\Exception $e) {
      $this->throwDamException($e);
    }

    return Json::decode($data);
  }

  /**
   * Fetches category information.
   *
   * @param string $category_name_encoded
   *   Human-readable label of the category in Widen rawurlencode()-d.
   *
   * @return array|null
   *   List of the category items or NULL if no categories are available.
   *
   * @throws \Drupal\acquia_dam\Exception\DamClientException
   * @throws \Drupal\acquia_dam\Exception\DamServerException
   * @throws \Drupal\acquia_dam\Exception\DamConnectException
   */
  public function getCategories(string $category_name_encoded = ''): ?array {
    try {
      $path = $category_name_encoded ? '/v2/categories/' . $category_name_encoded : '/v2/categories';
      $response = $this->get($path);
      $data = (string) $response->getBody();
    }
    catch (\Exception $e) {
      $this->throwDamException($e);
    }

    return Json::decode($data);
  }

  /**
   * Fetch asset groups information.
   *
   * @return array|null
   *   List of the asset groups.
   *
   * @throws \Drupal\acquia_dam\Exception\DamClientException
   * @throws \Drupal\acquia_dam\Exception\DamServerException
   * @throws \Drupal\acquia_dam\Exception\DamConnectException
   */
  public function getAssetGroups(): ?array {
    try {
      $path = '/v2/assets/assetgroups';
      $response = $this->get($path);
      $data = (string) $response->getBody();
    }
    catch (\Exception $e) {
      $this->throwDamException($e);
    }

    return Json::decode($data);
  }

  /**
   * Converts a category name to its corresponding UUID.
   *
   * @param string $name
   *   The category name in Widen. Method tries to guess if it left URL
   *   encoded, then decodes.
   * @param bool $all_data
   *   (optional) Flag indicating whether the entire information set received
   *   should be returned.
   *
   * @return string|array|null
   *   The UUID of the given category.
   */
  public function convertCategoryNameToUuid(string $name, bool $all_data = FALSE): string|array|null {
    $all_categories = $this->getCategories();

    return $this->convertNameToUuid('category', $all_categories, $name, $all_data = FALSE);
  }

  /**
   * Converts a asset group name to its corresponding UUID.
   *
   * @param string $name
   *   The asset group name in Widen. Method tries to guess if it left URL
   *   encoded, then decodes.
   * @param bool $all_data
   *   (optional) Flag indicating whether the entire information set received
   *   should be returned.
   *
   * @return string|array|null
   *   The UUID of the given asset group.
   */
  public function convertAssetGroupNameToUuid(string $name, bool $all_data = FALSE): string|array|null {
    $all_asset_groups = $this->getAssetGroups();

    return $this->convertNameToUuid('asset_group', $all_asset_groups, $name, $all_data);
  }

  /**
   * Converts a given list name to its corresponding UUID.
   *
   * @param string $source
   *   The source type.
   * @param array $list
   *   The list of asset group or category.
   * @param string $name
   *   The asset group or category name in Widen. Method tries to
   *   guess if it left URL encoded, then decodes.
   * @param bool $all_data
   *   (optional) Flag indicating whether the entire information set received
   *   should be returned.
   *
   * @return string|array|null
   *   The UUID of the given asset group or category.
   */
  private function convertNameToUuid(string $source, array $list, string $name, bool $all_data = FALSE): string|array|null {
    // Probably the input is left encoded yet but plain-text is required here.
    if (preg_match('/%[0-9A-F]{2}/i', $name)) {
      $name = rawurldecode($name);
    }

    foreach ($list['items'] as $data) {
      if (!isset($data['name']) || $data['name'] !== $name) {
        continue;
      }

      if ($all_data) {
        return $data;
      }

      // Return the identifier or null if not available.
      return $data[$this->sourceMapping[$source]] ?? null;
    }

    return NULL;
  }

  /**
   * Converts a category UUID to its corresponding name.
   *
   * @param string $uuid
   *   UUID of the category in Widen.
   *
   * @return string|null
   *   The human label of the category.
   */
  public function convertCategoryUuidToName(string $uuid): ?string {
    $all_categories = $this->getCategories();

    return $this->convertUuidToName('category', $all_categories, $uuid);
  }

  /**
   * Converts a asset group UUID to its corresponding name.
   *
   * @param string $uuid
   *   UUID of the asset group in Widen.
   *
   * @return string|null
   *   The human label of the asset group.
   */
  public function convertAssetGroupUuidToName(string $uuid): ?string {
    $all_asset_groups = $this->getAssetGroups();

    return $this->convertUuidToName('asset_group', $all_asset_groups, $uuid);
  }

  /**
   * Converts a asset group UUID to its corresponding name.
   *
   * @param string $uuid
   *   UUID of the asset group in Widen.
   *
   * @return string|null
   *   The human label of the asset group.
   */
  public function convertUuidToName(string $source, array $list, string $uuid): ?string {
    foreach ($list['items'] as $data) {
      if (!isset($data[$this->sourceMapping[$source]]) || $data[$this->sourceMapping[$source]] !== $uuid) {
        continue;
      }

      return $data['name'] ?: NULL;
    }

    return NULL;
  }

  /**
   * Throws specific exception.
   *
   * @param \Exception $exception
   *   Exception that occurred during request.
   *
   * @throws \Drupal\acquia_dam\Exception\DamClientException
   * @throws \Drupal\acquia_dam\Exception\DamConnectException
   * @throws \Drupal\acquia_dam\Exception\DamServerException
   */
  protected function throwDamException(\Exception $exception): void {
    $this->damLoggerChannel->error($exception->getMessage());

    if ($exception instanceof ClientException) {
      $response = $exception->getResponse();
      $status = $response->getStatusCode();
      $message = "API responded with status: $status. If the issue persist contact the site admin. Error message: {$exception->getMessage()}";

      if ($status === 401) {
        $message = 'Unable to finish request due to authorization errors. If the issue persist contact the site admin.';
      }
      if ($status === 408) {
        $message = 'Request timed out while trying to connect DAM API. If the issue persist contact the site admin.';
      }

      throw new DamClientException($message, $status, $exception);
    }

    if ($exception instanceof ConnectException) {
      throw new DamConnectException('Unable to complete network request.', $exception->getCode(), $exception);
    }

    throw new DamServerException($exception->getMessage(), $exception->getCode(), $exception);
  }

  /**
   * Writes asset data to the cache.
   *
   * @param string $cache_key
   *   The type of data being stored.
   * @param string $raw_data
   *   The unserialized JSON data to store.
   * @param string $asset_id
   *   The asset ID.
   * @param string $version_id
   *   (optional) The asset version ID.
   */
  private function writeToCache(string $cache_key, string $raw_data, string $asset_id, string $version_id = ''): void {
    $decoded_data = Json::decode($raw_data);

    // Cache expiration time should be the same as the asset thumbnail
    // expiration time sent by remote system, otherwise thumbnail URLs will
    // broke. Just to play on the safe side we calculate a default value first.
    $expiration_date = new \DateTime('now + 10 hours');

    // Not affecting media item thumbnails, only reading their Widen-dictated
    // expiration date to set for caching.
    if (isset($decoded_data['thumbnails'])) {
      $first_thumbnail = reset($decoded_data['thumbnails']);
      if (isset($first_thumbnail['valid_until'])) {
        $expiration_date = \DateTime::createFromFormat(\DateTimeInterface::ISO8601, $first_thumbnail['valid_until']);
      }
    }

    // Then prepare some cache tag variations too.
    $cache_tags = [
      'acquia-dam-asset',
      'acquia-dam-asset:' . $asset_id,
    ];

    if ($version_id !== '') {
      $cache_tags[] = 'acquia-dam-asset:' . $asset_id . ':' . $version_id;
    }

    // Finally store them in.
    $this->cacheBackend->set(
      $cache_key . $asset_id,
      $raw_data,
      $expiration_date->getTimestamp(),
      $cache_tags,
    );
  }

  /**
   * Reads asset data from the cache.
   *
   * @param string $cache_key
   *   The cache key.
   *
   * @return string|null
   *   The cached raw (unserialized JSON) data, or null if not found.
   */
  private function readFromCache(string $cache_key): ?string {
    $cached = $this->cacheBackend->get($cache_key);

    if ($cached !== FALSE && property_exists($cached, 'data')) {
      return $cached->data;
    }

    return NULL;
  }

}
