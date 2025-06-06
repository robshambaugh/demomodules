<?php

namespace Drupal\acquia_dam_integration_links\AssetDetector;

use Drupal\acquia_dam\Plugin\media\Source\Asset;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\media\MediaInterface;

/**
 * Detects DAM asset usage in media reference fields.
 */
final class MediaReferenceAssetDetector extends AssetDetectorBase {

  /**
   * List of field types supported by this detector.
   *
   * @var string[]
   */
  protected $supportedFieldTypes = [
    'entity_reference',
  ];

  /**
   * {@inheritdoc}
   */
  public function discoverAsset(ContentEntityInterface $entity, array $field_definitions, bool $is_title_changed): array {
    $supported_fields = $this->filterSupportedFields($field_definitions, $this->supportedFieldTypes);
    // If there are no relevant updates we still need the current value, to make
    // sure that we do not delete an integration link for the given entity if a
    // referenced asset is removed from another field type.
    $has_relevant_updates = $this->hasRelevantUpdates($supported_fields, $entity);

    $old_asset_ids = [];
    $new_asset_ids = [];
    // Cannot filter the field by update status. If entity has more than one
    // field which supports DAM asset reference, then we must make sure that
    // removing a reference on update does not remove the integration link from
    // the API if the other field still has the asset. If we filter by relevant
    // update then we could remove integration link from a non-updated field
    // which is still needed.
    foreach ($supported_fields as $field) {
      // Not able to check isNew() since at this point entity is saved.
      if (isset($entity->original) && $has_relevant_updates) {
        // Media entities referenced before update.
        $old_referenced_entities = $entity->original->get($field->getName())->referencedEntities();
        $old_asset_ids = array_merge($old_asset_ids, $this->getAssetIds($old_referenced_entities));
      }
      // Media entities referenced after update.
      $new_referenced_entities = $entity->get($field->getName())->referencedEntities();
      $new_asset_ids = array_merge($new_asset_ids, $this->getAssetIds($new_referenced_entities));
    }

    // It can have duplicates, asset tracker will take care of those.
    return [
      'asset_to_register' => $new_asset_ids,
      'assets_to_remove' => $old_asset_ids,
    ];
  }

  /**
   * Returns asset ids of given Media entities.
   *
   * @param \Drupal\media\MediaInterface[] $media_entities
   *   Media entity array.
   *
   * @return array
   *   Referenced DAM asset ids.
   */
  protected function getAssetIds(array $media_entities): array {
    // Array filter to work with only media entities from proper source.
    $media_entities = array_filter(
      $media_entities,
      static function (MediaInterface $media) {
        return $media->getSource() instanceof Asset;
      });

    return array_map(
      static function (MediaInterface $media) {
        return $media->get('acquia_dam_asset_id')->asset_id;
      },
      $media_entities
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function filterSupportedFields(array $field_definitions, array $supported_field_types): array {
    $supported_fields = [];

    foreach (parent::filterSupportedFields($field_definitions, $supported_field_types) as $field) {
      $storage = $field->getFieldStorageDefinition();
      $settings = $storage->getSettings();
      if (!isset($settings['target_type'])
        || $settings['target_type'] !== 'media') {
        continue;
      }

      $supported_fields[] = $field;
    }

    return $supported_fields;
  }

}
