(function (Drupal, drupalSettings) {
  Drupal.behaviors.MediaLibraryResetFilters = {
    attach: function attach(context) {
      const reset_link = once(
      'acquia-dam-clear-filter',
      '.acquia-dam-clear-filter'
    ).shift()
      if (reset_link) {
        reset_link.addEventListener('click', function (e) {
          e.preventDefault()
          const inputs = document.getElementById('views-exposed-form-acquia-dam-asset-library-widget').elements;
          for (let i = 0; i < inputs.length; i++) {
            if (inputs[i].tagName === 'INPUT') {
              if (['text', 'textarea', 'select', 'hidden'].includes(inputs[i].type)) {
                inputs[i].value = "";
              }
              else if (['radio', 'checkbox'].includes(inputs[i].type)) {
                inputs[i].checked = false;
              }
            }
            else if (inputs[i].tagName === 'SELECT') {
              inputs[i].value = "";
            }
          }
          document.querySelector("input[value=Apply], input[id*=edit-submit-acquia-dam-asset-library]").click();
        });
      }
    }
  };
  })(Drupal, drupalSettings);
