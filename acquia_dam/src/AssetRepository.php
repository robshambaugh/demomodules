<?php

declare(strict_types=1);

namespace Drupal\acquia_dam;

use Drupal\acquia_dam\Client\AcquiaDamClientFactory;
use Drupal\acquia_dam\Entity\MediaExpiryDateField;
use Drupal\acquia_dam\Entity\MediaSourceField;
use Drupal\acquia_dam\Exception\AssetImportException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\media\MediaStorage;
use Drupal\media\MediaTypeInterface;

/**
 * Helper service for assets.
 */
final class AssetRepository {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * The client factory.
   *
   * @var \Drupal\acquia_dam\Client\AcquiaDamClientFactory
   */
  private $clientFactory;

  /**
   * The media type resolver.
   *
   * @var \Drupal\acquia_dam\MediaTypeResolver
   */
  private $mediaTypeResolver;

  /**
   * AssetRepository constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\acquia_dam\Client\AcquiaDamClientFactory $client_factory
   *   Acquia dam client factory.
   * @param \Drupal\acquia_dam\MediaTypeResolver $media_type_resolver
   *   The media type resolver.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AcquiaDamClientFactory $client_factory, MediaTypeResolver $media_type_resolver) {
    $this->entityTypeManager = $entity_type_manager;
    $this->clientFactory = $client_factory;
    $this->mediaTypeResolver = $media_type_resolver;
  }

  /**
   * Get media storage.
   */
  public function getMediaStorage(): MediaStorage {
    return $this->entityTypeManager->getStorage('media');
  }

  /**
   * Import media assets.
   *
   * @param array $asset_ids
   *   Array of asset ids to import.
   *
   * @return array
   *   Array of imported entity ids.
   */
  public function import(array $asset_ids): array {
    $source_field_name = MediaSourceField::SOURCE_FIELD_NAME;
    $client = $this->clientFactory->getSiteClient();
    $imported_entities_id = [];

    foreach ($asset_ids as $asset_id) {
      try {
        $asset = $client->getAsset($asset_id);

        if (!$asset['released_and_not_expired']) {
          // Exception is expected to be thrown.
          // @see \Drupal\acquia_dam\Plugin\views\field\MediaLibrarySelectForm::processInputValues()
          throw new AssetImportException('Asset not found.', $asset['exception_code']);
        }
        $bundle = $this->mediaTypeResolver->resolve($asset);
        // Could not resolve to a bundle, which should be impossible.
        if ($bundle === NULL) {
          continue;
        }
        $field_values = [
          'bundle' => $bundle->id(),
          'name' => $asset['filename'],
          $source_field_name => [
            'asset_id' => $asset_id,
            // @todo Why not populated here 'version_id' and 'external_id'? The
            //   majority of current 'acquia_dam_media_presave()' hook could be
            //   eliminated.
          ],
        ];
        // Not all asset have an expiration date.
        if ($asset['security']['expiration_date']) {
          $date = \DateTime::createFromFormat(\DateTimeInterface::ISO8601, $asset['security']['expiration_date']);
          $field_values[MediaExpiryDateField::EXPIRY_DATE_FIELD_NAME]['value'] = $date->getTimeStamp();
        }
        $media = $this->getMediaStorage()->create($field_values);
        $media->save();
        $imported_entities_id[] = $media->id();
      }
      catch (\Exception $e) {
        throw new AssetImportException($e->getMessage(), $e->getCode());
      }
    }

    return $imported_entities_id;
  }

  /**
   * Finds existing media assets.
   *
   * @param array $assets
   *   Array of asset ids to find.
   *
   * @return array
   *   Array of existing media assets ids.
   */
  public function find(array $assets): array {
    $source_field_name = MediaSourceField::SOURCE_FIELD_NAME;

    if (empty($assets)) {
      return [];
    }

    // Find existing assets that have been imported.
    $existing_media_query = $this->getMediaStorage()
      ->getQuery()
      ->accessCheck(FALSE);

    // Asset IDs are UUIDs and media IDs are integers. The database engine may
    // try to type cast the string to integer. If the UUID begins with a numeric
    // value,like "1B4XC", the resulting value will be 1 instead of none.
    // @note this does not happen on SQLite but does with MySQL.
    $int_assets_ids = array_filter($assets, 'is_numeric');
    if (count($int_assets_ids) > 0) {
      $existing_media_query
        ->condition(
          $existing_media_query->orConditionGroup()
            ->condition("$source_field_name.asset_id", $assets, 'IN')
            ->condition('mid', $int_assets_ids, 'IN')
        );
    }
    else {
      $existing_media_query
        ->condition("$source_field_name.asset_id", $assets, 'IN');
    }
    $existing_media_asset_ids = $existing_media_query->execute();

    return array_values($existing_media_asset_ids);
  }

  /**
   * Checks if an asset exists locally for a given bundle.
   *
   * @param MediaTypeInterface $media_type
   *   The media bundle to check.
   * @param int $filled
   *   Check whether we're looking for local assets or not. Defaults to filled.
   *
   * @return int
   *   Number of assets stored locally.
   */
  public function countLocalAssets(MediaTypeInterface $media_type, bool $filled = TRUE): int {
    $negate = $filled ? " NOT" : "";
    $query = $this->getMediaStorage()->getQuery()
      ->accessCheck(FALSE);
    $query->condition('bundle', $media_type->id())
      ->condition($media_type->getSource()->getLocalFileAssetField(), NULL, "IS$negate NULL");
    return $query->count()->execute();
  }

  /**
   * Get a count of total published assets for a given media type.
   *
   * @param string $bundle
   *   Acquia DAM media type.
   *
   * @return int
   *   Count of media items.
   */
  public function countTotalAssets(string $bundle): int {
    $query = $this->getMediaStorage()->getQuery()
      ->accessCheck(FALSE);
    $query->condition('bundle', $bundle)->condition('status', 1);

    return $query->count()->execute();
  }

}
