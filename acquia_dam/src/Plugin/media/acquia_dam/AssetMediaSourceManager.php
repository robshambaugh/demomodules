<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\Plugin\media\acquia_dam;

use Drupal\acquia_dam\Plugin\media\Source\Asset;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Manages DAM Asset Media Source plugin definitions.
 *
 * Each media source definition array is set in the dervier's annotation.
 */
final class AssetMediaSourceManager extends DefaultPluginManager {

  /**
   * Constructs a new DAM Asset Media Source plugin manager.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations,.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/media/acquia_dam', $namespaces, $module_handler, 'Drupal\acquia_dam\Plugin\media\acquia_dam\MediaSourceTypeInterface', 'Drupal\acquia_dam\Annotation\AssetMediaSource');

    $this->setCacheBackend($cache_backend, 'acquia_dam_media_source_plugins');
    $this->alterInfo('acquia_dam_media_source');
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    $definitions = parent::getDefinitions();
    foreach ($definitions as &$definition) {
      $definition['asset_class'] = $definition['class'];
      $definition['class'] = Asset::class;
    }
    return $definitions;
  }

}
