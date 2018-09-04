/*
    This script file is invoked from the main map page. It is responsible for
    client-side functionality for controls, adding POIs, reporting research,
    handling interactions with map markers, managing client-side settings, and
    more.
*/

/*
    `addingPoi` is a boolean flag on whether or not the user is currently adding
    a POI to the map. This is set to `true` whenever POI adding is initiated to
    prevent duplicate processing of new POIs.
*/
var addingPoi = false;

/*
    `pois` holds the current, complete list of POIs available on this FreeField
    instance. An element in `pois` looks like this:

        pois[0] = {
            "element" -> A DOM element representing the marker on the map
            "id" -> The ID of the marker
            "latitude" -> The latitude of the POI
            "longitude" -> The longitude of the POI
            "name" -> The name of the POI
            "objective" -> The currently active research objective on this POI
            "reward" -> The currently active research reward on this POI
            "updated": {
                "on" -> A timestamp of the last time research was reported
                "by" -> The identity of the user who reported the field research
            }
        }
*/
var pois = [];

/*
    A mapping between map themes and the color theme that should be used for map
    markers when using that theme.
*/
var styleMap = {
    mapbox: {
        basic: "light",
        streets: "light",
        bright: "light",
        light: "light",
        dark: "dark",
        satellite: "dark"
    }
}

/*
    Removes the banner prompting users to click on the map to select a location
    when adding a new POI, and also unbinds the event handlers that opens the
    dialog box prompting users for details about the POI they are adding. If
    `setFlag` is set, `addingPoi` is reset to the default `false` value;
    otherwise the `addingPoi` flag remains `true`.

    `disableAddPOI(false)` is called when the user has clicked on the map to
    select a location. The user has not finished adding the POI yet, hence
    `addingPoi` should remain true.

    `disableAddPOI(true)` is called if the user cancels adding a new POI by
    clicking on the "cancel" link on the banner prompting them to select a
    location.
*/
function disableAddPOI(setFlag) {
    /*
        `getCoordsForPOI()` is an event handler that, when called with a map
        click event containing coordinate data, opens a dialog box that prompts
        the user for more details about the POI, such as its name.
    */
    map.off('click', getCoordsForPOI);
    /*
        Hide the "please select a location on the map" banner.
    */
    $("#add-poi-banner").fadeOut(150);
    addingPoi = !setFlag;
}

/*
    `getCoordsForPOI()` is an event handler that, when called with a map click
    event containing coordinate data, opens a dialog box that prompts the user
    for more details about the POI, such as its name.
*/
function getCoordsForPOI(e) {
    /*
        Disables the map click event handler and hides the POI banner.
    */
    disableAddPOI(false);
    /*
        The dialog box allowing the user to specify details for the newly added
        POI contains text fields displaying the coordinates of the POI. These
        cannot be changed, but are there to give the user an indication of the
        coordinates of the POI they are adding.
    */
    $("#add-poi-lon").val(e.lngLat.lng);
    $("#add-poi-lat").val(e.lngLat.lat);
    /*
        When a POI is submitted, the button that submits the form is disabled to
        prevent duplicate submissions. Make sure that the button is enabled when
        the dialog is displayed.
    */
    $("#add-poi-submit").prop("disabled", false);

    $("#add-poi-details").fadeIn(150);
}

/*
    Escapes HTML in a string.

    data
        The string to escape
*/
function encodeHTML(data) {
    /*
        This works by creating a new empty element, setting its innerText, and
        the extracting the innerHTML from the same node. If innerText contains
        HTML syntax, it will have to be escaped in the underlying HTML in order
        to render as text, hence we can fetch that escaped HTML by calling the
        contents of innerHTML.
    */
    return $("<div />").text(data).html();
}

/*
    Generates a new ID used to uniquely identify banner elements displayed as
    overlays on top of the map.
*/
function getNewID() {
    return Math.random().toString(36).substr(2, 8);
}

/*
    Fades away the element with the given ID.
*/
function dismiss(id) {
    $(id).fadeOut(150);
}

/*
    Creates a banner that is displayed on top of the map. The banner contains a
    notification for the user that can indicate success and failure states for
    various actions the user performed. It automatically fades out after 5
    seconds unless the user manually dismisses it.

    type
        A CSS class for the banner. Typically "success" or "failed". "success"
        renders the banner with a green background, and "failed" with a red
        background.

    message
        The message to display in the banner.
*/
function spawnBanner(type, message) {
    /*
        Generate a random ID for the banner, and create th banner. Assign an
        event handler that dismisses the banner on click, and additionally
        schedule the banner to dismiss automatically after 5 seconds.
    */
    var id = getNewID();
    var node = $.parseHTML(
        '<div class="banner ' + type + '" id="dyn-' + id + '">' +
            '<div class="banner-inner">' +
                encodeHTML(message) +
            '</div>' +
        '</div>'
    );
    $("#dynamic-banner-container").append(node);
    $("#dyn-" + id).on("click", function() {
        dismiss("#dyn-" + id);
    });
    $("#dyn-" + id).fadeIn(150);
    setTimeout(function() {
        $("#dyn-" + id).fadeOut(150);
    }, 5000);
}

/*
    Whenever a marker is clicked on the map, this function is called with the
    instance of the Mapbox popup that would open, as well as the ID of the
    marker that was clicked.

    The purpose of the function is to display a custom marker popup. The default
    Mapbox popups aren't very flexible, and don't allow us to display and style
    controls like we want. Hence, what we do is override the marker with a
    custom one. This function will configure and display that popup.

    When a marker is opened, we also assign the ID of that marker to
    `currentMarker`. This is because the map periodically checks for updates to
    reported field research, and if an update is made to the POI that is
    currently open, the POI details dialog should reflect that by updating the
    displayed research icons and text.
*/
var currentMarker = -1;
function openMarker(popup, id) {
    currentMarker = id;
    /*
        Get data for the POI in the list of POIs received from the server. This
        list is stored in the `pois` variable, and can be looked up using the ID
        of the POI. Once we have the data, we can put it in the popup.
    */
    var poiObj = pois[id];
    $("#poi-name").text(poiObj.name);
    $("#poi-objective-icon").attr("src", resolveIconUrl(poiObj.objective.type));
    $("#poi-reward-icon").attr("src", resolveIconUrl(poiObj.reward.type));
    $("#poi-objective").text(resolveObjective(poiObj.objective));
    $("#poi-reward").text(resolveReward(poiObj.reward));

    /*
        Add event handlers to the directions and close buttons.
    */
    $("#poi-directions").on("click", function() {
        var url = naviProviders[settings.get("naviProvider")];

        url = url.split("{%LAT%}").join(encodeURI(poiObj.latitude));
        url = url.split("{%LON%}").join(encodeURI(poiObj.longitude));
        url = url.split("{%NAME%}").join(encodeURI(poiObj.name));

        window.open(url);
    });
    $("#poi-close").on("click", function() {
        popup._onClickClose();
    });

    /*
        Check if the user has permission to report field research. If there is
        already research reported for the POI, a separate permission is also
        required to overwrite existing research.

        If permission has been granted to the current user, bind a click event
        handler to the "report field research" button, and display the button.

        If permission is not granted, hide the button.
    */
    var canReportResearch = permissions["report-research"];
    if (
        canReportResearch &&
        (
            poiObj.objective.type != "unknown" ||
            poiObj.reward.type != "unknown"
        )
    ) {
        canReportResearch = permissions["overwrite-research"];
    }
    if (canReportResearch) {
        $("#poi-add-report").on("click", function() {
            /*
                This event handler opens the dialog box for reporting field
                research for a POI. Since the same dialog is reused for all
                POIs, we have to ensure that the form is empty when it's opened.
                Setting all <input>s to null and selecting whatever is the first
                element of all <select>s will result in this behavior.
            */
            $("input.parameter").val(null);
            $("select.parameter").each(function() {
                $(this)[0].selectedIndex = 0;
            });

            /*
                When the form is reset, we can start overwriting it with the
                research task that is already active on the POI, if any. If the
                objective or reward is "unknown", then no research has been
                reported on that POI today, and the fields should be left blank.
            */
            $("#update-poi-objective").val(
                poiObj.objective.type == "unknown"
                ? null
                : poiObj.objective.type
            );
            if (poiObj.objective.type !== "unknown") {
                /*
                    The objective select box has an event handler that ensures
                    that the correct parameters for the objective are displayed
                    on the dialog. This is normally triggered when the user
                    changes the objective selection, but updating the selection
                    with `$.val()` won't trigger the event. Thus, trigger it
                    manually.
                */
                $("#update-poi-objective").trigger("change");
                /*
                    Get the list of parameters for the objective from the list
                    of available objectives, then loop over the list and input
                    the values of the parameters to the dialog box using
                    `parseObjectiveParameter()`. The functions used for each
                    specific parameter type are defined in the parameter's class
                    in /includes/lib/research.php.
                */
                var params = objectives[poiObj.objective.type].params;
                for (var i = 0; i < params.length; i++) {
                    parseObjectiveParameter(params[i], poiObj.objective.params[params[i]]);
                }
            } else {
                $(".objective-parameter").hide();
            }
            $("#update-poi-reward").val(
                poiObj.reward.type == "unknown"
                ? null
                : poiObj.reward.type
            );
            if (poiObj.reward.type !== "unknown") {
                /*
                    The reward select box has an event handler that ensures that
                    the correct parameters for the reward are displayed on the
                    dialog. This is normally triggered when the user changes the
                    reward selection, but updating the selection with `$.val()`
                    won't trigger the event. Thus, trigger it manually.
                */
                $("#update-poi-reward").trigger("change");
                /*
                    Get the list of parameters for the reward from the list of
                    of available rewards, then loop over the list and input the
                    values of the parameters to the dialog box using
                    `parseRewardParameter()`. The functions used for each
                    specific parameter type are defined in the parameter's class
                    in /includes/lib/research.php.
                */
                var params = rewards[poiObj.reward.type].params;
                for (var i = 0; i < params.length; i++) {
                    parseRewardParameter(params[i], poiObj.reward.params[params[i]]);
                }
            } else {
                $(".reward-parameter").hide();
            }

            /*
                Replace the POI details dialog with the research report dialog.
            */
            $("#poi-details").hide();
            $("#update-poi-details").show();
        });
        $("#poi-add-report").show();
    } else {
        $("#poi-add-report").hide();
    }

    /*
        Event handler for the submit button on the "report field research"
        dialog. This function will initiate an HTTP PATCH request to
        /xhr/poi.php to update the current field research on the POI. A banner
        is displayed once a response is received from the server showing the
        success state of the request.
    */
    $("#update-poi-submit").on("click", function() {
        /*
            Disallow submitting research if an objective/reward has not been
            selected and tell the user that the request was denied.
        */
        var objective = $("#update-poi-objective").val();
        if (objective == null) {
            spawnBanner("failed", resolveI18N(
                "poi.update.failed.message",
                resolveI18N("poi.update.failed.reason.objective_null")
            ));
            return;
        }
        var reward = $("#update-poi-reward").val();
        if (reward == null) {
            spawnBanner("failed", resolveI18N(
                "poi.update.failed.message",
                resolveI18N("poi.update.failed.reason.reward_null")
            ));
            return;
        }

        /*
            Ensure that all required parameters are set for both the objective
            and reward components of the research quest. This is done by
            retrieving the list of required parameters from the `objectives` and
            `rewards` objects, populated from /includes/lib/research.php. The
            definitions for the objective and reward contain these lists, so
            we loop over them, fetch the user data from the dialog, and ensure
            that none of the fields are `null` or empty. If they are, we abort
            the submission and notify the user with a banner.
        */
        var objDefinition = objectives[objective];
        var rewDefinition = rewards[reward];

        var objParams = {};
        for (var i = 0; i < objDefinition.params.length; i++) {
            var paramData = getObjectiveParameter(objDefinition.params[i]);
            if (paramData == null || paramData == "") {
                spawnBanner("failed", resolveI18N(
                    "poi.update.failed.message",
                    resolveI18N("xhr.failed.reason.missing_fields")
                ));
                return;
            }
            objParams[objDefinition.params[i]] = paramData;
        }

        var rewParams = {};
        for (var i = 0; i < rewDefinition.params.length; i++) {
            var paramData = getRewardParameter(rewDefinition.params[i]);
            if (paramData == null || paramData == "") {
                spawnBanner("failed", resolveI18N(
                    "poi.update.failed.message",
                    resolveI18N("xhr.failed.reason.missing_fields")
                ));
                return;
            }
            rewParams[rewDefinition.params[i]] = paramData;
        }

        /*
            At this point, all client-side validation has passed, so we can
            submit the research task update to the server. Disable the submit
            button to prevent accidental multiple submissions.

            Since updating the research requires a call to the server, the task
            doesn't complete immediately, thus a "processing" indicator
            (`#update-poi-working`) should be displayed to the user to let them
            know that the research update call is in progress.
        */
        $("#update-poi-submit").prop("disabled", true);
        $("#update-poi-working").fadeIn(150);
        $.ajax({
            url: "./xhr/poi.php",
            type: "PATCH",
            dataType: "json",
            data: JSON.stringify({
                id: poiObj.id,
                objective: {
                    type: objective,
                    params: objParams
                },
                reward: {
                    type: reward,
                    params: rewParams
                }
            }),
            statusCode: {
                204: function(data) {
                    /*
                        If the request was successful, update the display of the
                        marker on the map to show the updated research task.
                        Also update the instance of the object in `pois` with
                        the new research task.
                    */
                    var oldObjective = poiObj.objective.type;
                    var oldReward = poiObj.reward.type;
                    if ($(poiObj.element).hasClass(oldReward)) {
                        $(poiObj.element).removeClass(oldReward).addClass(reward);
                    }
                    if ($(poiObj.element).hasClass(oldObjective)) {
                        $(poiObj.element).removeClass(oldObjective).addClass(objective);
                    }
                    poiObj.objective = {
                        type: objective,
                        params: objParams
                    };
                    poiObj.reward = {
                        type: reward,
                        params: rewParams
                    };
                    console.log(poiObj.element);

                    /*
                        Let the user know that the research was successfully
                        reported, and then close the report dialog.

                        Note that at this point, the POI details popup is
                        already hidden - we hid it when we displayed the "report
                        field research" dialog - but as far as Mapbox concerns,
                        the popup is sitll open, because it hasn't received a
                        click event on either a "close" button on the popup, or
                        a click event somewhere else on the map. This means that
                        if we try to click something that is not the Mapbox
                        native popup box, Mapbox will attempt to "close" the
                        popup despite there being none open.

                        Since we're using our own implementation of a popup,
                        we've bound the close event to hide the POI details
                        overlay. This, combined with the fact that the map would
                        trigger the "close" event for the overlay if another
                        marker is clicked, and the fact that we're reusing the
                        POI details overlay HTML for all markers, means that the
                        next time a marker is clicked, the POI details screen
                        for that POI would open, only to immediately close again
                        because Mapbox fired the close event. To prevent this,
                        we indicate to Mapbox that we want to trigger the close
                        event manually. This ensures that Mapbox knows there is
                        no open dialog, thus the close event won't be triggered
                        the next time the map, or something on it, is clicked.
                    */
                    spawnBanner("success", resolveI18N("poi.update.success", poiObj.name));
                    popup._onClickClose();
                    $("#update-poi-details").fadeOut(150);
                    $("#update-poi-working").fadeOut(150);
                }
            }
        }).fail(function(xhr) {
            /*
                If the update request failed, then the user should be informed
                of the reason with a red banner.
            */
            var data = xhr.responseJSON;
            var reason = resolveI18N("xhr.failed.reason.unknown_reason");
            console.log(data);
            if (data !== undefined && data.hasOwnProperty("reason")) {
                reason = resolveI18N(data["reason"]);
            }
            spawnBanner("failed", resolveI18N("poi.update.failed.message", reason));
            $("#update-poi-working").fadeOut(150);
            $("#update-poi-submit").prop("disabled", false);
        });
    });

    /*
        The "POI name" field on the "report field research" dialog. Only for
        displaying the name of the POI to make sure the user reports research to
        the right POI. The field value is not sent to the server.
    */
    $("#update-poi-name").val(poiObj.name);

    $("#poi-details").fadeIn(150);
}

/*
    This function is an event handler triggered whenever Mapbox fires its popup
    "close" event. It should close the marker and disable all event handlers
    assigned to it in `openMarker()`.
*/
function closeMarker(popup) {
    /*
        Reset the `currentMarker` ID since the POI details dialog is no longer
        open (i.e. there is no currently displayed marker).
    */
    currentMarker = -1;

    $("#poi-directions").off();
    $("#poi-close").off();
    $("#poi-add-report").off();
    $("#update-poi-submit").off();
    $("#poi-details").fadeOut(150);

    /*
        Reset the marker icons in the POI details popup.
    */
    setTimeout(function() {
        $("#poi-objective-icon").attr("src", "about:blank");
        $("#poi-reward-icon").attr("src", "about:blank");
    }, 150);
}

/*
    Event handler that cancels updating field research. This is displayed as the
    "cancel" button on the field research update dialog. It hides the update
    dialog and shows the POI details screen instead.

    The event handler that opens the field research update dialog is defined in
    the `openMarker()` function.
*/
$("#update-poi-cancel").on("click", function() {
    $("#update-poi-details").hide();
    $("#poi-details").show();
});

/*
    Gets an image URL for the given icon, i.e. marker. This takes into account
    the theme that the user has selected.

    The URL may contain the {%variant%} token, which indicates that the icon
    supports several different color variants. {%variant%}, if present, will be
    replaced with `variant` ("light" or "dark") to get the correct variant based
    on the color theme selected by the user in their local settings.
*/
function resolveIconUrl(icon) {
    var variant = settings.get("theme");
    var url = iconSets[settings.get("iconSet")][icon].split("{%variant%}").join(variant);
    return url;
}

/*
    Adds a set of marker icons to the map. The `markers` parameter is an array
    of objects, where each object describes the properties of one POI.
    format of the array is the same as the format output in JSON format by GET
    /xhr/poi.php.
*/
function addMarkers(markers) {
    markers.forEach(function(marker) {
        /*
            Create a marker element. This is the element that is displayed on
            the map itself and is rendered with the relevant icon to indicate
            the currently active field research on the POI.
        */
        var e = document.createElement("div");
        e.className =
            // Basic map marker class
            "marker "

            // Render the icon for the current reward active on the POI
            + marker.reward.type + " "

            // Set the color theme of the markers depending on the map style
            + styleMap[settings.get("mapProvider")][settings.get("mapStyle/"+settings.get("mapProvider"))] + " "

            // Set the icon set from which marker icons are fetched
            + settings.get("iconSet");

        marker["element"] = e;

        /*
            Add the marker to the global `pois` array for easy properties lookup
            from elsewhere in the script.
        */
        pois[marker.id] = marker;

        /*
            Define a Mapbox popup that is attached to the marker. This popup is
            just added to facilitate event handlers - we're making a custom
            popup overlay that will be displayed instead of this one, so the
            Mapbox native popup is hidden through CSS.
        */
        var popup = new mapboxgl.Popup({
            offset: 25
        });
        /*
            These are the event handlers we need. We need to know when a marker
            is clicked on, and when the user requested to close a marker through
            any means. These will open and close the custom POI details overlay
            respectively.
        */
        popup.on("open", function() {
            openMarker(popup, marker.id);
        });
        popup.on("close", function() {
            closeMarker(popup);
        });

        /*
            Declare and add the Mapbox marker to the map.
        */
        new mapboxgl.Marker(e)
            .setLngLat([marker.longitude, marker.latitude])
            .setPopup(popup)
            .addTo(map);
    });
}

/*
    Connects to /xhr/poi.php to retrieve an updated list of all map markers.
    This function is called periodically to ensure that the markers displayed on
    the map are up to date.
*/
function refreshMarkers() {
    $.getJSON("./xhr/poi.php", function(data) {
        var markers = data["pois"];

        markers.forEach(function(marker) {
            /*
                Retrieve the old marker from the `pois` array and replace the
                marker icon on the marker to reflect the new objective/reward.
            */
            var oldMarker = pois[marker.id];

            var oldObjective = oldMarker.objective.type;
            var oldReward = oldMarker.reward.type;
            var newObjective = marker.objective.type;
            var newReward = marker.reward.type;

            if ($(oldMarker.element).hasClass(oldReward)) {
                $(oldMarker.element).removeClass(oldReward).addClass(newReward);
            }
            if ($(oldMarker.element).hasClass(oldObjective)) {
                $(oldMarker.element).removeClass(oldObjective).addClass(newObjective);
            }

            /*
                If the POI details screen is currently open for the given
                marker, make sure that the details displayed on that screen are
                updated as well.
            */
            if (marker.id == currentMarker) {
                $("#poi-objective-icon").attr("src", resolveIconUrl(marker.objective.type));
                $("#poi-reward-icon").attr("src", resolveIconUrl(marker.reward.type));
                $("#poi-objective").text(resolveObjective(marker.objective));
                $("#poi-reward").text(resolveReward(marker.reward));
            }

            /*
                Overwrite the marker in the `pois` array with the updated values
                from the server. Use a for loop to ensure that elements that
                aren't in the received `marker` instance (such as the marker DOM
                element) aren't overwritten in `pois`.
            */
            for (var prop in marker) {
                if (marker.hasOwnProperty(prop)) {
                    pois[marker.id][prop] = marker[prop];
                }
            }
        });
    });
}

/*
    Local storage is used to save user settings client-side. There's no good
    universal way to query the browser for local storage support, so we'll
    instead just try to use it, and if our attempt fails, local storage isn't
    supported.
*/
function hasLocalStorageSupport() {
    try {
        localStorage.setItem("featureTest", "featureTest");
        localStorage.removeItem("featureTest");
        return true;
    } catch (ex) {
        return false;
    }
}

var localStorageSupport = hasLocalStorageSupport();

/*
    Saves client-side user settings to local storage. The settings have to be
    stored as a JSON encoded string, as saving the object directly to a key is
    not stable (see https://stackoverflow.com/a/2010948).
*/
function saveSettings() {
    if (localStorageSupport) {
        localStorage.setItem("settings", JSON.stringify(settings));
        console.log("Saved!");
    }
}

$(document).ready(function() {
    /*
        Many mobile browsers have an issue where the viewport height exceeds the
        window height. Ideally, setting `height: 100vh;` would result in the
        element taking up vertical space equivalent to the height of the visible
        area of the page in the browser. However, many mobile browsers have a
        top bar that contains various controls (e.g. address bar) that is hidden
        automatically upon scrolling down. Because this bar is hidden, this
        would have resulted in the viewport height changing upon scroll, and the
        continous layout updates this would cause would cause stuttering on the
        page that can be difficult to mitigate, especially at the higher
        framerates required to ensure a smooth user experience. The solution
        implemented by all major mobile browsers is to define the 100% viewport
        height as the height of the viewport when the navigation bar is hidden.
        This has the unfortunate side-effect that parts of the viewport is
        hidden beneath a scrollbar when the page first loads and the navigation
        bar is visible. There is some good research into the issue on this blog
        post by Nicolas Hoizey:

        https://nicolas-hoizey.com/2015/02/viewport-height-is-taller-than-the-
        visible-part-of-the-document-in-some-mobile-browsers.html

        The solution to prevent the scrollbar from appearing, and to ensure the
        entire contents of the page actually fits within the visible part of the
        viewport, is to manually set the height of the elements that use
        `height: 100vh;` with JavaScript when the page loads. The function below
        reads the *real* height of the visible part of the viewport, and forces
        the elements to assume that height.
    */
    var screenHeight = $(window).height();
    $('.full-container').css('height', screenHeight + 'px');

    /*
        Upon page load, the list of POIs should be fetched from the server and
        displayed. This is done after page load to ensure that all required DOM
        elements have loaded first.
    */
    $.getJSON("./xhr/poi.php", function(data) {
        addMarkers(data["pois"]);
        /*
            Automatically refresh the marker list with updated to information
            from the server to stay in sync with other users' reports.
        */
        setInterval(function() {
            refreshMarkers();
        }, autoRefreshInterval);
    });
});

/*
    In addition to the patch above that sets the height of the map to the real
    height of the viewport on load, we'll also bind an event handler to ensure
    that full-height containers retain full height when the window is resized.
*/
$(window).on("resize", function() {
    var screenHeight = $(window).height();
    $('.full-container').css('height', screenHeight + 'px');
})

/*
    Event handler for the sidebar button "Add POI". When clicked, this function
    starts the process for adding a new POI to the map. It registers an event
    handler that fetches the coordinates of a location the user clicks on on the
    map. It also displays a banner prompting the user to perform this action.
*/
$("#add-poi-start").on("click", function() {
    if (addingPoi) return;
    /*
        Set the flag that indicates that a POI is currently in process of being
        added to the map. This is used to prevent duplicate event handlers being
        registered from clicking the button multiple times.
    */
    addingPoi = true;
    $("#add-poi-banner").fadeIn(150);
    map.on('click', getCoordsForPOI);
});

/*
    Event handler for the "cancel" button on the banner that prompts users to
    click on the map to add a new POI. This function cancels that action and
    unbinds the associated event handler.
*/
$("#add-poi-cancel-banner").on("click", function() {
    disableAddPOI(true);
});

/*
    Event handler for the "cancel" button on the "Add POI" dialog that requests
    users for more information about the POI they are adding. This function
    cancels the action of adding the POI and closes the dialog.
*/
$("#add-poi-cancel").on("click", function() {
    disableAddPOI(true);
    $("#add-poi-details").fadeOut();
});

/*
    Event handler for the "submit" button on the "Add POI" dialog that requests
    users for more information about the POI they are adding. This function
    sends a PUT request to /xhr/poi.php to request the addition of the new POI.
*/
$("#add-poi-submit").on("click", function() {
    /*
        Immediately disable the submit button to prevent duplicate submissions.
    */
    $("#add-poi-submit").prop("disabled", true);

    var poiName = $("#add-poi-name").val();
    var poiLat = parseFloat($("#add-poi-lat").val());
    var poiLon = parseFloat($("#add-poi-lon").val());

    /*
        Since adding a new POI involves a server call, it may take a while
        before the script can proceed from here. Display a loading animation
        while we wait for the call to complete.
    */
    $("#add-poi-working").fadeIn(150);
    $.ajax({
        url: "./xhr/poi.php",
        type: "PUT",
        dataType: "json",
        data: JSON.stringify({
            lat: poiLat,
            lon: poiLon,
            name: poiName
        }),
        statusCode: {
            201: function(data) {
                /*
                    The POI add request was accepted. `data` contains an array
                    as defined in PUT /xhr/poi.php. Add the marker to the map
                    so that research can be reported for it immediately.
                */
                var markers = [data.poi];
                addMarkers(markers);

                addingPoi = false;
                spawnBanner("success", resolveI18N("poi.add.success", poiName));
                $("#add-poi-details").fadeOut(150);
                $("#add-poi-working").fadeOut(150);
            }
        }
    }).fail(function(xhr) {
        /*
            The POI add request was denied for some reason. The user should be
            informed of the reason the addition was deined through a banner
            overlay.
        */
        var data = xhr.responseJSON;
        var reason = resolveI18N("xhr.failed.reason.unknown_reason");
        console.log(data);
        if (data !== undefined && data.hasOwnProperty("reason")) {
            reason = resolveI18N(data["reason"]);
        }
        spawnBanner("failed", resolveI18N(
            "poi.add.failed.message",
            poiName,
            reason
        ));
        $("#add-poi-working").fadeOut(150);
        /*
            Re-enable the submit button that was previously disabled, to allow
            the user to make more attempts at adding the POI once the submission
            error that triggered the addition failure has been resolved by the
            user.
        */
        $("#add-poi-submit").prop("disabled", false);
    });
});

/*
    Event handler for the sidebar button responsible for opening the client-side
    local user settings. Displays a setting configuration panel that allows the
    user to change various display settings that apply to the user's local
    browser session only.
*/
$("#menu-open-settings").on("click", function() {
    /*
        Update all the input and select boxes in the configuration pane with the
        values currently stored in the configuration.
    */
    $(".user-setting").each(function() {
        var key = $(this).attr("data-key");
        var value = settings.get(key, true);
        $(this).val(value);
    });

    /*
        Hides the map and map-specific sidebar items, and shows the settings
        pane and settings-specific sidebar items instead.
    */
    $("#map-menu").hide();
    $("#map-container").hide();
    $("#settings-container").show();
    $("#settings-menu").show();

    return false;
});

/*
    Event handler for the sidebar button responsible for closing the client-side
    local user settings.
*/
$("#menu-close-settings").on("click", function() {
    /*
        Hides the settings pane and settings-specific sidebar items, and shows
        the map and map-specific sidebar items instead.
    */
    $("#settings-menu").hide();
    $("#settings-container").hide();
    $("#map-container").show();
    $("#map-menu").show();

    return false;
});

/*
    Event handler for the "save settings" button on the settings pane. Loops
    over all configurable user settings and saves them to local storage.
*/
$("#user-settings-save").on("click", function() {
    $(".user-setting").each(function() {
        var key = $(this).attr("data-key");
        var value = $(this).val();

        /*
            This block of `case` statements allows saving settings up to 10
            objects deep in `settings`. There probably a much better way to do
            this in a recursive manner. TODO?
        */
        var tree = key.split("/");
        switch (tree.length) {
            case 1:
                settings[tree[0]] = value;
                break;
            case 2:
                settings[tree[0]][tree[1]] = value;
                break;
            case 3:
                settings[tree[0]][tree[1]][tree[2]] = value;
                break;
            case 4:
                settings[tree[0]][tree[1]][tree[2]][tree[3]] = value;
                break;
            case 5:
                settings[tree[0]][tree[1]][tree[2]][tree[3]][tree[4]] = value;
                break;
            case 6:
                settings[tree[0]][tree[1]][tree[2]][tree[3]][tree[4]][tree[5]] = value;
                break;
            case 7:
                settings[tree[0]][tree[1]][tree[2]][tree[3]][tree[4]][tree[5]][tree[6]] = value;
                break;
            case 8:
                settings[tree[0]][tree[1]][tree[2]][tree[3]][tree[4]][tree[5]][tree[6]][tree[7]] = value;
                break;
            case 9:
                settings[tree[0]][tree[1]][tree[2]][tree[3]][tree[4]][tree[5]][tree[6]][tree[7]][tree[8]] = value;
                break;
            case 10:
                settings[tree[0]][tree[1]][tree[2]][tree[3]][tree[4]][tree[5]][tree[6]][tree[7]][tree[8]][tree[9]] = value;
                break;
        }
    });

    /*
        Save and then reload for the changes to take effect.
    */
    saveSettings();
    location.reload();
});

/*
    Event handler for the "reset all" button in the settings sidebar. Erases all
    client-side settings and returns to default configuration.
*/
$("#menu-reset-settings").on("click", function() {
    if (confirm(resolveI18N("user_settings.reset.confirm"))) {
        if (localStorageSupport) {
            /*
                Save and then reload for the changes to take effect.
            */
            localStorage.removeItem("settings");
            location.reload();
        }
    }
});
