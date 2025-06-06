<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_dam\Kernel;

use Drupal\filter\Entity\FilterFormat;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Tests that filter formats are processed to include our data attribute.
 *
 * The acquia_dam module is not installed by default to test hooks.
 *
 * @group acquia_dam
 */
final class FilterIntegrationTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'editor',
    'file',
    'filter',
    'views',
    'media',
    'media_library',
  ];

  /**
   * Tests the install hook.
   */
  public function testUpdateExisting(): void {
    FilterFormat::create([
      'format' => 'test_format',
      'name' => 'Test format',
      'filters' => [
        'media_embed' => ['status' => TRUE],
        'filter_html' => [
          'status' => TRUE,
          'settings' => [
            'allowed_html' => '<drupal-media data-entity-type data-entity-uuid data-view-mode data-align data-caption alt title>',
          ],
        ],
      ],
    ])->save();

    $filter_format = FilterFormat::load('test_format');
    self::assertNotNull($filter_format);
    $restrictions = $filter_format->getHtmlRestrictions();
    self::assertIsArray($restrictions);
    self::assertArrayNotHasKey('data-embed-code-id', $restrictions['allowed']['drupal-media']);

    $this->installModule('acquia_dam');
    $this->container->get('module_handler')->loadInclude('acquia_dam', 'install');
    acquia_dam_install(FALSE);

    $filter_format = $this->reloadEntity($filter_format);
    self::assertInstanceOf(FilterFormat::class, $filter_format);
    $restrictions = $filter_format->getHtmlRestrictions();
    self::assertIsArray($restrictions);
    self::assertArrayHasKey('data-embed-code-id', $restrictions['allowed']['drupal-media'], var_export($restrictions, TRUE));
  }

  /**
   * Tests that new formats are processed.
   */
  public function testNewFormat(): void {
    $this->installModule('acquia_dam');

    FilterFormat::create([
      'format' => 'test_format',
      'name' => 'Test format',
      'filters' => [
        'media_embed' => ['status' => TRUE],
        'filter_html' => [
          'status' => TRUE,
          'settings' => [
            'allowed_html' => '<drupal-media data-entity-type data-entity-uuid data-view-mode data-align data-caption alt title>',
          ],
        ],
      ],
    ])->save();

    $filter_format = FilterFormat::load('test_format');
    self::assertInstanceOf(FilterFormat::class, $filter_format);
    $restrictions = $filter_format->getHtmlRestrictions();
    self::assertIsArray($restrictions);
    self::assertArrayHasKey('data-embed-code-id', $restrictions['allowed']['drupal-media'], var_export($restrictions, TRUE));
  }

}
