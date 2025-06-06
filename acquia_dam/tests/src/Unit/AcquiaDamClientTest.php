<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_dam\Unit;

use Drupal\acquia_dam\Client\AcquiaDamClient;
use Drupal\Component\Datetime\Time;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Test the Acquia DAM client connection and assests.
 *
 * @group acquia_dam
 */
final class AcquiaDamClientTest extends UnitTestCase {

  /**
   * Tests getting an asset from the client leverages caching.
   */
  public function testGetAsset(): void {
    $mock_data = file_get_contents(__DIR__ . '/../../fixtures/a56fb261-8ad5-4e0d-8323-0e8a3659ed39.json');
    self::assertNotFalse($mock_data);
    $backend = $this->createMock(CacheBackendInterface::class);
    $backend->expects($this->exactly(2))
      ->method('get')
      ->with('asset:foo')
      ->willReturn(FALSE, (object) [
        'data' => $mock_data,
      ]);
    $backend->expects($this->once())
      ->method('set')
      ->with('asset:foo', $mock_data, 1649256864, [
        'acquia-dam-asset',
        'acquia-dam-asset:foo',
        'acquia-dam-asset:foo:bar',
      ]);

    $time = new Time(new RequestStack());
    $config = $this->createMock(ImmutableConfig::class);
    $logger = $this->createMock(LoggerInterface::class);
    $client = new AcquiaDamClient(
      $backend,
      $time,
      $config,
      [
        'handler' => new MockHandler([
          new Response(200, [], $mock_data),
        ]),
      ],
      $logger,
      $this->createMock(MessengerInterface::class)
    );
    $first = $client->getAsset('foo', 'bar');
    self::assertEquals(Json::decode($mock_data), $first);
    $second = $client->getAsset('foo', 'bar');
    self::assertEquals(Json::decode($mock_data), $second);
  }

}
