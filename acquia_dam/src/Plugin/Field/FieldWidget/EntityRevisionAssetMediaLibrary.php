<?php

namespace Drupal\acquia_dam\Plugin\Field\FieldWidget;

use Drupal\acquia_dam\Plugin\media\Source\Asset;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use Drupal\media_library\Plugin\Field\FieldWidget\MediaLibraryWidget;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Implementation of 'entity_reference_revisions_asset_media_library' widget.
 *
 * @FieldWidget(
 *   id = "entity_reference_revisions_asset_media_library",
 *   label = @Translation("Entity Revision Asset Media Library"),
 *   description = @Translation("A media library asset field for revisions"),
 *   multiple_values = TRUE,
 *   field_types = {
 *     "entity_reference_revisions"
 *   }
 * )
 */
class EntityRevisionAssetMediaLibrary extends MediaLibraryWidget {

  /**
   * The HTTP request object from the stack.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $httpRequest;

  /**
   * The date & time service of Core.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $dateTime;

  /**
   * Constructs an EntityRevisionAssetMediaLibrary widget.
   *
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current active user.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Symfony\Component\HttpFoundation\Request $http_request
   *   The HTTP request stack object of Symfony.
   * @param \Drupal\Component\Datetime\TimeInterface $date_time
   *   The date & time service of Core.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, EntityTypeManagerInterface $entity_type_manager, AccountInterface $current_user, ModuleHandlerInterface $module_handler, Request $http_request, TimeInterface $date_time) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings, $entity_type_manager, $current_user, $module_handler);
    $this->httpRequest = $http_request;
    $this->dateTime = $date_time;
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
      $configuration['third_party_settings'],
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('module_handler'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $referenced_entities = $items->referencedEntities();
    $field_name = $this->fieldDefinition->getName();
    $request_values = $this->httpRequest->request->all($items->getName());
    // New values are updated only in the request form.
    if ($request_values && array_key_exists('selection', $request_values)) {
      $this->checkForRevisionUpdates($referenced_entities, $request_values);
    }
    if ($referenced_entities) {
      $entity_ids = array_map(function ($entity) {
        return $entity->id();
      }, $referenced_entities);
      $result = $this->entityTypeManager->getStorage('media')->getQuery()
        ->condition('mid', $entity_ids, 'IN')
        ->accessCheck()
        ->execute();
      $vid_list = array_keys($result);
      $current_time = $this->dateTime->getCurrentTime();
      foreach ($referenced_entities as $key => $entity) {
        assert($entity instanceof MediaInterface);
        $revision_id = $entity->getRevisionId();
        $id = $entity->id();
        $element['selection'][$key]['target_revision_id'] = [
          '#type' => 'hidden',
          '#value' => $revision_id,
        ];
        // This is much efficient than isLatestRevision when it comes to
        // multiple selections.
        if (!in_array($revision_id, $vid_list)) {
          $latest_entity = Media::load($entity->id());
          if (!$latest_entity->getSource() instanceof Asset) {
            continue;
          }
          $expiry_date = $latest_entity->get('acquia_dam_expiry_date')->getValue();
          if ($expiry_date && (int) $expiry_date[0]['value'] < $current_time) {
            continue;
          }
          $element['selection'][$key]['new_update'] = [
            '#type' => 'button',
            '#value' => $this->t('Update Media'),
            '#name' => $field_name . '_update_media_' . $id,
            '#ajax' => [
              'callback' => [static::class, 'openUpdateDialog'],
              'progress' => [
                'type' => 'throbber',
                'message' => $this->t('Opening update form dialog.'),
              ],
              // The AJAX system automatically moves focus to the first tabbable
              // element of the modal, so we need to disable refocus on the
              // button.
              'disable-refocus' => TRUE,
            ],
          ];
        }
      }
    }
    $parents = $form['#parents'];
    $id_suffix = $parents ? '-' . implode('-', $parents) : '';
    $field_widget_id = implode(':', array_filter([$field_name, $id_suffix]));
    // Revision field containing the id to update the widget accordingly.
    $element['media_library_selection_revision'] = [
      '#type' => 'hidden',
      '#attributes' => [
        // This is used to pass the selection from the modal to the widget.
        'data-media-library-widget-value' => $field_widget_id . '_revisions',
      ],
    ];
    return $element;
  }

  /**
   * AJAX callback to open the library modal.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response to open the update dialog.
   */
  public static function openUpdateDialog(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($triggering_element['#array_parents'], 0, -1));
    $library_ui = \Drupal::formBuilder()->getForm('Drupal\acquia_dam\Form\FieldMediaRevisionDialog', $element['target_revision_id']['#value'], $element['target_id']['#value'], $element['#attributes']['data-drupal-selector'], $element['#parents'][0]);
    $dialog_options = [
      'dialogClass' => 'update-widget-modal',
      'title' => t('Update Media'),
      'height' => '75%',
      'width' => '75%',
    ];
    return (new AjaxResponse())
      ->addCommand(new OpenModalDialogCommand($dialog_options['title'], $library_ui, $dialog_options));

  }

  /**
   * {@inheritDoc}
   */
  public static function updateWidget(array $form, FormStateInterface $form_state) {
    $response = parent::updateWidget($form, $form_state);

    $triggering_element = $form_state->getTriggeringElement();

    // This callback is either invoked from the remove button or the update
    // button, which have different nesting levels.
    $is_remove_button = end($triggering_element['#parents']) === 'remove_button';
    $length = $is_remove_button ? -3 : -1;
    if (count($triggering_element['#array_parents']) < abs($length)) {
      throw new \LogicException('The element that triggered the widget update was at an unexpected depth. Triggering element parents were: ' . implode(',', $triggering_element['#array_parents']));
    }
    $parents = array_slice($triggering_element['#array_parents'], 0, $length);
    $element = NestedArray::getValue($form, $parents);

    // Clear the revision field along with the id.
    $element['media_library_selection_revision']['#value'] = '';
    $wrapper_id = $triggering_element['#ajax']['wrapper'];
    $response->addCommand(new ReplaceCommand("#$wrapper_id", $element));
    return $response;
  }

  /**
   * {@inheritDoc}
   */
  public static function addItems(array $form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -1));

    $field_state = static::getFieldState($element, $form_state);

    $media = static::getNewMediaItems($element, $form_state);
    if (!empty($media)) {
      // Get the weight of the last items and count from there.
      $last_element = end($field_state['items']);
      $weight = $last_element ? $last_element['weight'] : 0;
      foreach ($media as $media_item) {
        // Any ID can be passed to the widget, so we have to check access.
        if ($media_item->access('view')) {
          $field_state['items'][] = [
            'entity' => $media_item,
            'target_id' => $media_item->id(),
            'target_revision_id' => $media_item->getRevisionId(),
            'weight' => ++$weight,
          ];
        }
      }
      static::setFieldState($element, $form_state, $field_state);
    }

    $form_state->setRebuild();
  }

  /**
   * To check if the added entities are update manually updated by the user.
   *
   * @param array $referenced_entities
   *   The array of referenced entities.
   * @param array $results
   *   Array containing the field values.
   */
  public function checkForRevisionUpdates(array &$referenced_entities, array $results) {
    $cleaned_selections = [];
    foreach ($results['selection'] as $selection) {
      $cleaned_selections[$selection['target_id']] = $selection['target_revision_id'];
    }
    foreach ($referenced_entities as $key => $entity) {
      assert($entity instanceof MediaInterface);
      $current_id = $entity->getRevisionId();
      if ($cleaned_selections[$entity->id()] && $cleaned_selections[$entity->id()] != $current_id) {
        // Loading the latest media entity.
        $referenced_entities[$key] = Media::load($entity->id());
      }
    }
  }

}
