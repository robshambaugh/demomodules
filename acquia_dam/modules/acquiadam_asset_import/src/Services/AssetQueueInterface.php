<?php

namespace Drupal\acquiadam_asset_import\Services;

/**
 * Interface for AssetQueue.
 */
interface AssetQueueInterface {

  /**
   * Get categories and asset groups to queue from config.
   *
   * @return int|null
   *   Number of items added in queue. Shouldn't return null, but kept for BC.
 */
  public function addAssetsToQueue(): ?int;

}
