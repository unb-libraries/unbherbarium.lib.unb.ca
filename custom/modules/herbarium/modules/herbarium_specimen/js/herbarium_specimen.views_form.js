jQuery(document).ready(function () {
    // Workaround: change Exposed filters type from "text' to 'date' for mindate & maxdate fields.
    jQuery("#edit-mindate").prop("type", "date");
    jQuery("#edit-maxdate").prop("type", "date");

    jQuery("#toggle-widget").click(function () {
        jQuery(".toggle-default-off").slideToggle(300);
    });

    // Browser back button checkbox state fix.
    if (jQuery("#toggle-widget").is(":checked")) {
        jQuery(".toggle-default-off").show();
    } else {
        jQuery(".toggle-default-off").hide();
    }
});
