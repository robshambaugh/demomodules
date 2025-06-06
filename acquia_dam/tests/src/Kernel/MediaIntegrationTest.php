<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_dam\Kernel;

use Drupal\acquia_dam\Entity\MediaSourceField;
use Drupal\acquia_dam\Plugin\Field\FieldType\AssetItem;
use Drupal\file\FileInterface;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\media\MediaInterface;

/**
 * Media integration tests.
 *
 * @group acquia_dam
 */
final class MediaIntegrationTest extends AcquiaDamKernelTestBase {

  /**
   * Tests the configuration install for the module.
   */
  public function testInstall(): void {
    $this->installConfig([
      'image',
      'media',
      'media_library',
      'acquia_dam',
    ]);
    self::assertNotNull(MediaType::load('acquia_dam_audio_asset'));
    self::assertNotNull(MediaType::load('acquia_dam_generic_asset'));
    self::assertNotNull(MediaType::load('acquia_dam_pdf_asset'));
    self::assertNotNull(MediaType::load('acquia_dam_video_asset'));
  }

  /**
   * Tests the media source plugin.
   */
  public function testMediaSource(): void {
    $this->drupalSetUpCurrentUser();
    $this->setDamSiteToken();

    $media_source_manager = $this->container->get('plugin.manager.media.source');
    self::assertTrue($media_source_manager->hasDefinition('acquia_dam_asset:audio'));
    self::assertTrue($media_source_manager->hasDefinition('acquia_dam_asset:generic'));
    self::assertTrue($media_source_manager->hasDefinition('acquia_dam_asset:pdf'));
    self::assertTrue($media_source_manager->hasDefinition('acquia_dam_asset:video'));

    $media_type = $this->createPdfMediaType();
    $media = Media::create([
      'bundle' => $media_type->id(),
      'acquia_dam_asset_id' => [
        'asset_id' => '0324b0b2-5293-4aa0-b0aa-c85b003395e2',
        'version_id' => '7b67948f-ee7e-405c-a0cd-344a24d8afb2',
        'external_id' => '8a1ouvfchk',
      ],
    ]);
    $media->save();
    assert($media instanceof MediaInterface);
    self::assertEquals('0324b0b2-5293-4aa0-b0aa-c85b003395e2', $media->get('acquia_dam_asset_id')->asset_id);
    self::assertEquals('8a1ouvfchk', $media->get('acquia_dam_asset_id')->external_id);
    self::assertEquals(
      'public://acquia_dam_thumbnails/0324b0b2-5293-4aa0-b0aa-c85b003395e2/7b67948f-ee7e-405c-a0cd-344a24d8afb2.png',
      $media_type->getSource()->getMetadata($media, 'thumbnail_uri')
    );
    self::assertFileExists($media_type->getSource()->getMetadata($media, 'thumbnail_uri'));
  }

  /**
   * Tests that a version ID is updated when a media entity is inserted.
   */
  public function testMediaPreSave(): void {
    $media_type = $this->createVideoMediaType();
    $media = Media::create([
      'bundle' => $media_type->id(),
      'acquia_dam_asset_id' => [
        'asset_id' => 'efb03f75-3c42-497b-baa9-5ec79d1f56af',
        'version_id' => '',
        'external_id' => '',
      ],
    ]);
    $media->save();
    assert($media instanceof MediaInterface);
    $source_field_item = $media->get(MediaSourceField::SOURCE_FIELD_NAME)->first();
    assert($source_field_item instanceof AssetItem);
    self::assertEquals('efb03f75-3c42-497b-baa9-5ec79d1f56af', $source_field_item->asset_id);
    self::assertEquals('04984534-8682-4fbf-95ae-f3c7b46af9ee', $source_field_item->version_id);
    self::assertEquals('mnmc58hipn', $source_field_item->external_id);
    $revision_id = $media->getRevisionId();

    $media->setNewRevision();
    $source_field_item->version_id = 'a_new_version';
    $media->save();
    $media = $this->reloadEntity($media);
    assert($media instanceof MediaInterface);
    $source_field_item = $media->get(MediaSourceField::SOURCE_FIELD_NAME)->first();
    assert($source_field_item instanceof AssetItem);
    self::assertEquals('efb03f75-3c42-497b-baa9-5ec79d1f56af', $source_field_item->asset_id);
    self::assertEquals('a_new_version', $source_field_item->version_id);

    $media_storage = $this->container->get('entity_type.manager')->getStorage('media');
    $media_revision = $media_storage->loadRevision($revision_id);
    assert($media_revision instanceof MediaInterface);
    $source_field_item = $media_revision->get(MediaSourceField::SOURCE_FIELD_NAME)->first();
    assert($source_field_item instanceof AssetItem);
    self::assertEquals('efb03f75-3c42-497b-baa9-5ec79d1f56af', $source_field_item->asset_id);
    self::assertEquals('04984534-8682-4fbf-95ae-f3c7b46af9ee', $source_field_item->version_id);
  }

  /**
   * Tests that a thumbnail is mapped when a media entity is inserted.
   */
  public function testMediaThumbnails(): void {
    $media_type = $this->createVideoMediaType();
    $media = Media::create([
      'name' => 'SD-Social Promo.mp4',
      'bundle' => $media_type->id(),
      'acquia_dam_asset_id' => [
        'asset_id' => 'efb03f75-3c42-497b-baa9-5ec79d1f56af',
        'version_id' => '',
        'external_id' => '',
      ],
    ]);
    self::assertTrue($media->get('thumbnail')->isEmpty());
    $media->save();
    self::assertFalse($media->get('thumbnail')->isEmpty());
    $thumbnail = $media->get('thumbnail')->entity;
    self::assertInstanceOf(FileInterface::class, $thumbnail);
    self::assertEquals(
      'public://acquia_dam_thumbnails/efb03f75-3c42-497b-baa9-5ec79d1f56af/04984534-8682-4fbf-95ae-f3c7b46af9ee.png',
      $thumbnail->getFileUri()
    );
    $revision_id = $media->getRevisionId();

    $media->get(MediaSourceField::SOURCE_FIELD_NAME)->version_id = '04984534-8682-4fbf-95ae-f3c7b46af9ee';
    $media->setNewRevision(TRUE);
    $media->updateQueuedThumbnail();
    $media->save();
    $media = $this->reloadEntity($media);
    $thumbnail = $media->get('thumbnail')->entity;
    self::assertInstanceOf(FileInterface::class, $thumbnail);
    self::assertEquals(
      'public://acquia_dam_thumbnails/efb03f75-3c42-497b-baa9-5ec79d1f56af/04984534-8682-4fbf-95ae-f3c7b46af9ee.png',
      $thumbnail->getFileUri()
    );

    $media_storage = $this->container->get('entity_type.manager')->getStorage('media');
    $media_revision = $media_storage->loadRevision($revision_id);
    assert($media_revision instanceof MediaInterface);
    self::assertEquals(
      'public://acquia_dam_thumbnails/efb03f75-3c42-497b-baa9-5ec79d1f56af/04984534-8682-4fbf-95ae-f3c7b46af9ee.png',
      $media_revision->get('thumbnail')->entity->getFileUri()
    );
  }

  /**
   * Local asset copies.
   *
   * Tests whether the binary file of a media asset is being downloaded and
   * stored in the local file system.
   *
   * @covers Drupal\acquia_dam\AssetFileEntityHelper::downloadFile
   */
  public function testAssetFileDownload(): void {
    $media_type = $this->createImageMediaType(['download_assets' => TRUE]);
    self::assertTrue($media_type->get('source_configuration')['download_assets']);

    $filename = 'An asset with many versions.png';
    $media_entity = Media::create([
      'bundle' => $media_type->id(),
      'name' => $filename,
      'acquia_dam_asset_id' => [
        'asset_id' => 'f2a9c03d-3664-477c-8013-e84504ed5adc',
        'version_id' => 'e43bde3a-be80-418e-a69e-6de9285afbbf',
      ],
    ]);
    $media_entity->save();
    assert($media_entity instanceof MediaInterface);
    $filename = strtolower(preg_replace('/\s/', '-', $filename));
    self::assertFileExists("public://dam/asset_external_id/$filename", 'The asset file expected to be already downloaded does not exist.');
    self::assertFalse($media_entity->get('acquia_dam_managed_image')->isEmpty());
  }

}
