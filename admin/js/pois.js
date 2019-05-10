/*
    This script file is invoked from the administration pages and is responsible
    for client-side functionality for POI imports.
*/

/*
    `importingTable` is an array of objects representing the contents of the
    file submitted by the user when importing POIs. Each element is an object
    with keys corresponding to the keys/headers in the file. This variable is
    used to draw and populate the preview table.
*/
var importingTable = {
    "poi": null,
    "arena": null
};

/*
    POI import file parsing logic happens client-side. This event handler binds
    to the file input box when importing POIs. Its purpose is to parse the file
    and construct an array for `importingTable`. It also displays the preview
    table if applicable.
*/
$("#import-poi-file, #import-arena-file").on("change", function(e) {
    var type = $(this).attr("data-type");
    /*
        Reset all fields and data.
    */
    // Clear select boxes
    $(".import-" + type + "-optgroup").html("");
    // Disable select boxes
    $(".import-" + type + "-field").prop("disabled", true);
    // Clear importing POI list and preview table
    importingTable[type] = null;
    drawTable(type);

    /*
        Ensure compatibility with HTML5 File API.
    */
    if (!(
        window.File &&
        window.FileReader &&
        window.FileList &&
        window.Blob
    )) return;

    /*
        Check if a file was selected by the user. If not, the preview table
        remains hidden and nothing changes.
    */
    if (e.target.files.length > 0) {
        var file = e.target.files[0];

        /*
            Determine file type. Browsers are unreliable with file types for CSV
            files as they don't consistently recognize the "text/csv" MIME type.
            Hence, get the data type from the file extension.
        */
        var ftype = null;
        if (file.name.toLowerCase().endsWith(".csv")) ftype = "csv";

        /*
            Read the file.
        */
        switch (ftype) {
            case "csv":
                /*
                    `elements` contains a list of POI objects for the import. It
                    will be put into `importingTable`.
                */
                var elements = [];
                /*
                    `header` contains a list of fields in the CSV file, as
                    defined by the CSV header.
                */
                var header = null;
                /*
                    Parse the file line by line.
                */
                Papa.parse(file, {
                    header: true,
                    step: function(row) {
                        for (var i = 0; i < row.data.length; i++) {
                            /*
                                Check if the header fields have been defined
                                yet. If not, define the list of headers using
                                this row.
                            */
                            if (header == null) {
                                header = [];
                                for (var j = 0; j < row.meta.fields.length; j++) {
                                    header.push(row.meta.fields[j].trim());
                                }
                            }

                            /*
                                Create an object that represents this POI and
                                populate it with data. Ensure that the data are
                                trimmed so that unnecessary spaces are removed.
                            */
                            var object = {};
                            for (var key in row.data[i]) {
                                if (row.data[i].hasOwnProperty(key)) {
                                    object[key.trim()] = row.data[i][key].trim();
                                }
                            }

                            /*
                                Verify that this row has all fields specified in
                                the header row. This check will eliminate
                                malformed and empty rows.
                            */
                            var hasAllRequiredFields = true;
                            for (var j = 0; j < header.length; j++) {
                                if (!object.hasOwnProperty(header[j])) hasAllRequiredFields = false;
                            }

                            /*
                                If the row is valid, add the POI object to the
                                elements array.
                            */
                            if (hasAllRequiredFields) elements.push(object);
                        }
                    },
                    complete: function() {
                        /*
                            When all POIs have been parsed, prepare for
                            displaying the list of POIs to import in the preview
                            table.
                        */
                        preparePreview(header, elements, type);
                    }
                });
                break;
        }
    }
});

/*
    This function takes an array of headers and an array of POI objects and uses
    them to construct a preview table and enable the UI for importing POIs.
*/
function preparePreview(header, table, type) {
    /*
        The field selection boxes on the page exist to let the user determine
        which column of the imported data corresopnds to the names, latitudes
        and longitudes of the POIs in the file. The user should be able to
        select a column from each of the dropboxes.
    */

    /*
        Clear the existing list of column names, if any.
    */
    $(".import-" + type + "-optgroup").html("");

    /*
        Loop through the column names in the `header` array and add each of them
        as an option to each of the field selection boxes.
    */
    for (var i = 0; i < header.length; i++) {
        var headerField = $("<option />");
        headerField.attr("value", header[i]);
        headerField.text(header[i]);
        $(".import-" + type + "-optgroup").append(headerField);
    }

    /*
        Ensure that the selection boxes are enabled, so the user can actually
        make a selection.
    */
    $(".import-" + type + "-field").prop("disabled", false);

    /*
        Assign the list of POI objects imported from the file to the global
        `importingTable` array. The `importingTable` variable is later used to
        draw and populate the preview table.
    */
    importingTable[type] = table;

    /*
        Create the table's nodes in the DOM and fill it with values.
    */
    drawTable(type);
    renderPreview(type);
}

/*
    This function constructs a preview table in the DOM for the imported POIs.
    It only creates the structure - it does not fill the table with values. That
    function is handled by `renderPreview()` instead.
*/
function drawTable(type) {
    /*
        Clear all current rows.
    */
    $("#import-" + type + "-preview-rows").html("");
    if (importingTable[type] == null) {
        /*
            Hide the preview if the user has not selected a file, or if the
            selected file is invalid.
        */
        $("#import-" + type + "-preview-section").hide();
    } else {
        /*
            Loop over the POI list, adding the objects to the table one by one.
        */
        for (var i = 0; i < importingTable[type].length; i++) {
            var row = importingTable[type][i];

            /*
                Create a row node that corresponds to this particular POI.
            */
            var rowNode = $('<tr id="import-' + type + '-preview-row-' + i + '" />');

            /*
                Create an add a cell for displaying the name, latitude and
                longitude of the imported POI. These values can be changed by
                the user through the <input> box if they desire.
            */
            var colName = $(
                '<td>' +
                    '<input type="text" ' +
                           'class="import-' + type + '-data-field" ' +
                           'data-new-id="' + i + '" ' +
                           'data-new-key="name">' +
                '</td>'
            );
            rowNode.append(colName);

            var colLatitude = $(
                '<td>' +
                    '<input type="number" ' +
                           'step="0.000000000001" ' +
                           'class="import-' + type + '-data-field" ' +
                           'data-new-id="' + i + '" ' +
                           'data-new-key="latitude">' +
                '</td>'
            );
            rowNode.append(colLatitude);

            var colLongitude = $(
                '<td>' +
                    '<input type="number" ' +
                           'step="0.000000000001" ' +
                           'class="import-' + type + '-data-field" ' +
                           'data-new-id="' + i + '" ' +
                           'data-new-key="longitude">' +
                '</td>'
            );
            rowNode.append(colLongitude);

            /*
                Add an "include" selection box that lets users exclude POIs that
                they do not wish to import from the file.

                `data-changed` is used to ensure that the selection made here is
                sent to the server (it otherwise wouldn't due to
                /admin/js/limit-inputs.js disabling it for not being changed).
                If the `data-changed` attribute is set to "true", the <select>
                will not be disabled and thus be sent to the server.
            */
            var colInclude = $(
                '<td>' +
                    '<select data-new-id="' + i + '" ' +
                            'data-new-key="include" ' +
                            'class="import-action import-' + type + '-data-field" ' +
                            'data-changed="true">' +
                        '<option value="yes"></option>' +
                        '<option value="no"></option>' +
                    '</select>' +
                '</td>'
            );
            /*
                Localize the options. This is not done directly in the HTML
                above because it could pose an XSS problem.
            */
            colInclude.find('option[value="yes"]').text(resolveI18N(
                "admin.clientside.pois.preview_table.actions.include"
            ));
            colInclude.find('option[value="no"]').text(resolveI18N(
                "admin.clientside.pois.preview_table.actions.exclude"
            ));
            rowNode.append(colInclude);

            /*
                Append the row to the table body.
            */
            $("#import-" + type + "-preview-rows").append(rowNode);
        }

        /*
            Display a count of POIs importable from the selected file, and
            display the table itself if hidden.
        */
        $("#import-" + type + "-counter").text(resolveI18N(
            "admin.clientside.pois.import.count_" + type,
            importingTable[type].length
        ));
        $("#import-" + type + "-preview-section").show();
    }
}

/*
    This table populates the POI import preview table with values.
*/
function renderPreview(type) {
    if (importingTable[type] !== null) {
        /*
            Get the name of the fields containing the POI name, latitude and
            longitude, as specified by the user.
        */
        var fieldName = $("#import-" + type + "-field-name").val();
        var fieldLatitude = $("#import-" + type + "-field-latitude").val();
        var fieldLongitude = $("#import-" + type + "-field-longitude").val();

        for (var i = 0; i < importingTable[type].length; i++) {
            var row = importingTable[type][i];
            var rowNode = $("#import-" + type + "-preview-row-" + i);

            /*
                Check that the field names exists in the object for the name,
                latitude and longitude column names selected. If they are found,
                parse the values and put them into the table.
            */
            if (fieldName != "" && row.hasOwnProperty(fieldName))
                rowNode.find('input[data-new-id="' + i + '"][data-new-key="name"]')
                       .val(row[fieldName]);

            if (fieldLatitude != "" && row.hasOwnProperty(fieldLatitude))
                rowNode.find('input[data-new-id="' + i + '"][data-new-key="latitude"]')
                       .val(parseFloat(row[fieldLatitude]));

            if (fieldLongitude != "" && row.hasOwnProperty(fieldLongitude))
                rowNode.find('input[data-new-id="' + i + '"][data-new-key="longitude"]')
                       .val(parseFloat(row[fieldLongitude]));
        }

        /*
            Check for empty cells and flag them to grab the user's attention.
        */
        $("#import-" + type + "-preview-rows input").trigger("input");
    }
}

/*
    Event handler for the field selectors. When the selection in an field header
    selection box changes, the table should be re-filled with values
    corresponding to the data in the `importingTable` using the keys provided
    by the selection boxes.
*/
$(".import-poi-field").on("input", function() {
    renderPreview("poi");
});
$(".import-arena-field").on("input", function() {
    renderPreview("arena");
});

/*
    Handle changes to the "import?" column of the POI preview table. If the user
    specifies that they do not want to import a POI, it should be flagged in red
    to visibly highlight that face.
*/
$("#import-poi-preview-rows, #import-arena-preview-rows").on("change", ".import-action", function() {
    if ($(this).val() == "no") {
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
    If the table contains empty cells, the rows with those cells will be
    ignored. To ensure that as many POIs as possible are submitted, highlight
    empty cells with a red border.
*/
$("#import-poi-preview-rows").on("input", "input", function() {
    if ($(this).val() == "") {
        $(this).css("border", "1px solid red");
        $(this).css("color", "red");
        $(this).css("margin-right", "");
        $(this).addClass("data-invalid");
    } else {
        $(this).css("border", "");
        $(this).css("color", "");
        $(this).css("margin-right", "");
        $(this).removeClass("data-invalid");
    }
    /*
        If there exist rows with invalid data, display a warning underneath the
        table that those rows will not be imported into the POI list.
    */
    if ($('#import-poi-preview-rows input.data-invalid').length > 0) {
        $("#import-poi-invalid-warning").show();
    } else {
        $("#import-poi-invalid-warning").hide();
    }
});
$("#import-arena-preview-rows").on("input", "input", function() {
    if ($(this).val() == "") {
        $(this).css("border", "1px solid red");
        $(this).css("color", "red");
        $(this).css("margin-right", "");
        $(this).addClass("data-invalid");
    } else {
        $(this).css("border", "");
        $(this).css("color", "");
        $(this).css("margin-right", "");
        $(this).removeClass("data-invalid");
    }
    /*
        If there exist rows with invalid data, display a warning underneath the
        table that those rows will not be imported into the POI list.
    */
    if ($('#import-arena-preview-rows input.data-invalid').length > 0) {
        $("#import-arena-invalid-warning").show();
    } else {
        $("#import-arena-invalid-warning").hide();
    }
});

/*
    Handle changes to the Actions down-down for POIs. If the "delete" action is
    selected, the box should be re-styled to make it very obvious that the POI
    will be deleted (i.e. it shouldn't be possible to do it by accident).
    Setting the border and text color to red should draw enough attention to the
    box that accidental deletions doesn't happen (or at least happens very
    rarely). The same is done for the action that clears the field research task
    currently reported on the POI.
*/
$(".poi-actions, .arena-actions").on("change", function() {
    switch ($(this).val()) {
        case "delete":
        case "delete-poi":
        case "delete-arena":
        case "delete-all":
            $(this).css("border", "1px solid red");
            $(this).css("color", "red");
            $(this).css("margin-right", "");
            break;

        case "clear":
            $(this).css("border", "1px solid darkorange");
            $(this).css("color", "darkorange");
            $(this).css("margin-right", "");
            break;

        default:
            $(this).css("border", "");
            $(this).css("color", "");
            $(this).css("margin-right", "");
    }
});

$("form").on("submit", function(ev) {
    /*
        Importing ~250 or more POIs at the same time may cause the form to
        exceed the maximum number of allowed data fields per HTTP request. To
        mitigate this, a hidden input field by id "import-poi-json" will contain
        a JSON-encoded representation of all the fields. Populate this field as
        we are about to submit the form.
    */
    var pnArray = [];
    $(".import-poi-data-field").each(function(idx, e) {
        var id = $(e).attr("data-new-id");
        var key = $(e).attr("data-new-key");
        if (typeof pnArray[id] === "undefined") pnArray[id] = {};
        pnArray[id][key] = $(e).val();
    });
    $("#import-poi-json").val(JSON.stringify(pnArray));

    var anArray = [];
    $(".import-arena-data-field").each(function(idx, e) {
        var id = $(e).attr("data-new-id");
        var key = $(e).attr("data-new-key");
        if (typeof anArray[id] === "undefined") anArray[id] = {};
        anArray[id][key] = $(e).val();
    });
    $("#import-arena-json").val(JSON.stringify(anArray));

    /*
        Changes to inputs on the form are tracked to stop data being
        accidentally discarded if the user tries to navigate away from the page
        without saving the settings. Ensure that the warning isn't displayed if
        the user clicks on the submit button.

        This must be set manually on submit because the form on this page does
        not use `require-validation`. Forms that use `require-validation` have
        this  handled automatically by the validation script. Please see the end
        of the /admin/index.php script for more information.
    */
    unsavedChanges = false;
});
