<?php

namespace Drupal\acquia_dam\Drush\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\acquia_dam\AssetDownloader;
use Drupal\acquia_dam\AssetRepository;
use Drupal\acquia_dam\Plugin\media\Source\Asset;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\media\MediaTypeInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Download assets drush command class definition.
 */
final class DownloadAssetsDrushCommands extends DrushCommands {

  use StringTranslationTrait;

  /**
   * Constructs a CustomDrushCommands object.
   */
  public function __construct(private readonly AssetRepository $assetRepository, private readonly EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('acquia_dam.asset_repository'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Download and sync media for specific acquia dam media type.
   */
  #[CLI\Command(name: 'acquia-dam:download-assets', aliases: ['das'])]
  #[CLI\Argument(name: 'media-bundle', description: 'Media bundle to download & sync assets from.')]
  #[CLI\Usage(name: 'acquia-dam:download-assets acquia_dam_video_asset', description: 'Download & sync media from acquia DAM video.')]
  public function downloadAssets(): void {
    $media_type = $this->input()->getArgument('media-bundle');
    /** @var MediaTypeInterface $media_type */
    $media_type = $this->entityTypeManager->getStorage('media_type')->load($media_type);
    if ($this->assetRepository->countLocalAssets($media_type, FALSE) > 0) {
      AssetDownloader::buildBatch($media_type);
      drush_backend_batch_process();
    }
    else {
      $this->logger()->success($this->t('No assets are available for download and sync for the selected bundle.'));
    }
  }

  /**
   * Validate command argument.
   */
  #[CLI\Hook(type: HookManager::ARGUMENT_VALIDATOR, target: 'acquia-dam:download-assets')]
  public function validateDownloadAssetsArgument(CommandData $command) {
    $media_type = $command->input()->getArgument('media-bundle');
    $allowed_media_types = array_filter($this->entityTypeManager->getStorage('media_type')->loadMultiple(), static function (MediaTypeInterface $media_type) {
      return $media_type->getSource() instanceof Asset;
    });
    if (!in_array($media_type, array_keys($allowed_media_types))) {
      $this->io()->text('<fg=white;bg=red>[error]</> Media type must be from one of the following:');
      $elements = array_map(fn ($element) => \sprintf(' * %s', $element), array_keys($allowed_media_types));
      $this->io()->text($elements);
      $this->writeln("");
      return new CommandError($this->t('Invalid media type argument.'));
    }
  }

}
