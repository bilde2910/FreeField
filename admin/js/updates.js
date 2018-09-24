/*
    This script file is invoked from the administration pages and is responsible
    for client-side functionality for software updates.
*/

/*
    Each available update channel has a header that can be clicked to reveal a
    body with release notes for the update and a button to trigger installation
    from that channel.
*/
$(".update-channel-head").on("click", function() {
    $(this).next(".update-channel-body").toggle();
});

/*
    Whenever such an installation button is clicked, ensure that the liability
    checkbox is unchecked. Also update the target version number on the
    installation confirmation popup dialog.

    The button then displays the confirmation dialog.
*/
$(".install-button").on("click", function() {
    $("#update-disclaimer-checkbox").prop("checked", false);
    $("#update-disclaimer-checkbox").trigger("input");
    $("#update-to-version").val($(this).attr("data-version"));
    $("#updates-install-confirmation-overlay").fadeIn(150);
});

/*
    If the user cancels the update from the confirmation dialog, uncheck the
    liability checkbox if it is checked, and hide the dialog.
*/
$("#update-button-cancel-install").on("click", function() {
    /*
        Uncheck the checkbox after a timeout to ensure that the checkbox isn't
        unchecked while the dialog is fading out.
    */
    setTimeout(function() {
        $("#update-disclaimer-checkbox").prop("checked", false);
        $("#update-disclaimer-checkbox").trigger("input");
        $("#update-to-version").val("");
    }, 150);
    $("#updates-install-confirmation-overlay").fadeOut(150);
});

/*
    The liability checkbox must be checked in order for the update to be
    installed. If checked, enable the install button. If unchecked, disable the
    button.
*/
$("#update-disclaimer-checkbox").on("input", function() {
    if ($(this).is(":checked")) {
        $("#update-button-confirm-install").removeAttr("disabled");
    } else {
        $("#update-button-confirm-install").attr("disabled", "disabled");
    }
});

/*
    Changes to inputs on the form are tracked to stop data being accidentally
    discarded if the user tries to navigate away from the page without saving
    the settings. Ensure that the warning isn't displayed if the user clicks on
    the submit button.

    This must be set manually on submit because the form on this page does not
    use `require-validation`. Forms that use `require-validation` have this
    handled automatically by the validation script. Please see the end of the
    /admin/index.php script for more information.
*/
$("form").on("submit", function() {
    unsavedChanges = false;
});
