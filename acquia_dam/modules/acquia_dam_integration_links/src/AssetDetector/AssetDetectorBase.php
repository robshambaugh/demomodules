<?php

namespace Drupal\acquia_dam_integration_links\AssetDetector;

use Drupal\acquia_dam_integration_links\AssetDetectorInterface;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Base class for asset detectors.
 */
abstract class AssetDetectorBase implements AssetDetectorInterface {

  /**
   * Checks if the given fields have relevant updates.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface[] $field_definitions
   *   The array of field definitions for the entity.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity instance.
   *
   * @return bool
   *   TRUE if there was any change on one of the given fields.
   */
  protected function hasRelevantUpdates(array $field_definitions, ContentEntityInterface $entity): bool {
    if (!isset($entity->original)) {
      return TRUE;
    }

    foreach ($field_definitions as $definition) {
      $field_name = $definition->getName();
      $items = $entity->get($field_name);
      $original_items = $entity->original->get($field_name);
      if ($items->hasAffectingChanges($original_items, $entity->language()->getId())) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Returns supported fields.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface[] $field_definitions
   *   The array of field definitions for the entity.
   * @param string[] $supported_field_types
   *   The array of field definitions for the entity.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   Array with the supported field definitions.
   */
  protected function filterSupportedFields(array $field_definitions, array $supported_field_types) : array {
    $supported_fields = [];

    foreach ($field_definitions as $field) {
      if (!in_array($field->getType(), $supported_field_types, TRUE)) {
        continue;
      }

      $supported_fields[] = $field;
    }

    return $supported_fields;
  }

}
