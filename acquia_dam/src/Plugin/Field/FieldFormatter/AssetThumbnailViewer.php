<?php

namespace Drupal\acquia_dam\Plugin\Field\FieldFormatter;

use Drupal\acquia_dam\Plugin\media\Source\Asset;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Displays a media thumbnail by hotlinking the image file directly from Widen.
 *
 * @FieldFormatter(
 *   id = "acquia_dam_thumbnail",
 *   label = @Translation("Acquia DAM Image Thumbnail"),
 *   field_types = {
 *     "image"
 *   }
 * )
 *
 * @deprecated in acquia_dam:1.1.0 and is removed from acquia_dam:1.2.0.
 *   Functionality of directly linking remote thumbnail images from Widen now
 *   is taken over by the following combination of configuration:
 *   - A field instance of `acquia_dam_asset` type (usually the one provided
 *     by `Drupal\acquia_dam\Entity\MediaSourceField`) +
 *   - Its default `acquia_dam_embed_code` formatter provided by
 *     `Drupal\acquia_dam\Plugin\Field\FieldFormatter\EmbedCodeFormatter` +
 *   - Its default `remotely_referenced_thumbnail_image` universal embed style
 *
 * @see https://www.drupal.org/node/3493879
 */
final class AssetThumbnailViewer extends FormatterBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'thumbnail_size' => '300px',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    $sizes = [
      '125px' => '125px',
      '160px' => '160px',
      '300px' => '300px',
      '600px' => '600px',
      '2048px' => '2048px',
    ];
    $elements['thumbnail_size'] = [
      '#title' => $this->t('Image size'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('thumbnail_size'),
      '#options' => $sizes,
    ];
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $size_settings = $this->getSetting('thumbnail_size');
    if (isset($size_settings)) {
      $summary[] = $this->t('DAM Image size: @size', ['@size' => $size_settings]);
    }
    else {
      $summary[] = $this->t('DAM Image style: 300px');
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode = NULL): array {
    $elements = [];
    $parent = $items->getEntity();
    if ($parent->getSource() instanceof Asset) {
      $elements[0] = [
        '#theme' => 'image',
        '#width' => $this->getSetting('thumbnail_size'),
        '#uri' => $parent->getSource()->getMetadata($parent, 'thumbnail_uri'),
        '#alt' => $this->t('@filename preview', [
          '@filename' => $parent->getName(),
        ]),
      ];
    }
    return $elements;

  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    // Only run on our media type + field.
    if ($field_definition->getTargetEntityTypeId() !== 'media') {
      return FALSE;
    }

    if ($field_definition->getName() !== 'thumbnail') {
      return FALSE;
    }

    return TRUE;
  }

}
