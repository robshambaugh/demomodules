import { Plugin } from 'ckeditor5/src/core';
import UpdateMediaExpiredCommand from './command'
import MediaExpiredRepository from './repository'
import { Template } from 'ckeditor5/src/ui'

export default class MediaExpiredEditing extends Plugin {

  static get requires() {
    return ['DrupalMediaEditing', MediaExpiredRepository];
  }

  static get pluginName() {
    return 'MediaExpiredEditing';
  }

  init() {
    const {editor} = this;
    editor.model.schema.extend('drupalMedia', {
      allowAttributes: ['mediaIsExpired'],
    });

    const { conversion } = editor;
    conversion.for('upcast')
      .add((dispatcher) => {
        dispatcher.on(
          'element:drupal-media',
          (event, data) => {
            const [modelElement] = data.modelRange.getItems();
            const metadataRepository = this.editor.plugins.get(
              'MediaExpiredRepository',
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
                      'mediaIsExpired',
                      metadata.isExpired,
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
      dispatcher.on(
        'attribute:mediaIsExpired',
        (event, data) => {
          if (data.attributeOldValue === null || data.attributeOldValue === data.attributeNewValue) {
            return;
          }
          const metadataRepository = this.editor.plugins.get(
            'MediaExpiredRepository',
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
                    'mediaIsExpired',
                    metadata.isExpired,
                    data.item,
                  );
                },
              );
            })
        },
      )
      dispatcher.on(
        'attribute:mediaIsExpired',
        (event, data, conversionApi) => {
          const { writer, mapper } = conversionApi;
          const container = mapper.toViewElement(data.item);
          if (data.attributeNewValue === false) {
            const existingError = Array.from(container.getChildren()).find(
              (child) => child.getCustomProperty('mediaExpiredWarning'),
            );
            if (existingError) {
              writer.remove(existingError)
            }
            return;
          }

          const message = Drupal.t(
            'This media item is expired. Expired media is not visible to content viewers, replace the media.',
          );

          const html = new Template({
            tag: 'span',
            children: [
              {
                tag: 'span',
                attributes: {
                  class: 'drupal-media__mediaexpired-alert-icon',
                  'data-cke-tooltip-text': message,
                },
              },
            ],
          }).render();
          const error = writer.createRawElement(
            'div',
            {
              class: 'drupal-media__mediaexpired-alert',
            },
            (domElement, domConverter) => {
              domConverter.setContentOf(domElement, html.outerHTML);
            },
          );
          writer.setCustomProperty('mediaExpiredWarning', true, error);

          writer.insert(writer.createPositionAt(container, 0), error);
        },
        { priority: 'low' },
      );
    });

    editor.commands.add(
      'updateMediaExpired',
      new UpdateMediaExpiredCommand(editor),
    );
  }

}
