<?php

namespace Drupal\Tests\acquia_dam\Kernel;

/**
 * Test AuthPage controller.
 *
 * @group acquia_dam
 */
class UserAuthPageTest extends AcquiaDamKernelTestBase {

  /**
   * Test permission of different users.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testAuthPageAccess(): void {
    $user1 = $this->drupalSetUpCurrentUser(['name' => 'user1']);
    $user2 = $this->drupalSetUpCurrentUser(['name' => 'user2'], ['authorize with acquia dam']);
    $admin_user = $this->drupalSetUpCurrentUser([], [], TRUE);

    $request = $this->getMockedRequest("/user/{$admin_user->id()}/acquia-dam", 'GET');
    $response = $this->processRequest($request);
    self::assertEquals(200, $response->getStatusCode());
    $request = $this->getMockedRequest("/user/{$user1->id()}/acquia-dam", 'GET');
    $response = $this->processRequest($request);
    self::assertEquals(403, $response->getStatusCode());

    // Log in as user1, who does not have the
    // `authorize with acquia dam` permission.
    $this->setCurrentUser($user1);
    $request = $this->getMockedRequest("/user/{$user1->id()}/acquia-dam", 'GET');
    $response = $this->processRequest($request);
    self::assertEquals(403, $response->getStatusCode());

    // Log in as user2, who has the `authorize with acquia dam` permission.
    $this->setCurrentUser($user2);
    $request = $this->getMockedRequest("/user/{$user2->id()}/acquia-dam", 'GET');
    $response = $this->processRequest($request);
    self::assertEquals(200, $response->getStatusCode());

    $this->container->get('acquia_dam.authentication_service')->disconnectSiteAndUsers();
    $request = $this->getMockedRequest("/user/{$user2->id()}/acquia-dam", 'GET');
    $response = $this->processRequest($request);
    self::assertEquals(403, $response->getStatusCode());
  }

  /**
   * Test the auth page content.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testAuthPageContent(): void {
    // Do not user drupalSetUpCurrentUser since it sets the user token.
    $admin_user = $this->drupalCreateUser([
      'administer site configuration',
    ]);

    $this->setCurrentUser($admin_user);
    $request = $this->getMockedRequest("/user/{$admin_user->id()}/acquia-dam", 'GET');
    $response = $this->processRequest($request);
    self::assertEquals(200, $response->getStatusCode());
    $this->assertStringContainsString('Connect to Acquia Dam', $response->getContent());

    $this->container->get('acquia_dam.authentication_service')->setUserData(
      (int) $admin_user->id(),
      [
        'acquia_dam_username' => $admin_user->getEmail(),
        'acquia_dam_token' => $this->randomString(),
      ]
    );

    $request = $this->getMockedRequest("/user/{$admin_user->id()}/acquia-dam", 'GET');
    $response = $this->processRequest($request);
    self::assertEquals(200, $response->getStatusCode());
    $this->assertStringContainsString('Disconnect from Acquia DAM', $response->getContent());
  }

}
