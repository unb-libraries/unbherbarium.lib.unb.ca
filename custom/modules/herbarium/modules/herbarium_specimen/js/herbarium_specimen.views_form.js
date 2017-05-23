jQuery(document).ready(function () {
    // Workaround: change Exposed filters type from "text' to 'date' for mindate & maxdate fields.
    jQuery('#edit-mindate').prop('type', 'date');
    jQuery('#edit-maxdate').prop('type', 'date');
});
