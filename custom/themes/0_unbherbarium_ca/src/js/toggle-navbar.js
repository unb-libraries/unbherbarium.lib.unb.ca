(function (Drupal, once) {
  Drupal.behaviors.toggleNavbar = {
    attach: function (context) {
      // Use `once` to ensure this is applied only once.
      once('toggle-navbar', '.navbar-toggle', context).forEach(function (button) {
        button.addEventListener('click', function () {
          const collapsible = document.querySelector('#navbar-collapse');
          if (collapsible) {
            collapsible.classList.toggle('show');
          }
        });
      });
    },
  };
})(Drupal, once);