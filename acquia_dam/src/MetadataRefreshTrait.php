<?php

namespace Drupal\acquia_dam;

use Drupal\media\MediaInterface;

/**
 * Provides trait for forcing a refresh of metadata from the DAM.
 */
trait MetadataRefreshTrait {

  /**
   * Forces metadata to be refreshed on a Media entity.
   *
   * This trait is a workaround for a core bug, and can be removed when fixed:
   * https://www.drupal.org/project/drupal/issues/2983456
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   */
  protected function forceMappedFieldRefresh(MediaInterface $media): void {
    $media_source = $media->getSource();
    $translations = $media->getTranslationLanguages();
    foreach ($translations as $langcode => $data) {
      if ($media->hasTranslation($langcode)) {
        $translation = $media->getTranslation($langcode);
        // Try to set fields provided by the media source and mapped in
        // media type config.
        foreach ($translation->bundle->entity->getFieldMap() as $metadata_attribute_name => $entity_field_name) {
          if ($translation->hasField($entity_field_name)) {
            $translation->set($entity_field_name, $media_source->getMetadata($translation, $metadata_attribute_name));
          }
        }
      }
    }
  }

}
