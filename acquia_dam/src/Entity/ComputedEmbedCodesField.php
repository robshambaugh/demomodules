<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\Entity;

use Drupal\acquia_dam\Plugin\Field\FieldType\ComputedEmbedCodes;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides storage and field definitions for the embed field.
 */
final class ComputedEmbedCodesField {

  /**
   * The field name.
   */
  public const FIELD_NAME = 'acquia_dam_embed_codes';

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
   *
   * @return \Drupal\acquia_dam\Entity\BundleFieldDefinition
   *   The field definition.
   */
  public static function getFieldDefinition(string $entity_type_id, string $bundle): BundleFieldDefinition {
    return self::bundleFieldDefinition()
      ->setTargetEntityTypeId($entity_type_id)
      ->setTargetBundle($bundle);

  }

  /**
   * Get the bundle field definition.
   *
   * @return \Drupal\acquia_dam\Entity\BundleFieldDefinition
   *   The bundle field definition.
   */
  private static function bundleFieldDefinition(): BundleFieldDefinition {
    return BundleFieldDefinition::create('map')
      ->setProvider('acquia_dam')
      ->setName(self::FIELD_NAME)
      ->setLabel(new TranslatableMarkup('Computed embed codes'))
      ->setCardinality(1)
      ->setComputed(TRUE)
      ->setClass(ComputedEmbedCodes::class)
      ->setReadOnly(TRUE);
  }

}
