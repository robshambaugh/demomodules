<?php

namespace Drupal\acquia_dam\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\Ajax\EditorDialogSave;
use Drupal\media\MediaInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Dialog form for the media revision CKEditor plugin.
 */
final class EditorMediaRevisionDialog extends MediaRevisionDialogBase {

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new self();
    $instance->entityRepository = $container->get('entity.repository');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->setStringTranslation($container->get('string_translation'));
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'editor_media_revision_dialog';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if (isset($form_state->getUserInput()['editor_object'])) {
      $editor_object = $form_state->getUserInput()['editor_object'];
      // The data that the text editor sends to any dialog is in
      // the 'editor_object' key.
      // @see core/modules/ckeditor/js/ckeditor.es6.js
      $media_embed_element = $editor_object['attributes'];
      $form_state->set('media_embed_element', $media_embed_element)
        ->setCached(TRUE);
    }
    else {
      // Retrieve the user input from form state.
      $media_embed_element = $form_state->get('media_embed_element');
    }

    // This will always load the latest revision.
    $form_state->set('media_uuid', $media_embed_element['data-entity-uuid']);
    $form_state->set('revised_media_id', $media_embed_element['data-entity-revision']);
    $form_state->set('media_data_embed_code_id', $media_embed_element['data-embed-code-id']);
    $form_state->set('field_type', 'editor');
    $form = parent::buildForm($form, $form_state);
    $form['actions']['save_modal']['#ajax']['callback'] = [$this, 'submitForm'];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    $form_state->setValue(['attributes', 'data-entity-revision'], $form_state->getValue('latest_revision_id'));

    // Only send back the relevant values.
    $values = [
      'attributes' => $form_state->getValue('attributes'),
    ];
    $response->addCommand(new EditorDialogSave($values));
    $response->addCommand(new CloseModalDialogCommand());

    return $response;
  }

  /**
   * {@inheritDoc}
   */
  protected function loadMedia(FormStateInterface $form_state): ?MediaInterface {
    return \Drupal::service('entity.repository')->loadEntityByUuid('media', $form_state->get('media_uuid'));
  }

}
