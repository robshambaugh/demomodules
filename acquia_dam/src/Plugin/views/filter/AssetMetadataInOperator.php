<?php

namespace Drupal\acquia_dam\Plugin\views\filter;

use Drupal\acquia_dam\Client\AcquiaDamClient;
use Drupal\acquia_dam\Client\AcquiaDamClientFactory;
use Drupal\acquia_dam\Exception\DamClientException;
use Drupal\acquia_dam\Exception\DamServerException;
use Drupal\acquia_dam\Plugin\views\MetadataFilterPluginTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\InOperator;
use Drupal\views_remote_data\Plugin\views\query\RemoteDataQuery;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter for assets based on metadata select, checkbox, and palette fields.
 *
 * @ViewsFilter("asset_metadata_in_operator")
 */
class AssetMetadataInOperator extends InOperator {

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
   * @param \Drupal\acquia_dam\Client\AcquiaDamClientFactory $client_factory
   *   The Acquia DAM client factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AcquiaDamClientFactory $client_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->clientFactory = $client_factory;
    $this->valueFormType = 'select';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
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
   * {@inheritdoc}
   */
  public function query(): void {
    assert($this->query instanceof RemoteDataQuery);
    $this->query->addWhere(
      $this->options['group'],
      $this->options['display_key'],
      $this->value,
      $this->options['filter_operation']
    );
  }

  /**
   * {@inheritdoc}
   */
  public function operators(): array {
    return [
      'in' => [
        'title' => $this->t('Is one of'),
        'short' => $this->t('in'),
        'short_single' => $this->t('='),
        'method' => 'opSimple',
        'values' => 1,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getValueOptions(): array {
    if ($this->options['display_key'] === '') {
      $this->valueOptions = [];
    }

    if ($this->valueOptions === NULL) {
      try {
        $client = $this->clientFactory->getSiteClient();
        $list = $client->getDisplayKeyVocabulary($this->options['display_key']);
        $this->valueOptions = array_combine($list['vocabulary'], $list['vocabulary']);
      }
      catch (DamClientException | DamServerException $e) {
        $this->messenger()->addError($e->getMessage());
        $this->valueOptions = [];
      }
    }
    return $this->valueOptions;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $this->defineDisplayKeyOption($options);
    $options['filter_operation'] = ['default' => 'OR'];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $this->buildDisplayKeyOption($form, 'selection_list,checkboxes,selection_list_multi', $this->options['display_key']);
    $form['filter_operation'] = [
      '#type' => 'radios',
      '#title' => $this->t('Type of operation expected'),
      '#description' => $this->t('This works only if the user can add multiple filter options'),
      '#options' => [
        'OR' => 'OR',
        'AND' => 'AND',
      ],
      '#default_value' => $this->options['filter_operation'],
      '#required' => TRUE,
      '#states' => [
        'invisible' => [
          ':input[name="options[expose_button][checkbox][checkbox]"]' => ['checked' => FALSE],
        ],
        'visible' => [
          ':input[name="options[expose][multiple]"]' => ['checked' => TRUE],

        ],
      ],
    ];
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
