<?php

namespace Drupal\acquia_dam\Controller;

use Drupal\acquia_dam\Client\AcquiaDamClientFactory;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns widen category information.
 */
class AcquiaDamCategoriesController implements ContainerInjectionInterface {

  /**
   * DAM client factory.
   *
   * @var \Drupal\acquia_dam\Client\AcquiaDamClientFactory
   */
  protected $damClientFactory;

  /**
   * Constructs a new AcquiaDamCategoriesController.
   *
   * @param \Drupal\acquia_dam\Client\AcquiaDamClientFactory $clientFactory
   *   DAM Client factory.
   */
  public function __construct(AcquiaDamClientFactory $clientFactory) {
    $this->damClientFactory = $clientFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('acquia_dam.client.factory')
    );
  }

  /**
   * Returns DAM category information.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Json response with category data.
   *
   * @throws \Drupal\acquia_dam\Exception\DamClientException
   */
  public function getCategory(Request $request): JsonResponse {
    $client = $this->damClientFactory->getUserClient();

    $category_path = $request->query->get('category', '');
    if (!is_string($category_path)) {
      $category_path = '';
    }
    try {
      $response = $client->getCategories($category_path);
    }
    catch (\Exception $exception) {
      $response = [];
    }

    return new JsonResponse($response);
  }

}
