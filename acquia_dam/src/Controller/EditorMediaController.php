<?php

namespace Drupal\acquia_dam\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\media\MediaInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for the media revision CKEditor plugin.
 */
final class EditorMediaController implements ContainerInjectionInterface {

  /**
   * The CSRF token service.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  private $csrfToken;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  private $entityRepository;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a new EditorMediaRevisionController object.
   *
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrfToken
   *   The CSRF token service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   The entity repository.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time object.
   */
  public function __construct(CsrfTokenGenerator $csrfToken, EntityTypeManagerInterface $entityTypeManager, EntityRepositoryInterface $entityRepository, TimeInterface $time) {
    $this->csrfToken = $csrfToken;
    $this->entityTypeManager = $entityTypeManager;
    $this->entityRepository = $entityRepository;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('csrf_token'),
      $container->get('entity_type.manager'),
      $container->get('entity.repository'),
      $container->get('datetime.time')
    );
  }

  /**
   * Returns JSON response to determine if a media embed is the latest revision.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function isLatestRevision(Request $request): JsonResponse {
    $this->csrfCheck($request);

    $uuid = $request->query->get('uuid', '');
    $revision_id = $request->query->get('revisionId', '');
    if ($uuid === '' || $revision_id === '') {
      throw new NotFoundHttpException();
    }

    $media = $this->entityRepository->loadEntityByUuid('media', $uuid);
    if (!$media instanceof MediaInterface) {
      throw new NotFoundHttpException();
    }
    $revision = $this->entityTypeManager->getStorage('media')->loadRevision($revision_id);
    if (!$revision instanceof MediaInterface) {
      throw new NotFoundHttpException();
    }

    if ($revision->uuid() !== $media->uuid()) {
      throw new NotFoundHttpException();
    }
    $is_latest = $revision->isLatestRevision();
    // If the media is expired then there is no need to show the update field.
    $expiry_date = $media->get('acquia_dam_expiry_date')->getValue();
    if ($expiry_date && (int) $expiry_date[0]['value'] < $this->time->getCurrentTime()) {
      $is_latest = TRUE;
    }

    return (new JsonResponse([
      'isLatest' => $is_latest,
    ]))
      ->setPrivate()
      // Allow the end user to cache this response for 5 minutes.
      ->setMaxAge(300);
  }

  /**
   * Returns JSON response to determine if a media embed is the latest revision.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function isExpired(Request $request): JsonResponse {
    $this->csrfCheck($request);
    $is_media_expired = FALSE;

    $uuid = $request->query->get('uuid', '');
    if ($uuid === '') {
      throw new NotFoundHttpException();
    }

    $media = $this->entityRepository->loadEntityByUuid('media', $uuid);
    if (!$media instanceof MediaInterface) {
      throw new NotFoundHttpException();
    }

    // This not a DAM asset, it cannot be expired.
    if (!$media->hasField('acquia_dam_expiry_date')) {
      return (new JsonResponse([
        'isExpired' => FALSE,
      ]))
        ->setPrivate()
        // Allow the end user to cache this response for 5 minutes.
        ->setMaxAge(300);
    }

    $expiry_date = $media->get('acquia_dam_expiry_date')->getValue();
    if ($expiry_date && (int) $expiry_date[0]['value'] < $this->time->getCurrentTime()) {
      $is_media_expired = TRUE;
    }

    return (new JsonResponse([
      'isExpired' => $is_media_expired,
    ]))
      ->setPrivate()
      // Allow the end user to cache this response for 5 minutes.
      ->setMaxAge(300);
  }

  /**
   * Performs CSRF check on the request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   */
  private function csrfCheck(Request $request): void {
    $header = 'X-Drupal-AcquiaDam-CSRF-Token';
    if (!$request->headers->has($header)) {
      throw new AccessDeniedHttpException("$header header is missing.");
    }
    $token = $request->headers->get($header);
    if ($token === NULL || !$this->csrfToken->validate($token, $header)) {
      throw new AccessDeniedHttpException("$header is invalid.");
    }
  }

}
