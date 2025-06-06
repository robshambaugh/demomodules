<?php

namespace Drupal\Tests\acquia_dam_integration_links\Kernel;

use Drupal\Component\Utility\Html;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\acquia_dam\Kernel\AcquiaDamKernelTestBase;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;

// Workaround to support tests against both Drupal 10.1 and Drupal 11.0.
// @todo Remove once we depend on Drupal 10.2.
if (!trait_exists(EntityReferenceFieldCreationTrait::class)) {
  class_alias('\Drupal\Tests\field\Traits\EntityReferenceTestTrait', EntityReferenceFieldCreationTrait::class);
}

/**
 * Test integration link register on node create and edit.
 *
 * @group acquia_dam
 */
class EnhancedIntegrationLinkTest extends AcquiaDamKernelTestBase {

  use EntityReferenceFieldCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'views',
    'file',
    'image',
    'media',
    'media_library',
    'node',
    'acquia_dam',
    'acquia_dam_integration_links',
    'acquia_dam_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $mediaTypeId = '';

  /**
   * The Acquia DAM integration link register object.
   *
   * @var \Drupal\acquia_dam\IntegrationLinkRegister
   */
  protected $register;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installConfig('node');
    $this->installEntitySchema('node');
    $this->installEntitySchema('node_type');
    $this->installSchema('node', ['node_access']);

    NodeType::create(['type' => 'page', 'name' => 'page'])->save();

    $field_storage = FieldStorageConfig::loadByName('node', 'body');
    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'page',
      'label' => 'Body',
    ])->save();

    $this->createEntityReferenceField(
      'node',
      'page',
      'media_ref',
      'Media reference',
      'media'
    );

    $media_type = $this->createPdfMediaType();
    $this->mediaTypeId = $media_type->id();

    $this->drupalSetUpCurrentUser();
    $this->setDamSiteToken();
    $this->register = \Drupal::service('acquia_dam.integration_link_register');
  }

  /**
   * Tests integration link creation on node create and edit (reference field).
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testIntegrationLinkEntityUpdate() {
    $media = $this->createPdfMedia();

    self::assertEquals(0, $this->getTableRowCount('acquia_dam_integration_link_tracking'));
    $this->register->destruct();
    $this->container->get('cron')->run();
    self::assertEquals(1, $this->getTableRowCount('acquia_dam_integration_link_tracking'));

    $node = Node::create([
      'type' => 'page',
      'title' => 'Test',
    ]);
    $node->media_ref->target_id = $media->id();
    $node->save();
    $this->register->destruct();
    $this->container->get('cron')->run();
    self::assertEquals(2, $this->getTableRowCount('acquia_dam_integration_link_tracking'));

    $node->set('media_ref', NULL);
    $node->save();
    $this->register->destruct();
    $this->container->get('cron')->run();
    self::assertEquals(1, $this->getTableRowCount('acquia_dam_integration_link_tracking'));
  }

  /**
   * Tests integration link creation with text field with embedded media.
   */
  public function testIntegrationLinkTextEmbed() {
    $media = $this->createPdfMedia();

    self::assertEquals(0, $this->getTableRowCount('acquia_dam_integration_link_tracking'));
    $this->register->destruct();
    $this->container->get('cron')->run();
    self::assertEquals(1, $this->getTableRowCount('acquia_dam_integration_link_tracking'));

    $node = Node::create([
      'type' => 'page',
      'title' => 'Test',
    ]);
    $node->set('body', $this->createEmbed($media->uuid()));
    $node->save();

    $this->register->destruct();
    $this->container->get('cron')->run();
    self::assertEquals(2, $this->getTableRowCount('acquia_dam_integration_link_tracking'));

    $node->set('body', NULL);
    $node->save();
    $this->register->destruct();
    $this->container->get('cron')->run();
    self::assertEquals(1, $this->getTableRowCount('acquia_dam_integration_link_tracking'));
  }

  /**
   * Tests when the same media is referenced multiple times.
   */
  public function testDuplicateReferences() {
    $media = $this->createPdfMedia();

    self::assertEquals(0, $this->getTableRowCount('acquia_dam_integration_link_tracking'));
    $this->register->destruct();
    $this->container->get('cron')->run();
    // Link to media entity.
    self::assertEquals(1, $this->getTableRowCount('acquia_dam_integration_link_tracking'));

    $node = Node::create([
      'type' => 'page',
      'title' => 'Test',
    ]);
    $node->set('body', $this->createEmbed($media->uuid()));
    $node->media_ref->target_id = $media->id();
    $node->save();

    $this->register->destruct();
    $this->container->get('cron')->run();
    // Link to media and node, media referenced 2 times.
    self::assertEquals(2, $this->getTableRowCount('acquia_dam_integration_link_tracking'));

    $node->set('body', NULL);
    $node->save();
    $this->register->destruct();
    $this->container->get('cron')->run();
    // Link to media and node, media referenced once.
    self::assertEquals(2, $this->getTableRowCount('acquia_dam_integration_link_tracking'));

    $node->set('media_ref', NULL);
    $node->set('body', $this->createEmbed($media->uuid()));
    $node->save();
    $this->register->destruct();
    $this->container->get('cron')->run();
    // Link to media and node, media referenced once.
    self::assertEquals(2, $this->getTableRowCount('acquia_dam_integration_link_tracking'));

    $node->set('body', NULL);
    $node->save();
    $this->register->destruct();
    $this->container->get('cron')->run();
    // Link to media referenced nowhere.
    self::assertEquals(1, $this->getTableRowCount('acquia_dam_integration_link_tracking'));
  }

  /**
   * Tests what happens with cron if the entity is deleted.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testEntityDeletedBeforeCron() {
    $media = $this->createPdfMedia();

    self::assertEquals(0, $this->getTableRowCount('acquia_dam_integration_link_tracking'));
    $node = Node::create([
      'type' => 'page',
      'title' => 'Test',
    ]);
    $node->set('body', $this->createEmbed($media->uuid()));
    $node->media_ref->target_id = $media->id();
    $node->save();

    $this->register->destruct();

    // 2 queue should be there, on for media and one for the parent entity.
    $this->assertEquals(2, $this->getTableRowCount('queue'));

    // Delete entity before cron starts to register the integration links.
    $node->delete();
    $this->container->get('cron')->run();
    // Link to media and node, media referenced once.
    self::assertEquals(1, $this->getTableRowCount('acquia_dam_integration_link_tracking'));
    $this->assertEquals(0, $this->getTableRowCount('queue'));
  }

  /**
   * Test the integration link media deletion.
   */
  public function testIntegrationLinkMediaDelete() {
    $media = $this->createPdfMedia();

    self::assertEquals(0, $this->getTableRowCount('acquia_dam_integration_link_tracking'));
    $this->register->destruct();
    $this->container->get('cron')->run();
    self::assertEquals(1, $this->getTableRowCount('acquia_dam_integration_link_tracking'));

    $node = Node::create([
      'type' => 'page',
      'title' => 'Test',
    ]);
    $node->media_ref->target_id = $media->id();
    $node->save();
    $this->register->destruct();
    $this->container->get('cron')->run();
    self::assertEquals(2, $this->getTableRowCount('acquia_dam_integration_link_tracking'));

    $media->delete();
    $this->register->destruct();
    $this->container->get('cron')->run();
    self::assertEquals(0, $this->getTableRowCount('acquia_dam_integration_link_tracking'));
  }

  /**
   * Creates mock embed code for testing.
   *
   * @param string $media_uuid
   *   Media uuid to mock as embedded.
   *
   * @return string
   *   String to set for text field.
   */
  protected function createEmbed(string $media_uuid): string {
    $dom = Html::load("<drupal-media data-embed-code-id=\"original\" data-entity-type=\"media\" data-entity-uuid=\"{$media_uuid}\"></drupal-media>");
    return Html::serialize($dom);
  }

  /**
   * Creates media entity of DAM pdf type.
   *
   * @return \Drupal\media\MediaInterface
   *   Newly created media instance.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createPdfMedia(): MediaInterface {
    $media = Media::create([
      'bundle' => $this->mediaTypeId,
      'acquia_dam_asset_id' => [
        'asset_id' => '0324b0b2-5293-4aa0-b0aa-c85b003395e2',
      ],
    ]);
    $media->save();

    return $media;
  }

  /**
   * Returns count of rows from given table.
   *
   * @param string $table
   *   DB table name.
   *
   * @return int
   *   Amount of rows in the table.
   */
  protected function getTableRowCount(string $table) :int {
    return \Drupal::database()
      ->select($table, 'int')
      ->countQuery()
      ->execute()
      ->fetchField();
  }

}
