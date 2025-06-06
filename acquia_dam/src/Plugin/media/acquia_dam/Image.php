<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\Plugin\media\acquia_dam;

use Drupal\acquia_dam\Entity\ManagedImageField;

/**
 * Image Media Source Type.
 *
 * @AssetMediaSource(
 *   id = "image",
 *   label = @Translation("Acquia DAM: Image"),
 *   default_thumbnail_filename = "generic.png",
 *   asset_search_key = "ft",
 *   asset_search_value = "image",
 * )
 */
final class Image extends MediaSourceTypeBase implements MediaSourceTypeInterface {

  /**
   * {@inheritdoc}
   */
  protected string $localFileAssetField = ManagedImageField::MANAGED_IMAGE_FIELD_NAME;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->assetFieldFormatterConfiguration[$this->localFileAssetField] = [
      'type' => 'image',
      'label' => 'hidden',
      'settings' => [
        'image_link' => '',
        'image_style' => '',
        'image_loading' => ['attribute' => 'lazy'],
      ],
      'third_party_settings' => [],
      'region' => 'content',
      'weight' => 0,
    ];
  }

  /**
   * Helper function to get content region.
   *
   * @param array $content_region
   *   The content region.
   * @param string $existing_field_name
   *   Existing field to disable in the display.
   * @param string $active_field_name
   *   Name of the (newly) active field.
   * @param array $active_field
   *   Settings for the (newly) active field.
   *
   * @return array
   *   Return the active field data.
   */
  protected function setSettings(array $content_region, string $existing_field_name, string $active_field_name, array $active_field): array {
    // Get the existing field settings.
    $settings = $content_region[$existing_field_name]['settings'];

    // Style keys
    $styles = [
      $this->localFileAssetField => 'image_style',
      $this->embedCodeAssetField => 'embed_style',
    ];
    // Mapping of styles
    // Key is the source style, value is the target style for the specified field.
    $mapping = [
      $this->embedCodeAssetField => [
        ''  => 'original',
      ],
      $this->localFileAssetField => [
        'remotely_referenced_thumbnail_image' => '',
        'original' => '',
      ],
    ];

    // Get the style value of existing field.
    $style_value = $settings[$styles[$existing_field_name]];

    // Ensure the image style is a valid embed format.
    if ($active_field_name === $this->embedCodeAssetField) {
      /** @var \Drupal\acquia_dam\ImageStyleHelper $image_style_helper */
      $image_style_helper = \Drupal::service('acquia_dam.image_style_support');
      $embed_styles = $image_style_helper->getAllowedImageStyles();
      if (!in_array($style_value, $embed_styles)) {
        $image_style_helper->addAllowedImageStyle($style_value);
      }
    }

    // Apply mapping.
    $active_field['settings'][$styles[$active_field_name]] = $mapping[$active_field_name][$style_value] ?? $style_value;

    return $active_field;
  }

}
