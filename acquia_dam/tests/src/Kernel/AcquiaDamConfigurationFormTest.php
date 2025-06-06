<?php

namespace Drupal\Tests\acquia_dam\Kernel;

use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;

/**
 * Test config form access.
 *
 * @group acquia_dam
 */
class AcquiaDamConfigurationFormTest extends AcquiaDamKernelTestBase {

  /**
   * Tests config form access.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testFormAccess(): void {
    // Set current user as admin.
    $this->drupalSetUpCurrentUser([], [], TRUE);

    $request = $this->getMockedRequest("/admin/config/acquia-dam", 'GET');
    $response = $this->processRequest($request);
    self::assertEquals(200, $response->getStatusCode());

    // Set current user as authorised with necessary permission.
    $this->drupalSetUpCurrentUser([], ['administer site configuration']);
    $request = $this->getMockedRequest("/admin/config/acquia-dam", 'GET');
    $response = $this->processRequest($request);
    self::assertEquals(200, $response->getStatusCode());

    // Set current user as authorised without necessary permission.
    $this->drupalSetUpCurrentUser();
    $request = $this->getMockedRequest("/admin/config/acquia-dam", 'GET');
    $response = $this->processRequest($request);
    self::assertEquals(403, $response->getStatusCode());
  }

  /**
   * Test form input of the domain.
   */
  public function testFormDomainField(): void {
    $this->drupalSetUpCurrentUser([], [], TRUE);
    $url = Url::fromRoute('acquia_dam.config');
    $this->processRequest(Request::create($url->toString()));
    $response = $this->doFormSubmit(
      $url->toString(),
      ['domain' => 'test.widencollective.edited'],
      'Save DAM configuration'
    );
    self::assertEquals(302, $response->getStatusCode());
    $this->assertEquals('test.widencollective.edited', $this->config('acquia_dam.settings')->get('domain'));
    $this->assertTrue($response->isRedirect('https://test.widencollective.edited/allowaccess?client_id=3b41085e6ff4d9f87307f4418bfce7ef6ed12860.app.widen.com&redirect_uri=http%3A//localhost/acquia-dam/auth'));
  }

}
