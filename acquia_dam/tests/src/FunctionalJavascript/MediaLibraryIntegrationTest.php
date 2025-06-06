<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_dam\FunctionalJavascript;

use Drupal\acquia_dam\Entity\MediaExpiryDateField;
use Drupal\acquia_dam\Entity\MediaSourceField;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use Drupal\Tests\ckeditor5\Traits\CKEditor5TestTrait;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\Tests\TestFileCreationTrait;

// Workaround to support tests against both Drupal 10.1 and Drupal 11.0.
// @todo Remove once we depend on Drupal 10.2.
if (!trait_exists(EntityReferenceFieldCreationTrait::class)) {
  class_alias('\Drupal\Tests\field\Traits\EntityReferenceTestTrait', EntityReferenceFieldCreationTrait::class);
}

/**
 * Tests media library integration.
 *
 * Helpers copied from the media_library web driver test base class.
 *
 * @group acquia_dam
 *
 * @see \Drupal\Tests\media_library\FunctionalJavascript\MediaLibraryTestBase
 * @see \Drupal\Tests\media_library\FunctionalJavascript\CKEditorIntegrationTest
 */
class MediaLibraryIntegrationTest extends AcquiaDamWebDriverTestBase {

  use CKEditor5TestTrait;
  use TestFileCreationTrait;
  use MediaTypeCreationTrait;
  use EntityReferenceFieldCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    // Install dblog to assist with debugging.
    'dblog',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    // The test ::testWidgetUpload fails with z-index issues for the dialog with
    // starterkit_theme starting on 10.1.0-alpha1, unsure why.
    if ($this->toString() === 'testWidgetUpload') {
      $this->defaultTheme = 'starterkit_theme';
    }
    parent::setUp();
  }

  /**
   * Tests importing assets from the media library with CKEditor.
   *
   * @param string $asset_type
   *   The asset type.
   * @param string $asset_id
   *   The ID of the asset.
   * @param int $asset_count
   *   The count of assets.
   * @param string $format
   *   Display format of the asset.
   *
   * @dataProvider assetProvider
   */
  public function testEditorMediaLibrary(string $asset_type, string $asset_id, int $asset_count, string $format): void {
    $this->createAndLoginContentCreator();
    $this->grantSiteRegistrationToken();
    $this->grantDamDomain();
    $this->grantCurrentUserDamToken();
    $this->assertMediaEntityCount(0);

    $this->drupalGet('/node/add/page');
    $this->waitForEditor();
    $this->pressEditorButton('Insert Media');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $modal = $this->assertSession()->waitForElement('css', '#drupal-modal');
    // Verify our tabs as present.
    // Asserts ordering from DrupalMediaLibrary::getConfig().
    $page = $this->getSession()->getPage();
    $tabs = $page->findAll('css', '.media-library-menu__link');
    $expected_tab_order = [
      'Show Audio media (selected)',
      'Show Document media',
      'Show Generic media',
      'Show Image media',
      'Show PDF media',
      'Show Spinset media',
      'Show Video media',
    ];
    foreach ($tabs as $key => $tab) {
      $this->assertSame($expected_tab_order[$key], $tab->getText());
    }

    // Get the correct media library window.
    $modal->clickLink($asset_type);
    $this->assertSession()->assertWaitOnAjaxRequest();

    $media_elements = $modal->findAll('css', '.js-media-library-item');
    self::assertCount($asset_count, $media_elements);

    // Verify nothing imported, yet.
    $this->assertMediaEntityCount(0);

    $this->selectAndInsertAsset($asset_id, $asset_type, $format);

    // Verify it imported.
    $this->assertMediaEntityCount(1);
    $this->assertAssetImported($asset_id);

    // Verify it was not re-imported if selected again.
    $this->pressEditorButton('Insert Media');

    $this->selectAndInsertAsset($asset_id, $asset_type, $format);
    $this->assertMediaEntityCount(1);
  }

  /**
   * Data for the Asset test.
   */
  public static function assetProvider(): array {
    return [
      ['PDF', '0324b0b2-5293-4aa0-b0aa-c85b003395e2', 2, 'inline_view'],
      ['Video', 'efb03f75-3c42-497b-baa9-5ec79d1f56af', 1, 'inline_view'],
      ['Spinset', 'eec6d92b-6452-4ab6-894a-b4d0826e65ba', 1, 'inline_view'],
      ['Document',
        'abab96ac-c2ed-40b1-aaf7-56a52f898230',
        2,
        'link_text_download',
      ],
      ['Image', 'f2a9c03d-3664-477c-8013-e84504ed5adc', 2, 'large'],
    ];
  }

  /**
   * Test media library access.
   */
  public function testMediaLibraryAccess() {
    $this->createAndLoginContentCreator();

    $this->drupalGet('/node/add/page');

    $this->waitForEditor();
    $this->pressEditorButton('Insert Media');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->waitForElement('css', '#drupal-modal');
    $this->getSession()->getPage()->findLink('configure');

    $this->assertSession()->waitForText(
      'Site is not configured for Acquia DAM. Please configure it to browse assets.'
    );

    $this->getSession()->getPage()->pressButton('Close');
    $this->grantDamDomain();

    $this->pressEditorButton('Insert Media');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->assertSession()->waitForElement('css', '#drupal-modal');

    $this->assertSession()->waitForText(
      'To initialize the Acquia DAM module, you need to authenticate with a user that has permission to view & download assets that are applicable to your website.'
    );

    $this->assertSession()->linkExists('Connect');
    $this->assertSession()->linkExists('Skip');

    $this->getSession()->getPage()->pressButton('Close');
    $this->pressEditorButton('Insert Media');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->assertSession()->waitForElement('css', '#drupal-modal');

    $this->getSession()->getPage()->findLink('authenticate');
    $this->assertSession()->waitForText(
      'Site is not authenticated with Acquia DAM. Please authenticate it to browse assets. Once successfully authenticated, close this modal and reopen it to browse Acquia DAM assets.'
    );
    $this->getSession()->getPage()->pressButton('Close');

    $this->grantSiteRegistrationToken();
    $this->grantCurrentUserDamToken();

    $this->pressEditorButton('Insert Media');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $modal = $this->assertSession()->waitForElement('css', '#drupal-modal');
    $modal->clickLink('PDF');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $media_select_checkbox = $this->assertSession()->elementExists('css', "[value='0324b0b2-5293-4aa0-b0aa-c85b003395e2']", $modal);
    self::assertTrue(!empty($media_select_checkbox), 'Checks if checkbox exist.');
  }

  /**
   * Tests filtering the media library.
   *
   * @param string $asset_type
   *   The asset type.
   * @param int $asset_count
   *   The count of assets.
   * @param string $search_word
   *   The search word.
   * @param string $file_name
   *   The target file name.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Drupal\Core\Entity\EntityStorageException
   *
   * @dataProvider searchAssetProvider
   */
  public function testEditorMediaLibrarySearch(string $asset_type, int $asset_count, string $search_word, string $file_name): void {
    $this->createAndLoginContentCreator();
    $this->grantSiteRegistrationToken();
    $this->grantCurrentUserDamToken();

    $this->drupalGet('/node/add/page');
    $this->waitForEditor();
    $this->pressEditorButton('Insert Media');

    $modal = $this->assertSession()->waitForElement('css', '#drupal-modal');
    $modal->clickLink($asset_type);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $media_elements = $modal->findAll('css', '.js-media-library-item');
    self::assertCount($asset_count, $media_elements);

    // Type the name of an existing asset.
    $modal->fillField('Search', $search_word);
    $modal->pressButton('Apply');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $media_elements = $modal->findAll('css', '.js-media-library-item');
    self::assertCount(1, $media_elements);
    $this->assertSession()->pageTextContains($file_name);

    // Type an empty string which does not refresh the result list but leaves
    // it unchanged.
    $modal->fillField('Search', '');
    $modal->pressButton('Apply');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $media_elements = $modal->findAll('css', '.js-media-library-item');
    self::assertCount($asset_count, $media_elements);

    // Type some words yielding no results.
    $modal->fillField('Search', 'does not exist');
    $modal->pressButton('Apply');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('No result found for does not exist.');
    $media_elements = $modal->findAll('css', '.js-media-library-item');
    self::assertCount(0, $media_elements);

    // Type the name of an existing asset, again.
    $modal->fillField('Search', $search_word);
    $modal->pressButton('Apply');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $media_elements = $modal->findAll('css', '.js-media-library-item');
    self::assertCount(1, $media_elements);

    // Type the name of an existing asset, the third time.
    $modal->fillField('Search', $search_word);
    $modal->pressButton('Apply');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $modal->pressButton('Apply');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $media_elements = $modal->findAll('css', '.js-media-library-item');
    self::assertCount(1, $media_elements);
  }

  /**
   * Data for the Asset test.
   */
  public static function searchAssetProvider() {
    return [
      [
        'PDF',
        2,
        'Explorer',
        'Explorer owner\'s manual.pdf',
      ],
      [
        'Video',
        1,
        'SD-Social',
        'SD-Social Promo.mp4',
      ],
      [
        'Spinset',
        1,
        'eudaimonia_spin',
        'eudaimonia_spin.zip',
      ],
      [
        'Image',
        2,
        '422-lake-shore',
        '422-lake-shore-drive.webp',
      ],
      [
        'Document',
        2,
        'Best',
        'Best Practice - Content Architecture - v2.1.pptx',
      ],
    ];
  }

  /**
   * Tests that error messages are returned if there is a problem with search.
   *
   * @param string $search_word
   *   The search word.
   * @param string $expected_error_message
   *   The expected error message.
   *
   * @dataProvider searchErrorProvider
   */
  public function testEditorMediaLibrarySearchWithErrors(string $search_word, string $expected_error_message): void {
    $this->createAndLoginContentCreator();
    $this->grantSiteRegistrationToken();
    $this->grantCurrentUserDamToken();
    $this->drupalGet('/node/add/page');
    $this->waitForEditor();
    $this->pressEditorButton('Insert Media');

    $modal = $this->assertSession()->waitForElement('css', '#drupal-modal');
    $modal->clickLink('PDF');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $media_elements = $modal->findAll('css', '.js-media-library-item');
    self::assertCount(2, $media_elements);
    $modal->fillField('Search', $search_word);
    $modal->pressButton('Apply');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $media_elements = $modal->findAll('css', '.js-media-library-item');
    self::assertCount(0, $media_elements);
    $this->assertSession()->elementTextContains('css', '#drupal-modal', $expected_error_message);
  }

  /**
   * Data providers for search errors.
   *
   * @return array
   *   The test data.
   */
  public static function searchErrorProvider() {
    return [
      '4xx' => [
        '4xx_error',
        'Something went wrong with the request, the search could not be completed.',
      ],
      '5xx' => [
        '5xx_error',
        'Something went wrong contacting Acquia DAM, the search could not be completed.',
      ],
    ];
  }

  /**
   * Tests embed code for embedded assets.
   *
   * @param string $asset_id
   *   The asset ID.
   * @param string $asset_type
   *   The asset type.
   * @param string $embed_code
   *   The embed code to use.
   * @param string $expected_html
   *   The expected HTML.
   *
   * @dataProvider embedCodeData
   */
  public function testEditorEmbedCodesForm(string $asset_id, string $asset_type, string $embed_code, string $expected_html): void {
    $this->createAndLoginContentCreator();
    $this->grantSiteRegistrationToken();
    $this->grantCurrentUserDamToken();

    $this->drupalGet('/node/add/page');
    $this->getSession()->getPage()->fillField('Title', 'Embed Form test');

    $this->waitForEditor();
    $this->pressEditorButton('Insert Media');

    // Check the embedcode form.
    $this->selectAndInsertAsset($asset_id, $asset_type, $embed_code);

    // Switch back to editor to verify the preview.
    $media_preview_selector = '.ck-content .ck-widget.drupal-media .media';
    $embed = $this->assertSession()->waitForElementVisible('css', $media_preview_selector, 1000);
    self::assertNotNull($embed);
    self::assertStringContainsString($expected_html, $this->cleanHtmlMarkup($embed->getHtml()));
    // Save page and assert rendered output.
    $this->getSession()->switchToIFrame();
    $this->getSession()->getPage()->pressButton('Save');
    self::assertStringContainsString(
      $expected_html,
      $this->cleanHtmlMarkup($this->getSession()->getPage()->getHtml())
    );
  }

  /**
   * Tests embed code for embedded assets.
   *
   * @param string $asset_id
   *   The asset ID.
   * @param string $asset_type
   *   The asset type.
   * @param string $embed_code
   *   The embed code to use.
   * @param string $expected_html
   *   The expected HTML.
   *
   * @dataProvider embedCodeData
   */
  public function testEditorEmbedCodes(string $asset_id, string $asset_type, string $embed_code, string $expected_html): void {
    if (version_compare(\Drupal::VERSION, '10.3', '<')) {
      $expected_html = str_replace('webp', 'web', $expected_html);
    }

    // Default embed code needed by the select format.
    $default_embed_code = [
      'PDF' => 'inline_view',
      'Video' => 'inline_view',
      'Spinset' => 'link_text',
      'Image' => 'original',
      'Document' => 'link_thumbnail',
    ];
    $this->createAndLoginContentCreator();
    $this->grantSiteRegistrationToken();
    $this->grantCurrentUserDamToken();
    $this->drupalGet('/node/add/page');

    $this->getSession()->getPage()->fillField('Title', 'Embed test');

    $this->waitForEditor();
    $this->pressEditorButton('Insert Media');

    $embed_code = $embed_code ?? $default_embed_code[$asset_type];
    $this->selectAndInsertAsset($asset_id, $asset_type, $embed_code);

    // Save page and assert rendered output.
    $this->getSession()->switchToIFrame();
    $this->getSession()->getPage()->pressButton('Save');
    self::assertStringContainsString(
      $expected_html,
      $this->cleanHtmlMarkup($this->getSession()->getPage()->getHtml()),
    );
  }

  /**
   * Data for testing embed codes.
   *
   * @return \Generator
   *   The test data.
   */
  public static function embedCodeData(): \Generator {
    yield 'pdf: Inline viewer without download' => [
      '0324b0b2-5293-4aa0-b0aa-c85b003395e2',
      'PDF',
      'inline_view',
      '<iframe src="https://laser.widen.net/content/8a1ouvfchk/pdf/Explorer-owners-manual.pdf" title="Document for Explorer owner\'s manual.pdf" webkitallowfullscreen="" mozallowfullscreen="" allowfullscreen=""></iframe>',
    ];
    yield 'pdf: Text linked to viewer with Download' => [
      '0324b0b2-5293-4aa0-b0aa-c85b003395e2',
      'PDF',
      'link_text_download',
      '<a href="https://laser.widen.net/view/pdf/8a1ouvfchk/Explorer-owners-manual.pdf?t.download=true" target="_blank" title="Download the file of asset &quot;Explorer owner\'s manual.pdf&quot;">Explorer owner\'s manual.pdf</a>',
    ];
    yield 'pdf: Thumbnail linked to viewer with download' => [
      '0324b0b2-5293-4aa0-b0aa-c85b003395e2',
      'PDF',
      'link_thumbnail_download',
      '<a href="https://laser.widen.net/view/pdf/8a1ouvfchk/Explorer-owners-manual.pdf?t.download=true" target="_blank"><img src="https://laser.widen.net/content/8a1ouvfchk/jpeg/Explorer-owners-manual.jpg" width="300" height="300" alt="Explorer owner\'s manual.pdf preview" loading="lazy">',
    ];
    yield 'video: Inline player with download' => [
      'efb03f75-3c42-497b-baa9-5ec79d1f56af',
      'Video',
      'inline_view_download',
      '<iframe src="https://laser.widen.net/view/video/mnmc58hipn/SD-Social-Promo.mp4?t.download=true" webkitallowfullscreen="" mozallowfullscreen="" allowfullscreen="" frameborder="0" allowtransparency="true" scrolling="no" style="position:absolute;top:0;left:0;width:100%;height:100%;"></iframe>',
    ];
    yield 'Spinset: Text linked to viewer without Download' => [
      'eec6d92b-6452-4ab6-894a-b4d0826e65ba',
      'Spinset',
      'link_text',
      '<a href="https://laser.widen.net/view/spinset/yjezd5iwh5/eudaimonia_spin.zip" target="_blank" title="Download the file of asset &quot;eudaimonia_spin.zip&quot;">eudaimonia_spin.zip</a>',
    ];
    // Displaying image assets as original works different which current test case does not support to check.
    // yield 'Image: Original' => [
    //   'f2a9c03d-3664-477c-8013-e84504ed5adc',
    //   'Image',
    //   'original',
    //   '<img width="150" src="https://embed.widencdn.net/img/test/m2x3z2j9ou/150px@2x/?q=75&amp;7583" alt="An asset with many versions.png">',
    // ];
    yield 'Image: Image style applied' => [
      'f2a9c03d-3664-477c-8013-e84504ed5adc',
      'Image',
      'media_library',
      '<img src="https://laser.widen.net/content/e43bde3a-be80-418e-a69e-6de9285afbbf/web/An%20asset%20with%20many%20versions.png?w=220" width="220" height="124" alt="An asset with many versions.png" loading="lazy" class="image-style-media-library">',
    ];
    yield 'documents: Text linked to viewer with Download' => [
      'abab96ac-c2ed-40b1-aaf7-56a52f898230',
      'Document',
      'link_text_download',
      '<a href="https://laser.widen.net/view/pdf/rfnwimkigc/Best-Practice---Content-Architecture---v2.1.pptx?t.download=true" target="_blank" title="Download the file of asset &quot;Best Practice - Content Architecture - v2.1.pptx&quot;">Best Practice - Content Architecture - v2.1.pptx</a>',
    ];
  }

  /**
   * Tests source menu for the media_library.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  public function testSourceMenu(): void {
    $this->createAndLoginContentCreator();
    $this->grantSiteRegistrationToken();
    $this->grantCurrentUserDamToken();
    $this->createMediaType('image');
    $this->createMediaType('file');
    $this->drupalGet('/node/add/page');

    $this->waitForEditor();
    $this->pressEditorButton('Insert Media');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->assertSession()->waitForElement('css', '#drupal-modal');

    $this->assertSession()->pageTextContains('Select Media Source');
    $this->assertSession()->pageTextContains('To begin searching for media, select a source.');
    $this->assertSession()->pageTextContains('Your selection saves as your default choice. You can change your source anytime from the dropdown in the upper left of this module.');

    $field = current($this->getSession()->getPage()->findAll('css', '.js-acquia-dam-source-field'));
    $field->selectOption('acquia_dam', FALSE);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $modal = $this->assertSession()->waitForElement('css', '#drupal-modal');

    $media_elements = $modal->findAll('css', '.media-library-menu__link');
    self::assertCount(7, $media_elements);

    $field = current($this->getSession()->getPage()->findAll('css', '.js-acquia-dam-source-field'));
    $field->selectOption('core', FALSE);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $modal = $this->assertSession()->waitForElement('css', '#drupal-modal');

    $media_elements = $modal->findAll('css', '.media-library-menu__link');
    self::assertCount(2, $media_elements);

  }

  /**
   * Test to check if no items are selected in media library leads to error.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testNoItemSelected(): void {
    $this->createAndLoginContentCreator();
    $this->grantSiteRegistrationToken();
    $this->grantCurrentUserDamToken();

    $this->drupalGet('/node/add/page');
    $this->waitForEditor();
    $this->pressEditorButton('Insert Media');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $modal = $this->assertSession()->waitForElement('css', '#drupal-modal');
    $modal->clickLink('PDF');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->pressDialogButton('Next: Select Format');
    $this->assertSession()->pageTextContains('No items selected.');
  }

  /**
   * Test to check if error on saving media item shows error message.
   */
  public function testErrorShownIfMediaNoImported(): void {
    $this->createAndLoginContentCreator();
    $this->grantSiteRegistrationToken();
    $this->grantCurrentUserDamToken();

    $this->drupalGet('/node/add/page');
    $this->waitForEditor();
    $this->pressEditorButton('Insert Media');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $modal = $this->assertSession()->waitForElement('css', '#drupal-modal');
    $modal->clickLink('PDF');

    $this->assertSession()->assertWaitOnAjaxRequest();
    $media_select_checkbox = $this->assertSession()->elementExists('css', "[value='0324b0b2-5293-4aa0-b0aa-c85b003395e2']", $modal);
    $media_select_checkbox->check();
    // Modify the selected value in hidden input.
    $this->getSession()->executeScript(<<<JS
     document.getElementById('media-library-modal-selection').type = 'text'
     document.getElementById('media-library-modal-selection').value = 'c2bbed58-427f-43f7-91d8-c380307dac67'
     JS);
    $this->pressDialogButton('Next: Select Format');
    $this->assertSession()->pageTextContains('There was an error selecting the asset.');
  }

  /**
   * Tests that media library has the links to change layout.
   */
  public function testMediaLibraryLayoutLinks(): void {
    $this->createAndLoginContentCreator();
    $this->grantSiteRegistrationToken();
    $this->grantCurrentUserDamToken();

    $this->drupalGet('/node/add/page');
    $this->waitForEditor();
    $this->pressEditorButton('Insert Media');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $modal = $this->assertSession()->waitForElement('css', '#drupal-modal');
    $modal->clickLink('PDF');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $modal->hasLink('Grid');
    $modal->hasLink('Table');
  }

  /**
   * Tests if a DAM asset is insert its expiry date will be saved in DB.
   */
  public function testEditorExpiryDateSave(): void {
    $this->createAndLoginContentCreator();
    $this->grantSiteRegistrationToken();
    $this->grantCurrentUserDamToken();
    $this->drupalGet('/node/add/page');

    $this->getSession()->getPage()->fillField('Title', 'Embed test');

    $this->waitForEditor();
    $this->pressEditorButton('Insert Media');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->selectAndInsertAsset('0324b0b2-5293-4aa0-b0aa-c85b003395e2', 'PDF', 'inline_view');
    $media = $this->assertAssetImported('0324b0b2-5293-4aa0-b0aa-c85b003395e2');
    $expiry_date = $media->get(MediaExpiryDateField::EXPIRY_DATE_FIELD_NAME)->value;
    self::assertEquals("2039513839", $expiry_date);
  }

  /**
   * Tests adding a metadata field filter.
   *
   * @param string $filter_name
   *   The filter id defined in the view.
   * @param string $display_key
   *   The disply key value from the viewable api.
   * @param string $filter_type
   *   The type of the filter.
   * @param string $locator
   *   HTML field name.
   * @param string $filter_value
   *   Search value.
   *
   * @dataProvider viewFilterData
   */
  public function testAssetMetaFilter(string $filter_name, string $display_key, string $filter_type, string $locator, string $filter_value) {
    \Drupal::getContainer()->get('module_installer')->install(['views_ui']);

    $this->grantSiteRegistrationToken();
    $user = $this->drupalCreateUser([
      'administer views',
      'use text format test_format',
      'access media overview',
      'create page content',
    ]);
    $this->drupalLogin($user);
    $this->grantCurrentUserDamToken();

    // Disable automatic live preview to make the sequence of calls clearer.
    $this->config('views.settings')->set('ui.always_live_preview', FALSE)->save();

    $web_assert = $this->assertSession();

    $this->drupalGet(Url::fromRoute('entity.view.edit_form', ['view' => 'acquia_dam_asset_library']));

    $page = $this->getSession()->getPage();
    // Open the 'Add filter dialog'.
    $page->clickLink('views-add-filter');
    $web_assert->waitForField('override[controls][group]');
    $page->checkField("name[$filter_name]");
    $page->find('css', '.ui-dialog .ui-dialog-buttonpane')->pressButton('Apply (all displays)');
    $web_assert->waitForText('Add and configure filter criteria');
    $web_assert->waitForField('options[expose_button][checkbox][checkbox]');
    $page->findField('options[expose_button][checkbox][checkbox]')->click();
    $web_assert->waitForField('options[expose][label]');
    $page->selectFieldOption('options[display_key]', $display_key);

    $page->find('css', '.ui-dialog .ui-dialog-buttonpane')->pressButton('Apply');
    $web_assert->waitForText('DAM Assets: Metadata: ' . $filter_type . ' (exposed)');
    $web_assert->responseContains('DAM Assets: Metadata: ' . $filter_type . ' (exposed)');

    $page->find('css', '#edit-actions-submit')->click();
    $web_assert->waitForText('The view Acquia DAM Asset Library has been saved.');
    $this->assertSession()->pageTextContains('The view Acquia DAM Asset Library has been saved.');
    $this->drupalGet('/node/add/page');
    $this->waitForEditor();
    $this->pressEditorButton('Insert Media');

    $modal = $this->assertSession()->waitForElement('css', '#drupal-modal');
    $modal->clickLink('PDF');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Metadata: ' . $filter_type);
    $media_elements = $modal->findAll('css', '.js-media-library-item');
    self::assertCount(2, $media_elements);
    if ($filter_type === 'Selection list') {
      $modal->selectFieldOption($locator, $filter_value);
    }
    elseif ($filter_type === 'Text data') {
      $modal->fillField($locator, $filter_value);

    }

    $modal->pressButton('Apply');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $media_elements = $modal->findAll('css', '.js-media-library-item');
    self::assertCount(1, $media_elements);
  }

  /**
   * Data for testing different filters.
   *
   * @return \Generator
   *   The test data.
   */
  public static function viewFilterData(): \Generator {
    yield 'metadata:string' => [
      'acquia_dam_assets.metadata_text',
      'keywords',
      'Text data',
      'metadata_text',
      'bicycle',
    ];
    yield 'metadata:selected_list' => [
      'acquia_dam_assets.metadata_selection_list',
      'assettype',
      'Selection list',
      'metadata_selection_list',
      'Document',
    ];
  }

  /**
   * Tests adding assets through field embed of media library.
   *
   * @param string $asset_type
   *   The asset type.
   * @param string $asset_id
   *   The asset ID.
   * @param string $expected_html
   *   The expected HTML.
   *
   * @dataProvider fieldAssetProvider
   */
  public function testFieldEmbedMediaLibrary(string $asset_type, string $asset_id, string $expected_html) {
    $this->createMediaReferenceField();
    $this->createAndLoginContentCreator();
    $this->grantSiteRegistrationToken();
    $this->grantCurrentUserDamToken();
    $this->drupalGet('/node/add/page');
    $this->getSession()->getPage()->fillField('Title', 'Embed test');
    $wrapper = $this->assertSession()->elementExists('css', '#media_field-media-library-wrapper');

    $wrapper->pressButton('Add media');
    $this->assertNotNull($this->assertSession()->waitForText('Add or select media'));
    $this->selectAndInsertAsset($asset_id, $asset_type, '', FALSE);
    $wrapper->pressButton('Add media');
    $this->assertNotNull($this->assertSession()->waitForText('Add or select media'));
    $this->selectAndInsertAsset($asset_id, $asset_type, '', FALSE);

    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->waitForText('Embed test');
    self::assertStringContainsString(
      $expected_html,
      $this->getSession()->getPage()->getHtml()
    );
    $field_items = $this->getSession()->getPage()->findAll('css', '.field--name-acquia-dam-asset-id');
    self::assertCount(2, $field_items);
  }

  /**
   * Data for testing assets on media field.
   *
   * @return array
   *   The test data.
   */
  public static function fieldAssetProvider(): array {
    return [
      [
        'PDF',
        '0324b0b2-5293-4aa0-b0aa-c85b003395e2',
        '<a href="https://laser.widen.net/view/pdf/8a1ouvfchk/Explorer-owners-manual.pdf?t.download=true" target="_blank"><img src="https://laser.widen.net/content/8a1ouvfchk/jpeg/Explorer-owners-manual.jpg" width="300" height="300" alt="Explorer owner\'s manual.pdf preview" loading="lazy">',
      ],
      [
        'Video',
        'efb03f75-3c42-497b-baa9-5ec79d1f56af',
        '<iframe src="https://laser.widen.net/view/video/mnmc58hipn/SD-Social-Promo.mp4" webkitallowfullscreen="" mozallowfullscreen="" allowfullscreen="" frameborder="0" allowtransparency="true" scrolling="no" style="position:absolute;top:0;left:0;width:100%;height:100%;"></iframe>',
      ],
      [
        'Spinset',
        'eec6d92b-6452-4ab6-894a-b4d0826e65ba',
        '<a href="https://laser.widen.net/view/spinset/yjezd5iwh5/eudaimonia_spin.zip" target="_blank" title="Download the file of asset &quot;eudaimonia_spin.zip&quot;">eudaimonia_spin.zip</a>',
      ],
      [
        'Image',
        'f2a9c03d-3664-477c-8013-e84504ed5adc',
        '<img src="https://laser.widen.net/content/e43bde3a-be80-418e-a69e-6de9285afbbf/web/An%20asset%20with%20many%20versions.png" width="1920" height="1080" alt="An asset with many versions.png" loading="lazy">',
      ],
      [
        'Document',
        'abab96ac-c2ed-40b1-aaf7-56a52f898230',
        '<a href="https://laser.widen.net/content/rfnwimkigc/original/Best-Practice---Content-Architecture---v2.1.pptx?download=true" target="_blank" title="Download the file of asset &quot;Best Practice - Content Architecture - v2.1.pptx&quot;">Best Practice - Content Architecture - v2.1.pptx</a>',
      ],
    ];
  }

  /**
   * Ajax fails if when core asset selected and source is changed.
   */
  public function testMultipleAssetSelectionCoreFirst() {
    $this->createMediaType('image', ['id' => 'image']);
    File::create([
      'uri' => $this->getTestFiles('image')[0]->uri,
    ])->save();
    $image_target_id = 1;
    $media_image = Media::create([
      'bundle' => 'image',
      'name' => 'Screaming hairy armadillo',
      'field_media_image' => [
        [
          'target_id' => $image_target_id,
          'alt' => 'default alt',
          'title' => 'default title',
        ],
      ],
    ]);
    $media_image->save();

    $this->createMediaReferenceField();
    $this->createAndLoginContentCreator();
    $this->grantSiteRegistrationToken();
    $this->grantCurrentUserDamToken();
    $this->drupalGet('/node/add/page');
    $this->getSession()->getPage()->fillField('Title', 'Embed test');
    $wrapper = $this->assertSession()->elementExists('css', '#media_field-media-library-wrapper');
    // Core first.
    $wrapper->pressButton('Add media');
    $this->assertNotNull($this->assertSession()->waitForText('Add or select media'));
    $field = current($this->getSession()->getPage()->findAll('css', '.js-acquia-dam-source-field'));
    $field->selectOption('core', FALSE);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $modal = $this->assertSession()->waitForElement('css', '#drupal-modal');
    $media_select_checkbox = $this->assertSession()->elementExists('css', "[value='$image_target_id']", $modal);
    $media_select_checkbox->check();
    $field->selectOption('acquia_dam', FALSE);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $modal = $this->assertSession()->waitForElement('css', '#drupal-modal');
    $modal->clickLink('PDF');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $media_select_checkbox = $this->assertSession()->elementExists('css', "[value='0324b0b2-5293-4aa0-b0aa-c85b003395e2']", $modal);
    $media_select_checkbox->check();
    $this->pressDialogButton('Insert selected');
    $field_items = $this->getSession()->getPage()->findAll('css', '.js-media-library-item');
    self::assertCount(2, $field_items);
    // Acquia dam first.
    $wrapper->pressButton('Add media');
    $this->assertNotNull($this->assertSession()->waitForText('Add or select media'));
    $field = current($this->getSession()->getPage()->findAll('css', '.js-acquia-dam-source-field'));
    $field->selectOption('acquia_dam', FALSE);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $modal = $this->assertSession()->waitForElement('css', '#drupal-modal');
    $modal->clickLink('PDF');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $media_select_checkbox = $this->assertSession()->elementExists('css', "[value='0324b0b2-5293-4aa0-b0aa-c85b003395e2']", $modal);
    $media_select_checkbox->check();
    $field->selectOption('core', FALSE);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $modal = $this->assertSession()->waitForElement('css', '#drupal-modal');
    $media_select_checkbox = $this->assertSession()->elementExists('css', "[value='$image_target_id']", $modal);
    $media_select_checkbox->check();
    $this->pressDialogButton('Insert selected');
    $field_items = $this->getSession()->getPage()->findAll('css', '.js-media-library-item');
    self::assertCount(4, $field_items);
  }

  /**
   * Tests to check when media asset added on a table layout.
   */
  public function testMediaLibraryTableLayoutLinks(): void {
    $this->createMediaType('image', ['id' => 'image']);

    $this->createAndLoginContentCreator();
    $this->grantSiteRegistrationToken();
    $this->grantCurrentUserDamToken();

    $this->drupalGet('/node/add/page');
    $this->waitForEditor();
    $this->pressEditorButton('Insert Media');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $modal = $this->assertSession()->waitForElement('css', '#drupal-modal');
    $field = current($this->getSession()->getPage()->findAll('css', '.js-acquia-dam-source-field'));
    $field->selectOption('acquia_dam', FALSE);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $modal->clickLink('PDF');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $modal->clickLink('Table');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->selectAndInsertAsset('0324b0b2-5293-4aa0-b0aa-c85b003395e2', 'PDF', 'inline_view');
    $this->assertMediaEntityCount(1);
    $this->assertAssetImported('0324b0b2-5293-4aa0-b0aa-c85b003395e2');
  }

  /**
   * Tests that uploads in the Media library won't affect the source menu.
   */
  public function testWidgetUpload() {
    $assert_session = $this->assertSession();

    $this->createMediaReferenceField();
    $user = $this->drupalCreateUser([
      'access administration pages',
      'access content',
      'administer media',
      'create media',
      'view media',
      'use text format test_format',
      'access media overview',
      'create page content',
    ]);
    $this->drupalLogin($user);
    $this->grantSiteRegistrationToken();
    $this->grantCurrentUserDamToken();
    $this->createMediaType('image', ['id' => 'image', 'label' => 'Image']);
    $this->createMediaType('file', ['id' => 'file', 'label' => 'File']);

    // Visit a node create page and open the media library.
    $this->drupalGet('node/add/page');
    $wrapper = $this->assertSession()->elementExists('css', '#media_field-media-library-wrapper');
    $wrapper->pressButton('Add media');
    $this->assertNotNull($this->assertSession()->waitForText('Select Media Source'));

    $field = current($this->getSession()->getPage()->findAll('css', '.js-acquia-dam-source-field'));
    $field->selectOption('core', FALSE);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $modal = $this->assertSession()->waitForElement('css', '#drupal-modal');

    // Assert the upload form is now visible for default tab type_three.
    $modal->clickLink('Image');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $assert_session->elementExists('css', '.js-media-library-add-form');
    $assert_session->fieldExists('Add files');
    $page = $this->getSession()->getPage();
    $image_file = current($this->getTestFiles('image'));
    $image_path = $this->container->get('file_system')->realpath($image_file->uri);
    $page->attachFileToField('Add files', $image_path);
    $selector = '.js-media-library-add-form-added-media';
    // Assert that focus is shifted to the new media items.
    $this->assertJsCondition('jQuery("' . $selector . '").is(":focus")');

    $assert_session = $this->assertSession();
    $assert_session->pageTextMatches('/The media items? ha(s|ve) been created but ha(s|ve) not yet been saved. Fill in any required fields and save to add (it|them) to the media library./');
    $assert_session->elementAttributeContains('css', $selector, 'aria-label', 'Added media items');

    $assert_session->elementNotExists('css', '.js-media-library-menu');
    $page->fillField('Alternative text', $this->randomString());
    $this->pressDialogButton('Save');
    $this->assertSession()->elementExists('css', '.js-acquia-dam-source-field');
    $field->selectOption('acquia_dam', FALSE);
    $this->assertSession()->assertWaitOnAjaxRequest();
  }

  /**
   * Tests the media_library/click_to_select library is applied.
   */
  public function testClickToSelectTrigger(): void {
    $this->createAndLoginContentCreator();
    $this->grantSiteRegistrationToken();
    $this->grantCurrentUserDamToken();

    $this->drupalGet('/node/add/page');
    $this->waitForEditor();
    $this->pressEditorButton('Insert Media');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $modal = $this->assertSession()->waitForElement('css', '#drupal-modal');
    $modal->clickLink('PDF');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $media_items = $modal->findAll('css', '.js-click-to-select-trigger');
    self::assertTrue(count($media_items) > 0);
    $first_media_item = $media_items[0];
    $first_media_item->click();
    $media_item_checkbox = $first_media_item->getParent()->find('css', 'input[type="checkbox"]');
    self::assertTrue($media_item_checkbox->isChecked());
  }

  /**
   * Tests resetting the media library filter.
   */
  public function testEditorMediaLibraryFilterReset(): void {
    $this->createAndLoginContentCreator();
    $this->grantSiteRegistrationToken();
    $this->grantCurrentUserDamToken();

    $this->drupalGet('/node/add/page');
    $this->waitForEditor();
    $this->pressEditorButton('Insert Media');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $modal = $this->assertSession()->waitForElement('css', '#drupal-modal');
    $modal->clickLink('PDF');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $media_elements = $modal->findAll('css', '.js-media-library-item');
    self::assertCount(2, $media_elements);
    $modal->fillField('Search', 'Explorer');
    $modal->pressButton('Apply');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $media_elements = $modal->findAll('css', '.js-media-library-item');
    self::assertCount(1, $media_elements);
    $modal->clickLink('Clear Filter');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $media_elements = $modal->findAll('css', '.js-media-library-item');
    self::assertCount(2, $media_elements);
    self::assertEquals($modal->findField('Search')->getValue(), '');
  }

  /**
   * Tests to check the image assets embed code is image style.
   *
   * @param array $image_styles
   *   Image style ids to save for config.
   * @param int $count
   *   Expected count of image styles displayed.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Drupal\Core\Entity\EntityStorageException
   *
   * @dataProvider providerImageStyle
   */
  public function testMediaLibraryImageAssetEmbedCode(array $image_styles, int $count) {
    $this->createAndLoginContentCreator();
    $this->grantSiteRegistrationToken();
    $this->grantCurrentUserDamToken();

    $this->config('acquia_dam.settings')
      ->set('allowed_image_styles', $image_styles)
      ->save();

    $this->drupalGet('/node/add/page');
    $this->getSession()->getPage()->fillField('Title', 'Embed test');

    $this->waitForEditor();
    $this->pressEditorButton('Insert Media');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $modal = $this->assertSession()->waitForElement('css', '#drupal-modal');
    $modal->clickLink('Image');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $media_select_checkbox = $this->assertSession()->elementExists('css', "[value='f2a9c03d-3664-477c-8013-e84504ed5adc']", $modal);
    $media_select_checkbox->check();

    $this->pressDialogButton('Next: Select Format');
    $this->assertSession()->pageTextContains('Choose a format for your media');

    $embedcode_fieldset = $this->assertSession()->waitForElement('css', '.embed-select-form');
    $embed_fieldset_count = $embedcode_fieldset->findAll('css', '.js-form-item-embed-code');
    self::assertCount($count, $embed_fieldset_count);
    $image_style_helper = $this->container->get('acquia_dam.image_style_support');
    $image_styles = $image_style_helper->getAllowedImageStyles();
    $session = $this->assertSession();
    foreach ($image_styles as $style) {
      $session->pageTextContains($style->label());
    }
  }

  /**
   * Tests to check the image assets embed code is image style.
   */
  public function testMediaLibraryImageAssetVersioning() {
    $media_preview_selector = '.ck-content .ck-widget.drupal-media .media';
    $this->createAndLoginContentCreator();
    $this->grantSiteRegistrationToken();
    $this->grantCurrentUserDamToken();
    $this->drupalGet('/node/add/page');
    $this->getSession()->getPage()->fillField('Title', 'Embed test');

    $this->waitForEditor();
    $this->pressEditorButton('Insert Media');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $modal = $this->assertSession()->waitForElement('css', '#drupal-modal');
    $modal->clickLink('Image');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $media_select_checkbox = $this->assertSession()->elementExists('css', "[value='f2a9c03d-3664-477c-8013-e84504ed5adc']", $modal);
    $media_select_checkbox->check();

    $this->pressDialogButton('Next: Select Format');
    $this->assertSession()->pageTextContains('Select to automatically update media as versions are added in DAM or manually update when you know a new version is availabe in the DAM.');

    $media_select_checkbox = $this->assertSession()->elementExists('css', "[value='manual']", $modal);
    $media_select_checkbox->click();
    $this->pressDialogButton('Insert selected');

    $this->assertNotEmpty($this->assertSession()->waitForElementVisible('css', $media_preview_selector, 1000));
    $xpath = new \DOMXPath($this->getEditorDataAsDom());
    $embed = $xpath->query('//drupal-media')[0];
    self::assertNotNull($embed);
    self::assertTrue($embed->hasAttribute('data-entity-revision'));

    $media = $this->container
      ->get('entity.repository')
      ->loadEntityByUuid('media', $embed->getAttribute('data-entity-uuid'));
    self::assertInstanceOf(MediaInterface::class, $media);
    self::assertEquals($media->getRevisionId(), $embed->getAttribute('data-entity-revision'));
  }

  /**
   * Tests pager on asset library.
   */
  public function testMediaLibraryPager() {
    $this->createAndLoginContentCreator();
    $this->grantSiteRegistrationToken();
    $this->grantCurrentUserDamToken();
    $this->drupalGet('/node/add/page');

    $this->waitForEditor();
    $this->pressEditorButton('Insert Media');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->assertSession()->waitForElement('css', '#drupal-modal');

    // 'Audio' is the first tab active by default.
    $this->assertSession()->pageTextContains('Displaying 1 - 1 of 1');

    $modal = $this->assertSession()->waitForElement('css', '#drupal-modal');
    $modal->clickLink('Document');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Displaying 1 - 2 of 2');

    $modal->clickLink('Image');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Displaying 1 - 2 of 2');

    $this->assertSession()->linkNotExists('Go to page 2');
  }

  /**
   * Asserts the number of media entities that exist.
   *
   * @param int $expected_count
   *   The expected count.
   */
  private function assertMediaEntityCount(int $expected_count): void {
    $count = $this->container->get('entity_type.manager')
      ->getStorage('media')
      ->getQuery()
      ->accessCheck(FALSE)
      ->count()
      ->execute();
    self::assertEquals($expected_count, $count);
  }

  /**
   * Assets that an asset has been imported, and returns its media entity.
   *
   * @param string $asset_id
   *   The asset ID.
   *
   * @return \Drupal\media\MediaInterface
   *   The media entity.
   */
  private function assertAssetImported(string $asset_id): MediaInterface {
    $media_storage = $this->container->get('entity_type.manager')
      ->getStorage('media');
    $ids = $media_storage
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition(MediaSourceField::SOURCE_FIELD_NAME . '.asset_id', $asset_id)
      ->execute();
    self::assertCount(1, $ids);
    $entities = $media_storage->loadMultiple($ids);
    $media = reset($entities);
    // The previous assertCount ensures this `assert` passes.
    assert($media instanceof MediaInterface);

    return $media;
  }

  /**
   * Image style provider.
   */
  public static function providerImageStyle(): array {
    return [
      // Having zero image style available is quite unlikely to happen: the
      // 'media_library' (220px square) should always exist in this scenario.
      [
        [],
        1,
      ],
      // Always having two more in extra:
      // 'original' + 'remotely-referenced-thumbnail-image'.
      [
        [
          'large',
        ],
        3,
      ],
      [
        [
          'large',
          'medium',
        ],
        4,
      ],
      [
        [
          'wide',
          'large',
          'medium',
        ],
        5,
      ],
    ];
  }

  /**
   * Removes any additional spaces and new lines from a HTML markup.
   *
   * @param string $html
   *   Html markup.
   *
   * @return string
   *   Trimmed html markup.
   */
  private function cleanHtmlMarkup(string $html) {
    return preg_replace("/[\r\n]*/", "", $html);
  }

}
