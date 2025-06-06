<?php

declare(strict_types=1);

namespace Drupal\acquia_dam_test;

use Drupal\acquia_dam_test\HttpClientMiddleware\MockedResponseMiddleware;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Service provider to register HTTP client middleware for testing.
 */
final class AcquiaDamTestServiceProvider implements ServiceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    // Add the HTTP request middleware to Guzzle.
    $container
      ->register('acquia_dam_test.http_client.middleware', MockedResponseMiddleware::class)
      ->addArgument(new Reference('logger.channel.default'))
      ->addTag('http_client_middleware');
  }

}
