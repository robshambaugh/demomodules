<?php

declare(strict_types=1);

namespace Drupal\Tests\acquiadam_asset_import\FunctionalJavascript;

use Drupal\Tests\acquia_dam\FunctionalJavascript\AcquiaDamWebDriverTestBase;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;

// Workaround to support tests against both Drupal 10.1 and Drupal 11.0.
// @todo Remove once we depend on Drupal 10.2.
if (!trait_exists(EntityReferenceFieldCreationTrait::class)) {
  class_alias('\Drupal\Tests\field\Traits\EntityReferenceTestTrait', EntityReferenceFieldCreationTrait::class);
}

/**
 * Tests config form submission.
 *
 * @tests \Drupal\acquiadam_asset_import\Form\BulkImportConfigForm
 *
 * @group acquia_dam
 */
final class BulkImportConfigFormSubmitTest extends AcquiaDamWebDriverTestBase {

  use EntityReferenceFieldCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'acquia_dam',
    'acquiadam_asset_import',
  ];

  /**
   * Tests that the form can be submitted.
   *
   * @param string $source_type_id
   *   The source type id.
   * @param string $source_type_name
   *   The source type name.
   * @param string $name
   *   The name of the option to select.
   * @param array $expected_Selectors
   *   The list of media types to select.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException|\Behat\Mink\Exception\ResponseTextException
   *
   * @dataProvider categorySelectionProvider
   */
  public function testFormSubmit(string $source_type_id, string $source_type_name, string $name, array $expected_Selectors): void {
    $user = $this->createUser(['administer site configuration']);
    $this->drupalLogin($user);
    $this->grantSiteRegistrationToken();
    $this->grantCurrentUserDamToken();
    $this->drupalGet('/admin/config/acquia-dam/bulk-import');

    $assert_session = $this->assertSession();

    // Check the initial state of the form.
    $assert_session->pageTextContains('Acquia DAM bulk import');
    $assert_session->pageTextContains('Assets to import from');
    $source_select_list = $assert_session->selectExists('source_type');

    // Check the category selection on the form.
    $source_select_list->selectOption($source_type_name);
    $assert_session->assertWaitOnAjaxRequest();
    $asset_select_list = $assert_session->selectExists("{$source_type_id}_uuid");
    $assert_session->pageTextContains("List of $source_type_name in the remote DAM system available for the authorized user account. Please choose which of them the media assets should be imported from.");
    $assert_session->elementExists('css', '#selected-data-table');
    $assert_session->pageTextContains('No category or asset group has been selected yet.');

    // Select a category.
    $asset_select_list->selectOption($name);
    $assert_session->assertWaitOnAjaxRequest();
    $asset_filtering_checkbox = $assert_session->elementExists('css', '[data-drupal-selector="edit-enable-filter"]');

    // Enable filtering.
    $asset_filtering_checkbox->check();
    $assert_session->pageTextContains('Import only assets which would be assigned to these media types');
    $assert_session->elementExists('css', '[data-drupal-selector="edit-media-bundles-acquia-dam-image-asset"]');

    // Add the current assignment to the list.
    $assert_session->buttonExists('+ Add')->click();
    $assert_session->assertWaitOnAjaxRequest();

    // Check the table content.
    //$assert_session->elementExists('css', 'tr[data-drupal-selector="edit-selected-table-0"].odd');
    $assert_session->elementTextContains('css', 'table#selected-data-table > tbody > tr:nth-child(1) > td:nth-child(1)', $source_type_name);
    $assert_session->elementTextContains('css', 'table#selected-data-table > tbody > tr:nth-child(1) > td:nth-child(2)', $name);
    $assert_session->elementTextContains('css', 'table#selected-data-table > tbody > tr:nth-child(1) > td:nth-child(3)', 'All assets (no filtering)');
    $assert_session->buttonExists('Save')->click();

    // Delete the first row.
    $assert_session->buttonExists('Remove')->click();
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->pageTextContains('No category or asset group has been selected yet.');
    $assert_session->buttonExists('Save')->click();

    // Select a category again.
    $source_select_list->selectOption($source_type_name);
    $assert_session->assertWaitOnAjaxRequest();
    $asset_select_list->selectOption($name);
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->pageTextContains("List of $source_type_name in the remote DAM system available for the authorized user account. Please choose which of them the media assets should be imported from.");
    $assert_session->elementExists('css', '[data-drupal-selector="edit-enable-filter"]')->check();

    // Select few media types.
    $assert_session->elementExists('css', '[data-drupal-selector="edit-media-bundles-acquia-dam-documents-asset"]')->check();
    $assert_session->elementExists('css', '[data-drupal-selector="edit-media-bundles-acquia-dam-image-asset"]')->check();
    $assert_session->elementExists('css', '[data-drupal-selector="edit-media-bundles-acquia-dam-pdf-asset"]')->check();

    // Add the current assignment to the list again.
    $assert_session->buttonExists('+ Add')->click();
    $assert_session->assertWaitOnAjaxRequest();

    // Finally submit the form.
    $assert_session->buttonExists('Save')->click();
    $assert_session->pageTextContains('The configuration settings have been successfully saved.');

    // Select remaining media types.
    $source_select_list = $assert_session->selectExists('source_type');
    $source_select_list->selectOption($source_type_name);
    $assert_session->assertWaitOnAjaxRequest();
    $asset_select_list = $assert_session->selectExists("{$source_type_id}_uuid");
    $asset_select_list->selectOption($name);
    $assert_session->assertWaitOnAjaxRequest();

    $this->assertTrue($assert_session->elementExists('css', '[data-drupal-selector="edit-media-bundles-acquia-dam-documents-asset"]')->isChecked());
    $this->assertTrue($assert_session->elementExists('css', '[data-drupal-selector="edit-media-bundles-acquia-dam-image-asset"]')->isChecked());
    $this->assertTrue($assert_session->elementExists('css', '[data-drupal-selector="edit-media-bundles-acquia-dam-pdf-asset"]')->isChecked());

    // Check the remaining media types.
    $assert_session->elementExists('css', '[data-drupal-selector="edit-media-bundles-acquia-dam-spinset-asset"]')->check();
    $assert_session->elementExists('css', '[data-drupal-selector="edit-media-bundles-acquia-dam-video-asset"]')->check();

    // Update existing checked values with new one.
    $assert_session->buttonExists('Update')->click();
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->buttonExists('Save')->click();

    // If source type and name is selected,
    // the filtering checkbox should be checked along with the media types.;
    $source_select_list->selectOption($source_type_name);
    $assert_session->assertWaitOnAjaxRequest();
    $asset_select_list->selectOption($name);
    $assert_session->assertWaitOnAjaxRequest();

    foreach ($expected_Selectors as $selector) {
      $this->assertTrue($assert_session->elementExists('css', $selector)->isChecked());
    }
  }

  /**
   * Data provider for testFormSubmit.
   */
  public static function categorySelectionProvider(): array {
    return [
      [
        'categories',
        'Categories',
        'Testing',
        [
          '[data-drupal-selector="edit-enable-filter"]',
          '[data-drupal-selector="edit-media-bundles-acquia-dam-documents-asset"]',
          '[data-drupal-selector="edit-media-bundles-acquia-dam-image-asset"]',
          '[data-drupal-selector="edit-media-bundles-acquia-dam-pdf-asset"]',
          '[data-drupal-selector="edit-media-bundles-acquia-dam-spinset-asset"]',
          '[data-drupal-selector="edit-media-bundles-acquia-dam-video-asset"]',
        ],
      ],
      [
        'asset_groups',
        'Asset Groups',
        'WidenQA',
        [
          '[data-drupal-selector="edit-enable-filter"]',
          '[data-drupal-selector="edit-media-bundles-acquia-dam-documents-asset"]',
          '[data-drupal-selector="edit-media-bundles-acquia-dam-image-asset"]',
          '[data-drupal-selector="edit-media-bundles-acquia-dam-pdf-asset"]',
          '[data-drupal-selector="edit-media-bundles-acquia-dam-spinset-asset"]',
          '[data-drupal-selector="edit-media-bundles-acquia-dam-video-asset"]',
        ],
      ],
    ];
  }

}
