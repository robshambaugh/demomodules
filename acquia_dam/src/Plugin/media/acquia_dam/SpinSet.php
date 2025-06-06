<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\Plugin\media\acquia_dam;

/**
 * Spinset Media Source Type.
 *
 * @AssetMediaSource(
 *   id = "spinset",
 *   label = @Translation("Acquia DAM: SpinSet"),
 *   default_thumbnail_filename = "generic.png",
 *   asset_search_key = "ff",
 *   asset_search_value = "SpinSet",
 * )
 */
final class SpinSet extends MediaSourceTypeBase implements MediaSourceTypeInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->assetFieldFormatterConfiguration[$this->embedCodeAssetField] = [
      'type' => 'acquia_dam_embed_code',
      'label' => 'hidden',
      'settings' => [
        'embed_style' => 'link_text',
      ],
      'third_party_settings' => [],
      'region' => 'content',
      'weight' => 0,
    ];
  }

}
