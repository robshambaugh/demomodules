<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_dam\Kernel;

use Drupal\acquia_dam\Entity\ComputedEmbedCodesField;
use Drupal\acquia_dam\Entity\MediaSourceField;
use Drupal\Component\Serialization\Json;
use Drupal\image\Entity\ImageStyle;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests JSON API integration.
 *
 * @group acquia_dam
 */
final class JsonapiIntegrationTest extends AcquiaDamKernelTestBase {

  use MediaTypeCreationTrait {
    createMediaType as drupalCreateMediaType;
  }

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'serialization',
    'jsonapi',
    'image',
  ];

  /**
   * Tests that our resource types have been modified.
   */
  public function testResourceTypeBuild(): void {
    $media_type = $this->createImageMediaType();
    $repository = $this->container->get('jsonapi.resource_type.repository');
    $resource_type = $repository->get('media', $media_type->id());
    self::assertEquals(
      'acquia_dam_embed_codes',
      $resource_type->getFieldByInternalName(ComputedEmbedCodesField::FIELD_NAME)->getPublicName()
    );
  }

  /**
   * Tests the JSON:API output.
   */
  public function testJsonApiOutput(): void {
    $media_type = $this->createPdfMediaType();
    $media = Media::create([
      'bundle' => $media_type->id(),
      MediaSourceField::SOURCE_FIELD_NAME => [
        'asset_id' => '4f656c07-6a08-47b3-9403-16082d2fcda2',
      ],
    ]);
    $media->save();

    $document = $this->doJsonApiRequest($media);
    self::assertEquals([
      'document_html5_viewer' => [
        'href' => 'https://laser.widen.net/view/pdf/8q3z9gm2ec/test-course-catalog-v2.pdf?u=xgdchd',
      ],
      'document_thumbnail' => [
        'href' => 'https://laser.widen.net/content/8q3z9gm2ec/jpeg/test-course-catalog-v2.jpg?u=xgdchd',
      ],
      'document_viewer' => [
        'href' => 'https://laser.widen.net/content/8q3z9gm2ec/pdf/test-course-catalog-v2.pdf?u=xgdchd',
      ],
      'document_viewer_with_download' => [
        'href' => 'https://laser.widen.net/view/pdf/8q3z9gm2ec/test-course-catalog-v2.pdf?t.download=true&u=xgdchd',
      ],
      'original' => [
        'href' => 'https://laser.widen.net/content/8q3z9gm2ec/original/test-course-catalog-v2.pdf?u=xgdchd&download=true',
      ],
    ], $document['data']['attributes']['acquia_dam_embed_codes']);
  }

  /**
   * Tests the JSON:API output.
   */
  public function testJsonApiOutputForImages(): void {
    $media_type = $this->createImageMediaType();
    $media = Media::create([
      'bundle' => $media_type->id(),
      MediaSourceField::SOURCE_FIELD_NAME => [
        'asset_id' => '56ff14de-02cd-41b5-9a73-c917eab19abf',
      ],
    ]);
    $media->save();

    $image_style_definitions = [
      'style_1' => [
        [
          'id' => 'image_convert',
          'data' => [
            'extension' => 'png',
          ],
        ],
      ],
      'style_2' => [
        [
          'id' => 'image_crop',
          'data' => [
            'anchor' => 'top',
            'width' => 300,
            'height' => 600,
          ],
        ],
      ],
    ];
    foreach ($image_style_definitions as $image_style_name => $image_style_definition) {
      $image_style = ImageStyle::create(['name' => $image_style_name]);
      foreach ($image_style_definition as $effects) {
        $image_style->addImageEffect($effects);
      }
      $image_style->save();
    }

    $document = $this->doJsonApiRequest($media);
    self::assertEquals([
      'style_1' => [
        'href' => 'https://laser.widen.net/content/9e4e810c-147b-4ac2-85a9-cf64f8fa61e0/png/Wheel%20Illustration.ai',
      ],
      'style_2' => [
        'href' => 'https://laser.widen.net/content/9e4e810c-147b-4ac2-85a9-cf64f8fa61e0/web/Wheel%20Illustration.ai?crop=yes&k=n&w=300&h=600',
      ],
      'original' => [
        'href' => 'https://laser.widen.net/content/9e4e810c-147b-4ac2-85a9-cf64f8fa61e0/web/Wheel%20Illustration.ai',
      ],
    ], $document['data']['attributes']['acquia_dam_embed_codes']);
  }

  /**
   * Perform a JSON:API request and return the JSON:API document.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media.
   *
   * @return array
   *   The JSON:API document.
   */
  private function doJsonApiRequest(MediaInterface $media): array {
    if (version_compare(\Drupal::VERSION, '10.1', '<')) {
      $user = $this->createUser([], ['view media']);
    }
    else {
      $user = $this->createUser(['view media']);
    }
    $this->container->get('current_user')->setAccount($user);

    $request = Request::create("/jsonapi/media/{$media->bundle()}/{$media->uuid()}");
    $response = $this->container->get('http_kernel')->handle($request);
    self::assertEquals(200, $response->getStatusCode());
    $document = Json::decode($response->getContent());
    self::assertArrayHasKey('data', $document);
    self::assertArrayHasKey('attributes', $document['data']);
    self::assertArrayHasKey(ComputedEmbedCodesField::FIELD_NAME, $document['data']['attributes']);
    return $document;
  }

}
