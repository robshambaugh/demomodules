<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_dam\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\acquia_dam\Entity\ManagedImageField;
use Drupal\acquia_dam\Entity\MediaSourceField;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\media\Entity\MediaType;
use Drupal\media\MediaInterface;

/**
 * Tests creation of DAM asset media types and form/view display defaults.
 *
 * @group acquia_dam
 */
final class MediaTypeTest extends AcquiaDamWebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'ckeditor5',
    'node',
    'field_ui',
    'acquia_dam',
    'acquia_dam_test',
    // Install dblog to assist with debugging.
    'dblog',
    'datetime',
  ];

  /**
   * Tests creating media types with our source plugin.
   *
   * @param string $asset_type
   *   The asset type ID.
   * @param string $label
   *   The media type label.
   * @param bool $use_download
   *   Whether the downloading feature is enabled on the media type or not.
   *
   * @dataProvider damMediaTypeData
   *
   * @see \Drupal\Tests\media\FunctionalJavascript\MediaSourceTestBase::doTestCreateMediaType
   */
  public function testCreateDamMediaType(string $asset_type, string $label, bool $use_download = FALSE): void {
    $this->grantSiteRegistrationToken();
    $user = $this->createUser([
      'access content',
      'administer media types',
      'administer media fields',
      'administer media form display',
      'administer media display',
    ]);
    self::assertNotFalse($user);
    $this->drupalLogin($user);
    $this->drupalGet(Url::fromRoute('entity.media_type.add_form'));
    $this->getSession()->getPage()->fillField('label', $label);
    // Wait for machine-name.js to append its elements.
    $this->assertSession()->waitForElementVisible('css', '#edit-label-machine-name-suffix .admin-link');
    $this->getSession()->getPage()->fillField('Description', "Assets from the DAM of the type $asset_type");

    $source_id = "acquia_dam_asset:$asset_type";
    $this->assertSession()->optionExists('Media source', $source_id);
    $this->getSession()->getPage()->selectFieldOption('Media source', $source_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->waitForElementVisible('css', 'fieldset[data-drupal-selector="edit-source-configuration"]');
    $this->assertSession()->fieldValueEquals('source_configuration[source_field]', 'acquia_dam_asset_id');

    // If dataset is set to use the download feature, mark the checkbox.
    if ($use_download) {
      $this->getSession()->getPage()->checkField('Download and sync assets');
    }

    $this->getSession()->getPage()->selectFieldOption('field_map[filename]', 'name');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->waitForText("The media type $label has been added.");
    $this->assertSession()->pageTextContains("The media type $label has been added.");
    $this->assertSession()->pageTextContains("Media Library form and view displays have been created for the $label media type.");

    $this->assertSession()->addressEquals('/admin/structure/media');
    $media_type_table_row = $this->getMediaTypeTableRow($label);
    $media_type_table_row->clickLink('Edit');
    if ($use_download) {
      $this->assertSession()->checkboxChecked('Download and sync assets');
    }
    else {
      $this->assertSession()->checkboxNotChecked('Download and sync assets');
    }
    $this->getSession()->getPage()->clickLink('Manage form display');

    // Hide hidden fields in display management.
    // Drupal core has \Drupal\Tests\HiddenFieldSelector for input[type=hidden]
    // but there aren't helpers for visibly hidden fields.
    $this->getSession()->getPage()->pressButton('Show row weights');

    $field_name = MediaSourceField::SOURCE_FIELD_NAME;
    $this->assertSession()->fieldValueEquals("fields[$field_name][region]", 'hidden');

    // Assert the view displays.
    $this->getSession()->getPage()->clickLink('Manage display');

    // view display mode.
    if ($use_download) {
      $this->assertSession()->fieldValueEquals("fields[$field_name][region]", 'hidden');
      $this->assertSession()->fieldValueEquals("fields[$field_name][type]", 'acquia_dam_embed_code');
      // Verify that image field is visible.
      $local_field_name = ManagedImageField::MANAGED_IMAGE_FIELD_NAME;
      $this->assertSession()->fieldValueEquals("fields[$local_field_name][region]", 'content');
      $this->assertSession()->fieldValueEquals("fields[$local_field_name][type]", 'image');
    }
    else {
      $this->assertSession()->fieldValueEquals("fields[$field_name][region]", 'content');
      $this->assertSession()->fieldValueEquals("fields[$field_name][type]", 'acquia_dam_embed_code');
    }

    $this->getSession()->getPage()->clickLink('Media library');
    $this->assertSession()->fieldValueEquals("fields[$field_name][region]", 'content');
    $this->assertSession()->fieldValueEquals("fields[$field_name][label]", 'hidden');
    $this->assertSession()->fieldValueEquals("fields[$field_name][type]", 'acquia_dam_embed_code');
    // The thumbnail should be hidden in favor of asset ID.
    $this->assertSession()->fieldValueEquals("fields[thumbnail][region]", 'hidden');
  }

  /**
   * Test data provider.
   *
   * @return \Generator
   *   The data.
   */
  public static function damMediaTypeData(): \Generator {
    yield ['pdf', 'PDF (DAM Asset Test)'];
    yield ['video', 'Video (DAM Asset Test)'];
    yield ['image', 'Image (DAM Asset Test)', TRUE];
    yield ['audio', 'Audio (DAM Asset Test)'];
    yield ['generic', 'Generic (DAM Asset Test)'];
  }

  /**
   * Tests configuring metadata mapping for media types.
   */
  public function testMediaTypeMetadataMapping(): void {
    $field_storage = FieldStorageConfig::create([
      'entity_type' => 'media',
      'field_name' => 'field_keywords',
      'type' => 'string',
    ]);
    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'acquia_dam_pdf_asset',
      'label' => 'Keywords',
    ])->save();
    $field_storage->save();

    $user = $this->createUser([
      'access content',
      'administer media types',
      'administer media fields',
      'administer media form display',
      'administer media display',
      'administer site configuration',
    ]);
    self::assertNotFalse($user);
    $this->drupalLogin($user);

    $this->grantSiteRegistrationToken();
    $this->grantCurrentUserDamToken();

    $this->drupalGet(Url::fromRoute('acquia_dam.metadata_config'));
    $this->getSession()->getPage()->hasTable('#edit-metadata');
    $this->getSession()->getPage()->hasUncheckedField('#edit-metadata-keywords');
    $this->click('#edit-metadata-keywords');
    $this->getSession()->getPage()->hasCheckedField('#edit-metadata-keywords');
    $this->getSession()->getPage()->pressButton('Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    $this->drupalGet(Url::fromRoute('entity.media_type.edit_form', [
      'media_type' => 'acquia_dam_pdf_asset',
    ]));
    $this->assertSession()->pageTextContains('Map Fields');
    $this->assertSession()->pageTextContains('Metadata can be mapped from the DAM to Drupal entity fields. Field mappings can be configured below. Information will be mapped only if an entity field is empty.');
    $this->assertSession()->pageTextContains('DAM metadata field');
    $this->assertSession()->pageTextContains('DAM field type');
    $this->assertSession()->pageTextContains('Drupal mapped field');
    $this->getSession()->getPage()->selectFieldOption('Drupal field for Keywords', 'field_keywords');
    $this->getSession()->getPage()->pressButton('Save');

    $media_type = MediaType::load('acquia_dam_pdf_asset');
    self::assertEquals([
      'keywords' => 'field_keywords',
      'filename' => 'name',
    ], $media_type->getFieldMap());

    $media_storage = \Drupal::entityTypeManager()->getStorage('media');
    $sut = $media_storage->create([
      'bundle' => 'acquia_dam_pdf_asset',
      MediaSourceField::SOURCE_FIELD_NAME => [
        'asset_id' => 'a56fb261-8ad5-4e0d-8323-0e8a3659ed39',
      ],
    ]);
    $sut->save();
    $media_storage->resetCache([$sut->id()]);
    $sut = $media_storage->load($sut->id());
    self::assertInstanceOf(MediaInterface::class, $sut);
    self::assertFalse($sut->get('field_keywords')->isEmpty());
    self::assertEquals('exercise, agile', $sut->get('field_keywords')->value);
  }

  /**
   * Tests configuring extra fields mapping for media types.
   *
   * @param string $field_name
   *   Field name.
   * @param string $field
   *   Field label to use during media type edit.
   * @param string $expected_field_value
   *   Expected date value which will be saved into the db.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   *
   * @dataProvider damMetadataExtraFields
   */
  public function testMappableDateFields(string $field_name, string $field, string $expected_field_value): void {
    $field_storage = FieldStorageConfig::create([
      'entity_type' => 'media',
      'field_name' => $field_name,
      'type' => 'datetime',
    ]);
    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'acquia_dam_pdf_asset',
      'label' => 'Test Date',
    ])->save();
    $field_storage->save();

    $user = $this->createUser([
      'access content',
      'administer media types',
      'administer media fields',
      'administer media form display',
      'administer media display',
      'administer site configuration',
    ]);
    self::assertNotFalse($user);
    $this->drupalLogin($user);

    $this->grantSiteRegistrationToken();
    $this->grantCurrentUserDamToken();

    $this->drupalGet(Url::fromRoute('entity.media_type.edit_form', [
      'media_type' => 'acquia_dam_pdf_asset',
    ]));
    $this->assertSession()->pageTextContains('Map Fields');
    $this->assertSession()->pageTextContains('Metadata can be mapped from the DAM to Drupal entity fields. Field mappings can be configured below. Information will be mapped only if an entity field is empty.');
    $this->assertSession()->pageTextContains('DAM metadata field');
    $this->assertSession()->pageTextContains('DAM field type');
    $this->assertSession()->pageTextContains('Drupal mapped field');
    $this->getSession()->getPage()->selectFieldOption("Drupal field for $field", $field_name);
    $this->getSession()->getPage()->pressButton('Save');

    $media_storage = \Drupal::entityTypeManager()->getStorage('media');
    $sut = $media_storage->create([
      'bundle' => 'acquia_dam_pdf_asset',
      MediaSourceField::SOURCE_FIELD_NAME => [
        'asset_id' => '0324b0b2-5293-4aa0-b0aa-c85b003395e2',
      ],
    ]);
    $sut->save();
    $media_storage->resetCache([$sut->id()]);
    $sut = $media_storage->load($sut->id());
    self::assertInstanceOf(MediaInterface::class, $sut);
    self::assertEquals($expected_field_value, $sut->get($field_name)->value);
  }

  /**
   * Test data provider.
   *
   * @return \Generator
   *   The data.
   */
  public static function damMetadataExtraFields(): \Generator {
    yield ['expiration_date', 'Expiration date', '2034-08-18T11:37:19'];
    yield ['release_date', 'Release date', '2021-08-18T11:37:19'];
    yield ['last_update_date', 'Last updated date', '2021-10-26T14:23:20'];
    yield ['file_upload_date', 'File upload date', '2021-08-18T11:37:19'];
    yield ['deleted_date', 'Deleted date', ''];
  }

  /**
   * Gets the table row for a media type on the entity collection route.
   *
   * @param string $label
   *   The media type label.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The media type's table row.
   */
  private function getMediaTypeTableRow(string $label): NodeElement {
    $table_rows = $this->getSession()->getPage()->findAll('css', 'tr');
    if (count($table_rows) === 0) {
      throw new \RuntimeException('Could not find any table rows.');
    }
    foreach ($table_rows as $table_row) {
      if (strpos($table_row->getText(), $label) !== FALSE) {
        return $table_row;
      }
    }
    throw new \RuntimeException("Could not find table row matching '$label'");
  }

  /**
   * Tests that a new media type has default entity form & view mode configurations.
   */
  public function testNewMediaTypeHasDefaultEntityViewModeConfiguration() {
    $media_type = MediaType::create([
      'id' => 'test_media_type',
      'label' => 'Test Media Type',
      'source' => 'acquia_dam_asset:image',
    ]);
    $media_type->save();

    // Verify default form mode configuration.
    $configFactory = $this->container->get('config.factory');
    $form_display = $configFactory->getEditable('core.entity_form_display.media.test_media_type.default');
    $this->assertNotEmpty($form_display, 'Default form mode configuration exists for the new media type.');

    // Verify default view mode configuration.
    $view_display = $configFactory->getEditable('core.entity_view_display.media.test_media_type.default');
    $this->assertNotEmpty($view_display, 'Default view mode configuration exists for the new media type.');
  }

}
