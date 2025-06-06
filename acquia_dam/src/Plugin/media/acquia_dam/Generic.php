<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\Plugin\media\acquia_dam;

/**
 * Generic Media Source Type.
 *
 * @AssetMediaSource(
 *   id = "generic",
 *   label = @Translation("Acquia DAM: Generic"),
 *   default_thumbnail_filename = "generic.png",
 *   asset_search_key = "ft",
 *   asset_search_value = "generic_binary",
 * )
 */
final class Generic extends MediaSourceTypeBase implements MediaSourceTypeInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->assetFieldFormatterConfiguration[$this->embedCodeAssetField] = [
      'type' => 'acquia_dam_embed_code',
      'label' => 'hidden',
      'settings' => [
        'embed_style' => 'link_download',
      ],
      'third_party_settings' => [],
      'region' => 'content',
      'weight' => 0,
    ];
  }

}
