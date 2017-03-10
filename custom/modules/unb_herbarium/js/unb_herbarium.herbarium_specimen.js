jQuery(document).ready(function() {
    jQuery('#toggle-widget').click(function () {
        jQuery('.toggle-default-off').toggle(300);
        jQuery('.toggle-default-on').toggle(1);
    });

    jQuery('.hide').hide();
    jQuery('.toggle-filters-display').click(function() {
        jQuery('form .views-exposed-form').slideToggle(300);
    })
});
