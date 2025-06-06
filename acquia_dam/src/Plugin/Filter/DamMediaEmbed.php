<?php

namespace Drupal\acquia_dam\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\filter\FilterProcessResult;
use Drupal\media\MediaInterface;
use Drupal\media\Plugin\Filter\MediaEmbed;

/**
 * Overwrites MediaEmbed plugin process method.
 *
 * We have to override the entire process method to be able to support loading
 * the media entity by revision, if specified.
 *
 * @todo remove after https://www.drupal.org/project/drupal/issues/3282505
 */
class DamMediaEmbed extends MediaEmbed {

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $result = new FilterProcessResult($text);

    if (stristr($text, '<drupal-media') === FALSE) {
      return $result;
    }

    $dom = Html::load($text);
    $xpath = new \DOMXPath($dom);

    foreach ($xpath->query('//drupal-media[@data-entity-type="media" and normalize-space(@data-entity-uuid)!=""]') as $node) {
      /** @var \DOMElement $node */
      $uuid = $node->getAttribute('data-entity-uuid');

      $view_mode_id = $node->getAttribute('data-view-mode') ?: $this->settings['default_view_mode'];

      // Delete the consumed attributes.
      $node->removeAttribute('data-entity-type');
      $node->removeAttribute('data-entity-uuid');
      $node->removeAttribute('data-view-mode');

      $revision_id = $node->getAttribute('data-entity-revision');
      if ($revision_id) {
        $media = $this->entityTypeManager->getStorage('media')->loadRevision($revision_id);
        // Make sure the revision ID specified belongs to the expected media.
        if ($media && $media->uuid() !== $uuid) {
          $media = NULL;
        }
      }
      else {
        $media = $this->entityRepository->loadEntityByUuid('media', $uuid);
      }
      assert($media === NULL || $media instanceof MediaInterface);
      if (!$media) {
        $this->loggerFactory->get('media')->error('During rendering of embedded media: the media item with UUID "@uuid" does not exist.', ['@uuid' => $uuid]);
      }
      else {
        $media = $this->entityRepository->getTranslationFromContext($media, $langcode);
        $media = clone $media;
        $this->applyPerEmbedMediaOverrides($node, $media);
      }

      $view_mode = NULL;
      if ($view_mode_id !== EntityDisplayRepositoryInterface::DEFAULT_DISPLAY_MODE) {
        $view_mode = $this->entityRepository->loadEntityByConfigTarget('entity_view_mode', "media.$view_mode_id");
        if (!$view_mode) {
          $this->loggerFactory->get('media')->error('During rendering of embedded media: the view mode "@view-mode-id" does not exist.', ['@view-mode-id' => $view_mode_id]);
        }
      }

      $build = $media && ($view_mode || $view_mode_id === EntityDisplayRepositoryInterface::DEFAULT_DISPLAY_MODE)
        ? $this->renderMedia($media, $view_mode_id, $langcode)
        : $this->renderMissingMediaIndicator();

      if (empty($build['#attributes']['class'])) {
        $build['#attributes']['class'] = [];
      }
      // Any attributes not consumed by the filter should be carried over to the
      // rendered embedded entity. For example, `data-align` and `data-caption`
      // should be carried over, so that even when embedded media goes missing,
      // at least the caption and visual structure won't get lost.
      foreach ($node->attributes as $attribute) {
        if ($attribute->nodeName == 'class') {
          // We don't want to overwrite the existing CSS class of the embedded
          // media (or if the media entity can't be loaded, the missing media
          // indicator). But, we need to merge in CSS classes added by other
          // filters, such as filter_align, in order for those filters to work
          // properly.
          $build['#attributes']['class'] = array_unique(array_merge($build['#attributes']['class'], explode(' ', $attribute->nodeValue)));
        }
        else {
          $build['#attributes'][$attribute->nodeName] = $attribute->nodeValue;
        }
      }

      $this->renderIntoDomNode($build, $node, $result);
    }

    $result->setProcessedText(Html::serialize($dom));

    return $result;
  }

}
