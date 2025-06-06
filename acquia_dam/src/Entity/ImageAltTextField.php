<?php

namespace Drupal\acquia_dam\Entity;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides storage and field definitions for the alt text field.
 */
final class ImageAltTextField {

  /**
   * The field name.
   */
  public const IMAGE_ALT_TEXT_FIELD_NAME = 'acquia_dam_alt_text';

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
      ->setDisplayConfigurable('form', TRUE);
  }

  /**
   * Get the bundle field definition.
   *
   * @return \Drupal\acquia_dam\Entity\BundleFieldDefinition
   *   The bundle field definition.
   */
  private static function bundleFieldDefinition(): BundleFieldDefinition {
    return BundleFieldDefinition::create('string')
      ->setProvider('acquia_dam')
      ->setSetting('max_length', 512)
      ->setName(self::IMAGE_ALT_TEXT_FIELD_NAME)
      ->setLabel(new TranslatableMarkup('Alt text'));
  }

}
