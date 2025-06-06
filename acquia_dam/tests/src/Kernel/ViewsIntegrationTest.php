<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_dam\Kernel;

use Drupal\acquia_dam\Plugin\views\filter\AssetMetadataInOperator;
use Drupal\acquia_dam\Plugin\views\filter\AssetMetadataString;
use Drupal\Core\Form\FormState;
use Drupal\media\MediaInterface;
use Drupal\views\Views;
use Drupal\views_remote_data\Plugin\views\query\RemoteDataQuery;

/**
 * Views integration tests.
 *
 * @group acquia_dam
 */
final class ViewsIntegrationTest extends AcquiaDamKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig([
      'media',
      'media_library',
      'acquia_dam',
    ]);
  }

  /**
   * Tests the view provided for the media library.
   */
  public function testMediaLibraryView(): void {
    $this->drupalSetUpCurrentUser();

    $view = Views::getView('acquia_dam_asset_library');
    self::assertNotNull($view);
    $view->setDisplay('default');
    $view->executeDisplay('default', ['pdf']);
    self::assertCount(2, $view->result);

    $result = $view->result[0];
    self::assertIsObject($result);
    self::assertTrue(property_exists($result, 'asset'));
    self::assertEquals('pdf', $result->asset['file_properties']['format_type']);
    self::assertNotNull($result->_entity);
    self::assertInstanceOf(MediaInterface::class, $result->_entity);
    self::assertEquals($result->id, $result->_entity->id());
  }

  /**
   * Tests the search provided for the media library.
   */
  public function testMediaLibrarySearch(): void {
    $this->drupalSetUpCurrentUser();

    $view = Views::getView('acquia_dam_asset_library');
    self::assertNotNull($view);
    $view->setDisplay();

    $filters = $view->getDisplay()->getOption('filters');
    $filters['search']['value'] = 'Explorer';
    $view->display_handler->overrideOption('filters', $filters);

    $view->setDisplay();
    $view->preExecute(['pdf']);
    $view->execute();
    self::assertCount(1, $view->result);
  }

  /**
   * Tests the selection_list metadata filter.
   */
  public function testMediaLibrarySelectListFilter(): void {
    $this->setDamSiteToken();
    $this->setUpCurrentUser();
    $view = Views::getView('acquia_dam_asset_library');
    self::assertNotNull($view);

    $view->initDisplay();
    $query = $view->getQuery();
    self::assertInstanceOf(RemoteDataQuery::class, $query);

    $filter_instance = $this->container
      ->get('plugin.manager.views.filter')
      ->createInstance('asset_metadata_in_operator');
    self::assertInstanceOf(AssetMetadataInOperator::class, $filter_instance);

    $filter_options = [
      'display_key' => 'assettype',
      'filter_operation' => 'in',
      'group' => 1,
    ];
    $filter_instance->init(
      $view,
      $view->getDisplay(),
      $filter_options
    );

    $form = [];
    $filter_instance->buildOptionsForm($form, new FormState());
    self::assertEquals([
      'assettype' => 'Asset Type',
      'containsFnords' => 'Contains Fnords',
    ], $form['display_key']['#options']);

    $filter_instance->getValueOptions();
    self::assertEquals([
      'Document' => 'Document',
      'Graphic' => 'Graphic',
      'Image' => 'Image',
      'Other' => 'Other',
      'Video' => 'Video',
    ], $filter_instance->getValueOptions());

    $filter_instance->value = 'Document';
    $filter_instance->query();

    self::assertCount(1, $query->where);
    $group = reset($query->where);
    self::assertArrayHasKey('conditions', $group);
    $conditions = $group['conditions'];
    self::assertCount(1, $conditions);
    self::assertEquals([
      [
        'field' => ['assettype'],
        'value' => 'Document',
        'operator' => 'in',
      ],
    ], $conditions, var_export($conditions[0], TRUE));
  }

  /**
   * Tests the text, tex_short, text_long metadata filter.
   */
  public function testMediaLibraryTextFilter(): void {
    $this->setDamSiteToken();
    $this->setUpCurrentUser();
    $view = Views::getView('acquia_dam_asset_library');
    self::assertNotNull($view);

    $view->initDisplay();
    $query = $view->getQuery();
    self::assertInstanceOf(RemoteDataQuery::class, $query);

    $filter_instance = $this->container
      ->get('plugin.manager.views.filter')
      ->createInstance('asset_metadata_string');
    self::assertInstanceOf(AssetMetadataString::class, $filter_instance);

    $filter_options = [
      'display_key' => 'keywords',
      'operator' => '=',
      'group' => 1,
    ];
    $filter_instance->init(
      $view,
      $view->getDisplay(),
      $filter_options
    );

    $form = [];
    $filter_instance->buildOptionsForm($form, new FormState());
    self::assertEquals([
      'author' => 'Author',
      'byline' => 'By-line',
      'keywords' => 'Keywords',
      'description' => 'Description',
      'longtext' => 'Long text',
    ], $form['display_key']['#options']);

    $filter_instance->value = 'foobar';
    $filter_instance->query();

    self::assertCount(1, $query->where);
    $group = reset($query->where);
    self::assertArrayHasKey('conditions', $group);
    $conditions = $group['conditions'];
    self::assertCount(1, $conditions);
    self::assertEquals([
      [
        'field' => ['keywords'],
        'value' => 'foobar',
        'operator' => '=',
      ],
    ], $conditions, var_export($conditions[0], TRUE));
  }

}
