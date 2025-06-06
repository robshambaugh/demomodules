import { Plugin } from 'ckeditor5/src/core';
import { getPreviewContainer } from '../../../../../../../core/modules/ckeditor5/js/ckeditor5_plugins/drupalMedia/src/utils'

export default class MediaEmbedCodeEditing extends Plugin {
  /**
   * @inheritdoc
   */
  static get requires() {
    return ['DrupalMediaEditing', 'DamMediaEmbedCodeEditing'];
  }

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'MediaEmbedCodeEditing';
  }

  /**
   * @inheritdoc
   */
  init() {
    const { editor } = this;
    editor.model.schema.extend('drupalMedia', {
      allowAttributes: ['embedCodeId', 'data-embed-code-id'],
    });
    const mediaEditing = editor.plugins.get('DrupalMediaEditing');
    mediaEditing.attrs['embedCodeId'] = 'data-embed-code-id';

    // Copied from drupalmediaediting so that the preview is refreshed whenever
    // our embed code attribute is changed.
    // @todo remove after https://www.drupal.org/i/3300246.
    const conversion = this.editor.conversion;
    conversion
      .for('editingDowncast')
      .add((dispatcher) => {
        const converter = (event, data, conversionApi) => {
          const viewWriter = conversionApi.writer;
          const modelElement = data.item;
          const container = conversionApi.mapper.toViewElement(data.item);

          // Search for preview container recursively from its children because
          // the preview container could be wrapped with an element such as
          // `<a>`.
          let media = getPreviewContainer(container.getChildren());

          // Use pre-existing media preview container if one exists. If the
          // preview element doesn't exist, create a new element.
          if (media) {
            // Stop processing if media preview is unavailable or a preview is
            // already loading.
            if (media.getAttribute('data-drupal-media-preview') !== 'ready') {
              return;
            }

            // Preview was ready meaning that a new preview can be loaded.
            // "Change the attribute to loading to prepare for the loading of
            // the updated preview. Preview is kept intact so that it remains
            // interactable in the UI until the new preview has been rendered.
            viewWriter.setAttribute(
              'data-drupal-media-preview',
              'loading',
              media,
            );
          } else {
            media = viewWriter.createRawElement('div', {
              'data-drupal-media-preview': 'loading',
            });
            viewWriter.insert(viewWriter.createPositionAt(container, 0), media);
          }

          mediaEditing._fetchPreview(modelElement).then(({ label, preview }) => {
            if (!media) {
              // Nothing to do if associated preview wrapped no longer exist.
              return;
            }
            // CKEditor 5 doesn't support async view conversion. Therefore, once
            // the promise is fulfilled, the editing view needs to be modified
            // manually.
            this.editor.editing.view.change((writer) => {
              const mediaPreview = writer.createRawElement(
                'div',
                { 'data-drupal-media-preview': 'ready', 'aria-label': label },
                (domElement) => {
                  domElement.innerHTML = preview;
                },
              );
              // Insert the new preview before the previous preview element to
              // ensure that the location remains same even if it is wrapped
              // with another element.
              writer.insert(writer.createPositionBefore(media), mediaPreview);
              writer.remove(media);
            });
          });
        };
        dispatcher.on(
          'attribute:drupalElementStyleMediaEmbedCode:drupalMedia',
          converter
        )
      })
  }
}
