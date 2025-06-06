<?php

namespace Drupal\acquia_dam_integration_links;

use Drupal\acquia_dam\IntegrationLinkRegister;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\media\MediaInterface;
use Drupal\paragraphs\ParagraphInterface;
use Psr\Log\LoggerInterface;

/**
 * Enhanced integration link register service.
 */
class EnhancedIntegrationLinkRegister {

  /**
   * Entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Integration link register service.
   *
   * @var \Drupal\acquia_dam\IntegrationLinkRegister
   */
  protected $register;

  /**
   * Asset tracker service.
   *
   * @var \Drupal\acquia_dam_integration_links\AssetTracker
   */
  protected $assetTracker;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * AssetTracker constructor.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   Entity field manager.
   * @param \Drupal\acquia_dam_integration_links\AssetTracker $assetTracker
   *   Asset tracker service.
   * @param \Drupal\acquia_dam\IntegrationLinkRegister $register
   *   Integration link register service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(EntityFieldManagerInterface $entityFieldManager, AssetTracker $assetTracker, IntegrationLinkRegister $register, LoggerInterface $logger) {
    $this->entityFieldManager = $entityFieldManager;
    $this->assetTracker = $assetTracker;
    $this->register = $register;
    $this->logger = $logger;
  }

  /**
   * Discover asset usage updates, saves locally and notifies the service.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity instance.
   */
  public function trackAssetUsage(EntityInterface $entity) {
    if (!$this->isEntityEligible($entity)) {
      return;
    }

    $field_definitions = $this
      ->entityFieldManager
      ->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $title_changed = $this->isTitleChanged($entity);
    $result = $this->assetTracker->runAssetDiscovery($entity, $field_definitions, $title_changed);

    // If title changes we re-register everything because of description change.
    if ($title_changed) {
      $this->removeAssetUsage($entity);
    }

    if (!empty($result['asset_to_register'])) {
      $this->registerIntegrationLinks($result['asset_to_register'], $entity);
    }

    if (!empty($result['assets_to_remove'])) {
      foreach ($result['assets_to_remove'] as $asset_id) {
        if ($asset_id === NULL || $asset_id === '') {
          $this->logger->warning("Empty asset ID found when removing integration link for {$entity->label()} ({$entity->id()}.");
          continue;
        }
        $this->register->addToTrackingDeleteList($asset_id, $entity->uuid());
      }
    }
  }

  /**
   * Remove all asset usage, deletes from local store and notifies the service.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity instance.
   */
  public function removeAssetUsage(EntityInterface $entity): void {
    $this->register->addIntegrationToDeleteList($entity->uuid());
  }

  /**
   * Passes discovered usage information to integration link register.
   *
   * @param array $discovered_usage
   *   Discovered assets.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Related entity instance.
   */
  protected function registerIntegrationLinks(array $discovered_usage, ContentEntityInterface $entity): void {
    foreach ($discovered_usage as $asset_uuid) {
      $this->register->addIntegrationLinksList($asset_uuid, $entity);
    }
  }

  /**
   * Determines if entity is eligible for discovery process.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity instance.
   *
   * @return bool
   *   TRUE if discovery process can start, FALSE otherwise.
   */
  protected function isEntityEligible(EntityInterface $entity): bool {
    if ($entity instanceof MediaInterface || $entity instanceof ParagraphInterface) {
      // Media entity reference tracked by the main module.
      // Tracking of paragraphs started within ParagraphsAssetDetector.
      return FALSE;
    }

    if ($entity instanceof ContentEntityInterface) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Checks title changed or not.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity instance.
   *
   * @return bool
   *   TRUE if label changed, FALSE otherwise.
   */
  protected function isTitleChanged(ContentEntityInterface $entity): bool {
    if (!isset($entity->original)) {
      // Title not changed since entity is new, return FALSE.
      return FALSE;
    }
    return $entity->label() !== $entity->original->label();
  }

}
