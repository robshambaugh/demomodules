<?php

namespace Drupal\acquia_dam\Element;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;
use Drupal\Core\Render\Markup;

/**
 * Provides widen category form element.
 *
 * @FormElement("widen_categories")
 */
class WidenCategories extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#input' => TRUE,
      '#process' => [
        [self::class, 'preRender'],
      ],
      '#theme_wrappers' => ['form_element'],
    ];
  }

  /**
   * Renders an element for widen category filter.
   *
   * @param array $element
   *   Element array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   Render array for category filter.
   */
  public static function preRender(array $element, FormStateInterface $form_state): array {
    $input_field = [
      '#type' => 'textfield',
      '#id' => $element['#id'],
      '#wrapper_attributes' => [
        'class' => [
          'widen-categories-category-select',
        ],
      ],
      '#placeholder' => t('Select category'),
      '#attributes' => [
        'readonly' => TRUE,
        'class' => [
          'form-element--type-select',
        ],
      ],
    ];
    $element['container'] = [
      '#type' => 'container',
      '#prefix' => Markup::create('<widen-categories>'),
      '#suffix' => Markup::create('</widen-categories>'),
      '#attached' => [
        'library' => ['acquia_dam/acquia_dam.widen_categories'],
      ],
      'placeholder' => $input_field,
      'category_path' => [
        '#type' => 'hidden',
        '#name' => $element['#name'],
        // Hidden values cannot receive input from form state, it's always lost.
        '#value' => NestedArray::getValue($form_state->getUserInput(), $element['#array_parents']),
      ],
    ];
    $element['#tree'] = FALSE;

    return $element;
  }

}
