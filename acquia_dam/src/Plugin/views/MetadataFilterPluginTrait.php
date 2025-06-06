<?php

namespace Drupal\acquia_dam\Plugin\views;

use Drupal\acquia_dam\Client\AcquiaDamClient;
use Drupal\acquia_dam\Exception\DamClientException;
use Drupal\acquia_dam\Exception\DamConnectException;
use Drupal\acquia_dam\Exception\DamServerException;

/**
 * Provides trait for creating metadata filter from the DAM.
 */
trait MetadataFilterPluginTrait {

  /**
   * Get the client interface.
   *
   * @return \Drupal\acquia_dam\Client\AcquiaDamClient
   *   Connection object.
   */
  abstract public function getClient(): AcquiaDamClient;

  /**
   * Adds display_key to the plugin's options.
   *
   * @param array $options
   *   The options.
   */
  protected function defineDisplayKeyOption(array &$options): void {
    $options['display_key'] = ['default' => ''];
  }

  /**
   * Fetches the appropriate display key from the DAM creates the option.
   *
   * @param array $form
   *   Option form.
   * @param string $field_types
   *   Type of the field on which the display key will be fetched.
   * @param string $display_key
   *   Display key option's default value.
   */
  protected function buildDisplayKeyOption(array &$form, string $field_types, string $display_key = '') {
    try {
      /** @var array<string, mixed> $list */
      $list = $this->getClient()->getDisplayKeys($field_types);
      $display_keys = array_column($list['fields'], 'display_key');
      $display_names = array_column($list['fields'], 'display_name');
      $display_key_options = array_combine($display_keys, $display_names);
    }
    catch (DamClientException | DamServerException | DamConnectException $e) {
      $display_key_options = [];
      $this->messenger()->addError($e->getMessage());
    }

    $form['display_key'] = [
      '#type' => 'select',
      '#title' => $this->t('Display Keys'),
      '#description' => $this->t('Available display keys from the metadata'),
      '#options' => $display_key_options,
      '#default_value' => $display_key,
      '#required' => TRUE,
    ];
  }

}
