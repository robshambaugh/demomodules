<?php

namespace Drupal\acquia_dam;

use Drupal\acquia_dam\Entity\ImageAltTextField;
use Drupal\acquia_dam\Entity\MediaSourceField;
use Drupal\acquia_dam\Plugin\Field\FieldType\AssetItem;
use Drupal\Core\Link;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\media\MediaInterface;

/**
 * Generates specific embed codes depending on source plugins of media item.
 */
final class EmbedCodeFactory implements TrustedCallbackInterface {

  use StringTranslationTrait;

  /**
   * Returns an array containing select options for select render.
   *
   * @param string|null $asset_type
   *   Asset type.
   *
   * @return string[]
   *   Select options for given type or full mapping if asset type not provided.
   */
  public static function getSelectOptions(?string $asset_type = NULL): array {
    // Generated URL won't have the download option.
    // https://www.drupal.org/project/drupal/issues/2984272 Core issue where
    // "." in the query parameter gets replaced with "_" so the url with
    // "t.download=true" will be replaced with "t_download=true".
    $image_styles = [];
    /** @var \Drupal\acquia_dam\ImageStyleHelper $image_style_helper */
    $image_style_helper = \Drupal::service('acquia_dam.image_style_support');
    foreach ($image_style_helper->getAllowedImageStyles() as $styles) {
      $image_styles[$styles->id()] = $styles->label();
    }
    $image_styles = ['original' => 'Original'] + $image_styles;
    $mapping = [
      'audio' => [
        'remote_streaming' => 'Remote streaming',
        'remote_streaming_download' => 'Remote streaming with download',
      ],
      'documents' => [
        'inline_view_download' => 'Inline viewer with download',
        'link_text_download' => 'Text linked to viewer with download',
        'link_thumbnail_download' => 'Thumbnail linked to viewer with download',
        'link_thumbnail' => 'Thumbnail linked to viewer without download',
      ],
      'generic' => [
        'link_download' => 'A simple link directly pointing to the remote asset file',
      ],
      'image' => $image_styles,
      'pdf' => [
        'inline_view_download' => 'Inline viewer with download',
        'inline_view' => 'Inline viewer without download',
        'link_text_download' => 'Text linked to viewer with download',
        'link_text' => 'Text linked to viewer without download',
        'link_thumbnail_download' => 'Thumbnail linked to viewer with download',
        'link_thumbnail' => 'Thumbnail linked to viewer without download',
      ],
      'spinset' => [
        'inline_view' => 'Inline viewer without download',
        'link_text' => 'Text linked to viewer without download',
      ],
      'video' => [
        'inline_view_download' => 'Inline player with download',
        'inline_view' => 'Inline player without download',
        'link_thumbnail_download' => 'Thumbnail linked to player with download',
        'link_thumbnail' => 'Thumbnail linked to player without download',
        'video_stream' => 'Using the browser\'s default HTML5 player – controls displayed',
        'video_stream_muted_looped_autoplay' => 'Using the browser\'s default HTML5 player – no controls, autoplay, muted & looped',
      ],
    ];

    // If the universal 'remotely referenced thumbnail image' option is the
    // default embed style of this formatter used for this field type, then
    // add it to all media types as it's independent from them.
    $field_definition = \Drupal::service('plugin.manager.field.field_type')->getDefinition('acquia_dam_asset');

    if (isset($field_definition['default_formatter'])) {
      $default_formatter_settings = \Drupal::service('plugin.manager.field.formatter')->getDefaultSettings('acquia_dam_embed_code');

      if (isset($default_formatter_settings['embed_style'])) {
        $default_embed_style = $default_formatter_settings['embed_style'];
        $remote_thumbnail_embed_style_name = 'remotely_referenced_thumbnail_image';

        if ($default_embed_style === $remote_thumbnail_embed_style_name) {
          $thumbnail_image_option = [$remote_thumbnail_embed_style_name => 'Thumbnail image of the finalized asset version, or the alternate preview if set'];
          if ($asset_type) {
            foreach ($mapping as $key => $value) {
              $mapping[$key] += $thumbnail_image_option;
            }
          }
          else {
            $mapping['General'] = $thumbnail_image_option;
          }
        }
      }
    }

    return $asset_type ? $mapping[$asset_type] : $mapping;
  }

  /**
   * Returns a render array based on the parameters.
   *
   * @param string $format
   *   Embed code id for formatting.
   * @param \Drupal\media\MediaInterface $media
   *   Media instance.
   * @param int|null $thumbnail_width
   *   (optional) Asset thumbnail width to render.
   *
   * @return array
   *   Render array.
   *
   * @throws \Drupal\acquia_dam\Exception\DamServerException
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public static function renderAsset(string $format, MediaInterface $media, ?int $thumbnail_width = 150): array {
    $embed_code = [];

    /** @var \Drupal\acquia_dam\Plugin\media\Source\Asset $source */
    $source = $media->getSource();
    $asset_field = $media->get(MediaSourceField::SOURCE_FIELD_NAME)->first();
    assert($asset_field instanceof AssetItem);

    // Fetch alt text from the media entity, fallback to media label if not available.
    $alt_text = $media->hasField(ImageAltTextField::IMAGE_ALT_TEXT_FIELD_NAME)
      ? $media->get(ImageAltTextField::IMAGE_ALT_TEXT_FIELD_NAME)->value ?? $media->label()
      : $media->label();

    // Providing the embed code of the thumbnail image is independent from the
    // media type.
    if ($format === 'remotely_referenced_thumbnail_image') {
      $remote_thumbnail_uri = $source->getMetadata($media, 'thumbnail_uri_remote');

      return [
        '#theme' => 'image',
        '#uri' => preg_replace('/\/150(px@2x\/\?q=)/', "/{$thumbnail_width}$1", $remote_thumbnail_uri),
        '#alt' => $alt_text,
        '#attributes' => [
          'width' => $thumbnail_width,
        ],
      ];
    }

    // Get the domain for the embed code.
    $embed_codes = $source->getMetadata($media, 'embeds');
    $original = parse_url($embed_codes['original']['url']);
    $domain = "https://" . $original['host'];
    $external_id = $source->getMetadata($media, 'external_id');

    switch ($source->getDerivativeId()) {
      case 'audio':
        $embed_code = self::renderAudioAsset($domain, $external_id, $format, $media->getName(), self::encodeFilename($media->getName()));
        break;

      case 'documents':
      case 'pdf':
        $embed_code = self::renderDocumentsPdfAsset($domain, $external_id, $format, $media->getName(), self::encodeFilename($media->getName()));
        break;

      case 'video':
        $embed_code = self::renderVideoAsset($domain, $external_id, $format, $media->getName(), self::encodeFilename($media->getName()));
        break;

      case 'spinset':
        $embed_code = self::renderSpinsetAsset($domain, $external_id, $format, $media->getName(), self::encodeFilename($media->getName()));
        break;

      case 'image':
        $image_properties = $source->getMetadata($media, 'image_properties');
        if ($format == 'original') {
          $embed_code = [
            '#theme' => 'image',
            '#uri' => "acquia-dam://$asset_field->asset_id/$asset_field->version_id",
            // @todo fetch alt from metadata for a proper value.
            '#alt' => $alt_text,
            '#width' => $image_properties['width'],
            '#height' => $image_properties['height'],
          ];
        }
        else {
          $embed_code = [
            '#theme' => 'image_style',
            '#uri' => "acquia-dam://$asset_field->asset_id/$asset_field->version_id.png",
            '#style_name' => $format,
            // @todo fetch alt from metadata for a proper value.
            '#alt' => $alt_text,
            '#width' => $image_properties['width'],
            '#height' => $image_properties['height'],
          ];
        }
        break;

      default:
        $embed_code = self::renderAsLink($embed_codes['original']['url'], $media->getName());
    }

    return $embed_code;
  }

  /**
   * Returns an audio asset render array based on the parameters.
   *
   * @param string $domain
   *   Domain.
   * @param string $external_id
   *   Asset external id.
   * @param string $format
   *   Embed code id for formatting.
   * @param string $filename
   *   Media name.
   * @param string $encoded_filename
   *   URL encoded filename.
   *
   * @return array
   *   Render array.
   */
  protected static function renderAudioAsset(string $domain, string $external_id, string $format, string $filename, string $encoded_filename): array {
    $build = [
      '#type' => 'html_tag',
      '#tag' => 'audio',
      '#attributes' => [
        'controls' => TRUE,
        'oncontextmenu' => 'return false;',
        'preload' => 'metadata',
        'title' => t('Listen audio file "@filename"', ['@filename' => $encoded_filename]),
      ],
      'children' => [
        '#type' => 'html_tag',
        '#tag' => 'source',
        '#attributes' => [
          'type' => 'audio/mpeg',
          'src' => "$domain/content/$external_id/mp3/$filename",
        ],
      ],
    ];

    if (!str_contains($format, 'download')) {
      $build['#attributes']['controlsList'] = 'nodownload';
    }

    return $build;
  }

  /**
   * Returns a pdf/document asset render array based on the parameters.
   *
   * @param string $domain
   *   Domain.
   * @param string $external_id
   *   Asset external id.
   * @param string $format
   *   Embed code id for formatting.
   * @param string $filename
   *   Media name.
   * @param string $encoded_filename
   *   URL encoded filename.
   *
   * @return array
   *   Render array.
   */
  protected static function renderDocumentsPdfAsset(string $domain, string $external_id, string $format, string $filename, string $encoded_filename) :array {
    switch ($format) {
      case 'original':
        $url = "{$domain}/content/{$external_id}/original/{$encoded_filename}?download=true";
        $embed = self::renderAsLink($url, $filename);
        break;

      case 'inline_view_download':
        $embed = [
          '#type' => 'html_tag',
          '#tag' => 'iframe',
          '#attributes' => [
            'src' => "{$domain}/view/pdf/{$external_id}/{$encoded_filename}?t.download=true",
            'title' => "Document for $filename",
            'webkitallowfullscreen' => '',
            'mozallowfullscreen' => '',
            'allowfullscreen' => '',
          ],
        ];
        break;

      case 'inline_view':
        $embed = [
          '#type' => 'html_tag',
          '#tag' => 'iframe',
          '#attributes' => [
            'src' => "{$domain}/content/{$external_id}/pdf/{$encoded_filename}",
            'title' => "Document for $filename",
            'webkitallowfullscreen' => '',
            'mozallowfullscreen' => '',
            'allowfullscreen' => '',
          ],
        ];
        break;

      case 'link_text_download':
        $url = "{$domain}/view/pdf/{$external_id}/{$encoded_filename}?t.download=true";
        $embed = self::renderAsLink($url, $filename);
        break;

      case 'link_text':
        $url = "{$domain}/content/{$external_id}/pdf/{$encoded_filename}";
        $embed = self::renderAsLink($url, $filename);
        break;

      case 'link_thumbnail_download':
        $filename_without_extension = self::getFilenameWithoutExtension($encoded_filename);

        $asset_url = "{$domain}/view/pdf/{$external_id}/{$encoded_filename}?t.download=true";
        $thumbnail_url = "{$domain}/content/{$external_id}/jpeg/{$filename_without_extension}.jpg";

        $embed = self::renderAsThumbnailLink($asset_url, $thumbnail_url, $filename);
        break;

      case 'link_thumbnail':
        $filename_without_extension = self::getFilenameWithoutExtension($encoded_filename);

        $asset_url = "{$domain}/view/pdf/{$external_id}/{$filename}?t.download=true";
        $thumbnail_url = "{$domain}/content/{$external_id}/jpeg/{$filename_without_extension}.jpg";

        $embed = self::renderAsThumbnailLink($asset_url, $thumbnail_url, $filename);
        break;

      default:
        $embed = [];
    }

    return $embed;
  }

  /**
   * Returns a video asset render array based on the parameters.
   *
   * @param string $domain
   *   Domain.
   * @param string $external_id
   *   Asset external id.
   * @param string $format
   *   Embed code id for formatting.
   * @param string $filename
   *   Filename.
   * @param string $encoded_filename
   *   URL encoded filename.
   *
   * @return array
   *   Render array.
   */
  protected static function renderVideoAsset(string $domain, string $external_id, string $format, string $filename, string $encoded_filename): array {
    switch ($format) {
      case 'original':
        $url = "{$domain}/content/{$external_id}/original/{$encoded_filename}?download=true";
        $embed = self::renderAsLink($url, $filename);
        break;

      case 'inline_view_download':
        $embed = [
          '#theme' => 'acquia_dam_iframe_responsive',
          '#src' => "{$domain}/view/video/{$external_id}/{$encoded_filename}?t.download=true",
        ];
        break;

      case 'inline_view':
        $embed = [
          '#theme' => 'acquia_dam_iframe_responsive',
          '#src' => "{$domain}/view/video/{$external_id}/{$encoded_filename}",
        ];
        break;

      case 'link_thumbnail_download':
        $filename_without_extension = self::getFilenameWithoutExtension($encoded_filename);

        $asset_url = "{$domain}/view/video/{$external_id}/{$encoded_filename}?t.download=true";
        $thumbnail_url = "{$domain}/content/{$external_id}/jpeg/{$filename_without_extension}.jpg";

        $embed = self::renderAsThumbnailLink($asset_url, $thumbnail_url, $filename);
        break;

      case 'link_thumbnail':
        $filename_without_extension = self::getFilenameWithoutExtension($encoded_filename);

        $asset_url = "{$domain}/view/video/{$external_id}/{$encoded_filename}";
        $thumbnail_url = "{$domain}/content/{$external_id}/jpeg/{$filename_without_extension}.jpg";

        $embed = self::renderAsThumbnailLink($asset_url, $thumbnail_url, $filename);
        break;

      case 'video_stream':
        $embed = [
          '#theme' => 'acquia_dam_video_stream',
          '#attributes' => [
            "controls" => "controls",
          ],
          '#source_attributes' => [
            'src' => "{$domain}/content/{$external_id}/mp4/{$encoded_filename}?quality=hd",
            'video' => "video/mp4",
          ],
        ];
        break;

      case 'video_stream_muted_looped_autoplay':
        $embed = [
          '#theme' => 'acquia_dam_video_stream',
          '#attributes' => [
            "muted" => TRUE,
            "autoplay" => TRUE,
            "loop" => TRUE,
            "playsinline" => TRUE,
          ],
          '#source_attributes' => [
            'src' => "{$domain}/content/{$external_id}/mp4/{$encoded_filename}?quality=hd",
            'video' => "video/mp4",
          ],
        ];
        break;

      default:
        $embed = [];
    }

    return $embed;
  }

  /**
   * Returns a spinset asset render array based on the parameters.
   *
   * @param string $domain
   *   Domain.
   * @param string $external_id
   *   Asset external id.
   * @param string $format
   *   Embed code id for formatting.
   * @param string $filename
   *   Filename.
   * @param string $encoded_filename
   *   URL encoded filename.
   *
   * @return array
   *   Render array.
   */
  protected static function renderSpinsetAsset(string $domain, string $external_id, string $format, string $filename, string $encoded_filename): array {
    switch ($format) {
      case 'original':
        $embed = [
          '#type' => 'markup',
          '#markup' => "{$domain}/content/{$external_id}/original/{$encoded_filename}?download=true",
        ];
        break;

      case 'inline_view':
        $embed = [
          '#theme' => 'acquia_dam_iframe_responsive',
          '#src' => "{$domain}/view/spinset/{$external_id}/{$encoded_filename}",
        ];
        break;

      case 'link_text':
        $url = "{$domain}/view/spinset/{$external_id}/{$encoded_filename}";
        $embed = self::renderAsLink($url, $filename);
        break;

      default:
        $embed = [];
    }

    return $embed;
  }

  /**
   * Returns link pointing to the given asset embed.
   *
   * @param string $url
   *   Link url.
   * @param string $asset_name
   *   Asset name as link name.
   *
   * @return array
   *   Render array.
   */
  protected static function renderAsLink(string $url, string $asset_name): array {
    $link = Link::fromTextAndUrl($asset_name, Url::fromUri($url, ['attributes' => ['target' => '_blank']]));
    $build = $link->toRenderable();
    $build['#post_render'] = [[static::class, 'postRenderDamLink']];
    $build['#attributes']['title'] = t('Download the file of asset "@asset_name"', ['@asset_name' => $asset_name]);

    return $build;
  }

  /**
   * Returns thumbnail as a link.
   *
   * @param string $url
   *   Asset url.
   * @param string $thumbnail_url
   *   Thumbnail url.
   * @param string $name
   *   File name.
   *
   * @return array
   *   Render array.
   */
  protected static function renderAsThumbnailLink(string $url, string $thumbnail_url, string $name): array {
    $link_title = [
      '#theme' => 'image',
      '#width' => 300,
      '#height' => 300,
      '#uri' => $thumbnail_url,
      '#alt' => sprintf('%s preview', $name),
    ];

    return [
      '#type' => 'container',
      '#theme_wrappers' => ['container__acquia_dam_asset'],
      'embed' => [
        '#type' => 'link',
        '#title' => $link_title,
        '#url' => Url::fromUri($url, ['attributes' => ['target' => '_blank']]),
        '#post_render' => [[static::class, 'postRenderDamLink']],
      ],
    ];
  }

  /**
   * Replace t_download within the markup with the correct key.
   *
   * @param string $markup
   *   Link markup generated for embed code.
   *
   * @return string
   *   Markup.
   */
  public static function postRenderDamLink(string $markup) {
    if (!str_contains($markup, 't_download')) {
      return $markup;
    }

    return str_replace('t_download', 't.download', $markup);
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['postRenderDamLink'];
  }

  /**
   * Replaces certain characters in the filename so it matches the DAM ones.
   *
   * @param string $filename
   *   Media name.
   *
   * @return string
   *   Returns filename.
   */
  private static function encodeFilename(string $filename) {
    return str_replace(
      [' ', "'", '"'],
      ['-', '', ''],
      $filename
    );
  }

  /**
   * Gets a filename without the extension.
   *
   * @param string $filename
   *   Media name.
   *
   * @return string
   *   Returns filename without the extension.
   */
  private static function getFilenameWithoutExtension(string $filename) {
    $filename_without_extension = pathinfo($filename, PATHINFO_FILENAME);
    $filename_without_extension = urlencode($filename_without_extension);

    return $filename_without_extension;
  }

}
