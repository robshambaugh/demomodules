<?php

namespace Drupal\acquia_dam\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\StringFilter;
use Drupal\views_remote_data\Plugin\views\query\RemoteDataQuery;

/**
 * Filter for assets based on category.
 *
 * @ViewsFilter("asset_category_filter")
 */
class AssetCategoryFilter extends StringFilter {

  /**
   * {@inheritdoc}
   */
  public function query() {
    assert($this->query instanceof RemoteDataQuery);
    $this->query->addWhere(
      $this->options['group'],
      'cat',
      rawurldecode($this->value),
      $this->operator
    );
  }

  /**
   * {@inheritdoc}
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

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    $form['value'] = [
      '#type' => 'widen_categories',
      '#title' => $this->t('Category'),
    ];
  }

}
