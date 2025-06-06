<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_dam\Unit;

use Drupal\acquia_dam\Entity\MediaSourceField;
use Drupal\acquia_dam\Plugin\media\Source\Asset;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Tests\UnitTestCase;

/**
 * Checks asset filename resolving against edge case situations.
 *
 * @group acquia_dam
 */
class AssetTest extends UnitTestCase {

  /**
   * A source plugin instance of DAM assets.
   *
   * @var \Drupal\acquia_dam\Plugin\media\Source\Asset
   */
  protected Asset $sourcePlugin;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $configuration = [
      'source_field' => MediaSourceField::SOURCE_FIELD_NAME,
      'download_assets' => 1,
    ];
    $plugin_definition = [
      'id' => 'image',
      'label' => new TranslatableMarkup('Acquia DAM: Image'),
      'default_thumbnail_filename' => 'generic.png',
      'asset_search_key' => 'ft',
      'asset_search_value' => 'image',
      'description' => new TranslatableMarkup('Reference a media asset from the Acquia DAM.'),
      'allowed_field_types' => ['acquia_dam_asset'],
      'forms' => [],
      'thumbnail_uri_metadata_attribute' => 'thumbnail_uri',
      'thumbnail_width_metadata_attribute' => 'thumbnail_width',
      'thumbnail_height_metadata_attribute' => 'thumbnail_height',
      'default_name_metadata_attribute' => 'default_name',
      'deriver' => 'Drupal\acquia_dam\Plugin\media\Source\AssetDeriver',
      'class' => 'Drupal\acquia_dam\Plugin\media\Source\Asset',
      'provider' => 'acquia_dam',
    ];
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_field_manager = $this->createMock(EntityFieldManagerInterface::class);
    $field_type_manager = $this->createMock(FieldTypePluginManagerInterface::class);
    $config_factory = $this->createMock(ConfigFactoryInterface::class);

    $this->sourcePlugin = new Asset(
      $configuration,
      'acquia_dam_asset:image',
      $plugin_definition,
      $entity_type_manager,
      $entity_field_manager,
      $field_type_manager,
      $config_factory,
    );
  }

  /**
   * Checks asset filename resolving against both regular and edge cases.
   *
   * @covers \Drupal\acquia_dam\Plugin\media\Source\Asset::getValidFilename
   *
   * @dataProvider inputProvider
   */
  public function testGetValidFilename(array $asset_data, string $expected): void {
    $this->assertEquals($expected, $this->sourcePlugin->getValidFilename($asset_data));
  }

  /**
   * Array of various inputs might received through the Widen API.
   */
  public static function inputProvider() {
    $external_id = 'n4y5s4l0ae';

    yield 'Least problematic' => [
      'asset_data' => [
        'embeds' => [
          'templated' => [
            'url' => "https://embed.widencdn.net/img/laser/$external_id/{size}px@{scale}x/oneword.jpeg?q={quality}&x.template=y",
          ],
        ],
        'external_id' => $external_id,
        'file_properties' => [
          'format' => 'JPG',
        ],
        'filename' => 'Oneword.jpeg',
        'id' => '4e8913b6-296b-45a1-9332-c7a88a06540f',
      ],
      'result' => 'oneword.jpeg',
    ];

    yield 'White space char in file name' => [
      'asset_data' => [
        'embeds' => [
          'templated' => [
            'url' => "https://embed.widencdn.net/img/laser/$external_id/{size}px@{scale}x/two-words.tiff?q={quality}&x.template=y",
          ],
        ],
        'external_id' => $external_id,
        'file_properties' => [
          'format' => 'TIFF',
        ],
        'filename' => 'Two Words.tiff',
        'id' => 'f6bb93ee-ccc6-43f1-b455-51759142e8ce',
      ],
      'result' => 'two-words.tiff',
    ];

    yield 'White space char in file extension' => [
      'asset_data' => [
        'embeds' => [
          'templated' => [
            'url' => "https://embed.widencdn.net/img/laser/$external_id/{size}px@{scale}x/with-three-words.bmp?q={quality}&x.template=y",
          ],
        ],
        'external_id' => $external_id,
        'file_properties' => [
          'format' => 'BMP',
        ],
        'filename' => 'With Three Words.b p',
        'id' => 'c4d9d5dc-52ac-4655-a115-c97417f92e9e',
      ],
      'result' => 'with-three-words.bmp',
    ];

    yield 'Invalid asset label & invalid URL' => [
      'asset_data' => [
        'embeds' => [
          'templated' => [
            'url' => "https://embed.widencdn.net/img/laser/$external_id/{size}px@{scale}x/.?q={quality}&x.template=y",
          ],
        ],
        'external_id' => $external_id,
        'file_properties' => [
          'format' => 'JPG',
        ],
        'filename' => 'e',
        'id' => '9a2c438c-ed2d-4773-9b69-c117803fbc53',
      ],
      'result' => "$external_id.jpg",
    ];

    yield 'Only asset UUID & invalid URL' => [
      'asset_data' => [
        'embeds' => [
          'templated' => [
            'url' => "https://embed.widencdn.net/img/laser/$external_id/{size}px@{scale}x/.?q={quality}&x.template=y",
          ],
        ],
        'id' => '9a2c438c-ed2d-4773-9b69-c117803fbc53',
      ],
       // 'NULL' in real.
      'result' => '',
    ];
  }

  /**
   * Checks asset filename resolving against an exceptionally edge case input.
   *
   * @covers \Drupal\acquia_dam\Plugin\media\Source\Asset::getValidFilename
   */
  public function testGetInValidFilename(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Insufficient amount of asset data was received to deduce a valid filename.');
    $this->sourcePlugin->getValidFilename(['filename' => 'e']);
  }

}
