<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_dam\Kernel;

use Drupal\acquia_dam\AssetDownloader;
use Drupal\acquia_dam\AssetRepository;
use Drupal\Core\Config\MemoryStorage;

/**
 * Tests the ConfigImportSubscriber functionality.
 *
 * @coversDefaultClass \Drupal\acquia_dam\EventSubscriber\ConfigImportSubscriber
 * @group acquia_dam
 */
class ConfigImportSubscriberTest extends AcquiaDamKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'acquia_dam',
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
   * Tests the onImportTransform method.
   *
   * @covers ::onImportTransform
   */
  public function testOnImportTransform(): void {
    $image = $this->createImageMediaType();
    $this->assetRepository->import([
      "f2a9c03d-3664-477c-8013-e84504ed5adc",
    ]);

    $storage = new MemoryStorage();
    $this->copyConfig($this->container->get('config.storage'), $storage);

    // Set the download_assets to TRUE in source_configuration.
    $config_name = "media.type." . $image->id();
    $rawConfig["source_configuration"]["download_assets"] = TRUE;
    $storage->write($config_name, $rawConfig);

    $import = $this->container->get('config.import_transformer')->transform($storage);

    $config_data = $import->read($config_name);
    $this->assertFalse(
      $config_data['source_configuration']['download_assets'],
      "When assets and files doesn't exist locally, the download_assets option should be set to FALSE.",
    );

    AssetDownloader::buildBatch($image);

    // Start the batch processing.
    $batch = &batch_get();
    $batch['progressive'] = FALSE;
    batch_process();

    $import = $this->container->get('config.import_transformer')->transform($storage);

    $config_data = $import->read($config_name);
    $this->assertTRUE(
      $config_data['source_configuration']['download_assets'],
      "When assets and files exist locally, the download_assets option can be set to TRUE.",
    );

  }

}
