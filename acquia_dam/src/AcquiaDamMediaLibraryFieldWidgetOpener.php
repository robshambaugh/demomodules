<?php

namespace Drupal\acquia_dam;

use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\media\Entity\Media;
use Drupal\media_library\MediaLibraryFieldWidgetOpener;
use Drupal\media_library\MediaLibraryOpenerInterface;
use Drupal\media_library\MediaLibraryState;

/**
 * Decorates the media library editor opener with our customizations.
 */
class AcquiaDamMediaLibraryFieldWidgetOpener implements MediaLibraryOpenerInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The decorated MediaLibraryFieldOpener.
   *
   * @var \Drupal\media_library\MediaLibraryFieldWidgetOpener
   */
  protected $inner;

  /**
   * MediaLibraryFieldWidgetOpener constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\media_library\MediaLibraryFieldWidgetOpener $inner
   *   The decorated function.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entityFieldManager, MediaLibraryFieldWidgetOpener $inner) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entityFieldManager;
    $this->inner = $inner;
  }

  /**
   * {@inheritDoc}
   */
  public function getSelectionResponse(MediaLibraryState $state, array $selected_ids) {
    $response = $this->inner->getSelectionResponse($state, $selected_ids);
    $parameters = $state->getOpenerParameters();
    $widget_id = $parameters['field_widget_id'];

    $field_definitions = $this->entityFieldManager->getFieldDefinitions($parameters['entity_type_id'], $parameters['bundle']);
    if (!isset($field_definitions[$parameters['field_name']])) {
      return $response;
    }
    $field_definition = $field_definitions[$parameters['field_name']];

    // If the field has revision add them as well.
    if ($field_definition->getType() === 'entity_reference_revisions') {
      $selected_media_ids = Media::loadMultiple($selected_ids);
      $selected_rids = [];
      foreach ($selected_media_ids as $media) {
        $selected_rids[] = $media->getRevisionId();
      }
      $widget_revision_id = $widget_id . '_revisions';
      $response
        ->addCommand(new InvokeCommand("[data-media-library-widget-value=\"$widget_revision_id\"]", 'val', [implode(',', $selected_rids)]), TRUE);
    }
    return $response;
  }

  /**
   * {@inheritDoc}
   */
  public function checkAccess(MediaLibraryState $state, AccountInterface $account) {
    return $this->inner->checkAccess($state, $account);
  }

}
