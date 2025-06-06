<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\Plugin\media\Source;

use Drupal\acquia_dam\Entity\MediaSourceField;
use Drupal\acquia_dam\Plugin\Field\FieldType\AssetItem;
use Drupal\acquia_dam\Plugin\media\acquia_dam\MediaSourceTypeInterface;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Config\Config;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceBase;
use Drupal\media\MediaTypeInterface;
use GuzzleHttp\Exception\TransferException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Media source plugin for DAM assets.
 *
 * @MediaSource(
 *   id = "acquia_dam_asset",
 *   label = @Translation("DAM asset"),
 *   description = @Translation("Reference a media asset from the Acquia DAM."),
 *   allowed_field_types = {"acquia_dam_asset"},
 *   default_thumbnail_filename = "no-thumbnail.png",
 *   deriver = "Drupal\acquia_dam\Plugin\media\Source\AssetDeriver"
 * )
 */
final class Asset extends MediaSourceBase {

  /**
   * Asset storage.
   *
   * @var array
   */
  protected $assetData = [];

  /**
   * The DAM client factory.
   *
   * @var \Drupal\acquia_dam\Client\AcquiaDamClientFactory
   */
  private $clientFactory;

  /**
   * The DAM asset version resolver.
   *
   * @var \Drupal\acquia_dam\AssetVersionResolver
   */
  protected $assetVersionResolver;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  private $httpClient;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  private $fileSystem;

  /**
   * The managed file tracking service.
   *
   * @var \Drupal\file\FileRepositoryInterface
   */
  private $fileRepository;

  /**
   * Logger channel interface.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  private $damLoggerChannel;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Acquia DAM module config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $moduleConfig;

  /**
   * A service helping to handle managed files of assets.
   *
   * @var \Drupal\acquia_dam\AssetFileEntityHelper
   */
  protected $assetFileHelper;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The route provider service.
   *
   * @var \Drupal\Core\Routing\RouteProvider
   */
  protected $routeProvider;

  /**
   * Derivative Plugin for Media Source data.
   *
   * @var MediaSourceTypeInterface
   */
  protected $derivativePlugin;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->derivativePlugin = new $plugin_definition['asset_class']($configuration, $plugin_definition['id'], $plugin_definition);
    $instance->clientFactory = $container->get('acquia_dam.client.factory');
    $instance->assetVersionResolver = $container->get('acquia_dam.asset_version_resolver');
    $instance->httpClient = $container->get('http_client');
    $instance->fileSystem = $container->get('file_system');
    $instance->fileRepository = $container->get('file.repository');
    $instance->damLoggerChannel = $container->get('logger.channel.acquia_dam');
    $instance->assetFileHelper = $container->get('acquia_dam.asset_file_helper');
    $instance->routeMatch = $container->get('current_route_match');
    $instance->routeProvider = $container->get('router.route_provider');
    $instance->configFactory = $container->get('config.factory');
    $instance->moduleConfig = $container->get('config.factory')->get('acquia_dam.settings');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'source_field' => '',
      'download_assets' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    parent::setConfiguration($configuration);
    if (isset($this->derivativePlugin)) {
      $this->derivativePlugin->setConfiguration($configuration);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadataAttributes(): array {
    $attributes = [
      'created_date' => $this->t('Created date'),
      'filename' => $this->t('File name'),
      'size' => $this->t('Size'),
      'last_update_date' => $this->t('Last updated date'),
      'file_upload_date' => $this->t('File upload date'),
      'expiration_date' => $this->t('Expiration date'),
      'release_date' => $this->t('Release date'),
      'deleted_date' => $this->t('Deleted date'),
      'format_type' => $this->t('Format Type'),
      'format' => $this->t('Format'),
    ];
    if ($this->moduleConfig->get('allowed_metadata')) {
      return array_merge($attributes, $this->moduleConfig->get('allowed_metadata'));
    }
    return $attributes;
  }

  /**
   * {@inheritdoc}
   *
   * Disable PHPMD.CyclomaticComplexity due to the switch statement, which is
   * a pattern used in all the implementations of this method.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   *
   * @throws \Drupal\acquia_dam\Exception\DamServerException
   */
  public function getMetadata(MediaInterface $media, $attribute_name) {
    [$asset_id, $version_id, $external_id] = array_values($this->getSourceFieldValue($media));

    if (empty($asset_id)) {
      return NULL;
    }

    if (empty($version_id)) {
      $version_id = $this->assetVersionResolver->getFinalizedVersion($asset_id);
    }

    // If the requested attribute is already known at this point let's fulfill.
    switch ($attribute_name) {
      case 'id':
      case 'asset_id':
        return $asset_id;

      case 'version_id':
        return $version_id;

      // If known, return it. If not, then its value will be assigned later.
      case 'external_id':
        if ($external_id) {
          return $external_id;
        }
    }

    $asset = $this->assetData;
    if ($asset === []) {
      try {
        $asset = $this->clientFactory->getSiteClient()->getAsset($asset_id, $version_id);
      }
      catch (\Exception $exception) {
        $this->damLoggerChannel->error(sprintf(
            'Following error occurred while trying to get asset from dam. Asset: %s, error: %s',
            $asset_id,
            $exception->getMessage()
          )
        );
        return NULL;
      }
    }

    // If the DAM asset has gone, then better to cut off the method call chain.
    if (isset($asset['exception_code'])) {
      return NULL;
    }

    if (empty($external_id)) {
      $external_id = $asset['external_id'];
    }

    // The field mapping is used by some attributes to transform values for
    // better storage compatibility.
    $field_map = $media->bundle->entity->getFieldMap();
    $field_definition = NULL;

    if (isset($field_map[$attribute_name])) {
      $field_definition = $media->getFieldDefinition($field_map[$attribute_name]);
    }

    switch ($attribute_name) {
      // By till now it has a value for sure, no condition is needed.
      case 'external_id':
        return $external_id;

      case 'default_name':
        return $asset['filename'];

      case 'filename':
        return $this->getValidFilename($asset);

      case 'size':
        return $asset['file_properties']['size_in_kbytes'] * 1024;

      case 'thumbnail_uri':
        return $this->getLocalThumbnailUri($asset_id, $version_id, $external_id);

      case 'thumbnail_uri_remote':
        return $this->getRemoteThumbnailUri($external_id) ?: $asset['thumbnails']['600px']['url'];

      case 'file':
        return $this->assetFileHelper->downloadFile(
          $media,
          $this,
          $asset,
          $asset_id
        );

      case 'embeds':
        return $asset['embeds'];

      case 'image_properties':
        return $asset['file_properties']['image_properties'];

      case 'format_type':
        return $asset['file_properties']['format_type'];

      case 'format':
        return $asset['file_properties']['format'];

      case 'video_properties':
        return $asset['file_properties']['video_properties'];

      case 'created_date':
      case 'last_update_date':
      case 'file_upload_date':
      case 'deleted_date':
        return $asset[$attribute_name] ? $this->transformMetadataForStorage($asset[$attribute_name], 'datetime', $field_definition) : NULL;

      case 'expiration_date':
      case 'release_date':
        return $asset['security'][$attribute_name] ? $this->transformMetadataForStorage($asset['security'][$attribute_name], 'datetime', $field_definition) : NULL;

      case 'is_asset_available':
        return $asset['released_and_not_expired'];

      default:
        if (!array_key_exists($attribute_name, $asset['metadata']['fields'])) {
          return NULL;
        }
        $value = $asset['metadata']['fields'][$attribute_name];
        if (count($asset['metadata']['fields'][$attribute_name]) === 0) {
          return NULL;
        }

        $is_multiple = $field_definition && $field_definition->getFieldStorageDefinition()->isMultiple();

        if (isset($asset['metadata_info']) && $field_definition !== NULL) {
          $metadata_type = self::getMetadataFieldType($asset['metadata_info'], $attribute_name);
          if ($metadata_type !== NULL) {
            $value = $this->transformMetadataForStorage($value, $metadata_type, $field_definition);
          }
        }
        return $is_multiple ? $value : implode(', ', $value);
    }
  }

  /**
   * Transforms metadata values for field storage.
   *
   * @param string|array $value
   *   The metadata's value.
   * @param string $metadata_type
   *   The metadata's type.
   * @param \Drupal\Core\Field\FieldDefinitionInterface|null $field_definition
   *   The field definition, if metadata is mapped to a field.
   *
   * @return string|array
   *   The transformed metadata values.
   */
  private function transformMetadataForStorage($value, string $metadata_type, ?FieldDefinitionInterface $field_definition) {
    if ($field_definition === NULL) {
      return $value;
    }
    $field_type = $field_definition->getType();
    $field_storage_definition = $field_definition->getFieldStorageDefinition();

    if ($field_type === 'string') {
      $max_length = $field_definition->getSetting('max_length');
      if (is_array($value)) {
        $value = array_map(function ($value) use ($max_length) {
          return $this->formatStringValues($value, $max_length);
        }, $value);
      }
      else {
        $value = $this->formatStringValues($value, $max_length);
      }
    }

    if (in_array($metadata_type, ['date', 'datetime'])) {
      $source_format = $metadata_type === 'date' ? 'Y-m-d' : \DateTimeInterface::ATOM;
      if ($field_type === 'datetime') {
        $datetime_type = $field_storage_definition->getSetting('datetime_type');
        $format = $datetime_type === DateTimeItem::DATETIME_TYPE_DATETIME ? DateTimeItemInterface::DATETIME_STORAGE_FORMAT : DateTimeItemInterface::DATE_STORAGE_FORMAT;
      }
      elseif ($field_type === 'timestamp') {
        $format = 'U';
      }
      else {
        return $value;
      }

      if (is_array($value)) {
        $value = array_map(function ($value) use ($source_format, $format) {
          return $this->formatDateForDateField($value, $source_format, $format);
        }, $value);
      }
      else {
        $value = $this->formatDateForDateField($value, $source_format, $format);
      }
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['source_field']['#default_value'] = MediaSourceField::SOURCE_FIELD_NAME;
    $form['source_field']['#disabled'] = TRUE;
    $description = $this->t('Please enable this option if you wish to serve assets from the Drupal file system. Otherwise, leave it unchecked to serve assets from the Widen CDN URL.');

    $form['download_assets'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Download and sync assets'),
      '#description' => $description,
      '#default_value' => $this->configuration['download_assets'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceFieldValue(MediaInterface $media): array {
    $items = $media->get($this->getSourceFieldName());
    if ($items->isEmpty()) {
      return [
        'asset_id' => '',
        'version_id' => '',
        'external_id' => '',
      ];
    }
    $field_item = $items->first();
    assert($field_item instanceof AssetItem);
    // The ::getValue method on FieldItem only returns an array where properties
    // have been initiated with a value. It does not return properties that have
    // no value. Using ::toArray ensures the result has `version_id`, which may
    // be empty when a media item is first saved.
    return $field_item->toArray();
  }

  /**
   * {@inheritdoc}
   */
  public function createSourceField(MediaTypeInterface $type) {
    return $this->getSourceFieldDefinition($type);
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceFieldDefinition(MediaTypeInterface $type) {
    return MediaSourceField::getFieldDefinition('media', $type->id(), $type->label());
  }

  /**
   * {@inheritdoc}
   */
  protected function getSourceFieldName() {
    return MediaSourceField::SOURCE_FIELD_NAME;
  }

  /**
   * Returns the remote URI of the thumbnail image for the given asset.
   *
   * The proper embed code for the remote thumbnail image is generated by
   * EmbedCodeFactory.
   *
   * @param string $external_id
   *   The external ID of the asset.
   *
   * @return string|null
   *   The remote URI to the thumbnail file. NULL if any problem occurred.
   */
  protected function getRemoteThumbnailUri(string $external_id) {
    $remote_hostname = $this->moduleConfig->get('domain');
    preg_match('/^([^\.]+)\.(widencollective|acquiadam)\.com/', $remote_hostname, $instance_name);

    if (!$instance_name[1]) {
      $this->damLoggerChannel->error(sprintf(
        'Unable to determine the name of the Widen instance from the remote hostname "%s"',
        $remote_hostname,
      ));

      return NULL;
    }

    // Although it has nothing to do with the GD Toolkit but it might better to
    // rely on a configurable value rather than hardcoding one instead.
    $jpeg_quality = $this->configFactory->get('system.image.gd')->get('jpeg_quality') ?: 90;

    return 'https://embed.widencdn.net/img/' . $instance_name[1] . '/' . $external_id . '/150px@2x/?q=' . $jpeg_quality
      // Enforce Widen CDN's caching logic to always return the freshest raster
      // image data.
      . '&' . rand(1000, 9999);
  }

  /**
   * Handles local thumbnail images in general.
   *
   * Returns existing file URIs, or saves the remote image otherwise. When
   * doing so, also flushes off all the other versions' thumbnails for the
   * given asset to ensure always the most recent one is being stored only.
   *
   * @param string $asset_id
   *   The asset UUID.
   * @param string $version_id
   *   The asset version UUID.
   * @param string $external_id
   *   The external ID of the asset.
   *
   * @return string|null
   *   The local URI to the thumbnail file. NULL if any problem occurred.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *
   * @see \Drupal\media\Plugin\media\Source\OEmbed::getLocalThumbnailUri
   */
  public function getLocalThumbnailUri(string $asset_id, string $version_id, string $external_id): ?string {
    $directory = 'public://acquia_dam_thumbnails/' . $asset_id;

    // If there is an existing local thumbnail for the version, return it.
    if ($this->fileSystem->prepareDirectory($directory)) {
      $files = $this->fileSystem->scanDirectory($directory, "/^$version_id\..*/");

      if (count($files) > 0) {
        return reset($files)->uri;
      }
    }

    $remote_thumbnail_url = $this->getRemoteThumbnailUri($external_id);

    try {
      $response = $this->httpClient->request('GET', $remote_thumbnail_url);
    }
    catch (TransferException $e) {
      $this->damLoggerChannel->error(sprintf('Unable to fetch thumbnail image data from %s: %s %s',
        $remote_thumbnail_url,
        get_class($e),
        $e->getMessage()
      ));

      return NULL;
    }

    if ($response->getStatusCode() !== 200) {
      $this->damLoggerChannel->error(sprintf('Widen API responded with a non-200 status code at "%s"',
        $remote_thumbnail_url
      ));

      return NULL;
    }

    $local_thumbnail_uri = $directory . DIRECTORY_SEPARATOR . $version_id . '.png';

    if (!$this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
      $this->damLoggerChannel->error(sprintf(
        'Unable to prepare directory for the thumbnail of asset "%s"',
        $asset_id,
      ));

      return NULL;
    }

    // Delete the local thumbnail images of all other versions for this asset.
    $files_to_delete = $this->fileSystem->scanDirectory($directory, '/[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\.\S{3,}/', ['nomask' => "/$version_id.png/"]);

    if (count($files_to_delete) > 0) {
      foreach ($files_to_delete as $file) {
        if ($file_delete = $this->fileRepository->loadByUri($file->uri)) {
          $file_delete->delete();
        }
      }
    }

    // w.r.t CR - https://www.drupal.org/node/3426517
    if (version_compare(\Drupal::VERSION, '10.3.0', '>=')) {
      $fileExists = FileExists::Replace;
    }
    else {
      // @phpstan-ignore-next-line
      $fileExists = FileSystemInterface::EXISTS_REPLACE;
    }

    try {
      $this->fileSystem->saveData((string) $response->getBody(), $local_thumbnail_uri, $fileExists);
    }
    catch (FileException $e) {
      $this->damLoggerChannel->error(sprintf(
        'Unable to create thumbnail file at %s: %s %s',
        $local_thumbnail_uri,
        get_class($e),
        $e->getMessage()
      ));

      return NULL;
    }

    return $local_thumbnail_uri;
  }

  /**
   * Formats date coming from DAM to save into storage.
   *
   * @param string $value
   *   Date string coming from API in ISO8601 format.
   * @param string $source_format
   *   The source date time format.
   * @param string $format
   *   The date time format.
   *
   * @return string
   *   The formatted date.
   */
  protected function formatDateForDateField(string $value, string $source_format, string $format): string {
    $system_date = $this->configFactory->get('system.date');

    try {
      $date = DrupalDateTime::createFromFormat(
        $source_format,
        $value,
        new \DateTimeZone($system_date->get('timezone.default')),
        [
          // We do not want to validate the format. Incoming ISO8601 has the Z
          // timezone offset, while PHP may return +00:00 when comparing the
          // output with the `P` option.
          'validate_format' => FALSE,
        ]
      );
      // If the format did not include an explicit time portion, then the time
      // will be set from the current time instead. Provide a default for
      // consistent values.
      if (!str_contains($value, 'T')) {
        $date->setDefaultDateTime();
      }
    }
    catch (\InvalidArgumentException | \UnexpectedValueException $exception) {
      return $value;
    }
    return $date->format($format);
  }

  /**
   * Cuts last part of the string if it is longer than allowed.
   *
   * @param string $value
   *   String value.
   * @param int $max_length
   *   Allowed max length on field.
   *
   * @return string
   *   Formatted string.
   */
  protected function formatStringValues(string $value, int $max_length): string {
    if ($max_length < strlen($value)) {
      return Unicode::truncate($value, $max_length - 3, TRUE, TRUE);
    }
    return $value;
  }

  /**
   * Sets the asset data.
   *
   * @param array $data
   *   The asset data.
   */
  public function setAssetData(array $data) {
    $this->assetData = $data;
  }

  /**
   * Utility method to bridge over conceptual differences.
   *
   * Widen's liberal asset naming requirements do not guarantee that
   * `$asset_data['filename']` always contains a valid filename. However, some
   * of our operations do require such one, thus we play on the safe side.
   *
   * @param array $asset_data
   *   The asset data.
   *
   * @return string|null
   *   A valid filename together with its file extension or NULL on failure.
   *
   * @throws \RuntimeException
   */
  public function getValidFilename(array $asset_data): ?string {
    $result = NULL;

    if (!$asset_data) {
      return $result;
    }

    $validity_pattern = '/^[a-z0-9](?:[a-z0-9._-]*[a-z0-9])?\.[a-z0-9]{2,}$/';

    // First rely on the human label of the asset.
    if (isset($asset_data['filename'])) {
      $result = preg_replace('/\s/', '-', strtolower($asset_data['filename']));

      // If it's entirely valid, then use that as it is.
      if (preg_match($validity_pattern, $result) === 1) {
        return $result;
      }

      // If not, then let's try to obtain from the embed URL.
      if (isset($asset_data['embeds']['templated']['url'])) {
        $embed_url_parts = parse_url($asset_data['embeds']['templated']['url']);

        if ($path = $embed_url_parts['path']) {
          $result = strtolower(pathinfo($path, PATHINFO_FILENAME) . '.' . pathinfo($path, PATHINFO_EXTENSION));

          if (preg_match($validity_pattern, $result) === 1) {
            return $result;
          }
        }
      }

      // From here we need to compose an output from different sources.
      $result_parts = [];

      // If the label fits only to be a filename but file extension is invalid.
      if (preg_match('/^([a-z0-9](?:[a-z0-9._-]*[a-z0-9]){2,})\./', $result, $matches) === 1) {
        $result_parts['name'] = $matches[1];
      }

      // Continue searching if it's still undetermined.
      if (!isset($result_parts['name'])) {

        // As a last resort, fallback to external ID as name part.
        if (isset($asset_data['external_id'])) {
          $result_parts['name'] = $asset_data['external_id'];
        }
      }

      // If no file extension but the asset label fits to be one, then use it.
      unset($matches);
      if (preg_match('/\.([a-z0-9_-]{2,})$/', $asset_data['filename'], $matches) === 1) {
        $result_parts['extension'] = $matches[1];
      }

      // As last resort fallback to the file format which might also work.
      elseif (!isset($result_parts['extension']) && isset($asset_data['file_properties']['format'])) {
        $result_parts['extension'] = strtolower($asset_data['file_properties']['format']);
      }

      $result = implode('.', $result_parts);

      if (preg_match($validity_pattern, $result) === 1) {
        return $result;
      }
    }

    // Otherwise leave some traces in Watchdog about the failure.
    if (isset($asset_data['id'])) {

      // @todo Needed only to make `AssetTest::testGetValidFilename()` to pass.
      if ($this->damLoggerChannel) {
        $this->damLoggerChannel->error($this->t('Unable to deduce a valid filename for asset %asset_id.', [
          '%asset_id' => $asset_data['id'],
        ]));
      }

      return $result;
    }

    // Reaching this point is fatal.
    throw new \RuntimeException('Insufficient amount of asset data was received to deduce a valid filename.');
  }

  /**
   * Gets the field type for a metadata field.
   *
   * @param array $metadata_info
   *   The asset's metadata info.
   * @param string $field_name
   *   The metadata field name.
   *
   * @return string|null
   *   The field type.
   */
  private static function getMetadataFieldType(array $metadata_info, string $field_name): ?string {
    $mapping = [];
    foreach ($metadata_info['field_set_fields'] as $field) {
      $mapping[$field['key']] = $field['type'];
    }
    return $mapping[$field_name] ?? NULL;
  }

  /**
   * Get the active field name from the derivative plugin.
   *
   * @return string
   *   The field name.
   */
  public function getActiveFieldName(): string {
    return $this->derivativePlugin->getActiveFieldName();
  }

  /**
   * Get field formatter settings for the specified field name.
   *
   * @param string $managed_field_name
   *   Name of the managed field (asset, image, file, etc).
   * @return array
   *   The field configuration.
   */
  public function getFormatterSettings($managed_field_name): array {
    return $this->derivativePlugin->getFormatter($managed_field_name);
  }

  /**
   * Get the active local field name from the derivative plugin.
   *
   * @return string
   *   The field name.
   */
  public function getLocalFileAssetField(): string {
    return $this->derivativePlugin->getLocalFileAssetField();
  }

  /**
   * Helper function to swap asset fields for a provided view display.
   *
   * @param \Drupal\Core\Config\Config|\Drupal\Core\Entity\Display\EntityViewDisplayInterface $view_display
   *   Current view display to swap fields.
   * @param string $existing_field_name
   *   Existing field to disable in the display.
   * @param string $active_field_name
   *   Name of the (newly) active field.
   * @param array $active_field
   *   Settings for the (newly) active field.
   *
   * @return Config|EntityViewDisplay
   *   This method can be used for both Config objects and Entity View Displays.
   */
  public function swapAssetFields(Config|EntityViewDisplay $view_display, string $existing_field_name, string $active_field_name, array $active_field): Config|EntityViewDisplay {
    return $this->derivativePlugin->swapAssetFields($view_display, $existing_field_name, $active_field_name, $active_field);
  }

  /**
   * Helper method to update default and media library view display.
   *
   * @param \Drupal\Core\Entity\Display\EntityViewDisplayInterface $view_display
   *   Current view display to swap fields.
   *
   * @return EntityViewDisplay
   *   This method can be used for both Config objects and Entity View Displays.
   */
  public function updateViewDisplay(EntityViewDisplay $view_display): EntityViewDisplay {
    return $this->derivativePlugin->updateViewDisplay($view_display);
  }

}
