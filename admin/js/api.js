/*
    This script file is invoked from the administration pages and is responsible
    for client-side functionality for API client management.
*/

/*
    Generates a new ID used to uniquely identify each API client.
*/
function getNewID() {
    /*
        Each client registered in each installation of FreeField has a numerical
        ID assigned to it that is auto-incremented for each new client in the
        database. The ID is used to connect changes made on the client-side form
        in /includes/admin/api.php to the proper API client to apply the changes
        to server-side, identifying a particular client in the database.

        Webhooks and geofences use an 8-character random alphanumeric ID to
        identify themselves and to avoid concurrency issues. However, API
        clients use numerical IDs to take advantage of AUTO_INCREMENT in the
        database table.

        If a user adds a new API client, an ID then has to be assigned to that
        client so that the server has a reference to the client for later use.
        However, the user cannot generate this ID client-side, because multiple
        users could potentially be modifying and adding API clients at the same
        time, and since IDs are sequential, there would be collisions between
        the clients. The solution to this is to have the browser generate a
        temporary ID that is only used to collect all the attributes of a client
        (such as label, color, permission level, etc.) into a single object for
        processing server-side. This means a newly added client would have the
        HTML form fields `an_ID[name]`, `an_ID[color]` etc, where "an" means
        "API client new", and combined with an ID, automatically forms an array
        `an_ID` in PHP which contains all of the properties of the newly created
        client.

        When the client is inserted into the database, the generated ID of the
        client is ignored, as ID generation is handled by the SQL server.
        /admin/apply-api.php performs an INSERT query on the database without
        passing any ID, and the ID is internally generated upon insertion as an
        incremented integer value.

        Because the browser generated IDs are ignored on the server, there is no
        need for the client-side IDs to be globally unique. The only requirement
        is that there are no ID collisions between two or more added API clients
        within the context of a single user. If the same ID is generated for
        two different groups in the same browser window, the latter of the
        clients in the list would overwrite the former, and the first client
        would never be added. This can be avoided entirely using a local
        collision check. Functions which call `getNewID()` will confirm that the
        generated ID is not in use by any existing newly created clients, and if
        a collision is found, it will randomly generate another ID instead.

        An alternative would be to have an incrementing variable for this
        function, that would return the value of the previously generated ID
        plus one for every invocation. However, for consistency with other ID
        generation functions in FreeField, we will be generating alphanumeric
        IDs instead.
    */
    return Math.random().toString(36).substr(2, 8);
}

/*
    Handle changes to the Actions down-down for API clients. If the "delete"
    action is selected, the box should be re-styled to make it very obvious that
    the client will be deleted (i.e. it shouldn't be possible to do it by
    accident). Setting the border and text color to red should draw enough
    attention to the box that accidental deletions doesn't happen (or at least
    happens very rarely). The same is done for the action resets the client's
    access token.
*/
$("#client-list").on("change", ".client-actions", function() {
    if ($(this).val() == "delete") {
        $(this).css("border", "1px solid red");
        $(this).css("color", "red");
        $(this).css("margin-right", "");
    } else if ($(this).val() == "reset") {
        $(this).css("border", "1px solid darkorange");
        $(this).css("color", "darkorange");
        $(this).css("margin-right", "");
    } else {
        $(this).css("border", "");
        $(this).css("color", "");
        $(this).css("margin-right", "");
    }
});

/*
    Event handler for the "Click to view" access token display button in the
    client list. When clicked, this button should open a popup dialog that
    displays the API client's access token and lets the administrator copy it to
    clipboard for usage.
*/
$("#client-list").on("click", ".api-table-view-token", function() {
    /*
        Get the API client name and token from the table.
    */
    var clientName =
        $(this).closest(".api-table-client-row")
               .find(".api-table-name-cell input")
               .val();
    var token =
        $(this).attr("data-token");

    /*
        Update the popup window with the details, then display the window.
    */
    $("#api-popup-token-name").val(clientName);
    $("#api-popup-token-value").val(token);
    $("#api-popup-token-box").fadeIn(150);
});

/*
    Event handler for the "Close" button on the access token popup window. This
    button hides the popup.
*/
$("#api-popup-token-close").on("click", function() {
    $("#api-popup-token-box").fadeOut(150);
});

/*
    Event handler for the "Copy to clipboard" button on the access token popup
    window. This button highlights the access token, then copies it to the
    clipboard.
*/
$("#api-popup-token-copy").on("click", function() {
    $("#api-popup-token-value").select();
    document.execCommand("copy");
});

/*
    Event handler for the permissions list in the API clients table. When
    clicked, this list will open a popup dialog prompting the user to select
    which permissions and which permission level the client should operate at.
*/
$("#client-list").on("click", ".api-table-access-label", function() {
    /*
        Identify the permission list div and find the name of the client in
        question.
    */
    var labelSpan = $(this);
    var clientName =
        $(this).closest(".api-table-client-row")
               .find(".api-table-name-cell input")
               .val();

    /*
        The table uses hidden input fields to store the permission data for each
        client to make it easier for the server to process. Identify these input
        fields and extract data from them to update the permissions dialog popup
        with proper values.
    */
    var accessStore = $(this).find('input[data-perm-type="access"]');
    var levelStore = $(this).find('input[data-perm-type="level"]');
    var perms = accessStore.val().split("/").join(".").split("-").join("_").split(',');
    var level = parseInt(levelStore.val());

    /*
        Bind an event handler for the "Save settings" dialog of the permissions
        list popup dialog. This button should save the changes made by the user
        back into the hidden input fields.
    */
    $("#api-popup-access-close").on("click", function() {
        /*
            Unbind the parent click handler to prevent duplicate or erroneous
            handlers for this button.
        */
        $(this).off("click");
        /*
            Retrieve the values set by the user in the dialog.
        */
        var newLevel = $("#api-popup-access-level").val();
        var permsList = [];
        var permsHTML = [];
        $(".api-popup-access-checkbox").each(function(idx, e) {
            if ($(this).is(":checked")) {
                permsList.push($(this).attr("data-perm-safe")
                                      .split(".").join("/")
                                      .split("_").join("-"));
                permsHTML.push($("<span>").text(resolveI18N(
                        "setting.permissions.level." +
                        $(this).attr("data-perm-safe") +
                        ".name"
                )).html());
            }
        });
        var newPerms = permsList.join(",");
        permsHTML.sort();

        /*
            Place the new values back into the hidden input boxes and update the
            display label in the table.
        */
        accessStore.val(newPerms);
        levelStore.val(newLevel);
        if (permsHTML.length == 0) {
            labelSpan.find(".api-table-label-list").text(
                resolveI18N("admin.clientside.api.client_list.access.none")
            );
        } else {
            labelSpan.find(".api-table-label-list").html(
                permsHTML.join("<br />")
            );
        }
        labelSpan.find(".api-table-label-level-value").text(newLevel);
        unsavedChanges = true;
        $("#api-popup-access-box").fadeOut(150);
    })

    /*
        Update the input boxes and checkboxes in the permissions list popup
        dialog with current values retrieved from the hidden input boxes, then
        display the dialog window.
    */
    $("#api-popup-access-name").val(clientName);
    $(".api-popup-access-checkbox").prop("checked", false);
    for (var i = 0; i < perms.length; i++) {
        $('.api-popup-access-checkbox[data-perm-safe="' + perms[i] + '"]')
            .prop("checked", true);
    }
    $("#api-popup-access-level").val(level);
    $("#api-popup-access-box").fadeIn(150);
});

/*
    Event handler for the "Cancel" button on the permissions list dialog popup.
    This button closes the popup without saving changes.
*/
$("#api-popup-access-cancel").on("click", function() {
    $("#api-popup-access-box").fadeOut(150);
});

/*
    This is an event handler for the button that adds new clients.
*/
$("#client-new").on("click", function() {
    /*
        Each client has a unique ID. Generate such an ID for the new client.
        Please see the `getNewID()` function to see how the IDs are generated.
    */
    var id = getNewID();

    /*
        Ensure that the ID is unique, to avoid conflicts where this client would
        overwrite another one. The `api-client-instance` class is only assigned
        to clients which are newly generated in the browser, since existing
        clients sent by the server uses a different ID nomenclature and thus
        isn't prone to ID conflicts from this function. See `getNewID()`
        commentary for details.
    */
    while ($(".api-client-instance[data-client-id=" + id + "]").length > 0) {
        id = getNewID();
    }

    /*
        Create a new table row node for the API client and add it to the clients
        table.
    */
    var html =
        '<tr class="api-client-instance api-table-client-row" data-client-id="{%ID%}">' +
            '<td class="api-table-name-cell">' +
                '<input type="text" name="an_{%ID%}[name]">' +
            '</td>' +
            '<td>' +
                resolveI18N("admin.clientside.api.client_list.token.new") +
            '</td>' +
            '<td>' +
                '<input type="color" name="an_{%ID%}[color]" value="#' + defaultColor + '">' +
            '</td>' +
            '<td>' +
                resolveI18N("admin.clientside.api.client_list.seen.never") +
            '</td>' +
            '<td>' +
                '<div class="api-table-access-label">' +
                    '<p class="api-table-label-level-text">' +
                        resolveI18N("admin.clientside.api.client_list.access.level") +
                        ' <span class="api-table-label-level-value">0</span>' +
                    '</p>' +
                    '<p class="api-table-label-list">' +
                        resolveI18N("admin.clientside.api.client_list.access.none") +
                    '</p>' +
                    '<input type="hidden" data-perm-type="access" name="an_{%ID%}[access]" value="">' +
                    '<input type="hidden" data-perm-type="level" name="an_{%ID%}[level]" value="0">' +
                '</div>' +
            '</td>' +
            '<td>' +
                '<select class="client-actions" name="an_{%ID%}[action]">' +
                    '<option value="none" selected>' +
                        resolveI18N("admin.clientside.api.client_list.action.none") +
                    '</option>' +
                    '<option value="reset">' +
                        resolveI18N("admin.clientside.api.client_list.action.reset") +
                    '</option>' +
                    '<option value="delete">' +
                        resolveI18N("admin.clientside.api.client_list.action.delete") +
                    '</option>' +
                '</select>' +
            '</td>' +
        '</tr>';

    html = html.split("{%ID%}").join(id);
    var node = $($.parseHTML(html));
    $("#client-list").append(node);
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
