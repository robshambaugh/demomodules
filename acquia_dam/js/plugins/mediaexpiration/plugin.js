(function ($, Drupal, CKEDITOR) {
  CKEDITOR.plugins.add('acquia_dam_mediaexpiration', {
    requires: 'drupalmedia',
    beforeInit(editor) {
      function setupPopper(widget) {
        const expire_item = widget.element.findOne('.acquia-dam-expired-asset');
        const popperElement = widget.element.findOne('.acquia-dam-asset-expired__popper');
        // Hide tooltip by default.
        popperElement.addClass('visually-hidden');
        // @todo replace with Floating UI on Drupal 10.
        if (typeof window.Popper === 'undefined') {
          return;
        }
        const popperInstance = window.Popper.createPopper(
          expire_item,
          popperElement,
        );

        // Show tooltip on focus.
        expire_item.on('mouseenter', function () {
          popperElement.removeClass('visually-hidden')
          popperInstance.update();
        });
        expire_item.on('focus', (event)=> {
          popperElement.removeClass('visually-hidden')
          popperInstance.update();
        });

        // Hide tooltip on focus out.
        expire_item.on('mouseleave', function () {
          popperElement.addClass('visually-hidden');
        });
      }
      function setupExpirationTag(widget) {
        if (widget.element.findOne('.acquia-dam-expired-asset-container')) {
          return;
        }
        const embeddedMedia = getEmbeddedMediaElement(widget)
        var p = new CKEDITOR.dom.element( 'div' ).addClass('acquia-dam-ck-editor');
        p.setHtml(`<div class="acquia-dam-expired-asset-container"><div class="acquia-dam-expired-asset"><div class="acquia-dam-asset-expired__popper">${Drupal.t('Expired media is not visible to content viewers, replace the media.')}</div></div><span class="acquia-dam-asset-expired__label">${Drupal.t('Expired media')}</span></div>` );

        embeddedMedia.getFirst().getNext().insertBeforeMe(p);
        setupPopper(widget);
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
        const originalData = widgetDefinition.data;
        widgetDefinition.data = function (event) {
          // Use a mutation observer to handle async preview performed by the
          // `drupalmedia` plugin.
          const mutationObserver = new MutationObserver((mutationList, observer) => {
            mutationObserver.disconnect();
            if (this.element.getChildCount() === 0) {
              return;
            }

            $.get({
              url: Drupal.url(`acquia-dam/${editor.config.drupal.format}/media-expiration`),
              data: {
                uuid: this.data.attributes['data-entity-uuid'],
              },
              dataType: 'json',
              headers: {
                'X-Drupal-AcquiaDam-CSRF-Token':
                editor.config.drupalMedia_revisionCsrfToken,
              },
              success: (res) => {
                if (res.hasOwnProperty('isExpired') && res.isExpired) {
                  editor.fire('lockSnapshot');
                  setupExpirationTag(this)
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
