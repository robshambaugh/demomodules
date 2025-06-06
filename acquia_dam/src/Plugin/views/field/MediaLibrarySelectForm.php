<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\Plugin\views\field;

use Drupal\acquia_dam\Entity\MediaSourceField;
use Drupal\acquia_dam\Exception\AssetImportException;
use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\media_library\MediaLibraryState;
use Drupal\media_library\Plugin\views\field\MediaLibrarySelectForm as MediaEntityMediaLibrarySelectForm;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Media selection field for asset media type.
 *
 * @ViewsField("acquia_dam_media_library_select_form")
 *
 * @see \Drupal\media_library\Plugin\views\field\MediaLibrarySelectForm
 *
 * @phpstan-ignore-next-line
 */
final class MediaLibrarySelectForm extends MediaEntityMediaLibrarySelectForm {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * The asset repository.
   *
   * @var \Drupal\acquia_dam\AssetRepository
   */
  private $assetRepository;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->assetRepository = $container->get('acquia_dam.asset_repository');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getValue(ResultRow $row, $field = NULL) {
    // Core expects an MID, which may not be created yet.
    // The _entity property is the full external entity from Acquia DAM.
    if (version_compare(\Drupal::VERSION, '10.3', '<')) {
      return parent::getValue($row, $field);
    }
    else {
      return '<!--form-item-' . $this->options['id'] . '--' . $row->_entity->id() . '-->';
    }
  }

  // phpcs:disable
  /**
   * {@inheritdoc}
   */
  public function form_element_row_id(int $row_id): string {
    // phpcs:enable
    // Core expects an MID, which may not be created yet.
    // The _entity property is the full external entity from Acquia DAM.
    if (version_compare(\Drupal::VERSION, '10.3', '<')) {
      return (string) $row_id;
    }
    else {
      return $this->view->result[$row_id]->_entity->id();
    }
  }

  /**
   * Processes input values and import assets as media if required.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @phpstan-param array<string, mixed> $form
   * @see \Drupal\media_library\Form\AddFormBase::processInputValues
   */
  public function processInputValues(array $form, FormStateInterface $form_state): void {
    $media_storage = $this->entityTypeManager->getStorage('media');
    $source_field_name = MediaSourceField::SOURCE_FIELD_NAME;

    $field_id = $form_state->getTriggeringElement()['#field_id'];
    $selected_ids = $form_state->getValue($field_id);

    $selected_ids = $selected_ids ? array_filter(explode(',', $selected_ids)) : [];

    // No IDs were selected, nothing to do.
    if (count($selected_ids) === 0) {
      return;
    }
    $selected_media_ids = [];

    $existing_media_asset_ids = $this->assetRepository->find($selected_ids);

    /** @var array<int, \Drupal\media\MediaInterface> $existing_media_assets */
    $existing_media_assets = $media_storage->loadMultiple($existing_media_asset_ids);
    foreach ($existing_media_assets as $existing_media_asset) {
      $selected_media_ids[] = $existing_media_asset->id();
      if ($existing_media_asset->hasField($source_field_name)) {
        $key = array_search($existing_media_asset->get($source_field_name)->asset_id, $selected_ids, TRUE);
      }
      else {
        $key = array_search($existing_media_asset->id(), $selected_ids, TRUE);
      }
      // Remove this Asset ID from the selected IDs, so that it is not imported.
      unset($selected_ids[$key]);
    }

    if (count($selected_ids) > 0) {
      try {
        $selected_media_ids = array_merge($selected_media_ids, $this->assetRepository->import($selected_ids));
      }
      catch (AssetImportException $e) {
        // Temporarily mark the form as not having completed validation so
        // that we can set a new error. This will cause the AJAX callback to
        // display the error message.
        $form_state->setValidationComplete(FALSE);
        $form_state->setError($form, $e->getMessage());
        // FormValidator::finalizeValidation converts errors to messages,
        // which has already run. We need to manually set the message here.
        $this->messenger()->addError('There was an error selecting the asset.');
        $this->messenger()->addError($e->getMessage());
        $form_state->setValidationComplete();
      }
    }
    $form_state->setValue($field_id, implode(',', $selected_media_ids));
  }

  /**
   * {@inheritDoc}
   */
  public function viewsForm(array &$form, FormStateInterface $form_state) {
    $query = $this->view->getRequest()->query->get('media_library_opener_id');

    if (version_compare(\Drupal::VERSION, '10.3', '>=')) {
      // Core expects an MID, which may not be created yet.
      // The _entity property is the full external entity from Acquia DAM.
      foreach ($this->view->result as $row) {
        $row->mid = $row->mid ?? $row->_entity->id();
      }
    }

    parent::viewsForm($form, $form_state);
    $form['#submit'][] = [$this, 'processInputValues'];
    $source = $this->view->getRequest()->query->get('source');
    if (!$source) {
      $allowed_type = array_values($this->view->getRequest()->query->all('media_library_allowed_types'))[0] ?? NULL;
      if ($allowed_type) {
        $source = $this->entityTypeManager->getStorage('media_type')->load($allowed_type)->getSource()->getPluginDefinition()['provider'];
      }
    }
    if ($query === 'media_library.opener.editor' && $source === 'acquia_dam') {
      // @see \Drupal\layout_builder\Form\ConfigureBlockFormBase::doBuildForm().
      // @see https://www.drupal.org/node/2897377.
      $form['#id'] = Html::getId($form_state->getBuildInfo()['form_id']);
      $form['actions']['submit']['#value'] = $this->t('Next: Select Format');
      $form['actions']['submit']['#ajax']['callback'] = [
        self::class,
        'updateWidgetToNext',
      ];
    }
  }

  /**
   * Submit handler for media asset dam form.
   *
   * This handler will take care of moving the form to the next dialog.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   A command to send the selection to the current field widget.
   */
  public static function updateWidgetToNext(array &$form, FormStateInterface $form_state, Request $request): AjaxResponse {
    // If we have validation errors, do not process.
    // Taken from \Drupal\Core\Ajax\AjaxFormHelperTrait::ajaxSubmit().
    if ($form_state->hasAnyErrors()) {
      $form['status_messages'] = [
        '#type' => 'status_messages',
        '#weight' => -1000,
      ];
      $form['#sorted'] = FALSE;
      $response = new AjaxResponse();
      $response->addCommand(new ReplaceCommand(
        '[data-drupal-selector="' . $form['#attributes']['data-drupal-selector'] . '"]',
        $form
      ));
      return $response;
    }

    // Logic from updateWidget of Media Library.
    $field_id = $form_state->getTriggeringElement()['#field_id'];
    $selected_ids = $form_state->getValue($field_id);
    $selected_ids = $selected_ids ? array_filter(explode(',', $selected_ids)) : [];

    // Allow the opener service to handle the selection.
    $state = MediaLibraryState::fromRequest($request);

    $current_selection = $form_state->getValue($field_id);
    $available_slots = $state->getAvailableSlots();
    $selected_count = count(explode(',', $current_selection));
    if ($available_slots > 0 && $selected_count > $available_slots) {
      $response = new AjaxResponse();
      $error = \Drupal::translation()->formatPlural($selected_count - $available_slots, 'There are currently @total items selected, but the maximum number of remaining items for the field is @max. Please remove @count item from the selection.', 'There are currently @total items selected. The maximum number of remaining items for the field is @max. Please remove @count items from the selection.', [
        '@total' => $selected_count,
        '@max' => $available_slots,
      ]);
      $response->addCommand(new MessageCommand($error, '#media-library-item-count', ['type' => 'error']));
      return $response;
    }
    return self::buildEmbedForm($form_state, $request, $selected_ids);
  }

  /**
   * Build the embed form for the selected asset.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param array $selected_ids
   *   The array containing the selected assets.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse|void
   *   A command to send the replace the current form with an another one.
   */
  public static function buildEmbedForm(FormStateInterface $form_state, Request $request, array $selected_ids) {
    $asset_type = $request->query->get('media_library_selected_type');
    $embed_form = \Drupal::formBuilder()->getForm('Drupal\acquia_dam\Form\EmbedSelectForm', $asset_type, implode(',', $selected_ids));
    return (new AjaxResponse())
      ->addCommand(new ReplaceCommand('#media-library-wrapper', $embed_form));
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Do nothing here.
    // However, the field alias needs to be set. This is used for click sorting
    // in the Table style and used by ::clickSort().
    $this->field_alias = $this->realField;
  }

}
