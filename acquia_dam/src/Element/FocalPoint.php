<?php

namespace Drupal\acquia_dam\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides Focal Point form element.
 *
 * Related issue https://www.drupal.org/project/focal_point/issues/2657592.
 *
 * @FormElement("acquia_dam_focal_point")
 */
class FocalPoint extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#input' => TRUE,
      '#style_name' => 'thumbnail',
      '#process' => [
        [self::class, 'process'],
      ],
      '#theme_wrappers' => ['form_element'],
    ];
  }

  /**
   * Renders an element for focal point.
   *
   * @param array $element
   *   Element array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   Render array for focal point.
   */
  public static function process(array $element, FormStateInterface $form_state): array {
    if (!empty($element['#thumbnail_uri'])) {
      $element_selectors = [
        'focal_point' => 'focal-point-' . implode('-', $element['#parents']),
      ];

      // Delta set to 0, for now we insert one element at a time.
      $element['indicator'] = self::createFocalPointIndicator(0, $element_selectors);
      $element['preview_thumbnail'] = [
        '#theme' => 'image_style',
        '#style_name' => $element['#style_name'],
        '#uri' => $element['#thumbnail_uri'],
      ];

      if (!empty($element['#position'])) {
        $element['#value'] = $element['#position'];
      }

      // Add the focal point field.
      $element['focal_point'] = self::createFocalPointField($element['#name'], $element_selectors, $element['#value']);
    }

    return $element;
  }

  /**
   * Create the focal point form element.
   *
   * @param int $delta
   *   The delta of the image field widget.
   * @param array $element_selectors
   *   The element selectors to ultimately be used by javascript.
   *
   * @return array
   *   The focal point field form element.
   */
  private static function createFocalPointIndicator(int $delta, array $element_selectors): array {
    return [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['focal-point-indicator'],
        'data-selector' => $element_selectors['focal_point'],
        'data-delta' => $delta,
      ],
    ];
  }

  /**
   * Create the focal point form element.
   *
   * @param string $field_name
   *   The name of the field element for the image field.
   * @param array $element_selectors
   *   The element selectors to ultimately be used by javascript.
   * @param string $default_focal_point_value
   *   The default focal point value in the form x,y.
   *
   * @return array
   *   The preview link form element.
   */
  private static function createFocalPointField(string $field_name, array $element_selectors, string $default_focal_point_value): array {
    return [
      '#type' => 'textfield',
      '#title' => new TranslatableMarkup('Focal point'),
      '#description' => new TranslatableMarkup('Specify the focus of this image in the form "leftoffset,topoffset" where offsets are in percents. Ex: 25,75'),
      '#default_value' => $default_focal_point_value,
      '#attributes' => [
        'class' => ['focal-point', $element_selectors['focal_point']],
        'data-selector' => $element_selectors['focal_point'],
        'data-field-name' => $field_name,
      ],
      '#wrapper_attributes' => [
        'class' => ['focal-point-wrapper'],
      ],
      '#attached' => [
        'library' => ['focal_point/drupal.focal_point'],
      ],
    ];
  }

}
