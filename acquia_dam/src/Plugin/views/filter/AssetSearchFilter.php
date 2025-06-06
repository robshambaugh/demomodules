<?php

namespace Drupal\acquia_dam\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\StringFilter;
use Drupal\views_remote_data\Plugin\views\query\RemoteDataQuery;

/**
 * Assets remote search filter.
 *
 * @ViewsFilter("asset_search_filter")
 */
class AssetSearchFilter extends StringFilter {

  /**
   * {@inheritDoc}
   */
  public function query() {
    assert($this->query instanceof RemoteDataQuery);
    $this->query->addWhere(
      $this->options['group'],
      $this->realField,
      $this->value,
      $this->operator
    );
  }

  /**
   * {@inheritDoc}
   */
  public function operators() {
    return [
      '=' => [
        'title' => $this->t('Is equal to'),
        'short' => $this->t('='),
        'method' => 'opEqual',
        'values' => 1,
      ],
    ];
  }

}
