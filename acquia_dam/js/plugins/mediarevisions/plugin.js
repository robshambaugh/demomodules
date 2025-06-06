(function ($, Drupal, CKEDITOR) {
  CKEDITOR.plugins.add('acquia_dam_mediarevisions', {
    requires: 'drupalmedia',
    beforeInit(editor) {
      function setupUpdateButton(widget) {
        // The preview was not regenerated, so our button already exists.
        // @see `_previewNeedsServerSideUpdate` in `drupalmedia`
        if (widget.element.findOne('.media-library-item__version')) {
          return;
        }
        const embeddedMedia = getEmbeddedMediaElement(widget)
        const editButton = CKEDITOR.dom.element.createFromHtml(
          `<button class="media-library-item__version">${Drupal.t('Update media')}</button>`,
        );
        embeddedMedia.getFirst().getNext().insertBeforeMe(editButton);
        widget.element
          .findOne('.media-library-item__version')
          .on('click', event => {
            const saveCallback = values => {
              event.cancel();
              editor.fire('saveSnapshot');
              if (values.hasOwnProperty('attributes')) {
                CKEDITOR.tools.extend(
                  values.attributes,
                  widget.data.attributes,
                );
              }
              widget.setData({
                attributes: values.attributes,
              });
              editor.fire('saveSnapshot');
            }
            Drupal.ckeditor.openDialog(
              editor,
              Drupal.url(`editor/dialog/media-revisions/${editor.config.drupal.format}`),
              widget.data,
              saveCallback,
              {
                dialogClass: 'media-revisions-dialog',
              },
            )
          });
        // Allow opening the dialog with the return key or the space bar
        // by triggering a click event when a keydown event occurs on
        // the edit button.
        widget.element
          .findOne('.media-library-item__version')
          .on('keydown', (event) => {
            // The character code for the return key.
            const returnKey = 13;
            // The character code for the space bar.
            const spaceBar = 32;
            if (typeof event.data !== 'undefined') {
              const keypress = event.data.getKey();
              if (keypress === returnKey || keypress === spaceBar) {
                // Clicks the edit button that triggered the 'keydown'
                // event.
                event.sender.$.click();
              }
              // Stop propagation to keep the return key from
              // adding a line break.
              event.data.$.stopPropagation();
              event.data.$.stopImmediatePropagation();
            }
          });
      }

      function getEmbeddedMediaElement(widget) {
        const embeddedMediaContainer = widget.data.hasCaption
          ? widget.element.findOne('figure')
          : widget.element;
        let embeddedMedia = embeddedMediaContainer.getFirst(n => n.type === CKEDITOR.NODE_ELEMENT);
        if (widget.data.link) {
          embeddedMedia = embeddedMedia.getFirst(n => n.type === CKEDITOR.NODE_ELEMENT);
        }
        return embeddedMedia;
      }

      editor.on('widgetDefinition', function (event) {
        const widgetDefinition = event.data;
        if (widgetDefinition.name !== 'drupalmedia') {
          return;
        }
        widgetDefinition.allowedContent['drupal-media'].attributes['data-entity-revision'] = true;

        const originalData = widgetDefinition.data;
        widgetDefinition.data = function (event) {
          // Use a mutation observer to handle async preview performed by the
          // `drupalmedia` plugin.
          const mutationObserver = new MutationObserver((mutationList, observer) => {
            mutationObserver.disconnect();
            if (this.element.getChildCount() === 0) {
              return;
            }
            const revisionId = this.data.attributes['data-entity-revision'] || '';
            if (revisionId.length === 0) {
              return;
            }

            $.get({
              url: Drupal.url(`acquia-dam/${editor.config.drupal.format}/media-revision`),
              data: {
                uuid: this.data.attributes['data-entity-uuid'],
                revisionId
              },
              dataType: 'json',
              headers: {
                'X-Drupal-AcquiaDam-CSRF-Token':
                editor.config.drupalMedia_revisionCsrfToken,
              },
              success: (res) => {
                if (res.hasOwnProperty('isLatest') && res.isLatest === false) {
                  editor.fire('lockSnapshot');
                  setupUpdateButton(this)
                  editor.fire('unlockSnapshot');
                }
              },
              error: () => {
                // Do nothing.
              },
            })
          });
          mutationObserver.observe(this.element.$, {
            // We must watch for changes to the child elements, so we know when
            // the `drupalmedia` plugin has mounted the "Edit media" button.
            childList: true,
            subtree: true,
          })

          originalData.call(this, event);
        }
      }, null, null, 10);
    },
  });
})(jQuery, Drupal, CKEDITOR);
