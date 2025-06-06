<?php

namespace Drupal\Tests\acquia_dam\Kernel;

/**
 * Tests DAM authentication service disconnectSiteAndUsers method.
 *
 * @group acquia_dam
 */
class DamDomainChangeTest extends AcquiaDamKernelTestBase {

  /**
   * Tests that handleDomainChange deletes user and site tokens.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testSiteAndUsersDisconnect(): void {
    $this->drupalSetUpCurrentUser();

    $auth_service = $this->container->get('acquia_dam.authentication_service');

    $user_data = $this->container->get('user.data');
    $this->assertNotEmpty($user_data->get('acquia_dam'));

    $auth_service->disconnectSiteAndUsers();

    $this->assertEmpty($auth_service->getSiteToken());
    $this->assertEmpty($user_data->get('acquia_dam'));
  }

}
