<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\StreamWrapper;

use Drupal\acquia_dam\Client\AcquiaDamClient;
use Drupal\acquia_dam\Client\AcquiaDamClientFactory;
use Drupal\acquia_dam\EmbedCodeUrlBuilder;
use Drupal\acquia_dam\Exception\DamClientException;
use Drupal\acquia_dam\Exception\DamConnectException;
use Drupal\acquia_dam\Exception\DamServerException;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image\Entity\ImageStyle;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\StreamInterface;

/**
 * Provides the acquia-dam scheme stream wrapper.
 *
 * This is a stream wrapper implementation in write mode.
 *
 * Stream wrappers cannot use dependency injection, as functions like `stat`,
 * `stream_*` and others perform an instantiation of the object without
 * using the service container.
 *
 * @todo directory support could be added, as categories.
 *
 * @phpcs:disable Drupal.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
 */
final class AcquiaDamStreamWrapper implements StreamWrapperInterface {

  /**
   * The asset ID for this instance.
   *
   * @var string
   */
  private $assetId = '';

  /**
   * The version ID for this instance.
   *
   * @var string
   */
  private $versionId = '';

  /**
   * The asset data for this instance.
   *
   * @var array|null
   */
  private $asset;

  /**
   * The URI for this instance.
   *
   * @var string
   */
  private $uri = '';

  /**
   * Format for the image.
   *
   * @var string
   */

  private $format = 'original';

  /**
   * The list of effects for the image.
   *
   * @var array
   */
  private $imageStyleEffects = [];

  /**
   * The stream for this instance.
   *
   * @var \Psr\Http\Message\StreamInterface|null
   */
  private $stream;

  /**
   * {@inheritdoc}
   */
  public function dir_closedir() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function dir_opendir($path, $options) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function dir_readdir() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function dir_rewinddir() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function mkdir($path, $mode, $options) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function rename($path_from, $path_to) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function rmdir($path, $options) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function stream_cast($cast_as) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function stream_close() {
    if ($this->stream !== NULL) {
      $this->stream->close();
    }
    $this->flush();
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function stream_eof() {
    // Note: there should be no way to reach a null stream during eof.
    if ($this->stream === NULL) {
      return TRUE;
    }
    return $this->stream->eof();
  }

  /**
   * {@inheritdoc}
   */
  public function stream_flush() {
    $this->flush();
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function stream_lock($operation) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function stream_metadata($path, $option, $value) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function stream_open($path, $mode, $options, &$opened_path) {
    $this->flush();
    try {
      $this->loadAsset($path);
      return TRUE;
    }
    catch (DamClientException | DamServerException | DamConnectException $e) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function stream_read($count) {
    if ($this->stream === NULL) {
      $data = $this->streamAsset();
      if ($data === NULL) {
        return FALSE;
      }
      $this->stream = $data;
    }
    return $this->stream->read($count);
  }

  /**
   * {@inheritdoc}
   */
  public function stream_seek($offset, $whence = SEEK_SET) {
    if ($this->stream === NULL) {
      return FALSE;
    }
    return $this->stream->seek($offset, $whence);
  }

  /**
   * {@inheritdoc}
   */
  public function stream_set_option($option, $arg1, $arg2) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function stream_stat() {
    return $this->stat();
  }

  /**
   * {@inheritdoc}
   */
  public function stream_tell() {
    if ($this->stream === NULL) {
      return 0;
    }
    return $this->stream->tell();
  }

  /**
   * {@inheritdoc}
   */
  public function stream_truncate($new_size) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function stream_write($data) {
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function unlink($path) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function url_stat($path, $flags) {
    try {
      $this->loadAsset($path);
    }
    catch (DamClientException | DamServerException | DamConnectException $e) {
      return FALSE;
    }
    return $this->stat();
  }

  /**
   * {@inheritdoc}
   */
  public static function getType() {
    return self::WRITE;
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return new TranslatableMarkup('Acquia DAM');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return new TranslatableMarkup('Read-only stream wrapper for Acquia DAM assets.');
  }

  /**
   * {@inheritdoc}
   */
  public function setUri($uri) {
    $this->uri = $uri;
  }

  /**
   * {@inheritdoc}
   */
  public function getUri() {
    return $this->uri;
  }

  /**
   * {@inheritdoc}
   */
  public function getExternalUrl() {
    try {
      if ($this->loadAsset($this->uri) === 404) {
        return '';
      }
      assert($this->asset !== NULL);
    }
    catch (DamClientException | DamServerException | DamConnectException $e) {
      return '';
    }

    $asset_path_id = $this->asset['external_id'];
    if ($this->versionId !== '') {
      $asset_path_id = $this->versionId;
    }
    $effects = $this->imageStyleEffects;

    // Without animate=true gifs will load on the page but not play.
    if ($this->asset['file_properties']['format'] === 'GIF') {
      $effects['animate'] = 'true';
    }

    // Get the CDN hostname from the asset's `original` embed code.
    //
    // Currently the CDN is `*.widen.net` and cannot have a vanity URL.
    // The instance hostname may have a vanity domain attached, which does not
    // include the instance ID, which is used as a subdomain to the CDN. The
    // following ensures we use the correct hostname for the asset URL.
    $embed_code_uri = new Uri($this->asset['embeds']['original']['url']);
    $uri = (new Uri())
      ->withScheme('https')
      ->withHost($embed_code_uri->getHost())
      ->withPath("/content/$asset_path_id/$this->format/{$this->asset['filename']}");

    $uri = (string) Uri::withQueryValues($uri, $effects);
    // Once we have the correct uri, reset the image effects.
    $this->imageStyleEffects = [];
    return $uri;
  }

  /**
   * {@inheritdoc}
   */
  public function realpath() {
    return trim($this->uri, '\/');
  }

  /**
   * {@inheritdoc}
   */
  public function dirname($uri = NULL) {
    return FALSE;
  }

  /**
   * Clears the current stream.
   */
  private function flush(): void {
    $this->stream = NULL;
  }

  /**
   * Fetches a stream for the asset's data.
   *
   * @return \Psr\Http\Message\StreamInterface|null
   *   The stream, or NULL on error.
   */
  private function streamAsset(): ?StreamInterface {
    try {
      $url = $this->getExternalUrl();
      return $this->getHttpClient()->get($url)->getBody();
    }
    catch (RequestException $exception) {
      return NULL;
    }
  }

  /**
   * Loads the asset.
   *
   * @param string $uri
   *   The URI.
   *
   * @return int|null
   *   Numeric error code 404 if the Client was unable to fetch asset data.
   *
   * @throws \Drupal\acquia_dam\Exception\DamClientException
   * @throws \Drupal\acquia_dam\Exception\DamServerException
   * @throws \Drupal\acquia_dam\Exception\DamConnectException
   */
  private function loadAsset(string $uri): ?int {
    if (preg_match('/\/styles\/([\w]*)\/acquia-dam\/([\w\-\/]*)(.?[\w-]*)/', $uri, $image_style_matches) === 1) {
      $target = $image_style_matches[2];
      $parts = explode('/', $target, 2);
      $this->assetId = $parts[0];
      $this->versionId = count($parts) === 2 ? $parts[1] : '';
      $asset_data = $this->getDamClient()->getAsset($this->assetId, $this->versionId);

      if (isset($asset_data['exception_code'])) {
        return $asset_data['exception_code'];
      }

      $this->asset = $asset_data;
      $image_style = ImageStyle::load($image_style_matches[1]);
      if ($image_style !== NULL) {
        $image_effects = $image_style->getEffects()->getConfiguration();
        try {
          $image_properties = $this->asset['file_properties']['image_properties'];
          if(isset($image_properties['width']) && isset($image_properties['height'])) {
            $this->imageStyleEffects = EmbedCodeUrlBuilder::mapImageEffects(
              $image_effects,
              $image_properties,
              $uri
            );
          }
        }
        catch (\Exception $exception) {
          \Drupal::logger('acquia_dam')
            ->warning(sprintf('Error during image effect mapping. Error: %s', $exception->getMessage()));
        }
        if (array_key_exists('format', $this->imageStyleEffects)) {
          $this->format = $this->imageStyleEffects['format'];
          unset($this->imageStyleEffects['format']);
        }
      }
    }
    else {
      [, $target] = explode('://', $uri, 2);
    }
    $parts = explode('/', $target, 2);
    $this->uri = $uri;
    $this->assetId = $parts[0];
    $this->versionId = count($parts) === 2 ? $parts[1] : '';
    unset($asset_data);
    $asset_data = $this->getDamClient()->getAsset($this->assetId, $this->versionId);

    if (isset($asset_data['exception_code'])) {
      return $asset_data['exception_code'];
    }

    $this->asset = $asset_data;
    if ($this->asset['file_properties']['format_type'] === 'image' && $this->format === 'original') {
      $this->format = 'web';
    }

    return NULL;
  }

  /**
   * Returns the data needed to replicate stat().
   *
   * @return array|false
   *   The stat data, or FALSE if the asset is not loaded.
   */
  private function stat() {
    if (!$this->asset) {
      return FALSE;
    }

    $stat = [];
    $stat[0] = $stat['dev'] = 0;
    $stat[1] = $stat['ino'] = 0;
    $stat[2] = $stat['mode'] = 0100000 | 0444;
    $stat[3] = $stat['nlink'] = 0;
    $stat[4] = $stat['uid'] = 0;
    $stat[5] = $stat['gid'] = 0;
    $stat[6] = $stat['rdev'] = 0;
    $stat[7] = $stat['size'] = $this->asset['file_properties']['size_in_kbytes'] * 1024;
    $stat[8] = $stat['atime'] = strtotime($this->asset['last_update_date']);
    $stat[9] = $stat['mtime'] = strtotime($this->asset['last_update_date']);
    $stat[10] = $stat['ctime'] = strtotime($this->asset['last_update_date']);
    $stat[11] = $stat['blksize'] = 0;
    $stat[12] = $stat['blocks'] = 0;

    return $stat;
  }

  /**
   * Gets the HTTP client.
   *
   * The HTTP client is used for streaming asset data.
   *
   * @return \GuzzleHttp\Client
   *   The HTTP client.
   */
  private function getHttpClient(): Client {
    static $client;
    if ($client === NULL) {
      $client = \Drupal::httpClient();
    }
    return $client;
  }

  /**
   * Gets the DAM client.
   *
   * @return \Drupal\acquia_dam\Client\AcquiaDamClient
   *   The DAM client.
   *
   * @throws \Drupal\acquia_dam\Exception\DamClientException
   */
  private function getDamClient(): AcquiaDamClient {
    static $client;
    if ($client === NULL) {
      $client_factory = \Drupal::service('acquia_dam.client.factory');
      assert($client_factory instanceof AcquiaDamClientFactory);
      $client = $client_factory->getSiteClient();
    }
    return $client;
  }

}
