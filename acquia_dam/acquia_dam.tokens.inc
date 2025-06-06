<?php

/**
 * @file
 * Builds placeholder replacement tokens for DAM media.
 */

declare(strict_types=1);

use Drupal\acquia_dam\Plugin\media\Source\Asset;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\media\MediaInterface;

/**
 * Implements hook_token_info_alter().
 */
function acquia_dam_token_info_alter(&$data) {
  $module_handler = \Drupal::moduleHandler();
  // If the Token module is not installed, we need the `media` token type
  // to exist for our tokens to work. The Token module provides token types for
  // all entities, Drupal core does not.
  if (!$module_handler->moduleExists('token')) {
    $entity_info = \Drupal::entityTypeManager()->getDefinition('media');
    assert($entity_info !== NULL);
    $data['types'][$entity_info->id()] = [
      'name' => $entity_info->getLabel(),
      'needs-data' => $entity_info->id(),
      'module' => 'media',
    ];
  }
}

/**
 * Implements hook_token_info().
 */
function acquia_dam_token_info() {
  $info = [];

  $info['tokens']['media']['embed-code'] = [
    'name' => 'Embed codes',
    'description' => 'Embed codes for the DAM asset',
    'module' => 'acquia_dam',
    'type' => 'media-embed-code',
  ];
  $info['types']['media-embed-code'] = [
    'name' => 'embed-code',
    'needs-data' => 'media-embed-code',
    'nested' => TRUE,
  ];
  $info['tokens']['media-embed-code']['url'] = [
    'name' => 'URL',
    'module' => 'acquia_dam',
  ];
  $info['tokens']['media']['asset-path'] = [
    'name' => 'Asset Path',
    'description' => 'Path of the asset',
  ];

  return $info;
}

/**
 * Implements hook_tokens().
 */
function acquia_dam_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  $replacements = [];
  // Support Token module or our replacement type if Token is not installed.
  if ($type === 'media' && isset($data['media'])) {
    $media = $data['media'];
  }
  elseif ($type === 'entity' && isset($data['entity'])) {
    $media = $data['entity'];
  }
  else {
    return $replacements;
  }

  if (!$media instanceof MediaInterface) {
    return $replacements;
  }
  $source = $media->getSource();
  if (!$source instanceof Asset) {
    return $replacements;
  }
  foreach ($tokens as $name => $original) {
    if ($name == 'asset-path') {
      [$asset_id, $version_id] = array_values($media->get('acquia_dam_asset_id')->getValue()[0]);
      $replacements[$original] = "acquia-dam://$asset_id/$version_id";
      continue;
    }
    if (strpos($name, ':') === FALSE) {
      // All of our tokens are nested.
      continue;
    }
    [$token_name, $property] = explode(':', $name, 2);
    if ($token_name === 'embed-code') {
      /** @var array<string, array<string, string>> $embeds */
      $embeds = $source->getMetadata($media, 'embeds');
      if ($property === 'url') {
        $replacements[$original] = $embeds['original']['url'];
      }
    }
  }

  return $replacements;
}
