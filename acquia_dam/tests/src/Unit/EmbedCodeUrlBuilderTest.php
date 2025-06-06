<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_dam\Unit;

use Drupal\acquia_dam\EmbedCodeUrlBuilder;
use Drupal\Tests\UnitTestCase;

/**
 * Unit test for the EmbedCodeUrlBuilder class.
 *
 * @group acquia_dam
 */
class EmbedCodeUrlBuilderTest extends UnitTestCase {

  /**
   * Test to check the working of mapImageEffects.
   *
   * @dataProvider effectProvider
   */
  public function testEmbedCodeUrlBuilder(array $effects, float $aspect_ratio, array $result) {
    self::assertEquals(
      $result,
      EmbedCodeUrlBuilder::mapImageEffects(
        $effects,
        [
          'width' => 100,
          'height' => 100,
          'aspect_ratio' => $aspect_ratio,
        ],
        'uri'
      )
    );
  }

  /**
   * Tests image scaling with a large source image.
   *
   * @param int $width
   *   The image width.
   * @param int $height
   *   The image height.
   * @param int $scale_width
   *   The image scale width.
   * @param int $scale_height
   *   The image scale height.
   * @param bool $upscale
   *   To upscale the image or not.
   * @param array $result
   *   The expected result.
   *
   * @dataProvider largeImageUpscaleProvider
   */
  public function testImageScaleWithLargeImage(int $width, int $height, int $scale_width, int $scale_height, bool $upscale, array $result): void {
    self::assertEquals(
      $result,
      EmbedCodeUrlBuilder::mapImageEffects(
        [
          [
            'uuid' => '97f71b20-1c2e-4633-a39b-d6535b242a10',
            'id' => 'image_scale',
            'weight' => 1,
            'data' => [
              'width' => $scale_width,
              'height' => $scale_height,
              'upscale' => $upscale,
            ],
          ],
        ],
        [
          'width' => $width,
          'height' => $height,
          'aspect_ratio' => $width / $height,
        ],
        'uri'
      )
    );
  }

  /**
   * Test data for image scaling.
   */
  public static function largeImageUpscaleProvider() {
    yield 'with upscale, horizontal' => [
      8256,
      5504,
      450,
      550,
      TRUE,
      [
        'format' => 'web',
        'w' => 450,
      ],
    ];
    yield 'without upscale, horizontal' => [
      8256,
      5504,
      450,
      550,
      FALSE,
      [
        'format' => 'web',
        'w' => 450,
      ],
    ];
    yield 'with upscale, vertical' => [
      5504,
      8256,
      450,
      550,
      TRUE,
      [
        'format' => 'web',
        'h' => 550,
      ],
    ];
    yield 'without upscale, vertical' => [
      5504,
      8256,
      450,
      550,
      FALSE,
      [
        'format' => 'web',
        'h' => 550,
      ],
    ];
  }

  /**
   * Array of effects and expected result.
   */
  public static function effectProvider() {
    yield 'Effect: multiple' => [
      [
        [
          'uuid' => 'a2233cff-5bac-4a5a-b357-08de46095830',
          'id' => 'image_convert',
          'weight' => 1,
          'data' =>
            [
              'extension' => 'png',
            ],
        ],
        [
          'uuid' => '97f71b20-1c2e-4633-a39b-d6535b242ad9',
          'id' => 'image_resize',
          'weight' => 2,
          'data' =>
            [
              'width' => 500,
              'height' => 300,
            ],
        ],
        [
          'uuid' => 'c62737dd-fe10-4618-a655-fac9fd716113',
          'id' => 'image_scale',
          'weight' => 3,
          'data' =>
            [
              'width' => 500,
              'height' => 300,
              'upscale' => TRUE,
            ],
        ],
        [
          'uuid' => '49949cfa-51a3-4f8b-ba4b-f3615b305a52',
          'id' => 'image_rotate',
          'weight' => 4,
          'data' =>
            [
              'degrees' => 30,
              'bgcolor' => '#FFFFFF',
              'random' => FALSE,
            ],
        ],
        [
          'uuid' => '731c0502-8954-42ff-a797-db24cca05392',
          'id' => 'image_crop',
          'weight' => 5,
          'data' =>
            [
              'width' => 200,
              'height' => 300,
              'anchor' => 'right-top',
            ],
        ],
      ],
      1,
      [
        'format' => 'png',
        'w' => 200,
        'h' => 300,
        'r' => 30,
        'crop' => 'yes',
        'k' => 'ne',
        'color' => 'FFFFFF',
      ],
    ];

    yield 'Effect: crop' => [
      [
        [
          'uuid' => '731c0502-8954-42ff-a797-db24cca05392',
          'id' => 'image_crop',
          'weight' => 1,
          'data' => [
            'width' => 200,
            'height' => 300,
            'anchor' => 'center-center',
          ],
        ],
      ],
      1,
      [
        'w' => 200,
        'h' => 300,
        'crop' => 'yes',
        'k' => 'c',
        'format' => 'web',
      ],
    ];

    yield 'Effect: resize' => [
      [
        [
          'uuid' => '97f71b20-1c2e-4633-a39b-d6535b242ad9',
          'id' => 'image_resize',
          'weight' => 1,
          'data' =>
            [
              'width' => 500,
              'height' => 300,
            ],
        ],
      ],
      1,
      [
        'w' => 500,
        'h' => 300,
        'format' => 'web',
      ],
    ];

    yield 'Effect: scale - no upscale but scale setting is smaller than original' => [
      [
        [
          'uuid' => '97f71b20-1c2e-4633-a39b-d6535b242ad9',
          'id' => 'image_scale',
          'weight' => 1,
          'data' =>
            [
              'width' => 50,
              'height' => 50,
              'upscale' => FALSE,
            ],
        ],
      ],
      1,
      [
        'format' => 'web',
        'w' => 50,
      ],
    ];

    yield 'Effect: scale - allow upscale, the scale setting is bigger than original' => [
      [
        [
          'uuid' => '97f71b20-1c2e-4633-a39b-d6535b242ad9',
          'id' => 'image_scale',
          'weight' => 1,
          'data' =>
            [
              'width' => 200,
              'height' => 200,
              'upscale' => TRUE,
            ],
        ],
      ],
      1,
      [
        'format' => 'web',
        'w' => 200,
      ],
    ];

    yield 'Effect: scale - no upscale, the scale setting is bigger than original' => [
      [
        [
          'uuid' => '97f71b20-1c2e-4633-a39b-d6535b242ad9',
          'id' => 'image_scale',
          'weight' => 1,
          'data' =>
            [
              'width' => 200,
              'height' => 200,
              'upscale' => FALSE,
            ],
        ],
      ],
      1,
      [
        'format' => 'web',
        'w' => 100,
      ],
    ];

    yield 'Effect: scale big' => [
      [
        [
          'uuid' => '97f71b20-1c2e-4633-a39b-d6535b242a10',
          'id' => 'image_scale',
          'weight' => 1,
          'data' => [
            'width' => 2600,
            'height' => 2600,
            'upscale' => FALSE,
          ],
        ],
      ],
      1,
      [
        'format' => 'web',
        'w' => 100,
      ],
    ];

    yield 'Effect: scale big upscale' => [
      [
        [
          'uuid' => '97f71b20-1c2e-4633-a39b-d6535b242a10',
          'id' => 'image_scale',
          'weight' => 1,
          'data' => [
            'width' => 2600,
            'height' => 2600,
            'upscale' => TRUE,
          ],
        ],
      ],
      1,
      [
        'format' => 'web',
        'w' => 2048,
      ],
    ];

    yield 'Effect: crop big size' => [
      [
        [
          'uuid' => '731c0502-8954-42ff-a797-db24cca05392',
          'id' => 'image_crop',
          'weight' => 1,
          'data' => [
            'width' => 3000,
            'height' => 3000,
            'anchor' => 'center-center',
          ],
        ],
      ],
      1,
      [
        'w' => 2048,
        'h' => 2048,
        'crop' => 'yes',
        'k' => 'c',
        'format' => 'web',
      ],
    ];

    yield 'Effect: scale - no upscale aspect ration under 1' => [
      [
        [
          'uuid' => '97f71b20-1c2e-4633-a39b-d6535b242ad9',
          'id' => 'image_scale',
          'weight' => 1,
          'data' =>
            [
              'width' => 50,
              'height' => 100,
              'upscale' => FALSE,
            ],
        ],
      ],
      0.5,
      [
        'format' => 'web',
        'h' => 100,
      ],
    ];

    yield 'Effect: scale - no upscale aspect ration over 1.' => [
      [
        [
          'uuid' => '97f71b20-1c2e-4633-a39b-d6535b242ad9',
          'id' => 'image_scale',
          'weight' => 1,
          'data' =>
            [
              'width' => 100,
              'height' => 100,
              'upscale' => FALSE,
            ],
        ],
      ],
      1.5,
      [
        'format' => 'web',
        'w' => 100,
      ],
    ];

  }

}
