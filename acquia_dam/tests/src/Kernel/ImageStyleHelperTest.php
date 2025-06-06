<?php

namespace Drupal\Tests\acquia_dam\Kernel;

use Drupal\image\Entity\ImageStyle;

/**
 * Tests image style helper functions.
 *
 * @group acquia_dam
 */
class ImageStyleHelperTest extends AcquiaDamKernelTestBase {

  /**
   * Image style helper service.
   *
   * @var \Drupal\acquia_dam\ImageStyleHelper
   */
  protected $imageStyleHelper;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'crop',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('crop');

    $this->imageStyleHelper = $this->container->get('acquia_dam.image_style_support');
  }

  /**
   * Tests image width and height recalculation for large images.
   *
   * @param float $width
   *   Original width.
   * @param float $height
   *   Original height.
   * @param array $expected_values
   *   Calculated width and height.
   *
   * @testWith [200, 200, [200, 200]]
   *           [1000, 1000, [1000, 1000]]
   *           [1024, 4092, [512, 2048]]
   *           [4092, 4092, [2048, 2048]]
   *           [6000, 4092, [2048, 1396]]
   */
  public function testLargeImageHandling(float $width, float $height, array $expected_values) {
    $this->assertEquals($expected_values, $this->imageStyleHelper->handleLargeImages($width, $height));
  }

  /**
   * Test custom uri builder.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testBuildUriForCrop() {
    $image_style = ImageStyle::create(['name' => 'test']);
    $image_style->save();
    $this->assertEquals(
      'acquia-dam://styles/test/acquia-dam/asset/version.png',
      $this->imageStyleHelper->buildUriForCrop('asset', 'version', 'test')
    );
  }

  /**
   * Test crop position calculation for DAM.
   *
   * @param int $x
   *   Relative X coordinate (%).
   * @param float $width
   *   Original image width.
   * @param int $y
   *   Relative Y coordinate (%).
   * @param float $height
   *   Original image height.
   * @param array $expected_position
   *   Expected calculated position on crop.
   *
   * @dataProvider dataProviderCropUpdate
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testCropUpdate(int $x, float $width, int $y, float $height, array $expected_position) {
    $crop_values = [
      'type' => 'focal_point',
      'entity_type' => 'file',
      'uri' => 'valid_uri',
    ];

    /** @var \Drupal\crop\CropStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('crop');
    /** @var \Drupal\crop\CropInterface $crop */
    $crop = $storage->create($crop_values);

    $crop = $this->imageStyleHelper->relativeToAbsolute($x, $y, $width, $height, $crop);
    $this->assertEquals($expected_position, $crop->position());
  }

  /**
   * Data provider for test.
   *
   * @return array
   *   Test data and expected result for test.
   */
  public static function dataProviderCropUpdate(): array {
    return [
      'simple_image_top_left_corner' => [
        1,
        1500,
        1,
        1500,
        ['x' => 15, 'y' => 15],
      ],
      'simple_image_inner_position' => [
        20,
        1500,
        30,
        1500,
        ['x' => 300, 'y' => 450],
      ],
      'large_width_inner_position' => [
        20,
        4000,
        30,
        1500,
        ['x' => 410, 'y' => 230],
      ],
      'large_height_inner_position' => [
        80,
        2000,
        30,
        4092,
        ['x' => 800, 'y' => 614],
      ],
      'large_widht_height_center_position' => [
        50,
        4092,
        50,
        4092,
        ['x' => 1024, 'y' => 1024],
      ],
      'large_width_height_right_left_position' => [
        99,
        4092,
        99,
        4092,
        ['x' => 2028, 'y' => 2028],
      ],
    ];
  }

  /**
   * Tests absolute position value transform to relative.
   *
   * @param int $x
   *   Absolute 'x' position.
   * @param int $y
   *   Absolute 'y' position.
   * @param float $width
   *   Image width.
   * @param float $height
   *   Image height.
   * @param array $expected_position
   *   Expected absolute position values.
   *
   * @dataProvider dataProviderAbsoluteToRelative
   */
  public function testAbsoluteToRelative(int $x, int $y, float $width, float $height, array $expected_position) {
    $relative_position = $this->imageStyleHelper->absoluteToRelative($x, $y, $width, $height);
    $this->assertEquals($expected_position, $relative_position);
  }

  /**
   * Data provider for test.
   *
   * @return array
   *   Test data and expected result for test.
   */
  public static function dataProviderAbsoluteToRelative(): array {
    return [
      [
        15,
        15,
        1500,
        1500,
        ['x' => 1, 'y' => 1],
      ],
      [
        1024,
        1024,
        4092,
        4092,
        ['x' => 50, 'y' => 50],
      ],
      [
        300,
        450,
        1500,
        1500,
        ['x' => 20, 'y' => 30],
      ],
    ];
  }

}
