/*
    This script file is invoked from the administration pages and is responsible
    for client-side functionality for groups management.
*/

/*
    Generates a new ID used to uniquely identify each group.
*/
function getNewID() {
    /*
        Each group registered in each installation of FreeField has a numerical
        ID assigned to it that is auto-incremented for each new group in the
        database. The ID is used to connect changes made on the client-side form
        in /includes/admin/groups.php to the proper group to apply the changes
        to server-side, identifying a particular group in the database.

        Webhooks and geofences use an 8-character random alphanumeric ID to
        identify themselves and to avoid concurrency issues. However, groups use
        numerical IDs to take advantage of AUTO_INCREMENT in the database table.

        If a client adds a new group, an ID then has to be assigned to that
        group so that the server has a reference to the group for later use.
        However, the client cannot generate this ID client-side, because
        multiple clients could potentially be modifying and adding groups at the
        same time, and since IDs are sequential, there would be collisions
        between the clients. The solution to this is to have clients generate a
        temporary ID that is only used to collect all the attributes of a group
        (such as label, color, permission level, etc.) into a single object for
        processing server-side. This means a newly added group would have the
        HTML form fields `gn_ID[label]`, `gn_ID[color]` etc, where "gn" means
        "group new", and combined with an ID, automatically forms an array
        `gn_ID` in PHP which contains all of the properties of the newly created
        group.

        When the group is inserted into the database, the generated ID of the
        group is ignored, as ID generation is handled by the SQL server.
        /admin/apply-groups.php performs an INSERT query on the database without
        passing any ID, and the ID is internally generated upon insertion as an
        incremented integer value.

        Because the client generated IDs are ignored on the server, there is no
        need for the client-side IDs to be globally unique. The only requirement
        is that there are no ID collisions between two or more added groups
        within the context of a single client. If the same ID is generated for
        two different groups on the same client, the latter of the groups in the
        list would overwrite the formar, and the first group would never be
        added. This can be avoided entirely using a local collision check.
        Functions which call `getNewID()` will confirm that the generated ID is
        not in use by any existing newly generated groups, and if a collision is
        found, it will randomly generate another ID instead.

        An alternative would be to have an incrementing variable for this
        function, that would return the value of the previously generated ID
        plus one for every invocation. However, for consistency with other ID
        generation functions in FreeField, we will be generating alphanumeric
        IDs instead.
    */
    return Math.random().toString(36).substr(2, 8);
}

/*
    Handle changes to the Actions down-down for groups. If the "delete" action
    is selected, the box should be re-styled to make it very obvious that the
    group will be deleted (i.e. it shouldn't be possible to do it by accident).
    Setting the border and text color to red should draw enough attention to the
    box that accidental deletions doesn't happen (or at least happens very
    rarely).
*/
$("#group-list").on("change", ".group-actions", function() {
    if ($(this).val() == "delete") {
        $(this).css("border", "1px solid red");
        $(this).css("color", "red");
        $(this).css("margin-right", "");
    } else {
        $(this).css("border", "");
        $(this).css("color", "");
        $(this).css("margin-right", "");
    }
});

/*
    If the group color is changed, then the checkbox that sets the color to
    non-null should be set, since the user wants a color for the group. Vice
    versa, unchecking the checkbox should reset the color selector.
*/
$("#group-list").on("change", ".group-color-selector > input[type=color]", function() {
    $(this).parent().find("input[type=checkbox]").prop("checked", true);
});
$("#group-list").on("change", ".group-color-selector > input[type=checkbox]", function() {
    if (!$(this).is(":checked")) {
        $(this).parent().find("input[type=color]").val("#000000");
    }
});

/*
    The default border color for the group level selectors. Used in the
    following code block.
*/
var groupBorderColor = $("input.group-level").css("border-color");
$("form").on("submit", function(e) {
    /*
        Groups' permission levels are enforced unique by SQL. This means that no
        two or more groups may share the same permission level. Warn the user if
        duplicate group levels are found, and prevent them from submitting the
        form if that is the case.
    */
    /*
        `levels` stores all levels found on the form.
    */
    var levels = [];
    /*
        `hasDuplicates` is a boolean on whether or not the form contains
        duplicates and should therefore not be submitted.
    */
    var hasDuplicates = false;
    /*
        Permission level selectors that are in conflict are highlighted in red
        to easily distinguish them to the user. Reset the color of all of those
        input boxes first to ensure that there are no red borders around boxes
        that were, but no longer are, duplicates (i.e. if the user makes a
        second attempt at submitting the form but now has other duplicates).
    */
    $("input.group-level").css("border-color", groupBorderColor);
    $("input.group-level").each(function() {
        /*
            Search every permission level input box for their values. The values
            are pushed to the `levels` array. If the value of the current
            permission level selector is already in the `levels` array, then it
            was previously added for another input level box, which means there
            are permission level input boxes with duplicate values.
        */
        var val = $(this).val();
        for (var i = 0; i < levels.length; i++) {
            if (levels[i] == val) {
                /*
                    If a duplicate is found, find all permission level input
                    boxes that have this duplicate value and set a red border
                    around them to make it easy for the user to distinguish the
                    groups with duplicate permission levels.
                */
                $("input.group-level").each(function() {
                    if ($(this).val() == val) {
                        $(this).css("border-color", "red");
                    }
                });
                /*
                    Flag the form to not submit if a duplicate was found.
                */
                hasDuplicates = true;
            }
        }
        /*
            Add the level of the current group to the `levels` array to match
            against the remaining groups.
        */
        levels.push(val);
    });
    /*
        If duplicate levels were found, alert the user and do not submit the
        form.
    */
    if (hasDuplicates) {
        e.preventDefault();
        alert(resolveI18N("admin.clientside.groups.popup.conflicting_levels"));
    } else {
        /*
            Changes to inputs on the form are tracked to stop data being
            accidentally discarded if the user tries to navigate away from the
            page without saving the settings. Ensure that the warning isn't
            displayed if the user clicks on the submit button.

            This must be set manually on submit because the form on this page
            does not use `require-validation`. Forms that use
            `require-validation` have this handled automatically by the
            validation script. Please see the end of the /admin/index.php script
            for more information.
        */
        unsavedChanges = false;
    }
});

/*
    This is an event handler for the button that adds new groups.
*/
$("#group-new").on("click", function() {
    /*
        Each group has a unique ID. Generate such an ID for the new group.
        Please see the `getNewID()` function to see how the IDs are generated.
    */
    var id = getNewID();

    /*
        Ensure that the ID is unique, to avoid conflicts where this group would
        overwrite another one. The `group-instance` class is only assigned to
        groups which are newly generated on the client, since existing groups
        sent by the server uses a different ID nomenclature and thus isn't prone
        to ID conflicts from this function. See `getNewID()` commentary for
        details.
    */
    while ($(".group-instance[data-group-id=" + id + "]").length > 0) {
        id = getNewID();
    }

    /*
        Create a new table row node for the group and add it to the groups
        table.
    */
    var html =
        '<tr class="group-instance" data-group-id="{%ID%}">' +
            '<td>' +

            '</td>' +
            '<td>' +
                '<input type="text" name="gn_{%ID%}[label]"' +
                       'value="' + resolveI18N("admin.clientside.groups.new") + '">' +
            '</td>' +
            '<td>' +
                '<input type="number" min="0" max="250" name="gn_{%ID%}[level]" value="0">' +
            '</td>' +
            '<td class="no-wrap group-color-selector" data-id="gn_{%ID%}">' +
                '<input type="checkbox" id="gn_{%ID%}-usecolor" name="gn_{%ID%}[usecolor]">' +
                '<input type="color" name="gn_{%ID%}[color]">' +
            '</td>' +
            '<td>' +
                '<select class="group-actions" name="gn_{%ID%}[action]">' +
                    '<option value="none" selected>' +
                        resolveI18N("admin.clientside.groups.group_list.action.none") +
                    '</option>' +
                    '<option value="delete">' +
                        resolveI18N("admin.clientside.groups.group_list.action.delete") +
                    '</option>' +
                '</select>' +
            '</td>' +
        '</tr>';

    html = html.split("{%ID%}").join(id);
    var node = $($.parseHTML(html));
    $("#group-list").append(node);
});
