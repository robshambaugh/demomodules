<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\Controller;

use Drupal\acquia_dam\Entity\MediaSourceField;
use Drupal\acquia_dam\MetadataRefreshTrait;
use Drupal\acquia_dam\Plugin\Field\FieldType\AssetItem;
use Drupal\acquia_dam\Plugin\media\Source\Asset;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\media\MediaInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller to sync metadata from DAM to media entity.
 */
final class MetadataSyncController implements ContainerInjectionInterface {

  use StringTranslationTrait;
  use MetadataRefreshTrait;

  /**
   * The cache tag invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  private $cacheTagsInvalidator;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  private $messenger;

  /**
   * Time interface.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a new MetadataSyncController object.
   *
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cache_tags_invalidator
   *   The cache tag invalidator.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation
   *   The translation service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Time Interface.
   */
  public function __construct(CacheTagsInvalidatorInterface $cache_tags_invalidator, MessengerInterface $messenger, TranslationInterface $translation, TimeInterface $time) {
    $this->cacheTagsInvalidator = $cache_tags_invalidator;
    $this->messenger = $messenger;
    $this->stringTranslation = $translation;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('cache_tags.invalidator'),
      $container->get('messenger'),
      $container->get('string_translation'),
      $container->get('datetime.time')
    );
  }

  /**
   * Syncs metadata from DAM to the asset.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The response.
   */
  public function syncMetadata(MediaInterface $media): RedirectResponse {
    if (!$media->getSource() instanceof Asset) {
      throw new NotFoundHttpException('Media is not an Acquia DAM asset.');
    }
    $source_field = $media->get(MediaSourceField::SOURCE_FIELD_NAME);
    $source_field_item = $source_field->first();
    assert($source_field_item instanceof AssetItem);
    $this->cacheTagsInvalidator->invalidateTags(["acquia-dam-asset:$source_field_item->asset_id"]);

    // Force mapped fields for metadata to be refreshed.
    $this->forceMappedFieldRefresh($media);

    $media->setChangedTime($this->time->getCurrentTime());
    $media->save();
    $this->messenger->addMessage($this->t('Metadata has been synced for @label.', [
      '@label' => $media->label(),
    ]));
    return new RedirectResponse($media->toUrl('collection')->toString());
  }

}
