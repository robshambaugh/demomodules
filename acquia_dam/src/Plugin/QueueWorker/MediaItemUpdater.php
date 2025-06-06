<?php

namespace Drupal\acquia_dam\Plugin\QueueWorker;

use Drupal\acquia_dam\AssetFileEntityHelper;
use Drupal\acquia_dam\AssetVersionResolver;
use Drupal\acquia_dam\Client\AcquiaDamClientFactory;
use Drupal\acquia_dam\Entity\MediaExpiryDateField;
use Drupal\acquia_dam\Entity\MediaSourceField;
use Drupal\acquia_dam\Event\NewAssetVersionEvent;
use Drupal\acquia_dam\MetadataRefreshTrait;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\media\MediaInterface;
use Drupal\media\MediaStorage;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Updates media items based on changes of their corresponding DAM assets.
 *
 * @QueueWorker (
 *   id = "acquia_dam_media_item_update",
 *   title = @Translation("Acquia DAM media item updater"),
 *   cron = {"time" = 30}
 * )
 */
class MediaItemUpdater extends AssetQueueWorkerBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;
  use MetadataRefreshTrait;

  /**
   * Drupal entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal logger channel service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $loggerChannel;

  /**
   * The Acquia Dam client factory.
   *
   * @var \Drupal\acquia_dam\Client\AcquiaDamClientFactory
   */
  protected $clientFactory;

  /**
   * Time interface.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Cache tag invalidator service.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheTagInvalidator;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  private $eventDispatcher;

  /**
   * DAM asset version resolver.
   *
   * @var \Drupal\acquia_dam\AssetVersionResolver
   */
  protected $assetVersionResolver;

  /**
   * A service helping to handle managed files of assets.
   *
   * @var \Drupal\acquia_dam\AssetFileEntityHelper
   */
  protected $assetFileHelper;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerChannelInterface $loggerChannel, EntityTypeManagerInterface $entityTypeManager, AcquiaDamClientFactory $clientFactory, TimeInterface $time, CacheTagsInvalidatorInterface $cacheTagInvalidator, EventDispatcherInterface $event_dispatcher, AssetVersionResolver $assetVersionResolver, AssetFileEntityHelper $assetFileHelper) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->loggerChannel = $loggerChannel;
    $this->entityTypeManager = $entityTypeManager;
    $this->clientFactory = $clientFactory;
    $this->time = $time;
    $this->cacheTagInvalidator = $cacheTagInvalidator;
    $this->eventDispatcher = $event_dispatcher;
    $this->assetVersionResolver = $assetVersionResolver;
    $this->assetFileHelper = $assetFileHelper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('acquia_dam.media_item_update'),
      $container->get('entity_type.manager'),
      $container->get('acquia_dam.client.factory'),
      $container->get('datetime.time'),
      $container->get('cache_tags.invalidator'),
      $container->get('event_dispatcher'),
      $container->get('acquia_dam.asset_version_resolver'),
      $container->get('acquia_dam.asset_file_helper'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): bool {
    // Bail out early if it's unsure what to do.
    if (empty($data['asset_id'] || empty($data['media_id']))) {
      return FALSE;
    }

    // Emptying the asset cache to ensure always fresh data arrives.
    $this->cacheTagInvalidator->invalidateTags(["acquia-dam-asset:{$data['asset_id']}"]);

    try {
      $asset_data = $this->clientFactory->getSiteClient()->getAsset($data['asset_id']);
    }
    catch (\Exception $exception) {
      $this->loggerChannel->warning($this->t('Cannot get asset data from Widen API. Asset ID: %asset_id, error: %message', [
        '%asset_id' => $data['asset_id'],
        '%message' => $exception->getMessage(),
      ]));
      $this->processException($exception);
    }

    if (!$asset_data['released_and_not_expired']) {
      return FALSE;
    }

    $media_storage = $this->entityTypeManager->getStorage('media');
    assert($media_storage instanceof MediaStorage);
    /** @var \Drupal\media\MediaInterface $media_item */
    $media_item = $media_storage->load($data['media_id']);

    // Ensure that the media item still exists.
    if (!$media_item) {
      $this->loggerChannel->warning($this->t('Unable to load media item of ID %media_id associated with DAM asset of ID %asset_id.', [
        '%asset_id' => $data['asset_id'],
        '%media_id' => $data['media_id'],
      ]));

      return FALSE;
    }
    else {
      assert($media_item instanceof MediaInterface);
    }

    // The question of whether the media item needs to be updated or not was
    // already decided by another code (since this queue item exists), thus we
    // go ahead with setting a new revision for the media item.
    // Until the linked issue gets solved, it's not always guaranteed that a
    // once DAM-related media item still has a valid value referencing a DAM
    // asset in Widen.
    //
    // @todo remove this exit point once this issue has been solved.
    // @see https://www.drupal.org/i/3479183
    $asset_field = $media_item->get(MediaSourceField::SOURCE_FIELD_NAME)->first();
    if (!$asset_field) {
      $this->loggerChannel->error($this->t('Media item with ID %media_item_id has no asset ID set, which normally should not happen.', [
        '%media_item_id' => $media_item->id(),
      ]), ['link' => $media_item->toLink($this->t('View'))->toString()]);

      return FALSE;
    }

    // Then store a freshly obtained version ID.
    $asset_ids = $asset_field->getValue();
    $asset_ids['version_id'] = $this->assetVersionResolver->getFinalizedVersion($asset_ids['asset_id']);

    // Set the external ID if not available.
    if (empty($data['external_id'])) {
      $asset_ids['external_id'] = $asset_data['external_id'];
    }

    // Get the media type and its source configuration.
    $media_type = $media_item->get('bundle')->entity;
    $bundle_source_config = $media_type->get('source_configuration');
    // Download the asset file if the `download and sync` option is enabled.
    // Since the local asset version differs from the remote asset version,
    // we are downloading the latest file from DAM.
    if (isset($bundle_source_config['download_assets']) && $bundle_source_config['download_assets'] === TRUE) {
      $this->assetFileHelper->downloadFile(
        $media_item,
        $media_item->getSource(),
        $asset_data,
        $asset_ids['asset_id']
      );
    }

    // Store it early before changes are made to the entity later on.
    $old_revision_id = $media_item->getRevisionId();
    $media_item->setNewRevision();
    $media_item->set(MediaSourceField::SOURCE_FIELD_NAME, $asset_ids);

    // Set media item publicity status depending on asset availability.
    if ($asset_data['released_and_not_expired']) {
      $media_item->setPublished();
    }
    else {
      $media_item->setUnpublished();
    }

    // Not all asset have an expiration date.
    if ($asset_data['security']['expiration_date']) {
      $date = \DateTime::createFromFormat(\DateTimeInterface::ISO8601, $asset_data['security']['expiration_date']);
      $media_item->set(MediaExpiryDateField::EXPIRY_DATE_FIELD_NAME, $date->getTimestamp());
    }

    // Still update the thumbnail.
    $media_item->updateQueuedThumbnail();

    // Force mapped fields for metadata to be refreshed.
    $this->forceMappedFieldRefresh($media_item);

    $media_item->setRevisionCreationTime($this->time->getCurrentTime())
      ->setRevisionLogMessage($this->t('Automatically updated due to various changes detected on the associated remote DAM asset.'))
      ->setRevisionUserId($data['user_id'] ?? 0)
      ->save();

    // Dispatch this event after saving the new revision of the media item,
    // to allow the media system re-syncing field mappings.
    if ($old_revision_id !== $media_item->getRevisionId()) {
      try {
        $this->eventDispatcher->dispatch(new NewAssetVersionEvent(
          $media_item,
          // We do not use the original media object or clone it to prevent side
          // effects after saving a new revision from that object.
          $media_storage->loadRevision($old_revision_id)
        ));
      }
      catch (\Throwable $exception) {
        // Do nothing if an event subscriber causes an exception or error. The
        // media item has already been saved.
      }
    }

    return TRUE;
  }

}
