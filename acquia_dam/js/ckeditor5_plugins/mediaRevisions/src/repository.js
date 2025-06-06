import { Plugin } from 'ckeditor5/src/core';


export default class MediaRevisionsRepository extends Plugin {

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'MediaRevisionsRepository';
  }

  /**
   * @inheritdoc
   */
  init() {
    this._data = new WeakMap();
  }

  getRevisionMetadata(modelElement) {
    if (!modelElement.hasAttribute('entityRevision')) {
      this._data.set(modelElement, {
        isLatest: true,
      })
    }

    if (this._data.get(modelElement)) {
      return new Promise((resolve) => {
        resolve(this._data.get(modelElement));
      });
    }
    const { mediaRevisionCheckUrl, revisionCsrfToken } = this.editor.config.get('drupalMedia');
    const mediaUuid = modelElement.getAttribute('drupalMediaEntityUuid');
    const entityRevision = modelElement.getAttribute('entityRevision');
    return fetch(`${mediaRevisionCheckUrl}?uuid=${mediaUuid}&revisionId=${entityRevision}`, {
      headers: {
        'X-Drupal-AcquiaDam-CSRF-Token': revisionCsrfToken
      }
    })
      .then(res => res.json())
      .then(json => {
        this._data.set(modelElement, json)
        return json
      })
  }

  refreshModelMetadata(modelElement) {
    this._data.delete(modelElement)
    return this.getRevisionMetadata(modelElement)
  }
}
