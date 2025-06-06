<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\Plugin\media\Source;

use Drupal\acquia_dam\Plugin\media\acquia_dam\AssetMediaSourceManager;
use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Deriver to create asset media source plugins based on supported asset types.
 */
final class AssetDeriver extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * The Asset Media Source manager.
   *
   * @var \Drupal\acquia_dam\Plugin\media\acquia_dam\AssetMediaSourceManager
   */
  protected $assetMediaSourceManager;

  /**
   * Builds the all the DAM asset types.
   *
   * @param \Drupal\acquia_dam\Plugin\media\acquia_dam\AssetMediaSourceManager $asset_media_source_manager
   *   The Media Source type manager.
   */
  public function __construct(AssetMediaSourceManager $asset_media_source_manager) {
    $this->assetMediaSourceManager = $asset_media_source_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('plugin.manager.acquia_dam.asset_media_source'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    foreach ($this->assetMediaSourceManager->getDefinitions() as $media_source_id => $media_source) {
      $this->derivatives[$media_source_id] = array_merge($base_plugin_definition, $media_source);
    }
    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
