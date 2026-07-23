 /**
 * @file
 * Global subtheme JS functions.
 */
(function($, Drupal) {
   // See: https://www.drupal.org/project/colorbox/issues/3529726,
   // Interim fix: restore missing helper method for plugins that was removed from jQuery 4.x.
    if (typeof jQuery !== 'undefined' && !jQuery.isFunction) {
        jQuery.isFunction = function (obj) {
            return typeof obj === "function";
        };
    }
})(jQuery, Drupal);
