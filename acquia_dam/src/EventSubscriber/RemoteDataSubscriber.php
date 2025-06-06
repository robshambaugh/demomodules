<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\EventSubscriber;

use Drupal\acquia_dam\Client\AcquiaDamClientFactory;
use Drupal\acquia_dam\Entity\MediaSourceField;
use Drupal\acquia_dam\Exception\DamClientException;
use Drupal\acquia_dam\Exception\DamServerException;
use Drupal\acquia_dam\MediaTypeResolver;
use Drupal\acquia_dam\Plugin\media\Source\Asset;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\media\Entity\Media;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use Drupal\views_remote_data\Events\RemoteDataLoadEntitiesEvent;
use Drupal\views_remote_data\Events\RemoteDataQueryEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for Views remote data queries.
 */
final class RemoteDataSubscriber implements EventSubscriberInterface {

  /**
   * The Acquia DAM client factory.
   *
   * @var \Drupal\acquia_dam\Client\AcquiaDamClientFactory
   */
  private $clientFactory;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  private $messenger;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The media type resolver.
   *
   * @var \Drupal\acquia_dam\MediaTypeResolver
   */
  private $mediaTypeResolver;

  /**
   * Constructs a new RemoteDataSubscriber object.
   *
   * @param \Drupal\acquia_dam\Client\AcquiaDamClientFactory $clientFactory
   *   The client factory.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Time service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   Date formatter service.
   * @param \Drupal\acquia_dam\MediaTypeResolver $media_type_resolver
   *   The media type resolver.
   */
  public function __construct(AcquiaDamClientFactory $clientFactory, MessengerInterface $messenger, LoggerInterface $logger, TimeInterface $time, DateFormatterInterface $dateFormatter, MediaTypeResolver $media_type_resolver) {
    $this->clientFactory = $clientFactory;
    $this->messenger = $messenger;
    $this->logger = $logger;
    $this->time = $time;
    $this->dateFormatter = $dateFormatter;
    $this->mediaTypeResolver = $media_type_resolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RemoteDataQueryEvent::class => 'doQuery',
      RemoteDataLoadEntitiesEvent::class => 'doLoadEntities',
    ];
  }

  /**
   * Performs the query to return the results for a View.
   *
   * @param \Drupal\views_remote_data\Events\RemoteDataQueryEvent $event
   *   The event.
   *
   * @throws \Exception
   */
  public function doQuery(RemoteDataQueryEvent $event): void {
    if (!$this->isValidView($event->getView())) {
      return;
    }
    // @todo allow using site token if the View is not media library
    $conditions = [];
    foreach ($event->getConditions() as $condition_group) {
      foreach ($condition_group['conditions'] as $condition) {
        $field = implode('.', $condition['field']);
        $value = $condition['value'];
        if ($field === 'search') {
          $conditions[] = $value;
        }
        else {
          if (is_array($value)) {
            $value = implode(',', $value);
          }
          $condition_value = "$field:($value)";
          if ($condition['operator'] === '<>') {
            $condition_value = "-($condition_value)";
          }
          $conditions[] = $condition_value;
        }
      }
    }
    $conditions[] = $this->getAssetIsActiveCondition();
    try {
      $results = $this
        ->clientFactory
        ->getUserClient()->search(
          implode(' ', $conditions),
          '-created_date',
          $event->getLimit(),
          $event->getOffset(),
          [
            'asset_properties',
            'embeds',
            'file_properties',
            'metadata',
            'security',
            'thumbnails',
          ]
        );
    }
    catch (DamClientException $exception) {
      $this->messenger->addError('Something went wrong with the request, the search could not be completed.');
      $this->logger->error($exception->getMessage());
      return;
    }
    catch (DamServerException $exception) {
      $this->messenger->addError('Something went wrong contacting Acquia DAM, the search could not be completed.');
      $this->logger->error($exception->getMessage());
      return;
    }
    catch (\Exception $exception) {
      $this->messenger->addError('Something went wrong, the search could not be completed.');
      $this->logger->error($exception->getMessage());
      return;
    }

    $assets = $results['items'] ?? [];
    foreach ($assets as $asset) {
      $event->addResult(new ResultRow([
        'id' => $asset['id'],
        'asset' => $asset,
        'filename' => $asset['filename'],
      ]));
    }

    $pager = $event->getView()->getPager();
    // We have to set the correct value for the pager. This value will be used
    // by Views Remote Data to set the total row count for the view.
    $pager->total_items = $results['total_count'];
  }

  /**
   * Creates stub Media entities to a View with remote data from the DAM.
   *
   * @param \Drupal\views_remote_data\Events\RemoteDataLoadEntitiesEvent $event
   *   The event.
   */
  public function doLoadEntities(RemoteDataLoadEntitiesEvent $event): void {
    if (!$this->isValidView($event->getView())) {
      return;
    }

    foreach ($event->getResults() as $result) {
      assert(property_exists($result, 'id'));
      assert(property_exists($result, 'asset'));
      $bundle = $this->mediaTypeResolver->resolve($result->asset);
      if ($bundle === NULL) {
        continue;
      }
      $result->_entity = Media::create([
        'mid' => $result->id,
        'bundle' => $bundle->id(),
        'name' => $result->asset['filename'],
        MediaSourceField::SOURCE_FIELD_NAME => [
          'asset_id' => $result->id,
        ],
      ]);
      $media_source = $result->_entity->getSource();
      assert($media_source instanceof Asset);
      $media_source->setAssetData($result->asset);
    }
  }

  /**
   * Returns search conditions string to filter assets by status.
   *
   * Adds one day time to the current date since the API "before" query string
   * excludes assets released today, but those count as active as well.
   *
   * @return string
   *   Search condition string.
   */
  protected function getAssetIsActiveCondition(): string {
    $now = $this->time->getCurrentTime();
    $today = $this->dateFormatter->format(
      $now,
      'custom',
      'm/d/Y'
    );

    $tomorrow = $this->dateFormatter->format(
      $now + 86400,
      'custom',
      'm/d/Y'
    );

    // Release date is before tomorrow and the expiration date is later or
    // empty.
    return "rd:([before $tomorrow]) AND ed:((isEmpty) OR [after $today])";
  }

  /**
   * Checks if the View is for Acquia DAM assets.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The View.
   *
   * @return bool
   *   Returns TRUE if the view is valid, otherwise FALSE.
   */
  private function isValidView(ViewExecutable $view): bool {
    return array_key_exists('acquia_dam_assets', $view->getBaseTables());
  }

}
