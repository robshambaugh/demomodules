<?php

namespace Drupal\acquia_dam\Form;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use GuzzleHttp\Exception\TransferException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Acquia DAM module configuration form.
 */
class AcquiaDamConfigurationForm extends ConfigFormBase {

  /**
   * Client interface.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * DAM auth service.
   *
   * @var \Drupal\acquia_dam\AcquiadamAuthService
   */
  protected $authService;

  /**
   * The field plugin manager service.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * Image style helper service.
   *
   * @var \Drupal\acquia_dam\ImageStyleHelper
   */
  protected $imageStyleHelper;

  /**
   * Acquia DAM logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $damLoggerChannel;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);

    $instance->httpClient = $container->get('http_client');
    $instance->authService = $container->get('acquia_dam.authentication_service');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->imageStyleHelper = $container->get('acquia_dam.image_style_support');
    $instance->damLoggerChannel = $container->get('logger.channel.acquia_dam');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'acquia_dam_config';
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
    $config = $this->config('acquia_dam.settings');

    if ($config->get('domain')) {
      $form['site_auth'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Site authentication'),
      ];

      $form['site_auth']['actions']['#type'] = 'actions';

      if (!$this->authService->isSiteAuthenticated()) {
        $form['site_auth']['desc'] = [
          '#markup' => '<p>' . $this->t('Authenticate your site with an Acquia DAM user account. It is recommended to use an Acquia DAM user account that has only view and download permissions.') . '</p>',
        ];

        $auth_link = Url::fromRoute('acquia_dam.site_auth')->setAbsolute()->toString();
        $form['site_auth']['actions']['authenticate_site'] = [
          '#type' => 'link',
          '#title' => 'Authenticate site',
          '#url' => Url::fromUri($this->authService->generateAuthUrl($auth_link)),
          '#cache' => [
            'max-age' => 0,
          ],
          '#attributes' => [
            'class' => ['button button--primary'],
          ],
        ];
      }
      else {
        $form['site_auth']['desc'] = [
          '#markup' => '<p>' . $this->t('Site is authenticated with Acquia DAM.') . '</p>',
        ];

        $form['site_auth']['actions']['authenticate_site'] = [
          '#type' => 'link',
          '#title' => 'Disconnect site',
          '#url' => Url::fromRoute('acquia_dam.disconnect_site')->setAbsolute(),
          '#cache' => [
            'max-age' => 0,
          ],
          '#attributes' => [
            'class' => ['button', 'button--danger'],
          ],
        ];
      }
    }

    $form['configuration'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Acquia DAM configuration details'),
    ];

    $form['configuration']['domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Acquia DAM domain'),
      '#default_value' => $config->get('domain'),
      '#description' => $this->t('example: demo.acquiadam.com'),
      '#required' => TRUE,
    ];

    $form['configuration']['actions']['#type'] = 'actions';
    $form['configuration']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save DAM configuration'),
      '#button_type' => 'primary',
    ];

    $image_styles = [];
    foreach ($this->imageStyleHelper->getImageStylesBySupportStatus()['supported'] as $styles) {
      $image_styles[$styles->id()] = $styles->label();
    }

    $is_media_acquiadam_installed = $this->moduleHandler->moduleExists('media_acquiadam');
    $form['configuration']['auth_type'] = [
      '#type' => 'radios',
      '#access' => !$is_media_acquiadam_installed,
      '#default_value' => $config->get('auth_type'),
      '#options' => [
        'authorization_code' => $this->t('Authorization code (standard)'),
        'refresh_token' => $this->t('Refresh token (more secure)'),
      ],
      'authorization_code' => [
        '#description' => $this->t('Standard connection using long-lived access token.'),
        '#disabled' => $this->authService->isUsingRefreshToken(),
      ],
      'refresh_token' => [
        '#description' => $this->t('Requires support assistance to enable an integration with refresh token support. Refresh tokens improve security by using short-lived access tokens. Disabled if Media Acquia DAM module is installed.'),
      ],
    ];

    $form['configuration']['client_id'] = [
      '#type' => 'textfield',
      '#access' => !$is_media_acquiadam_installed,
      '#title' => $this->t('OAuth client ID'),
      '#default_value' => $config->get('client_id') ?? '',
      '#description' => $this->t('The client ID for your OAuth integration.'),
      '#states' => [
        'visible' => [
          ':input[name="auth_type"]' => ['value' => 'refresh_token'],
        ],
      ],
    ];

    $form['configuration']['client_secret'] = [
      '#type' => 'textfield',
      '#access' => !$is_media_acquiadam_installed,
      '#title' => $this->t('OAuth client secret'),
      '#default_value' => $config->get('client_secret') ?? '',
      '#description' => $this->t('The client secret for your OAuth integration.'),
      '#states' => [
        'visible' => [
          ':input[name="auth_type"]' => ['value' => 'refresh_token'],
        ],
      ],
    ];

    $form['configuration']['image_style'] = [
      '#type' => 'checkboxes',
      '#description' => $this->t('Select which image style should be allowed to render Acquia DAM image assets.'),
      '#description_display' => 'before',
      '#title' => $this->t('Allowed image styles'),
      '#options' => $image_styles,
      '#default_value' => $config->get('allowed_image_styles') ?? [],
      '#size' => 6,
      '#multiple' => TRUE,
    ];

    return $form;
  }

  /**
   * Save allowed image styles.
   *
   * @param array $form
   *   Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   */
  public function saveImageStyles(array &$form, FormStateInterface $form_state): void {
    $checkboxes = $form_state->getValue('image_style') ?? [];

    $this->config('acquia_dam.settings')
      ->set('allowed_image_styles', array_filter($checkboxes))
      ->save();
  }

  /**
   * Validate that the provided values are valid or nor.
   *
   * @param array $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state instance.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $domain = Xss::filter($form_state->getValue('domain'));
    if (!$domain) {
      $form_state->setErrorByName(
        'domain',
        $this->t('Provided domain is not valid.')
      );

      return;
    }

    $domain = preg_replace('#^https?://#', '', $domain);
    $domain = rtrim($domain, '/');
    $form_state->setValue('domain', $domain);
    $this->validateDomain($form_state);
  }

  /**
   * Validates that the provided domain is valid.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state instance.
   */
  private function validateDomain(FormStateInterface $form_state): void {
    $domain = $form_state->getValue('domain');

    // Generate the ping endpoint non-SSL URL of the configured domain.
    $endpoints = [
      'http' => 'http://' . $domain . '/collective.ping',
      'https' => 'https://' . $domain . '/collective.ping',
    ];

    foreach ($endpoints as $protocol => $endpoint) {
      try {
        // Process the response of the HTTP request.
        $response = $this->httpClient->get($endpoint);
        $status = $response->getStatusCode();

        // If ping returns a successful HTTP response, display a confirmation
        // message.
        if ($status == '200') {
          $this->messenger()->addStatus($this->t('Validating domain (@protocol): OK!', [
            '@protocol' => $protocol,
          ]));
        }
        else {
          // If failed, display an error message.
          $form_state->setErrorByName('domain', $this->t('Validating domain (@protocol): @status', [
            '@protocol' => $protocol,
            '@status' => $status,
          ]));
        }
      }
      catch (TransferException $e) {
        $form_state->setErrorByName(
          'domain',
          $this->t('Unable to connect to the domain. Please verify the domain is entered correctly.')
        );
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $current_domain = $this
      ->config('acquia_dam.settings')
      ->get('domain');

    $new_domain = $form_state->getValue('domain');
    // Handles domain swap when the site is already authenticated.
    if ($new_domain !== $current_domain && $this->authService->isSiteAuthenticated()) {
      try {
        $this->authService->disconnectSiteAndUsers();
      }
      catch (\Exception $exception) {
        $this->messenger()->addMessage('Something went wrong during domain change. Please contact the site administrator for more information.');
        $this->damLoggerChannel->error('Error during domain change: ' . $exception->getMessage());
        return;
      }

      $this->messenger()->addMessage('Previous domain has been disconnected successfully. All user registration has been cancelled.');
    }

    $this->config('acquia_dam.settings')
      ->set('domain', $new_domain)
      ->save();
    if ($this->moduleHandler->moduleExists('media_acquiadam')) {
      $this->configFactory->getEditable('media_acquiadam.settings')
        ->set('domain', $new_domain)
        ->save();
    }

    $auth_type = $form_state->getValue('auth_type') ?? 'authorization_code';
    $this->config('acquia_dam.settings')
      ->set('auth_type', $auth_type)
      ->set('client_id', $auth_type === 'refresh_token' ? $form_state->getValue('client_id') : '')
      ->set('client_secret', $auth_type === 'refresh_token' ? $form_state->getValue('client_secret') : '')
      ->save();

    // Authenticate only if the site isn't authenticated yet.
    // On domain change  it will be disconnected first.
    if (!$this->authService->isSiteAuthenticated()) {
      $auth_link = Url::fromRoute('acquia_dam.site_auth')->setAbsolute()->toString();
      $url = Url::fromUri($this->authService->generateAuthUrl($auth_link));
      $form_state->setResponse(new TrustedRedirectResponse($url->toString()));
    }

    $this->saveImageStyles($form, $form_state);
    parent::submitForm($form, $form_state);
  }

}
