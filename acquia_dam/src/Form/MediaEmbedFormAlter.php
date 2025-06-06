<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\Form;

use Drupal\acquia_dam\EmbedCodeFactory;
use Drupal\acquia_dam\Plugin\media\Source\Asset;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\media\MediaInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Alters the media embed form to add support for embed codes.
 *
 * @see \Drupal\media\Form\EditorMediaDialog::buildForm
 * @link https://www.drupal.org/docs/core-modules-and-themes/core-modules/media-library-module/embedding-media-with-ckeditor#s-customizing-the-edit-embedded-media-form
 */
final class MediaEmbedFormAlter implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  private $entityRepository;

  /**
   * Constructs a new MediaEmbedFormAlter object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   */
  public function __construct(EntityRepositoryInterface $entity_repository) {
    $this->entityRepository = $entity_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self($container->get('entity.repository'));
  }

  /**
   * Alters the media embed form to add embed code selection.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function formAlter(array &$form, FormStateInterface $form_state): void {
    if (isset($form_state->getUserInput()['editor_object'])) {
      $editor_object = $form_state->getUserInput()['editor_object'];
      $media_embed_element = $editor_object['attributes'];
    }
    else {
      $media_embed_element = $form_state->get('media_embed_element');
    }

    // Something went wrong, bail out.
    if (!is_array($media_embed_element) || empty($media_embed_element['data-entity-uuid'])) {
      return;
    }

    $media = $this->entityRepository->loadEntityByUuid('media', $media_embed_element['data-entity-uuid']);
    // Something went _really_ wrong and the media entity could not be loaded.
    if (!$media instanceof MediaInterface) {
      return;
    }
    if (!$media->getSource() instanceof Asset) {
      return;
    }

    /** @var \Drupal\acquia_dam\Plugin\media\Source\Asset $asset */
    $asset = $media->getSource();
    $form['data-embed-code-id'] = [
      '#title' => $this->t('Embed code'),
      '#type' => 'select',
      '#options' => EmbedCodeFactory::getSelectOptions($asset->getDerivativeId()),
      '#default_value' => $media_embed_element['data-embed-code-id'] ?? 'original',
      // Setting the parent to `attributes` ensures the embed form saves our
      // custom attribute properly.
      '#parents' => ['attributes', 'data-embed-code-id'],
    ];
  }

}
