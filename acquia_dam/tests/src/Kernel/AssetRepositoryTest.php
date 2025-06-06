<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_dam\Kernel;

use Drupal\acquia_dam\AssetRepository;
use Drupal\acquia_dam\Entity\MediaSourceField;
use Drupal\media\Entity\Media;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;

/**
 * Tests AssetRepository.
 *
 * @group acquia_dam
 */
final class AssetRepositoryTest extends AcquiaDamKernelTestBase {

  use MediaTypeCreationTrait {
    createMediaType as drupalCreateMediaType;
  }

  /**
   * Tests importing ids.
   *
   * @dataProvider providerImportIds
   */
  public function testImport(array $selected_ids, array $expected_ids): void {
    $this->drupalSetUpCurrentUser();
    $this->setDamSiteToken();

    $this->createPdfMediaType();

    /** @var \Drupal\acquia_dam\AssetRepository $instance */
    $instance = $this->container->get('acquia_dam.asset_repository');
    self::assertInstanceOf(AssetRepository::class, $instance);

    $imported_ids = $instance->import($selected_ids);
    self::assertEquals($expected_ids, $imported_ids);
  }

  /**
   * Import IDs provider.
   */
  public static function providerImportIds() {
    yield 'empty no fail' => [
      [],
      [],
    ];
    yield 'import' => [
      [
        '0324b0b2-5293-4aa0-b0aa-c85b003395e2',
      ],
      ['1'],
    ];
    yield 'import multiple' => [
      [
        '0324b0b2-5293-4aa0-b0aa-c85b003395e2',
        '4f656c07-6a08-47b3-9403-16082d2fcda2',
      ],
      ['1', '2'],
    ];
  }

  /**
   * Tests finding existing media assets ids.
   *
   * @dataProvider providerFindIds
   */
  public function testFind(array $selected_ids, array $expected_ids): void {
    $this->drupalSetUpCurrentUser();
    $this->setDamSiteToken();

    $media_type = $this->createPdfMediaType();
    Media::create([
      'bundle' => $media_type->id(),
      MediaSourceField::SOURCE_FIELD_NAME => [
        'asset_id' => 'a56fb261-8ad5-4e0d-8323-0e8a3659ed39',
      ],
    ])->save();

    Media::create([
      'bundle' => $media_type->id(),
      MediaSourceField::SOURCE_FIELD_NAME => [
        'asset_id' => 'abab96ac-c2ed-40b1-aaf7-56a52f898230',
      ],
    ])->save();

    /** @var \Drupal\acquia_dam\AssetRepository $instance */
    $instance = $this->container->get('acquia_dam.asset_repository');
    self::assertInstanceOf(AssetRepository::class, $instance);

    $existing_ids = $instance->find($selected_ids);
    self::assertEquals($expected_ids, $existing_ids);
  }

  /**
   * Find IDs provider.
   */
  public static function providerFindIds() {
    yield 'empty no fail' => [
      [],
      [],
    ];
    yield 'not existing' => [
      [
        '0324b0b2-5293-4aa0-b0aa-c85b003395e2',
      ],
      [],
    ];
    yield 'find existing' => [
      [
        'a56fb261-8ad5-4e0d-8323-0e8a3659ed39',
      ],
      ['1'],
    ];
    yield 'find existing multiple' => [
      [
        'a56fb261-8ad5-4e0d-8323-0e8a3659ed39',
        'abab96ac-c2ed-40b1-aaf7-56a52f898230',
      ],
      ['1', '2'],
    ];
  }

  /**
   * Tests countLocalAssets method.
   *
   * @covers ::countLocalAssets
   */
  public function testCountLocalAssets(): void {
    $image = $this->createImageMediaType();
    /** @var \Drupal\acquia_dam\AssetRepository $instance */
    $instance = $this->container->get('acquia_dam.asset_repository');
    self::assertInstanceOf(AssetRepository::class, $instance);

    $this->assertEquals(0, $instance->countLocalAssets($image), "When there are no media items, the asset count should be 0");

    // By default, the download_sync option is embed & we are importing assets.
    $instance->import([
      "f2a9c03d-3664-477c-8013-e84504ed5adc",
    ]);

    $this->assertEquals(0, $instance->countLocalAssets($image), "When assets are imported and assets doesn't exist locally.");
  }

}
