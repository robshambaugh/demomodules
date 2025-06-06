<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_dam\Kernel;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\media\Entity\MediaType;

/**
 * Tests compatibility with module installation.
 *
 * @group acquia_dam
 */
final class ModuleInstallationTest extends AcquiaDamKernelTestBase {

  /**
   * Tests that data is copied over on installation.
   */
  public function testUninstallInstall(): void {
    $this->setDamSiteToken();
    $user = $this->drupalSetUpCurrentUser();
    $user_data = $this->container->get('acquia_dam.authentication_service')->getUserData((int) $user->id());
    self::assertArrayHasKey('acquia_dam_token', $user_data);
    self::assertNotNull($this->container->get('state')->get('acquia_dam_token'));
    $this->container->get('module_installer')->uninstall(['acquia_dam']);
    $this->assertNull(\Drupal::state()->get('acquia_dam.last_update_check'));
    self::assertNull($this->container->get('state')->get('acquia_dam_token'));
    self::assertNull($this->container->get('user.data')->get('acquia_dam', $user->id(), 'account'));
    $this->installModule('acquia_dam');
    $this->container->get('module_handler')->loadInclude('acquia_dam', 'install');
    acquia_dam_install(FALSE);
    $this->assertNotNull(\Drupal::state()->get('acquia_dam.last_update_check'));
    $this->assertCount(1, \Drupal::service('messenger')->messagesByType(MessengerInterface::TYPE_STATUS));
    $this->assertEquals('The Acquia DAM module has been installed. You need to <a href="/admin/config/acquia-dam">configure the module</a> for others to make use of it.', \Drupal::service('messenger')->messagesByType(MessengerInterface::TYPE_STATUS)[0]->__toString());
  }

  /**
   * Tests to check data saved correctly after the hook_update.
   */
  public function testUpdateHook(): void {
    $config_array = [];

    $configuration_map = [
      'media.type.acquia_dam_image_asset',
      'media.type.acquia_dam_spinset_asset',
      'media.type.acquia_dam_documents_asset',
      'core.entity_view_display.media.acquia_dam_image_asset.default',
      'core.entity_view_display.media.acquia_dam_image_asset.media_library',
      'core.entity_view_display.media.acquia_dam_spinset_asset.default',
      'core.entity_view_display.media.acquia_dam_spinset_asset.media_library',
      'core.entity_view_display.media.acquia_dam_documents_asset.default',
      'core.entity_view_display.media.acquia_dam_documents_asset.media_library',
    ];
    $this->installModule('acquia_dam');
    $this->container->get('module_handler')->loadInclude('acquia_dam', 'install');
    acquia_dam_install(FALSE);
    // For the media library display view config.
    $this->installConfig(['media_library', 'acquia_dam']);
    foreach ($configuration_map as $config) {
      $config_array[$config] = $this->config($config)->get();
    }
    self::assertEquals(7, count(MediaType::loadMultiple()));

    $media_storage = $this->entityTypeManager->getStorage('media_type');
    $media_storage->load('acquia_dam_image_asset')->delete();
    $media_storage->load('acquia_dam_spinset_asset')->delete();
    $media_storage->load('acquia_dam_documents_asset')->delete();
    foreach ($configuration_map as $config) {
      self::assertEmpty($this->config($config)->get());
    }
    self::assertEquals(4, count(MediaType::loadMultiple()));
    acquia_dam_update_9001();
    self::assertEquals(7, count(MediaType::loadMultiple()));
    foreach ($configuration_map as $config) {
      $new_config = $this->config($config)->get();
      unset($new_config['uuid']);
      unset($config_array[$config]['uuid']);
      unset($config_array[$config]['_core']);

      self::assertEquals($config_array[$config], $new_config);
    }

  }

}
