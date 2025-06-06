import { Command } from 'ckeditor5/src/core';
import {
  getClosestSelectedDrupalMediaElement,
  isDrupalMedia
} from './../../../../../../../core/modules/ckeditor5/js/ckeditor5_plugins/drupalMedia/src/utils'

export default class UpdateMediaExpiredCommand extends Command {

  refresh() {
    const { editor } = this;
    const { selection } = editor.model.document;
    let selectedElement = selection.getSelectedElement();
    if (!selectedElement) {
      selectedElement = getClosestSelectedDrupalMediaElement(selection)
    }

    this.isEnabled = isDrupalMedia(selectedElement)
      && selectedElement.hasAttribute('mediaIsExpired')
      && selectedElement.getAttribute('mediaIsExpired');

  }
  execute(options = {}) {
    const { mediaIsExpired } = options
    const { editor } = this;
    editor.model.change((writer) => {
      const element = editor.model.document.selection.getSelectedElement();
      writer.setAttribute('mediaIsExpired', mediaIsExpired, element)
    });
  }

}
