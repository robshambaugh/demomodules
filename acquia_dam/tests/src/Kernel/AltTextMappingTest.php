<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_dam\Kernel;

use Drupal\acquia_dam\Entity\MediaSourceField;
use Drupal\Core\Url;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests mapping for alt text on images.
 *
 * @group acquia_dam
 */
final class AltTextMappingTest extends AcquiaDamKernelTestBase {

  /**
   * Tests alt text mapping.
   *
   * @param string $metadata_field
   *   Metadata field name.
   * @param string $expected_value
   *   Expected value.
   *
   * @dataProvider altTextTestProvider
   */
  public function testAltTextMapping(string $metadata_field, string $expected_value): void {
    $media_type = $this->createImageMediaType();
    $this->drupalSetUpCurrentUser([], [
      'access content',
      'administer media types',
    ]);
    $this->config('acquia_dam.settings')
      ->set('allowed_metadata', [$metadata_field => $metadata_field])
      ->save();

    $url = Url::fromRoute('entity.media_type.edit_form', [
      'media_type' => $media_type->id(),
    ]);
    $this->processRequest(Request::create($url->toString()));
    $response = $this->doFormSubmit($url->toString(), [
      'label' => $media_type->label(),
      'id' => $media_type->id(),
      'field_map' => [
        $metadata_field => 'acquia_dam_alt_text',
      ],
    ]);
    self::assertEquals(303, $response->getStatusCode());

    $sut = Media::create([
      'bundle' => $media_type->id(),
      MediaSourceField::SOURCE_FIELD_NAME => [
        'asset_id' => '56ff14de-02cd-41b5-9a73-c917eab19abf',
      ],
    ]);
    $sut->save();
    $sut = $this->reloadEntity($sut);
    self::assertInstanceOf(MediaInterface::class, $sut);
    self::assertFalse($sut->get('acquia_dam_alt_text')->isEmpty());
    self::assertEquals($expected_value, $sut->get('acquia_dam_alt_text')->value);
  }

  /**
   * Data provider for testAltTextMapping.
   *
   * @return \Generator
   *   Data for testAltTextMapping.
   */
  public static function altTextTestProvider(): \Generator {
    yield ['description', 'Illustration of a rim, Clorox'];
    yield [
      'longtext',
      'laskdjflaskjdflaksjdflkasjdflaksdjflaksjdlaskdjflaskjdflaksjdflkasjdflaksdjflaksjdflaksdjflaksdjflaksjdflaskdjflaskjdflaksjdflkasjdflaksdjflaksjdflaksdjflaksdjflaksjdflaskdjflaskjdflaksjdflkasjdflaksdjflaksjdflaksdjflaksdjflaksjdflaskdjflaskjdflaksjdflkasjdflaksdjflaksjdflaksdjflaksdjflaksjdflaskdjflaskjdflaksjdflkasjdflaksdjflaksjdflaksdjflaksdjflaksjdflaskdjflaskjdflaksjdflkasjdflaksdjflaksjdflaksdjflaksdjflaksjdflaskdjflaskjdflaksjdflkasjdflaksdjflaksjdflaksdjflaksdjflaksjdfflaksdjflaksdjflaksjdfasdf…',
    ];
  }

}
