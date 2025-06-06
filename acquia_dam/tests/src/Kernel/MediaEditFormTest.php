<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_dam\Kernel;

use Drupal\acquia_dam\Entity\MediaSourceField;
use Drupal\media\Entity\Media;

/**
 * Test media edit form.
 *
 * @group acquia_dam
 */
final class MediaEditFormTest extends AcquiaDamKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installConfig('system');
  }

  /**
   * Tests that the edit form is available with alterations.
   */
  public function testEditForm(): void {
    $media_type = $this->createImageMediaType();
    $this->drupalSetUpCurrentUser([], [], TRUE);

    $media = Media::create([
      'bundle' => $media_type->id(),
      MediaSourceField::SOURCE_FIELD_NAME => [
        'asset_id' => '4f656c07-6a08-47b3-9403-16082d2fcda2',
      ],
    ]);
    $media->save();

    $request = $this->getMockedRequest($media->toUrl('edit-form')->toString(), 'GET');
    $response = $this->processRequest($request);
    self::assertEquals(200, $response->getStatusCode());
  }

}
