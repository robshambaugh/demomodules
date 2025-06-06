<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_dam\Kernel;

use Drupal\acquia_dam\Entity\MediaExpiryDateField;
use Drupal\acquia_dam\Entity\MediaSourceField;
use Drupal\Core\Config\FileStorage;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\media\Entity\Media;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Symfony\Component\HttpFoundation\Request;

// Workaround to support tests against both Drupal 10.1 and Drupal 11.0.
// @todo Remove once we depend on Drupal 10.2.
if (!trait_exists(EntityReferenceFieldCreationTrait::class)) {
  class_alias('\Drupal\Tests\field\Traits\EntityReferenceTestTrait', EntityReferenceFieldCreationTrait::class);
}

/**
 * Test Acquia Dam expiry tag.
 *
 * @group acquia_dam
 */
final class ExpiredTagTest extends AcquiaDamKernelTestBase {

  use EntityReferenceFieldCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'image']);

    $source = new FileStorage(__DIR__ . '/../../../config/install');
    $view = $this->container->get('entity_type.manager')
      ->getStorage('view')
      ->createFromStorageRecord($source->read('views.view.dam_content_overview'));
    $view->save();

    $this->createEntityReferenceField('entity_test', 'entity_test', 'media_field', 'A Media Field', 'media', 'default', [], -1);
    $display_repository = $this->container->get('entity_display.repository');
    $display_repository->getFormDisplay('entity_test', 'entity_test')
      ->setComponent('media_field', [
        'type' => 'media_library_widget',
        'region' => 'content',
      ])
      ->save();
    $display_repository->getViewDisplay('entity_test', 'entity_test', 'default')
      ->setComponent('media_field', [
        'type' => 'entity_reference_entity_view',
      ])
      ->save();
  }

  /**
   * Test the tags and expiration.
   */
  public function testExpiryTag(): void {
    $this->drupalSetUpCurrentUser([], [
      'administer entity_test content',
      'view media',
    ]);
    $media_type = $this->createDocumentMediaType();

    $time = $this->container->get('datetime.time');
    $media_expired = Media::create([
      'bundle' => $media_type->id(),
      MediaSourceField::SOURCE_FIELD_NAME => [
        'asset_id' => 'efb03f75-3c42-497b-baa9-5ec79d1f56af',
      ],
      MediaExpiryDateField::EXPIRY_DATE_FIELD_NAME => [
        'value' => $time->getCurrentTime() - 86400,
      ],
    ]);
    $media_expired->save();
    $media = Media::create([
      'bundle' => $media_type->id(),
      MediaSourceField::SOURCE_FIELD_NAME => [
        'asset_id' => '0324b0b2-5293-4aa0-b0aa-c85b003395e2',
      ],
      MediaExpiryDateField::EXPIRY_DATE_FIELD_NAME => [
        'value' => $time->getCurrentTime() + 86400,
      ],
    ]);
    $media->save();

    $entity = EntityTest::create([
      'name' => 'test_expiry',
      'media_field' => [
        ['target_id' => $media_expired->id()],
        ['target_id' => $media->id()],
      ],
    ]);
    $entity->save();

    $response = $this->processRequest(
      Request::create($entity->toUrl('edit-form')->toString())
    );
    self::assertEquals(200, $response->getStatusCode());

    $media_items = $this->cssSelect('.js-media-library-item');
    self::assertCount(2, $media_items);
    $expired_items = $this->cssSelect('.acquia-dam-expired-asset');
    self::assertCount(1, $expired_items);

    $response = $this->processRequest(
      Request::create('/admin/content/dam-media')
    );
    self::assertEquals(200, $response->getStatusCode());

    $media_items = $this->cssSelect('.views-field-acquia-dam-expiry-date-value');
    self::assertCount(2, $media_items);
    $expired_items = $this->cssSelect('.acquia-dam-expired-asset');
    self::assertCount(1, $expired_items);
  }

}
