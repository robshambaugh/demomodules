import { Plugin } from 'ckeditor5/src/core';

export default class MediaEmbedCodeUI extends Plugin {
  /**
   * @inheritdoc
   */
  static get requires() {
    return ['DrupalMediaEditing'];
  }

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'MediaEmbedCodeUI';
  }

  /**
   * @inheritdoc
   */
  init() {
    const { editor } = this;
    const viewDocument = editor.editing.view.document;

    this.listenTo(
      viewDocument,
      'click',
      (evt, data) => {
        if (this._isSelectedLinkedMedia(editor.model.document.selection)) {
          // Prevent browser navigation when clicking a linked media.
          data.preventDefault();

          // Block the `LinkUI` plugin when a media was clicked. In such a case,
          // we'd like to display the media toolbar.
          evt.stop();
        }
      },
      { priority: 'high' },
    );
  }

  /**
   * Returns true if a linked media is the only selected element in the model.
   *
   * @param {module:engine/model/selection~Selection} selection
   * @return {Boolean}
   *
   * @see DrupalLinkMediaUI._isSelectedLinkedMedia
   */
  _isSelectedLinkedMedia(selection) {
    const selectedModelElement = selection.getSelectedElement();
    return (
      !!selectedModelElement
      && selectedModelElement.is('element', 'drupalMedia')
      && selectedModelElement.hasAttribute('drupalElementStyleMediaEmbedCode')
    );
  }

}
