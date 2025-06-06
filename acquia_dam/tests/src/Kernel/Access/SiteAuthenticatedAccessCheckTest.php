<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_dam\Kernel\Access;

use Drupal\Tests\acquia_dam\Kernel\AcquiaDamKernelTestBase;

/**
 * Tests SiteAuthenticatedAccessCheck.
 *
 * @group acquia_dam
 */
final class SiteAuthenticatedAccessCheckTest extends AcquiaDamKernelTestBase {

  /**
   * Tests access based on site authentication.
   */
  public function testAccess(): void {
    $auth_service = $this->container->get('acquia_dam.authentication_service');
    $sut = $this->container->get('acquia_dam.site_authenticated_access_check');

    // Parent setup grants site token by default.
    self::assertTrue($sut->access()->isAllowed());

    $auth_service->disconnectSiteAndUsers();
    self::assertFalse($sut->access()->isAllowed());

    $auth_service->authenticateDam('ABC');
    self::assertTrue($sut->access()->isAllowed());
  }

}
