<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_dam\StreamWrapper;

use Drupal\acquia_dam\StreamWrapper\AcquiaDamStreamWrapper;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\Tests\acquia_dam\Kernel\AcquiaDamKernelTestBase;

/**
 * Tests the acquia-dam stream wrapper.
 *
 * @group acquia_dam
 */
final class AcquiaDamStreamWrapperTest extends AcquiaDamKernelTestBase {

  /**
   * Tests that the stream wrapper is registered with the manager.
   */
  public function testSchemeManagerIntegration(): void {
    $manager = $this->container->get('stream_wrapper_manager');
    self::assertEquals(AcquiaDamStreamWrapper::class, $manager->getClass('acquia-dam'));
    self::assertInstanceOf(AcquiaDamStreamWrapper::class, $manager->getViaScheme('acquia-dam'));
    self::assertInstanceOf(AcquiaDamStreamWrapper::class, $manager->getViaUri('acquia-dam://0324b0b2-5293-4aa0-b0aa-c85b003395e2'));
    self::assertEquals('0324b0b2-5293-4aa0-b0aa-c85b003395e2', $manager::getTarget('acquia-dam://0324b0b2-5293-4aa0-b0aa-c85b003395e2'));

    $names = array_map('strval', $manager->getNames(StreamWrapperInterface::WRITE));
    self::assertContains('Acquia DAM', $names);
    $descriptions = array_map('strval', $manager->getDescriptions(StreamWrapperInterface::WRITE));
    self::assertContains('Read-only stream wrapper for Acquia DAM assets.', $descriptions);
  }

  /**
   * Tests the URI methods.
   */
  public function testUriMethods(): void {
    $manager = $this->container->get('stream_wrapper_manager');
    $sut = $manager->getViaUri('acquia-dam://0324b0b2-5293-4aa0-b0aa-c85b003395e2/');
    self::assertEquals('acquia-dam://0324b0b2-5293-4aa0-b0aa-c85b003395e2/', $sut->getUri());
    self::assertEquals('acquia-dam://0324b0b2-5293-4aa0-b0aa-c85b003395e2', $sut->realpath());
  }

  /**
   * Tests that a URI is transformed into external URL.
   *
   * @dataProvider provideUris
   */
  public function testFileUrlGeneratorIntegration(string $uri, string $expected_url): void {
    $file_url_generator = $this->container->get('file_url_generator');
    assert($file_url_generator instanceof FileUrlGeneratorInterface);
    self::assertEquals($expected_url, $file_url_generator->generateString($uri));
  }

  /**
   * Tests `stat` on stream wrapper.
   */
  public function testStat(): void {
    $result = stat('acquia-dam://56ff14de-02cd-41b5-9a73-c917eab19abf');
    self::assertEquals([
      0 => 0,
      1 => 0,
      2 => 0100000 | 0444,
      3 => 0,
      4 => 0,
      5 => 0,
      6 => 0,
      7 => 312320,
      8 => 1632436527,
      9 => 1632436527,
      10 => 1632436527,
      11 => 0,
      12 => 0,
      'dev' => 0,
      'ino' => 0,
      'mode' => 0100000 | 0444,
      'nlink' => 0,
      'uid' => 0,
      'gid' => 0,
      'rdev' => 0,
      'size' => 312320,
      'atime' => 1632436527,
      'mtime' => 1632436527,
      'ctime' => 1632436527,
      'blksize' => 0,
      'blocks' => 0,
    ], $result);
  }

  /**
   * Tests image factory integration support.
   */
  public function testImageFactoryIntegration(): void {
    $image_factory = $this->container->get('image.factory');
    $image = $image_factory->get('acquia-dam://56ff14de-02cd-41b5-9a73-c917eab19abf');
    self::assertTrue($image->isValid());
    self::assertEquals('image/png', $image->getMimeType());
  }

  /**
   * Tests the `image_scale_and_crop` image effect.
   *
   * @param int $width
   *   The width.
   * @param int $height
   *   The height.
   * @param string $anchor
   *   The anchor (see ImageEffect\CropImageEffect::buildConfigurationForm.)
   * @param array $expected_parameters
   *   The expected DAM URL customization parameters.
   *
   * @dataProvider scaleAndCropEffectValues
   */
  public function testImageScaleAndCrop(int $width, int $height, string $anchor, array $expected_parameters): void {
    $image_style = ImageStyle::create(['name' => $this->randomMachineName()]);
    $image_style->addImageEffect([
      'id' => 'image_scale_and_crop',
      'data' => [
        'anchor' => $anchor,
        'width' => $width,
        'height' => $height,
      ],
    ]);
    $image_style->save();
    $image_style_id = $image_style->id();
    $image_uri = 'acquia-dam://56ff14de-02cd-41b5-9a73-c917eab19abf';
    $derivative_uri = $image_style->buildUri($image_uri);

    self::assertEquals("acquia-dam://styles/$image_style_id/acquia-dam/56ff14de-02cd-41b5-9a73-c917eab19abf", $derivative_uri);
    $wrapper = $this->container->get('stream_wrapper_manager')->getViaUri($derivative_uri);
    $url_query = parse_url($wrapper->getExternalUrl(), PHP_URL_QUERY);
    self::assertIsString($url_query);
    $parsed_url_query = [];
    parse_str($url_query, $parsed_url_query);
    self::assertEquals(
      $expected_parameters,
      $parsed_url_query
    );
  }

  /**
   * Test `image_scale_and_crop` effect configurations and expected results.
   *
   * @return \Generator
   *   The test data.
   */
  public static function scaleAndCropEffectValues(): \Generator {
    yield 'center 300x300' => [
      300,
      300,
      '',
      [
        'crop' => 'yes',
        'k' => 'c',
        'w' => '300',
        'h' => '300',
      ],
    ];
    yield 'right-bottom 100x400' => [
      100,
      400,
      '',
      [
        'crop' => 'yes',
        'k' => 'c',
        'w' => '100',
        'h' => '400',
      ],
    ];
  }

  /**
   * Tests image style integration support.
   */
  public function testImageStyleIntegration(): void {
    $file_url_generator = $this->container->get('file_url_generator');
    assert($file_url_generator instanceof FileUrlGeneratorInterface);

    $image_style = ImageStyle::create(['name' => 'test']);
    $image_style->save();
    $image_style_id = $image_style->id();

    $image_uri = 'acquia-dam://56ff14de-02cd-41b5-9a73-c917eab19abf';
    $derivative_uri = $image_style->buildUri($image_uri);
    self::assertEquals("acquia-dam://styles/$image_style_id/acquia-dam/56ff14de-02cd-41b5-9a73-c917eab19abf", $derivative_uri);

    $image_style->createDerivative($image_uri, $derivative_uri);
    $image_factory = $this->container->get('image.factory');
    $image = $image_factory->get($derivative_uri);
    self::assertTrue($image->isValid());

    $image_style = ImageStyle::create(['name' => 'style_2']);
    $image_style->addImageEffect([
      'id' => 'image_convert',
      'data' => [
        'extension' => 'png',
      ],
    ]);
    $image_style->save();

    $image_style_id = $image_style->id();
    $image_uri = 'acquia-dam://56ff14de-02cd-41b5-9a73-c917eab19abf';
    $derivative_uri = $image_style->buildUri($image_uri);
    // Builduri attach the extension given in the image style into the URI.
    self::assertEquals("acquia-dam://styles/$image_style_id/acquia-dam/56ff14de-02cd-41b5-9a73-c917eab19abf.png", $derivative_uri);
    $wrapper = $this->container->get('stream_wrapper_manager')->getViaUri($derivative_uri);
    self::assertEquals("https://laser.widen.net/content/kcnabdscl5/png/Wheel%20Illustration.ai", $wrapper->getExternalUrl());

    $image_style = ImageStyle::create(['name' => 'style_3']);
    $effects = [
      [
        'id' => 'image_rotate',
        'data' => [
          'degrees' => '90',
          'bgcolor' => '',
        ],
      ],
      [
        'id' => 'image_resize',
        'data' => [
          'width' => 300,
          'height' => 600,

        ],
      ],
      [
        'id' => 'image_crop',
        'data' => [
          'anchor' => 'top',
          'width' => 300,
          'height' => 600,
        ],
      ],
    ];
    foreach ($effects as $effect) {
      $image_style->addImageEffect($effect);
    }
    $image_style->save();

    $image_style_id = $image_style->id();
    $image_uri = 'acquia-dam://56ff14de-02cd-41b5-9a73-c917eab19abf';
    $derivative_uri = $image_style->buildUri($image_uri);
    // Builduri attach the extension given in the image style into the URI.
    self::assertEquals("acquia-dam://styles/$image_style_id/acquia-dam/56ff14de-02cd-41b5-9a73-c917eab19abf", $derivative_uri);
    $wrapper = $this->container->get('stream_wrapper_manager')->getViaUri($derivative_uri);
    self::assertEquals("https://laser.widen.net/content/kcnabdscl5/web/Wheel%20Illustration.ai?r=90&w=300&h=600&crop=yes&k=n", $wrapper->getExternalUrl());
  }

  /**
   * Provides URIs for testing.
   *
   * @phpstan-return \Generator<string, string[]>
   */
  public static function provideUris(): \Generator {
    yield 'image' => [
      'acquia-dam://56ff14de-02cd-41b5-9a73-c917eab19abf',
      'https://laser.widen.net/content/kcnabdscl5/web/Wheel%20Illustration.ai',
    ];
    yield 'pdf' => [
      'acquia-dam://0324b0b2-5293-4aa0-b0aa-c85b003395e2',
      'https://laser.widen.net/content/8a1ouvfchk/original/Explorer%20owner\'s%20manual.pdf',
    ];
    yield 'invalid' => [
      'acquia-dam://c2bbed58-427f-43f7-91d8-c380307dac67',
      '',
    ];
    yield 'versioned embed' => [
      'acquia-dam://efb03f75-3c42-497b-baa9-5ec79d1f56af/04984534-8682-4fbf-95ae-f3c7b46af9ee',
      'https://laser.widen.net/content/04984534-8682-4fbf-95ae-f3c7b46af9ee/original/SD-Social%20Promo.mp4',
    ];
  }

}
