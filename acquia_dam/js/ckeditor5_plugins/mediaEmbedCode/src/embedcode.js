import { Plugin } from 'ckeditor5/src/core';

export default class DamMediaEmbedCodeEditing extends Plugin {

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'DamMediaEmbedCodeEditing';
  }

  /**
   * @inheritdoc
   */
  init() {
    const editor = this.editor;

    const getDropdownElements = (callback) => {
      const checkElements = () => {
        const viewModeDropdown = document.querySelector('[data-cke-tooltip-text="View mode"]');
        const embedCodeDropdown = document.querySelector('[data-cke-tooltip-text="Embed code"]');

        if (viewModeDropdown || embedCodeDropdown) {
          callback(viewModeDropdown, embedCodeDropdown);
        } else {
          setTimeout(checkElements, 100); // Retry after 100ms
        }
      };

      checkElements();
    };

    const hasMediaEmbedCode = (mediaElements) => {
      return mediaElements.some(element => element.name === 'drupalMedia' && element.getAttribute('drupalElementStyleMediaEmbedCode'));
    };

    const toggleDropdownVisibility = (viewModeDropdown, embedCodeDropdown, hasEmbedCode) => {
      if (viewModeDropdown) viewModeDropdown.style.display = hasEmbedCode ? 'none' : '';
      if (embedCodeDropdown) embedCodeDropdown.style.display = hasEmbedCode ? '' : 'none';
    };

    const updateDropdownVisibility = () => {
      setTimeout(() => {
        getDropdownElements((viewModeDropdown, embedCodeDropdown) => {
          const mediaElements = Array.from(editor.model.document.getRoot().getChildren());
          const hasEmbedCode = hasMediaEmbedCode(mediaElements);
          toggleDropdownVisibility(viewModeDropdown, embedCodeDropdown, hasEmbedCode);
        });
      });
    };

    const handleSelectionChange = () => {
      const selectedElement = editor.model.document.selection.getSelectedElement();
      if (selectedElement && selectedElement.name === 'drupalMedia') {
        getDropdownElements((viewModeDropdown, embedCodeDropdown) => {
          const embedCodeAttribute = selectedElement.getAttribute('drupalElementStyleMediaEmbedCode');
          toggleDropdownVisibility(viewModeDropdown, embedCodeDropdown, !!embedCodeAttribute);
        });
      }
    };

    // Register event listeners.
    editor.model.document.on('change', handleSelectionChange);
    editor.model.on('deleteContent', updateDropdownVisibility);
    editor.model.on('insertContent', updateDropdownVisibility);

    // Initial check on initialization.
    updateDropdownVisibility();
  }
}
