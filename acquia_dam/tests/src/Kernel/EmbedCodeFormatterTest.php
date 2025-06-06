<?php

namespace Drupal\Tests\acquia_dam\Kernel;

use Drupal\acquia_dam\EmbedCodeFactory;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\media\MediaInterface;

/**
 * Tests embed code field formatter.
 *
 * @group acquia_dam
 */
class EmbedCodeFormatterTest extends AcquiaDamKernelTestBase {

  /**
   * Test media type.
   *
   * @var \Drupal\media\Entity\MediaType
   */
  protected MediaType $testMediaType;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'image',
    'file',
    'media',
    'media_library',
    'acquia_dam',
    'acquia_dam_test',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Create a test media type.
    $this->testMediaType = $this->createPdfMediaType();
  }

  /**
   * Tests image embed code.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testImageEmbedCode(): void {
    $media_type = $this->createImageMediaType();
    $media = Media::create([
      'bundle' => $media_type->id(),
      'name' => 'Wheel Illustration.ai',
      'acquia_dam_asset_id' => [
        'asset_id' => '56ff14de-02cd-41b5-9a73-c917eab19abf',
      ],
    ]);
    $media->save();
    assert($media instanceof MediaInterface);

    $build = EmbedCodeFactory::renderAsset('original', $media);
    self::assertEquals([
      '#theme' => 'image',
      '#uri' => 'acquia-dam://56ff14de-02cd-41b5-9a73-c917eab19abf/9e4e810c-147b-4ac2-85a9-cf64f8fa61e0',
      '#alt' => 'Wheel Illustration.ai',
      '#width' => 157,
      '#height' => 120,
    ], $build);
    $this->render($build);
    $this->assertStringContainsString(
      '<img src="https://laser.widen.net/content/9e4e810c-147b-4ac2-85a9-cf64f8fa61e0/web/Wheel%20Illustration.ai" width="157" height="120" alt="Wheel Illustration.ai" loading="lazy" />',
      $this->getRawContent()
    );
  }

  /**
   * Tests embed formatter.
   *
   * @dataProvider embedFormatterData
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testEmbedFormatter(string $embed_style, string $embed_key) {
    $embed_data = file_get_contents(__DIR__ . "/../../fixtures/0324b0b2-5293-4aa0-b0aa-c85b003395e2.json");
    $asset_data = json_decode($embed_data, TRUE);
    $media = Media::create([
      'bundle' => $this->testMediaType->id(),
      'name' => 'test',
      'acquia_dam_asset_id' => [
        'asset_id' => '0324b0b2-5293-4aa0-b0aa-c85b003395e2',
        'version_id' => '7b67948f-ee7e-405c-a0cd-344a24d8afb2',
        'external_id' => '8a1ouvfchk',
      ],
      'acquia_dam_embeds' => [
        'value' => $asset_data['embeds'],
      ],
    ]);
    $media->save();
    assert($media instanceof MediaInterface);

    $render_as_field = $this->renderAssetField($media, $embed_style);
    $renderer = \Drupal::service('renderer');

    $rendered_with_factory = EmbedCodeFactory::renderAsset(
      $embed_style,
      $media);
    $this->assertStringContainsString($renderer->renderRoot($rendered_with_factory), $render_as_field);
  }

  /**
   * Renders media field with view builder.
   *
   * @param \Drupal\media\Entity\Media $media
   *   Media entity instance.
   * @param string $embed_style
   *   Field formatter config value.
   *
   * @return callable|\Drupal\Component\Render\MarkupInterface|mixed
   *   Rendered field markup.
   *
   * @throws \Exception
   */
  protected function renderAssetField(Media $media, string $embed_style) {
    $view_builder = \Drupal::entityTypeManager()->getViewBuilder('media');
    $render_array = $view_builder->viewField(
      $media->get('acquia_dam_asset_id'),
      [
        'settings' => [
          'embed_style' => $embed_style,
        ],
      ]
    );

    return $this->render($render_array);
  }

  /**
   * Data provider for testEmbedFormatter.
   *
   * @return \string[][]
   *   Data sets for testEmbedFormatter.
   */
  public static function embedFormatterData(): array {
    return [
      [
        'original',
        'original',
      ],
      [
        'inline_view_download',
        'document_viewer_with_download',
      ],
      [
        'inline_view',
        'document_viewer',
      ],
      [
        'link_text_download',
        'document_viewer_with_download',
      ],
      [
        'link_text',
        'document_viewer',
      ],
      [
        'link_thumbnail_download',
        'document_viewer_with_download',
      ],
      [
        'link_thumbnail',
        'document_viewer',
      ],
    ];
  }

}
