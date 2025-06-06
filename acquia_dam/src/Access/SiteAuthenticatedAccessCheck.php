<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\Access;

use Drupal\acquia_dam\AcquiadamAuthService;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;

/**
 * Acquia DAM site authentication access check.
 */
final class SiteAuthenticatedAccessCheck implements AccessInterface {

  /**
   * The Acquia DAM authentication service.
   */
  protected AcquiadamAuthService $authService;

  /**
   * Creates a new AcquiaDamConfiguredAccessCheck.
   *
   * @param \Drupal\acquia_dam\AcquiadamAuthService $auth_service
   *   The Acquia DAM authentication service.
   */
  public function __construct(AcquiadamAuthService $auth_service) {
    $this->authService = $auth_service;
  }

  /**
   * Checks if the site is authenticated to Acquia DAM for access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(): AccessResultInterface {
    return AccessResult::allowedIf($this->authService->isSiteAuthenticated())
      ->addCacheTags([AcquiadamAuthService::AUTHORIZED_CACHE_TAG]);
  }

}
