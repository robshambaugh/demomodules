<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_dam\Kernel;

/**
 * Tests the media type resolver service.
 *
 * @group acquia_dam
 */
final class MediaTypeResolverTest extends AcquiaDamKernelTestBase {

  /**
   * Tests resolving media types from asset properties.
   */
  public function testResolve(): void {
    $sut = $this->container->get('acquia_dam.media_type_resolver');
    $audio_type = $this->createAudioMediaType();
    $document_type = $this->createDocumentMediaType();
    $generic_type = $this->createGenericMediaType();
    $image_type = $this->createImageMediaType();
    $pdf_type = $this->createPdfMediaType();
    $spinset_type = $this->createSpinsetMediaType();
    $video_type = $this->createVideoMediaType();

    self::assertNull($sut->resolve([]));
    self::assertNull($sut->resolve(['file_properties' => ['some_field' => 'pdf']]));

    $resolved_as_audio = $sut->resolve(['file_properties' => ['format_type' => 'audio']]);
    self::assertNotNull($resolved_as_audio);
    self::assertEquals($audio_type->id(), $resolved_as_audio->id());

    $resolved_as_document = $sut->resolve(['file_properties' => ['format_type' => 'office']]);
    self::assertNotNull($resolved_as_document);
    self::assertEquals($document_type->id(), $resolved_as_document->id());

    $resolved_as_generic = $sut->resolve(['file_properties' => ['format_type' => 'generic_binary']]);
    self::assertNotNull($resolved_as_generic);
    self::assertEquals($generic_type->id(), $resolved_as_generic->id());

    $resolved_as_image = $sut->resolve(['file_properties' => ['format_type' => 'image']]);
    self::assertNotNull($resolved_as_image);
    self::assertEquals($image_type->id(), $resolved_as_image->id());

    $resolved_as_pdf = $sut->resolve(['file_properties' => ['format_type' => 'pdf']]);
    self::assertNotNull($resolved_as_pdf);
    self::assertEquals($pdf_type->id(), $resolved_as_pdf->id());

    $resolved_as_spinset = $sut->resolve(['file_properties' => ['format' => 'SpinSet']]);
    self::assertNotNull($resolved_as_spinset);
    self::assertEquals($spinset_type->id(), $resolved_as_spinset->id());

    $resolved_as_video = $sut->resolve(['file_properties' => ['format_type' => 'video']]);
    self::assertNotNull($resolved_as_video);
    self::assertEquals($video_type->id(), $resolved_as_video->id());
  }

}
