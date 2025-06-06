<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_dam\Kernel;

use Drupal\media\Entity\MediaType;
use Drupal\media_library\MediaLibraryState;

/**
 * @coversDefaultClass \Drupal\acquia_dam\AssetLibraryBuilder
 * @group acquia_dam
 */
final class AssetLibraryBuilderTest extends AcquiaDamKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->container->get('user.data')->set('acquia_dam', 0, 'seen_prompt', TRUE);
  }

  /**
   * Tests the source menu when there is one allowed media type.
   *
   * @covers ::addSourceMenu
   */
  public function testWhenOneAllowedMediaType(): void {
    $dam_image_type = $this->createImageMediaType();
    $sut = $this->container->get('acquia_dam.asset_library_builder');
    $state = MediaLibraryState::create(
      'editor',
      [$dam_image_type->id()],
      $dam_image_type->id(),
      1,
      []
    );
    $state->set('source', 'acquia_dam');
    $state->set('hash', $state->getHash());
    $build = $sut->buildUi($state);
    self::assertEquals([], $build['menu']);
  }

  /**
   * Tests the source menu when two allowed types, different sources.
   *
   * @covers ::addSourceMenu
   */
  public function testSourceMenu(): void {
    $dam_image_type = $this->createImageMediaType();
    $core_image_type = MediaType::create([
      'id' => 'core_image',
      'label' => 'Core Image',
      'source' => 'image',
    ]);
    $core_image_type->save();
    $sut = $this->container->get('acquia_dam.asset_library_builder');
    $state = MediaLibraryState::create(
      'editor',
      [$dam_image_type->id(), $core_image_type->id()],
      $dam_image_type->id(),
      1,
    );
    $state->set('source', 'acquia_dam');
    $state->set('hash', $state->getHash());
    $build = $sut->buildUi($state);
    self::assertNotEquals([], $build['menu']);
    self::assertArrayHasKey('field', $build['menu']);
    self::assertArrayNotHasKey('link', $build['menu'], 'only one media type, no links');
  }

}
