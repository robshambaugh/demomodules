<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Field type for storing reference to an asset in the DAM.
 *
 * @FieldType(
 *   id = "acquia_dam_asset",
 *   label = @Translation("Asset"),
 *   description = @Translation("Targets an asset in the Acquia DAM."),
 *   category = @Translation("acquia_dam"),
 *   no_ui = TRUE,
 *   default_formatter = "acquia_dam_embed_code",
 *   default_widget = "acquia_dam_asset_item"
 * )
 *
 * @property string $asset_id
 * @property string $version_id
 * @property string $external_id
 */
final class AssetItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    $properties = [];
    $properties['asset_id'] = DataDefinition::create('string')
      ->setLabel('Asset ID')
      ->setDescription('The asset identifier.')
      ->setRequired(TRUE);
    $properties['version_id'] = DataDefinition::create('string')
      ->setLabel('Version ID')
      ->setDescription('The version ID for the asset.')
      ->setRequired(FALSE);
    $properties['external_id'] = DataDefinition::create('string')
      ->setLabel('External ID')
      ->setDescription('The external ID for the asset.')
      ->setRequired(FALSE);
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition): array {
    return [
      'columns' => [
        'asset_id' => [
          'type' => 'varchar_ascii',
          'length' => 255,
        ],
        'version_id' => [
          'type' => 'varchar_ascii',
          'length' => 255,
        ],
        'external_id' => [
          'type' => 'varchar_ascii',
          'length' => 255,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName(): string {
    return 'asset_id';
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    $value = $this->get('asset_id')->getValue();
    return $value === NULL || $value === '';
  }

}
