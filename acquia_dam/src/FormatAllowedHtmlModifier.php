<?php

declare(strict_types=1);

namespace Drupal\acquia_dam;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\editor\EditorInterface;
use Drupal\filter\FilterFormatInterface;
use Drupal\filter\FilterPluginCollection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes filter formats for appropriate allowed_html when using media_embed.
 */
final class FormatAllowedHtmlModifier implements ContainerInjectionInterface {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  private $moduleHandler;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * Constructs a new FormatAllowedHtmlModifier object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(ModuleHandlerInterface $module_handler, EntityTypeManagerInterface $entity_type_manager) {
    $this->moduleHandler = $module_handler;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('module_handler'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Ensures the allowed_html setting for filter_html is correct.
   *
   * @param \Drupal\filter\FilterFormatInterface $filter_format
   *   The filter format.
   */
  public function process(FilterFormatInterface $filter_format): void {
    // This code isn't needed for CKEditor5.
    if ($this->moduleHandler->moduleExists('editor')) {
      $editor = $this->entityTypeManager->getStorage('editor')->load($filter_format->id());
      if ($editor instanceof EditorInterface && $editor->getEditor() === 'ckeditor5') {
        return;
      }
    }

    $filters = $filter_format->filters();
    assert($filters instanceof FilterPluginCollection);
    if ($filters->has('media_embed') && $filters->has('filter_html')) {
      $filter_html = $filters->get('filter_html');
      $configuration = $filter_html->getConfiguration();

      // Explode the `allowed_html` values into an array.
      $restrictions = $filter_html->getHTMLRestrictions();
      $restrictions['allowed']['drupal-media']['data-embed-code-id'] = TRUE;
      $restrictions['allowed']['drupal-media']['data-entity-revision'] = TRUE;

      // Convert `allowed_html` back to a string and update the configuration.
      $configuration['settings']['allowed_html'] = self::allowedHtmlToString($restrictions['allowed']);
      $filter_format->setFilterConfig('filter_html', $configuration);
    }
  }

  /**
   * Converts the allowed elements/attributes array back to a string.
   *
   * @param array $allowed
   *   The allowed elements and attributes.
   *
   * @return string
   *   The allowed_html string.
   */
  private static function allowedHtmlToString(array $allowed): string {
    $filter_html_allowed = '';
    foreach ($allowed as $element => $attributes) {
      if ($element === '*') {
        continue;
      }
      $filter_html_allowed .= '<' . $element;
      if (is_array($attributes)) {
        $filter_html_allowed .= ' ' . implode(' ', array_keys($attributes));
      }
      $filter_html_allowed .= '> ';
    }
    return $filter_html_allowed;
  }

}
