<?php

use Drupal\acquia_dam\Entity\MediaSourceField;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\Tests\acquia_dam\FunctionalJavascript\AcquiaDamWebDriverTestBase;

/**
 * Tests integration with Acquia Site Studio.
 *
 * @group acquia_dam
 * @requires module entity_reference_revisions
 */
final class AcquiaDamEntityReferenceRevisionsIntegrationTest extends AcquiaDamWebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_reference_revisions',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Look for or add the specified field to the requested entity bundle.
    if (!FieldStorageConfig::loadByName('node', 'media_revision_field')) {
      FieldStorageConfig::create([
        'field_name' => 'media_revision_field',
        'type' => 'entity_reference_revisions',
        'entity_type' => 'node',
        'cardinality' => -1,
        'settings' => [
          'target_type' => 'media',
        ],
      ])->save();
    }
    if (!FieldConfig::loadByName('node', 'page', 'media_revision_field')) {
      FieldConfig::create([
        'field_name' => 'media_revision_field',
        'entity_type' => 'node',
        'bundle' => 'page',
        'label' => 'A Media Field',
        'settings' => [
          'handler' => 'default:media',
          'handler_settings' => ['target_bundles' => NULL],
        ],
      ])->save();
    }
    $display_repository = $this->container->get('entity_display.repository');
    $display_repository->getFormDisplay('node', 'page', 'default')
      ->setComponent('media_revision_field', [
        'type' => 'entity_reference_revisions_asset_media_library',
        'region' => 'content',
        'settings' => [
          'media_types' => [],
        ],
      ])
      ->save();
    $display_repository->getViewDisplay('node', 'page', 'default')
      ->setComponent('media_revision_field', [
        'type' => 'entity_reference_revisions_entity_view',
      ])
      ->save();
  }

  /**
   * Test to check the versioning in a ERR widget.
   */
  public function testMediaLibraryErrWidget() {
    $this->createAndLoginContentCreator();
    $this->grantSiteRegistrationToken();
    $this->grantCurrentUserDamToken();
    $media = Media::create([
      'bundle' => 'acquia_dam_image_asset',
      MediaSourceField::SOURCE_FIELD_NAME => [
        'asset_id' => 'f2a9c03d-3664-477c-8013-e84504ed5adc',
        'version_id' => '9a5d5c65-0260-43d4-9e24-404862c91cbf',
      ],
    ]);
    $media->save();
    $first_revision = $media->getRevisionId();

    $node = $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'test',
      'media_revision_field' => [
        [
          'target_id' => $media->id(),
          'target_revision_id' => $first_revision,
        ],
      ],
    ]);

    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->assertSession()->waitForText('A media Field');

    $this->assertSession()->pageTextContains('An-asset-with-many-versions.png');
    $media->setNewRevision();
    $media->set(MediaSourceField::SOURCE_FIELD_NAME, [
      'asset_id' => 'f2a9c03d-3664-477c-8013-e84504ed5adc',
      'version_id' => '04984534-8682-4fbf-95ae-f3c7b46af9ee',
    ]);
    $media->save();
    $second_revision = $media->getRevisionId();
    self::assertNotEquals($first_revision, $second_revision);
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->assertSession()->waitForText('A media Field');
    $wrapper = $this->assertSession()->elementExists('css', '#media_revision_field-media-library-wrapper');
    $wrapper->hasButton('Update Media');
    $wrapper->pressButton('Update Media');
    $this->assertSession()->assertWaitOnAjaxRequest(20000);
    $this->assertSession()->waitForElement('css', '#drupal-modal', 20000);
    $this->assertSession()->waitForText('You are about to update your current media to a newer version.');
    $this->assertSession()->pageTextContains('You are about to update your current media to a newer version.');
    $this->assertSession()->pageTextContains('Current media');
    $this->assertSession()->pageTextContains('Updated media');

    $this->pressDialogButton('Update');
    $this->assertSession()->pageTextNotContains('Update Media');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->waitForText('page test has been updated.');
    $updated_node = Node::load($node->id());
    self::assertEquals($updated_node->get('media_revision_field')->getValue()[0]['target_revision_id'], $second_revision);
  }

  /**
   * Helper function to log in a user with necessary permission and access.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException.
   */
  protected function createAndLoginContentCreator() {
    $user = $this->drupalCreateUser([
      'use text format test_format',
      'access media overview',
      'create page content',
      'edit any page content',
      'view all media revisions',
    ]);

    $this->drupalLogin($user);
  }

}
