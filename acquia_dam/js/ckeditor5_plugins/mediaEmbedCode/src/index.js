import { Plugin } from 'ckeditor5/src/core';
import DamMediaEmbedCodeEditing from "./embedcode";
import MediaEmbedCodeEditing from './editing'
import MediaEmbedCodeUI from './ui'

class MediaEmbedCode extends Plugin {

  static get requires() {
    return [MediaEmbedCodeEditing, DamMediaEmbedCodeEditing, MediaEmbedCodeUI]
  }

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'MediaEmbedCode';
  }

}

export default {
  MediaEmbedCode,
};
