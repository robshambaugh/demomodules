<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\Entity;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides storage and field definitions for the DAM media source plugin.
 */
final class ManagedImageField {

  /**
   * The field name.
   */
  public const MANAGED_IMAGE_FIELD_NAME = 'acquia_dam_managed_image';

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
    return self::bundleFieldDefinition()->setTargetEntityTypeId($entity_type_id);
  }

  /**
   * Get the field definition.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle_id
   *   The entity bundle ID.
   *
   * @return \Drupal\acquia_dam\Entity\BundleFieldDefinition
   *   The field definition.
   */
  public static function getFieldDefinition(string $entity_type_id, string $bundle_id): BundleFieldDefinition {
    return self::bundleFieldDefinition()
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setTargetEntityTypeId($entity_type_id)
      ->setTargetBundle($bundle_id);
  }

  /**
   * Get the bundle field definition.
   *
   * @return \Drupal\acquia_dam\Entity\BundleFieldDefinition
   *   The bundle field definition.
   *
   * @todo Once this module needs to support SVG images, then the list of
   *    allowed file extensions inherited from the parent `Image` field type
   *    can be extended here.
   *
   * @code
   *  ->setSetting('file_extensions', 'png gif jpg jpeg webp svg')
   * @endcode
   *
   * @see \Drupal\image\Plugin\Field\FieldType\ImageItem::defaultFieldSettings()
   */
  private static function bundleFieldDefinition(): BundleFieldDefinition {
    return BundleFieldDefinition::create('image')
      ->setProvider('acquia_dam')
      ->setName(self::MANAGED_IMAGE_FIELD_NAME)
      ->setDescription(t('For keeping track of locally stored images of DAM assets.'))
      ->setLabel(new TranslatableMarkup('On-site stored asset image'))
      ->setReadOnly(FALSE);
  }

}
