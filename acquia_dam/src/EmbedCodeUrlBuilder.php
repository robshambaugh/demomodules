<?php

namespace Drupal\acquia_dam;

/**
 * Create widen specific image url.
 */
final class EmbedCodeUrlBuilder {

  /**
   * Image formats available on the Widen end.
   *
   * @see \Drupal\image\Plugin\Field\FieldType\ImageItem::defaultFieldSettings()
   */
  const AVAILABLE_IMAGE_FORMATS = [
    'gif',
    'jpeg',
    'jpg',
    'png',
    'web',
#   'webp',
  ];

  /**
   * Converts image effect plugin configuration to query parameters.
   *
   * @param array $effects
   *   Array of effects from a particular style.
   * @param array $image_properties
   *   Image properties.
   * @param string $uri
   *   Image uri.
   *
   * @return array
   *   Query parameters for embed code URL.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function mapImageEffects(array $effects, array $image_properties, string $uri): array {
    $anchor_mapping = [
      'center-center' => 'c',
      'top' => 'n',
      'right-top' => 'ne',
      'right' => 'e',
      'right-bottom' => 'se',
      'bottom' => 's',
      'left-bottom' => 'sw',
      'left' => 'w',
      'left-top' => 'nw',
    ];
    $values = [];
    $values['format'] = 'web';

    foreach ($effects as $effect) {
      switch ($effect['id']) {
        case 'focal_point_scale_and_crop':
          $values['crop'] = 'yes';
          $values['w'] = $effect['data']['width'];
          $values['h'] = $effect['data']['height'];
          break;

        case 'focal_point_crop':
          if (isset($effect['data']['crop_type'])) {
            /** @var \Drupal\crop\CropInterface $crop */
            if (!$crop = \Drupal::entityTypeManager()
              ->getStorage('crop')
              ->getCrop($uri, $effect['data']['crop_type'])) {
              \Drupal::logger('acquia_dam')->error(sprintf('Cannot find crop entity for %s', $uri));
              break;
            }

            $crop_width = $effect['data']['width'];
            $crop_height = $effect['data']['height'];

            /** @var \Drupal\acquia_dam\ImageStyleHelper $image_style_helper */
            $image_style_helper = \Drupal::service('acquia_dam.image_style_support');
            // Need to recalculate the values if those are higher than 2048.
            [$calculated_with, $calculated_height] = $image_style_helper->handleLargeImages($image_properties['width'], $image_properties['height']);

            // If cropped is bigger than calculated don't do anything.
            if ($crop_width >= $calculated_with || $crop_height >= $calculated_height) {
              break;
            }

            $position = $crop->position();
            $values['crop'] = 'yes';
            $values['w'] = $crop_width;
            $values['h'] = $crop_height;
            $values['a'] =
              self::calculateAnchorPointForWiden($position['x'], $crop_width, $calculated_with)
              . ','
              . self::calculateAnchorPointForWiden($position['y'], $crop_height, $calculated_height);
          }
          break;

        case 'image_convert':
          if (in_array($effect['data']['extension'], self::AVAILABLE_IMAGE_FORMATS, TRUE)) {
            $values['format'] = $effect['data']['extension'];
          }
          break;

        case 'image_rotate':
          $values['r'] = $effect['data']['degrees'];
          if ($effect['data']['bgcolor'] !== "") {
            $values['color'] = str_replace('#', '', $effect['data']['bgcolor']);
          }
          break;

        case 'image_resize':
          $values['w'] = self::getDimensionValue($effect['data']['width']);
          $values['h'] = self::getDimensionValue($effect['data']['height']);
          break;

        case 'image_crop':
          $values['crop'] = 'yes';
          $values['k'] = $anchor_mapping[$effect['data']['anchor']];

          $values['w'] = self::getDimensionValue($effect['data']['width']);
          $values['h'] = self::getDimensionValue($effect['data']['height']);

          break;

        case 'image_scale':
          if ($effect['data']['width'] && $image_properties['aspect_ratio'] >= 1) {
            $values['w'] = self::calculateScaleDimensions(
              self::getDimensionValue($image_properties['width']),
              self::getDimensionValue($effect['data']['width']),
              $effect['data']['upscale']
            );
            break;
          }

          // If width is not set or set but ratio lower than 1.
          if ($effect['data']['height']) {
            $values['h'] = self::calculateScaleDimensions(
              self::getDimensionValue($image_properties['height']),
              self::getDimensionValue($effect['data']['height']),
              $effect['data']['upscale']
            );
          }

          break;

        case 'image_scale_and_crop':
          $values['crop'] = 'yes';
          $values['k'] = $anchor_mapping[$effect['data']['anchor']] ?? 'c';
          $values['w'] = self::calculateScaleDimensions(
            $image_properties['width'],
            self::getDimensionValue($effect['data']['width']),
            TRUE
          );
          $values['h'] = self::calculateScaleDimensions(
            $image_properties['height'],
            self::getDimensionValue($effect['data']['height']),
            TRUE
          );
          break;
      }
    }
    return $values;
  }

  /**
   * Scale dimension calculation.
   *
   * @param int $original_dimension
   *   Original image dimension.
   * @param int $scale_dimension
   *   Requested scale dimension.
   * @param bool $upscale
   *   Allow upscale.
   *
   * @return int
   *   Returns the correct scale dimension.
   */
  protected static function calculateScaleDimensions(int $original_dimension, int $scale_dimension, bool $upscale): int {
    if ($original_dimension > $scale_dimension) {
      return $scale_dimension;
    }

    if ($upscale && $scale_dimension > $original_dimension) {
      return $scale_dimension;
    }

    return $original_dimension;
  }

  /**
   * Gets a dimension value.
   *
   * @param int $number
   *   Current dimension value.
   *
   * @return int
   *   Returns the current value or maximum allowed if the current is higher.
   */
  protected static function getDimensionValue(int $number): int {
    return $number > 2048 ? 2048 : $number;
  }

  /**
   * Anchor point calculation.
   *
   * We use position coordinates to calculate the proper anchor point for WIDEN.
   * Using anchor() method returns position values since focal point does not
   * save crop with and height value.
   *
   * @param int $coordinate
   *   Crop position x or y coordinate.
   * @param int $crop_length
   *   Width or height of the cropped image.
   * @param int $origin_length
   *   Width or height of the original image.
   *
   * @return int
   *   Returns the calculated anchor point.
   */
  protected static function calculateAnchorPointForWiden(int $coordinate, int $crop_length, int $origin_length): int {
    if ($coordinate - $crop_length / 2 <= 0) {
      return 0;
    }
    if ($coordinate + $crop_length / 2 > $origin_length) {
      return $origin_length - $crop_length;
    }

    return $coordinate - $crop_length / 2;
  }

}
