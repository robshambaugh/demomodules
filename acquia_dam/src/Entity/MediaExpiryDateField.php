<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\Entity;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides storage and field definitions for the embed filed.
 */
final class MediaExpiryDateField {

  /**
   * The field name.
   */
  public const EXPIRY_DATE_FIELD_NAME = 'acquia_dam_expiry_date';

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
      ->setRevisionable(TRUE)
      ->setDisplayConfigurable('form', TRUE);

  }

  /**
   * Get the bundle field definition.
   *
   * @return \Drupal\acquia_dam\Entity\BundleFieldDefinition
   *   The bundle field definition.
   */
  private static function bundleFieldDefinition(): BundleFieldDefinition {
    return BundleFieldDefinition::create('timestamp')
      ->setProvider('acquia_dam')
      ->setName(self::EXPIRY_DATE_FIELD_NAME)
      // @phpstan-ignore-next-line
      ->setLabel(new TranslatableMarkup('Expiration date'));
  }

}
