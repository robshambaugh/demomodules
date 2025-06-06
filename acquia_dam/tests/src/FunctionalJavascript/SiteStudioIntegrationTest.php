<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_dam\FunctionalJavascript;

use Drupal\cohesion\Drush\DX8CommandHelpers;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Url;

/**
 * Tests integration with Acquia Site Studio.
 *
 * @group acquia_dam
 * @requires module cohesion
 */
final class SiteStudioIntegrationTest extends AcquiaDamWebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $configSchemaCheckerExclusions = [
    'cohesion.settings',
    'core.entity_view_display.node.site_studio.default',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'cohesion_theme';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'path',
    'text',
    'node',
    'views',
    'cohesion',
    'cohesion_templates',
    'cohesion_elements',
    'media_library',
    // Undocumented dependencies.
    'cohesion_base_styles',
    'cohesion_custom_styles',
    'cohesion_website_settings',
    // Install dblog to assist with debugging.
    'dblog',
  ];

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $log = \Drupal::database()
      ->select('watchdog', 'w')
      ->fields('w')
      ->condition('severity', RfcLogLevel::ERROR, '<=')
      ->execute()
      ->fetchAll();
    if (count($log) > 0) {
      var_export($log);
    }
    parent::tearDown();
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    if (empty(getenv('SITESTUDIO_API_KEY')) || empty(getenv('SITESTUDIO_ORG_KEY'))) {
      $this->markTestSkipped('Cannot test without Site Studio key.');
    }

    parent::setUp();

    \Drupal::configFactory()->getEditable('cohesion.settings')
      ->set('api_key', getenv('SITESTUDIO_API_KEY'))
      ->set('organization_key', getenv('SITESTUDIO_ORG_KEY'))
      ->set('image_browser', [
        'config' => [
          'type' => 'medialib_imagebrowser',
          'dx8_entity_browser' => 'media_browser',
          'cohesion_media_lib_types' => [],
        ],
        'content' => [
          'type' => 'medialib_imagebrowser',
          'dx8_entity_browser' => 'media_browser',
          'cohesion_media_lib_types' => [],
        ],
      ])
      ->save(TRUE);

    $errors = DX8CommandHelpers::import();
    self::assertFalse($errors);
  }

  /**
   * Tests creating a PDF component for Site Studio.
   *
   * @param string $asset_type
   *   Type of the asset.
   * @param string $asset_id
   *   ID of the asset.
   * @param string $asset_name
   *   Name of the asset.
   *
   * @dataProvider assetList
   */
  public function testCreateComponent(string $asset_type, string $asset_id, string $asset_name): void {
    $this->grantSiteRegistrationToken();
    $this->createSiteStudioAccessUser();
    $this->grantCurrentUserDamToken();

    $this->drupalGet(Url::fromRoute('entity.cohesion_component.add_form'));

    // Wait for machine-name.js to append its elements.
    $this->getSession()->getPage()->fillField('label', "$asset_type Component");
    $this->assertSession()->waitForElementVisible('css', '#edit-label-machine-name-suffix .admin-link');
    $this->getSession()->getPage()->selectFieldOption('Category', 'cpt_cat_category');

    $this->getSession()->getPage()->find('css', 'button[aria-label="Add form fields"]')->click();
    $this->assertSession()->waitForElement('css', '#ssa-sidebar-browser');
    // Wait for sidebar to finish loading.
    $this->assertSession()->waitForElementRemoved('css', '#ssa-sidebar-browser dev[role="status"]');

    $this->getSession()->getPage()
      ->find('css', 'li[data-ssa-name="Entity browser"]')
      ->find('css', 'button[aria-label="Add to canvas"]')
      ->click();
    $this->getSession()->getPage()
      ->find('css', 'ol[data-canvas-key="componentForm"]')
      ->find('css', 'li[data-type="Entity browser"]')
      ->doubleClick();
    $this->assertSession()->waitForElement('css', '.ssa-modal-sidebar-editor');
    $this->assertSession()->waitForElement('css', 'iframe[title="Edit component"]');
    $this->getSession()->executeScript(<<<JS
     document.querySelector('iframe[title="Edit component"]').name = 'edit_component'
     JS);
    $this->getSession()->switchToIFrame('edit_component');
    $this->assertSession()->waitForText('Entity browser type');
    $this->getSession()->getPage()->selectFieldOption('Entity browser type', 'Media library');
    $this->getSession()->getPage()
      ->find('css', 'coh-checkbox-toggle[model="model[\'model\'][\'value\'][\'bundles\'][\'acquia_dam_' . strtolower($asset_type) . '_asset\']"]')
      ->find('css', 'span.checkbox-toggle-off')
      ->click();
    $this->getSession()->getPage()->pressButton('Apply');

    $this->getSession()->switchToIFrame();
    $this->assertSession()->waitForElementRemoved('css', 'iframe[title="Edit component"]');
    $component_entity_browser = $this->assertSession()->waitForElement('css', 'coh-entity-browser');
    $this->assertSession()->waitForElement('css', '.coh-file-browser-button');
    $component_entity_browser->pressButton('Browse');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->assertSession()->waitForElement('css', 'iframe[title="Entity browser"]');
    $this->getSession()->executeScript(<<<JS
document.querySelector('iframe[title="Entity browser"]').name = 'entity_browser'
JS);
    $this->getSession()->switchToIFrame('entity_browser');

    $this->assertSession()->waitForElement('css', '#media-library-wrapper');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $media_select_checkbox = $this->assertSession()->elementExists('css', '[value="' . $asset_id . '"]');

    $media_select_checkbox->check();
    $this->getSession()->getPage()->pressButton('Insert selected');
    $this->getSession()->switchToIFrame();
    $this->assertSession()->waitForElementRemoved('css', 'iframe[title="Entity browser"]');
    $this->assertSession()->waitForElement('css', 'img[alt="' . $asset_name . '"]');

    $this->getSession()->getPage()->find('css', 'button[aria-label="Add content"]')->click();
    $this->assertSession()->waitForElement('css', '#ssa-sidebar-browser');
    // Wait for sidebar to finish loading.
    $this->assertSession()->waitForElementRemoved('css', '#ssa-sidebar-browser dev[role="status"]');
    $this->getSession()->getPage()
      ->find('css', 'li[data-ssa-name="Entity browser"]')
      ->find('css', 'button[type="button"]')
      ->click();
    $this->getSession()->getPage()
      ->find('css', 'ol[data-canvas-key="canvas"]')
      ->find('css', 'li[data-type="Entity browser"]')
      ->doubleClick();

    $this->assertSession()->waitForElement('css', '.ssa-modal-sidebar-editor');
    $this->assertSession()->waitForElement('css', 'iframe[title="Edit component"]');
    $this->getSession()->executeScript(<<<JS
     document.querySelector('iframe[title="Edit component"]').name = 'edit_component'
     JS);
    $this->getSession()->switchToIFrame('edit_component');
    $this->assertSession()->waitForText('Settings');
    $this->assertSession()->waitForElement('css', '.coh-actions-bar');
    $this->getSession()->getPage()
      ->find('css', '.coh-actions-bar')
      ->find('css', '.coh-toggle-variable-mode-btn')
      ->click();
    $this->assertSession()->waitForText('Entity browser');
    $this->getSession()->getPage()->find('css', 'input[ng-model="dir.variableModeModel"]')->setValue('[field.entity-browser]');
    $this->getSession()->getPage()->pressButton('Apply');
    $this->getSession()->switchToIFrame();
    $this->assertSession()->waitForElementRemoved('css', 'iframe[title="Edit component"]');
    $this->getSession()->getPage()->pressButton('Save and continue');
    $this->assertSession()->pageTextContains("Created the Component $asset_type Component.");
  }

  /**
   * Tests using the PDF component.
   *
   * @param string $asset_type
   *   Type of the asset.
   * @param string $asset_id
   *   ID of the asset.
   * @param string $asset_name
   *   Name of the asset.
   * @param string $expected_html
   *   The expected HTML for the comparison.
   *
   * @dataProvider assetList()
   */
  public function testCreateAndUseComponent(string $asset_type, string $asset_id, string $asset_name, string $expected_html) {
    $this->testCreateComponent($asset_type, $asset_id, $asset_name);

    $this->drupalGet('/node/add/site_studio');
    $this->getSession()->getPage()->fillField('Title', "$asset_type Site Studio Node");

    $this->assertSession()->waitForElement('css', '.ssa-layout-canvas button[aria-label="Add content"]');
    $this->getSession()->getPage()
      ->find('css', 'button[aria-label="Add content"]')
      ->click();
    $this->assertSession()->waitForElement('css', 'li[data-ssa-name="' . $asset_type . ' Component"]');
    $this->getSession()->getPage()
      ->find('css', 'li[data-ssa-name="' . $asset_type . ' Component"]')
      ->find('css', 'button[aria-label="Add to canvas"]')
      ->click();
    $this->assertSession()->waitForElement('css', 'ol[data-canvas-key="canvas"]');
    $this->getSession()->getPage()
      ->find('css', 'ol[data-canvas-key="canvas"]')
      ->find('css', 'li[data-type="' . $asset_type . ' Component"]')
      ->doubleClick();
    $this->assertSession()->waitForElement('css', '.ssa-modal-sidebar-editor');
    $this->assertSession()->waitForElement('css', 'iframe[title="Edit component"]');
    $this->getSession()->executeScript(<<<JS
document.querySelector('iframe[title="Edit component"]').name = 'edit_component'
JS);
    $this->getSession()->switchToIFrame('edit_component');
    $this->assertSession()->waitForText("$asset_type Component");
    $this->getSession()->getPage()->pressButton('Browse');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->waitForElement('css', 'iframe[title="Entity browser"]');
    $this->getSession()->executeScript(<<<JS
document.querySelector('iframe[title="Entity browser"]').name = 'entity_browser'
JS);
    $this->getSession()->switchToIFrame('entity_browser');
    $this->assertSession()->waitForElement('css', '#media-library-wrapper');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $media_select_checkbox = $this->assertSession()->elementExists('css', '[value="' . $asset_id . '"]');

    $media_select_checkbox->check();
    $this->getSession()->getPage()->pressButton('Insert selected');
    $this->getSession()->switchToWindow();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->waitForElement('css', 'img[alt="' . $asset_name . '"]');
    // @note: must switch to parent frame to switch back to edit_component.
    $this->getSession()->switchToIFrame();
    $this->getSession()->switchToIFrame('edit_component');
    $this->getSession()->getPage()->pressButton('Apply');
    $this->getSession()->switchToIFrame();
    $this->assertSession()->waitForElementRemoved('css', 'iframe[title="Edit component"]');

    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->waitForText("Site Studio $asset_type Site Studio Node has been created");
    $this->assertSession()->responseContains($expected_html);
  }

  /**
   * Data for the node creation.
   */
  public static function assetList() {
    return [
      ['Video',
        'efb03f75-3c42-497b-baa9-5ec79d1f56af',
        'SD-Social Promo.mp4',
        '<iframe src="https://test.widen.net/view/video/mnmc58hipn/SD-Social-Promo.mp4" webkitallowfullscreen="" mozallowfullscreen="" allowfullscreen="" frameborder="0" allowtransparency="true" scrolling="no" style="position:absolute;top:0;left:0;width:100%;height:100%;"></iframe>',

      ],
      ['PDF',
        '0324b0b2-5293-4aa0-b0aa-c85b003395e2',
        'Explorer owner\'s manual.pdf',
        '<a href="https://test.widen.net/view/pdf/8a1ouvfchk/Explorer-owners-manual.pdf?t.download=true" target="_blank">',
      ],
      ['Spinset',
        'eec6d92b-6452-4ab6-894a-b4d0826e65ba',
        'eudaimonia_spin.zip',
        '<a href="https://test.widen.net/view/spinset/yjezd5iwh5/eudaimonia_spin.zip" target="_blank">eudaimonia_spin.zip</a>',
      ],
      ['Image',
        '56ff14de-02cd-41b5-9a73-c917eab19abf',
        'Wheel Illustration.ai',
        '<img src="https://laser.widen.net/content/9e4e810c-147b-4ac2-85a9-cf64f8fa61e0/web/Wheel%20Illustration.ai" width="157" height="120" alt="Wheel Illustration.ai" loading="lazy">',
      ],
    ];
  }

  /**
   * Test for creating image template in the site studio.
   */
  public function testImageComponents() {
    // Site studio breaks with more content in the iframe without the module
    // admin toolbar so restricting it using permission.
    $this->createSiteStudioTemplateAccessUser();
    $this->drupalGet('/admin/cohesion/templates/content_templates/media/media_acquia_dam_image_asset_ful/edit');
    $this->assertSession()->waitForElement('css', '.ssa-layout-canvas button[aria-label="Add content"]');
    $this->getSession()->getPage()
      ->find('css', 'button[aria-label="Add content"]')
      ->click();
    $this->assertSession()->waitForElement('css', '#ssa-sidebar-browser');

    // Wait for sidebar to finish loading.
    $this->assertSession()->waitForElementRemoved('css', '#ssa-sidebar-browser dev[role="status"]');
    $this->assertSession()->waitForText('Media Elements');
    $this->getSession()->getPage()
      ->find('css', 'li[data-ssa-name="Picture"]')
      ->find('css', 'button[type="button"]')
      ->click();
    $this->getSession()->getPage()
      ->find('css', 'ol[data-canvas-key="canvas"]')
      ->find('css', 'li[data-type="Picture"]')
      ->doubleClick();
    $this->assertSession()->waitForElement('css', '.ssa-modal-sidebar-editor');
    $this->assertSession()->waitForElement('css', 'iframe[title="Edit component"]');
    $this->getSession()->executeScript(<<<JS
     document.querySelector('iframe[title="Edit component"]').name = 'edit_component'
     JS);
    $this->getSession()->switchToIFrame('edit_component');
    $this->assertSession()->waitForText('Settings');
    $this->assertSession()->waitForElement('css', '.coh-actions-bar');
    $this->getSession()->getPage()
      ->find('css', '.coh-actions-bar')
      ->find('css', '.coh-toggle-variable-mode-btn')
      ->click();
    $inputs = $this->getSession()->getPage()->findAll('xpath', '//input[contains(@id,"settings-styles-xl-pictureImagesArray")]');
    self::assertCount(2, $inputs);
    $inputs[0]->setValue('[media:asset-path]');
    $inputs[1]->setValue('thumbnail');
    $this->getSession()->getPage()->pressButton('Apply');
    $this->getSession()->switchToIFrame();
    $this->assertSession()->waitForElementRemoved('css', 'iframe[title="Edit component"]');
    $this->getSession()->getPage()
      ->find('css', 'input[id="edit-set-default"]')
      ->click();
    $this->getSession()->getPage()->pressButton('Save and continue');
    $this->assertSession()->pageTextContains("Your styles have been updated.");
    $this->testCreateAndUseComponent('Image', '56ff14de-02cd-41b5-9a73-c917eab19abf', 'Wheel Illustration.ai', '<img class="coh-image coh-image-responsive-xl" src="https://laser.widen.net/content/9e4e810c-147b-4ac2-85a9-cf64f8fa61e0/web/Wheel%20Illustration.ai?w=100&amp;h=100">');
  }

  /**
   * Creates and logs in a Site Studio user.
   */
  public function createSiteStudioAccessUser(): void {
    $user = $this->drupalCreateUser([
      // Permission for generating machine names.
      'access content',
      // Content permissions.
      'administer nodes',
      'bypass node access',
      // Site Studio permissions.
      'administer cohesion',
      'administer cohesion settings',
      'administer base styles',
      'administer components',
      'administer component content',
      'administer component categories',
      // These permissions are explicitly checked in callbacks and are not
      // superseded by any administer permission.
      'access elements',
      'access fields',
      'access helpers',
      'access components',
      'access tokens',
      'access drupal core elements group',
    ]);

    $this->drupalLogin($user);
  }

  /**
   * Creates and logs in a Site Studio user.
   */
  public function createSiteStudioTemplateAccessUser(): void {
    $user = $this->drupalCreateUser([
      // Permission for generating machine names.
      'access content',
      // Content permissions.
      'administer nodes',
      'bypass node access',
      // Site Studio permissions.
      'administer cohesion',
      'administer cohesion settings',
      'administer base styles',
      'administer components',
      'administer component content',
      'administer component categories',
      // These permissions are explicitly checked in callbacks and are not
      // superseded by any administer permission.
      'access elements',
      'access fields',
      'access helpers',
      'access components',
      'access tokens',
      // Template permissions.
      'administer content templates',
      'access media elements group',
    ]);

    $this->drupalLogin($user);
  }

}
