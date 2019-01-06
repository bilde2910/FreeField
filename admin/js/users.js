/*
    This script file is invoked from the administration pages and is responsible
    for client-side functionality for user management.
*/

/*
    Add event handlers for the "Click to unlock" buttons used to enable
    management of one's own account if the user has permission to do so. This is
    done to ensure that users do not accidentally demote or delete their own
    account.
*/
$(document).ready(function() {
    $(".user-unlock").on("click", function() {
        if (confirm(resolveI18N("admin.clientside.users.user_list.unlock_warning"))) {
            $(this).parent().prev().prop("disabled", false);
            $(this).parent().hide();
        }
    });
});
