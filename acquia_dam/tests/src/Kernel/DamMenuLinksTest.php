<?php

namespace Drupal\Tests\acquia_dam\Kernel;

/**
 * Test Acquia Dam and Media Acquia Dam menu links.
 *
 * @group acquia_dam
 * @requires module media_acquiadam
 */
class DamMenuLinksTest extends AcquiaDamKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'media_acquiadam',
  ];

  /**
   * Test menu links.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testMenuLinks(): void {
    $this->drupalSetUpCurrentUser([], [], TRUE);

    $admin_route = $this->getMockedRequest('/admin/config/media', 'GET');
    $response = $this->processRequest($admin_route);
    self::assertEquals(200, $response->getStatusCode());
    $this->assertLinkByHref('admin/config/acquia-dam');
    $this->assertLinkByHref('/admin/config/media/acquiadam');
    $this->assertLink('Acquia DAM');
    $this->assertLink('Acquia DAM Entity Browser');
  }

}
