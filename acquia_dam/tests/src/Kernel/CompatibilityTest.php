<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_dam\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests compatibility with media_acquiadam.
 *
 * @group acquia_dam
 * @requires module media_acquiadam
 */
final class CompatibilityTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * The test users.
   *
   * @var array
   */
  private $testUserData = [];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'file',
    'filter',
    'views',
    'media',
    'media_library',
    'media_acquiadam',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('user');
    $this->installConfig(['media_acquiadam']);
    $this->config('media_acquiadam.settings')
      ->set('domain', 'foo.bar.com')
      ->set('token', 'the_integration_token')
      ->save();

    $user1 = $this->createUser();
    $this->testUserData[(int) $user1->id()] = [
      'acquiadam_username' => $user1->getEmail(),
      'acquiadam_token' => $this->randomString(),
    ];
    $user2 = $this->createUser();
    $this->testUserData[(int) $user2->id()] = [
      'acquiadam_username' => $user2->getEmail(),
      'acquiadam_token' => $this->randomString(),
    ];

    $user_data = $this->container->get('user.data');
    foreach ($this->testUserData as $user_id => $user_data_value) {
      $user_data->set('media_acquiadam', $user_id, 'account', $user_data_value);
    }
  }

  /**
   * Tests that data is copied over on installation.
   */
  public function testInstall(): void {
    $this->enableModules(['acquia_dam']);
    $this->container->get('module_handler')->loadInclude('acquia_dam', 'install');
    acquia_dam_install(FALSE);

    $acquia_dam_settings = $this->config('acquia_dam.settings');
    self::assertEquals('foo.bar.com', $acquia_dam_settings->get('domain'));

    $site_token = $this->container->get('state')->get('acquia_dam_token');
    self::assertEquals('the_integration_token', $site_token);

    $auth_service = $this->container->get('acquia_dam.authentication_service');
    foreach ($this->testUserData as $user_id => $expected_user_data) {
      $user_data = $auth_service->getUserData($user_id);
      self::assertEquals([
        'acquia_dam_username' => $expected_user_data['acquiadam_username'],
        'acquia_dam_token' => $expected_user_data['acquiadam_token'],
      ], $user_data);
    }
  }

}
