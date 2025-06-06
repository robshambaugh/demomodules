<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_dam\Kernel;

use Drupal\acquia_dam\Plugin\media\acquia_dam\MediaSourceTypeBase;
use Drupal\acquia_dam\Plugin\media\acquia_dam\MediaSourceTypeInterface;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\media\Entity\MediaType;
use Drupal\media\MediaTypeInterface;

/**
 * Tests the Asset media source plugin.
 *
 * @group acquia_dam
 */
class AssetTest extends AcquiaDamKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['media', 'media_library', 'acquia_dam']);

    $this->config('acquia_dam.settings')
      ->set('allowed_image_styles', ['large'])
      ->save();
  }

  /**
   * Ensure default media types have the correct field configuration initially
   * and updates the view display correctly when download_sync is enabled.
   */
  public function testDefaultFieldConfiguration(): void {
    // Get all the default media types
    $acquia_dam_media_types = $this->entityTypeManager->getStorage('media_type')->loadMultiple();
    foreach ($acquia_dam_media_types as $media_type_id => $media_type) {
      // Validate that the active field matches configuration
      $expected_field = $media_type->getSource()->getActiveFieldName();
      $entity_view_display = $this->entityTypeManager->getStorage('entity_view_display')->load('media.' . $media_type_id . '.default');
      $this->assertArrayHasKey($expected_field, $entity_view_display->get('content'));
      $this->assertArrayNotHasKey($expected_field, $entity_view_display->get('hidden'));

      // Test that the toggle function works with new media types
      $this->testDownloadSyncToggle($media_type_id, $media_type);
    }
  }

  /**
   * Test a newly created media type matches the expected configuration.
   *
   * @dataProvider dataProviderDamMediaSourceFields
   * @param string $source_plugin_id
   *   The source plugin ID.
   * @param array $source_config
   *   The source configuration.
   */
  public function testNewMediaTypeDefaultFieldConfiguration(string $source_plugin_id, array $source_config, array $expected_config): void {
    // Create the media type with the specified source configuration.
    $media_type = $this->createMediaType($source_plugin_id, $source_config);

    // Test that the default field matches what exists within the view display.
    $expected_field = $media_type->getSource()->getActiveFieldName();
    $entity_view_display = $this->entityTypeManager->getStorage('entity_view_display')->load('media.' . $media_type->id() . '.default');
    $actual_display = $entity_view_display->get('content');
    // Remove weight as its dynamically set and we don't care about it.
    unset($actual_display[$expected_field]['weight']);
    $this->assertArrayHasKey($expected_field, $actual_display);
    $this->assertArrayNotHasKey($expected_field, $entity_view_display->get('hidden'));
    $this->assertEquals($expected_config, $actual_display[$expected_field]);

    // Test that the toggle function works with new media types
    $this->testDownloadSyncToggle($media_type->id(), $media_type);
  }

  /**
   * Test that the image style is swapped correctly.
   *
   * @dataProvider dataProviderImageTypeFields
   * @param string $source_plugin_id
   *   The source plugin ID.
   * @param array $source_configuration
   *   The source configuration.
   * @param array $existing_config
   *   The existing configuration.
   * @param array $expected_config
   *   The expected configuration.
   */
  public function testImageStyleSwap(array $existing_config, array $expected_config): void {
    $download_assets = $existing_config['type'] !== 'acquia_dam_embed_code';
    $media_type = $this->createMediaType('acquia_dam_asset:image', ['download_assets' => $download_assets]);

    // Edit the entity display to use our image.
    // Add the source field to the form display for the media type.
    $source = $media_type->getSource();

    // Add the source field to the content display for the media type.
    $content_display = \Drupal::service('entity_display.repository')->getViewDisplay('media', $media_type->id(), 'default');
    $content_display->setComponent($source->getActiveFieldName(), $existing_config);
    $content_display->save();

    // Test that the toggle function works with new media types
    $this->testDownloadSyncToggle($media_type->id(), $media_type);

    // Test that the default field matches what exists within the view display.
    $expected_field = $media_type->getSource()->getActiveFieldName();
    $entity_view_display = $this->entityTypeManager->getStorage('entity_view_display')->load('media.' . $media_type->id() . '.default');
    $actual_display = $entity_view_display->get('content');
    unset($actual_display[$expected_field]['weight']);
    $this->assertEquals($expected_config, $actual_display[$expected_field]);
  }

  /**
   * Create a media type for a source plugin.
   *
   * @param string $source_plugin_id
   *   The media source plugin ID.
   * @param mixed[] $values
   *   (optional) Additional values for the media type entity:
   *   - id: The ID of the media type. If none is provided, a random value will
   *     be used.
   *   - label: The human-readable label of the media type. If none is provided,
   *     a random value will be used.
   *   See \Drupal\media\MediaTypeInterface and \Drupal\media\Entity\MediaType
   *   for full documentation of the media type properties.
   *
   * @return \Drupal\media\MediaTypeInterface
   *   A media type.
   *
   * @see \Drupal\media\MediaTypeInterface
   * @see \Drupal\media\Entity\MediaType
   */
  private function createMediaType($source_plugin_id, array $configuration = []): MediaTypeInterface {
    $values = [
      'id' => $this->randomMachineName(),
      'label' => $this->randomString(),
      'source' => $source_plugin_id,
    ];

    /** @var \Drupal\media\MediaTypeInterface $media_type */
    $media_type = MediaType::create($values);

    $source = $media_type->getSource();
    $source_field = $source->createSourceField($media_type);
    $source_configuration = array_merge($source->getConfiguration(), $configuration);
    $source_configuration['source_field'] = $source_field->getName();
    $source->setConfiguration($source_configuration);

    $this->assertSame(SAVED_NEW, $media_type->save());

    // Add the source field to the form display for the media type.
    $form_display = \Drupal::service('entity_display.repository')->getFormDisplay('media', $media_type->id(), 'default');
    $source->prepareFormDisplay($media_type, $form_display);
    $form_display->save();

    // Add the source field to the content display for the media type.
    $content_display = \Drupal::service('entity_display.repository')->getViewDisplay('media', $media_type->id(), 'default');
    $source->prepareViewDisplay($media_type, $content_display);
    $content_display->save();

    return $media_type;
  }

  /**
   * Helper method to toggle download/sync functionality and field swapping.
   *
   * @param $media_type_id
   *   The Media Type ID
   * @param $media_type
   *   The media type object
   *
   * @return void
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function testDownloadSyncToggle($media_type_id, $media_type) {
    // Store the existing active field.
    $expected_field = $media_type->getSource()->getActiveFieldName();

    // Toggle the download assets option.
    $configuration = $media_type->getSource()->getConfiguration();
    $configuration['download_assets'] = !$configuration['download_assets'];
    $media_type->getSource()->setConfiguration($configuration);
    $media_type->save();

    // Test that the new configuration is different from original.
    $media_type = $this->entityTypeManager->getStorage('media_type')->load($media_type_id);
    $new_expected_field = $media_type->getSource()->getActiveFieldName();
    $this->assertNotEquals($expected_field, $new_expected_field);
    $entity_view_display = $this->entityTypeManager->getStorage('entity_view_display')->load('media.' . $media_type_id . '.default');
    $this->assertArrayHasKey($new_expected_field, $entity_view_display->get('content'));
    $this->assertArrayNotHasKey($expected_field, $entity_view_display->get('content'));
    $this->assertArrayHasKey($expected_field, $entity_view_display->get('hidden'));
    $this->assertArrayNotHasKey($new_expected_field, $entity_view_display->get('hidden'));
  }

  /**
   * Data provider for Asset Tests.
   *
   * @return \Generator
   *   The data.
   */
  public static function dataProviderDamMediaSourceFields(): \Generator {
    yield [
      'acquia_dam_asset:audio',
      ['download_assets' => FALSE],
      [
        'type' => 'acquia_dam_embed_code',
        'label' => 'hidden',
        'settings' => [
          'embed_style' => 'remote_streaming',
        ],
        'third_party_settings' => [],
        'region' => 'content',
      ]
    ];
    yield [
      'acquia_dam_asset:audio',
      ['download_assets' => TRUE],
      [
        'type' => 'file_audio',
        'label' => 'hidden',
        'settings' => [
          'controls' => TRUE,
          'autoplay' => FALSE,
          'loop' => FALSE,
          'multiple_file_display_type' => 'tags',
        ],
        'third_party_settings' => [],
        'region' => 'content',
        'file_extensions' => 'mp3 ogg wav',
      ]
    ];
    yield [
      'acquia_dam_asset:documents',
      ['download_assets' => FALSE],
      [
        'type' => 'acquia_dam_embed_code',
        'label' => 'hidden',
        'settings' => [
          'embed_style' => 'original',
        ],
        'third_party_settings' => [],
        'region' => 'content',
      ]
    ];
    yield [
      'acquia_dam_asset:documents',
      ['download_assets' => TRUE],
      [
        'type' => 'file_default',
        'label' => 'hidden',
        'settings' => ['use_description_as_link_text' => TRUE],
        'third_party_settings' => [],
        'region' => 'content',
      ]
    ];
    yield [
      'acquia_dam_asset:generic',
      ['download_assets' => FALSE],
      [
        'type' => 'acquia_dam_embed_code',
        'label' => 'hidden',
        'settings' => [
          'embed_style' => 'link_download',
        ],
        'third_party_settings' => [],
        'region' => 'content',
      ]
    ];
    yield [
      'acquia_dam_asset:generic',
      ['download_assets' => TRUE],
      [
        'type' => 'file_default',
        'label' => 'hidden',
        'settings' => ['use_description_as_link_text' => TRUE],
        'third_party_settings' => [],
        'region' => 'content',
      ]
    ];
    yield [
      'acquia_dam_asset:image',
      ['download_assets' => FALSE],
      [
        'type' => 'acquia_dam_embed_code',
        'label' => 'hidden',
        'settings' => [
          'embed_style' => 'original',
        ],
        'third_party_settings' => [],
        'region' => 'content',
      ]
    ];
    yield [
      'acquia_dam_asset:image',
      ['download_assets' => TRUE],
      [
        'type' => 'image',
        'label' => 'hidden',
        'settings' => [
          'image_link' => '',
          'image_style' => '',
          'image_loading' => ['attribute' => 'lazy'],
        ],
        'third_party_settings' => [],
        'region' => 'content',
      ]
    ];
    yield [
      'acquia_dam_asset:pdf',
      ['download_assets' => FALSE],
      [
        'type' => 'acquia_dam_embed_code',
        'label' => 'hidden',
        'settings' => [
          'embed_style' => 'link_thumbnail_download',
        ],
        'third_party_settings' => [],
        'region' => 'content',
      ]
    ];
    yield [
      'acquia_dam_asset:pdf',
      ['download_assets' => TRUE],
      [
        'type' => 'file_default',
        'label' => 'hidden',
        'settings' => ['use_description_as_link_text' => TRUE],
        'third_party_settings' => [],
        'region' => 'content',
      ]
    ];
    yield [
      'acquia_dam_asset:spinset',
      ['download_assets' => FALSE],
      [
        'type' => 'acquia_dam_embed_code',
        'label' => 'hidden',
        'settings' => [
          'embed_style' => 'link_text',
        ],
        'third_party_settings' => [],
        'region' => 'content',
      ]
    ];
    yield [
      'acquia_dam_asset:spinset',
      ['download_assets' => TRUE],
      [
        'type' => 'file_default',
        'label' => 'hidden',
        'settings' => ['use_description_as_link_text' => TRUE],
        'third_party_settings' => [],
        'region' => 'content',
      ]
    ];
    yield [
      'acquia_dam_asset:video',
      ['download_assets' => FALSE],
      [
        'type' => 'acquia_dam_embed_code',
        'label' => 'hidden',
        'settings' => [
          'embed_style' => 'inline_view',
        ],
        'third_party_settings' => [],
        'region' => 'content',
      ]
    ];
    yield [
      'acquia_dam_asset:video',
      ['download_assets' => TRUE],
      [
        'type' => 'file_video',
        'label' => 'hidden',
        'settings' => [
          'controls' => TRUE,
          'autoplay' => FALSE,
          'loop' => FALSE,
          'multiple_file_display_type' => 'tags',
          'muted' => FALSE,
          'width' => 640,
          'height' => 480,
        ],
        'third_party_settings' => [],
        'region' => 'content',
        'file_extensions' => 'mp4 mov',
      ]
    ];
  }

  /**
   * Data provider for Image Asset  Tests.
   *
   * @return \Generator
   *   The data.
   */
  public static function dataProviderImageTypeFields(): \Generator {
    yield [
      [
        'type' => 'acquia_dam_embed_code',
        'label' => 'hidden',
        'settings' => [
          'embed_style' => 'original',
        ],
        'third_party_settings' => [],
        'region' => 'content',
      ],
      [
        'type' => 'image',
        'label' => 'hidden',
        'settings' => [
          'image_link' => '',
          'image_style' => '',
          'image_loading' => ['attribute' => 'lazy'],
        ],
        'third_party_settings' => [],
        'region' => 'content',
      ]
    ];
    yield [
      [
        'type' => 'image',
        'label' => 'hidden',
        'settings' => [
          'image_link' => '',
          'image_style' => '',
          'image_loading' => ['attribute' => 'lazy'],
        ],
        'third_party_settings' => [],
        'region' => 'content',
      ],
      [
        'type' => 'acquia_dam_embed_code',
        'label' => 'hidden',
        'settings' => [
          'embed_style' => 'original',
        ],
        'third_party_settings' => [],
        'region' => 'content',
      ],
    ];
    yield [
      [
        'type' => 'acquia_dam_embed_code',
        'label' => 'hidden',
        'settings' => [
          'embed_style' => 'large',
        ],
        'third_party_settings' => [],
        'region' => 'content',
      ],
      [
        'type' => 'image',
        'label' => 'hidden',
        'settings' => [
          'image_link' => '',
          'image_style' => 'large',
          'image_loading' => ['attribute' => 'lazy'],
        ],
        'third_party_settings' => [],
        'region' => 'content',
      ]
    ];
    yield [
      [
        'type' => 'image',
        'label' => 'hidden',
        'settings' => [
          'image_link' => '',
          'image_style' => 'large',
          'image_loading' => ['attribute' => 'lazy'],
        ],
        'third_party_settings' => [],
        'region' => 'content',
      ],
      [
        'type' => 'acquia_dam_embed_code',
        'label' => 'hidden',
        'settings' => [
          'embed_style' => 'large',
        ],
        'third_party_settings' => [],
        'region' => 'content',
      ],
    ];
  }

}
