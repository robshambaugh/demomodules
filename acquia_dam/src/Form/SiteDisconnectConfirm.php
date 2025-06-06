<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\Form;

use Drupal\acquia_dam\AcquiadamAuthService;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a form to confirm the cancellation of Acquia DAM authentication.
 */
class SiteDisconnectConfirm extends ConfirmFormBase {

  /**
   * The request stack factory service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * DAM auth service.
   *
   * @var \Drupal\acquia_dam\AcquiadamAuthService
   */
  protected $authService;

  /**
   * SiteDisconnectConfirm constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\acquia_dam\AcquiadamAuthService $auth_service
   *   DAM Authentication service.
   */
  public function __construct(RequestStack $request_stack, AcquiadamAuthService $auth_service) {
    $this->requestStack = $request_stack;
    $this->authService = $auth_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('acquia_dam.authentication_service'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'acquia_dam_site_disconnect_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to disconnect this site from Acquia DAM?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('acquia_dam.config');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Disconnecting this website from Acquia DAM will prevent its all users from using DAM assets in content. This action cannot be undone.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Disconnect');
  }

  /**
   * Disconnects site from Acquia DAM.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirects to configuration page.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $acquia_dam_token = $this->authService->getSiteToken();

    try {
      $this->authService->cancelSiteRegistration($acquia_dam_token);
    }
    catch (\Exception $exception) {
      $this->messenger()->addMessage('Something went wrong during site disconnect process. Please contact the site administrator for more information.');

      $this->logger('acquia_dam')->error('Error during site logout request: ' . $exception->getMessage());
      return $this->redirect('acquia_dam.config');
    }

    $this->messenger()->addMessage($this->t('Site successfully disconnected from Acquia DAM.'));
    $form_state->setRedirectUrl(Url::fromRoute('acquia_dam.config'));

    return $this->redirect('acquia_dam.config');
  }

}
