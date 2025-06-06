<?php

namespace Drupal\Tests\acquia_dam\Kernel;

use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\media\Entity\Media;

/**
 * Tests mapping of metadata.
 *
 * @group acquia_dam
 */
class MediaSourceAssetMapTest extends AcquiaDamKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'datetime',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    $this->config('system.date')
      ->set('timezone.default', 'UTC')
      ->save();
  }

  /**
   * Tests Media metadata mapping.
   *
   * @param string $metadata_field_name
   *   The metadata field name.
   * @param string $field_type
   *   The field's type.
   * @param array $field_storage_settings
   *   The field's storage setting.
   * @param array $expected_value
   *   The expected mapped value.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *
   * @dataProvider metadataMappingData
   */
  public function testMetadataMapWithVersion(string $metadata_field_name, string $field_type, array $field_storage_settings, array $expected_value) {
    $this->drupalSetUpCurrentUser();

    $configFactory = $this->container->get('config.factory');
    $configFactory->getEditable('acquia_dam.settings')
      ->set('allowed_metadata', [$metadata_field_name])
      ->save();
    $media_type = $this->createImageMediaType();

    $field_storage = FieldStorageConfig::create([
      'entity_type' => 'media',
      'field_name' => 'field_sut',
      'type' => $field_type,
      'settings' => $field_storage_settings,
    ]);
    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $media_type->id(),
      'label' => 'field_sut',
    ])->save();
    $field_storage->save();

    $media_type->setFieldMap([
      $metadata_field_name => 'field_sut',
    ])->save();
    $media = Media::create([
      'bundle' => $media_type->id(),
      'name' => 'Wheel Illustration.ai',
      'acquia_dam_asset_id' => [
        'asset_id' => '56ff14de-02cd-41b5-9a73-c917eab19abf',
        'version_id' => '7b67948f-ee7e-405c-a0cd-344a24d8afb2',
      ],
    ]);
    $media->save();
    $media = $this->reloadEntity($media);
    self::assertEquals($expected_value, $media->get('field_sut')->getValue());

    // Clear the value to verify cron + version bump sync metadata.
    $media->set('field_sut', NULL);
    self::assertTrue($media->get('field_sut')->isEmpty());
    $this->container->get('cron')->run();

    // Reload media instance after cron run. The version will have updated,
    // which causes a change in the source field and re-syncs the metadata.
    $media = $this->reloadEntity($media);
    $updated_version_id = $media->get('acquia_dam_asset_id')->version_id;
    self::assertEquals('9e4e810c-147b-4ac2-85a9-cf64f8fa61e0', $updated_version_id);
    self::assertEquals($expected_value, $media->get('field_sut')->getValue());
  }

  /**
   * The metadata mapping test data.
   *
   * @return \Generator
   *   The data.
   */
  public static function metadataMappingData() {
    yield 'assettype string' => [
      'assettype',
      'string',
      [],
      [['value' => 'image']],
    ];
    yield 'assettype text' => [
      'assettype',
      'text',
      [],
      [['value' => 'image', 'format' => NULL]],
    ];
    yield 'dateSent string' => [
      'dateSent',
      'string',
      [],
      [['value' => '2022-06-19']],
    ];
    yield 'dateSent timestamp' => [
      'dateSent',
      'timestamp',
      [],
      [['value' => '1655640000']],
    ];
    yield 'dateSent datetime' => [
      'dateSent',
      'datetime',
      ['datetime_type' => DateTimeItem::DATETIME_TYPE_DATETIME],
      [['value' => '2022-06-19T12:00:00']],
    ];
    yield 'dateSent date' => [
      'dateSent',
      'datetime',
      ['datetime_type' => DateTimeItem::DATETIME_TYPE_DATE],
      [['value' => '2022-06-19']],
    ];
    yield 'expiration_date datetime' => [
      'expiration_date',
      'datetime',
      ['datetime_type' => DateTimeItem::DATETIME_TYPE_DATETIME],
      [],
    ];
    yield 'expiration_date date' => [
      'expiration_date',
      'datetime',
      ['datetime_type' => DateTimeItem::DATETIME_TYPE_DATE],
      [],
    ];
    yield 'release_date date' => [
      'release_date',
      'datetime',
      ['datetime_type' => DateTimeItem::DATETIME_TYPE_DATE],
      [['value' => '2021-09-23']],
    ];
    yield 'release_date datetime' => [
      'release_date',
      'datetime',
      ['datetime_type' => DateTimeItem::DATETIME_TYPE_DATETIME],
      [['value' => '2021-09-23T05:00:00']],
    ];
    yield 'last_update_date date' => [
      'last_update_date',
      'datetime',
      ['datetime_type' => DateTimeItem::DATETIME_TYPE_DATE],
      [['value' => '2021-09-23']],
    ];
    yield 'last_update_date datetime' => [
      'last_update_date',
      'datetime',
      ['datetime_type' => DateTimeItem::DATETIME_TYPE_DATETIME],
      [['value' => '2021-09-23T22:35:27']],
    ];
    yield 'file_upload_date date' => [
      'file_upload_date',
      'datetime',
      ['datetime_type' => DateTimeItem::DATETIME_TYPE_DATE],
      [['value' => '2021-09-23']],
    ];
    yield 'file_upload_date datetime' => [
      'file_upload_date',
      'datetime',
      ['datetime_type' => DateTimeItem::DATETIME_TYPE_DATETIME],
      [['value' => '2021-09-23T22:34:29']],
    ];
    yield 'deleted_date date' => [
      'deleted_date',
      'datetime',
      ['datetime_type' => DateTimeItem::DATETIME_TYPE_DATE],
      [],
    ];
    yield 'deleted_date datetime' => [
      'deleted_date',
      'datetime',
      ['datetime_type' => DateTimeItem::DATETIME_TYPE_DATETIME],
      [],
    ];
    yield 'format_type string' => [
      'format_type',
      'string',
      [],
      [['value' => 'image']],
    ];
    yield 'format string' => [
      'format',
      'string',
      [],
      [['value' => 'IllustratorNative']],
    ];
  }

}
