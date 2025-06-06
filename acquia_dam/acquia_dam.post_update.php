<?php

/**
 * @file
 * Post update functions for Acquia Dam.
 */

declare(strict_types=1);

use Drupal\acquia_dam\Entity\MediaSourceField;
use Drupal\acquia_dam\Plugin\Field\FieldType\AssetItem;
use Drupal\acquia_dam\Plugin\media\Source\Asset;
use Drupal\Core\Database\Database;
use Drupal\media\MediaTypeInterface;

/**
 * Populate the external_id field.
 */
function acquia_dam_post_update_existing_media_external_id_field(&$sandbox) {
  // Populate existing media.
  $media_ids = \Drupal::entityTypeManager()->getStorage('media')
    ->getQuery()
    ->accessCheck(TRUE)
    ->condition(MediaSourceField::SOURCE_FIELD_NAME, NULL, 'IS NOT NULL')
    ->execute();

  if (empty($sandbox['progress'])) {
    $sandbox['progress'] = 0;
    $sandbox['media_ids'] = array_values($media_ids);
    $sandbox['max'] = count($media_ids);
  }

  $start = $sandbox['progress'];
  $end = min($sandbox['max'], $start + 10);

  for ($i = $start; $i < $end; $i++) {
    $media_entity_id = $sandbox['media_ids'][$i];

    /** @var \Drupal\media\MediaInterface $media_entity */
    $media_entity = \Drupal::entityTypeManager()->getStorage('media')
      ->load($media_entity_id);

    $source_field = $media_entity->get(MediaSourceField::SOURCE_FIELD_NAME);
    if ($source_field->isEmpty()) {
      continue;
    }

    $source_field_item = $source_field->first();
    assert($source_field_item instanceof AssetItem);
    $source = $media_entity->getSource();

    $source_data = $source->getSourceFieldValue($media_entity);
    if (isset($source_data['external_id']) && $source_data['external_id']) {
      continue;
    }

    $external_id = $source->getMetadata($media_entity, "external_id");
    $media_entity->get(MediaSourceField::SOURCE_FIELD_NAME)->external_id = $external_id;
    $media_entity->save();
  }

  if ($sandbox['max'] > 0 && $end < $sandbox['max']) {
    $sandbox['progress'] = $end;
    $sandbox['#finished'] = ($end - 1) / $sandbox['max'];
  }
  else {
    $sandbox['#finished'] = 1;
  }
}

/**
 * New service definition crop_new_asset_version_subscriber.
 */
function acquia_dam_post_update_add_crop_new_asset_version_subscriber(&$sandbox) {
  // Empty post_update hook to rebuild service container.
}

/**
 * Implements hook_post_update_NAME().
 */
function acquia_dam_post_update_link_tracking_primary_key(&$sandbox) {
  $schema = Database::getConnection()->schema();
  if (!$schema->indexExists('acquia_dam_integration_link_tracking', 'PRIMARY')) {
    if ($schema->indexExists('acquia_dam_integration_link_tracking', 'integration_link_id')) {
      $schema->dropIndex('acquia_dam_integration_link_tracking', 'integration_link_id');
    }
    $schema->addPrimaryKey('acquia_dam_integration_link_tracking', ['integration_link_id']);
  }
}

/**
 * Implements hook_post_update_NAME().
 */
function acquia_dam_post_update_link_tracking_change_primary_key(&$sandbox) {
  $schema = Database::getConnection()->schema();
  if ($schema->indexExists('acquia_dam_integration_link_tracking', 'PRIMARY')) {
    $schema->dropPrimaryKey('acquia_dam_integration_link_tracking');
    $schema->addPrimaryKey('acquia_dam_integration_link_tracking', ['integration_link_id']);
  }
}

/**
 * Update the Acquia DAM media types.
 */
function acquia_dam_post_update_default_configuration(&$sandbox) {
  // Acquia Dam default media types.
  $dam_media_types = [
    'acquia_dam_audio_asset' => 'Acquia DAM: Audio',
    'acquia_dam_generic_asset' => 'Acquia DAM: Generic',
    'acquia_dam_documents_asset' => 'Acquia DAM: Document',
    'acquia_dam_image_asset' => 'Acquia DAM: Image',
    'acquia_dam_pdf_asset' => 'Acquia DAM: PDF',
    'acquia_dam_spinset_asset' => 'Acquia DAM: Spinset',
    'acquia_dam_video_asset' => 'Acquia DAM: Video',
  ];
  // Get only media types based on Acquia DAM source.
  $media_types = array_filter(\Drupal::entityTypeManager()->getStorage('media_type')->loadMultiple(), static function (MediaTypeInterface $media_type) {
    return $media_type->getSource() instanceof Asset;
  });
  // Update all dam media to get the latest changes.
  foreach ($media_types as $media_type) {
    // Check if media source is of type Acquia DAM.
    if (str_contains($media_type->getSource()->getPluginId(), 'acquia_dam_asset')) {
      $default_config = $media_type->getSource()->getConfiguration();
      // Update the label for Acquia DAM default media types.
      if (array_key_exists($media_type->id(), $dam_media_types)) {
        $media_type->set('label', $dam_media_types[$media_type->id()]);
      }
      // Updating the source configuration.
      $media_type->set('source_configuration', $default_config);
      $media_type->save();
    }
  }
}

/**
 * Force a cache refresh because new services were added.
 */
function acquia_dam_post_update_refresh_container(&$sandbox) {
  // Empty post-update hook.
}
