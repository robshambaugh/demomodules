import { Plugin } from 'ckeditor5/src/core';
import MediaRevisionsUI from './ui'
import MediaRevisionsEditing from './editing'

class MediaRevisions extends Plugin {

  /**
   * @inheritdoc
   */
  static get requires() {
    return [MediaRevisionsEditing, MediaRevisionsUI];
  }

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'MediaRevisions';
  }
}

export default {
  MediaRevisions
}
