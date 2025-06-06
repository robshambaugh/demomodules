<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\Plugin\media\acquia_dam;

use Drupal\acquia_dam\Entity\ManagedFileField;
use Drupal\acquia_dam\Entity\MediaSourceField;

/**
 * Video Media Source Type.
 *
 * @AssetMediaSource(
 *   id = "video",
 *   label = @Translation("Acquia DAM: Video"),
 *   default_thumbnail_filename = "generic.png",
 *   asset_search_key = "ft",
 *   asset_search_value = "video",
 * )
 */
final class Video extends MediaSourceTypeBase implements MediaSourceTypeInterface
{

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
        'embed_style' => 'inline_view',
      ],
      'third_party_settings' => [],
      'region' => 'content',
      'weight' => 0,
    ],
    ManagedFileField::MANAGED_FILE_FIELD_NAME => [
      'type' => 'file_video',
      'settings' => [
        'controls' => TRUE,
        'autoplay' => FALSE,
        'loop' => FALSE,
        'multiple_file_display_type' => 'tags',
        'muted' => FALSE,
        'width' => 640,
        'height' => 480,
      ],
      'third_party_settings' => [],
      'region' => 'content',
      'file_extensions' => 'mp4 mov',
      'label' => 'hidden',
      'weight' => 0,
    ],
  ];

}
