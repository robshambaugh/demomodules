<?php

namespace Drupal\acquia_dam\Form;

use Drupal\acquia_dam\EmbedCodeFactory;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\media\MediaInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base form for the media revision.
 */
abstract class MediaRevisionDialogBase extends FormBase {

  /**
   * Entity storage service for media items.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $mediaStorage;

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $media_storage
   *   Entity storage service for media items.
   */
  public function __construct(EntityStorageInterface $media_storage) {
    $this->mediaStorage = $media_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager')->getStorage('media'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'media_revision_dialog_base';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#tree'] = TRUE;
    $form['#attached']['library'][] = 'editor/drupal.editor.dialog';
    $form['#prefix'] = '<div id="editor-media-dialog-form">';
    $form['#suffix'] = '</div>';
    $media = $this->loadMedia($form_state);
    assert($media instanceof MediaInterface);
    $revision = $this->mediaStorage->loadRevision($form_state->get('revised_media_id'));
    assert($revision instanceof MediaInterface);

    $embed_code_id = $form_state->get('media_data_embed_code_id');

    $form['latest_revision_id'] = [
      '#type' => 'hidden',
      '#value' => $media->getRevisionId(),
    ];

    $form['intro'] = [
      '#type' => 'inline_template',
      '#template' => '<p>{{ text }}</p>',
      '#context' => [
        'text' => $this->t('You are about to update your current media to a newer version.'),
      ],
    ];
    $form['preview'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['layout-row', 'clearfix']],
    ];
    $form['preview']['current'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['layout-column', 'layout-column--half'],
      ],
      'label' => [
        '#type' => 'inline_template',
        '#template' => '<h5>{{ text }}</h5>',
        '#context' => [
          'text' => $this->t('Current media'),
        ],
      ],
      'entity' => [
        '#type' => 'container',
        '#theme_wrappers' => ['container__acquia_dam_asset'],
        'embed' => EmbedCodeFactory::renderAsset(
          $embed_code_id,
          $revision
        ),
      ],
    ];
    $form['preview']['latest'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['layout-column', 'layout-column--half'],
      ],
      'label' => [
        '#type' => 'inline_template',
        '#template' => '<h5>{{ text }}</h5>',
        '#context' => [
          'text' => $this->t('Updated media'),
        ],
      ],
      'entity' => [
        '#type' => 'container',
        '#theme_wrappers' => ['container__acquia_dam_asset'],
        'embed' => EmbedCodeFactory::renderAsset(
          $embed_code_id,
          $media
        ),
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['cancel'] = [
      '#type' => 'button',
      '#value' => $this->t('Cancel'),
      // No regular submit-handler. This form only works via JavaScript.
      '#submit' => [],
      '#ajax' => [
        'callback' => '::closeForm',
        'event' => 'click',
      ],
      // Prevent this hidden element from being tabbable.
      '#attributes' => [
        'tabindex' => -1,
      ],
    ];
    $form['actions']['save_modal'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update'),
      '#button_type' => 'primary',
      // No regular submit-handler. This form only works via JavaScript.
      '#submit' => [],
      '#ajax' => [
        'callback' => [static::class, 'submitForm'],
        'event' => 'click',
        'disable-refocus' => TRUE,
      ],
      // Prevent this hidden element from being tabbable.
      '#attributes' => [
        'tabindex' => -1,
      ],
    ];

    return $form;
  }

  /**
   * Submit handler to cancel and close the dialog without updates.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function closeForm(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(new CloseModalDialogCommand());
    return $response;
  }

  /**
   * Load the media for the revision dialog.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\media\MediaInterface|null
   *   The media object.
   */
  abstract protected function loadMedia(FormStateInterface $form_state): ?MediaInterface;

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
