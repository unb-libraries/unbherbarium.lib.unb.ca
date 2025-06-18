(function ($, Drupal, once) {
  Drupal.behaviors.colorboxForce = {
    attach: function (context, settings) {
      once('colorboxForce', '.colorbox', context).forEach(function (el) {
        $(el).colorbox();
      });
    }
  };
})(jQuery, Drupal, once);