<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_dam\FunctionalJavascript;

class AcquiaDamDownloadAndSyncAssetTest extends AcquiaDamWebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected bool $useOneTimeLoginLinks = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createMediaReferenceField(1);
    // Create user to update image media type and login.
    $user = $this->createUser(['administer media types', 'create page content', 'edit any page content', 'administer site configuration']);
    $this->drupalLogin($user);
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
   * Tests the damMediaExistLocally method.
   */
  public function testDamMediaExistLocally(): void {
    $asset_id = 'f2a9c03d-3664-477c-8013-e84504ed5adc';
    $filename = 'An asset with many versions.png';

    // Acquia DAM authorization.
    $this->grantSiteRegistrationToken();
    $this->grantCurrentUserDamToken();

    // Verify the "Download and sync assets" checkbox is not disabled.
    $this->drupalGet('admin/structure/media/manage/acquia_dam_image_asset');
    $this->assertSession()->elementAttributeNotExists('css', 'input[name="source_configuration[download_assets]"]', 'disabled');

    // Create node of type page and add media from acquia DAM.
    $this->drupalGet('node/add/page');
    $this->getSession()->getPage()->fillField('Title', 'Download and sync Media locally');
    $wrapper = $this->assertSession()->elementExists('css', '#field_acquia_dam-media-library-wrapper');
    $wrapper->pressButton('Add media');

    // Wait for media library modal to open.
    $modal = $this->assertSession()->waitForElement('css', '#drupal-modal');
    // Wait for the "Image" link to appear.
    $this->assertSession()->waitForElement('named', ['link', 'Image']);
    $modal->clickLink('Image');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Select and insert specified image.
    $this->selectAndInsertAsset($asset_id, 'Image', '', FALSE);
    $this->getSession()->switchToIFrame();
    $this->getSession()->getPage()->pressButton('Save');

    // Verify the "Download and sync assets" checkbox is disabled.
    $this->drupalGet('admin/structure/media/manage/acquia_dam_image_asset');
    $this->assertSession()->elementAttributeContains('css', 'input[name="source_configuration[download_assets]"]', 'disabled', 'disabled');

    // Run batch to sync media locally
    $this->getSession()->getPage()->pressButton('edit-source-configuration-download-assets-button');

    // Wait for batch to complete.
    $this->assertSession()->waitForText('All assets have been downloaded and synced successfully.');

    // Verify now the "Download and sync assets" checkbox is enabled.
    $this->drupalGet('admin/structure/media/manage/acquia_dam_image_asset');
    $this->getSession()->getPage()->checkField('source_configuration[download_assets]');
    $this->drupalGet('node/1');

    // Validate image rendered local url.
    $expected_uri = "files/dam/m2x3z2j9ou/" . preg_replace('/\s/', '-', $filename);
    $this->assertSession()->elementAttributeContains('css', 'img', 'src', $expected_uri);
    $this->assertSession()->elementAttributeContains('css', 'img', 'loading', 'lazy');
  }
}
