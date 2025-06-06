<?php

declare(strict_types=1);

namespace Drupal\acquia_dam;

use Drupal\acquia_dam\Entity\ManagedImageField;
use Drupal\acquia_dam\Plugin\media\Source\Asset;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\media\MediaTypeInterface;

/**
 * Media type resolver for assets.
 */
final class MediaTypeResolver {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * Constructs a new MediaTypeResolver object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Resolves the media type for an asset.
   *
   * @param array $asset
   *   The asset data.
   * @param array $bundle
   *   The media type list.
   *
   * @return \Drupal\media\MediaTypeInterface|null
   *   The media type, or NULL if one cannot be resolved.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function resolve(array $asset, array $bundles = []): ?MediaTypeInterface {
    $result = NULL;

    if (!isset($asset['file_properties'])) {
      return $result;
    }
    $mapping = [
      'ft' => 'format_type',
      'ff' => 'format',
    ];

    // 1. Run a simple mapping logic based on media source plugin definitions.
    foreach ($this->getMediaTypes() as $media_type) {
      $source = $media_type->getSource();
      $definition = $source->getPluginDefinition();
      if (!isset($mapping[$definition['asset_search_key']])) {
        continue;
      }
      $property_name = $mapping[$definition['asset_search_key']];
      $property_value = $asset['file_properties'][$property_name] ?? '';

      // Compare the data type the actual source plugin can handle vs. the type
      // of the current asset in question.
      if ($definition['asset_search_value'] === $property_value && (empty($bundles) || in_array($media_type->id(), $bundles))) {
        $result = $media_type;
        break;
      }
    }

    // 2. Refine the decision based on the conceptual difference between Widen
    // and Drupal. Widen considers, for example, '.svg/.tiff/.ai' files as an
    // image, so a media item of type 'Image' would be created. With on-site
    // storing enabled, reference to the locally saved asset file would be
    // stored in an 'Image' field. In this case, Drupal is unable to apply
    // image styles on such files when rendering the field. Therefore override
    // here the media type based on what an 'Image' field can render.
    if ($result instanceof MediaTypeInterface && $result->id() === 'acquia_dam_image_asset') {
      $media_type_config = $result->get('source_configuration');

      if (isset($media_type_config['download_assets']) && $media_type_config['download_assets']) {
        $file_extension = pathinfo($source->getValidFilename($asset), PATHINFO_EXTENSION);

        // At this point there is no media item existing yet for the asset, so
        // we need to instantiate a temporary one to read its bundle field
        // definition and its settings from.
        $accepted_file_extensions = $this->entityTypeManager->getStorage('media')
          ->create(['bundle' => 'acquia_dam_image_asset'])
          ->getFieldDefinition(ManagedImageField::MANAGED_IMAGE_FIELD_NAME)->getSetting('file_extensions');

        if (!in_array($file_extension, explode(' ', $accepted_file_extensions))) {
          // Rely on the generic fallback at the end.
          $result = NULL;
        }
      }
    }

    // 3. Finally fallback to the 'Generic' media type as a last resort.
    return $result ?? $this->entityTypeManager->getStorage('media_type')->load('acquia_dam_generic_asset');
  }

  /**
   * Gets media types with Asset source plugin.
   *
   * @return \Drupal\media\MediaTypeInterface[]
   *   The media types.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @phpstan-return array<string, \Drupal\media\MediaTypeInterface>
   */
  private function getMediaTypes(): array {
    static $media_types = [];

    if ($media_types === []) {
      $media_type_storage = $this->entityTypeManager->getStorage('media_type');
      $media_types = array_filter($media_type_storage->loadMultiple(), static function (MediaTypeInterface $media_type) {
        return $media_type->getSource() instanceof Asset;
      });
    }

    return $media_types;
  }

}
