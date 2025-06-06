<?php

namespace Drupal\acquia_dam_integration_links;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Service to track Acquia DAM asset usage on entities.
 */
final class AssetTracker {

  /**
   * Asset detector list.
   *
   * @var \Drupal\acquia_dam_integration_links\AssetDetectorInterface[]
   *   Asset detector interface instances.
   */
  private $assetDetectors;

  /**
   * Adds asset detector service.
   *
   * @param \Drupal\acquia_dam_integration_links\AssetDetectorInterface $assetDetector
   *   Asset detector instance.
   */
  public function addAssetDetector(AssetDetectorInterface $assetDetector) {
    $this->assetDetectors[] = $assetDetector;
  }

  /**
   * Runs asset detection process.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity instance.
   * @param \Drupal\Core\Field\FieldDefinitionInterface[] $field_definitions
   *   The array of field definitions for the entity.
   * @param bool $title_changed
   *   Tells if the main entity title changed or not.
   *
   * @return array
   *   Asset usage.
   */
  public function runAssetDiscovery(ContentEntityInterface $entity, array $field_definitions, $title_changed = FALSE): array {
    $assets = [];
    foreach ($this->assetDetectors as $detector) {
      $assets = array_merge_recursive(
        $assets,
        $detector->discoverAsset($entity, $field_definitions, $title_changed));
    }

    // If title changed we need every single asset which is referenced on main.
    // We will delete everything registered before to the parent, so we must
    // leave assets_to_remove empty.
    if ($title_changed) {
      return [
        'asset_to_register' => array_unique($assets['asset_to_register']),
        'assets_to_remove' => [],
      ];
    }

    return [
      'asset_to_register' => array_unique(array_diff($assets['asset_to_register'], $assets['assets_to_remove'])),
      'assets_to_remove' => array_unique(array_diff($assets['assets_to_remove'], $assets['asset_to_register'])),
    ];
  }

}
