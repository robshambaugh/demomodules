<?php

declare(strict_types=1);

namespace Drupal\acquia_dam;

use Drupal\acquia_dam\Entity\ManagedFileField;
use Drupal\acquia_dam\Entity\ManagedImageField;
use Drupal\acquia_dam\Entity\MediaSourceField;
use Drupal\acquia_dam\Plugin\media\Source\Asset;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\Core\Utility\Token;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\media\MediaInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;

/**
 * The `Asset File Entity Helper` service.
 *
 * Abstracts out primarily file entity and system file related functionality.
 */
final class AssetFileEntityHelper {

  /**
   * Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Entity Field Manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Drupal config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Drupal filesystem service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Drupal token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * Acquia DAM config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Acquia DAM logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $loggerChannel;

  /**
   * The file repository.
   *
   * @var \Drupal\file\FileRepositoryInterface
   */
  protected $fileRepository;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * AssetFileEntityHelper constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity Type Manager service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   Entity Field Manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Drupal config factory.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   Drupal filesystem service.
   * @param \Drupal\Core\Utility\Token $token
   *   Drupal token service.
   * @param \Drupal\Core\Logger\LoggerChannel $loggerChannel
   *   This module's own dedicated logger service.
   * @param \Drupal\file\FileRepositoryInterface $fileRepository
   *   The file repository service.
   * @param \GuzzleHttp\Client $client
   *   The HTTP client.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    EntityFieldManagerInterface $entityFieldManager,
    ConfigFactoryInterface $configFactory,
    FileSystemInterface $fileSystem,
    Token $token,
    LoggerChannel $loggerChannel,
    FileRepositoryInterface $fileRepository,
    Client $client
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityFieldManager = $entityFieldManager;
    $this->configFactory = $configFactory;
    $this->config = $configFactory->get('acquia_dam.settings');
    $this->fileSystem = $fileSystem;
    $this->token = $token;
    $this->loggerChannel = $loggerChannel;
    $this->fileRepository = $fileRepository;
    $this->httpClient = $client;
  }

  /**
   * Returns an associated file or creates a new one.
   *
   * @todo Supply the missing paramater list in this docblock once it's final.
   * @todo pay attention to the case when large files are being saved to the disk. Use queue instead maybe?
   *
   * @return \Drupal\file\FileInterface|null
   *   The file entity or NULL on failure.
   *
   * @throws \GuzzleHttp\Exception\TransferException
   * @throws \Drupal\Core\File\Exception\FileException
   */
  public function downloadFile(
    MediaInterface $media_entity,
    Asset $source_plugin,
    array $asset_data,
    string $asset_id,
  ): ?FileInterface {

    // Images have a different field name than files.
    // @see \Drupal\acquia_dam\MediaTypeResolver::resolve()
    $field_name = $source_plugin->getDerivativeId() === 'image' ? ManagedImageField::MANAGED_IMAGE_FIELD_NAME : ManagedFileField::MANAGED_FILE_FIELD_NAME;

    if (!$media_entity->hasField($field_name)) {
      $this->loggerChannel->error(sprintf(
        'Acquia DAM %s type is missing field %s',
        $source_plugin->getDerivativeId(),
        $field_name,
      ));
      return NULL;
    }

    // Get the local/current version ID of the asset.
    $asset_field = $media_entity->get(MediaSourceField::SOURCE_FIELD_NAME)->first();
    $local_version_id = $asset_field->getValue()['version_id'];
    $remote_version_id = $asset_data['version_id'];

    // Check if the required file for current version already exists.
    if (!$media_entity->isNew() && $local_version_id === $remote_version_id) {
      $field_item = $media_entity->get($field_name)->first();
      if ($field_item && !$field_item->isEmpty()) {
        $file_entity = $this->entityTypeManager->getStorage('file')->load($field_item->getValue()['target_id']);
        return ($file_entity instanceof FileInterface) ? $file_entity : NULL;
      }
    }

    // Download the file for a new asset or replace the existing file
    // for an old asset with a newer version.
    try {
      $response = $this->httpClient->request('GET', $asset_data['_links']['download']);
    }
    catch (TransferException | FileException $e) {
      $this->loggerChannel->error(sprintf(
        'Unable to download asset file at %s: %s %s',
        $asset_data['_links']['download'],
        get_class($e),
        $e->getMessage()
      ));
      return NULL;
    }

    if ($response->getStatusCode() !== 200) {
      $this->loggerChannel->warning('Unknown error occurred during the HTTP request while fetching data of @asset_id asset.', [
        '@asset_id' => $asset_id,
      ]);
      return NULL;
    }

    // Create the managed file.
    /** @var \GuzzleHttp\Psr7\Stream $stream */
    $stream = $response->getBody();
    $custom_path = $this->config->get('asset_file_directory_path');
    $scheme = \Drupal::config('system.file')->get('default_scheme');
    $directory = $scheme . '://' . $this->token->replace($custom_path, ['media' => $media_entity]);

    if (!$this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
      $this->loggerChannel->warning('Unable to prepare @dir_name directory for @asset_id asset.', [
        '@dir_name' => $directory,
        '@asset_id' => $asset_id,
      ]);
      return NULL;
    }

    // @see \Drupal\demo_umami_content\InstallHelper::createFileEntity()
    $local_asset_file_uri = $directory . DIRECTORY_SEPARATOR . $source_plugin->getMetadata($media_entity, 'filename');
    $this->fileRepository->writeData((string) $stream, $local_asset_file_uri, FileSystemInterface::EXISTS_REPLACE);

    // Ensure that the file has been created by loading in.
    if (!$file_entity = $this->fileRepository->loadByUri($local_asset_file_uri)) {
      $this->loggerChannel->warning('Unable to load the newly created file at @file_path location.', [
        '@file_path' => $local_asset_file_uri,
      ]);
      return NULL;
    }

    // When the asset file entity is created store its ID as a reference
    // together with other sub-field columns on the media entity as well.
    $values['target_id'] = $file_entity->id();

    // Prepare some textual information about the asset we can use for several
    // purposes.
    // @todo Revisit this logic when ACMS-3785, and then ACMS-4340 has landed.
    if (isset($asset_data['metadata']['fields']['description'])) {
      $asset_description = reset($asset_data['metadata']['fields']['description']);
    }
    else {
      $asset_description = $source_plugin->getMetadata($media_entity, 'filename');
    }

    switch ($field_name) {
      case ManagedFileField::MANAGED_FILE_FIELD_NAME:
        // This field is non-configurable by users, so set it in general.
        $values['display'] = 0;
        $values['description'] = $asset_description;
        break;

      case ManagedImageField::MANAGED_IMAGE_FIELD_NAME:
        $values['alt'] = $asset_description;
        $values['title'] = $asset_description;
        $values['width'] = (int) $asset_data['file_properties']['image_properties']['width'] ?: NULL;
        $values['height'] = (int) $asset_data['file_properties']['image_properties']['height'] ?: NULL;
        break;

      default:
        $this->loggerChannel->error('No field found on %media_item_name to reference the managed file/image.', [
          '%media_item_name' => $media_entity->getName(),
        ]);
        return NULL;
    }

    $media_entity->set($field_name, $values);

    return $file_entity;
  }

}
