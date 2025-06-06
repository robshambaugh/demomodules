<?php

namespace Drupal\acquia_dam_integration_links\AssetDetector;

use Drupal\acquia_dam_integration_links\AssetTracker;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;

/**
 * Detects DAM asset usage in paragraph reference fields.
 */
class ParagraphsAssetDetector extends AssetDetectorBase {

  /**
   * List of field types supported by this detector.
   *
   * @var string[]
   */
  protected $supportedFieldTypes = [
    'entity_reference_revisions',
  ];

  /**
   * Asset tracker service.
   *
   * @var \Drupal\acquia_dam_integration_links\AssetTracker
   */
  protected $assetTracker;

  /**
   * Entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * ParagraphsAssetDetector constructor.
   *
   * @param \Drupal\acquia_dam_integration_links\AssetTracker $assetTracker
   *   Asset tracker service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   Entity field manager service.
   */
  public function __construct(AssetTracker $assetTracker, EntityFieldManagerInterface $entityFieldManager) {
    $this->assetTracker = $assetTracker;
    $this->entityFieldManager = $entityFieldManager;
  }

  /**
   * {@inheritdoc}
   */
  public function discoverAsset(ContentEntityInterface $entity, array $field_definitions, bool $is_title_changed): array {
    $supported_fields = $this->filterSupportedFields($field_definitions, $this->supportedFieldTypes);

    $new_assets = [];
    $old_assets = [];
    foreach ($supported_fields as $field) {
      // Get paragraph entities referenced on the field.
      $referenced_entities = $entity->get($field->getName())->referencedEntities();

      // Re-runs asset gathering process for paragraphs.
      $new_assets = array_merge($new_assets, $this->getParagraphsAssets($referenced_entities));

      // If the title changed on the main entity we don't care what was on the
      // original since we will delete every link and re-register.
      if (isset($entity->original) && !$is_title_changed) {
        // Re-runs asset gathering process for paragraphs on parent update.
        $old_referenced_entities = $entity->original->get($field->getName())->referencedEntities();
        $old_assets = array_merge($old_assets, $this->getParagraphsAssets($old_referenced_entities));
      }
    }

    return [
      'asset_to_register' => array_diff($new_assets, $old_assets),
      'assets_to_remove' => array_diff($old_assets, $new_assets),
    ];
  }

  /**
   * Re-runs asset discovery for paragraph entities.
   *
   * @param array $referenced_entities
   *   Referenced paragraph instances.
   *
   * @return array
   *   Asset ids from paragraphs.
   */
  protected function getParagraphsAssets(array $referenced_entities): array {
    $result = [
      'asset_to_register' => [],
      'assets_to_remove' => [],
    ];

    foreach ($referenced_entities as $referenced_entity) {
      // Get field definitions for every entity, those can be of different type.
      $field_definitions = $this
        ->entityFieldManager
        ->getFieldDefinitions($referenced_entity->getEntityTypeId(), $referenced_entity->bundle());
      $assets = $this->assetTracker->runAssetDiscovery($referenced_entity, $field_definitions);
      $result = array_merge_recursive(
        $result,
        $assets
      );
    }

    // Always work with asset_to_register values. assets_to_remove only gets
    // populated on update, but we cannot track paragraph update since original
    // property will be never set for paragraph instances. It might change in
    // the future.
    // https://www.drupal.org/project/paragraphs/issues/3013961
    return !empty($result['asset_to_register']) ? $result['asset_to_register'] : [];
  }

  /**
   * {@inheritdoc}
   */
  protected function filterSupportedFields(array $field_definitions, array $supported_field_types): array {
    $supported_fields = [];

    foreach (parent::filterSupportedFields($field_definitions, $supported_field_types) as $field) {
      $storage = $field->getFieldStorageDefinition();
      $settings = $storage->getSettings();
      if ($settings['target_type'] !== 'paragraph') {
        continue;
      }

      $supported_fields[] = $field;
    }

    return $supported_fields;
  }

}
