<?php

namespace Drupal\acquia_dam\Controller;

use Drupal\acquia_dam\AcquiadamAuthService;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Authentication page for Acquia DAM.
 */
class AuthenticationPage extends ControllerBase {

  /**
   * Authentication service.
   *
   * @var \Drupal\acquia_dam\AcquiadamAuthService
   */
  protected $authService;

  /**
   * The configuration factory service of Core.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * AuthenticationPage construct.
   *
   * @param \Drupal\acquia_dam\AcquiadamAuthService $auth_service
   *   Acquia DAM authentication service.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The configuration factory service of Core.
   */
  public function __construct(AcquiadamAuthService $auth_service, ConfigFactory $config_factory) {
    $this->authService = $auth_service;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('acquia_dam.authentication_service'),
      $container->get('config.factory'),
    );
  }

  /**
   * Custom access check to match route parameter with current user.
   *
   * @param \Drupal\user\UserInterface $user
   *   User instance.
   *
   * @return \Drupal\Core\Access\AccessResult|\Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultNeutral
   *   Allowed if the current user id matching the id from the route parameter.
   */
  public function access(UserInterface $user) {
    return AccessResult::allowedIf((int) $user->id() === (int) $this->currentUser()->id());
  }

  /**
   * Build authentication page for Acquia DAM.
   *
   * @param \Drupal\user\UserInterface $user
   *   User instance.
   *
   * @return array
   *   Render array.
   */
  public function authPage(UserInterface $user): array {
    $domain = $this->configFactory->get('acquia_dam.settings')->get('domain');

    if (empty($domain)) {
      return ['#markup' => 'You must configure your Acquia DAM domain first. ' . Link::createFromRoute($this->t('Click here to configure.'), 'acquia_dam.config')->toString()];
    }

    $build = [
      '#attached' => [
        'library' => ['acquia_dam/acquia_dam.authorization'],
      ],
    ];
    if (!$this->authService->isAuthenticated($user->id())) {
      $return_link = Url::fromRoute('acquia_dam.user_auth')->setAbsolute()->toString() . '?uid=' . $user->id();
      $build['link'] = $this->renderField($this->t('Authorize'), Url::fromUri($this->authService->generateAuthUrl($return_link)), $this->t('Connect to Acquia Dam'));
    }
    else {
      $build['link'] = $this->renderField($this->t('Log out'), Url::fromRoute('acquia_dam.logout'), $this->t('Disconnect from Acquia DAM'), TRUE);
    }

    return $build;
  }

  /**
   * Returns a link rendered as a button.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $button_text
   *   Button title.
   * @param \Drupal\Core\Url $url
   *   Url object for the link.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $title
   *   Title of the field.
   * @param bool $delete
   *   If TRUE, sets attributes with button--danger class.
   *   Otherwise, 'button--primary' class is set. Default FALSE.
   *
   * @return array
   *   Link render array.
   */
  protected function renderField(TranslatableMarkup $button_text, Url $url, TranslatableMarkup $title, bool $delete = FALSE): array {
    $build['acquia_dam'] = [
      '#type' => 'fieldset',
      '#title' => $title,
    ];
    $build['acquia_dam']['link'] = [
      '#type' => 'link',
      '#title' => $button_text,
      '#url' => $url,
      '#cache' => [
        'max-age' => 0,
      ],
      '#attributes' => [
        'class' => $delete ? ['button', 'button--danger'] : ['button button--primary'],
      ],
    ];
    return $build;
  }

}
