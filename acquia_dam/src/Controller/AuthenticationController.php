<?php

namespace Drupal\acquia_dam\Controller;

use Drupal\acquia_dam\AcquiadamAuthService;
use Drupal\acquia_dam\Client\AcquiaDamClientFactory;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller handling the authentication for Acquia DAM.
 */
class AuthenticationController extends ControllerBase {

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
   * Acquia DAM logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $damLoggerChannel;

  /**
   * DAM client factory.
   *
   * @var \Drupal\acquia_dam\Client\AcquiaDamClientFactory
   */
  protected $clientFactory;

  /**
   * Instantiates an AuthenticationController object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   Request stack.
   * @param \Drupal\acquia_dam\AcquiadamAuthService $authService
   *   The DAM Authentication service.
   * @param \Drupal\acquia_dam\Client\AcquiaDamClientFactory $client_factory
   *   The DAM Client Factory.
   */
  public function __construct(RequestStack $request_stack, AcquiadamAuthService $authService, AcquiaDamClientFactory $client_factory) {
    $this->requestStack = $request_stack;
    $this->authService = $authService;
    $this->damLoggerChannel = $this->getLogger('logger.channel.acquia_dam');
    $this->clientFactory = $client_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('acquia_dam.authentication_service'),
      $container->get('acquia_dam.client.factory'),
    );
  }

  /**
   * Checks that the user or site has access to the remote DAM.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account being checked for access.
   * @param string $auth_type
   *   (optional) If the authentication request is site or user specific.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function checkAccess(AccountInterface $account, string $auth_type = 'site') {
    switch ($auth_type) {
      case 'site':
        return AccessResult::allowedIf($this->authService->isSiteAuthenticated())->setCacheMaxAge(0);

      case 'user':
        if ((int) $account->id() === (int) $this->currentUser()->id() && ($this->authService->isAuthenticated($account->id()))) {
          return AccessResult::allowed()->cachePerUser();
        }
        return AccessResult::forbidden()->cachePerUser();

      default:
        return AccessResult::neutral();
    }
  }

  /**
   * Callback from Acquia DAM to complete user authorization process.
   */
  public function authenticateUser(): RedirectResponse {
    // Get the code returned by the Acquia DAM API endpoint, if available.
    $code = $this->requestStack->getCurrentRequest()->query->get('code');
    $user_id = $this->requestStack->getCurrentRequest()->query->get('uid');

    $error_message = '';

    if (empty($user_id)) {
      $this->damLoggerChannel->error('User authentication request does not contain user id.');
      $this->messenger()->addError($this->t('Your site has not been authenticated with Acquia DAM.'));
      // Redirect to Acquia DAM config.
      return $this->redirect('acquia_dam.config');
    }

    /** @var \Drupal\user\UserInterface $user */
    $user = $this
      ->entityTypeManager()
      ->getStorage('user')
      ->load($user_id);

    if (empty($user)) {
      $this->damLoggerChannel->error('Cannot find user with the given user ID.');
      $error_message = $this->t('This site has not been authenticated with Acquia DAM.');
    }

    if (empty($code)) {
      $this->damLoggerChannel->error('Authentication request does not contain authentication code.');
      $error_message = $this->t('This site has not been authenticated with Acquia DAM.');
    }

    if (isset($code) && !empty($user)) {
      try {
        $this->authService->authenticateDam($code, (int) $user->id());
      }
      catch (\Exception $exception) {
        $this->damLoggerChannel->error('Error during user authentication: ' . $exception->getMessage());
        $error_message = $exception->getMessage();
      }
    }

    if ($error_message) {
      $this->messenger()->addError($error_message);
    }
    else {
      $this->messenger()->addStatus($this->t('This user account has been authenticated with Acquia DAM.'));
    }

    return $this->redirect('entity.user.acquia_dam_auth', ['user' => $user->id()]);
  }

  /**
   * Callback from Acquia DAM to complete site authorization process.
   */
  public function authenticateSite(): RedirectResponse {
    // Get the code returned by the Acquia DAM API endpoint, if available.
    $code = $this->requestStack->getCurrentRequest()->query->get('code');

    $error_message = '';

    if (empty($code)) {
      $this->damLoggerChannel->error('Authenticate request does not contain authentication code.');
      $error_message = $this->t('Your site has not been authenticated with Acquia DAM.');
    }

    if (isset($code)) {
      try {
        $this->authService->authenticateDam($code);
      }
      catch (\Exception $exception) {
        $this->damLoggerChannel->error('Error during site authentication: ' . $exception->getMessage());
        $error_message = $exception->getMessage();
      }
    }

    if ($error_message) {
      $this->messenger()->addError($error_message);
    }
    else {
      $this->messenger()->addStatus($this->t('The site has been authenticated with Acquia DAM. <a href=":url">Authenticate your user account too</a>', [
        ':url' => Url::fromRoute('entity.user.acquia_dam_auth', ['user' => $this->currentUser()->id()])->toString(),
      ]));
    }

    return $this->redirect('acquia_dam.config');
  }

  /**
   * Deletes authentication info from user data.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect response instance.
   */
  public function logout(): RedirectResponse {
    $user_id = $this->currentUser()->id();
    $acquia_dam_account = $this->authService->getUserData($user_id);
    try {
      $this->authService->cancelUserRegistration($acquia_dam_account['acquia_dam_token'], (int) $user_id);
    }
    catch (\Exception $exception) {
      $this->messenger()->addMessage('Something went wrong during logout process. Please contact the site administrator for more information.');
      $this->damLoggerChannel->error('Error during logout request: ' . $exception->getMessage());
      return $this->redirect('entity.user.acquia_dam_auth', ['user' => $user_id]);
    }

    $this->messenger()->addMessage($this->t('Your account has been disconnected from Acquia DAM.'));

    return $this->redirect('entity.user.acquia_dam_auth', ['user' => $user_id]);
  }

}
