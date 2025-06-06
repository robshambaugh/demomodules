<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_dam\FunctionalJavascript;

use Drupal\editor\Entity\Editor;
use Drupal\filter\Entity\FilterFormat;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\user\UserInterface;
use PHPUnit\Runner\BaseTestRunner;

// Workaround to support tests against both Drupal 10.1 and Drupal 11.0.
// @todo Remove once we depend on Drupal 10.2.
if (!trait_exists(EntityReferenceFieldCreationTrait::class)) {
  class_alias('\Drupal\Tests\field\Traits\EntityReferenceTestTrait', EntityReferenceFieldCreationTrait::class);
}

/**
 * Testing base class for all WebDriver tests in acquia_dam.
 */
abstract class AcquiaDamWebDriverTestBase extends WebDriverTestBase {

  use EntityReferenceFieldCreationTrait;

  /**
   * {@inheritdoc}
   *
   * Remove when dropping support below Drupal 10.3.
   */
  //phpcs:ignore DrupalPractice.Objects.StrictSchemaDisabled.StrictConfigSchema
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'olivero';

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
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->placeBlock('local_tasks_block');
    $this->placeBlock('local_actions_block');
    $this->placeBlock('page_title_block');

    FilterFormat::create([
      'format' => 'test_format',
      'name' => 'Test format',
      'filters' => [
        'media_embed' => ['status' => TRUE],
        'filter_html' => [
          'status' => TRUE,
          'settings' => [
            // Indirectly tests `data-embed-code-id` is added to filter_html.
            // @todo needs a kernel test for this as well to directly test.
            'allowed_html' => '<drupal-media data-entity-type data-entity-uuid data-view-mode data-align data-caption alt title>',
          ],
        ],
      ],
    ])->save();

    $editor = 'ckeditor5';
    $editor_settings = [
      'toolbar' => [
        'items' => [
          'drupalMedia',
          'sourceEditing',
        ],
      ],
      'plugins' => [
        'ckeditor5_sourceEditing' => [
          'allowed_tags' => [],
        ],
        'media_media' => [
          'allow_view_mode_override' => TRUE,
        ],
      ],
    ];

    Editor::create([
      'editor' => $editor,
      'format' => 'test_format',
      'settings' => $editor_settings,
    ])->save();
    $this->drupalCreateContentType(['type' => 'page']);
    // Suppressing itok to make the image style URLs easier to test.
    $config = $this->config('image.settings');
    $config->set('suppress_itok_output', TRUE)->save();

  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    // Backwards compatibility for PHPUnit 9 and below.
    if (method_exists($this, 'getStatus')) {
      $status = $this->getStatus();
      if ($status === BaseTestRunner::STATUS_ERROR || $status === BaseTestRunner::STATUS_WARNING || $status === BaseTestRunner::STATUS_FAILURE) {
        $log = \Drupal::database()
          ->select('watchdog', 'w')
          ->fields('w')
          ->execute()
          ->fetchAll();
        throw new \RuntimeException(var_export($log, TRUE));
      }
    }
    else {
      $status = $this->status();
      if ($status->isError() || $status->isWarning() || $status->isFailure()) {
        $log = \Drupal::database()
          ->select('watchdog', 'w')
          ->fields('w')
          ->execute()
          ->fetchAll();
        throw new \RuntimeException(var_export($log, TRUE));
      }
    }

    parent::tearDown();
  }

  /**
   * Grants the current user a dummy DAM token.
   *
   * @param string $token
   *   (Optional) Token of the user object.
   */
  protected function grantCurrentUserDamToken(?string $token = NULL): void {
    if (!$token) {
      $token = $this->randomString();
    }
    self::assertInstanceOf(UserInterface::class, $this->loggedInUser);
    $this->container->get('acquia_dam.authentication_service')->setUserData(
      (int) $this->loggedInUser->id(),
      [
        'acquia_dam_username' => $this->loggedInUser->getEmail(),
        'acquia_dam_token' => $token,
      ]
    );
  }

  /**
   * Grants the site a dummy DAM domain.
   */
  protected function grantDamDomain() {
    /** @var \Drupal\Core\Config\ConfigFactoryInterface $configFactory */
    $configFactory = $this->container->get('config.factory');
    $configFactory->getEditable('acquia_dam.settings')
      ->set('domain', 'test.widencollective.com')
      ->save();
  }

  /**
   * Grants the site a dummy DAM token and domain configuration.
   */
  protected function grantSiteRegistrationToken() {
    $this->grantDamDomain();
    $this->container->get('state')->set(
      'acquia_dam_token',
      $this->randomString()
    );
  }

  /**
   * Select an asset and import it from the media library.
   *
   * @param string $asset_id
   *   The asset ID.
   * @param string $asset_type
   *   The asset type.
   * @param string $format
   *   Display format of the asset.
   * @param bool $is_editor
   *   True if the call coming from a ck_editor, False if it's a field widget.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  protected function selectAndInsertAsset(string $asset_id, string $asset_type, string $format = '', bool $is_editor = TRUE): void {
    $modal = $this->assertSession()->waitForElement('css', '#drupal-modal');
    $this->assertSession()->waitForElement('css', $asset_type);
    $modal->clickLink($asset_type);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $media_select_checkbox = $this->assertSession()->elementExists('css', "[value='$asset_id']", $modal);
    $media_select_checkbox->check();

    if ($is_editor) {
      $this->pressDialogButton('Next: Select Format');
      // $this->assertSession()->assertWaitOnAjaxRequest();
      $this->assertSession()->pageTextContains('Choose a format for your media');
      $this->assertSession()->waitForElement('css', 'embed_code');
      $this->getSession()->getPage()->selectFieldOption('embed_code', $format);
      // Image with original format doesn't make ajax call.
      if ($format != 'original') {
        $this->assertSession()->assertWaitOnAjaxRequest();
      }
    }

    sleep(2);
    $this->assertSession()->waitForElement('css', ' .form-actions button[type=button].js-form-submit.form-submit');
    $this->pressDialogButton('Insert selected');
  }

  /**
   * Presses a button in the dialog.
   *
   * Drupal hides the original buttons and places them in a special area of
   * the dialog. This ensures the proper button is clicked and assertions do not
   * error on clicking a hidden button.
   *
   * @param string $locator
   *   The button locator.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  protected function pressDialogButton(string $locator): void {
    $this->assertSession()->elementExists('css', '.ui-dialog-buttonpane')->pressButton($locator);
    $this->assertSession()->assertWaitOnAjaxRequest();
  }

  /**
   * Creating a media field for the test.
   */
  protected function createMediaReferenceField($cardinality = -1) {
    $this->createEntityReferenceField('node', 'page', 'media_field', 'A Media Field', 'media', 'default', [], $cardinality);
    $display_repository = $this->container->get('entity_display.repository');
    $display_repository->getFormDisplay('node', 'page')
      ->setComponent('media_field', [
        'type' => 'media_library_widget',
        'region' => 'content',
      ])
      ->save();
    $display_repository->getViewDisplay('node', 'page', 'default')
      ->setComponent('media_field', [
        'type' => 'entity_reference_entity_view',
      ])
      ->save();
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
    ]);

    $this->drupalLogin($user);
  }

}
