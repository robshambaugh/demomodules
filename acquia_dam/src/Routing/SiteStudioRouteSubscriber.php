<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Route subscriber for Site Studio routes.
 */
final class SiteStudioRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Site Studio uses its own media library route with an overridden service.
    // However, the service does not use decoration. It also has no business
    // logic, so we revert  to the main service which we decorate.
    $site_studio_media_library_route = $collection->get('cohesion.media_library_ui');
    if ($site_studio_media_library_route) {
      $site_studio_media_library_route->setDefault('_controller', 'media_library.ui_builder:buildUi');
    }
  }

}
