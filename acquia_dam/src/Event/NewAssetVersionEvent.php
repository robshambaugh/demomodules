<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\media\MediaInterface;

/**
 * Defines an event dispatched when an asset has a new version.
 */
final class NewAssetVersionEvent extends Event {

  /**
   * The current media version.
   *
   * @var \Drupal\media\MediaInterface
   */
  private $latest;

  /**
   * The previous media version.
   *
   * @var \Drupal\media\MediaInterface
   */
  private $previous;

  /**
   * Constructs a new NewAssetVersionEvent object.
   *
   * @param \Drupal\media\MediaInterface $latest
   *   The latest media version.
   * @param \Drupal\media\MediaInterface $previous
   *   The previous media version.
   */
  public function __construct(MediaInterface $latest, MediaInterface $previous) {
    $this->latest = $latest;
    $this->previous = $previous;
  }

  /**
   * Get the latest media version.
   *
   * @return \Drupal\media\MediaInterface
   *   The media.
   */
  public function getLatest(): MediaInterface {
    return $this->latest;
  }

  /**
   * Get the previous media version.
   *
   * @return \Drupal\media\MediaInterface
   *   The media.
   */
  public function getPrevious(): MediaInterface {
    return $this->previous;
  }

}
