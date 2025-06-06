<?php

namespace Drupal\Tests\acquia_dam\Kernel;

use GuzzleHttp\HandlerStack;
use Psr\Http\Message\UriInterface;

/**
 * Tests client factory client creation.
 *
 * @group acquia_dam
 */
class DamClientFactoryTest extends AcquiaDamKernelTestBase {

  /**
   * Tests the client created with client factory.
   *
   * @throws \Exception
   */
  public function testClient() {
    $user = $this->drupalSetUpCurrentUser();
    $client_factory = $this->container->get('acquia_dam.client.factory');
    $client = $client_factory->getUserClient();

    /** @var array<string, mixed> $config */
    $config = $client->getConfig();
    $this->assertNotEmpty($config);
    $this->assertNotEmpty($config['base_uri']);

    $base_uri = $config['base_uri'];
    self::assertInstanceOf(UriInterface::class, $base_uri);
    $this->assertEquals('api.widencollective.com', $base_uri->getHost());
    $this->assertEquals('https', $base_uri->getScheme());

    $this->assertNotEmpty($config['headers']);
    $this->assertNotEmpty($config['headers']['Content-Type']);
    $this->assertNotEmpty($config['handler']);

    $token = $this->container->get('acquia_dam.authentication_service')
      ->getUserData($user->id());
    self::assertArrayHasKey('acquia_dam_token', $token);
    $this->assertEquals('application/json', $config['headers']['Content-Type']);
    $this->assertInstanceOf(HandlerStack::class, $config['handler']);
  }

  /**
   * Tests the config for the created client.
   */
  public function testClientConfig(): void {
    $client = $this->container->get('acquia_dam.client.factory')->getUserClient();
    $config = $client->getConfig();
    self::assertEquals('https://api.widencollective.com', (string) $config['base_uri']);
    self::assertStringContainsString('AcquiaDam', $config['client-user-agent']);
    self::assertEquals('application/json', $config['headers']['Content-Type']);
  }

  /**
   * Tests the refresh token.
   */
  public function testRefreshToken(): void {
    $this->config('acquia_dam.settings')
      ->set('auth_type', 'refresh_token')
      ->save();
    $expected_access_token = 'wat_laser_b1d3c61e03c65d0650550f35a330249e';
    $expected_refresh_token = 'wrt_laser_cccc84d27f899e00b27fbe25b1fcf35f';

    $this->setDamSiteToken();
    // At this point we have a random string as access and refresh tokens.
    // Mock middleware will return 401.
    $access_token = $this->state->get('acquia_dam_token');
    $refresh_token = $this->state->get('acquia_dam_refresh_token');
    $this->assertNotEquals($expected_access_token, $access_token);
    $this->assertNotEquals($expected_refresh_token, $refresh_token);

    $client = $this->container->get('acquia_dam.client.factory')->getSiteClient();
    // Following request will trigger the middleware to replace the tokens.
    $client->get('/v2/test');

    $access_token = $this->state->get('acquia_dam_token');
    $refresh_token = $this->state->get('acquia_dam_refresh_token');
    $this->assertEquals($expected_access_token, $access_token);
    $this->assertEquals($expected_refresh_token, $refresh_token);
  }

}
