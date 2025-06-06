<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\Plugin\media\acquia_dam;

/**
 * Documents Media Source Type.
 *
 * @AssetMediaSource(
 *   id = "documents",
 *   label = @Translation("Acquia DAM: Document"),
 *   default_thumbnail_filename = "generic.png",
 *   asset_search_key = "ft",
 *   asset_search_value = "office",
 * )
 */
final class Documents extends MediaSourceTypeBase implements MediaSourceTypeInterface {}
