<?php

namespace Drupal\acquia_dam\Plugin\views\filter;

use Drupal\acquia_dam\Client\AcquiaDamClient;
use Drupal\acquia_dam\Client\AcquiaDamClientFactory;
use Drupal\acquia_dam\Plugin\views\MetadataFilterPluginTrait;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\StringFilter;
use Drupal\views_remote_data\Plugin\views\query\RemoteDataQuery;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Assets remote search filter.
 *
 * @ViewsFilter("asset_metadata_string")
 */
class AssetMetadataString extends StringFilter {
  use MetadataFilterPluginTrait;


  /**
   * DAM client factory.
   *
   * @var \Drupal\acquia_dam\Client\AcquiaDamClientFactory
   */
  protected $clientFactory;

  /**
   * Constructs a new AssetFieldTypeFilter object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\acquia_dam\Client\AcquiaDamClientFactory $client_factory
   *   The Acquia DAM client factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $connection, AcquiaDamClientFactory $client_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $connection);
    $this->clientFactory = $client_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
      $container->get('acquia_dam.client.factory'),
    );
  }

  /**
   * {@inheritDoc}
   *
   * @throws \Drupal\acquia_dam\Exception\DamClientException
   */
  public function getClient():AcquiaDamClient {
    return $this->clientFactory->getSiteClient();
  }

  /**
   * {@inheritDoc}
   */
  public function query() {
    assert($this->query instanceof RemoteDataQuery);
    $this->query->addWhere(
      $this->options['group'],
      $this->options['display_key'],
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

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $this->defineDisplayKeyOption($options);
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $this->buildDisplayKeyOption($form, 'text,text_short,text_long', $this->options['display_key']);
    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    parent::submitOptionsForm($form, $form_state);
    $this->options['display_key'] = $form_state->getValue('display_key');
  }

}
