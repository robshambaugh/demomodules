<?php

namespace Drupal\Tests\acquia_dam\Kernel;

use Drupal\media\Entity\Media;

/**
 * Integration link tests.
 *
 * @group acquia_dam
 */
class IntegrationLinkTest extends AcquiaDamKernelTestBase {

  /**
   * Test the integration.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testIntegrationLink(): void {
    $this->drupalSetUpCurrentUser();
    /** @var \Drupal\acquia_dam\IntegrationLinkRegister $register */
    $register = \Drupal::service('acquia_dam.integration_link_register');

    $asset_uuid = '0324b0b2-5293-4aa0-b0aa-c85b003395e2';
    $media_type = $this->createPdfMediaType();
    $media = Media::create([
      'bundle' => $media_type->id(),
      'acquia_dam_asset_id' => [
        'asset_id' => $asset_uuid,
        'version_id' => '7b67948f-ee7e-405c-a0cd-344a24d8afb2',
      ],
    ]);

    $media->save();

    self::assertEquals(0, $this->getTrackingTableRowCount());
    self::assertEquals(0, $this->getAssetUsageCount($asset_uuid));
    $register->destruct();
    $this->container->get('cron')->run();
    $this->getAssetUsageCount($asset_uuid);
    self::assertEquals(1, $this->getTrackingTableRowCount());
    self::assertEquals(1, $this->getAssetUsageCount($asset_uuid));
    $media->delete();
    $register->destruct();
    $this->container->get('cron')->run();
    self::assertEquals(0, $this->getTrackingTableRowCount());
    self::assertEquals(0, $this->getAssetUsageCount($asset_uuid));
  }

  /**
   * Returns count of rows from 'acquia_dam_integration_link_tracking' table.
   *
   * @return int
   *   Amount of rows in the table.
   */
  protected function getTrackingTableRowCount(): int {
    return \Drupal::database()
      ->select('acquia_dam_integration_link_tracking', 'int')
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * Returns usage coung of asset from tracking aggregate table.
   *
   * @return int
   *   Amount of rows in the table.
   */
  protected function getAssetUsageCount(string $asset_id): int {
    return (int) \Drupal::database()
      ->select('acquia_dam_integration_link_aggregate', 'int')
      ->fields('int', ['usage_count'])
      ->condition('asset_uuid', $asset_id)
      ->execute()
      ->fetchField();
  }

}
