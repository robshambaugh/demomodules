<?php

namespace Drupal\acquia_dam_integration_links;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Asset detector interface.
 */
interface AssetDetectorInterface {

  /**
   * Discovers Acquia DAM asset usage.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity instance.
   * @param \Drupal\Core\Field\FieldDefinitionInterface[] $field_definitions
   *   The array of field definitions for the entity.
   * @param bool $is_title_changed
   *   Title changed on main entity.
   *
   * @return array
   *   Info about DAM asset usage.
   *
   *   Example:
   *   [
   *     'asset_to_register' => [asset uuids for register]
   *     'assets_to_remove'  => [asset uuids for unlink]
   *   ];
   */
  public function discoverAsset(ContentEntityInterface $entity, array $field_definitions, bool $is_title_changed): array;

}
