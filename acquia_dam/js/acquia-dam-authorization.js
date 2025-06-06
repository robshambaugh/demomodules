(function (Drupal, drupalSettings) {
  Drupal.behaviors.acquiaDamAuthorizationLink = {
    attach: function attach() {
      function ajaxMediaLibraryReplace() {
        var ajaxObject = Drupal.ajax({
          wrapper: 'media-library-wrapper',
          url: drupalSettings.media_library.url,
          dialogType: 'ajax',
          progress: {
            type: 'fullscreen',
            message: Drupal.t('Please wait...'),
          },
        });
        ajaxObject.execute();
      }

      var authLink = document.getElementById('acquia-dam-user-authorization');
      if (authLink) {
        authLink.addEventListener('click', function (event) {
          event.preventDefault();
          var authWindow = window.open(authLink.href, 'aquiaDamAuthornization', 'popup');
          var checkIfClosed = setInterval(() => {
            if (authWindow.closed) {
              clearInterval(checkIfClosed)
              var mediaTypeLink = document.querySelector('.js-media-library-menu a.media-library-menu__link.active');
              if (mediaTypeLink) {
                mediaTypeLink.click();
              } else {
                ajaxMediaLibraryReplace();
              }
            }
          }, 100)
          return false;
        });
      }
      var skipLink = document.getElementById('acquia-dam-user-authorization-skip');
      if (skipLink) {
        skipLink.addEventListener('click', function (event) {
          event.preventDefault();
          ajaxMediaLibraryReplace();
        })
      }
    }
  };
  Drupal.behaviors.acquiaDamAuthorizationClose = {
    attach: function attach() {
      if (window.opener !== null) {
        window.close();
      }
    }
  }
})(Drupal, drupalSettings);
