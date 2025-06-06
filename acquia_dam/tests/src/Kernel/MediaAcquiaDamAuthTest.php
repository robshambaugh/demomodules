<?php

namespace Drupal\Tests\acquia_dam\Kernel;

use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests access of the form when the media acquia dam installed.
 *
 * @group acquia_dam
 * @requires module media_acquiadam
 */
class MediaAcquiaDamAuthTest extends AcquiaDamKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'media_acquiadam',
  ];

  /**
   * Test user form access and media_acquiadam user state details.
   */
  public function testUserFormCheck() {
    $user = $this->drupalSetUpCurrentUser([], [], TRUE);

    $request = $this->getMockedRequest($user->toUrl('edit-form')->toString(), 'POST');
    $response = $this->processRequest($request);
    self::assertEquals(200, $response->getStatusCode());
    self::assertStringNotContainsString('Acquia DAM Authorization', $response->getContent());

    $user_data = $this->container->get('user.data');
    $media_acquiadam = $user_data->get('media_acquiadam', $user->id(), 'account');
    $this->assertArrayHasKey('acquiadam_username', $media_acquiadam);
    $this->assertArrayHasKey('acquiadam_token', $media_acquiadam);

    $acquia_dam = $user_data->get('acquia_dam', $user->id(), 'account');
    $this->assertEquals($acquia_dam['acquia_dam_username'], $media_acquiadam['acquiadam_username']);
    $this->assertEquals($acquia_dam['acquia_dam_token'], $media_acquiadam['acquiadam_token']);
  }

  /**
   * Test config changes in acquia_dam is reflected in media_acquiadam.
   */
  public function testConfigFormCheck() {
    $this->assertNotEquals('test.widencollective.edited', $this->config('acquia_dam.settings')->get('domain'));
    $this->assertNotEquals('test.widencollective.edited', $this->config('media_acquiadam.settings')->get('domain'));

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
    $this->assertEquals('test.widencollective.edited', $this->config('media_acquiadam.settings')->get('domain'));
  }

}
