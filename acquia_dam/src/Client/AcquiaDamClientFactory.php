<?php

namespace Drupal\acquia_dam\Client;

use Drupal\acquia_dam\AcquiadamAuthService;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Instantiates an Acquia DAM Client object.
 */
class AcquiaDamClientFactory {

  /**
   * The handler stack.
   *
   * @var \GuzzleHttp\HandlerStack
   */
  protected $stack;

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleList;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  private $cacheBackend;

  /**
   * DAM auth service.
   *
   * @var \Drupal\acquia_dam\AcquiadamAuthService
   */
  protected $authService;

  /**
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Acquia DAM logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $damLoggerChannel;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * AcquiaDamClientFactory constructor.
   *
   * @param \GuzzleHttp\HandlerStack $stack
   *   Handler stack.
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_list
   *   Module list service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend.
   * @param \Drupal\acquia_dam\AcquiadamAuthService $authService
   *   User data service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   Current user.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $loggerChannel
   *   Acquia DAM logger channel.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(HandlerStack $stack, ModuleExtensionList $module_list, CacheBackendInterface $cache_backend, AcquiadamAuthService $authService, AccountProxyInterface $currentUser, LoggerChannelInterface $loggerChannel, TimeInterface $time, ConfigFactoryInterface $configFactory, MessengerInterface $messenger) {
    $this->stack = $stack;
    $this->moduleList = $module_list;
    $this->cacheBackend = $cache_backend;
    $this->authService = $authService;
    $this->currentUser = $currentUser;
    $this->damLoggerChannel = $loggerChannel;
    $this->time = $time;
    $this->configFactory = $configFactory;
    $this->messenger = $messenger;
  }

  /**
   * Returns a AcquiaDamClient instance using site token.
   *
   * @return \Drupal\acquia_dam\Client\AcquiaDamClient
   *   DAM client.
   */
  public function getSiteClient(): AcquiaDamClient {
    return $this->getClient(TRUE);
  }

  /**
   * Returns a AcquiaDamClient instance using the current user token.
   *
   * @return \Drupal\acquia_dam\Client\AcquiaDamClient
   *   DAM client.
   */
  public function getUserClient(): AcquiaDamClient {
    return $this->getClient(FALSE);
  }

  /**
   * Returns a AcquiaDamClient instance.
   *
   * @param bool $is_site_client
   *   Parameter which tells to get a site or a user client.
   *
   * @return \Drupal\acquia_dam\Client\AcquiaDamClient
   *   Client instance.
   */
  protected function getClient(bool $is_site_client): AcquiaDamClient {
    $acquia_dam_config = $this->configFactory->get('acquia_dam.settings');

    $stack = clone $this->stack;
    // Middleware to attach Authorization header with access token.
    $stack->after('prepare_body', Middleware::mapRequest(function (RequestInterface $request) use ($is_site_client) {
      $access_token = $this->getClientAccessToken($is_site_client);
      if (isset($access_token)) {
        return $request->withHeader('Authorization', 'Bearer ' . $access_token);
      }
      return $request;
    }));

    // Middleware to handle refresh tokens if access token was not accepted.
    if ($this->authService->isUsingRefreshToken()) {
      $stack->after('prepare_body', function (callable $next) use ($is_site_client) {
        return function (RequestInterface $request, array $options = []) use ($next, $is_site_client) {
          $access_token = $this->getClientAccessToken($is_site_client);
          if (!isset($access_token)) {
            return $next($request, $options);
          }

          if (!isset($options['retries'])) {
            $options['retries'] = 0;
          }

          return $next($request, $options)->then(
            function ($value) use ($next, $request, $options, $is_site_client) {
              if ($options['retries'] > 0) {
                return $value;
              }
              if (!$value instanceof ResponseInterface) {
                return $value;
              }
              if ($value->getStatusCode() !== 401) {
                return $value;
              }

              // Only pass user id if we want to refresh the user access token.
              $user_id = $is_site_client ? NULL : $this->currentUser->id();
              $refresh_token = $is_site_client ?
                $this->authService->getRefreshToken()
                : $this->authService->getUserRefreshToken($user_id);
              $this->authService->refreshAccessToken($refresh_token, $user_id);

              return $next($request, $options);
            },
          );
        };
      });
    }

    $config = [
      'base_uri' => 'https://api.widencollective.com',
      'client-user-agent' => $this->getClientUserAgent(),
      'headers' => [
        'Content-Type' => 'application/json',
      ],
      'handler' => $stack,
    ];

    return new AcquiaDamClient($this->cacheBackend, $this->time, $acquia_dam_config, $config, $this->damLoggerChannel, $this->messenger);
  }

  /**
   * Returns Client's user agent.
   *
   * @return string
   *   User Agent.
   */
  protected function getClientUserAgent(): string {
    // Find out the module version in use.
    $module_info = $this->moduleList->getExtensionInfo('acquia_dam');
    $module_version = $module_info['version'] ?? '0.0.0';
    $drupal_version = \Drupal::VERSION;

    return 'AcquiaDam/' . $drupal_version . '-' . $module_version;
  }

  /**
   * Returns access token.
   *
   * @param bool $is_site_client
   *   Token for site or user.
   *
   * @return string|null
   *   Token.
   *
   * @throws \Exception
   *   Throws exception if it cannot get an access token.
   */
  protected function getClientAccessToken(bool $is_site_client): ?string {
    return $is_site_client ?
      $this->authService->getSiteToken()
      : $this->authService->getUserAccessToken($this->currentUser->id());
  }

}
