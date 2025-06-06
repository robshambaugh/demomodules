(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.MediaLibrarySourceTabs = {
    attach: function attach(context) {
      const source_field = once(
        'js-acquia-dam-source-field',
        '.js-acquia-dam-source-field',
        context,
      ).shift();
      if (source_field) {
        source_field.addEventListener('change', function (e) {
          // Prevent the user to re-trigger the ajax while on progress.
          e.currentTarget.classList.add('disable-select')
          const ajaxObject = Drupal.ajax({
            wrapper: 'media-library-wrapper',
            url: drupalSettings.media_library[e.target.value],
            dialogType: 'ajax',
            progress: {
              type: 'throbber',
            },
          });
          ajaxObject.execute();
        });
      }
    },
  };
})(jQuery, Drupal, drupalSettings);
