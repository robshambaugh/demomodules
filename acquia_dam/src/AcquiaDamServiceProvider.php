<?php

namespace Drupal\acquia_dam;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Acquia dam service provider.
 */
class AcquiaDamServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // We cannot use the module handler as the container is not yet compiled.
    // @see \Drupal\Core\DrupalKernel::compileContainer()
    $modules = $container->getParameter('container.modules');
    if (!isset($modules['crop'])) {
      $container->removeDefinition('acquia_dam.crop_new_asset_version_subscriber');
    }
  }

}
