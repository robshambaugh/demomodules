<?php

/**
 * @file
 * acquia_dam.api.php
 *
 * There is a conceptual difference between Widen and Drupal. Widen considers
 * files with an extension, for example, .svg, .tiff, or .ai as an image, so a
 * media item of type 'Image' would be created by default. With on-site storing
 * enabled, reference to the locally saved asset file would be stored in an
 * 'Image' field (unlike any other media types using a 'File' field for the
 * same). In this case, administrators would be offered to set up applying
 * image styles when rendering this 'Image' field. Drupal core's Image API,
 * however, is unable to deal with such unsupported files. Therefore the
 * \Drupal\acquia_dam\MediaTypeResolver's logic fallbacks when the 'Image'
 * field is not configured to support the file extension of a given DAM asset.
 * In certain situations on some sites, it might be required to intervene in
 * this decision by extending the 'Image' field's scope of supported file
 * extensions by implementing an alter hook similar to seen below.
 */

use Drupal\acquia_dam\Entity\BundleFieldDefinition;
use Drupal\acquia_dam\Entity\ManagedImageField;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Implements hook_entity_bundle_field_info_alter().
 */
function yourmodulename_entity_bundle_field_info_alter(&$fields, EntityTypeInterface $entity_type, $bundle) {
  $field_name = ManagedImageField::MANAGED_IMAGE_FIELD_NAME;

  if ($bundle === 'acquia_dam_image_asset' && isset($fields[$field_name])) {
    assert($fields[$field_name] instanceof BundleFieldDefinition);
    // Define the desired file extensions at the end of the following line.
    $extension_list = $fields[$field_name]->getSetting('file_extensions') . ' avif';
    $fields[$field_name]->setSetting('file_extensions', $extension_list);
  }
}
