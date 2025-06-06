(function (Drupal, drupalSettings) {
  Drupal.behaviors.ExpiredAssets = {
    attach: function attach(context) {
      const expire_items = once(
        'acquia-dam-expired-asset',
        '.acquia-dam-expired-asset'
      )
      const popperElements = once(
        'popperElement',
        '.acquia-dam-asset-expired__popper'
      )
      expire_items.forEach((expire_item, index) => {
        let popperElement = popperElements[index];
        // Hide tooltip by default.
        popperElement.classList.add('visually-hidden');

        // @todo replace with Floating UI on Drupal 10.
        if (typeof window.Popper === 'undefined') {
          return;
        }

        const popperInstance = window.Popper.createPopper(
          expire_item,
          popperElement,
          {
            placement: 'top',
          }
        );

        // Show tooltip on focus.
        expire_item.addEventListener('mouseenter', function () {
          popperElement.classList.remove('visually-hidden');
          popperInstance.update();
        });
        expire_item.addEventListener('focus', function () {
          popperElement.classList.remove('visually-hidden');
          popperInstance.update();
        });

        // Hide tooltip on focus out.
        expire_item.addEventListener('mouseleave', function () {
          popperElement.classList.add('visually-hidden');

        });
      })
    }
}
})(Drupal, drupalSettings);
