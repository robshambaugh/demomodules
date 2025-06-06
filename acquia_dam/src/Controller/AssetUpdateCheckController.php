<?php

namespace Drupal\acquia_dam\Controller;

use Drupal\acquia_dam\AssetUpdateChecker;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Url;
use Drupal\media\MediaInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Handles manually invoked asset update checks.
 *
 * A simple wrapper handling a custom entity operation button by transmitting
 * the invocation to the responsible centralized service.
 */
class AssetUpdateCheckController extends ControllerBase {

  /**
   * DAM asset update checker.
   *
   * @var \Drupal\acquia_dam\AssetUpdateChecker
   */
  protected $assetUpdateChecker;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * AssetUpdateCheckController constructor.
   *
   * @param \Drupal\acquia_dam\AssetUpdateChecker $asset_update_checker
   *   DAM asset update checker.
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   The current user.
   */
  public function __construct(AssetUpdateChecker $asset_update_checker, AccountProxy $current_user) {
    $this->assetUpdateChecker = $asset_update_checker;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('acquia_dam.asset_update_checker'),
      $container->get('current_user'),
    );
  }

  /**
   * The class' single method performing the business logic.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media item whose DAM asset needs to be checked.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect response.
   *
   * @throws \Drupal\acquia_dam\Exception\DamServerException
   */
  public function checkAssetUpdate(MediaInterface $media): RedirectResponse {
    $media_item_name = $media->getName();
    $redirection_target = 'view.dam_content_overview.page_1';

    // Let the centralized logic decide for us. Also, provide the current user
    // ID: in case a new revision will be created for the media item, then it
    // will happen on their behalf.
    $check_results = $this->assetUpdateChecker->checkAssets($media, (int) $this->currentUser->id());

    // At this point, the queue item has already been created (if necessary).
    // No more tasks left here but notifying the user and redirecting them
    // back. Prepare for any errors first.
    if ($check_results === NULL) {
      $this->messenger()->addError($this->t('An unexpected error occurred while checking the asset update status for media item %media_item_name.', [
        '%media_item_name' => $media_item_name,
      ]));

      return $this->redirect($redirection_target);
    }

    if ($check_results) {
      $this->messenger()->addStatus($this->t('The media item %media_item_name seems to be identical to the DAM asset it is associated with.', [
        '%media_item_name' => $media_item_name,
      ]));
    }
    else {
      $this->messenger()->addStatus($this->t('The media item %media_item_name is different from the DAM asset it is associated with, so it will be automatically updated soon.', [
        '%media_item_name' => $media_item_name,
      ]));
    }

    return $this->redirect($redirection_target);
  }

}
