<?php

namespace Drupal\Tests\acquia_dam\Kernel;

use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;

/**
 * Tests asset version update with media_acquiadam.
 *
 * @group acquia_dam
 */
class AssetVersionUpdateTest extends AcquiaDamKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'acquia_dam_test',
  ];

  /**
   * Tests asset version update.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testUpdateAssetVersion() {
    $this->drupalSetUpCurrentUser();

    $media_type = $this->createImageMediaType();
    $media = Media::create([
      'bundle' => $media_type->id(),
      'name' => 'Wheel Illustration.ai',
      'acquia_dam_asset_id' => [
        'asset_id' => '56ff14de-02cd-41b5-9a73-c917eab19abf',
        'version_id' => '7b67948f-ee7e-405c-a0cd-344a24d8afb2',
      ],
    ]);
    $media->save();
    assert($media instanceof MediaInterface);
    self::assertEquals(
      'public://acquia_dam_thumbnails/56ff14de-02cd-41b5-9a73-c917eab19abf/7b67948f-ee7e-405c-a0cd-344a24d8afb2.png',
      $media->get('thumbnail')->entity->getFileUri()
    );

    $this->container->get('cron')->run();

    // Reload media instance after cron run.
    $media = $this->reloadEntity($media);
    $updated_version_id = $media->get('acquia_dam_asset_id')->version_id;
    self::assertEquals('9e4e810c-147b-4ac2-85a9-cf64f8fa61e0', $updated_version_id);
    self::assertEquals(
      'public://acquia_dam_thumbnails/56ff14de-02cd-41b5-9a73-c917eab19abf/9e4e810c-147b-4ac2-85a9-cf64f8fa61e0.png',
      $media->get('thumbnail')->entity->getFileUri()
    );
  }

}
