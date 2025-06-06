<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\Plugin\Field\FieldFormatter;

use Drupal\acquia_dam\Entity\ImageAltTextField;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'Responsive Image' formatter.
 *
 * @FieldFormatter(
 *   id = "acquia_dam_responsive_image",
 *   label = @Translation("Acquia DAM Responsive Image"),
 *   field_types = {
 *     "acquia_dam_asset"
 *   }
 * )
 */
final class ResponsiveImageFormatter extends FormatterBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The link generator.
   *
   * @var \Drupal\Core\Utility\LinkGeneratorInterface
   */
  protected $linkGenerator;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a ResponsiveImageFormatter object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $link_generator
   *   The link generator service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, EntityTypeManagerInterface $entity_type_manager, LinkGeneratorInterface $link_generator, AccountInterface $current_user) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->entityTypeManager = $entity_type_manager;
    $this->linkGenerator = $link_generator;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager'),
      $container->get('link_generator'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'responsive_image_style' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $responsive_image_options = [];
    $responsive_image_styles = $this
      ->entityTypeManager
      ->getStorage('responsive_image_style')
      ->loadMultiple();
    uasort($responsive_image_styles, '\Drupal\responsive_image\Entity\ResponsiveImageStyle::sort');
    if ($responsive_image_styles && !empty($responsive_image_styles)) {
      foreach ($responsive_image_styles as $machine_name => $responsive_image_style) {
        if ($responsive_image_style->hasImageStyleMappings()) {
          $responsive_image_options[$machine_name] = $responsive_image_style->label();
        }
      }
    }

    $elements['responsive_image_style'] = [
      '#title' => $this->t('Responsive image style'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('responsive_image_style') ?: NULL,
      '#required' => TRUE,
      '#options' => $responsive_image_options,
      '#description' => [
        '#markup' => $this->linkGenerator->generate($this->t('Configure Responsive Image Styles'), Url::fromRoute('entity.responsive_image_style.collection')),
        '#access' => $this->currentUser->hasPermission('administer responsive image styles'),
      ],
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $responsive_image_style = $this
      ->entityTypeManager
      ->getStorage('responsive_image_style')
      ->load($this->getSetting('responsive_image_style'));
    if ($responsive_image_style) {
      $summary[] = $this->t('Responsive image style: @responsive_image_style', [
        '@responsive_image_style' => $responsive_image_style->label(),
      ]);
    }
    else {
      $summary[] = $this->t('Select a responsive image style.');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    $asset_field = $items->first();
    // Early opt-out if the field is empty.
    if (empty($asset_field)) {
      return $elements;
    }

    $cache_tags = [];
    $image_styles_to_load = [];
    $responsive_image_style = $this
      ->entityTypeManager
      ->getStorage('responsive_image_style')
      ->load($this->getSetting('responsive_image_style'));
    if ($responsive_image_style) {
      $cache_tags = Cache::mergeTags($cache_tags, $responsive_image_style->getCacheTags());
      $image_styles_to_load = $responsive_image_style->getImageStyleIds();
    }

    $image_styles = $this
      ->entityTypeManager
      ->getStorage('image_style')
      ->loadMultiple($image_styles_to_load);
    foreach ($image_styles as $image_style) {
      $cache_tags = Cache::mergeTags($cache_tags, $image_style->getCacheTags());
    }

    /** @var \Drupal\media\Entity\Media $media */
    $media = $items->getEntity();
    $source = $media->getSource();
    $image_attributes = $source->getMetadata($media, 'image_properties');

    $uri = "acquia-dam://$asset_field->asset_id";

    // @todo Undocumented obscure hack but for why?
    if (!$media->isLatestRevision()) {
      $uri .= "/$asset_field->version_id";
    }

    $alt_text = $media->getFieldDefinition(ImageAltTextField::IMAGE_ALT_TEXT_FIELD_NAME) ?
      $media->get(ImageAltTextField::IMAGE_ALT_TEXT_FIELD_NAME)->value : $media->label();

    $elements[] = [
      '#theme' => 'responsive_image',
      '#responsive_image_style_id' => $responsive_image_style ? $responsive_image_style->id() : '',
      '#uri' => $uri,
      '#cache' => [
        'tags' => $cache_tags,
      ],
      '#width' => !empty($image_attributes['width']) ? $image_attributes['width'] : 100,
      '#height' => !empty($image_attributes['height']) ? $image_attributes['height'] : 100,
      '#attributes' => [
        'alt' => $alt_text,
      ],
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return (\Drupal::moduleHandler()->moduleExists('responsive_image') &&
      count(\Drupal::service('config.storage')->listAll('responsive_image.styles')) > 0);
  }

}
