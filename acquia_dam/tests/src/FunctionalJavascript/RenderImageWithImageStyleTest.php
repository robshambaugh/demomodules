<?php

namespace Drupal\Tests\acquia_dam\FunctionalJavascript;

/**
 * Tests image render using On-site stored asset image field.
 *
 * This module alters the definition of 'Thumbnail' formatter and
 * provides its own formatter to handle the rendering functionality.
 * When a `Download and sync assets` option is enabled for Image media type,
 * then image renders using the On-site stored asset image field and
 * image style is selected.
 *
 * @see acquia_dam_field_formatter_info_alter
 *
 * @group acquia_dam
 */
final class RenderImageWithImageStyleTest extends AcquiaDamWebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createMediaReferenceField(1);
    // Create user to update image media type and login.
    $user = $this->createUser(['administer media types']);
    $this->drupalLogin($user);
    // Configure the Acquia DAM Image media type for download and sync options.
    $this->drupalGet('admin/structure/media/manage/acquia_dam_image_asset');
    $this->assertSession()->waitForText('Download and sync assets');
    // Verify the download and sync assets option is disabled.
    $this->assertSession()->checkboxNotChecked('source_configuration[download_assets]');
    // Enable the download and sync assets option.
    $this->getSession()->getPage()->checkField('source_configuration[download_assets]');
    $this->getSession()->getPage()->pressButton('Save');
  }

  /**
   * Creating a media field for the test.
   */
  protected function createMediaReferenceField($cardinality = -1) {
    $this->createEntityReferenceField(
      'node',
      'page',
      'field_acquia_dam',
      'Acquia DAM',
      'media',
      'default:media',
      [],
      $cardinality,
    );
    $display_repository = $this->container->get('entity_display.repository');
    $display_repository->getFormDisplay('node', 'page')
      ->setComponent('field_acquia_dam', [
        'type' => 'media_library_widget',
        'region' => 'content',
      ])->save();
    $display_repository->getViewDisplay('node', 'page', 'default')
      ->setComponent('field_acquia_dam', [
        'type' => 'media_thumbnail',
      ])->save();
  }

  /**
   * Update field display with given image style.
   *
   * @param string $image_style
   *   The image style.
   */
  private function updateFieldViewDisplay(string $image_style): void {
    $this->container->get('entity_display.repository')
      ->getViewDisplay('node', 'page', 'default')
      ->setComponent('field_acquia_dam', [
        'type' => 'media_thumbnail',
        'settings' => [
          'image_link' => '',
          'image_style' => $image_style,
          'image_loading' => ['attribute' => 'lazy'],
        ],
      ])->save();
  }

  /**
   * Test image rendered using selected image style.
   *
   * @param string $image_style
   *   Name of an image style.
   *
   * @dataProvider imageStyleProvider
   */
  public function testImageWithImageStyle(string $image_style) {
    $asset_id = 'f2a9c03d-3664-477c-8013-e84504ed5adc';
    $filename = 'An asset with many versions.png';

    // Update field view display settings with selected image style.
    $this->updateFieldViewDisplay($image_style);

    // Create user and login.
    $user = $this->createUser(['create page content', 'edit any page content']);
    $this->drupalLogin($user);

    // Acquia DAM authorization.
    $this->grantSiteRegistrationToken();
    $this->grantCurrentUserDamToken();

    // Create node of type page and add media from acquia DAM.
    $this->drupalGet('node/add/page');
    $this->getSession()->getPage()->fillField('Title', 'Image with image style large');
    $wrapper = $this->assertSession()->elementExists('css', '#field_acquia_dam-media-library-wrapper');
    $wrapper->pressButton('Add media');
    $this->assertNotNull($this->assertSession()->waitForText('Select Media Source'));

    // Wait for media library modal to open.
    $modal = $this->assertSession()->waitForElement('css', '#drupal-modal');
    $modal->clickLink('Image');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Select and insert specified image.
    $this->selectAndInsertAsset($asset_id, 'Image', '', FALSE);

    $this->getSession()->switchToIFrame();
    $this->getSession()->getPage()->pressButton('Save');

    $this->getSession()->getPage()->findLink('Image with image style large')->click();

    // Validate image rendered with selected images style.
    $expected_uri = "styles/$image_style/public/dam/m2x3z2j9ou/" . preg_replace('/\s/', '-', $filename);

    $this->assertSession()->elementAttributeContains('css', 'img', 'src', $expected_uri);
    $this->assertSession()->elementAttributeContains('css', 'img', 'loading', 'lazy');
  }

  /**
   * Image style provider.
   */
  public static function imageStyleProvider(): array {
    return [
      ['large'],
      ['media_library'],
      ['medium'],
      ['thumbnail'],
      ['wide'],
    ];
  }

}
