<?php

namespace Drupal\acquia_dam\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\Views;

/**
 * Filters DAM Asset in views.
 *
 * @ViewsFilter("dam_asset_filter")
 */
class DamAssetFilter extends FilterPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $form['dam_asset'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Applying this filter will only show Media entities from DAM.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();

    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->query;
    $table = array_key_first($query->tables);
    $configuration = [
      'table' => 'media__acquia_dam_asset_id',
      'field' => 'entity_id',
      'left_table' => $table,
      'left_field' => 'mid',
      'operator' => '=',
    ];

    /** @var \Drupal\views\Plugin\views\join\JoinPluginBase $join */
    $join = Views::pluginManager('join')->createInstance('standard', $configuration);
    $query->addRelationship('da', $join, 'media_field_data');
    $query->addWhere($this->options['group'], 'da.entity_id', NULL, 'IS NOT NULL');
  }

}
