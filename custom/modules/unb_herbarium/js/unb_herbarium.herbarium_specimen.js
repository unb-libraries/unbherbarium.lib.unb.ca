jQuery(document).ready(function() {
    jQuery('#toggle-widget').click(function () {
        jQuery('.toggle-default-off').toggle(300);
        jQuery('.toggle-default-on').toggle(1);
    });
});
