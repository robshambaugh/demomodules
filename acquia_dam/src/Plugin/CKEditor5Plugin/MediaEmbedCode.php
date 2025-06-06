<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\Plugin\CKEditor5Plugin;

use Drupal\acquia_dam\EmbedCodeFactory;
use Drupal\acquia_dam\Plugin\media\Source\Asset;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\editor\EditorInterface;
use Drupal\media\Entity\MediaType;

/**
 * CKEditor 5 asset embed code plugin.
 */
final class MediaEmbedCode extends CKEditor5PluginDefault {

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $dynamic_plugin_config = $static_plugin_config;
    $media_bundles = MediaType::loadMultiple();
    $items = [];
    $dynamic_plugin_config['drupalElementStyles']['mediaEmbedCode'] = [];
    foreach ($media_bundles as $bundle) {
      $source = $bundle->getSource();
      if (!$source instanceof Asset) {
        continue;
      }
      $embed_code_options = EmbedCodeFactory::getSelectOptions($source->getDerivativeId());
      foreach ($embed_code_options as $embed_code_id => $embed_code_label) {
        $items[] = "drupalElementStyle:mediaEmbedCode:$embed_code_id";
        $dynamic_plugin_config['drupalElementStyles']['mediaEmbedCode'][] = [
          'name' => $embed_code_id,
          'title' => $embed_code_label,
          'attributeName' => 'data-embed-code-id',
          'attributeValue' => $embed_code_id,
          'modelElements' => ['drupalMedia'],
          'modelAttributes' => [
            'drupalMediaType' => [$bundle->id()],
          ],
        ];
      }
    }
    $dynamic_plugin_config['drupalMedia']['toolbar'][] = [
      'name' => 'mediaEmbedCode:mediaEmbedCode',
      'display' => 'listDropdown',
      'defaultItem' => 'drupalElementStyle:mediaEmbedCode:original',
      'defaultText' => 'Embed code',
      'items' => $items,
    ];
    return $dynamic_plugin_config;
  }

}
