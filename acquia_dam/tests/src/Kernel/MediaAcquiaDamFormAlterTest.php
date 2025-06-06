<?php

namespace Drupal\Tests\acquia_dam\Kernel;

/**
 * Tests Media Acquia Dam form alter.
 *
 * @group acquia_dam
 * @requires module media_acquiadam
 */
class MediaAcquiaDamFormAlterTest extends AcquiaDamKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'media_acquiadam',
  ];

  /**
   * Test media Acquia DAM form alter.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testFormAlter(): void {
    $this->drupalSetUpCurrentUser([], [], TRUE);

    $request = $this->getMockedRequest('/admin/config/media/acquiadam', 'GET');
    $response = $this->processRequest($request);
    $content = $response->getContent();
    self::assertEquals(200, $response->getStatusCode());
    self::assertStringContainsString('The site credentials to your Acquia DAM account are now under', $content);
    self::assertStringContainsString('<a href="/admin/config/acquia-dam">Acquia DAM</a>', $content);
  }

}
