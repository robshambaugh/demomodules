<?php

namespace Drupal\acquia_dam\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Subscriber for acquia_dam routes.
 *
 * @internal
 *   Tagged services are internal.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Add the media library UI access checks to the widget displays of the
    // media library view.
    if ($route = $collection->get('view.acquia_dam_asset_library.widget')) {
      $route->addRequirements(['_custom_access' => 'media_library.ui_builder:checkAccess']);
    }
    if ($route = $collection->get('view.acquia_dam_asset_library.widget_table')) {
      $route->addRequirements(['_custom_access' => 'media_library.ui_builder:checkAccess']);
    }

    if ($route = $collection->get('view.dam_content_overview.page_1')) {
      $route->addRequirements(['_acquia_dam_site_authenticated_access_check' => 'TRUE']);
    }
  }

}
