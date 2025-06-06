<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\Plugin\Action;

use Drupal\acquia_dam\AssetUpdateChecker;
use Drupal\acquia_dam\Plugin\media\Source\Asset;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\media\MediaInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Checks for asset update of selected media items.
 *
 * @Action(
 *    id = "asset_update_check_action",
 *    label = @Translation("Check for asset update"),
 *    type = "media"
 *  )
 */
class CheckAssetUpdateAction extends ActionBase implements ContainerFactoryPluginInterface {

  /**
   * The tempstore factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * DAM asset update checker.
   *
   * @var \Drupal\acquia_dam\AssetUpdateChecker
   */
  protected $assetUpdateChecker;

  /**
   * CheckAssetUpdateAction constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The tempstore factory.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Current user.
   * @param \Drupal\acquia_dam\AssetUpdateChecker $asset_update_checker
   *   DAM asset update checker.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    PrivateTempStoreFactory $temp_store_factory,
    AccountInterface $current_user,
    AssetUpdateChecker $asset_update_checker
  ) {
    $this->currentUser = $current_user;
    $this->tempStoreFactory = $temp_store_factory;
    $this->assetUpdateChecker = $asset_update_checker;

    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('tempstore.private'),
      $container->get('current_user'),
      $container->get('acquia_dam.asset_update_checker'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function execute($media_item = NULL) {
    if ($media_item !== NULL) {
      if ($media_item->getSource() instanceof Asset) {
        $this->assetUpdateChecker->checkAssets($media_item, (int) $this->currentUser->id());
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    assert($object instanceof MediaInterface);
    /** @var \Drupal\media\MediaInterface $object */
    $access = $object->status->access('edit', $account, TRUE)
      ->andIf($object->access('update', $account, TRUE));

    return $return_as_object ? $access : $access->isAllowed();
  }

}
