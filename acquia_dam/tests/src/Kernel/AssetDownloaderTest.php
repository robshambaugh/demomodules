<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_dam\Kernel;

use Drupal\acquia_dam\AssetDownloader;
use Drupal\acquia_dam\AssetRepository;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;

/**
 * Tests the AssetDownloader functionality.
 *
 * @coversDefaultClass \Drupal\acquia_dam\AssetDownloader
 * @group acquia_dam
 */
class AssetDownloaderTest extends AcquiaDamKernelTestBase {

  use MediaTypeCreationTrait {
    createMediaType as drupalCreateMediaType;
  }

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'acquia_dam',
    'media_test_source',
  ];

  /**
   * The asset import queue.
   *
   * @var \Drupal\acquia_dam\AssetRepository
   */
  protected $assetRepository;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    /** @var \Drupal\acquia_dam\AssetRepository $instance */
    $instance = $this->container->get('acquia_dam.asset_repository');
    self::assertInstanceOf(AssetRepository::class, $instance);
    $this->assetRepository = $instance;
  }

  /**
   * Tests the buildBatch method.
   *
   * @covers ::buildBatch
   */
  public function testBuildBatch(): void {
    $image = $this->createImageMediaType();

    // Import assets.
    $this->assetRepository->import([
      "f2a9c03d-3664-477c-8013-e84504ed5adc",
    ]);

    $this->assertEquals(0, $this->assetRepository->countLocalAssets($image),
      "When assets exist but asset files doesn't exist locally.",
    );

    AssetDownloader::buildBatch($image);

    // Start the batch processing.
    $batch = &batch_get();
    $batch['progressive'] = FALSE;
    batch_process();

    $this->assertEquals(1, $this->assetRepository->countLocalAssets($image),
      "When assets exist and asset files exist locally.",
    );
  }

}
