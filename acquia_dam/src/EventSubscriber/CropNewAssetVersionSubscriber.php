<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\EventSubscriber;

use Drupal\acquia_dam\Entity\MediaSourceField;
use Drupal\acquia_dam\Event\NewAssetVersionEvent;
use Drupal\acquia_dam\ImageStyleHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to new asset versions for Crop module integration.
 */
final class CropNewAssetVersionSubscriber implements EventSubscriberInterface {

  /**
   * The image style helper.
   *
   * @var \Drupal\acquia_dam\ImageStyleHelper
   */
  private $imageStyleHelper;

  /**
   * Constructs a new CropNewAssetVersionSubscriber object.
   *
   * @param \Drupal\acquia_dam\ImageStyleHelper $image_style_helper
   *   The image style helper.
   */
  public function __construct(ImageStyleHelper $image_style_helper) {
    $this->imageStyleHelper = $image_style_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      NewAssetVersionEvent::class => 'onNewAssetVersion',
    ];
  }

  /**
   * Copies existing crop entities.
   *
   * @param \Drupal\acquia_dam\Event\NewAssetVersionEvent $event
   *   The event.
   */
  public function onNewAssetVersion(NewAssetVersionEvent $event) {
    $current_version = $event->getLatest()->get(MediaSourceField::SOURCE_FIELD_NAME)->version_id;
    $previous_version = $event->getPrevious()->get(MediaSourceField::SOURCE_FIELD_NAME)->version_id;
    $crops = $this->imageStyleHelper->getCrops($event->getPrevious());
    foreach ($crops as $crop) {
      $crop_uri = $crop->uri->value;
      if (str_contains($crop_uri, $previous_version)) {
        $crop->uri = str_replace($previous_version, $current_version, $crop_uri);
        $crop->save();
      }
    }
  }

}
