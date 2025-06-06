<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a DAM Media Source annotation object.
 *
 * @Annotation
 */
class AssetMediaSource extends Plugin {

  /**
   * The plugin ID of the Media Source type.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the Media Source type.
   *
   * @var \Drupal\Core\Annotation\Translation
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * The default thumbnail filename.
   *
   * @var string
   */
  public $default_thumbnail_filename;

  /**
   * The search key passed to DAM.
   *
   * @var string
   */
  public $asset_search_key;

  /**
   * The search value passed to DAM.
   *
   * @var string
   */
  public $asset_search_value;

}
