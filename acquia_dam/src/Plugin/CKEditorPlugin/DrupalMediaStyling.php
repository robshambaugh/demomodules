<?php

namespace Drupal\acquia_dam\Plugin\CKEditorPlugin;

use Drupal\ckeditor\CKEditorPluginContextualInterface;
use Drupal\ckeditor\CKEditorPluginCssInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\editor\Entity\Editor;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the "acquia_dam_drupalmediastyling" plugin.
 *
 * @CKEditorPlugin(
 *   id = "acquia_dam_drupalmediastyling",
 *   label = @Translation("Drupal Media Styling")
 * )
 */
final class DrupalMediaStyling extends PluginBase implements ContainerFactoryPluginInterface, CKEditorPluginContextualInterface, CKEditorPluginCssInterface {

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  private $moduleExtensionList;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new self(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
    $instance->moduleExtensionList = $container->get('extension.list.module');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled(Editor $editor) {
    if (!$editor->hasAssociatedFilterFormat()) {
      return FALSE;
    }

    // Automatically enable this plugin if the text format associated with this
    // text editor uses the media_embed filter.
    $filters = $editor->getFilterFormat()->filters();
    return $filters->has('media_embed') && $filters->get('media_embed')->status;
  }

  /**
   * {@inheritdoc}
   */
  public function isInternal() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getDependencies(Editor $editor) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraries(Editor $editor) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCssFiles(Editor $editor) {
    return [
      $this->moduleExtensionList->getPath('acquia_dam') . '/css/plugins/ckeditor.drupalmedia.css',
      $this->moduleExtensionList->getPath('acquia_dam') . '/css/acquia-dam-expired-assets.css',
    ];
  }

}
