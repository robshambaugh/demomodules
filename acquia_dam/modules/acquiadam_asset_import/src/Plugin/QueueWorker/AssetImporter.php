<?php

declare(strict_types=1);

namespace Drupal\acquiadam_asset_import\Plugin\QueueWorker;

use Drupal\acquia_dam\AssetRepository;
use Drupal\acquia_dam\Entity\MediaSourceField;
use Drupal\acquia_dam\Plugin\QueueWorker\AssetQueueWorkerBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Imports Acquia DAM assets.
 *
 * @QueueWorker (
 *   id = "acquia_dam_asset_import",
 *   title = @Translation("Acquia DAM Asset Importer"),
 *   cron = {"time" = 30}
 * )
 */
class AssetImporter extends AssetQueueWorkerBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * Entity storage service for media items.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $mediaStorage;

  /**
   * The HTTP client of Guzzle.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Acquia DAM specific logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Store module-wide used constant as class attribute.
   *
   * @var string
   */
  protected $sourceFieldName;

  /**
   * The asset repository service.
   *
   * @var \Drupal\acquia_dam\AssetRepository
   */
  protected $assetRepository;

  /**
   * {@inheritdoc}
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $media_storage
   *   Entity storage service for media items.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client of Guzzle.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Psr\Log\LoggerInterface $logger
   *   Acquia DAM specific logger service.
   * @param \Drupal\acquia_dam\AssetRepository $asset_repository
   *   The asset repository service.
   */
  public function __construct(array $configuration, string $plugin_id, array $plugin_definition, EntityStorageInterface $media_storage, ClientInterface $http_client, MessengerInterface $messenger, LoggerInterface $logger, AssetRepository $asset_repository) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->mediaStorage = $media_storage;
    $this->httpClient = $http_client;
    $this->messenger = $messenger;
    $this->logger = $logger;
    $this->assetRepository = $asset_repository;
    $this->sourceFieldName = MediaSourceField::SOURCE_FIELD_NAME;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('media'),
      $container->get('http_client'),
      $container->get('messenger'),
      $container->get('logger.channel.acquiadam_asset_import'),
      $container->get('acquia_dam.asset_repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    // If the asset is already imported, skip it.
    if ($this->assetRepository->find([$data['asset_uuid']])) {
      return;
    }

    // Create a media item for the asset.
    $media_entity = $this->mediaStorage->create([
      'bundle' => $data['target_bundle'],
      'name' => $data['file_name'],
      'uid' => $data['queuer_uid'],
      $this->sourceFieldName => [
        'asset_id' => $data['asset_uuid'],
      ],
    ]);
    // Save the media item.
    $media_entity->save();

    // Log the successful creation of the media item.
    $this->logger->info($this->t('DAM asset %file_name (asset ID: %asset_id) was created as a media item with ID: %media_id.', [
      '%file_name' => $data['file_name'],
      '%asset_id' => $data['asset_uuid'],
      '%media_id' => $media_entity->id(),
      'link' => $media_entity->toLink($this->t('View'))->toString(),
    ]));
  }

}
