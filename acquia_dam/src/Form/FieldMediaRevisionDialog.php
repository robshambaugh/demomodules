<?php

namespace Drupal\acquia_dam\Form;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;

/**
 * Dialog form for the media revision field widget.
 */
class FieldMediaRevisionDialog extends MediaRevisionDialogBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'field_media_revision_dialog';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, int $revision_id = -1, int $target_id = -1, string $triggering_id = '', string $parent_field = '') {
    if (!($revision_id | $target_id)) {
      $form_state->setErrorByName('Revision Not found', 'Operation due to missing revision id');
      return $form;
    }
    // This will always load the latest revision.
    $form_state->set('media_id', $target_id);
    $form_state->set('revised_media_id', $revision_id);
    $form_state->set('media_data_embed_code_id', 'original');
    $form_state->set('field_type', 'err_widget');
    $form = parent::buildForm($form, $form_state);
    $hash_string = implode(':', [
      $this->getFormId(),
      $triggering_id,
      $parent_field,
    ]);
    $hash = Crypt::hmacBase64($hash_string, \Drupal::service('private_key')->get() . Settings::getHashSalt());
    $form['actions']['save_modal']['#ajax']['url'] = Url::fromRoute('acquia_dam.field_media_revision_dialog', [
      'revision_id' => $revision_id,
      'target_id' => $target_id,
    ]);
    $form['actions']['save_modal']['#ajax']['options'] = [
      'query' => [
        'triggered_value' => $triggering_id,
        'parent_field' => $parent_field,
        'hash' => $hash,
        'ajax_form' => 1,
      ],
    ];
    $form['actions']['save_modal']['#ajax']['callback'] = [
      static::class,
      'submitFormAjax',
    ];
    $form['actions']['cancel']['#ajax']['url'] = Url::fromRoute('acquia_dam.field_media_revision_dialog', [
      'revision_id' => $revision_id,
      'target_id' => $target_id,
    ]);
    $form['actions']['cancel']['#ajax']['options'] = [
      'query' => [
        'triggered_value' => $triggering_id,
        'parent_field' => $parent_field,
        'hash' => $hash,
        'ajax_form' => 1,
      ],
    ];
    return $form;
  }

  /**
   * Ajax callback for updating the media to the given revision.
   */
  public static function submitFormAjax(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $triggered_id = \Drupal::requestStack()->getCurrentRequest()->query->get('triggered_value');
    $parent_field = \Drupal::requestStack()->getCurrentRequest()->query->get('parent_field');
    $latest_revision_id = $form_state->getValue('latest_revision_id');
    $triggered_id = $triggered_id . '-target-revision-id';
    $response
      ->addCommand(new InvokeCommand("[data-drupal-selector=\"$triggered_id\"]", 'val', [$latest_revision_id]))
      ->addCommand(new InvokeCommand("[data-media-library-widget-update=\"$parent_field\"]", 'trigger', ['mousedown']))
      ->addCommand(new CloseModalDialogCommand());

    return $response;
  }

  /**
   * {@inheritDoc}
   */
  protected function loadMedia(FormStateInterface $form_state): MediaInterface {
    return Media::load($form_state->get('media_id'));
  }

}
