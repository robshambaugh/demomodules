<?php

declare(strict_types=1);

namespace Drupal\acquia_dam;

use Drupal\acquia_dam\Client\AcquiaDamClientFactory;
use Drupal\acquia_dam\Entity\MediaSourceField;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\media\MediaInterface;

/**
 * Are the DAM asset and the associated media item the same?
 *
 * Centralized logic of checking new updates available to the DAM asset of a
 * given media item. Being invoked both during regular cron runs and
 * eventually by a custom entity operation button.
 */
class AssetUpdateChecker {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The actual time.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Media item update queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $mediaItemUpdateQueue;

  /**
   * Media item update logging channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $mediaItemUpdateLogger;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The Acquia DAM client factory.
   *
   * @var \Drupal\acquia_dam\Client\AcquiaDamClientFactory
   */
  protected $clientFactory;

  /**
   * The asset version resolver utility.
   *
   * @var \Drupal\acquia_dam\AssetVersionResolver
   */
  protected $assetVersionResolver;

  /**
   * Constructs a new MediaItemUpdater object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Component\Datetime\TimeInterface $actual_time
   *   The actual time.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   Queue factory service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_channel_factory
   *   Media item update logging channel.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\acquia_dam\Client\AcquiaDamClientFactory $client_factory
   *   The Acquia DAM client factory.
   * @param \Drupal\acquia_dam\AssetVersionResolver $asset_version_resolver
   *   The asset version resolver utility.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    TimeInterface $actual_time,
    QueueFactory $queue_factory,
    LoggerChannelFactoryInterface $logger_channel_factory,
    MessengerInterface $messenger,
    AcquiaDamClientFactory $client_factory,
    AssetVersionResolver $asset_version_resolver
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->time = $actual_time;
    $this->mediaItemUpdateQueue = $queue_factory->get('acquia_dam_media_item_update');
    $this->mediaItemUpdateLogger = $logger_channel_factory->get('acquia_dam.media_item_update');
    $this->messenger = $messenger;
    $this->clientFactory = $client_factory;
    $this->assetVersionResolver = $asset_version_resolver;
  }

  /**
   * The class' single method performing the business logic.
   *
   * @param \Drupal\media\MediaInterface $media_item
   *   The media item which DAM asset needs to be checked.
   * @param int $user_id
   *   (optional) The numerical ID of the initiating user.
   *
   * @return null|bool
   *   A boolean value answering the question "Are the DAM asset and the
   *   associated media item the same?". False means the media item needs to be
   *   updated. Null if any error happens.
   *
   *   Until the linked issue gets solved, it's not always guaranteed that a
   *   once DAM-related media item still has a valid value referencing a DAM
   *   asset in Widen.
   *
   * @todo remove this exit point once this issue has been solved.
   *
   * @see https://www.drupal.org/i/3479183
   */
  public function checkAssets(MediaInterface $media_item, int $user_id = 0): ?bool {
    assert($media_item instanceof MediaInterface);
    $asset_field = $media_item->get(MediaSourceField::SOURCE_FIELD_NAME)->first();
    if (!$asset_field || is_null($asset_field->getValue()['asset_id'])) {
      $this->mediaItemUpdateLogger->error($this->t('Media item with ID %media_item_id has no asset ID set, which normally should not happen.', [
        '%media_item_id' => $media_item->id(),
      ]), ['link' => $media_item->toLink($this->t('View'))->toString()]);
      return NULL;
    }

    $asset_ids = $asset_field->getValue();

    try {
      $asset_data = $this->clientFactory->getSiteClient()->getAsset($asset_ids['asset_id']);
    }
    catch (\Exception $exception) {
      $this->mediaItemUpdateLogger->warning($this->t('Cannot get asset data from Widen API for asset with ID %asset_id, error: %error_message', [
        '%asset_id' => $asset_ids['asset_id'],
        '%error_message' => $exception->getMessage(),
      ]), ['link' => $media_item->toLink($this->t('View'))->toString()]);

      return NULL;
    }

    // Once the DAM asset has been gone in Widen, we cannot play through the
    // logic chain down to the `MediaItemUpdater` queue worker, but instantly
    // perform the unpublishing action on the media item instead.
    if (!$asset_data['released_and_not_expired']) {
      $media_item->setNewRevision();
      $media_item->setUnpublished()
        ->setRevisionCreationTime($this->time->getCurrentTime())
        ->setRevisionLogMessage($this->t('Automatically unpublished as the associated DAM asset is unavailable.'))
        ->setRevisionUserId($user_id)
        ->save();

      return NULL;
    }

    // Prepare publicity status info for comparison later on.
    $local_status = $media_item->get('status')->value === '1';
    $remote_status = $asset_data['released_and_not_expired'];

    // Prepare asset version info for comparison later on.
    $local_version_id = $asset_ids['version_id'];
    $remote_version_id = $this->assetVersionResolver->getFinalizedVersion($asset_ids['asset_id']);

    // Key turning point deciding whether the media item is up-to-date or not.
    if ($local_status === $remote_status && $local_version_id === $remote_version_id) {
      return TRUE;
    }

    // Now if updating is needed, then queue the task for later processing.
    $this->mediaItemUpdateQueue->createItem([
      'asset_id' => $asset_ids['asset_id'],
      'media_id' => $media_item->id(),
      'user_id' => $user_id,
    ]);

    return FALSE;
  }

}
