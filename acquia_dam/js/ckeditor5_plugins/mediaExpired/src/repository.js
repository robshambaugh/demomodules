import { Plugin } from 'ckeditor5/src/core';


export default class MediaExpiredRepository extends Plugin {

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'MediaExpiredRepository';
  }

  /**
   * @inheritdoc
   */
  init() {
    this._data = new WeakMap();
  }

  getRevisionMetadata(modelElement) {
    if (this._data.get(modelElement)) {
      return new Promise((resolve) => {
        resolve(this._data.get(modelElement));
      });
    }
    const { mediaExpiredCheckUrl, acquiaDamCsrfToken } = this.editor.config.get('drupalMedia');
    const mediaUuid = modelElement.getAttribute('drupalMediaEntityUuid');
    return fetch(`${mediaExpiredCheckUrl}?uuid=${mediaUuid}`, {
      headers: {
        'X-Drupal-AcquiaDam-CSRF-Token': acquiaDamCsrfToken
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
