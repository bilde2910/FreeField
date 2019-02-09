/*
    This script file is invoked from the main map page. It is responsible for
    client-side functionality for controls, adding POIs, reporting research,
    handling interactions with map markers, managing client-side settings, and
    more.
*/

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
        "basic": "light",
        "streets": "light",
        "bright": "light",
        "light": "light",
        "dark": "dark",
        "satellite": "dark"
    },
    thunderforest: {
        "cycle": "light",
        "transport": "light",
        "landscape": "light",
        "outdoors": "light",
        "transport-dark": "dark",
        "spinal-map": "dark",
        "pioneer": "light",
        "mobile-atlas": "light",
        "neighbourhood": "light"
    }
}

/*
    ------------------------------------------------------------------------
        ADDING, MOVING AND DELETING POIS
    ------------------------------------------------------------------------
*/

/*
    `mapClickHandlerActive` is a boolean flag on whether or not the user is
    currently adding or moving a POI to the map. This is set to `true` whenever
    POI adding or movement is initiated toprevent duplicate processing of POIs.
*/
var mapClickHandlerActive = false;

/*
    Event handler for the sidebar button "Add POI". When clicked, this function
    starts the process for adding a new POI to the map. It registers an event
    handler that fetches the coordinates of a location the user clicks on on the
    map. It also displays a banner prompting the user to perform this action.
*/
$("#add-poi-start").on("click", function(e) {
    e.preventDefault();
    if (mapClickHandlerActive) return;

    /*
        Set the flag that indicates that a POI is currently in process of being
        added to the map. This is used to prevent duplicate event handlers being
        registered from clicking the button multiple times.
    */
    mapClickHandlerActive = true;
    $("#add-poi-banner").fadeIn(150);
    MapImpl.bindMapClickHandler(getCoordsForPOI);
});

/*
    Event handler for the "cancel" button on the banner that prompts users to
    click on the map to add a new POI. This function cancels that action and
    unbinds the associated event handler.
*/
$("#add-poi-cancel-banner").on("click", function(e) {
    e.preventDefault();
    disableAddMovePOI(true);
});

/*
    Event handler for the "cancel" button on the banner that prompts users to
    click on the map to move an existing POI. This function cancels that action
    and unbinds the associated event handler.
*/
$("#move-poi-cancel-banner").on("click", function(e) {
    e.preventDefault();
    disableAddMovePOI(true);
});

/*
    Removes the banner prompting users to click on the map to select a location
    when adding a new POI, and also unbinds the event handlers that opens the
    dialog box prompting users for details about the POI they are adding. If
    `setFlag` is set, `mapClickHandlerActive` is reset to the default `false`
    value; otherwise the `mapClickHandlerActive` flag remains `true`.

    `disableAddMovePOI(false)` is called when the user has clicked on the map to
    select a location. The user has not finished adding the POI yet, hence
    `mapClickHandlerActive` should remain true.

    `disableAddMovePOI(true)` is called if the user cancels adding a new POI by
    clicking on the "cancel" link on the banner prompting them to select a
    location. This function is also called when the movement process has
    completed.
*/
function disableAddMovePOI(setFlag) {
    MapImpl.unbindMapClickHandler();
    /*
        Hide the "please select a location on the map" banners.
    */
    $("#add-poi-banner").fadeOut(150);
    $("#move-poi-banner").fadeOut(150);
    mapClickHandlerActive = !setFlag;
}

/*
    `getCoordsForPOI()` is an event handler that, when called with a map click
    event containing coordinate data, opens a dialog box that prompts the user
    for more details about the POI, such as its name.
*/
function getCoordsForPOI(lat, lon) {
    /*
        Disables the map click event handler and hides the POI banner.
    */
    disableAddMovePOI(false);
    /*
        The dialog box allowing the user to specify details for the newly added
        POI contains text fields displaying the coordinates of the POI. These
        cannot be changed, but are there to give the user an indication of the
        coordinates of the POI they are adding.
    */
    $("#add-poi-lon").val(lon);
    $("#add-poi-lat").val(lat);
    /*
        When a POI is submitted, the button that submits the form is disabled to
        prevent duplicate submissions. Make sure that the button is enabled when
        the dialog is displayed.
    */
    $("#add-poi-submit").prop("disabled", false);

    $("#add-poi-details").fadeIn(150);
}

/*
    Event handler for the "cancel" button on the "Add POI" dialog that requests
    users for more information about the POI they are adding. This function
    cancels the action of adding the POI and closes the dialog.
*/
$("#add-poi-cancel").on("click", function() {
    disableAddMovePOI(true);
    $("#add-poi-details").fadeOut();
});

/*
    Event handler for the "submit" button on the "Add POI" dialog that requests
    users for more information about the POI they are adding. This function
    sends a PUT request to /api/poi.php to request the addition of the new POI.
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
        url: "./api/poi.php",
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
                    as defined in PUT /api/poi.php. Add the marker to the map
                    so that research can be reported for it immediately.
                */
                var markers = [data.poi];
                addMarkers(markers);

                mapClickHandlerActive = false;
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

        if (data !== undefined && data.hasOwnProperty("reason")) {
            reason = resolveI18N("xhr.failed.reason." + data["reason"]);
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
    ------------------------------------------------------------------------
        BANNER OVERLAYS
    ------------------------------------------------------------------------
*/

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
    var node = $(
        '<div class="banner ' + type + '">' +
            '<div class="banner-inner">' +
                encodeHTML(message) +
            '</div>' +
        '</div>'
    );
    $("#dynamic-banner-container").append(node);

    /*
        Assign an event handler that dismisses the banner on click, show the
        banner, and schedule the banner to dismiss automatically after 5
        seconds.
    */
    node.on("click", function() {
        dismiss(node);
    });
    node.fadeIn(150);
    setTimeout(function() {
        dismiss(node);
    }, 5000);
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
    Fades away the given element.
*/
function dismiss(node) {
    node.fadeOut(150);
}

/*
    ------------------------------------------------------------------------
        MAP MARKER, POI DETAILS AND RESEARCH REPORTING
    ------------------------------------------------------------------------
*/

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
    Gets an image URL for the given species, i.e. marker. This takes into
    account the theme that the user has selected.

    The URL may contain the {%variant%} token, which indicates that the icon
    supports several different color variants. {%variant%}, if present, will be
    replaced with `variant` ("light" or "dark") to get the correct variant based
    on the color theme selected by the user in their local settings.
*/
function resolveSpeciesUrl(icon) {
    var variant = settings.get("theme");
    var set = isc_opts.species.themedata[settings.get("speciesSet")];

    /*
        Try to determine the range of icons in the icon set in which the species
        icon can be found.
    */
    var range = null;
    for (var key in set) {
        if (!set.hasOwnProperty(key)) continue;
        if (key == "range" || key.startsWith("range")) {
            if (
                set[key]["range_start"] <= icon &&
                set[key]["range_end"] >= icon
            ) {
                range = set[key];
                break;
            }
        }
    }

    /*
        If no matching range was found, try the "default" section.
    */
    if (range == null) {
        for (var key in set) {
            if (!set.hasOwnProperty(key)) continue;
            if (key == "default") {
                range = set[key];
                break;
            }
        }
    }

    /*
        Create an URL for the species marker.
    */
    var uri = isc_opts.species.baseuri
            + "themes/species/"
            + settings.get("speciesSet")
            + "/";

    if (range.hasOwnProperty("vector")) {
        uri += range.vector.split("{%n%}").join(icon).split("{%variant%}").join(variant);
    } else if (tdata.hasOwnProperty("raster")) {
        uri += range.raster.split("{%n%}").join(icon).split("{%variant%}").join(variant);
    } else {
        uri = null;
    }
    return uri;
}

/*
    Adds a set of marker icons to the map. The `markers` parameter is an array
    of objects, where each object describes the properties of one POI.
    format of the array is the same as the format output in JSON format by GET
    /api/poi.php.
*/
function addMarkers(markers) {
    markers.forEach(function(marker) {
        /*
            Create a marker element. This is the element that is displayed on
            the map itself and is rendered with the relevant icon to indicate
            the currently active field research on the POI.
        */
        var e = document.createElement("div");
        e.id = "dynamic-marker-" + marker.id;
        e.className =
            // Basic map marker class
            "marker "

            // Render the icon for the current research active on the POI
            + marker[settings.get("markerComponent")].type + " "

            // Set the color theme of the markers depending on the map style
            + styleMap[settings.get("mapProvider")][settings.get("mapStyle-"+settings.get("mapProvider"))] + " "

            // Set the icon set from which marker icons are fetched
            + settings.get("iconSet") + " "

            // Set the species set from which species icons are fetched
            + settings.get("speciesSet");

        /*
            Add the reward species if encounter reward, if known.
        */
        if (
            settings.get("markerComponent") == "reward" &&
            marker.reward.type == "encounter" &&
            marker.reward.params.hasOwnProperty("species") &&
            marker.reward.params.species.length == 1
        ) {
            e.className += " sp-" + marker.reward.params.species[0];
        }
        marker["elementId"] = e.id;

        /*
            Add the marker to the map itself. Get a reference to the marker
            object itself, in case it has to be updated later.
        */
        var implMarkerObj = MapImpl.addMarker(e, marker.latitude, marker.longitude,
            function(markerObj) {
                openMarker(markerObj, marker.id);
            }, function(markerObj) {
                closeMarker(markerObj);
            },
            marker[settings.get("markerComponent")].type
        );
        marker["implObject"] = implMarkerObj;

        /*
            Add the marker to the global `pois` array for easy properties lookup
            from elsewhere in the script.
        */
        pois[marker.id] = marker;

    });
}

/*
    Connects to /api/poi.php to retrieve an updated list of all map markers.
    This function is called periodically to ensure that the markers displayed on
    the map are up to date.
*/
function refreshMarkers() {
    $.getJSON("./api/poi.php", function(data) {
        var markers = data["pois"];

        markers.forEach(function(marker) {
            /*
                Check if the POI already exists in the `pois` array. If not,
                add the marker.
            */
            if (
                pois.length < marker.id ||
                pois[marker.id] == null ||
                !("elementId" in pois[marker.id])
            ) {
                addMarkers([marker]);
                return;
            }

            /*
                Retrieve the old marker from the `pois` array and replace the
                marker icon on the marker to reflect the new objective/reward.
            */
            var oldMarker = pois[marker.id];

            var oldObjective = oldMarker.objective.type;
            var oldReward = oldMarker.reward.type;
            var newObjective = marker.objective.type;
            var newReward = marker.reward.type;

            var markerHtml = $("#" + oldMarker.elementId);

            switch (settings.get("markerComponent")) {
                case "reward":
                    /*
                        Replace the reward marker.
                    */
                    if (markerHtml.hasClass(oldReward)) {
                        markerHtml.removeClass(oldReward).addClass(newReward);
                        MapImpl.updateMarker(oldMarker.implObject, oldReward, newReward);
                    }
                    /*
                        If the reward marker is an encounter, see if a species
                        class has been defined. If so, remove it.
                    */
                    var cls;
                    if (oldReward == "encounter" && (cls = markerHtml.attr("class").match(/\bsp-\d+\b/))) {
                        markerHtml.removeClass(cls[0]);
                    }
                    /*
                        If a singular species is available as reward from the
                        research task, replace the marker with an icon for the
                        species in question.
                    */
                    if (
                        newReward == "encounter" &&
                        marker.reward.params.hasOwnProperty("species") &&
                        marker.reward.params.species.length == 1
                    ) {
                        markerHtml.addClass("sp-" + marker.reward.params.species[0]);
                    }
                    break;
                case "objective":
                    /*
                        Replace the objective marker.
                    */
                    if (markerHtml.hasClass(oldObjective)) {
                        markerHtml.removeClass(oldObjective).addClass(newObjective);
                        MapImpl.updateMarker(oldMarker.implObject, oldObjective, newObjective);
                    }
                    break;
            }

            /*
                If the POI details screen is currently open for the given
                marker, make sure that the details displayed on that screen are
                updated as well.
            */
            if (marker.id == currentMarker) {
                $("#poi-objective-icon").attr("src", resolveIconUrl(marker.objective.type));
                if (
                    /*
                        If a single encounter species is known, display the icon of that
                        species instead of the generic encounter reward icon.
                    */
                    marker.reward.type == "encounter" &&
                    marker.reward.params.hasOwnProperty("species") &&
                    marker.reward.params.species.length == 1
                ) {
                    $("#poi-reward-icon").attr("src", resolveSpeciesUrl(marker.reward.params.species[0]));
                } else {
                    $("#poi-reward-icon").attr("src", resolveIconUrl(marker.reward.type));
                }
                $("#poi-objective").text(resolveObjective(marker.objective));
                $("#poi-reward").text(resolveReward(marker.reward));
            }

            /*
                If the POI has been moved on another client, it should also be
                moved locally.
            */
            if (
                oldMarker.latitude !== marker.latitude ||
                oldMarker.longitude !== marker.longitude
            ) {
                MapImpl.moveMarker(
                    oldMarker.implObject,
                    marker.latitude,
                    marker.longitude
                );
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

        /*
            POIs can be deleted. If any locally stored POIs no longer exist,
            remove the relevant markers from the map. To do this, search
        */
        for (var i = 0; i < pois.length; i++) {
            if (pois[i] != null) {
                var exists = false;
                for (var j = 0; j < markers.length; j++) {
                    if (pois[i].id == markers[j].id) {
                        exists = true;
                        break;
                    }
                }
                if (!exists) {
                    MapImpl.removeMarker(pois[i].implObject);
                    pois[i] = null;
                }
            }
        }
    }).fail(function(xhr) {
        /*
            If the request failed, then the user should be informed of the
            reason with a red banner.
        */
        var data = xhr.responseJSON;
        var reason = resolveI18N("xhr.failed.reason.unknown_reason");

        if (data !== undefined && data.hasOwnProperty("reason")) {
            reason = resolveI18N("xhr.failed.reason." + data["reason"]);
        }
        spawnBanner("failed", resolveI18N("poi.list.failed.message", reason));
    });
}

$(document).ready(function() {
    /*
        Upon page load, the list of POIs should be fetched from the server and
        displayed. This is done after page load to ensure that all required DOM
        elements have loaded first.
    */
    $.getJSON("./api/poi.php", function(data) {
        addMarkers(data["pois"]);
        /*
            Handle deep-linking via URL hashes.
        */
        var hashFound = false;
        if (location.hash.length >= 2) {
            if (location.hash.startsWith("#/poi/")) {
                /*
                    The link has a POI ID. Open the ID listed in the URL.
                */
                var poiId = location.hash.substring("#/poi/".length);
                if (poiId.indexOf("/") >= 0) {
                    poiId = poiId.substring(0, poiId.indexOf("/"));
                }
                /*
                    If the POI is found, open it, otherwise, reset the hash back
                    to the main map view ("#/").
                */
                poiId = parseInt(poiId);
                if (!isNaN(poiId) && poiId >= 0 && poiId < pois.length) {
                    var poi = pois[poiId];
                    if (poi != null && poi.hasOwnProperty("elementId")) {
                        hashFound = true;
                        MapImpl.simulatePOIClick(poi);
                    }
                }
            } else if (location.hash.startsWith("#/settings")) {
                /*
                    The link points directly to the user settings page.
                */
                hashFound = true;
                $("#menu-open-settings").trigger("click");
            }
        }

        /*
            If no handler for the current URL was successful, reset the hash
            value back to the default map view URL ("#/").
        */
        if (false && !hashFound) {
            if (history.replaceState) {
                history.replaceState(null, null, "#/");
            } else {
                location.hash = "#/";
            }
        }
    }).fail(function(xhr) {
        /*
            If the request failed, then the user should be informed of the
            reason with a red banner.
        */
        var data = xhr.responseJSON;
        var reason = resolveI18N("xhr.failed.reason.unknown_reason");

        if (data !== undefined && data.hasOwnProperty("reason")) {
            reason = resolveI18N("xhr.failed.reason." + data["reason"]);
        }
        spawnBanner("failed", resolveI18N("poi.list.failed.message", reason));
    }).always(function() {
        /*
            Automatically refresh the marker list with updated to information
            from the server to stay in sync with other users' reports.
        */
        setInterval(function() {
            refreshMarkers();
        }, autoRefreshInterval);
    });
})

/*
    Whenever a marker is clicked on the map, this function is called with the
    instance of the popup object that would open, as well as the ID of the
    marker that was clicked.

    The purpose of the function is to display a custom marker popup. The default
    popup objects aren't very flexible, and don't allow us to display and style
    controls like we want. Hence, what we do is override the marker with a
    custom one. This function will configure and display that popup.

    When a marker is opened, we also assign the ID of that marker to
    `currentMarker`. This is because the map periodically checks for updates to
    reported field research, and if an update is made to the POI that is
    currently open, the POI details dialog should reflect that by updating the
    displayed research icons and text.
*/
var currentMarker = -1;
function openMarker(markerObj, id) {
    currentMarker = id;

    /*
        Set the address bar URL to reflect the POI, to let the user copy and
        paste it to share the specific POI with others.
    */
    if (history.replaceState) {
        history.replaceState(null, null, "#/poi/" + id + "/");
    } else {
        location.hash = "#/poi/" + id + "/";
    }

    /*
        Get data for the POI in the list of POIs received from the server. This
        list is stored in the `pois` variable, and can be looked up using the ID
        of the POI. Once we have the data, we can put it in the popup.
    */
    var poiObj = pois[id];
    $("#poi-name").text(poiObj.name);
    $("#poi-objective-icon").attr("src", resolveIconUrl(poiObj.objective.type));
    if (
        /*
            If a single encounter species is known, display the icon of that
            species instead of the generic encounter reward icon.
        */
        poiObj.reward.type == "encounter" &&
        poiObj.reward.params.hasOwnProperty("species") &&
        poiObj.reward.params.species.length == 1
    ) {
        $("#poi-reward-icon").attr("src", resolveSpeciesUrl(poiObj.reward.params.species[0]));
    } else {
        $("#poi-reward-icon").attr("src", resolveIconUrl(poiObj.reward.type));
    }
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
        MapImpl.closeMarker(markerObj);
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
    var canManagePOIs = permissions["admin/pois/general"];
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
    } else if (!isAuthenticated()) {
        /*
            If the user does not have permission to report research, but the
            reason is that they aren't logged in, then redirect the user to the
            login page instead of hiding the button. This is better UX than
            simply removing the button, leaving new users clueless about how to
            report research since 1) the button isn't shown and 2) it isn't
            immediately obvious that users have to sign in.
        */
        $("#poi-add-report").on("click", function() {
            location.href = "./auth/login.php?continue="
                          + encodeURIComponent("/" + location.hash);
        });
        $("#poi-add-report").show();
    } else {
        $("#poi-add-report").hide();
    }
    if (canManagePOIs) {
        /*
            Event handler for the sidebar button "Add POI". When clicked, this function
            starts the process for adding a new POI to the map. It registers an event
            handler that fetches the coordinates of a location the user clicks on on the
            map. It also displays a banner prompting the user to perform this action.
        */
        /*
            Event handler for the "Move POI" button. When clicked, this button
            starts the process for moving a POI on the map. It registers an
            event handler that fetches the coordinates of a location the user
            clicks on on the map. It also displays a banner prompting the user
            to perform this action.
        */
        $("#poi-move").on("click", function() {
            if (mapClickHandlerActive) return;
            /*
                Set the flag that indicates that a POI is currently in process
                of being moved on the map. This is used to prevent duplicate
                event handlers being registered from clicking the button
                multiple times.
            */
            mapClickHandlerActive = true;
            /*
                Replace the POI details dialog with the banner prompting users
                to select a new location for the POI.
            */
            MapImpl.closeMarker(markerObj);
            $("#move-poi-banner").fadeIn(150);
            MapImpl.bindMapClickHandler(function(lat, lon) {
                /*
                    Disables the map click event handler and hides the POI
                    banner. Since we're initiating a request to the server,
                    display a "working" spinner popup to visually invidate to
                    the user that the POI is being moved.
                */
                disableAddMovePOI(true);
                $("#move-poi-working").fadeIn(150);
                $.ajax({
                    url: "./api/poi.php",
                    type: "PATCH",
                    dataType: "json",
                    data: JSON.stringify({
                        id: poiObj.id,
                        moveTo: {
                            latitude: lat,
                            longitude: lon
                        }
                    }),
                    statusCode: {
                        204: function(data) {
                            /*
                                If the request was successful, update the
                                display of the marker on the map to reflect the
                                new location. Also update the instance of the
                                object in `pois` with the new location.
                            */
                            poiObj.latitude = lat;
                            poiObj.longitude = lon;
                            MapImpl.moveMarker(poiObj.implObject, lat, lon);

                            /*
                                Let the user know that the POI was successfully
                                moved, and then close the waiting popup.
                            */
                            spawnBanner("success", resolveI18N("poi.move.success"));
                            $("#move-poi-working").fadeOut(150);
                        }
                    }
                }).fail(function(xhr) {
                    /*
                        If the update request failed, then the user should be
                        informed of the reason with a red banner.
                    */
                    var data = xhr.responseJSON;
                    var reason = resolveI18N("xhr.failed.reason.unknown_reason");

                    if (data !== undefined && data.hasOwnProperty("reason")) {
                        reason = resolveI18N("xhr.failed.reason." + data["reason"]);
                    }
                    spawnBanner("failed", resolveI18N("poi.move.failed.message", reason));
                    $("#move-poi-working").fadeOut(150);
                });
            });
        });
        /*
            Event handler for the "Delete POI" button. When clicked, this button
            starts the process for removing a POI on the map.
        */
        $("#poi-delete").on("click", function() {
            if (confirm(resolveI18N("poi.delete.confirm"))) {
                MapImpl.closeMarker(markerObj);
                /*
                    Since we're initiating a request to the server, display a
                    "working" spinner popup to visually invidate to the user
                    that the POI is being deleted.
                */
                $("#delete-poi-working").fadeIn(150);
                $.ajax({
                    url: "./api/poi.php",
                    type: "DELETE",
                    dataType: "json",
                    data: JSON.stringify({
                        id: poiObj.id
                    }),
                    statusCode: {
                        204: function(data) {
                            /*
                                If the request was successful, remove the marker
                                from the map.
                            */
                            MapImpl.removeMarker(poiObj.implObject);

                            /*
                                Let the user know that the POI was successfully
                                moved, and then close the waiting popup.
                            */
                            spawnBanner("success", resolveI18N("poi.delete.success"));
                            $("#delete-poi-working").fadeOut(150);
                        }
                    }
                }).fail(function(xhr) {
                    /*
                        If the deletion request failed, then the user should be
                        informed of the reason with a red banner.
                    */
                    var data = xhr.responseJSON;
                    var reason = resolveI18N("xhr.failed.reason.unknown_reason");

                    if (data !== undefined && data.hasOwnProperty("reason")) {
                        reason = resolveI18N("xhr.failed.reason." + data["reason"]);
                    }
                    spawnBanner("failed", resolveI18N("poi.delete.failed.message", reason));
                    $("#delete-poi-working").fadeOut(150);
                });
            }
        });
    }

    /*
        Event handler for the submit button on the "report field research"
        dialog. This function will initiate an HTTP PATCH request to
        /api/poi.php to update the current field research on the POI. A banner
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
                resolveI18N("xhr.failed.reason.objective_null")
            ));
            return;
        }
        var reward = $("#update-poi-reward").val();
        if (reward == null) {
            spawnBanner("failed", resolveI18N(
                "poi.update.failed.message",
                resolveI18N("xhr.failed.reason.reward_null")
            ));
            return;
        }

        /*
            Determine if the user selected a pre-defined common research
            objective. If so, retrieve the objective it represents from the
            `commonObjectives` object.
        */
        if (objective.startsWith("_c_")) {
            var commonIndex = parseInt(objective.substring(3));
            objective = commonObjectives[commonIndex].type;
        }

        /*
            Ensure that all required parameters are set for both the objective
            and reward components of the research quest. This is done by
            retrieving the list of required parameters from the `objectives` and
            `rewards` objects, populated from /includes/data/objectives.yaml and
            /includes/data/rewards.yaml. The definitions for the objective and
            reward contain these lists, so we loop over them, fetch the user
            data from the dialog, and ensure that none of the fields are `null`
            or empty. If they are, we abort the submission and notify the user
            with a banner.
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
            url: "./api/poi.php",
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

                    switch (settings.get("markerComponent")) {
                        case "reward":
                            if ($("#" + poiObj.elementId).hasClass(oldReward)) {
                                $("#" + poiObj.elementId).removeClass(oldReward).addClass(reward);
                                MapImpl.updateMarker(poiObj.implObject, oldReward, reward);
                            }
                            break;
                        case "objective":
                            if ($("#" + poiObj.elementId).hasClass(oldObjective)) {
                                $("#" + poiObj.elementId).removeClass(oldObjective).addClass(objective);
                                MapImpl.updateMarker(poiObj.implObject, oldObjective, objective);
                            }
                            break;
                    }
                    poiObj.objective = {
                        type: objective,
                        params: objParams
                    };
                    poiObj.reward = {
                        type: reward,
                        params: rewParams
                    };

                    /*
                        Let the user know that the research was successfully
                        reported, and then close the report dialog.

                        Note that at this point, the POI details popup is
                        already hidden - we hid it when we displayed the "report
                        field research" dialog - but as far as the underlying
                        map provider concerns, the popup is still open, because
                        it hasn't received a click event on either a "close"
                        button on the popup, or a click event somewhere else on
                        the map. This means that if we try to click something
                        that is not the underlying native popup box, the map
                        will attempt to "close" the popup despite there being
                        none open.

                        Since we're using our own implementation of a popup,
                        we've bound the close event to hide the POI details
                        overlay. This, combined with the fact that the map would
                        trigger the "close" event for the overlay if another
                        marker is clicked, and the fact that we're reusing the
                        POI details overlay HTML for all markers, means that the
                        next time a marker is clicked, the POI details screen
                        for that POI would open, only to immediately close again
                        because the map fired the close event. To prevent this,
                        we indicate to the map implementation that we want to
                        trigger the close event manually. This ensures that the
                        implementation knows there is no open dialog, thus the
                        close event won't be triggered the next time the map, or
                        something on it, is clicked.
                    */
                    spawnBanner("success", resolveI18N("poi.update.success", poiObj.name));
                    MapImpl.closeMarker(markerObj);
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

            if (data !== undefined && data.hasOwnProperty("reason")) {
                reason = resolveI18N("xhr.failed.reason." + data["reason"]);
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
    This function is an event handler triggered whenever the map implementation
    fires its popup "close" event. It should close the marker and disable all
    event handlers assigned to it in `openMarker()`.
*/
function closeMarker(markerObj) {
    /*
        When using mapbox-gl.js, this function is somehow called whenever
        markers are added to the map. Ensure that this function can only be
        called if the marker is actually open.
    */
    if (currentMarker != -1) {
        /*
            Reset the `currentMarker` ID since the POI details dialog is no longer
            open (i.e. there is no currently displayed marker).
        */
        currentMarker = -1;

        /*
            Set the address bar URL back to the main map.
        */
        if (history.replaceState) {
            history.replaceState(null, null, "#/");
        } else {
            location.hash = "#/";
        }

        $("#poi-move").off();
        $("#poi-delete").off();
        $("#poi-directions").off();
        $("#poi-close").off();
        $("#poi-add-report").off();
        $("#update-poi-submit").off();
        $("#poi-details").fadeOut(150);

        /*
            Reset the marker icons in the POI details popup.
        */
        setTimeout(function() {
            // 1x1 transparent GIF
            var blankImage = "data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7";
            $("#poi-objective-icon").attr("src", blankImage);
            $("#poi-reward-icon").attr("src", blankImage);
        }, 150);
    }
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
    ------------------------------------------------------------------------
        LOCAL USER SETTINGS
    ------------------------------------------------------------------------
*/

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
    If local storage is not supported by the browser, alert the user, because it
    means that local settings will not be saved for them.
*/
if (!localStorageSupport) {
    alert(resolveI18N("user_settings.no_local_storage"));
}

/*
    Saves client-side user settings to local storage. The settings have to be
    stored as a JSON encoded string, as saving the object directly to a key is
    not stable (see https://stackoverflow.com/a/2010948).
*/
function saveSettings() {
    if (localStorageSupport) {
        localStorage.setItem("settings", JSON.stringify(settings));
    }
}

/*
    Event handler for the sidebar button responsible for opening the client-side
    local user settings. Displays a setting configuration panel that allows the
    user to change various display settings that apply to the user's local
    browser session only.
*/
$("#menu-open-settings").on("click", function(e) {
    e.preventDefault();

    /*
        For deep-linking.
    */
    if (history.replaceState) {
        history.replaceState(null, null, "#/settings/");
    } else {
        location.hash = "#/settings/";
    }

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
        Display the preview for the icon set selection box.
    */
    if ($("#icon-selector").length > 0) {
        $("#icon-selector").trigger("input");
    }
    if ($("#species-selector").length > 0) {
        $("#species-selector").trigger("input");
    }

    /*
        Hides the map and map-specific sidebar items, and shows the settings
        pane and settings-specific sidebar items instead.
    */
    $("#map-menu").hide();
    $("#map-container").hide();
    $("#settings-container").show();
    $("#settings-menu").show();
});

/*
    Event handler for the sidebar button responsible for closing the client-side
    local user settings.
*/
$("#menu-close-settings").on("click", function(e) {
    e.preventDefault();

    /*
        For deep-linking.
    */
    if (history.replaceState) {
        history.replaceState(null, null, "#/");
    } else {
        location.hash = "#/";
    }

    /*
        Hides the settings pane and settings-specific sidebar items, and shows
        the map and map-specific sidebar items instead.
    */
    $("#settings-menu").hide();
    $("#settings-container").hide();
    $("#map-container").show();
    $("#map-menu").show();
});

/*
    If the user clicks on the "sign out everywhere" button, the script that
    applies user settings server-side should be informed of the intention to
    perform that action. The best way to this when the button is in the middle
    of the form is to add a hidden element on the form before it is submitted
    that flags the sign-out action for the processing script.

    Normally, one could use `<input type="submit" name="sign-out-everywhere">`,
    i.e. a submit button with an associated name, to identify the submit button
    that was clicked server-side, so the correct action may be performed.
    However, when users submit the form by pressing Enter in some form field,
    the first submit button on the form is the one that is selected by default.
    Since the "sign out everywhere" button is the first submit button on the
    form (and it isn't really practical to place it after the "save settings"
    button), pressing Enter in any form field would cause the user to have all
    of their sessions invalidated. The solution to this is to let the "sign out"
    button be an `<input type="button">` instead, and binding an event handler
    to it that performs the following:
*/
$("#sign-out-everywhere").on("click", function() {
    var form = $("#user-settings-form");
    var signOutRequest = $('<input type="hidden" name="sign-out-everywhere" />')
    form.append(signOutRequest);
    form.submit();
});

/*
    Event handler for the user settings form on the settings pane. Loops over
    all configurable user settings and saves them to local storage before the
    form is submitted for processing of server-side settings.
*/
$("#user-settings-form").on("submit", function() {
    $(".user-setting").each(function() {
        var key = $(this).attr("data-key");
        var value = $(this).val();

        /*
            Push the setting change to `settings`. The process and thinking
            behind these loops are described in detail in
            /includes/lib/config.php, which uses the same saving procedure for
            the server-side configuration file.
        */
        var s = key.split("/");
        for (var i = s.length - 1; i >= 0; i--) {
            /*
                Loop over the segments and for every iteration, find the parent
                array directly above the current `s[i]`.
            */
            var parent = settings;
            for (var j = 0; j < i; j++) {
                parent = parent[s[j]];
            }
            /*
                Update the value of `s[i]` in the array. Store a copy of this
                array as the value to assign to the next parent segment.
            */
            parent[s[i]] = value;
            value = parent;
            /*
                The next iteration finds the next parent above the current
                parent and replaces the value of the key in that parent which
                would hold the value of the current parent array with the
                updated parent array that has the setting change applied to it.
            */
        }
    });

    /*
        Save and then submit the form/reload for the changes to take effect.
    */
    saveSettings();
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

/*
    Attempt to read settings from `localStorage` on load. If successful,
    overwrite the relevant entries in `settings`.
*/
if (hasLocalStorageSupport()) {
    var storedSettings = JSON.parse(localStorage.getItem("settings"));
    if (storedSettings !== null) {
        var keys = Object.keys(storedSettings);
        for (var i = 0; i < keys.length; i++) {
            settings[keys[i]] = storedSettings[keys[i]];
        }
    }
}

/*
    ------------------------------------------------------------------------
        GENERAL USER INTERFACE
    ------------------------------------------------------------------------
*/

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
    Grab a stylesheet for the "dark" or "light" themes depending on the user's
    selection.
*/
$("head").append('<link rel="stylesheet" ' +
                       'type="text/css" ' +
                       'href="./css/' + settings.get("theme") +
                             '.css?t=' + linkMod[
                                 "/css/" + settings.get("theme") + ".css"
                             ] + '">');

/*
    Configure the `IconSetOption` selector to use the correct user theme color.
*/
isc_opts.icons.colortheme = settings.get("theme");
isc_opts.species.colortheme = settings.get("theme");

/*
    Initialize the map.
*/
MapImpl.init("map", settings);

/*
    Automatically save the current center point and zoom level of
    the map to `localStorage` if the user pans or zooms on the map.
    This allows the map to retain the current view the next time the
    user visits this FreeField instance.
*/
var lastCenter = MapImpl.getCenter();
var lastZoom = MapImpl.getZoomLevel();
setInterval(function() {
    var center = MapImpl.getCenter();
    var zoom = MapImpl.getZoomLevel();
    if (center != lastCenter || zoom != lastZoom) {
        lastCenter = center;
        lastZoom = zoom;
        settings.center.longitude = center.lon;
        settings.center.latitude = center.lat;
        settings.zoom = zoom;
        saveSettings();
    }
}, 1000);

/*
    Mobile: Hide the sidebar if any of its elements is clicked. This can be done
    by triggering a click on the hamburger menu icon if it is displayed on the
    page. (If it's not displayed, it's not considered a mobile client!)
*/
$(".pure-menu-item > a").on("click", function() {
    if ($("#menuLink").is(":visible")) {
        $("#menuLink").trigger("click");
    }
});

/*
    ------------------------------------------------------------------------
        MESSAGE OF THE DAY
    ------------------------------------------------------------------------
*/

/*
    Check if the Message of the Day popup should be displayed.
*/
switch (motdDisplay) {
    case "on-change":
        /*
            Display only if the message of the day has changed.
        */
        if (settings.get("motdCurrentHash") == motdHash) break;
    case "always":
        /*
            Display unless the user has dismissed the MotD. The dismissal is
            revoked whenever the MotD changes.
        */
        if (settings.get("motdDismissedHash") == motdHash) break;
    case "forced":
        /*
            Display the MotD.
        */
        $("#motd-overlay").fadeIn(150);
}

/*
    Check if the user has dismissed the current MotD. If so, the "do not show
    again" checkbox should be pre-checked if the user opens the MotD dialog
    manually.
*/
if (settings.get("motdDismissedHash") == motdHash) {
    $("#motd-hide").prop("checked", true);
}

/*
    The "Close" button on the Message of the Day dialog box.
*/
$("#motd-close").on("click", function() {
    /*
        Save the hash of the current MotD. This is used to check if the user has
        dismissed the current MotD at least once if the "on-change" display mode
        is used.
    */
    settings.motdCurrentHash = motdHash;
    /*
        Check if the user has indicated they wish to hide the MotD by default
        until the MotD next changes. Change detection is done using a hash of
        the MotD content.
    */
    if ($("#motd-hide").length > 0 && $("#motd-hide").is(":checked")) {
        /*
            Save the hash of the current MotD. The next time the user loads the
            page, if the hash matches the stored value, the MotD will not be
            displayed.
        */
        settings.motdDismissedHash = motdHash;
    } else {
        /*
            If the user has not checked the checkbox to hide the MotD on
            subsequent visits, the hash should be reset to a blank value to
            ensure that the MotD displays next time.
        */
        settings.motdDismissedHash = "";
    }
    saveSettings();
    $("#motd-overlay").fadeOut(150);
});

/*
    The "Show MotD" link in the sidebar.
*/
$("#motd-open").on("click", function(e) {
    e.preventDefault();
    $("#motd-overlay").fadeIn(150);
});
