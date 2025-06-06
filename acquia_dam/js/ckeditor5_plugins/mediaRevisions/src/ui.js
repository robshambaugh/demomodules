import { Plugin } from 'ckeditor5/src/core';
import { ButtonView } from 'ckeditor5/src/ui';

export default class MediaRevisionsUI extends Plugin {

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
    return 'MediaRevisionsUI';
  }

  init() {
    const { editor } = this;
    const options = this.editor.config.get('drupalMedia');
    const { dialogSettings = {} } = options;

    editor.ui.componentFactory.add('openMediaRevision', (locale) => {
      const button = new ButtonView(locale);
      const command = editor.commands.get('updateMediaRevision');
      button.set({
        label: Drupal.t('Update media'),
        withText: true,
      })

      button.bind('isEnabled', 'isVisible').to(command, 'isEnabled', 'isEnabled')

      const { mediaRevisionDialogUrl } = editor.config.get('drupalMedia');

      this.listenTo(button, 'execute', () => {
        const selectedDrupalMedia = editor.model.document.selection.getSelectedElement();

        // @todo use `openDialog` after it allows passing existing values.
        // @see https://www.drupal.org/project/drupal/issues/3303191.
        const classes = dialogSettings.dialogClass ? dialogSettings.dialogClass.split(' ') : []
        classes.push('ui-dialog--narrow');
        dialogSettings.dialogClass = classes.join(' ');
        dialogSettings.autoResize = window.matchMedia('(min-width: 600px)').matches;
        dialogSettings.width = 'auto';
        const ckeditorAjaxDialog = Drupal.ajax({
          dialog: dialogSettings,
          dialogType: 'modal',
          selector: '.ckeditor5-dialog-loading-link',
          url: mediaRevisionDialogUrl,
          progress: { type: 'fullscreen' },
          submit: {
            editor_object: {
              attributes: {
                'data-entity-uuid': selectedDrupalMedia.getAttribute('drupalMediaEntityUuid'),
                'data-entity-revision': selectedDrupalMedia.getAttribute('entityRevision'),
                'data-embed-code-id': selectedDrupalMedia.getAttribute('drupalElementStyleMediaEmbedCode')
              }
            },
          },
        });
        ckeditorAjaxDialog.execute();

        // Store the save callback to be executed when this dialog is closed.
        Drupal.ckeditor5.saveCallback = (data) => {
          editor.execute('updateMediaRevision', { entityRevision: data.attributes['data-entity-revision'] });
        };
      });
      return button;
    });
  }

}
