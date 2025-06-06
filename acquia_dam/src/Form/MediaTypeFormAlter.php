<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\Form;

use Drupal\acquia_dam\AssetRepository;
use Drupal\acquia_dam\AssetDownloader;
use Drupal\acquia_dam\Client\AcquiaDamClientFactory;
use Drupal\acquia_dam\Exception\DamClientException;
use Drupal\acquia_dam\Exception\DamConnectException;
use Drupal\acquia_dam\Exception\DamServerException;
use Drupal\acquia_dam\Plugin\media\Source\Asset;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\media\MediaSourceInterface;
use Drupal\media\MediaTypeForm;
use Drupal\media\MediaTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Alters the media type form for DAM assets to improve field mapping UI.
 */
final class MediaTypeFormAlter implements ContainerInjectionInterface {

  use DependencySerializationTrait;
  use StringTranslationTrait;

  /**
   * The Asset Repository service.
   *
   * @var \Drupal\acquia_dam\AssetRepository
   */
  protected $assetRepository;

  /**
   * Entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * DAM client factory.
   *
   * @var \Drupal\acquia_dam\Client\AcquiaDamClientFactory
   */
  protected $clientFactory;

  /**
   * The request stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a new MediaTypeFormAlter object.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\acquia_dam\Client\AcquiaDamClientFactory $client_factory
   *   The Acquia DAM client factory.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository service.
   * @param \Drupal\acquia_dam\AssetRepository $asset_repository
   *   The Asset Repository service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack service.
   */
  public function __construct(
    EntityFieldManagerInterface $entity_field_manager,
    AcquiaDamClientFactory $client_factory,
    EntityDisplayRepositoryInterface $entity_display_repository,
    AssetRepository $asset_repository,
    RequestStack $request_stack,
  ) {
    $this->requestStack = $request_stack;
    $this->clientFactory = $client_factory;
    $this->assetRepository = $asset_repository;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityDisplayRepository = $entity_display_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new self(
      $container->get('entity_field.manager'),
      $container->get('acquia_dam.client.factory'),
      $container->get('entity_display.repository'),
      $container->get('acquia_dam.asset_repository'),
      $container->get('request_stack'),
    );
    $instance->setStringTranslation($container->get('string_translation'));
    return $instance;
  }

  /**
   * Alters the media type form to enhance the metadata mapping element.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function formAlter(array &$form, FormStateInterface $form_state): void {
    $form_object = $form_state->getFormObject();
    $media_type = $form_object->getEntity();
    if (!$form_object instanceof MediaTypeForm) {
      return;
    }
    if (!$media_type instanceof MediaTypeInterface) {
      return;
    }
    // Source is not set when the entity is initially created.
    if ($media_type->get('source') === NULL) {
      return;
    }
    $source = $media_type->getSource();
    if (!$source instanceof Asset) {
      return;
    }
    // Add submit handler to save the form
    // and view displays when creating new media type.
    if ($form_object->getOperation() === 'add') {
      $form['actions']['submit']['#submit'][] = [$this, 'createDefaultFormAndViewMode'];
      if (isset($form['actions']['save_continue'])) {
        $form['actions']['save_continue']['#submit'][] = [$this, 'createDefaultFormAndViewMode'];
      }
    }

    if ($form_object->getOperation() === 'edit' && $this->assetRepository->countTotalAssets($media_type->id()) > $this->assetRepository->countLocalAssets($media_type)) {
      // Disable the checkbox to prevent the user from serving media locally.
      $form['source_dependent']['source_configuration']['download_assets']['#disabled'] = TRUE;
      $form['source_dependent']['source_configuration']['download_assets']['#description'] = $this->t('
      There are existing assets that do not have assets downloaded or available locally,
      hence you can\'t choose the option of Download and sync.
      First, download all assets locally, then you can enable this option.
      To download the assets locally, click on <strong>Download and sync assets</strong> button or alternatively,
      run the drush command: <code><strong>drush acquia-dam:download-assets @media_type</strong></code>', [
        '@media_type' => $media_type->id(),
      ]);
      // Add a button to download and sync assets.
      $form['source_dependent']['source_configuration']['download_assets_button'] = [
        '#type' => 'submit',
        '#value' => $this->t('Download and sync assets'),
        '#submit' => [[$this, 'downloadAssetLocally']],
      ];
    }

    $form['source_dependent']['field_map']['#title'] = $this->t('Map Fields');
    unset($form['source_dependent']['field_map']['#description']);
    foreach (Element::children($form['source_dependent']['field_map']) as $child) {
      unset($form['source_dependent']['field_map'][$child]);
    }
    $form['source_dependent']['field_map']['description'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $this->t('Metadata can be mapped from the DAM to Drupal entity fields. Field mappings can be configured below. Information will be mapped only if an entity field is empty.') . '</p>',
    ];

    try {
      /** @var array{fields: array<int, array<string, string>>} $metadata_list */
      $metadata_list = $this->clientFactory->getSiteClient()->getDisplayKeys('all');
      $metadata_field_types = [
        'created_date' => 'datetime',
        'filename' => 'text',
        'size' => 'numeric',
        'last_update_date' => 'datetime',
        'file_upload_date' => 'datetime',
        'expiration_date' => 'datetime',
        'release_date' => 'datetime',
        'deleted_date' => 'datetime',
      ];
      foreach ($metadata_list['fields'] as $metadata_field) {
        $metadata_field_types[$metadata_field['display_key']] = $metadata_field['field_type'];
      }
    }
    catch (DamClientException | DamServerException | DamConnectException $e) {
      $form['source_dependent']['field_map']['mapping'] = [
        '#type' => 'markup',
        '#markup' => '<p>' . $this->t('Cannot configure mapping because: @error', [
          '@error' => $e->getMessage(),
        ]) . '</p>',
      ];
      return;
    }

    $form['source_dependent']['field_map']['mapping'] = [
      '#type' => 'table',
      '#input' => FALSE,
      '#header' => [
        $this->t('DAM metadata field'),
        $this->t('DAM field name'),
        $this->t('DAM field type'),
        $this->t('Drupal mapped field'),
      ],
      '#rows' => [],
    ];
    $field_map = $media_type->getFieldMap();

    $dam_field_types = [
      'checkboxes' => $this->t('Checkbox'),
      'date' => $this->t('Date'),
      'datetime' => $this->t('Date and time'),
      'text_multi_line' => $this->t('Text'),
      'text' => $this->t('Text'),
      'selection_list' => $this->t('Dropdown'),
      'limited_text_field' => $this->t('Limited text'),
      'selection_list_multi' => $this->t('Palette'),
      'numeric' => $this->t('Numeric'),
      'text_long' => $this->t('Long text'),
    ];

    $metadata_attributes = $source->getMetadataAttributes();
    ksort($metadata_attributes);
    foreach ($metadata_attributes as $attribute_name => $attribute_label) {
      // The metadata field may have been removed from the DAM, but it is still
      // configured to be used.
      if (empty($metadata_field_types[$attribute_name])) {
        continue;
      }
      $label = $dam_field_types[strtolower($metadata_field_types[$attribute_name])] ?? '';
      if ($label === '') {
        $label = ucfirst(str_replace('_', ' ', $metadata_field_types[$attribute_name]));
      }

      $options = [MediaSourceInterface::METADATA_FIELD_EMPTY => $this->t('- Skip field -')];
      foreach ($this->entityFieldManager->getFieldDefinitions('media', $media_type->id()) as $field_name => $field) {
        if ($field->getFieldStorageDefinition()->isBaseField()
          && $field_name !== 'name'
          || $field->isReadOnly()
        ) {
          continue;
        }
        $field_type = $field->getType();
        $metadata_type = $metadata_field_types[$attribute_name];

        // Dates can only go to string, timestamp, and datetime fields.
        if (!in_array($metadata_type, ['date', 'datetime'])
          && in_array($field_type, ['datetime', 'timestamp'])) {
          continue;
        }

        $options[$field_name] = $field->getLabel();
      }

      $row = [
        'label' => [
          '#plain_text' => $attribute_label,
        ],
        'name' => [
          '#plain_text' => $attribute_name,
        ],
        'type' => [
          '#plain_text' => $label,
        ],
        'field' => [
          [
            '#type' => 'select',
            '#title' => $this->t('Drupal field for @title', ['@title' => $attribute_label]),
            '#title_display' => 'invisible',
            '#options' => $options,
            '#default_value' => $field_map[$attribute_name] ?? MediaSourceInterface::METADATA_FIELD_EMPTY,
            '#parents' => ['field_map', $attribute_name],
          ],
        ],
      ];
      $form['source_dependent']['field_map']['mapping'][$attribute_name] = $row;
    }
  }

  /**
   * Submit callback for the media type form.
   *
   * This function handles the submission of the media type form, ensuring that
   * the form and view displays are properly configured for the media type.
   *
   * @param array $form
   *   The form structures.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function createDefaultFormAndViewMode(array &$form, FormStateInterface $form_state): void {
    $media_type = $form_state->getFormObject()->getEntity();
    $source = $media_type->getSource();
    $source_field = $source->getSourceFieldDefinition($media_type);
    // Save form display for the media type.
    if ($source_field->isDisplayConfigurable('form')) {
      $this->entityDisplayRepository->getFormDisplay('media', $media_type->id())->save();
    }
    // Save view display for the media type.
    if ($source_field->isDisplayConfigurable('view')) {
      $this->entityDisplayRepository->getViewDisplay('media', $media_type->id())->save();
    }
  }

  /**
   * Submit handler to downloads and syncs assets locally.
   *
   * @param array $form
   *   The form structures.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function downloadAssetLocally(array &$form, FormStateInterface $form_state): void {
    $form_object = $form_state->getFormObject();
    $media_type = $form_object->getEntity();
    AssetDownloader::buildBatch($media_type);
    // Remove the destination query parameter.
    $this->requestStack->getCurrentRequest()->query->remove('destination');
    // Redirect to the media type edit form.
    $form_state->setRedirectUrl(URL::fromRoute('entity.media_type.edit_form', ['media_type' => $media_type->id()]));
  }

}
