<?php

namespace Drupal\acquia_dam\Plugin\views\field;

use Drupal\acquia_dam\AssetUpdateChecker;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * AvailabilityPublishingStatusCompare.
 *
 * A views field plugin to display the results of comparing the availability
 * flag of the remote DAM asset with the publishing status of its associated
 * local media item.
 *
 * @ViewsField("availability_publishing_status_comparison")
 */
class AvailabilityPublishingStatusCompare extends FieldPluginBase {

  /**
   * The asset update checker service.
   *
   * @var \Drupal\acquia_dam\AssetUpdateChecker
   */
  protected AssetUpdateChecker $assetUpdateChecker;

  /**
   * Class constructor.
   *
   * @param array $configuration
   *   The plugin config.
   * @param string $plugin_id
   *   The plugin ID.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param \Drupal\acquia_dam\AssetUpdateChecker $asset_update_checker
   *   The asset update checker service.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    AssetUpdateChecker $asset_update_checker
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->assetUpdateChecker = $asset_update_checker;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('acquia_dam.asset_update_checker'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $configuration = [
      'field' => 'asset_uuid',
      'left_table' => NULL,
    ];

    Views::pluginManager('join')->createInstance('standard', $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    return ($this->assetUpdateChecker->checkAssets($values->_entity)) ? $this->t('Up-to-date') : $this->t('Outdated');
  }

}
