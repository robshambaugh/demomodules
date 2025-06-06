<?php

namespace Drupal\Tests\acquia_dam\Kernel;

use Drupal\Tests\media\Traits\MediaTypeCreationTrait;

/**
 * Test save button is disabled for acquia dam add media form.
 *
 * @group acquia_dam
 */
class MediaAddFormTest extends AcquiaDamKernelTestBase {

  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installConfig('system');
  }

  /**
   * Test is to check if the save button is disabled for dam assets.
   */
  public function testAddMediaForm() {
    $media_type = $this->createImageMediaType();
    $this->drupalSetUpCurrentUser([], [], TRUE);

    $request = $this->getMockedRequest("/media/add/{$media_type->id()}", 'GET');
    $response = $this->processRequest($request);
    self::assertEquals(200, $response->getStatusCode());
    $button = $this->cssSelect('input[type="submit"]');

    $attributes = [
      'disabled' => 'disabled',
      'id' => 'edit-submit',
      'value' => 'Save',
    ];

    foreach ($attributes as $key => $value) {
      $this->assertEquals($value, $button[0][$key]);
    }

    $type = $this->createMediaType('image');
    $request = $this->getMockedRequest("/media/add/{$type->id()}", 'GET');
    $response = $this->processRequest($request);
    self::assertEquals(200, $response->getStatusCode());
    $button = $this->cssSelect('input[type="submit"]');
    $this->assertFalse(isset($button[0]['disabled']));
  }

}
