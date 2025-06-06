<?php

namespace Drupal\acquia_dam_integration_links\AssetDetector;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Detects DAM asset usage in text fields.
 */
final class EntityEmbedTextDetector extends AssetDetectorBase {

  /**
   * List of field types supported by this detector.
   *
   * @var string[]
   */
  protected $supportedFieldTypes = [
    'text',
    'text_long',
    'text_with_summary',
  ];

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * EntityEmbedTextDetector constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity Type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public function discoverAsset(ContentEntityInterface $entity, array $field_definitions, bool $is_title_changed): array {
    $supported_fields = $this->filterSupportedFields($field_definitions, $this->supportedFieldTypes);
    // If there are no relevant updates we still need the current value, to make
    // sure that we do not delete an integration link for the given entity if a
    // referenced asset is removed from another field type.
    $has_relevant_updates = $this->hasRelevantUpdates($supported_fields, $entity);

    $new_asset_usage = [];
    $old_asset_usage = [];
    foreach ($supported_fields as $field) {
      if (isset($entity->original) && $has_relevant_updates) {
        $old_asset_usage = array_merge($old_asset_usage, $this->parseAssetId($entity->original->get($field->getName())));
      }
      $new_asset_usage = array_merge($new_asset_usage, $this->parseAssetId($entity->get($field->getName())));
    }

    // It can have duplicates, asset tracker will take care of those.
    return [
      'asset_to_register' => $new_asset_usage,
      'assets_to_remove' => $old_asset_usage,
    ];
  }

  /**
   * Gets embedded entity uuids from text fields.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   Field item list.
   *
   * @return array
   *   Array containing entity uuids.
   */
  protected function parseEntityUuid(FieldItemListInterface $field): array {
    $entities = [];

    foreach ($field->getValue() as $value) {
      if (empty($value['value'])) {
        continue;
      }

      $text = $value['value'];
      if (stristr($text, '<drupal-media') === FALSE) {
        continue;
      }

      $dom = Html::load($text);
      $xpath = new \DOMXPath($dom);
      foreach ($xpath->query('//drupal-media[@data-entity-type="media" and @data-entity-uuid and @data-embed-code-id!=""]') as $node) {
        $entities[] = $node->getAttribute('data-entity-uuid');
      }
    }

    return $entities;
  }

  /**
   * Get asset ids from text field values.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   Field instance.
   *
   * @return array
   *   Array containing asset ids.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function parseAssetId(FieldItemListInterface $field): array {
    $asset_ids = [];
    $media_storage = $this->entityTypeManager->getStorage('media');
    foreach (array_unique($this->parseEntityUuid($field)) as $entity_uuid) {
      $media = $media_storage->loadByProperties(['uuid' => $entity_uuid]);

      // In case if media item can not be loaded (eg. due to invalid UUID).
      if (empty($media)) {
        continue;
      }

      $media = current($media);
      $asset_ids[] = $media->get('acquia_dam_asset_id')->asset_id;
    }

    return $asset_ids;
  }

}
