<?php

namespace Drupal\acquia_dam;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\crop\CropInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\media\MediaInterface;

/**
 * Helper functions for image style operations.
 */
class ImageStyleHelper {

  /**
   * Image effect plugin ids which are currently not supported by Acquia DAM.
   */
  const UNSUPPORTED_IMAGE_EFFECT_PLUGIN_IDS = [];

  /**
   * Acquia DAM settings.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $acquiaDamSettings;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * ImageStyleHelper constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   */
  public function __construct(ConfigFactoryInterface $configFactory, EntityTypeManagerInterface $entityTypeManager) {
    $this->acquiaDamSettings = $configFactory;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Returns all image style based on support status.
   *
   * @return array[]
   *   Supported and non-supported image styles.
   */
  public function getImageStylesBySupportStatus(): array {
    $image_styles = [
      'supported' => [],
      'not-supported' => [],
    ];

    $image_styles_storage = $this->entityTypeManager->getStorage('image_style');
    foreach ($image_styles_storage->loadMultiple() as $image_style) {
      $supported = TRUE;
      if ($effects = $image_style->getEffects()) {
        foreach ($effects as $effect) {
          if (in_array($effect->getPluginId(), self::UNSUPPORTED_IMAGE_EFFECT_PLUGIN_IDS, TRUE)) {
            $image_styles['not-supported'][] = $image_style;
            $supported = FALSE;
            break;
          }
        }
      }

      if ($supported) {
        $image_styles['supported'][] = $image_style;
      }
    }

    return $image_styles;
  }

  /**
   * Returns allowed image styles.
   *
   * @return \Drupal\image\ImageStyleInterface[]
   *   Image styles set in config or all which is supported.
   */
  public function getAllowedImageStyles(): array {
    $styles = $this->acquiaDamSettings->get('acquia_dam.settings')->get('allowed_image_styles') ?? [];

    if (empty($styles)) {
      return $this->getImageStylesBySupportStatus()['supported'];
    }

    return ImageStyle::loadMultiple($styles);
  }

  /**
   * Add image style to allowed image styles.
   *
   * @param string $style
   *   Image style id.
   */
  public function addAllowedImageStyle($style): void {
    $styles = $this->acquiaDamSettings->get('acquia_dam.settings')->get('allowed_image_styles') ?? [];
    $styles[] = $style;
    $this->acquiaDamSettings->getEditable('acquia_dam.settings')->set('allowed_image_styles', $styles)->save();
  }

  /**
   * Save crop entity for focal point.
   *
   * @param int $x
   *   Relative x position.
   * @param int $y
   *   Relative y position.
   * @param \Drupal\media\MediaInterface $media
   *   Media entity.
   * @param string $image_style
   *   Image style.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function saveCropEntity(int $x, int $y, MediaInterface $media, string $image_style): void {
    $image_properties = $media->getSource()->getMetadata($media, 'image_properties');
    $asset_id = $media->get('acquia_dam_asset_id')->asset_id;
    $version_id = $media->get('acquia_dam_asset_id')->version_id;

    /** @var \Drupal\crop\CropStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('crop');
    /** @var \Drupal\crop\CropInterface $crop */
    $crop = $storage->create([
      'type' => $this->getCropTypeOfFocalPointEffect($image_style),
      'uri' => $this->buildUriForCrop($asset_id, $version_id, $image_style),
      'entity_type' => $media->getEntityTypeId(),
      'entity_id' => $media->id(),
    ]);

    $this->relativeToAbsolute(
      $x,
      $y,
      $image_properties['width'],
      $image_properties['height'],
      $crop
    );
  }

  /**
   * Update crop entity with new position values.
   *
   * @param int $x
   *   Relative x position.
   * @param int $y
   *   Relative y position.
   * @param \Drupal\media\MediaInterface $media
   *   Media entity.
   * @param int $crop_id
   *   Id of crop entity to update.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function updateCrop(int $x, int $y, MediaInterface $media, int $crop_id): void {
    /** @var \Drupal\crop\CropInterface $crop */
    if ($crop = $this->entityTypeManager->getStorage('crop')->load($crop_id)) {
      $image_properties = $media->getSource()->getMetadata($media, 'image_properties');

      $this->relativeToAbsolute(
        $x,
        $y,
        $image_properties['width'],
        $image_properties['height'],
        $crop
      );
    }

  }

  /**
   * Gets all crop instances for DAM image.
   *
   * @param \Drupal\media\MediaInterface $media
   *   Media entity.
   *
   * @return \Drupal\crop\CropInterface[]
   *   Crop instance if there is one, NULL otherwise.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getCrops(MediaInterface $media): array {
    $result = $this->entityTypeManager
      ->getStorage('crop')
      ->getQuery()
      ->accessCheck()
      ->condition('entity_type', $media->getEntityTypeId())
      ->condition('entity_id', $media->id())
      ->execute();

    return $result ? $this->entityTypeManager->getStorage('crop')->loadMultiple($result) : [];
  }

  /**
   * Calculates absolute coordinates of position and updates crop instance.
   *
   * @param int $x
   *   Relative x position.
   * @param int $y
   *   Relative y position.
   * @param float $width
   *   Image width.
   * @param float $height
   *   Image height.
   * @param \Drupal\crop\CropInterface $crop
   *   Crop instance.
   *
   * @return \Drupal\crop\CropInterface
   *   Crop instance after position is set and saved.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function relativeToAbsolute(int $x, int $y, float $width, float $height, CropInterface $crop): CropInterface {
    [$width, $height] = $this->handleLargeImages($width, $height);

    // Focal point JS provides relative location while crop entity
    // expects exact coordinate on the original image. Let's convert.
    $x = (int) round(($x / 100.0) * $width);
    $y = (int) round(($y / 100.0) * $height);
    $crop->setPosition($x, $y);
    $crop->save();
    return $crop;
  }

  /**
   * Calculates absolute coordinates of position and updates crop instance.
   *
   * @param int $x
   *   Relative x position.
   * @param int $y
   *   Relative y position.
   * @param float $width
   *   Image width.
   * @param float $height
   *   Image height.
   *
   * @return int[]
   *   Array with 'x' and 'y' keys where values are relative position values.
   */
  public function absoluteToRelative(int $x, int $y, float $width, float $height): array {
    // Since for calculating the absolute position in relativeToAbsolute we use
    // DAM friendly width and height values, we have to call this here as well
    // to get those correctly.
    [$width, $height] = $this->handleLargeImages($width, $height);

    return [
      'x' => $width ? (int) round($x / $width * 100) : 0,
      'y' => $height ? (int) round($y / $height * 100) : 0,
    ];
  }

  /**
   * Recalculate crop position if image width or height is bigger than 2048.
   *
   * @param float $width
   *   Original width.
   * @param float $height
   *   Original height.
   *
   * @return int[]
   *   Rescaled image width and height.
   */
  public function handleLargeImages(float $width, float $height): array {
    if ($width > 2048 || $height > 2048) {
      if ($width > $height) {
        return [2048, intval(2048 / $width * $height)];
      }

      return [intval(2048 / $height * $width), 2048];
    }

    return [(int) $width, (int) $height];
  }

  /**
   * Checks if the given image style has focal point crop effect.
   *
   * @param string $image_style
   *   Imgae style id.
   *
   * @return string
   *   If it has it returns the crop_type otherwise returns an empty string.
   */
  public function getCropTypeOfFocalPointEffect(string $image_style): string {
    $image_style = ImageStyle::load($image_style);
    if (!$image_style) {
      return '';
    }

    foreach ($image_style->getEffects() as $effect) {
      if ($effect->getPluginId() === 'focal_point_crop') {
        $config = $effect->getConfiguration();
        return $config['data']['crop_type'] ?? '';
      }
    }

    return '';
  }

  /**
   * Build uri for crop entity.
   *
   * @param string $asset_id
   *   Asset id.
   * @param string $version_id
   *   Version id.
   * @param string $image_style
   *   Image style id.
   *
   * @return string
   *   Asset uri.
   */
  public function buildUriForCrop(string $asset_id, string $version_id, string $image_style) {
    $image_style = ImageStyle::load($image_style);
    if (!$image_style) {
      return '';
    }

    $uri = "acquia-dam://$asset_id/$version_id.png";
    return $image_style->buildUri($uri);
  }

}
