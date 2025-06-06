<?php

namespace Drupal\acquia_dam\Plugin\QueueWorker;

use Drupal\acquia_dam\Exception\DamClientException;
use Drupal\acquia_dam\Exception\DamConnectException;
use Drupal\acquia_dam\Exception\DamServerException;
use Drupal\Core\Queue\DelayedRequeueException;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\Queue\SuspendQueueException;

/**
 * Base class for asset queue workers.
 */
abstract class AssetQueueWorkerBase extends QueueWorkerBase {

  /**
   * Process DAM client exceptions.
   *
   * @param \Exception $exception
   *   Exception thrown during API request.
   */
  protected function processException(\Exception $exception): void {
    switch (TRUE) {
      case $exception instanceof DamServerException:
      case $exception instanceof DamClientException && $exception->getCode() === 401:
      case $exception->getMessage() === 'Cannot get Acquia DAM token for client instantiation.':
        throw new SuspendQueueException(
          $exception->getMessage(),
          $exception->getCode(),
          $exception
        );

      case $exception instanceof DamClientException && $exception->getCode() === 408:
        throw new DelayedRequeueException(60, 'Timed out loading asset, trying again later.', 408, $exception);

      case $exception instanceof DamConnectException:
        throw new DelayedRequeueException(60, 'Unable to complete network request.', $exception->getCode(), $exception);

      default:
        // Try again for unknown 4xx responses.
        throw new RequeueException();
    }
  }

}
