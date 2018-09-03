/*
    This script file is invoked from the administration pages and is responsible
    for client-side rendering and functionality of geofences.
*/

/*
    Generates a new ID used to uniquely identify each geofence.
*/
function getNewID() {
    /*
        Each geofence registered in each installation of FreeField has an ID
        that is unique within that particular installation. The ID is used both
        client-side and server-side. Each input field in the geofence has the ID
        listed in the field name so that the server knows which fence changes
        should be applied to when changes are saved and pushed form the client.

        If a client adds a new geofence, an ID then has to be assigned to that
        fence so that the server has a reference to the fence for later use. The
        way this is solved, is that an ID is generated from this script, then
        passed to the server along with all the other, existing fences. The
        server checks if each fence exists, and if it does not, it creates a
        fence with the given ID and saves it with the data it receives.

        This creates a concurrency problem. It may happen that two users are
        separately setting up geofences at the same time, and a conflict may
        arise between those two users. If the IDs were numerically incremented
        from each other, then the two users would create the same ID and
        overwrite each others' fences. This is solved by randomly generating an
        ID on each client. An 8-character alphanumeric string is more than
        sufficient for this use case as there are no sane situations where the
        number of fences will give rise to a potential collision between two
        fence IDs.

        The function that adds geofences and which calls this function to get an
        ID for the fence also checks for collisions with existing fence IDs to
        ensure that an ID is never used twice, further minimizing the risk of a
        collision.
    */
    return Math.random().toString(36).substr(2, 8);
}

/*
    Handle changes to the Actions down-down for fences. If the "delete" action
    is selected, the box should be re-styled to make it very obvious that the
    fence will be deleted (i.e. it shouldn't be possible to do it by accident).
    Setting the border and text color to red should draw enough attention to the
    box that accidental deletions doesn't happen (or at least happens very
    rarely).
*/
$("#fence-list").on("change", ".fence-actions", function() {
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
    This is an event handler for the button that adds new geofences.
*/
$("#geofence-new").on("click", function() {
    /*
        Each geofence has a unique ID. Generate such an ID for the new fence.
        Please see the `getNewID()` function to see how the IDs are generated.
    */
    var id = getNewID();

    /*
        Ensure that the ID is unique, to avoid conflicts where this geofence
        would overwrite another one.
    */
    while ($(".fence-instance[data-fence-id=" + id + "]").length > 0) {
        id = getNewID();
    }

    /*
        Create a new table row node for the geofence and add it to the geofences
        table.
    */
    var html =
        '<tr class="fence-instance" data-fence-id="{%ID%}">' +
            '<td>' +
                '<input type="text" name="fence_{%ID%}[label]" value="">' +
            '</td>' +
            '<td>' +
                '<textarea data-validate-as="geofence"' +
                          'name="fence_{%ID%}[vertices]"></textarea>' +
            '</td>' +
            '<td>' +
                '<select class="fence-actions" name="fence_{%ID%}[action]">' +
                    '<option value="none" selected>' +
                        resolveI18N("admin.clientside.fences.fence_list.action.none") +
                    '</option>' +
                    '<option value="delete">' +
                        resolveI18N("admin.clientside.fences.fence_list.action.delete") +
                    '</option>' +
                '</select>' +
            '</td>' +
        '</tr>';

    html = html.split("{%ID%}").join(id);
    var node = $($.parseHTML(html));
    $("#fence-list").append(node);
});
