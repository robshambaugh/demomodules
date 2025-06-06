<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\editor\EditorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * CKEditor 5 media revisions plugin.
 */
final class MediaRevisions extends CKEditor5PluginDefault implements ContainerFactoryPluginInterface {

  /**
   * The CSRF token service.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  private $csrfToken;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new self(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
    $instance->csrfToken = $container->get('csrf_token');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $dynamic_plugin_config = $static_plugin_config;

    $format_id = $editor->getFilterFormat()->id();
    $dynamic_plugin_config['drupalMedia']['mediaRevisionCheckUrl'] = Url::fromRoute('acquia_dam.editor.media_revision_check', [
      'editor' => $format_id,
    ])->toString(TRUE)->getGeneratedUrl();
    $dynamic_plugin_config['drupalMedia']['mediaRevisionDialogUrl'] = Url::fromRoute('editor.media_revisions_dialog', [
      'editor' => $format_id,
    ])->toString(TRUE)->getGeneratedUrl();
    $dynamic_plugin_config['drupalMedia']['revisionCsrfToken'] = $this->csrfToken->get('X-Drupal-AcquiaDam-CSRF-Token');
    return $dynamic_plugin_config;
  }

}
