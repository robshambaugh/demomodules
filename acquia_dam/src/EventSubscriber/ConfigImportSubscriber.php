<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\EventSubscriber;

use Drupal\acquia_dam\AssetRepository;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\StorageTransformEvent;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class to handle the Acquia DAM configurations.
 */
class ConfigImportSubscriber implements EventSubscriberInterface {

  /**
   * The asset repository service.
   *
   * @var \Drupal\acquia_dam\AssetRepository
   */
  protected $assetRepository;

  /**
   * Entity Type Manager
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;
  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The active config storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $active;

  /**
   * Constructs a new MediaTypeConfigImportSubscriber object.
   *
   * @param \Drupal\acquia_dam\AssetRepository $asset_repository
   *   The asset handler service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The Entity Type Manager.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger service.
   * @param \Drupal\Core\Config\StorageInterface $active
   *   The Active storage.
   */
  public function __construct(AssetRepository $asset_repository, EntityTypeManagerInterface $entity_type_manager, LoggerChannelInterface $logger, StorageInterface $active) {
    $this->assetRepository = $asset_repository;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
    $this->active = $active;
  }

  /**
   * Reverts Acquia DAM configurations, if assets are not available locally.
   *
   * @param \Drupal\Core\Config\StorageTransformEvent $event
   *   The config storage transform event.
   *
   * @throws \Drupal\Core\Config\ConfigImporterException
   */
  public function onImportTransform(StorageTransformEvent $event): void {
    $storage = $event->getStorage();

    // Read all media type configurations.
    $media_types_config = $storage->listAll('media.type.');

    // Iterate over each media type configuration.
    foreach ($media_types_config as $media_type) {
      // Read the source and active configuration data for the media type.
      $source_config_data = $storage->read($media_type);
      $active_config_data = $this->active->read($media_type);

      // Check if both source and active configuration data are available.
      if ($source_config_data && $active_config_data) {
        // Read 'download_assets' option from source and active configurations.
        $source_download_sync_option = $source_config_data['source_configuration']['download_assets'] ?? NULL;
        $active_download_sync_option = $active_config_data['source_configuration']['download_assets'] ?? NULL;

        // Extract the bundle name from the media type configuration name.
        [, , $bundle] = explode(".", $media_type);

        // If the source has 'download_assets' option enabled, and active has
        // disabled, and the asset does not exist locally, disable
        // 'download_assets' in the source configuration.
        /** @var \Drupal\media\MediaTypeInterface $media_type_entity */
        $media_type_entity = $this->entityTypeManager->getStorage('media_type')->load($bundle);

        if ($source_download_sync_option === TRUE &&
          $active_download_sync_option === FALSE &&
          $this->assetRepository->countTotalAssets($bundle) > $this->assetRepository->countLocalAssets($media_type_entity)) {
          $source_config_data['source_configuration']['download_assets'] = FALSE;
          $storage->write($media_type, $source_config_data);

          // Log the action of disabling 'download_assets' for the media type.
          $this->logger->info(
            "Disabling 'Download and sync' for the '@bundle' media type as the media item does not exist locally.",
            ['@bundle' => $bundle]
          );
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ConfigEvents::STORAGE_TRANSFORM_IMPORT][] = ['onImportTransform'];
    return $events;
  }

}
