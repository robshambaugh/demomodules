<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\Plugin\media\acquia_dam;

use Drupal\acquia_dam\Entity\ManagedFileField;
use Drupal\acquia_dam\Entity\MediaSourceField;

/**
 * Audio Media Source Type.
 *
 * @AssetMediaSource(
 *   id = "audio",
 *   label = @Translation("Acquia DAM: Audio"),
 *   default_thumbnail_filename = "generic.png",
 *   asset_search_key = "ft",
 *   asset_search_value = "audio",
 * )
 */
final class Audio extends MediaSourceTypeBase implements MediaSourceTypeInterface {

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
        'embed_style' => 'remote_streaming',
      ],
      'third_party_settings' => [],
      'region' => 'content',
      'weight' => 0,
    ],
    ManagedFileField::MANAGED_FILE_FIELD_NAME => [
      'type' => 'file_audio',
      'label' => 'hidden',
      'settings' => [
        'controls' => TRUE,
        'autoplay' => FALSE,
        'loop' => FALSE,
        'multiple_file_display_type' => 'tags',
      ],
      'third_party_settings' => [],
      'region' => 'content',
      'file_extensions' => 'mp3 ogg wav',
      'weight' => 0,
    ],
  ];

}
