<?php

declare(strict_types=1);

namespace Drupal\Tests\acquiadam_asset_import\Kernel;

use Drupal\acquia_dam\Entity\MediaSourceField;
use Drupal\Tests\acquia_dam\Kernel\AcquiaDamKernelTestBase;

/**
 * Tests the AssetQueueService functionality.
 *
 * @coversDefaultClass \Drupal\acquiadam_asset_import\Services\AssetQueueService
 * @group acquiadam_asset_import
 */
final class AssetQueueServiceTest extends AcquiaDamKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'acquiadam_asset_import',
  ];

  /**
   * The asset import queue.
   *
   * @var \Drupal\acquiadam_asset_import\Services\AssetQueueService
   */
  protected $assetImportQueue;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->assetImportQueue = $this->container->get('acquiadam_asset_import.asset_queue');
  }

  /**
   * Tests the addAssetsToQueue method when no categories are configured.
   *
   * @covers ::addAssetsToQueue
   */
  public function testAddAssetsToQueueNoCategories(): void {
    $this->assertEquals(
      0,
      $this->assetImportQueue->addAssetsToQueue(),
      'Expected 0 items when no media types are found.'
    );
  }

  /**
   * Tests the addAssetsToQueue method when no items are added to the queue.
   *
   * @covers ::addAssetsToQueue
   */
  public function testNoItemsToAddToQueue(): void {

    // Add the category to the assert import configuration.
    $this->config('acquiadam_asset_import.settings')
      ->set('categories', ['42d56a37-2003-4a3e-a75c-769ffb2407c5' => []])
      ->save();

    $this->assertEquals(
      0,
      $this->assetImportQueue->addAssetsToQueue(),
      'Expected 0 items when no media types are found.'
    );

    // Create an Acquia DAM audio media type.
    $this->createAudioMediaType();

    $this->assertEquals(
      0,
      $this->assetImportQueue->addAssetsToQueue(),
      'Expected 0 item when media type is present, but asset resolves to a different media type.'
    );

  }

  /**
   * Tests the add assets to queue functionality.
   *
   * @covers ::addAssetsToQueue
   */
  public function testAddAssetsToQueue(): void {

    // Add the categories to the asset import configuration,
    // but limit to a specific bundle.
    $this->config('acquiadam_asset_import.settings')
      ->set('categories', [
        '42d56a37-2003-4a3e-a75c-769ffb2407c5' => [
          'acquia_dam_audio_asset' => 'acquia_dam_audio_asset',
        ],
      ])
      ->save();

    // Create an Acquia DAM video media type.
    $this->createVideoMediaType();

    $this->assertEquals(
      0,
      $this->assetImportQueue->addAssetsToQueue(),
      'Expected 0 item to be added to the queue.'
    );

    // Update the configuration but remove the bundle filter.
    $this->config('acquiadam_asset_import.settings')
      ->set('categories', [
        '42d56a37-2003-4a3e-a75c-769ffb2407c5' => [],
      ])
      ->save();

    $this->assertEquals(
      1,
      $this->assetImportQueue->addAssetsToQueue(),
      'Expected 1 item to be added to the queue.'
    );

    $this->assertEquals(
      0,
      $this->assetImportQueue->addAssetsToQueue(),
      'Expected 0 item, when trying to add duplicate item to the queue.',
    );

  }

  /**
   * Tests the add assets to queue on cron during functionality.
   *
   * @covers ::addAssetsToQueue
   */
  public function testProcessItemsOnCron(): void {
    // Create an Acquia DAM video media type.
    $this->createVideoMediaType();

    // Add the category to the assert import configuration.
    $this->config('acquiadam_asset_import.settings')
      ->set('categories', [
        '42d56a37-2003-4a3e-a75c-769ffb2407c5' => [],
      ])
      ->save();

    // Run cron to add and import item.
    $this->container->get('cron')->run();

    $medias = $this->entityTypeManager->getStorage('media')->loadMultiple();
    $media = reset($medias);
    $this->assertEquals(
      "5a070b29-4aba-4755-915c-e064bc29ceaf",
      $media->get(MediaSourceField::SOURCE_FIELD_NAME)->getString(),
    );

  }

  /**
   * Tests the getFilters method.
   *
   * @covers ::getFilters
   */
  public function testGetFilters(): void {
    // Create media types with different asset search configurations
    $video_media_type = $this->createVideoMediaType();
    $audio_media_type = $this->createAudioMediaType();

    // Test with single media type
    $filter = $this->assetImportQueue->getFilters([
      $video_media_type->id() => $video_media_type->id()
    ]);

    $this->assertEquals(
      'ft:(video)',
      $filter,
      'Expected correct filter string for video media type'
    );

    // Test with multiple media types
    $filter = $this->assetImportQueue->getFilters([
      $video_media_type->id() => $video_media_type->id(),
      $audio_media_type->id() => $audio_media_type->id(),
    ]);
    $this->assertEquals(
      'ft:(video or audio)',
      $filter,
      'Expected correct combined filter string for video and audio media types'
    );

    // Test with non-existent media type
    $filter = $this->assetImportQueue->getFilters(['non_existent_type']);
    $this->assertEquals(
      '',
      $filter,
      'Expected empty string for non-existent media type'
    );
  }

  /**
   * Data provider for buildQuery test.
   *
   * @return array
   *   Test cases.
   */
  public function buildQueryDataProvider(): array {
    return [
      'category without bundle filter' => [
        'source' => 'categories',
        'id' => '42d56a37-2003-4a3e-a75c-769ffb2407c5',
        'bundles' => [],
        'expected' => 'cat:(Testing)',
      ],
      'category with single bundle filter' => [
        'source' => 'categories',
        'id' => '42d56a37-2003-4a3e-a75c-769ffb2407c5',
        'bundles' => 'single_bundle',
        'expected' => 'cat:(Testing) ft:(video)',
      ],
      'category with multiple bundle filters' => [
        'source' => 'categories',
        'id' => '42d56a37-2003-4a3e-a75c-769ffb2407c5',
        'bundles' => 'multiple_bundles',
        'expected' => 'cat:(Testing) ft:(video or audio)',
      ],
      'asset group source' => [
        'source' => 'asset_groups',
        'id' => 'eae2014b-4264-486d-b59b-5ed126cb34c2',
        'bundles' => [],
        'expected' => 'assetgroup:(WidenQA)',
      ],
      'invalid source type' => [
        'source' => 'invalid_source',
        'id' => '42d56a37-2003-4a3e-a75c-769ffb2407c5',
        'bundles' => [],
        'expected' => null,
      ],
    ];
  }

  /**
   * Tests the buildQuery method.
   *
   * @covers ::buildQuery
   * @dataProvider buildQueryDataProvider
   */
  public function testBuildQuery(string $source, string $id, $bundles, $expected): void {
    // Use reflection to access the private buildQuery method
    $reflection = new \ReflectionClass($this->assetImportQueue);
    $method = $reflection->getMethod('buildQuery');
    $method->setAccessible(true);

    // Create media types with search configurations
    $video_media_type = $this->createVideoMediaType();
    $audio_media_type = $this->createAudioMediaType();

    // Prepare bundle data based on the test case
    $bundleData = [];
    if ($bundles === 'single_bundle') {
      $bundleData = [$video_media_type->id() => $video_media_type->id()];
    }
    elseif ($bundles === 'multiple_bundles') {
      $bundleData = [
        $video_media_type->id() => $video_media_type->id(),
        $audio_media_type->id() => $audio_media_type->id(),
      ];
    }

    // Execute the method and verify results
    $query = $method->invoke(
      $this->assetImportQueue,
      $source,
      $id,
      $bundleData
    );

    $this->assertSame(
      $expected,
      $query,
      "Expected $query for $source source type"
    );
  }

}
