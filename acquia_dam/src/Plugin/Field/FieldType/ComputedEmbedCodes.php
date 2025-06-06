<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\Plugin\Field\FieldType;

use Drupal\acquia_dam\EmbedCodeFactory;
use Drupal\acquia_dam\Entity\MediaSourceField;
use Drupal\acquia_dam\Plugin\media\Source\Asset;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\image\Entity\ImageStyle;
use Drupal\image\ImageStyleInterface;
use Drupal\media\MediaInterface;

/**
 * Computed field class to dynamically returning embed codes.
 */
final class ComputedEmbedCodes extends FieldItemList {
  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    $entity = $this->getEntity();
    if (!$entity instanceof MediaInterface) {
      return;
    }
    $source = $entity->getSource();
    if (!$source instanceof Asset) {
      return;
    }
    $asset_field = $entity->get(MediaSourceField::SOURCE_FIELD_NAME)->first();
    if (!$asset_field instanceof AssetItem) {
      return;
    }

    $asset_type = $source->getDerivativeId();
    $embed_options = EmbedCodeFactory::getSelectOptions($asset_type);
    if ($asset_type === 'image') {
      $stream_wrapper_manager = \Drupal::getContainer()->get('stream_wrapper_manager');
      $embeds = [];
      foreach (array_keys($embed_options) as $option) {
        $uri = "acquia-dam://$asset_field->asset_id/$asset_field->version_id";
        if ($option !== 'original') {
          $style = ImageStyle::load($option);
          if (!$style instanceof ImageStyleInterface) {
            continue;
          }
          $uri = $style->buildUri($uri);
        }
        $embeds[$option] = [
          'href' => $stream_wrapper_manager->getViaUri($uri)->getExternalUrl(),
        ];
      }
    }
    else {
      $embeds = array_map(static function (array $embed) {
        return [
          'href' => $embed['url'],
        ];
      }, $source->getMetadata($entity, 'embeds'));
      unset($embeds['templated']);
    }
    $this->list[0] = $this->createItem(0, $embeds);
  }

}
