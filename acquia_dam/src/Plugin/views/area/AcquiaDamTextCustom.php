<?php

namespace Drupal\acquia_dam\Plugin\views\area;

use Drupal\views\Plugin\views\area\AreaPluginBase;
use Drupal\views_remote_data\Plugin\views\query\RemoteDataQuery;

/**
 * A handler to display a message when there are no results.
 *
 * @ingroup views_area_handlers
 *
 * @ViewsArea("acquia_dam_no_results_text")
 */
final class AcquiaDamTextCustom extends AreaPluginBase {

  /**
   * {@inheritDoc}
   */
  public function render($empty = FALSE) {
    assert($this->query instanceof RemoteDataQuery);

    $search = '';
    $file_type = '';
    $category = '';
    foreach ($this->query->where as $condition_group) {
      foreach ($condition_group['conditions'] as $condition) {
        $field = implode('.', $condition['field']);
        if ($field === 'search') {
          $search = $condition['value'];
        }
        if ($field === 'cat') {
          $category = $condition['value'];
        }
        elseif ($field === 'FileType') {
          $file_type = $condition['value'];
        }
      }
    }
    if ($search === '') {
      if ($category !== '') {
        return ['#markup' => "No result found with category $category."];
      }
      return ['#markup' => "No result found for $file_type."];
    }

    return ['#markup' => "No result found for $search."];
  }

}
