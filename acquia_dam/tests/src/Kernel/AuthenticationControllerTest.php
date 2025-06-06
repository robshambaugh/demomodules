<?php

namespace Drupal\Tests\acquia_dam\Kernel;

/**
 * Test Acquia Dam authentication request handling.
 *
 * @group acquia_dam
 */
class AuthenticationControllerTest extends AcquiaDamKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'dblog',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installSchema('dblog', ['watchdog']);
  }

  /**
   * Test how the site handling the errors thrown from the server.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testAuthPageResponse(): void {
    $admin = $this->drupalSetUpCurrentUser([], [], TRUE);

    $request = $this->getMockedRequest("/acquia-dam/auth?code=server_error", 'GET');
    $response = $this->processRequest($request);
    self::assertEquals(302, $response->getStatusCode());
    $this->assertStringContainsString('/admin/config/acquia-dam', $response->getContent());

    $this->assertTrue($this->isErrorLogged('Server error: `POST https://test.widencollective.com/api/rest/oauth/token` resulted in a `502 Bad Gateway` response'));
    $this->assertTrue($this->isErrorLogged('Error during site authentication: Something went wrong contacting Acquia DAM, and your account can’t be connected. Contact the site administrator.'));

    $request = $this->getMockedRequest("/user/acquia-dam/auth?code=client_error", 'GET');
    $response = $this->processRequest($request);
    self::assertEquals(302, $response->getStatusCode());
    $this->assertTrue($this->isErrorLogged('User authentication request does not contain user id.'));

    $request = $this->getMockedRequest("/user/acquia-dam/auth?uid={$admin->id()}", 'GET');
    $response = $this->processRequest($request);
    self::assertEquals(302, $response->getStatusCode());
    $this->assertTrue($this->isErrorLogged('Authentication request does not contain authentication code.'));
  }

  /**
   * Checks if the given error message is in the watchdog table.
   *
   * @param string $message
   *   Error message.
   *
   * @return bool
   *   TRUE if the message present, FALSE otherwise.
   */
  protected function isErrorLogged(string $message) {
    return (bool) \Drupal::database()
      ->select('watchdog', 'w')
      ->fields('w')
      ->condition('message', $message)
      ->countQuery()
      ->execute()
      ->fetchField();
  }

}
