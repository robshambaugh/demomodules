<?php

namespace Drupal\Tests\acquia_dam\FunctionalJavascript;

use Drupal\image\Entity\ImageStyle;
use Drupal\Tests\ckeditor5\Traits\CKEditor5TestTrait;

/**
 * Focal point element test.
 *
 * @requires module focal_point
 * @requires module crop
 * @requires module ckeditor
 *
 * @group acquia_dam
 */
class FocalPointMediaLibraryTest extends AcquiaDamWebDriverTestBase {

  use CKEditor5TestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'focal_point',
  ];

  /**
   * Test DAM media with focal point.
   */
  public function testFocalPointElement() {
    $this->createAndLoginContentCreator();
    $this->grantSiteRegistrationToken();
    $this->grantCurrentUserDamToken();

    $image_style = ImageStyle::create(['name' => 'Focal Point']);
    $image_style->addImageEffect([
      'id' => 'focal_point_crop',
      'data' => [
        'width' => 200,
        'height' => 200,
        'crop_type' => 'focal_point',
      ],
    ]);
    $image_style->save();

    $this->drupalGet('/node/add/page');
    $this->waitForEditor();
    $this->pressEditorButton('Insert Media');

    $modal = $this->assertSession()->waitForElement('css', '#drupal-modal');
    $modal->clickLink('Image');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $media_select_checkbox = $this->assertSession()->waitForElementVisible('css', '[value="f2a9c03d-3664-477c-8013-e84504ed5adc"]');
    $media_select_checkbox->check();

    $this->pressDialogButton('Next: Select Format');
    $this->assertSession()->waitForText('Choose a format for your media');
    $this->getSession()->getPage()->selectFieldOption('embed_code', 'Focal Point');

    $this->assertSession()->waitForElementVisible('css', '.focal-point-indicator');
    $elements = $this->getSession()->getPage()->findAll('css', '.focal-point-indicator');
    $this->assertTrue(count($elements) === 1);

    $this->getSession()->getPage()->selectFieldOption('embed_code', 'large');
    $this->assertSession()->waitForElementRemoved('css', '.focal-point-indicator');
    $elements = $this->getSession()->getPage()->findAll('css', '.focal-point-indicator');
    $this->assertTrue(count($elements) === 0);
  }

}
