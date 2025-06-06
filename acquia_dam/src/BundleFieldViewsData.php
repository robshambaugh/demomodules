<?php

namespace Drupal\acquia_dam;

use Drupal\acquia_dam\Entity\BundleFieldDefinition;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\views\EntityViewsData;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Views data definitions for bundle fields.
 *
 * Drupal core only registers Field Config fields and not other bundle fields
 * that have been programmatically added.
 *
 * @todo remove after #2898635
 * @see https://www.drupal.org/project/drupal/issues/2898635
 * @see https://git.drupalcode.org/project/commerce/-/blob/8.x-2.x/src/CommerceEntityViewsData.php
 */
class BundleFieldViewsData extends EntityViewsData {

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  private $entityTypeBundleInfo;

  /**
   * The table mapping.
   *
   * @var \Drupal\Core\Entity\Sql\TableMappingInterface
   */
  private $tableMapping;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $instance = parent::createInstance($container, $entity_type);
    $instance->entityTypeBundleInfo = $container->get('entity_type.bundle.info');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = [];

    $this->tableMapping = $this->storage->getTableMapping();
    $entity_type_id = $this->entityType->id();

    $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
    foreach (array_keys($bundles) as $bundle) {
      $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);
      foreach ($field_definitions as $field_definition) {
        if ($field_definition instanceof BundleFieldDefinition) {
          $this->addBundleFieldData($data, $field_definition);
        }
      }
    }

    return $data;
  }

  /**
   * Adds views data for the given bundle field.
   *
   * Based on views_field_default_views_data(), which is only invoked
   * for configurable fields.
   *
   * Assumes that the bundle field is not shared between bundles, since
   * the bundle plugin API doesn't support that.
   *
   * @param array $data
   *   The views data.
   * @param \Drupal\acquia_dam\Entity\BundleFieldDefinition $bundle_field
   *   The bundle field.
   */
  protected function addBundleFieldData(array &$data, BundleFieldDefinition $bundle_field) {
    $this->tableMapping = $this->storage->getTableMapping();
    $field_name = $bundle_field->getName();
    $entity_type_id = $this->entityType->id();
    $base_table = $this->getViewsTableForEntityType($this->entityType);

    $field_tables = [];
    $field_tables[EntityStorageInterface::FIELD_LOAD_CURRENT] = [
      'table' => $this->tableMapping->getDedicatedDataTableName($bundle_field),
      'alias' => "{$entity_type_id}__{$field_name}",
    ];

    $table_alias = $field_tables[EntityStorageInterface::FIELD_LOAD_CURRENT]['alias'];
    $data[$table_alias]['table']['group'] = $this->entityType->getLabel();
    $data[$table_alias]['table']['join'][$base_table] = [
      'table' => $this->tableMapping->getDedicatedDataTableName($bundle_field),
      'left_field' => $this->entityType->getKey('id'),
      'field' => 'entity_id',
      'extra' => [
        ['field' => 'deleted', 'value' => 0, 'numeric' => TRUE],
      ],
    ];

    foreach ($field_tables as $table_info) {
      $table_alias = $table_info['alias'];
      $data[$table_alias]['table']['title'] = $bundle_field->getLabel();
      $data[$table_alias]['table']['help'] = $bundle_field->getDescription();
      $data[$table_alias]['table']['entity type'] = $this->entityType->id();
      $data[$table_alias]['table']['provider'] = $this->entityType->getProvider();

      $this->mapFieldDefinition($table_info['table'], $field_name, $bundle_field, $this->tableMapping, $data[$table_alias]);
    }
  }

}
