<?php

namespace Drupal\acquia_dam\Form;

use Drupal\acquia_dam\Exception\DamClientException;
use Drupal\acquia_dam\Exception\DamConnectException;
use Drupal\acquia_dam\Exception\DamServerException;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Acquia DAM metadata configuration form.
 */
class AcquiaDamMetadataConfigurationForm extends ConfigFormBase {

  /**
   * DAM client factory.
   *
   * @var \Drupal\acquia_dam\Client\AcquiaDamClientFactory
   */
  protected $clientFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);

    $instance->clientFactory = $container->get('acquia_dam.client.factory');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'acquia_dam_metadata_config';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'acquia_dam.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);

    try {
      /** @var array<string, mixed> $meta_data_list */
      $meta_data_list = $this->clientFactory->getSiteClient()->getDisplayKeys('all');
    }
    catch (DamClientException | DamServerException | DamConnectException $e) {
      $this->messenger()->addError($e->getMessage());
      return $form;
    }

    if (!isset($meta_data_list['fields']) || count($meta_data_list['fields']) < 1) {
      $this->t('No information received from the Widen API regarding asset metadata.');
    }

    $display_key_options = $default_values = [];
    $config = $this->config('acquia_dam.settings')->get('allowed_metadata');

    foreach ($meta_data_list['fields'] as $metadata) {
      $display_key_options[$metadata['display_key']]['column'] = $metadata['display_name'];

      if ($config) {
        foreach ($config as $key => $value) {
          $default_values[$key] = $value;
        }
      }
    }

    $form['instructions'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Each piece of asset metadata in Widen can be assigned to a custom field of media items in Drupal (with the field type that supports storing the given value, of course). These field assignments are set per each media type (eg. Image, Video, Document, etc.) on their configuration page. In case there are dozens of metadata fields existing in Widen, then browsing through the long list on the media type config page can be inconvenient. Therefore, this page allows administrators to narrow down the scope of metadata fields from the remote system on a site-wide basis. Only those which are checked here will be offered for mapping on media type configuration pages.'),
    ];
    $form['metadata'] = [
      '#type' => 'tableselect',
      '#header' => [
        'column' => $this->t('Name of metadata field in DAM'),
      ],
      '#options' => $display_key_options,
      '#default_value' => $default_values,
      '#empty' => $this->t('No metadata field found available in DAM.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Save the allowed metadata values.
    $checkboxes = $form_state->getValue('metadata') ?? [];
    $selected_values = $options = [];

    foreach ($form['metadata']['#options'] as $key => $value) {
      $options[$key] = $value['column'];
    }

    // Storing with labels in order to build the source mapping correctly.
    foreach (array_keys(array_filter($checkboxes)) as $selections) {
      $selected_values[$selections] = $options[$selections];
    }
    $this->config('acquia_dam.settings')
      ->set('allowed_metadata', array_filter($selected_values))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
