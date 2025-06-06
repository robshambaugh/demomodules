<?php

declare(strict_types=1);

namespace Drupal\acquia_dam;

use Drupal\acquia_dam\Client\AcquiaDamClient;
use Drupal\acquia_dam\Client\AcquiaDamClientFactory;
use Drupal\acquia_dam\Exception\DamServerException;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Helper class to tell the finalized version of a DAM asset.
 */
class AssetVersionResolver {

  use StringTranslationTrait;

  /**
   * The site-wide operable HTTP client for DAM.
   *
   * @var \Drupal\acquia_dam\Client\AcquiaDamClient
   */
  private AcquiaDamClient $damClient;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  private MessengerInterface $messenger;

  /**
   * Constructs a new AssetVersionResolver object.
   *
   * @param \Drupal\acquia_dam\Client\AcquiaDamClientFactory $client_factory
   *   The module's factory service to produce its own HTTP client objects.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(AcquiaDamClientFactory $client_factory, MessengerInterface $messenger) {
    $this->damClient = $client_factory->getSiteClient();
    $this->messenger = $messenger;
  }

  /**
   * Returns the UUID of the asset version marked as 'Finalized' in Widen.
   *
   * @param string $asset_id
   *   The DAM asset UUID.
   *
   * @return string
   *   The asset version UUID or empty string if unable to determine.
   *
   * @throws \Drupal\acquia_dam\Exception\DamServerException
   */
  public function getFinalizedVersion(string $asset_id): string {
    $asset_versions = $remote_versions = [];

    try {
      $asset_versions = $this->damClient->getAssetVersions($asset_id);
    }
    catch (\Exception $exception) {
      $this->messenger->addWarning($this->t('Cannot get the version list from the API for asset of ID %asset_id. The error is: %message', [
        '%asset_id' => $asset_id,
        '%message' => $exception->getMessage(),
      ])
      );
    }

    // When the client cannot obtain the version list, then nothing left to do.
    if (!$asset_versions) {
      return '';
    }

    foreach ($asset_versions as $asset_version) {
      if ($asset_version['current_version']) {
        $remote_versions[$asset_version['version_number']] = $asset_version['id'];
      }
    }

    if (count($remote_versions) !== 1) {
      throw new DamServerException('Illegal state detected: incorrect number of asset versions are marked as finalized.');
    }

    return reset($remote_versions) ?: '';
  }

}
