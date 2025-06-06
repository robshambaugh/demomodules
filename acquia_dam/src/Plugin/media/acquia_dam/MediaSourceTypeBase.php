<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\Plugin\media\acquia_dam;

use Drupal\acquia_dam\Entity\ManagedFileField;
use Drupal\acquia_dam\Entity\MediaSourceField;
use Drupal\Core\Config\Config;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Plugin\PluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Media Source Type for DAM Assets.
 */
abstract class MediaSourceTypeBase extends PluginBase implements MediaSourceTypeInterface {

  /**
   * Field name when using embed codes.
   */
  protected string $embedCodeAssetField = MediaSourceField::SOURCE_FIELD_NAME;

  /**
   * Field name when using download/sync.
   */
  protected string $localFileAssetField = ManagedFileField::MANAGED_FILE_FIELD_NAME;

  /**
   * Array of field configurations keyed by the base field name.
   *
   * @var array|array[]
   */
  protected array $assetFieldFormatterConfiguration = [
    MediaSourceField::SOURCE_FIELD_NAME => [
      'type' => 'acquia_dam_embed_code',
      'label' => 'hidden',
      'settings' => [
        'embed_style' => 'original',
      ],
      'third_party_settings' => [],
      'region' => 'content',
      'weight' => 0,
    ],
    ManagedFileField::MANAGED_FILE_FIELD_NAME => [
      'type' => 'file_default',
      'settings' => [
        'use_description_as_link_text' => TRUE,
      ],
      'third_party_settings' => [],
      'weight' => 0,
      'label' => 'hidden',
      'region' => 'content',
    ],
  ];

  /**
   * Array of media library field configurations.
   *
   * @var array
   */
  protected array $assetMediaLibraryFormatterConfiguration = [
    MediaSourceField::SOURCE_FIELD_NAME => [
      'type' => 'acquia_dam_embed_code',
      'label' => 'hidden',
      'settings' => [
        'embed_style' => 'remotely_referenced_thumbnail_image',
        'thumbnail_width' => 300,
      ],
      'third_party_settings' => [],
      'region' => 'content',
      'weight' => 0,
    ]
  ];

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
          $configuration,
          $plugin_id,
          $plugin_definition
      );
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function getLocalFileAssetField(): string {
    return $this->localFileAssetField;
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveFieldName(): string {
    if ($this->configuration['download_assets']) {
      return $this->localFileAssetField;
    }
    return $this->embedCodeAssetField;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormatter($managed_field_name): array
  {
    if (isset($this->assetFieldFormatterConfiguration[$managed_field_name])) {
      return $this->assetFieldFormatterConfiguration[$managed_field_name];
    }
    // Return the generic embed code asset field if no matches are found.
    return $this->assetFieldFormatterConfiguration[MediaSourceField::SOURCE_FIELD_NAME];
  }

  /**
   * Helper function to swap asset fields for a provided view display.
   *
   * @param \Drupal\Core\Config\Config|\Drupal\Core\Entity\Display\EntityViewDisplayInterface $view_display
   *   Current view display to swap fields.
   * @param string $existing_field_name
   *   Existing field to disable in the display.
   * @param string $active_field_name
   *   Name of the (newly) active field.
   * @param array $active_field
   *   Settings for the (newly) active field.
   *
   * @return Config|EntityViewDisplay
   *   This method can be used for both Config objects and Entity View Displays.
   */
  public function swapAssetFields(Config|EntityViewDisplay $view_display, string $existing_field_name, string $active_field_name, array $active_field): Config|EntityViewDisplay {
    // Get the hidden and content regions.
    $hidden_region = $view_display->get('hidden');
    $content_region = $view_display->get('content');

    // Update field label and weight.
    $active_field['label'] = $content_region[$existing_field_name]['label'] ?? 'hidden';
    $active_field['weight'] = $content_region[$existing_field_name]['weight'] ?? 0;

    // Set settings.
    $active_field = $this->setSettings($content_region, $existing_field_name, $active_field_name, $active_field);

    // Set the active field in the content region.
    $content_region[$active_field_name] = $active_field;
    // Remove existing field from a content region.
    unset($content_region[$existing_field_name]);

    // Update view display configuration.
    $view_display->set('content', $content_region);
    $hidden_region[$existing_field_name] = true;
    unset($hidden_region[$active_field_name]);
    $view_display->set('hidden', $hidden_region);

    return $view_display;
  }

  /**
   * Helper function to set the settings to active field.
   *
   * @param array $content_region
   *   The content region.
   * @param string $existing_field_name
   *    Existing field to disable in the display.
   * @param string $active_field_name
   *    Name of the (newly) active field.
   * @param array $active_field
   *    Settings for the (newly) active field.
   *
   * @return array
   *   Return the active field data.
   */
  protected function setSettings(array $content_region, string $existing_field_name, string $active_field_name, array $active_field): array {
    return $active_field;
  }

  /**
   * Helper method to update view display.
   *
   * @param \Drupal\Core\Entity\Display\EntityViewDisplayInterface $view_display
   *   The view display.
   *
   * @return \Drupal\Core\Entity\Display\EntityViewDisplayInterface
   *   The updated view display.
   */
  public function updateViewDisplay(EntityViewDisplay $view_display): EntityViewDisplay {
    $hidden_region = $view_display->get('hidden');
    $content_region = $view_display->get('content');

    // Remove the all fields from a content region.
    foreach ($content_region as $key => $field) {
      unset($content_region[$key]);
      $hidden_region[$key] = TRUE;
    }

    // Configure the media library view display.
    if ($view_display->getMode() === 'media_library') {
      // Remove active field from a hidden region.
      unset($hidden_region[$this->embedCodeAssetField]);
      $view_display->set('content', $this->assetMediaLibraryFormatterConfiguration);
    }
    // Configure the other view display.
    else {
      $active_field = $this->getActiveFieldName();
      // Remove active field from a hidden region.
      unset($hidden_region[$active_field]);
      $view_display->set('content', [$active_field => $this->assetFieldFormatterConfiguration[$active_field]]);
    }

    // Save the view display.
    $view_display->set('hidden', $hidden_region);

    return $view_display;
  }

}
