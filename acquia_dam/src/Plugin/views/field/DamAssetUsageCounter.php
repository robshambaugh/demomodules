<?php

namespace Drupal\acquia_dam\Plugin\views\field;

use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A counter views field plugin for dam asset usages.
 *
 * @ViewsField("dam_asset_usage_counter")
 */
class DamAssetUsageCounter extends FieldPluginBase {

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $database) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $configuration = [
      'table' => 'acquia_dam_integration_link_aggregate',
      'field' => 'asset_uuid',
      'left_table' => NULL,
      'left_field' => 'acquia_dam_asset_id_asset_id',
      'operator' => '=',
    ];

    /** @var \Drupal\views\Plugin\views\join\JoinPluginBase $join */
    $join = Views::pluginManager('join')->createInstance('standard', $configuration);
    $this->query->addRelationship('ata', $join, 'media_field_data', $this->relationship);
    $this->field_alias = $this->query->addField('ata', 'usage_count');
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    // @todo Create a views field formatter to render as proper link.
    /** @var \Drupal\media\MediaInterface $media */
    $media = $values->_entity;
    $asset_id = $media->get('acquia_dam_asset_id')->asset_id;

    $url = Url::fromUri("internal:/admin/acquia-dam-links/$asset_id");

    $usage_count = $values->ata_usage_count ?? 0;
    return [
      '#type' => 'link',
      '#title' => $this->formatPlural($usage_count, "$usage_count place", "$usage_count places"),
      '#url' => $url,
    ];
  }

}
