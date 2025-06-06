<?php

namespace Drupal\Tests\acquia_dam\StreamWrapper;

use Drupal\Core\Extension\ExtensionDiscovery;
use Drupal\image\Entity\ImageStyle;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use Drupal\Tests\acquia_dam\Kernel\AcquiaDamKernelTestBase;

/**
 * Focal point integration test.
 *
 * @requires module focal_point
 * @requires module crop
 *
 * @group acquia_dam
 */
class FocalPointIntegrationTest extends AcquiadamKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'crop',
    'focal_point',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('crop');
    $this->installConfig(['focal_point']);
    $this->grantDamDomain();
    $this->setDamSiteToken();
  }

  /**
   * Tests external url generation with focal point crop and scale effect.
   *
   * @param int $crop_width
   *   Crop width.
   * @param int $crop_height
   *   Crop height.
   * @param string $expected_string
   *   Expected query string.
   *
   * @testWith [200, 200, "?crop=yes&w=200&h=200"]
   *           [500, 500, "?crop=yes&w=500&h=500"]
   *           [100, 50, "?crop=yes&w=100&h=50"]
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testFocalPointCropAndScale(int $crop_width, int $crop_height, string $expected_string): void {
    $image_style = ImageStyle::create(['name' => 'test']);
    $image_style->save();
    $image_style_id = $image_style->id();

    $effect = [
      'id' => 'focal_point_scale_and_crop',
      'data' => [
        'width' => $crop_width,
        'height' => $crop_height,
        'crop_type' => 'focal_point',
      ],
    ];

    $image_style->addImageEffect($effect);
    $image_style->save();

    $derivative_uri = $image_style->buildUri('acquia-dam://56ff14de-02cd-41b5-9a73-c917eab19abf');
    self::assertEquals("acquia-dam://styles/$image_style_id/acquia-dam/56ff14de-02cd-41b5-9a73-c917eab19abf", $derivative_uri);

    // Build uri attach the extension given in the image style into the URI.
    self::assertEquals("acquia-dam://styles/$image_style_id/acquia-dam/56ff14de-02cd-41b5-9a73-c917eab19abf", $derivative_uri);
    $wrapper = $this->container->get('stream_wrapper_manager')->getViaUri($derivative_uri);
    self::assertEquals("https://laser.widen.net/content/kcnabdscl5/web/Wheel%20Illustration.ai$expected_string", $wrapper->getExternalUrl());

  }

  /**
   * Tests external url generation with focal point crop effect.
   *
   * @param int $x
   *   The "x" value of crop position.
   * @param int $y
   *   The "y" value of crop position.
   * @param string $expected_string
   *   Expected query string.
   * @param int $crop_width
   *   Crop width.
   * @param int $crop_height
   *   Crop height.
   *
   * @dataProvider focalPointDataProvider
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testFocalPointCropEffect(int $x, int $y, string $expected_string, int $crop_width, int $crop_height): void {
    $image_style = ImageStyle::create(['name' => 'test']);
    $image_style->save();
    $image_style_id = $image_style->id();

    $effect = [
      'id' => 'focal_point_crop',
      'data' => [
        'width' => $crop_width,
        'height' => $crop_height,
        'crop_type' => 'focal_point',
      ],
    ];

    $this->mockCropEntity($x, $y);

    $image_style->addImageEffect($effect);
    $image_style->save();

    $derivative_uri = $image_style->buildUri('acquia-dam://56ff14de-02cd-41b5-9a73-c917eab19abf');
    self::assertEquals("acquia-dam://styles/$image_style_id/acquia-dam/56ff14de-02cd-41b5-9a73-c917eab19abf", $derivative_uri);

    // Build uri attach the extension given in the image style into the URI.
    self::assertEquals("acquia-dam://styles/$image_style_id/acquia-dam/56ff14de-02cd-41b5-9a73-c917eab19abf", $derivative_uri);
    $wrapper = $this->container->get('stream_wrapper_manager')->getViaUri($derivative_uri);
    self::assertEquals("https://laser.widen.net/content/kcnabdscl5/web/Wheel%20Illustration.ai$expected_string", $wrapper->getExternalUrl());
  }

  /**
   * Tests that a revision inherits existing crop entities.
   */
  public function testCropCreatedForNewRevision() {
    $image_style = ImageStyle::create(['name' => 'test']);
    $image_style->save();
    $effect = [
      'id' => 'focal_point_crop',
      'data' => [
        'width' => 50,
        'height' => 50,
        'crop_type' => 'focal_point',
      ],
    ];
    $image_style->addImageEffect($effect);
    $image_style->save();

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

    $image_style_helper = $this->container->get('acquia_dam.image_style_support');
    $uri = $image_style_helper->buildUriForCrop(
      $media->get('acquia_dam_asset_id')->asset_id,
      $media->get('acquia_dam_asset_id')->version_id,
      'test',
    );
    $image_style_helper->saveCropEntity(
      80,
      60,
      $media,
      $image_style->id()
    );

    $crop_storage = $this->container->get('entity_type.manager')->getStorage('crop');
    self::assertEquals(
      1,
      $crop_storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('uri', $uri)
        ->count()
        ->execute()
    );

    $this->container->get('cron')->run();

    $media = $this->reloadEntity($media);
    $updated_version_id = $media->get('acquia_dam_asset_id')->version_id;
    self::assertEquals('9e4e810c-147b-4ac2-85a9-cf64f8fa61e0', $updated_version_id);

    $new_version_uri = $image_style_helper->buildUriForCrop(
      $media->get('acquia_dam_asset_id')->asset_id,
      $media->get('acquia_dam_asset_id')->version_id,
      'test',
    );
    self::assertEquals(
      1,
      $crop_storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('uri', $new_version_uri)
        ->count()
        ->execute()
    );
  }

  /**
   * Tests the media edit form is altered to include the Focal Point element.
   */
  public function testMediaEditForm(): void {
    $this->installModule('jquery_ui');
    // D9/D10 compatibility check for focal_point.
    $listing = new ExtensionDiscovery($this->root);
    $module_list = $listing->scan('module');
    if (isset($module_list['jquery_ui_draggable'])) {
      $this->installModule('jquery_ui_draggable');
    }

    // Requires date formats and media_library image style.
    $this->installConfig(['system', 'media_library']);

    $image_style = ImageStyle::create([
      'name' => 'test',
      'label' => 'Test image style',
    ]);
    $image_style->save();
    $effect = [
      'id' => 'focal_point_crop',
      'data' => [
        'width' => 50,
        'height' => 50,
        'crop_type' => 'focal_point',
      ],
    ];
    $image_style->addImageEffect($effect);
    $image_style->save();

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

    $image_style_helper = $this->container->get('acquia_dam.image_style_support');
    $image_style_helper->saveCropEntity(
      80,
      60,
      $media,
      $image_style->id()
    );

    $this->drupalSetUpCurrentUser([], [], TRUE);
    $request = $this->getMockedRequest($media->toUrl('edit-form')->toString(), 'GET');
    $response = $this->processRequest($request);
    self::assertEquals(200, $response->getStatusCode());
    $this->assertText('Focal point selection for image style: Test image style');

    $focal_point_elements = $this->cssSelect('[data-drupal-selector="edit-test-focal-point"]');
    self::assertCount(1, $focal_point_elements);
    $focal_point_element = $focal_point_elements[0];
    self::assertEquals('80,60', $focal_point_element->attributes()->value[0]);
  }

  /**
   * Data provider for testFocalPointIntegration().
   *
   * @phpstan-return \Generator<string, []>
   */
  public static function focalPointDataProvider(): \Generator {
    yield 'crop_middle' => [
      80,
      60,
      '?crop=yes&w=50&h=50&a=55,35',
      50,
      50,
    ];
    yield 'crop_middle_different_size' => [
      80,
      60,
      '?crop=yes&w=70&h=30&a=45,45',
      70,
      30,
    ];
    yield 'crop_top_left' => [
      0,
      0,
      '?crop=yes&w=50&h=50&a=0,0',
      50,
      50,
    ];
    yield 'crop_top_right' => [
      157,
      0,
      '?crop=yes&w=50&h=50&a=107,0',
      50,
      50,
    ];
    yield 'crop_bottom_left' => [
      0,
      120,
      '?crop=yes&w=50&h=50&a=0,70',
      50,
      50,
    ];
    yield 'crop_invalid' => [
      0,
      0,
      '',
      500,
      500,
    ];
  }

  /**
   * Creates a mock crop entity.
   *
   * @param int $x
   *   Crop height.
   * @param int $y
   *   Crop width.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function mockCropEntity(int $x, int $y): void {
    $values = [
      'type' => 'focal_point',
      'entity_type' => 'file',
      'uri' => 'acquia-dam://styles/test/acquia-dam/56ff14de-02cd-41b5-9a73-c917eab19abf',
    ];

    $crop = \Drupal::entityTypeManager()
      ->getStorage('crop')
      ->create($values);
    $crop->setPosition($x, $y);
    $crop->save();
  }

}
