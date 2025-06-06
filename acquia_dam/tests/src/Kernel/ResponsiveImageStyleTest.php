<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_dam\Kernel;

use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;

/**
 * Tests Acquia DAM responsive image field formatter.
 *
 * @group acquia_dam
 */
final class ResponsiveImageStyleTest extends AcquiaDamKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'breakpoint',
    'responsive_image',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['acquia_dam_test']);
    $this->config('image.settings')
      ->set('suppress_itok_output', TRUE)
      ->save();
  }

  /**
   * Tests Responsive Image Styles for 'acquia_dam_asset' fields.
   *
   * @param bool $create_revision
   *   If TRUE, create a new revision of the media entity.
   *
   * @dataProvider booleanProvider
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testMediaResponsiveImageStyles(bool $create_revision): void {
    $this->drupalSetUpCurrentUser();
    $this->setDamSiteToken();

    $asset_id = '56ff14de-02cd-41b5-9a73-c917eab19abf';
    $version_id = '9e4e810c-147b-4ac2-85a9-cf64f8fa61e0';
    $external_id = 'kcnabdscl5';
    $uri = "acquia-dam://$asset_id";

    if ($create_revision) {
      $uri .= "/$version_id";
    }

    // Create an image media.
    $media_type = $this->createImageMediaType();
    /** @var \Drupal\media\Entity\Media $media */
    $media = Media::create([
      'bundle' => $media_type->id(),
      'name' => 'Wheel Illustration.ai',
      'acquia_dam_asset_id' => [
        'asset_id' => $asset_id,
        'version_id' => $version_id,
        'external_id' => $external_id,
      ],
      'acquia_dam_alt_text' => "alt text for $external_id!",
    ]);
    $media->save();
    assert($media instanceof MediaInterface);

    // Create a new revision of the media to verify URI output for versioning.
    if ($create_revision) {
      // We 'clone' so that the existing media entity does not think it is
      // the latest revision.
      $versioned_media = clone $media;
      $versioned_media->setNewRevision();
      $versioned_media->save();
    }

    if (!$create_revision) {
      // @todo Undocumented obscure hack but for why?
      $version_id = $external_id;
    }

    // Check the updated image output for the 'bar' formatter.
    $field = $this->entityTypeManager
      ->getViewBuilder('media')
      ->viewField($media->get('acquia_dam_asset_id'), [
        'label' => 'hidden',
        'type' => 'acquia_dam_responsive_image',
        'settings' => [
          'responsive_image_style' => 'acquia_dam_responsive_image_style',
        ],
      ]);
    $this->assertEquals([
      '#theme' => 'responsive_image',
      '#responsive_image_style_id' => 'acquia_dam_responsive_image_style',
      '#uri' => $uri,
      '#cache' => [
        'tags' => [
          'config:responsive_image.styles.acquia_dam_responsive_image_style',
          'config:image.style.max_325x325',
          'config:image.style.max_2600x2600',
          'config:image.style.max_1300x1300',
          'config:image.style.max_650x650',
        ],
      ],
      '#width' => 157,
      '#height' => 120,
      '#attributes' => [
        'alt' => "alt text for $external_id!",
      ],
    ], $field[0]);

    // Render the field to assert the <picture> element exists.
    $this->render($field);
    $this->assertResponsiveImageOutput(
      "https://laser.widen.net/content/$version_id/web/Wheel%20Illustration.ai?w=325",
      [
        "https://laser.widen.net/content/$version_id/web/Wheel%20Illustration.ai?w=2048 1x",
        "https://laser.widen.net/content/$version_id/web/Wheel%20Illustration.ai?w=1300 1x",
        "https://laser.widen.net/content/$version_id/web/Wheel%20Illustration.ai?w=650 1x",
        "https://laser.widen.net/content/$version_id/web/Wheel%20Illustration.ai?w=325 1x",
      ],
      "alt text for $external_id!"
    );
  }

  /**
   * A boolean data provider.
   *
   * @return \Generator
   *   The test data.
   */
  public static function booleanProvider() {
    yield [FALSE];
    yield [TRUE];
  }

  /**
   * Assert the rendered output of a responsive image.
   *
   * @param string $expected_fallback
   *   The expected fallback URL.
   * @param array $expected_srcsets
   *   The expected srcsets.
   * @param string $expected_alt_text
   *   The expected alt text.
   */
  private function assertResponsiveImageOutput(string $expected_fallback, array $expected_srcsets, string $expected_alt_text): void {
    self::assertCount(1, $this->cssSelect('picture'));
    $picture_img = $this->cssSelect('picture img');
    self::assertEquals($expected_alt_text, $picture_img[0]->attributes()->alt[0] ?? '');
    // Verify fallback formatter is transformed by `max_325x325`.
    self::assertEquals($expected_fallback, $picture_img[0]->attributes()->src[0] ?? '');
    self::assertCount(1, $picture_img);
    $picture_source = $this->cssSelect('picture source');
    self::assertCount(4, $picture_source);
    $srcsets = array_map(static function (\SimpleXMLElement $element) {
      return (string) $element[0]->attributes()->srcset[0];
    }, $picture_source);
    self::assertEquals($expected_srcsets, $srcsets);
    foreach ($picture_source as $item) {
      self::assertNull($item[0]->attributes()->type);
    }
  }

}
