<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\EventSubscriber;

use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Rebuilds library definitions if the admin theme changes.
 */
final class SystemThemeConfigSubscriber implements EventSubscriberInterface {

  /**
   * The library discovery.
   *
   * @var \Drupal\Core\Asset\LibraryDiscoveryInterface
   */
  private $libraryDiscovery;

  /**
   * Constructs a new SystemThemeConfigSubscriber object.
   *
   * @param \Drupal\Core\Asset\LibraryDiscoveryInterface $library_discovery
   *   The library discovery.
   */
  public function __construct(LibraryDiscoveryInterface $library_discovery) {
    $this->libraryDiscovery = $library_discovery;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[ConfigEvents::SAVE][] = 'onConfigSave';
    return $events;
  }

  /**
   * Rebuilds library definitions when the admin theme is changed.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The event.
   */
  public function onConfigSave(ConfigCrudEvent $event): void {
    if ($event->getConfig()->getName() === 'system.theme' && $event->isChanged('admin')) {
      $this->libraryDiscovery->clearCachedDefinitions();
    }
  }

}
