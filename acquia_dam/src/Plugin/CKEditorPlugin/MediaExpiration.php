<?php

namespace Drupal\acquia_dam\Plugin\CKEditorPlugin;

use Drupal\ckeditor\CKEditorPluginContextualInterface;
use Drupal\ckeditor\CKEditorPluginCssInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\editor\Entity\Editor;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the "acquia_dam_mediaexpiration" plugin.
 *
 * @CKEditorPlugin(
 *   id = "acquia_dam_mediaexpiration",
 *   label = @Translation("Media Embed Expiration")
 * )
 */
final class MediaExpiration extends PluginBase implements ContainerFactoryPluginInterface, CKEditorPluginContextualInterface, CKEditorPluginCssInterface {

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  private $moduleExtensionList;

  /**
   * The CSRF token service.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  private $csrfToken;

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
    $instance->csrfToken = $container->get('csrf_token');
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
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getDependencies(Editor $editor) {
    // Do not declare dependencies, we must be loaded before `drupalmedia` for
    // our hooks to register properly.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraries(Editor $editor) {
    return [
      'core/jquery',
      'core/drupal',
      'core/drupal.ajax',
      'core/once',
      'core/popperjs',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    return $this->moduleExtensionList->getPath('acquia_dam') . '/js/plugins/mediaexpiration/plugin.js';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor) {
    return [
      'drupalMedia_revisionCsrfToken' => $this->csrfToken->get('X-Drupal-AcquiaDam-CSRF-Token'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCssFiles(Editor $editor) {
    return [];
  }

}
