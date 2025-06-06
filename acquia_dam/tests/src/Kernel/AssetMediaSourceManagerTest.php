<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_dam\Kernel;

use Drupal\acquia_dam\Plugin\media\acquia_dam\AssetMediaSourceManager;
use Drupal\Component\Plugin\Discovery\CachedDiscoveryInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Tests \Drupal\acquia_dam\Plugin\media\acquia_dam\AssetMediaSourceManager.
 *
 * @group purge
 */
final class AssetMediaSourceManagerTest extends AcquiaDamKernelTestBase {

  /**
   * Instance of the service being tested, instantiated by the container.
   *
   * @var null|\Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $assetMediaSourceManager;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['acquia_dam_test'];

  /**
   * All metadata from \Drupal\purge\Annotation\AssetMediaSource.
   *
   * @var string[]
   */
  protected $annotationFields = [
    'provider',
    'class',
    'asset_class',
    'id',
    'label',
    'default_thumbnail_filename',
    'asset_search_key',
    'asset_search_value',
  ];

  /**
   * All bundled plugins in acquia_dam, including in the test module.
   *
   * @var string[]
   */
  protected $plugins = [
    'audio',
    'documents',
    'generic',
    'image',
    'pdf',
    'spinset',
    'video',
    'assettestmediasource',
  ];

  /**
   * Set up the test.
   */
  public function setUp(): void {
    parent::setUp();
    $this->assetMediaSourceManager = new AssetMediaSourceManager(
      $this->container->get('container.namespaces'),
      $this->container->get('cache.discovery'),
      $this->container->get('module_handler')
    );
  }

  /**
   * Test if the plugin manager complies to the basic requirements.
   */
  public function testCodeContract(): void {
    $this->assertInstanceOf(AssetMediaSourceManager::class, $this->assetMediaSourceManager);
    $this->assertInstanceOf(PluginManagerInterface::class, $this->assetMediaSourceManager);
    $this->assertInstanceOf(DefaultPluginManager::class, $this->assetMediaSourceManager);
    $this->assertInstanceOf(CachedDiscoveryInterface::class, $this->assetMediaSourceManager);
  }

  /**
   * Test the plugins we expect to be available.
   */
  public function testDefinitions(): void {
    $definitions = $this->assetMediaSourceManager->getDefinitions();
    foreach ($this->plugins as $plugin_id) {
      $this->assertTrue(isset($definitions[$plugin_id]));
    }
    foreach ($definitions as $plugin_id => $md) {
      $this->assertTrue(in_array($plugin_id, $this->plugins));
    }
    foreach ($definitions as $md) {
      foreach ($md as $field => $value) {
        $this->assertTrue(in_array($field, $this->annotationFields));
      }
      foreach ($this->annotationFields as $field) {
        $this->assertTrue(isset($md[$field]));
      }
    }
  }

}
