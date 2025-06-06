<?php

namespace Drupal\acquia_dam;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\State\State;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\UserData;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Psr\Log\LoggerInterface;

/**
 * The service to authenticate on Acquia DAM.
 */
final class AcquiadamAuthService {

  use StringTranslationTrait;

  /**
   * Cache tag that is invalidated on authorization changes.
   */
  const AUTHORIZED_CACHE_TAG = 'acquia_dam_authorized';

  /**
   * The client_id used to identify Drupal module.
   *
   * @var string
   */
  const CLIENT_ID = '3b41085e6ff4d9f87307f4418bfce7ef6ed12860.app.widen.com';

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The client_secret used to identify Drupal module.
   *
   * @var string
   */
  const CLIENT_SECRET = 'ec216e0b87f9fa5b5828da524e360196ac74ed69';

  /**
   * The field plugin manager service.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * User data.
   *
   * @var \Drupal\user\UserData
   */
  protected $userData;

  /**
   * Acquia Dam configuration.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Guzzle client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * State storage.
   *
   * @var \Drupal\Core\State\State
   */
  protected $state;

  /**
   * The cache tag invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheTagsInvalidator;

  /**
   * AuthenticationPage construct.
   *
   * @param \Drupal\user\UserData $userData
   *   User data.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   Http client.
   * @param \Drupal\Core\State\State $state
   *   State storage.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Module handler.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cache_tags_invalidator
   *   The cache tag invalidator.
   */
  public function __construct(UserData $userData, ConfigFactoryInterface $configFactory, ClientInterface $httpClient, State $state, ModuleHandlerInterface $moduleHandler, LoggerInterface $logger, CacheTagsInvalidatorInterface $cache_tags_invalidator) {
    $this->userData = $userData;
    $this->configFactory = $configFactory;
    $this->httpClient = $httpClient;
    $this->state = $state;
    $this->moduleHandler = $moduleHandler;
    $this->logger = $logger;
    $this->cacheTagsInvalidator = $cache_tags_invalidator;
  }

  /**
   * Checks if the DAM domain has been configured.
   *
   * @return bool
   *   Returns TRUE if the domain has been set.
   */
  public function isConfigured(): bool {
    return !empty($this->configFactory->get('acquia_dam.settings')->get('domain'));
  }

  /**
   * Returns TRUE if configuration is set to use refresh token.
   */
  public function isUsingRefreshToken(): bool {
    return $this->configFactory->get('acquia_dam.settings')->get('auth_type') === 'refresh_token';
  }

  /**
   * Provides the authorization link with Acquia DAM.
   *
   * @param string $return_link
   *   The url where it should redirect after the authentication.
   *
   * @return string
   *   The absolute URL used for authorization.
   */
  public function generateAuthUrl(string $return_link): string {
    $dam_settings = $this->configFactory->get('acquia_dam.settings');
    $acquia_dam_domain = $dam_settings->get('domain');
    $client_id = $this->isUsingRefreshToken() ?
      $dam_settings->get('client_id')
      : Settings::get('acquia_dam_client_id', self::CLIENT_ID);
    return $acquia_dam_domain ?
      $this->generateEndpointUrl('/allowaccess', "?client_id=$client_id&redirect_uri=$return_link")
      : '';
  }

  /**
   * Purge Acquia DAM user authorization connection.
   *
   * @param string $access_token
   *   Acquia DAM user token.
   * @param int $user_id
   *   User id.
   */
  public function cancelUserRegistration(string $access_token, int $user_id): void {
    $this->sendLogoutRequest($access_token);
    $this->userData->delete('acquia_dam', $user_id);
  }

  /**
   * Purge Acquia DAM site authorization connection.
   *
   * @param string $access_token
   *   Acquia DAM user token.
   */
  public function cancelSiteRegistration(string $access_token): void {
    $this->sendLogoutRequest($access_token);
    $this->state->delete('acquia_dam_token');
    $this->state->delete('acquia_dam_refresh_token');
    $this->cacheTagsInvalidator->invalidateTags(['acquia_dam_authorized']);
  }

  /**
   * Sends a logout request.
   *
   * @param string $access_token
   *   Access token.
   *
   * @return bool
   *   Returns TRUE on success.
   *
   * @throws \Exception
   */
  protected function sendLogoutRequest(string $access_token): bool {
    if (empty($access_token)) {
      throw new \RuntimeException('No access token was provided.');
    }

    // Initiate and process the response of the HTTP request.
    try {
      if ($this->isUsingRefreshToken()) {
        $method = 'DELETE';
        $uri = 'https://api.widencollective.com/v2/oauth/access-token';
      }
      else {
        $method = 'POST';
        $uri = $this->generateEndpointUrl('/api/rest/oauth/logout');
      }
      $this->httpClient->request($method, $uri, [
        'headers' => [
          'Authorization' => 'Bearer ' . $access_token,
        ],
      ]);
    }
    catch (ClientException $e) {
    }
    catch (ServerException $e) {
      $this->logger->error($e->getMessage());
      throw new \RuntimeException('Something wrong at the server end, try again after sometime. If the issue persist contact the site admin.');
    }

    return TRUE;
  }

  /**
   * Authenticate with Acquia DAM and saves access and refresh tokens.
   *
   * @param string $auth_code
   *   Authorization code.
   * @param int|null $user_id
   *   User id. Only provide for user authentication.
   */
  public function authenticateDam(string $auth_code, ?int $user_id = NULL): void {
    $response = $this->sendAuthRequest($auth_code);

    $this->cacheTagsInvalidator->invalidateTags(['acquia_dam_authorized']);

    $auth_info = [
      'acquia_dam_token' => $response->access_token,
      'acquia_dam_refresh_token' => $response->refresh_token ?? NULL,
      'acquia_dam_username' => $response->username ?? NULL,
    ];

    if ($user_id) {
      $this->setUserData($user_id, $auth_info);
      return;
    }

    $this->state->setMultiple($auth_info);
    if ($this->moduleHandler->moduleExists('media_acquiadam')) {
      $this->configFactory->getEditable('media_acquiadam.settings')->set('token', $response->access_token)->save();
    }

  }

  /**
   * Sends authentication request.
   *
   * @param string $auth_code
   *   Authorization code.
   *
   * @return object
   *   Response.
   */
  public function sendAuthRequest(string $auth_code): object {
    $options = [
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
      ],
    ];

    if ($this->isUsingRefreshToken()) {
      $dam_settings = $this->configFactory->get('acquia_dam.settings');
      $uri = 'https://api.widencollective.com/v2/oauth/access-token';
      $options['json'] = [
        "grant_type" => "authorization_code",
        "authorization_code" => $auth_code,
        "client_id" => $dam_settings->get('client_id'),
        "client_secret" => $dam_settings->get('client_secret'),
      ];
    }
    else {
      $uri = $this->generateEndpointUrl('/api/rest/oauth/token');
      $options['json'] = [
        'authorization_code' => $auth_code,
        'grant_type' => 'authorization_code',
      ];
      $options['auth'] = [
        Settings::get('acquia_dam_client_id', self::CLIENT_ID),
        Settings::get('acquia_dam_client_secret', self::CLIENT_SECRET),
      ];
    }

    try {
      $response = $this->httpClient->post($uri, $options);
    }
    catch (ClientException $e) {
      $this->logger->error($e->getMessage());
      throw new \RuntimeException('Something went wrong with the request, and your account can’t be connected. Contact the site administrator.');
    }
    catch (ServerException $e) {
      $this->logger->error($e->getMessage());
      throw new \RuntimeException('Something went wrong contacting Acquia DAM, and your account can’t be connected. Contact the site administrator.');
    }
    $body = json_decode($response->getBody());
    if (!isset($body->access_token)) {
      throw new \RuntimeException('Authentication response does not contain necessary information.');
    }

    return $body;
  }

  /**
   * Refreshes access token.
   *
   * @param string $refresh_token
   *   Refresh token.
   * @param int|null $user_id
   *   Which user is refreshing token. Only use if user refresh token provided.
   */
  public function refreshAccessToken(string $refresh_token, ?int $user_id = NULL) {
    try {
      $dam_settings = $this->configFactory->get('acquia_dam.settings');
      $response = $this->httpClient->post('https://api.widencollective.com/v2/oauth/access-token', [
        'json' => [
          "grant_type" => "refresh_token",
          "refresh_token" => $refresh_token,
          "client_id" => $dam_settings->get('client_id'),
          "client_secret" => $dam_settings->get('client_secret'),
        ],
        'headers' => [
          'Accept' => 'application/json',
          'Content-Type' => 'application/json',
        ],
      ]);
    }
    catch (ClientException $e) {
      $this->logger->error($e->getMessage());
      throw new \RuntimeException('Something went wrong with the request, and your account can’t be connected. Contact the site administrator.');
    }
    catch (ServerException $e) {
      $this->logger->error($e->getMessage());
      throw new \RuntimeException('Something went wrong contacting Acquia DAM, and your account can’t be connected. Contact the site administrator.');
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
    }
    $body = json_decode($response->getBody());

    $info = [
      'acquia_dam_token' => $body->access_token,
      'acquia_dam_refresh_token' => $body->refresh_token,
    ];

    if ($user_id === NULL) {
      $this->state->setMultiple($info);
      if ($this->moduleHandler->moduleExists('media_acquiadam')) {
        $this->configFactory->getEditable('media_acquiadam.settings')->set('token', $body->access_token)->save();
      }
    }
    else {
      $this->setUserData($user_id, $info);
    }
  }

  /**
   * Checks if the media library should show the authorization prompt.
   *
   * This authorization prompt is only shown to the user once and if they are
   * not authenticated to the DAM with their user account.
   *
   * @param int $user_id
   *   The user ID.
   *
   * @return bool
   *   Returns TRUE if the authorization prompt should be shown.
   */
  public function shouldShowAuthorizationPrompt(int $user_id): bool {
    if (!$this->isConfigured()) {
      return FALSE;
    }
    $seen_prompt = $this->userData->get('acquia_dam', $user_id, 'seen_prompt') ?: FALSE;
    return !$seen_prompt && !$this->isAuthenticated($user_id);
  }

  /**
   * Marks that the user has seen the authorization prompt.
   *
   * @param int $user_id
   *   The user ID.
   */
  public function markAuthorizationPromptShown(int $user_id): void {
    $this->userData->set('acquia_dam', $user_id, 'seen_prompt', TRUE);
  }

  /**
   * Checks if user has stored authentication information.
   *
   * @param int $user_id
   *   User id.
   *
   * @return bool
   *   TRUE if necessary authentication information are set in user data.
   */
  public function isAuthenticated(int $user_id): bool {
    return !empty($this->userData->get('acquia_dam', $user_id, 'account'));
  }

  /**
   * Checks if site has stored authentication information.
   *
   * @return bool
   *   TRUE if necessary authentication information are set in state storage.
   */
  public function isSiteAuthenticated(): bool {
    if (!$this->isConfigured()) {
      return FALSE;
    }

    return $this->isUsingRefreshToken() ? (bool) $this->getRefreshToken() : (bool) $this->getSiteToken();
  }

  /**
   * Returns site token from state storage.
   */
  public function getSiteToken(): ?string {
    // We do not care how we get it, it will always be on the same key.
    return $this->state->get('acquia_dam_token');
  }

  /**
   * Get token from user data.
   *
   * @return string
   *   DAM token.
   */
  public function getUserAccessToken(int $user_id): ?string {
    $acquia_dam_account = $this->getUserData($user_id);
    return $acquia_dam_account['acquia_dam_token'] ?? NULL;
  }

  /**
   * Returns user refresh token.
   */
  public function getUserRefreshToken(int $user_id): string {
    $oauth_info = $this->getUserData($user_id);
    return $oauth_info['acquia_dam_refresh_token'] ?? '';
  }

  /**
   * Returns site refresh token.
   */
  public function getRefreshToken(): string {
    return $this->state->get('acquia_dam_refresh_token') ?? '';
  }

  /**
   * Sets data in user_data.
   *
   * @param int $user_id
   *   User Id.
   * @param array $data
   *   Data array to store on user object.
   */
  public function setUserData(int $user_id, array $data): void {
    if ($this->moduleHandler->moduleExists('media_acquiadam')) {
      $account = [
        'acquiadam_username' => $data['acquia_dam_username'],
        'acquiadam_token' => $data['acquia_dam_token'],
      ];
      // Store acquiadam account details.
      $this
        ->userData
        ->set('media_acquiadam', $user_id, 'account', $account);
    }

    $this->userData->set('acquia_dam', $user_id, 'account', $data);
  }

  /**
   * Gets data from user_data.
   *
   * @param int $user_id
   *   The user ID.
   *
   * @return array
   *   The user data.
   */
  public function getUserData(int $user_id): array {
    return $this->userData->get('acquia_dam', $user_id, 'account') ?: [];
  }

  /**
   * Generates DAM endpoint to ping.
   *
   * @param string $path
   *   Path string to concatenate to domain.
   * @param string $query_string
   *   Query string to concatenate to path. Optional parameter.
   *   Example: "?client_id=13".
   *
   * @return string
   *   Endpoint to send request to.
   */
  private function generateEndpointUrl(string $path, string $query_string = ''): string {
    return 'https://' . $this->configFactory->get('acquia_dam.settings')->get('domain') . $path . $query_string;
  }

  /**
   * Disconnect site before new domain register, user tokens as well.
   */
  public function disconnectSiteAndUsers(): void {
    $this->cancelSiteRegistration($this->getSiteToken());

    foreach ($this->userData->get('acquia_dam') as $uid => $account_info) {
      // Only delete from local storage.
      $this->userData->delete('acquia_dam', (int) $uid);
    }
  }

}
