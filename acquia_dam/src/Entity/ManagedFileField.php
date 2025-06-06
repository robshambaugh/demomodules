<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\Entity;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides storage and field definitions for the DAM media source plugin.
 */
final class ManagedFileField {

  /**
   * The field name.
   */
  public const MANAGED_FILE_FIELD_NAME = 'acquia_dam_managed_file';

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
   * @param string $bundle
   *   The entity bundle.
   * @param string $bundle_label
   *   The bundle label. Deprecated in Acquia Dam 1.1.
   * @param string $file_extensions
   *   Supported file extensions.
   *
   * @return \Drupal\acquia_dam\Entity\BundleFieldDefinition
   *   The field definition.
   */
  public static function getFieldDefinition(string $entity_type_id, string $bundle, string $bundle_label = '', string $file_extensions = ''): BundleFieldDefinition {
    if ($bundle_label !== '') {
      @trigger_error('Passing a bundle label to ManagedFileField::getFieldDefinition() is deprecated in acquia_dam:1.1.0 and is removed from acquia_dam:1.2.0. This parameter never set the label, there is no replacement.', E_USER_DEPRECATED);
    }
    $bundle_definitions =  self::bundleFieldDefinition()
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setTargetEntityTypeId($entity_type_id)
      ->setTargetBundle($bundle);
      if ($file_extensions !== '') {
        $bundle_definitions->setSetting('file_extensions', $file_extensions);
      }
    return $bundle_definitions;
  }

  /**
   * Get the bundle field definition.
   *
   * @return \Drupal\acquia_dam\Entity\BundleFieldDefinition
   *   The bundle field definition.
   */
  private static function bundleFieldDefinition(): BundleFieldDefinition {
    return BundleFieldDefinition::create('file')
      ->setSetting('target_type', 'file')
      ->setProvider('acquia_dam')
      ->setName(self::MANAGED_FILE_FIELD_NAME)
      ->setDescription(t('For keeping track of locally stored files of DAM assets.'))
      ->setLabel(new TranslatableMarkup('On-site stored asset file'))
      ->setReadOnly(FALSE);
  }

}
