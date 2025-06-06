<?php

namespace Drupal\acquia_dam;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\media\MediaTypeInterface;

/**
 * Asset Download Service.
 *
 * Provides methods to download assets from Widen dam locally and verify counts.
 */
final class AssetDownloader {

  use StringTranslationTrait;

  /**
   * Downloads assets locally.
   *
   * @param \Drupal\media\MediaTypeInterface $media_type
   *   The media bundle to download.
   */
  public static function buildBatch(MediaTypeInterface $media_type): void {
    $limit = 10;
    $asset_repository = \Drupal::service('acquia_dam.asset_repository');
    $media_items = self::fetchLocalAssets($media_type, $limit);
    $total_media_items = $asset_repository->countLocalAssets($media_type, FALSE);

    $batch_builder = new BatchBuilder();
    $batch_builder->setTitle(t('Downloading and syncing assets'))
      ->setInitMessage(t('Starting the download and sync process'))
      ->setErrorMessage(t('An error occurred during the download and sync process.'))
      ->setProgressMessage(t('Processed @current out of @total media items.'))
      ->setFinishCallback([self::class, 'finish']);

    $offset = $limit;
    do {
      foreach ($media_items as $media_item) {
        $batch_builder->addOperation(
          [self::class, 'process'],
          [$media_item, $total_media_items],
        );
      }
      $media_items = self::fetchLocalAssets($media_type, $limit, $offset);
      $offset += $limit;
    } while (!empty($media_items));

    batch_set($batch_builder->toArray());
  }

  /**
   * Batch operation callback to process a media item.
   *
   * @param int $media_item
   *   An array of media items to process.
   * @param int $total_media_items
   *   The total number of media items.
   * @param array $context
   *   The batch context array.
   */
  public static function process(int $media_item, int $total_media_items, &$context): void {
    $storage = \Drupal::service('entity_type.manager')->getStorage('media');

    /** @var \Drupal\media\Entity\Media $media */
    $media = $storage->load($media_item);

    static $progress = 0;
    // Initialize sandbox values only during the first execution.
    if (!isset($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = $progress;
      $context['sandbox']['max'] = $total_media_items;
    }

    try {
      $asset_id = $media->getSource()->getMetadata($media, "asset_id");
      /** @var \Drupal\acquia_dam\Plugin\media\acquia_dam\MediaSourceTypeInterface $source */
      $source = $media->getSource();

      // Check if the asset is available in Acquia DAM.
      $status = $source->getMetadata($media, 'is_asset_available');
      $context['message'] = t('processing media item @id', [
        '@id' => $media->id()
      ]);
      // If the asset is not available, we need to unpublish the media entity.
      if (is_null($status) && $media->isPublished()) {
        // The asset is not available in Acquia DAM, unpublish in Drupal.
        $media->setUnpublished()->save();

        // Log the unpublish action.
        \Drupal::service('logger.channel.acquia_dam')->warning(
          'The asset %asset_id is not available in Acquia DAM. Unpublished the media entity: @mid.',
          [
            '%asset_id' => $asset_id,
            '@mid' => $media->id(),
          ]
        );
        return;
      }
      $client = \Drupal::service('acquia_dam.client.factory')->getSiteClient();
      $file = \Drupal::service('acquia_dam.asset_file_helper')->downloadFile($media, $media->getSource(), $client->getAsset($asset_id), $asset_id);

      if ($file) {
        $media->set($source->getLocalFileAssetField(), ['target_id' => $file->id()]);
        $media->save();
      }
    }
    catch (\Exception $e) {
      \Drupal::service('logger.channel.acquia_dam')->error($e->getMessage());
      $context['message'] = t('An error occurred while processing media item @id: @message', [
        '@id' => $media->id(),
        '@message' => $e->getMessage(),
      ]);
      $context['results']['errors'][] = $e->getMessage();
    }

    $context['sandbox']['progress'] = $progress++;

    // Update the progress message.
    $context['message'] = t('Processed @current out of @total media items.', [
      '@current' => $context['sandbox']['progress'],
      '@total' => $context['sandbox']['max'],
    ]);
  }

  /**
   * Batch finished callback.
   *
   * @param bool $success
   *   TRUE if the batch processing was successful, FALSE otherwise.
   * @param array $results
   *   An array of results from the batch processing.
   * @param array $operations
   *   An array of remaining operations, if any.
   */
  public static function finish(bool $success, array $results, array $operations): void {
    $messenger = \Drupal::service('messenger');
    if ($success) {
      $messenger->addMessage(t('All assets have been downloaded and synced successfully.'));
    }
    else {
      $messenger->addMessage(t('An error occurred during the download and sync process.'), 'error');
    }
  }

  /**
   * Fetch local assets that are missing
   * @param \Drupal\media\MediaTypeInterface $media_type
   * @param int $limit
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function fetchLocalAssets(MediaTypeInterface $media_type, int $limit, int $offset = 0): array {
    $query = \Drupal::service('entity_type.manager')
      ->getStorage('media')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('bundle', $media_type->id())
      ->condition('status', 1) // Only published items.
      ->condition($media_type->getSource()->getLocalFileAssetField(), NULL, 'IS NULL')
      ->range($offset, $limit);

    return $query->execute();
  }
}
