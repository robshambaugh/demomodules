<?php

namespace Drupal\acquia_dam\Plugin\Field\FieldFormatter;

use Drupal\acquia_dam\EmbedCodeFactory;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\image\ImageStyleStorageInterface;
use Drupal\media\Plugin\Field\FieldFormatter\MediaThumbnailFormatter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'acquia dam media thumbnail' formatter.
 */
final class AcquiaDamMediaThumbnailFormatter extends MediaThumbnailFormatter {

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a MediaThumbnailFormatter object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\image\ImageStyleStorageInterface $image_style_storage
   *   The image style entity storage handler.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator
   *   The file URL generator.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, AccountInterface $current_user, ImageStyleStorageInterface $image_style_storage, FileUrlGeneratorInterface $file_url_generator, RendererInterface $renderer) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings, $current_user, $image_style_storage, $file_url_generator, $renderer);
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('current_user'),
      $container->get('entity_type.manager')->getStorage('image_style'),
      $container->get('file_url_generator'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);
    $image_styles = $this->buildImageStyleOption();
    $description_link = Link::fromTextAndUrl(
      $this->t('Configure Image Styles'),
      Url::fromRoute('entity.image_style.collection')
    );
    $element['image_style'] = [
      '#title' => $this->t('Image style'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('image_style'),
      '#empty_option' => $this->t('None (original image)'),
      '#options' => $image_styles,
      '#description' => $description_link->toRenderable() + [
        '#access' => $this->currentUser->hasPermission('administer image styles'),
      ],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $media_items = $this->getEntitiesToView($items, $langcode);

    // Early opt-out if the field is empty.
    if (empty($media_items)) {
      return $elements;
    }

    $image_style_setting = $this->getSetting('image_style');

    /** @var \Drupal\media\MediaInterface[] $media_items */
    foreach ($media_items as $delta => $media) {
      $elements[$delta] = [
        '#theme' => 'image_formatter',
        // Limitations: When the Acquia DAM image does not have a valid
        // extension in its name, then it fails to render the image using
        // selected image styles because ImageStyle::supportsUri() expects
        // a valid file extension, and failing this sets image access to false
        // which prevents the rendering of image.
        '#item' => $media->hasField('acquia_dam_managed_image')
          ? $media->get('acquia_dam_managed_image')->first()
          : $media->get('thumbnail')->first(),
        '#item_attributes' => [
          'loading' => $this->getSetting('image_loading')['attribute'],
        ],
        '#image_style' => $this->getSetting('image_style'),
        '#url' => $this->getMediaThumbnailUrl($media, $items->getEntity()),
      ];
      // Add cacheability of each item in the field.
      $this->renderer->addCacheableDependency($elements[$delta], $media);
    }
    // Add cacheability of the image style setting.
    if ($this->getSetting('image_link') && ($image_style = $this->imageStyleStorage->load($image_style_setting))) {
      $this->renderer->addCacheableDependency($elements, $image_style);
    }

    return $elements;
  }

  /**
   * Build image style option supported by Acquia DAM.
   *
   * @return string[]
   *   The supported image styles.
   */
  private function buildImageStyleOption(): array {
    $image_styles = EmbedCodeFactory::getSelectOptions('image');
    unset($image_styles['original']);
    return $image_styles;
  }

}
