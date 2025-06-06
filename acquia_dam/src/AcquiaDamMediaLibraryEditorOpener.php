<?php

namespace Drupal\acquia_dam;

use Drupal\acquia_dam\Plugin\media\Source\Asset;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\editor\Ajax\EditorDialogSave;
use Drupal\media\MediaInterface;
use Drupal\media_library\MediaLibraryEditorOpener;
use Drupal\media_library\MediaLibraryState;

/**
 * Decorates the media library editor opener with our customizations.
 *
 * @phpstan-ignore-next-line
 */
class AcquiaDamMediaLibraryEditorOpener extends MediaLibraryEditorOpener {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Acquia DAM image style helper.
   *
   * @var \Drupal\acquia_dam\ImageStyleHelper
   */
  protected $imageStyleHelper;

  /**
   * Sets the entity type manager.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function setEntityTypeManager(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Sets the Acquia DAM image style helper.
   *
   * @param \Drupal\acquia_dam\ImageStyleHelper $imageStyleHelper
   *   Acquia DAM image style helper.
   */
  public function setImageStyleHelper(ImageStyleHelper $imageStyleHelper) {
    $this->imageStyleHelper = $imageStyleHelper;
  }

  /**
   * {@inheritDoc}
   */
  public function getSelectionResponse(MediaLibraryState $state, array $selected_ids): AjaxResponse {
    $selected_media = $this->entityTypeManager->getStorage('media')->load(reset($selected_ids));
    if (!$selected_media instanceof MediaInterface) {
      return parent::getSelectionResponse($state, $selected_ids);
    }
    if (!$selected_media->getSource() instanceof Asset) {
      return parent::getSelectionResponse($state, $selected_ids);
    }

    $response = new AjaxResponse();
    $values = [
      'attributes' => [
        'data-entity-type' => 'media',
        'data-entity-uuid' => $selected_media->uuid(),
        'data-align' => 'center',
        'data-embed-code-id' => $state->get('embed_code'),
      ],
    ];

    if ($state->get('versioning') === 'manual') {
      $values['attributes']['data-entity-revision'] = $selected_media->getRevisionId();
    }

    if ($position = $state->get('focal_point')) {
      $image_style = $state->get('embed_code');
      [$x, $y] = explode(',', $position, 2);

      $this
        ->imageStyleHelper
        ->saveCropEntity(
          $x,
          $y,
          $selected_media,
          $image_style
        );
    }

    $response->addCommand(new EditorDialogSave($values));

    return $response;
  }

}
