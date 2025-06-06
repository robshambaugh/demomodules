<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\Entity;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides storage and field definitions for the DAM media source plugin.
 */
final class MediaSourceField {

  /**
   * The field name.
   */
  public const SOURCE_FIELD_NAME = 'acquia_dam_asset_id';

  /**
   * Get the field storage definition.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return \Drupal\acquia_dam\Entity\BundleFieldDefinition
   *   The storage definition.
   */
  public static function getStorageDefinition(string $entity_type_id): BundleFieldDefinition {
    return self::bundleFieldDefinition()
      ->setTargetEntityTypeId($entity_type_id);
  }

  /**
   * Get the field definition.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle
   *   The entity bundle.
   * @param string $label
   *   The field label.
   *
   * @return \Drupal\acquia_dam\Entity\BundleFieldDefinition
   *   The field definition.
   */
  public static function getFieldDefinition(string $entity_type_id, string $bundle, string $label): BundleFieldDefinition {
    return self::bundleFieldDefinition()
      ->setTargetEntityTypeId($entity_type_id)
      ->setTargetBundle($bundle)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      // @todo Redeclaring them here again might be redundant: it's probably inherited from the 'AssetItem' field type.
      ->setDisplayOptions('view', [
        'type' => 'acquia_dam_embed_code',
        'weight' => -5,
      ]);
  }

  /**
   * Get the bundle field definition.
   *
   * @return \Drupal\acquia_dam\Entity\BundleFieldDefinition
   *   The bundle field definition.
   */
  private static function bundleFieldDefinition(): BundleFieldDefinition {
    return BundleFieldDefinition::create('acquia_dam_asset')
      ->setProvider('acquia_dam')
      ->setName(self::SOURCE_FIELD_NAME)
      ->setLabel(new TranslatableMarkup('Asset reference'))
      ->setReadOnly(TRUE);
  }

}
