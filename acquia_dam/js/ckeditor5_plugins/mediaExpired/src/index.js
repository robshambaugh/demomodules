import { Plugin } from 'ckeditor5/src/core';
import MediaExpiredEditing from './editing'

class MediaExpired extends Plugin {

  /**
   * @inheritdoc
   */
  static get requires() {
    return [MediaExpiredEditing];
  }

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'MediaExpired';
  }
}

export default {
  MediaExpired
}
