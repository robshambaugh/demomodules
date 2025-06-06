<?php

namespace Drupal\acquia_dam\Form;

use Drupal\acquia_dam\ImageStyleHelper;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Alters the media edit form to add focal point element on DAM images.
 */
final class FocalPointMediaEditFormAlter implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The cached config storage.
   *
   * @var \Drupal\Core\Config\StorageCacheInterface
   */
  protected $configStorage;
  /**
   * Acquia DAM image helper service.
   *
   * @var \Drupal\acquia_dam\ImageStyleHelper
   */
  protected $imageStyleHelper;

  /**
   * Constructs a new FocalPointMediaEditFormAlter object.
   *
   * @param \Drupal\Core\Config\StorageCacheInterface $entity_type_manager
   *   The cached config storage.
   * @param \Drupal\acquia_dam\ImageStyleHelper $image_style_helper
   *   Acquia DAM image helper service.
   */
  public function __construct($config_storage, ImageStyleHelper $image_style_helper) {
    $this->configStorage = $config_storage;
    $this->imageStyleHelper = $image_style_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new self(
      $container->get('config.storage'),
      $container->get('acquia_dam.image_style_support')
    );
    $instance->setStringTranslation($container->get('string_translation'));
    return $instance;
  }

  /**
   * Alters the media edit form to add focal point element.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function formAlter(array &$form, FormStateInterface $form_state): void {
    $media = $form_state->getFormObject()->getEntity();

    /** @var \Drupal\crop\CropInterface $crop */
    if ($crops = $this->imageStyleHelper->getCrops($media)) {
      $image_styles = [];
      foreach ($crops as $crop) {
        $position = $crop->position();
        $image_properties = $media->getSource()->getMetadata($media, 'image_properties');
        $thumbnail_uri = $media->getSource()->getMetadata($media, 'thumbnail_uri');
        $relative_position = $this
          ->imageStyleHelper
          ->absoluteToRelative(
            $position['x'],
            $position['y'],
            $image_properties['width'],
            $image_properties['height']
          );

        $uri = $crop->get('uri')->first()->getValue();
        preg_match('/styles\/([\w]*)/', $uri['value'], $image_style_matches);

        if (!isset($image_style_matches[1])) {
          return;
        }

        $image_style_id = $image_style_matches[1];
        $image_style_data = $this->configStorage->read("image.style.$image_style_id");

        if (empty($image_style_data)) {
          return;
        }

        $form[$image_style_id] = [
          '#type' => 'acquia_dam_focal_point',
          '#tree' => TRUE,
          '#title' => $this->t('Focal point selection for image style: %label', ['%label' => $image_style_data['label']]),
          '#description' => $this->t('Select the point you would like to crop your media from.'),
          '#description_display' => 'before',
          '#position' => $relative_position['x'] . ',' . $relative_position['y'],
          '#thumbnail_uri' => $thumbnail_uri,
          '#style_name' => 'media_library',
        ];

        $image_styles[$crop->id()] = $image_style_id;
      }

      $form_state->set('image_styles', $image_styles);
      $form['actions']['submit']['#submit'][] = [self::class, 'updateCrop'];
    }
  }

  /**
   * Alters the media edit form submit.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public static function updateCrop(array &$form, FormStateInterface $form_state): void {
    $storage = $form_state->getStorage();
    $media = $form_state->getFormObject()->getEntity();
    /** @var \Drupal\acquia_dam\ImageStyleHelper $image_style_helper */
    $image_style_helper = \Drupal::service('acquia_dam.image_style_support');
    $values = $form_state->getValues();
    foreach ($storage['image_styles'] as $crop_id => $image_style_id) {
      if ($new_absolute_position = $values[$image_style_id]['focal_point']) {
        [$x, $y] = explode(',', $new_absolute_position, 2);
        $image_style_helper->updateCrop($x, $y, $media, $crop_id);
      }
    }
  }

}
