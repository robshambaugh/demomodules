<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Field widget for the asset item field type.
 *
 * @FieldWidget(
 *   id = "acquia_dam_asset_item",
 *   label = @Translation("Asset IDs + remote thumbnail image"),
 *   field_types = {
 *     "acquia_dam_asset"
 *   }
 * )
 */
final class AssetItemWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $original_element = $element;
    $entity = $items->getEntity();

    // As 'remotely_referenced_thumbnail_image' is the default option for embed
    // style, not needed to define in settings.
    $element['thumbnail'] = $entity->get('acquia_dam_asset_id')->view([
      'settings' => [
        'thumbnail_width' => 300,
      ],
    ]);

    $element['asset_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Asset ID'),
      '#disabled' => TRUE,
      '#default_value' => $items[$delta]->asset_id ?? NULL,
      '#attributes' => ['class' => ['js-text-full', 'text-full']],
    ] + $original_element;

    $element['version_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version ID'),
      '#disabled' => TRUE,
      '#default_value' => $items[$delta]->version_id ?? NULL,
      '#attributes' => ['class' => ['js-text-full', 'text-full']],
    ] + $original_element;

    $element['external_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('External ID'),
      '#disabled' => TRUE,
      '#default_value' => $items[$delta]->external_id ?? NULL,
      '#attributes' => ['class' => ['js-text-full', 'text-full']],
    ] + $original_element;

    return $element;
  }

  /**
   * {@inheritdoc}
   *
   * It's tightly coupled: this FieldType below annotates as its default field
   * widget.
   *
   * @see Drupal\acquia_dam\Plugin\Field\FieldType\AssetItem
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getItemDefinition()->getFieldDefinition()->getType() === 'acquia_dam_asset';
  }

}
