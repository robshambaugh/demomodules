<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\Plugin\Field\FieldFormatter;

use Drupal\acquia_dam\EmbedCodeFactory;
use Drupal\acquia_dam\Plugin\media\Source\Asset;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\media\Entity\MediaType;
use Drupal\media\MediaInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field formatter to render media assets with their embed code from the DAM.
 *
 * @FieldFormatter(
 *   id = "acquia_dam_embed_code",
 *   label = @Translation("Embed code"),
 *   field_types = {
 *     "acquia_dam_asset"
 *   }
 * )
 */
final class EmbedCodeFormatter extends FormatterBase {

  /**
   * The media type storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $mediaTypeStorage;

  /**
   * Constructs an EmbedCodeFormatter object.
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
   * @param \Drupal\Core\Entity\EntityStorageInterface $media_type_storage
   *   The media type storage.
   */
  public function __construct(
    string $plugin_id,
    mixed $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    string $label,
    string $view_mode,
    array $third_party_settings,
    EntityStorageInterface $media_type_storage
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->mediaTypeStorage = $media_type_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new EmbedCodeFormatter(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager')->getStorage('media_type'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];

    $media = $items->getEntity();
    assert($media instanceof MediaInterface);
    $embed = $media->getSource()->getMetadata($media, 'embeds');

    if ($embed === NULL) {
      return $elements;
    }

    $elements[0] = [
      '#type' => 'container',
      '#theme_wrappers' => ['container__acquia_dam_asset'],
      'embed' => EmbedCodeFactory::renderAsset(
        $this->getSetting('embed_style'),
        $media,
        $this->getSetting('thumbnail_width')),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'embed_style' => 'remotely_referenced_thumbnail_image',
      'thumbnail_width' => 150,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $build = [];
    $embed_style = $this->getSetting('embed_style');

    foreach (EmbedCodeFactory::getSelectOptions() as $embed_codes) {
      foreach ($embed_codes as $embed_key => $embed_label) {
        if ($embed_key === $embed_style) {
          $build['embed_style'] = $this->t('Embed style: %embed_style_label', [
            '%embed_style_label' => $embed_label,
          ]);
        }
      }
    }

    if ($embed_style === 'remotely_referenced_thumbnail_image' &&
      $thumbnail_width = $this->getSetting('thumbnail_width')) {
      $build['thumbnail_width'] = $this->t('Thumbnail width: %thumbnail_width <em>px</em>', [
        '%thumbnail_width' => $thumbnail_width,
      ]);
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $asset_type = $this->getAssetType($form);
    $element['embed_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Embed style'),
      '#default_value' => $this->getSetting('embed_style'),
      '#options' => EmbedCodeFactory::getSelectOptions($asset_type),
    ];
    $element['thumbnail_width'] = [
      '#type' => 'number',
      '#title' => $this->t('Thumbnail width'),
      '#default_value' => $this->getSetting('thumbnail_width'),
      '#min' => 100,
      '#max' => 500,
      '#description' => $this->t('DAM assets of various types are visually similar by different levels. To ease their distinguishing, site administrators can customize the size of their thumbnails here.'),
      '#field_suffix' => 'px',
      '#states' => [
        'visible' => [
          'select[name$="embed_style]"]' => ['value' => 'remotely_referenced_thumbnail_image'],
        ],
      ],
    ];

    return $element;
  }

  /**
   * Returns the DAM asset type from the media type.
   *
   * @param array $form
   *   Form array.
   *
   * @return string
   *   Asset type like "pdf", "video", etc.
   */
  protected function getAssetType(array $form): string {
    $media_type_id = $form['#bundle'] ?? '';
    $media_type = $this->mediaTypeStorage->load($media_type_id);
    return $media_type ? $media_type->getSource()->getDerivativeId() : '';
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    if ($field_definition->getTargetEntityTypeId() !== 'media') {
      return FALSE;
    }

    if (parent::isApplicable($field_definition)) {
      if ($media_type_id = $field_definition->getTargetBundle()) {
        return MediaType::load($media_type_id)->getSource() instanceof Asset;
      }
    }

    return FALSE;
  }

}
