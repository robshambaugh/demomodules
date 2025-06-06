<?php

namespace Drupal\acquia_dam\Plugin\media\acquia_dam;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Describes a DAM Asset compliant Media Source type for media items.
 */
interface MediaSourceTypeInterface extends ContainerFactoryPluginInterface {

  /**
   * Sets the configuration for this plugin instance.
   *
   * @param array $configuration
   *   An associative array containing the plugin's configuration.
   */
  public function setConfiguration(array $configuration): void;

  /**
   * Gets this plugin's configuration.
   *
   * @return array
   *   An array of this plugin's configuration.
   */
  public function getConfiguration(): array;

  /**
   * Get the actual local file asset field defined by the active plugin.
   *
   * @return string
   *   The Local File Asset Field.
   */
  public function getLocalFileAssetField(): string;

  /**
   * Get the name of the actual field used to represent an asset.
   *
   * Assets are represented by one field, but that field can change depending on the asset being shown.
   *
   * @return string
   *   The Asset Field Name.
   */
  public function getActiveFieldName(): string;

  /**
   * Get the formatter settings for a particular asset field.
   *
   * This method is used by acquia_dam_entity_bundle_field_info to set some formatter settings on the field storage.
   *
   * @param $managed_field_name
   *   The Managed Field.
   * @return array
   *   Formatter settings for the specified field using a specific media source type.
   */
  public function getFormatter($managed_field_name): array;
}
