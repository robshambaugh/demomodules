<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_dam\Unit;

use Drupal\acquia_dam\Client\AcquiaDamClient;
use Drupal\acquia_dam\Client\AcquiaDamClientFactory;
use Drupal\acquia_dam\EventSubscriber\RemoteDataSubscriber;
use Drupal\acquia_dam\MediaTypeResolver;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\views\Plugin\views\pager\PagerPluginBase;
use Drupal\views\ViewExecutable;
use Drupal\views_remote_data\Events\RemoteDataQueryEvent;
use Psr\Log\LoggerInterface;

/**
 * Test remote subscriber and data.
 *
 * @group acquia_dam
 * @coversDefaultClass \Drupal\acquia_dam\EventSubscriber\RemoteDataSubscriber
 */
final class RemoteDataSubscriberTest extends UnitTestCase {

  /**
   * Tests the date values for the `assetIsActive` condition.
   *
   * @covers ::getAssetIsActiveCondition
   */
  public function testGetAssetIsActiveCondition(): void {
    $client_factory = $this->createMock(AcquiaDamClientFactory::class);
    $client = $this->createMock(AcquiaDamClient::class);
    $client->expects($this->once())
      ->method('search')
      ->with(
        'rd:([before 12/18/2022]) AND ed:((isEmpty) OR [after 12/17/2022])',
        '-created_date',
        10,
        0,
        [
          'asset_properties',
          'embeds',
          'file_properties',
          'metadata',
          'security',
          'thumbnails',
        ]
      )->willReturn(['total_count' => 0, 'items' => []]);
    $client_factory->expects($this->once())
      ->method('getUserClient')
      ->willReturn($client);

    $now = 1671205561;
    $tomorrow = $now + 86400;
    $time = $this->createMock(TimeInterface::class);
    $time->expects($this->once())
      ->method('getCurrentTime')
      ->willReturn($now);
    $date_formatter = $this->createMock(DateFormatterInterface::class);

    $date_formatter->expects($this->exactly(2))
      ->method('format')
      ->willReturnMap([
        [$now, 'custom', 'm/d/Y', NULL, NULL, date('m/d/Y', $now)],
        [$tomorrow, 'custom', 'm/d/Y', NULL, NULL, date('m/d/Y', $tomorrow)],
      ]);
    $sut = new RemoteDataSubscriber(
      $client_factory,
      $this->createMock(MessengerInterface::class),
      $this->createMock(LoggerInterface::class),
      $time,
      $date_formatter,
      new MediaTypeResolver(
        $this->createMock(EntityTypeManagerInterface::class),
        $this->createMock(FieldTypePluginManagerInterface::class)
      )
    );

    $view = $this->createMock(ViewExecutable::class);
    $view->method('getBaseTables')
      ->willReturn(['acquia_dam_assets' => TRUE]);
    $view->method('getPager')
      ->willReturn($this->createMock(PagerPluginBase::class));
    $event = new RemoteDataQueryEvent(
      $view, [], [], 10, 0);
    $sut->doQuery($event);
  }

}
