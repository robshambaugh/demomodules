<?php

declare(strict_types=1);

namespace Drupal\Tests\acquiadam_asset_import\Kernel;

use Drupal\Tests\acquia_dam\Kernel\AcquiaDamKernelTestBase;

/**
 * Tests config form access.
 *
 * @tests \Drupal\acquiadam_asset_import\Form\BulkImportConfigForm
 *
 * @group acquia_dam
 */
final class BulkImportConfigFormAccessTest extends AcquiaDamKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'views',
    'file',
    'image',
    'media',
    'media_library',
    'views_remote_data',
    'token',
    'user',
    'acquia_dam',
    'acquia_dam_test',
    'acquiadam_asset_import',
  ];

  /**
   * The URL path of the config page.
   */
  const URL_PATH = '/admin/config/acquia-dam/bulk-import';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('view');
    $this->installConfig(['views']);
  }

  /**
   * Tests access control of the config form.
   */
  public function testFormAccess(): void {
    // Set current user as admin.
    $this->drupalSetUpCurrentUser([], [], TRUE);
    $request = $this->getMockedRequest($this::URL_PATH, 'GET');
    $response = $this->processRequest($request);
    self::assertEquals(200, $response->getStatusCode());

    // Set current user as authorised with necessary permission.
    $this->drupalSetUpCurrentUser([], ['administer site configuration']);
    $request = $this->getMockedRequest($this::URL_PATH, 'GET');
    $response = $this->processRequest($request);
    self::assertEquals(200, $response->getStatusCode());

    // Set current user as authorised without necessary permission.
    $this->drupalSetUpCurrentUser();
    $request = $this->getMockedRequest($this::URL_PATH, 'GET');
    $response = $this->processRequest($request);
    self::assertEquals(403, $response->getStatusCode());
  }

}
