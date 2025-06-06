<?php

declare(strict_types=1);

namespace Drupal\acquia_dam_test\Plugin\media\acquia_dam;

use Drupal\acquia_dam\Plugin\media\acquia_dam\MediaSourceTypeBase;
use Drupal\acquia_dam\Plugin\media\acquia_dam\MediaSourceTypeInterface;

/**
 * Test Media Source Type.
 *
 * @AssetMediaSource(
 *   id = "assettestmediasource",
 *   label = @Translation("Acquia DAM: Test Source"),
 *   default_thumbnail_filename = "generic.png",
 *   asset_search_key = "ft",
 *   asset_search_value = "test",
 * )
 */
final class AssetTestMediaSource extends MediaSourceTypeBase implements MediaSourceTypeInterface {}
