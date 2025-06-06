<?php

namespace Drupal\acquia_dam\Plugin\QueueWorker;

use Drupal\acquia_dam\IntegrationLinkRegister;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes integration link registration.
 *
 * @QueueWorker (
 *   id = "acquia_dam_integration_links",
 *   title = @Translation("Acquia DAM Integration links"),
 *   cron = {"time" = 30}
 * )
 */
final class IntegrationLinks extends AssetQueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The integration link register service.
   *
   * @var \Drupal\acquia_dam\IntegrationLinkRegister
   */
  private $integrationLinkRegister;

  /**
   * Constructs a IntegrationLinks object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\acquia_dam\IntegrationLinkRegister $integration_link_register
   *   The integration link register service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, IntegrationLinkRegister $integration_link_register) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->integrationLinkRegister = $integration_link_register;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new self($configuration, $plugin_id, $plugin_definition, $container->get('acquia_dam.integration_link_register'));
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    if (!is_array($data)) {
      return FALSE;
    }
    if (empty($data['op']) || !is_string($data['op']) || !method_exists($this->integrationLinkRegister, $data['op'])) {
      return FALSE;
    }
    if (empty($data['args']) || !is_array($data['args'])) {
      return FALSE;
    }

    $method = $data['op'];
    $args = $data['args'];
    try {
      $this->integrationLinkRegister->$method(...$args);
    }
    catch (\Exception $exception) {
      $this->processException($exception);
    }
    return TRUE;
  }

}
