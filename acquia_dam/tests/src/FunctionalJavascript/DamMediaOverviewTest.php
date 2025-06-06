<?php

namespace src\FunctionalJavascript;

use Drupal\Tests\acquia_dam\FunctionalJavascript\AcquiaDamWebDriverTestBase;
use Drupal\Tests\ckeditor5\Traits\CKEditor5TestTrait;

/**
 * Test DAM media overview.
 *
 * @group acquia_dam
 * @requires module ckeditor
 */
class DamMediaOverviewTest extends AcquiaDamWebDriverTestBase {

  use CKEditor5TestTrait;

  /**
   * Tests DAM content overview.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testDamMediaOverview() {
    $this->createAndLoginContentCreator();
    $this->grantSiteRegistrationToken();
    $this->grantCurrentUserDamToken();

    $this->drupalGet('/node/add/page');
    $this->getSession()->getPage()->fillField('Title', 'Embed Form test');

    $this->waitForEditor();
    $this->pressEditorButton('Insert Media');

    // Check the embedcode form.
    $this->selectAndInsertAsset('f2a9c03d-3664-477c-8013-e84504ed5adc', 'Image', 'original');

    $this->getSession()->switchToIFrame();
    $this->getSession()->getPage()->pressButton('Save');

    $this->drupalGet('/admin/content/dam-media');

    $this->getSession()->getPage()->hasLink('2 places');
    $this->getSession()->getPage()->hasLink('Check for update');
  }

}
