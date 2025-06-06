import { Plugin } from 'ckeditor5/src/core';
import { Template } from 'ckeditor5/src/ui';
import UpdateMediaRevisionCommand from './command'
import MediaRevisionsRepository from './repository'
import { getPreviewContainer } from '../../../../../../../core/modules/ckeditor5/js/ckeditor5_plugins/drupalMedia/src/utils'

export default class MediaRevisionsEditing extends Plugin {

  static get requires() {
    return ['DrupalMediaEditing', MediaRevisionsRepository];
  }

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'MediaRevisionsEditing';
  }

  init() {
    const {editor} = this;
    editor.model.schema.extend('drupalMedia', {
      allowAttributes: ['entityRevision', 'entityIsLatestRevision'],
    });
    const mediaEditing = editor.plugins.get('DrupalMediaEditing');
    mediaEditing.attrs['entityRevision'] = 'data-entity-revision';

    const { conversion } = editor;
    const attributeMapping = {
      model: {
        key: 'entityRevision',
        name: 'drupalMedia',
      },
      view: {
        name: 'drupal-media',
        key: 'data-entity-revision',
      },
    };
    conversion.for('dataDowncast').attributeToAttribute(attributeMapping);
    conversion.for('upcast')
      .attributeToAttribute(attributeMapping)
      .add((dispatcher) => {
        dispatcher.on(
          'element:drupal-media',
          (event, data) => {
            const [modelElement] = data.modelRange.getItems();
            const metadataRepository = this.editor.plugins.get(
              'MediaRevisionsRepository',
            );
            metadataRepository
              .getRevisionMetadata(modelElement)
              .then(metadata => {
                if (!modelElement) {
                  return;
                }
                editor.model.enqueueChange(
                  { isUndoable: false },
                  (writer) => {
                    writer.setAttribute(
                      'entityIsLatestRevision',
                      metadata.isLatest,
                      modelElement,
                    );
                  },
                );
              })
          },
          { priority: 'lowest' },
        )
      })
    conversion.for('editingDowncast').add((dispatcher) => {
      // Copied from drupalmediaediting so that the preview is refreshed whenever
      // our embed code attribute is changed.
      // @todo remove after https://www.drupal.org/i/3300246.
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
        'attribute:entityRevision',
        (event, data) => {
          if (data.attributeOldValue === null || data.attributeOldValue === data.attributeNewValue) {
            return;
          }
          const metadataRepository = this.editor.plugins.get(
            'MediaRevisionsRepository',
          );
          metadataRepository
            .refreshModelMetadata(data.item)
            .then(metadata => {
              if (!data.item) {
                return;
              }
              editor.model.enqueueChange(
                { isUndoable: false },
                (writer) => {
                  writer.setAttribute(
                    'entityIsLatestRevision',
                    metadata.isLatest,
                    data.item,
                  );
                },
              );
            })
        },
      )
      dispatcher.on(
        'attribute:entityRevision',
        converter
      )
      dispatcher.on(
        'attribute:entityIsLatestRevision',
        (event, data, conversionApi) => {
          const { writer, mapper } = conversionApi;
          const container = mapper.toViewElement(data.item);

          if (data.attributeNewValue === true) {
            const existingError = Array.from(container.getChildren()).find(
              (child) => child.getCustomProperty('entityRevisionWarning'),
            );
            if (existingError) {
              writer.remove(existingError)
            }
            return;
          }

          const message = Drupal.t(
            'This media item has a newer version available.',
          );

          const html = new Template({
            tag: 'span',
            children: [
              {
                tag: 'span',
                attributes: {
                  class: 'drupal-media__mediarevision-update-icon',
                  'data-cke-tooltip-text': message,
                },
              },
            ],
          }).render();
          const error = writer.createRawElement(
            'div',
            {
              class: 'drupal-media__mediarevision-update',
            },
            (domElement, domConverter) => {
              domConverter.setContentOf(domElement, html.outerHTML);
            },
          );
          writer.setCustomProperty('entityRevisionWarning', true, error);

          writer.insert(writer.createPositionAt(container, 0), error);
        },
        { priority: 'low' },
      );
    });

    editor.commands.add(
      'updateMediaRevision',
      new UpdateMediaRevisionCommand(editor),
    );
  }

}
