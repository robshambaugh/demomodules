<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\Plugin\views\argument;

use Drupal\acquia_dam\Plugin\media\Source\Asset;
use Drupal\media\MediaTypeInterface;
use Drupal\views\Plugin\views\argument\ArgumentPluginBase;
use Drupal\views_remote_data\Plugin\views\query\RemoteDataQuery;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Views argument for the asset type from a media type in media library.
 *
 * @ViewsArgument("acquia_dam_asset_type")
 */
final class AssetTypeArgument extends ArgumentPluginBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function query($group_by = FALSE): void {
    assert($this->query instanceof RemoteDataQuery);
    $media_type_storage = $this->entityTypeManager->getStorage('media_type');
    $media_type = $media_type_storage->load($this->argument);
    if ($media_type instanceof MediaTypeInterface) {
      $source = $media_type->getSource();
      $definition = $source->getPluginDefinition();
      if ($source instanceof Asset) {
        // Remap the argument value from the media type ID to file type value
        // that is the derivative ID from the plugin.
        $this->argument = $definition['asset_search_value'];
      }
    }

    // Remap the property value based on the asset_search_key.
    $this->query->addWhere(
      '0',
      $definition['asset_search_key'] ?? 'ft',
      $this->argument,
      '='
    );
  }

}
