<?php

namespace Drupal\Tests\acquia_dam_integration_links\FunctionalJavascript;

use Drupal\Tests\acquia_dam\FunctionalJavascript\AcquiaDamWebDriverTestBase;
use Drupal\Tests\ckeditor5\Traits\CKEditor5TestTrait;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;

// Workaround to support tests against both Drupal 10.1 and Drupal 11.0.
// @todo Remove once we depend on Drupal 10.2.
if (!trait_exists(EntityReferenceFieldCreationTrait::class)) {
  class_alias('\Drupal\Tests\field\Traits\EntityReferenceTestTrait', EntityReferenceFieldCreationTrait::class);
}

/**
 * Tests integration link register.
 *
 * @group acquia_dam
 */
class MediaInsertTest extends AcquiaDamWebDriverTestBase {

  use EntityReferenceFieldCreationTrait;
  use CKEditor5TestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'acquia_dam',
    'acquia_dam_integration_links',
  ];

  /**
   * Tests integration link creation on entity insert.
   *
   * @param string $asset_type
   *   Asset type.
   * @param string $asset_id
   *   Asset uuid.
   *
   * @dataProvider assetProvider
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException|\Behat\Mink\Exception\ResponseTextException
   */
  public function testIntegrationLinkOnInsert(string $asset_type, string $asset_id) {
    $this->createMediaReferenceField();
    $this->createAndLoginContentCreator();
    $this->grantSiteRegistrationToken();
    $this->grantCurrentUserDamToken();

    $this->drupalGet('/node/add');
    $this->getSession()->getPage()->fillField('Title', 'Embed test');

    // Add to CKEditor.
    $this->waitForEditor();
    $this->pressEditorButton('Insert Media');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $format = $asset_type === 'Image' ? 'original' : 'inline_view';
    $this->selectAndInsertAsset($asset_id, $asset_type, $format);
    self::assertEquals(1, $this->getTrackingTableRowCount());

    // Add same item to field.
    $wrapper = $this->assertSession()->elementExists('css', '#media_field-media-library-wrapper');
    $wrapper->pressButton('Add media');
    $this->assertNotNull($this->assertSession()->waitForText('Add or select media'));
    $this->selectAndInsertAsset($asset_id, $asset_type, '', FALSE);
    self::assertEquals(1, $this->getTrackingTableRowCount());

    // Save and verify.
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->waitForText('Embed test');
    self::assertEquals(2, $this->getTrackingTableRowCount());
  }

  /**
   * Returns count of rows from 'acquia_dam_integration_link_tracking' table.
   *
   * @return int
   *   Amount of rows in the table.
   */
  protected function getTrackingTableRowCount() :int {
    \Drupal::getContainer()->get('cron')->run();
    return \Drupal::database()
      ->select('acquia_dam_integration_link_tracking', 'int')
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * Data for the Asset test.
   */
  public static function assetProvider(): array {
    return [
      ['PDF', '0324b0b2-5293-4aa0-b0aa-c85b003395e2'],
      ['Video', 'efb03f75-3c42-497b-baa9-5ec79d1f56af'],
      ['Spinset', 'eec6d92b-6452-4ab6-894a-b4d0826e65ba'],
      ['Image', 'f2a9c03d-3664-477c-8013-e84504ed5adc'],
    ];
  }

}
