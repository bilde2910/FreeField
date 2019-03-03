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
    $("#poi-working-text").text(resolveI18N("poi.add.processing"));
    $("#poi-working-spinner").fadeIn(150);
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
                $("#poi-working-spinner").fadeOut(150);

                /*
                    Update the marker clustering prioritization list.
                */
                haversineList = getPOIHaversineDistances(getIDsOfAllPOIs());
                updateVisiblePOIs();
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
        $("#poi-working-spinner").fadeOut(150);
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
    var set = settings.get("iconSet");
    var url = iconSets[set][icon].split("{%variant%}").join(variant);
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
    var set = isc_opts.species.themedata[settings.get("speciesSet")]["data"];
    var fetchPath = isc_opts.species.themedata[settings.get("speciesSet")]["path"];

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
            + fetchPath
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
    This function updates the "last update" paragraph on the POI details panel.
    It takes an object as argument that contains elements `on` with a timestamp,
    as well as an optional `by` object with the `nick` and `color` of the last
    updating user.
*/
function setLastUpdate(updated) {
    /*
        Update the "last updated time" display label.
    */
    $("#poi-last-time").text(resolveI18N(
        "poi.last.time",
        new Date(updated.on * 1000).toLocaleDateString(currentLanguage, {
            year: "numeric",
            month: "short",
            day: "numeric",
            hour: "numeric",
            minute: "numeric",
            second: "numeric"
        })
    ));
    /*
        Check if the current user has permission to view who last updated the
        research task. If so, show the nickname and color of the user in
        question; otherwise, hide it.
    */
    if (updated.hasOwnProperty("by")) {
        $("#poi-last-user-text").html(
            encodeHTML(resolveI18N("poi.last.user")).split("{%1}").join(
                $("<span>")
                    .css({
                        "font-weight": "bold",
                        "color": updated.by.color
                    })
                    .text(updated.by.nick)
                    .prop("outerHTML")
            )
        );
        $("#poi-last-user-box").show();
    } else {
        $("#poi-last-user-box").hide();
    }
}

/*
    Adds a set of marker icons to the map. The `markers` parameter is an array
    of objects, where each object describes the properties of one POI.
    format of the array is the same as the format output in JSON format by GET
    /api/poi.php.
*/
function addMarkers(markers) {
    /*
        Add some basic display properties to the marker, then add it to the
        global `pois` array for easy properties lookup from elsewhere in the
        script.
    */
    markers.forEach(function(marker) {
        marker["elementId"] = "dynamic-marker-" + marker.id;
        marker["visible"] = false;
        pois[marker.id] = marker;
    });

    /*
        Check if the POI is in bounds of the current map area. If so, add it to
        the queue for element creation and rendering on the map.
    */
    var inBounds = [];
    markers.forEach(function(marker) {
        if (shouldBeVisibleOnMap(marker)) inBounds.push(marker.id);
    });

    /*
        Check that the number of POIs does not exceed the amount allowed on the
        map at the same time. If it does, create a prioritized list of POIs to
        add and drop the rest from being displayed for now.
    */
    var visibleLimit = parseInt(settings.get("clusteringLimit"));
    var visibleIDs;
    updateHiddenPOIsBanner(inBounds.length - visibleLimit, inBounds.length);
    if (inBounds.length > visibleLimit) {
        visibleIDs = prioritizePOIsForClustering(inBounds, visibleLimit);
    } else {
        visibleIDs = inBounds;
    }
    visibleIDs.sort(function(a, b) {
        return a - b;
    });

    /*
        Loop over the IDs of all POIs that should be displayed on the map, and
        set those POIs visible (rendering queue):
    */
    visibleIDs.forEach(function(id) {
        /*
            Create a marker element. This is the element that is displayed on
            the map itself and is rendered with the relevant icon to indicate
            the currently active field research on the POI.
        */
        var e = document.createElement("div");
        e.id = "dynamic-marker-" + id;
        e.className =
            // Basic map marker class
            "marker "

            // Render the icon for the current research active on the POI
            + pois[id][settings.get("markerComponent")].type + " "

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
            pois[id].reward.type == "encounter" &&
            pois[id].reward.params.hasOwnProperty("species") &&
            pois[id].reward.params.species.length == 1
        ) {
            e.className += " sp-" + pois[id].reward.params.species[0];
        }
        pois[id]["elementId"] = e.id;

        /*
            Add the marker to the map itself. Get a reference to the marker
            object itself, in case it has to be updated later.
        */
        var implMarkerObj = MapImpl.addMarker(e, pois[id].latitude, pois[id].longitude,
            function(markerObj) {
                openMarker(markerObj, id);
            }, function(markerObj) {
                closeMarker(markerObj);
            },
            pois[id][settings.get("markerComponent")].type
        );
        pois[id].visible = true;
        pois[id]["implObject"] = implMarkerObj;
    });
}

/*
    Connects to /api/poi.php to retrieve an updated list of all map markers.
    This function is called periodically to ensure that the markers displayed on
    the map are up to date.
*/
var lastRefresh = 0;
function refreshMarkers() {
    var curTime = Math.floor(Date.now() / 1000);
    var url = "./api/poi.php?updatedSince=" + (lastRefresh - curTime);
    lastRefresh = curTime;
    $.getJSON(url, function(data) {
        var markers = data["pois"];
        var ids = data["idList"];

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
                setLastUpdate(marker.updated);
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
        var removed = false;
        for (var i = 0; i < pois.length; i++) {
            if (pois[i] != null) {
                var exists = false;
                for (var j = 0; j < ids.length; j++) {
                    if (pois[i].id == ids[j]) {
                        exists = true;
                        break;
                    }
                }
                if (!exists) {
                    MapImpl.removeMarker(pois[i].implObject);
                    pois[i] = null;
                    removed = true;
                }
            }
        }

        /*
            Update the marker clustering prioritization list.
        */
        if (markers.length > 0 || removed) {
            haversineList = getPOIHaversineDistances(getIDsOfAllPOIs());
            updateVisiblePOIs();
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
    lastRefresh = Math.floor(Date.now() / 1000);
    $.getJSON("./api/poi.php", function(data) {
        addMarkers(data["pois"]);
        /*
            Handle deep-linking via URL hashes.
        */
        handleDeepLinking();
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
});

function updateHiddenPOIsBanner(hidden, total) {
    if (hidden > 0) {
        $("#clustering-active-count").text(hidden);
        $("#clustering-active-total").text(total);
        $("#clustering-active-banner").show();
    } else {
        $("#clustering-active-banner").hide();
    }
}

/*
    Checks whether or not the given POI is within the visible area of the map
    and has a research task that matches filters, if any.
*/
function shouldBeVisibleOnMap(poi) {
    /*
        Check whether the POI has research that matches filters.
    */
    if (filterMode == "unknown") {
        if (poi.objective.type != "unknown" || poi.reward.type != "unknown") {
            return false;
        }
    } else if (filterObjective != "any" || filterReward != "any") {
        var shouldMatch = filterMode == "only";
        if (filterObjective != "any") {
            if ((poi.objective.type == filterObjective) != shouldMatch) return false;
        }
        if (filterReward != "any") {
            if ((poi.reward.type == filterReward) != shouldMatch) return false;
        }
    }
    /*
        Get the bounding coordinates of the currently displayed portion of the
        map.
    */
    var bounds = MapImpl.getBounds();
    /*
        Calculate the degree density (degrees per pixel) for latitude and
        longitude, and multiply this by half the size of a marker (50px) to push
        the rendering boundary 25 pixels off all edges of the map. This ensures
        that markers which are visible, but whose center point is out of bounds,
        still display the portion that are within bounds of the map.
    */
    var latOffset = (bounds.north - bounds.south) / $("#map").height() * 25;
    var lonOffset = (bounds.east - bounds.west) / $("#map").width() * 25;
    /*
        Add the offsets to the boundaries.
    */
    bounds.north += latOffset;
    bounds.south -= latOffset;
    bounds.east += lonOffset;
    bounds.west -= lonOffset;
    /*
        Return whether or not the POI is within bounds of the map and should be
        displayed.
    */
    return (
        poi.latitude > bounds.south &&
        poi.latitude < bounds.north &&
        poi.longitude > bounds.west &&
        poi.longitude < bounds.east
    );
}

/*
    Returns an array of all POIs' IDs. The IDs correspond to their position in
    the `pois` array.
*/
function getIDsOfAllPOIs() {
    var ids = [];
    for (var i = 0; i < pois.length; i++) {
        /*
            Ignore POIs in the array that do not exist.
        */
        if (typeof pois[i] == 'undefined' || pois[i] == null) continue;
        ids.push(i);
    }
    return ids;
}

/*
    In order to improve the performance of the map, particularly on low-power
    mobile devices, clustering has been implemented that prevents an excessive
    number of POIs from being displayed on the map at the same time. In order to
    determine which POIs to display, a prioritization algorithm must be
    implemented that selects and drops POIs from the map, taking into account
    the proximity of all POIs to each other, as well as whether or not there is
    research of interest active on the POI.

    This function takes a list of POI ID candidates for display, as well as a
    limit to how many should actually be shown on the map. `haversineList`
    contains a cached list of POI ID/weight value pairs to save processing
    power for repeated uses of the list. This list is populated on first run of
    the function below, and regenerated periodically when POIs are updated on
    the map.
*/
var haversineList = [];
function prioritizePOIsForClustering(idList, limit) {
    /*
        If the cache is empty, populate it.
    */
    if (!haversineList.length) haversineList = getPOIHaversineDistances(getIDsOfAllPOIs());
    /*
        Create an array of IDs that should be returned for display. Limit this
        array to `limit` items, and ensure that each POI that is added is part
        of the requested `idList` of candidates.
    */
    var returnedIDs = [];
    var returnCount = 0;
    for (var i = 0; returnCount < limit && i < haversineList.length; i++) {
        for (var j = 0; j < idList.length; j++) {
            if (haversineList[i][0] == idList[j]) {
                returnedIDs.push(haversineList[i][0]);
                returnCount++;
            }
        }
    }
    return returnedIDs;
}

/*
    This function generates the POI weights list based on all POIs available to
    add to the map. It focuses particuarly on including POIs with active
    research on them, giving them a higher score to ensure most of them are
    displayed on the map even though they may be close to each other.
*/
function getPOIHaversineDistances(idList) {
    /*
        Weight multiplier to use for POIs with active research.
    */
    var RESEARCH_WEIGHT_MULTIPLIER = 5;
    /*
        Placeholder weight, set to an unrealistic weight value to ensure it does
        not collide with any actual weight values.
    */
    var DEFAULT_WEIGHT = 10;
    /*
        The prioritized list of POI IDs.
    */
    var priorityList = [];
    /*
        A list of POIs for separate processing.
    */
    var reportedList = [];

    for (var i = 0; i < idList.length; i++) {
        /*
            In the first run, we will only calculate the weights of POIs which
            do not have active research on them. POIs with research are pushed
            into a queue for separate weight calculation.
        */
        if (pois[idList[i]].objective.type != "unknown") {
            reportedList.push(i);
            continue;
        }
        /*
            Calculate the Haversine distances between this POI and all other
            POIs before it in the list, and set the weight of the POI in the
            priority list to the lowest of these distances.
        */
        var distance, weight = DEFAULT_WEIGHT;
        for (var j = 0; j < i; j++) {
            distance = distanceHaversine(pois[idList[i]], pois[idList[j]]);
            if (distance < weight) weight = distance;
        }
        /*
            In the event that this POI was the first in the list, there will not
            be a weight set for the POI above. Set it to a reasonable default.
        */
        if (weight == DEFAULT_WEIGHT) weight = 0.00001;
        /*
            Push the calculated weight onto the priority list.
        */
        priorityList.push([idList[i], weight]);
    }
    /*
        After the list of POIs with no active research has been processed,
        perform processing for the remaining POIs with highly beneficial
        weights. This ensures that POIs with research active on them are always
        displayed even though it would otherwise have been hidden due to
        proximity to other POIs.
    */
    for (var i = 0; i < reportedList.length; i++) {
        var distance, weight = DEFAULT_WEIGHT;
        for (var j = 0; j < i; j++) {
            distance = distanceHaversine(pois[idList[reportedList[i]]], pois[idList[reportedList[j]]]);
            if (distance < weight) weight = distance;
        }
        if (weight == DEFAULT_WEIGHT) weight = 0.0001;
        /*
            Multiply the weight with a multiplier to allow much tighter
            clustering than POIs with unknown research.
        */
        weight *= RESEARCH_WEIGHT_MULTIPLIER;
        priorityList.push([idList[reportedList[i]], weight]);
    }
    /*
        Sort the weighted list in order of decreasing weight. POIs at the end of
        this array will be sliced off when the number of displayed POIs must be
        limited.
    */
    priorityList.sort(function(a, b) {
        return a[1] < b[1] ? 1 : (a[1] > b[1] ? -1 : 0);
    });
    return priorityList;
}

/*
    This function calculates the distance between two POIs using the Haversine
    formula.
*/
function distanceHaversine(poi1, poi2) {
    /*
        Degrees to radians; π/180
    */
    var d2r = 0.017453292519943295;
    /*
        Convert latitudes and longitudes to radian form.
    */
    var p1lat = poi1.latitude * d2r, p2lat = poi2.latitude * d2r;
    var p1lon = poi1.longitude * d2r, p2lon = poi2.longitude * d2r;
    /*
        Calculate hav(lat2-lat1) and hav(lon2-lon1).
    */
    var havLat = Math.sin((p2lat - p1lat) / 2);
    havLat *= havLat;
    var havLon = Math.sin((p2lon - p1lon) / 2);
    havLon *= havLon;
    /*
        Calculate Haversine distance `d`.
    */
    var hav = havLat + Math.cos(p1lat) * Math.cos(p2lat) * havLon;
    var d = Math.asin(Math.sqrt(hav));
    return d;
}

/*
    This function calculates the bearing from one location/POI to another in
    degrees.
*/
function getBearingDegrees(from, to) {
    /*
        Degrees to radians; π/180
    */
    var d2r = 0.017453292519943295;
    /*
        Convert latitudes and longitudes to radian form.
    */
    var fromLat = from.latitude * d2r, toLat = to.latitude * d2r;
    var fromLon = from.longitude * d2r, toLon = to.longitude * d2r;
    /*
        Calculation code by krishnar from
        https://stackoverflow.com/a/52079217
    */
    var x = Math.cos(fromLat) * Math.sin(toLat)
          - Math.sin(fromLat) * Math.cos(toLat) * Math.cos(toLon - fromLon);
    var y = Math.sin(toLon - fromLon) * Math.cos(toLat);
    var heading = Math.atan2(y, x) / d2r;
    /*
        Normalize degrees result to a positive number.
    */
    return (heading + 360) % 360;
}

/*
    Handle deep-linking via URL hashes.
*/
$(window).on("hashchange", function() {
    handleDeepLinking();
});

function handleDeepLinking() {
    if (location.hash == "#/") return;
    var hashFound = false;
    if (location.hash.length >= 2) {
        if (location.hash.startsWith("#/poi/")) {
            /*
                Ignore this if a POI is already open (otherwise, there will be
                lots of conflicts with duplicate event handlers from open POIs).
            */
            if (currentMarker != -1) return;
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
        } else if (location.hash.startsWith("#/show/poi/")) {
            /*
                The link has a POI ID. Pan the map to its location.
            */
            var poiId = location.hash.substring("#/show/poi/".length);
            if (poiId.indexOf("/") >= 0) {
                poiId = poiId.substring(0, poiId.indexOf("/"));
            }
            /*
                If the POI is found, pan to it, then reset the hash back to
                the main map view ("#/").
            */
            poiId = parseInt(poiId);
            if (!isNaN(poiId) && poiId >= 0 && poiId < pois.length) {
                var poi = pois[poiId];
                if (poi != null && poi.hasOwnProperty("elementId")) {
                    MapImpl.panTo(poi.latitude, poi.longitude);
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
    if (!hashFound) {
        if (history.replaceState) {
            history.replaceState(null, null, "#/");
        } else {
            location.hash = "#/";
        }
    }
}

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
    setLastUpdate(poiObj.updated);

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
                $("#poi-working-text").text(resolveI18N("poi.move.processing"));
                $("#poi-working-spinner").fadeIn(150);
                $.ajax({
                    url: "./api/poi.php",
                    type: "PATCH",
                    dataType: "json",
                    data: JSON.stringify({
                        id: poiObj.id,
                        move_to: {
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
                            $("#poi-working-spinner").fadeOut(150);

                            /*
                                Update the marker clustering prioritization list.
                            */
                            haversineList = getPOIHaversineDistances(getIDsOfAllPOIs());
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
                    $("#poi-working-spinner").fadeOut(150);
                });
            });
        });
        /*
            Event handler for the "Rename POI" button. When clicked, this button
            starts the process for renaming a POI on the map.
        */
        $("#poi-rename").on("click", function() {
            var newName = prompt(resolveI18N("poi.rename.prompt"), poiObj.name);
            if (newName != null && newName.trim() != "") {
                MapImpl.closeMarker(markerObj);
                /*
                    Since we're initiating a request to the server, display a
                    "working" spinner popup to visually invidate to the user
                    that the POI is being renamed.
                */
                $("#poi-working-text").text(resolveI18N("poi.rename.processing"));
                $("#poi-working-spinner").fadeIn(150);
                newName = newName.trim();
                $.ajax({
                    url: "./api/poi.php",
                    type: "PATCH",
                    dataType: "json",
                    data: JSON.stringify({
                        id: poiObj.id,
                        rename_to: newName
                    }),
                    statusCode: {
                        204: function(data) {
                            /*
                                If the request was successful, update the
                                instance of the object in `pois` with the new
                                name.
                            */
                            poiObj.name = newName;

                            /*
                                Let the user know that the POI was successfully
                                moved, and then close the waiting popup.
                            */
                            spawnBanner("success", resolveI18N("poi.rename.success"));
                            $("#poi-working-spinner").fadeOut(150);
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
                    spawnBanner("failed", resolveI18N("poi.rename.failed.message", reason));
                    $("#poi-working-spinner").fadeOut(150);
                });
            };
        });
        /*
            Event handler for the "Clear POI research" button. When clicked,
            this button starts the process for resetting the current research
            task for one POI on the map.
        */
        $("#poi-clear").on("click", function() {
            if (confirm(resolveI18N("poi.clear.confirm"))) {
                MapImpl.closeMarker(markerObj);
                /*
                    Since we're initiating a request to the server, display a
                    "working" spinner popup to visually invidate to the user
                    that the POI is being renamed.
                */
                $("#poi-working-text").text(resolveI18N("poi.clear.processing"));
                $("#poi-working-spinner").fadeIn(150);
                $.ajax({
                    url: "./api/poi.php",
                    type: "PATCH",
                    dataType: "json",
                    data: JSON.stringify({
                        id: poiObj.id,
                        reset_research: true
                    }),
                    statusCode: {
                        204: function(data) {
                            /*
                                If the request was successful, update the
                                display of the marker on the map to show the
                                updated research task. Also update the instance
                                of the object in `pois` with the new research
                                task.
                            */
                            var objective = "unknown";
                            var reward = "unknown";
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
                                params: []
                            };
                            poiObj.reward = {
                                type: reward,
                                params: []
                            };

                            /*
                                Let the user know that the POI was successfully
                                moved, and then close the waiting popup.
                            */
                            spawnBanner("success", resolveI18N("poi.clear.success"));
                            $("#poi-working-spinner").fadeOut(150);
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
                    spawnBanner("failed", resolveI18N("poi.clear.failed.message", reason));
                    $("#poi-working-spinner").fadeOut(150);
                });
            };
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
                $("#poi-working-text").text(resolveI18N("poi.delete.processing"));
                $("#poi-working-spinner").fadeIn(150);
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
                            $("#poi-working-spinner").fadeOut(150);

                            /*
                                Update the marker clustering prioritization list.
                            */
                            haversineList = getPOIHaversineDistances(getIDsOfAllPOIs());
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
                    $("#poi-working-spinner").fadeOut(150);
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
            (`#poi-working-spinner`) should be displayed to the user to let them
            know that the research update call is in progress.
        */
        $("#update-poi-submit").prop("disabled", true);
        $("#poi-working-text").text(resolveI18N("poi.update.processing"));
        $("#poi-working-spinner").fadeIn(150);
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
                    $("#poi-working-spinner").fadeOut(150);

                    /*
                        Update the marker clustering prioritization list.
                    */
                    haversineList = getPOIHaversineDistances(getIDsOfAllPOIs());
                    updateVisiblePOIs();
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
            $("#poi-working-spinner").fadeOut(150);
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
        $("#poi-rename").off();
        $("#poi-clear").off();
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
    This function is a map update handler. It is called whenever the map is
    zoomed or panned and is responsible for updating the list of POIs that are
    currently visible on the map. Not all POIs are displayed on the map at once
    to ensure higher performance on client devices.
*/
function updateVisiblePOIs() {
    var inBounds = [];
    var visibleLimit = parseInt(settings.get("clusteringLimit"));
    var idList = getIDsOfAllPOIs();
    for (var i = 0; i < idList.length; i++) {
        /*
            If the POI should be visible, but isn't currently, flag it as
            visible and add it to the map. Inversely, if the POI is currently
            visible, but shouldn't be, remove it from the map to save resources.
        */
        if (shouldBeVisibleOnMap(pois[idList[i]])) {
            inBounds.push(idList[i]);
        } else if (pois[idList[i]].visible) {
            pois[idList[i]].visible = false;
            MapImpl.removeMarker(pois[idList[i]].implObject);
        }
    }

    /*
        Check that the number of POIs does not exceed the amount allowed on the
        map at the same time. If it does, create a prioritized list of POIs to
        add and drop the rest from being displayed for now.
    */
    var visibleIDs;
    if (inBounds.length > visibleLimit) {
        visibleIDs = prioritizePOIsForClustering(inBounds, visibleLimit);
    } else {
        visibleIDs = inBounds;
    }
    visibleIDs.sort(function(a, b) {
        return a - b;
    });

    /*
        Flag each POI as visible or hidden, and add or remove it from the map
        appropriately.
    */
    for (var i = 0, j = 0; i < inBounds.length; i++) {
        var poiId = inBounds[i];
        if (j < visibleIDs.length && poiId == visibleIDs[j]) {
            j++;
            if (!pois[poiId].visible) {
                pois[poiId].visible = true;
                addMarkers([pois[poiId]]);
            }
        } else if (pois[poiId].visible) {
            pois[poiId].visible = false;
            MapImpl.removeMarker(pois[poiId].implObject);
        }
    }

    updateHiddenPOIsBanner(inBounds.length - visibleLimit, inBounds.length);
}

/*
    ------------------------------------------------------------------------
        POI SEARCH
    ------------------------------------------------------------------------
*/

/*
    Current user position. If set to null, coordinates are displayed in the
    search results, otherwise, direction and distance to each POI are displayed.
*/
var currentPos = null;

/*
    Radius of the earth in kilometers, for distance calculations.
*/
var EARTH_RADIUS = 6371;

/*
    If the user clicks on the "locate me" map control before opening search,
    geolocation does not work properly. To mitigate this, ask for user position
    as soon as the map loads. This causes geolocation from this script to work
    properly even after the "locate me" control has been clicked later.
*/
$(document).ready(function() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function() {});
    };
});

/*
    Event handler for the "Search" setting in the sidebar on the map. When
    clicked, it displays a popup allowing the user to search for POIs in their
    proximity.
*/
$("#menu-open-search").on("click", function() {
    currentPos = null;
    // Pre-populate the search results list.
    $("#search-overlay-input").trigger("input");
    // Attempt to use geolocation to show distances to each POI rather than
    // coordinates.
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(pos) {
            // If position is found, re-populate the search results so that it
            // shows distance instead of coordinates.
            currentPos = pos.coords;
            $("#search-overlay-input").trigger("input");
        });
    }
    $("#search-poi").fadeIn(150);
});

/*
    Event handler for the close button on the POI search dialog. Hides the
    dialog when clicked.
*/
$("#search-poi-close").on("click", function() {
    $("#search-poi").fadeOut(150);
})

/*
    Event handler for text input in the search field on the POI search dialog.
    This changes the list of displayed POIs live as the user types.
*/
$("#search-overlay-input").on("input", function() {
    // Get user search query.
    var query = $(this).val().toLowerCase();
    // Make a list of candidates that match the search query.
    var candidates = [];
    // Get a list of POI IDs to loop over to check for eligibility,
    var poiIDList = getIDsOfAllPOIs();
    // Determine whether geolocation is available.
    var useDistance = currentPos != null;

    for (var i = 0; i < poiIDList.length; i++) {
        // Do case-insensitive substring search for the query on each POI name.
        if (pois[poiIDList[i]].name.toLowerCase().indexOf(query) !== -1) {    // L
            // Create a candidate object with the ID of the POI.
            var cand = {
                id: poiIDList[i]
            };
            // If geolocation is available, calculate and add the distance from
            // the user to the POI in question to the object. This is used for
            // sorting.
            if (useDistance) {
                cand.distance = EARTH_RADIUS * 2 * distanceHaversine(
                    pois[poiIDList[i]],
                    currentPos
                );
            }
            // Add the candidate to the array.
            candidates.push(cand);
        }
    }
    // Sort the candidates list by distance (if available) or names
    // alphanumerically (as fallback).
    candidates.sort(function(a, b) {
        if (useDistance) {
            return a.distance - b.distance;
        } else {
            return pois[a.id].name.localeCompare(pois[b.id].name);
        }
    });
    // Add a search result into each of the search result rows on the dialog.
    $(".search-overlay-result").each(function(idx, e) {
        if (candidates.length > idx) {
            // Bind the POI ID to the row for panning if clicked.
            $(e).attr("data-poi-id", candidates[idx].id);
            // Update the result row with the data (name, distance, etc.) of the
            // POI.
            $(e).find(".search-overlay-name").text(pois[candidates[idx].id].name);
            if (useDistance) {
                // If distance is available, show the distance and bearing from
                // the user to the POI.
                var distanceKm = candidates[idx].distance.toFixed(2);
                var bearing = getBearingDegrees(currentPos, pois[candidates[idx].id]);
                bearing -= 90; // Icon offset (arrow points right)
                $(e).find(".search-overlay-dir").show();
                $(e).find(".search-overlay-dir").css(
                    "transform", "rotate(" + bearing + "deg)"
                );
                $(e).find(".search-overlay-loc").text(
                    resolveI18N("poi.search.distance", distanceKm)
                );
            } else {
                // Otherwise, show coordinate pairs for the POI.
                $(e).find(".search-overlay-dir").hide();
                $(e).find(".search-overlay-loc").text(getLocationString(
                    pois[candidates[idx].id].latitude,
                    pois[candidates[idx].id].longitude
                ));
            }
            $(e).show();
        } else {
            // If there are more result rows than candidates, hide the excess
            // rows to clean up the list.
            $(e).hide();
        }
    });
});

/*
    Event handler for the result rows on the POI search dialog. When clicked,
    these result rows hide the dialog window and pan the map to the location of
    the POI that was clicked.
*/
$(".search-overlay-result").on("click", function() {
    $("#search-poi").fadeOut(150);
    var id = parseInt($(this).attr("data-poi-id"));
    MapImpl.panTo(pois[id].latitude, pois[id].longitude);
});

/*
    Converts a coordinate pair to a coordinate string in DD format. E.g.

        getLocationString(42.63445, -87.12012)
        ->  "42.6345°N, 87.1201°W"

    `precision` is an optional parameter for specifying the desired precision in
    number of decimal digits.
*/
function getLocationString(lat, lon) {
    var precision = 4;

    /*
        `ns` is the I18N token to use for latitude. For positive coordinates,
        this is the I18N token that corresponds to North. For negative ones, it
        is the token that corresponds to South. These tokens are resolved with
        the absolute value of the coordinates to ensure that coordinates are
        displayed as e.g. "87°W" rather than "-87°E".

        The same applies for `ew`, the longitude I18N token.
    */
    var ns = "geo.direction.deg_north";
    var ew = "geo.direction.deg_east";
    if (lat < 0) {
        lat *= -1;
        ns = "geo.direction.deg_south";
    }
    if (lon < 0) {
        lon *= -1;
        ew = "geo.direction.deg_west";
    }

    return resolveI18N(
        "geo.location.string",
        resolveI18N(ns, lat.toFixed(precision)),
        resolveI18N(ew, lon.toFixed(precision))
    );
}

/*
    ------------------------------------------------------------------------
        RESEARCH FILTERS
    ------------------------------------------------------------------------
*/

/*
    Default filter settings (show all POIs).
*/
var filterMode = "only";
var filterObjective = "any";
var filterReward = "any";

/*
    Event handler for the sidebar button responsible for opening the filtering
    menu. Displays a popup that allows the user to filter research tasks that
    are of interest to them.
*/
$("#menu-open-filters, #corner-filter-link").on("click", function(e) {
    e.preventDefault();

    /*
        Set the input options in the dialog to the current filter options.
    */
    $("#filter-poi-mode").val(filterMode);
    $("#filter-poi-objective").val(filterObjective);
    $("#filter-poi-reward").val(filterReward);

    /*
        Hides the map and map-specific sidebar items, and shows the filtering
        popup.
    */
    $("#filters-poi").fadeIn(150);
});

/*
    Event handler that resets POI filters. This is displayed as the "reset
    filters" button on the POI filtering dialog. It hides the filtering dialog,
    resetting the filters.
*/
$("#filter-poi-reset").on("click", function() {
    setFilters("only", "any", "any");
});

/*
    Event handler that cancels setting POI filters. This is displayed as the
    "cancel" button on the POI filtering dialog. It hides the filtering dialog,
    applying the filters.
*/
$("#filter-poi-submit").on("click", function() {
    setFilters(
        $("#filter-poi-mode").val(),
        $("#filter-poi-objective").val(),
        $("#filter-poi-reward").val()
    );
});

function setFilters(fMode, fObjective, fReward) {
    filterMode = fMode;
    filterObjective = fObjective;
    filterReward = fReward;
    updateVisiblePOIs();
    /*
        If filters are active, show a small icon underneath the menu icon and
        highlight the filters sidebar option to indicate this.
    */
    if (
        filterMode == "unknown" ||
        filterObjective != "any" ||
        filterReward != "any"
    ) {
        $("#corner-filter-link").show();
        $("#menu-open-filters").attr("data-active", "1");
    } else {
        $("#corner-filter-link").hide();
        $("#menu-open-filters").attr("data-active", "0");
    }
    $("#filters-poi").fadeOut(150);
}

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
    /*
        Check if any of the user's icon set preferences are invalid. If so,
        reset those preferences and reload the map. Broken icon set references
        can cause the map to not work correctly.
    */
    var brokenSets = false;
    var iconSet = settings.get("iconSet");
    if (iconSet != "" && !iconSets.hasOwnProperty(iconSet)) {
        settings.iconSet = "";
        brokenSets = true;
    }
    var speciesSet = settings.get("speciesSet");
    if (speciesSet != "" && !isc_opts.species.themedata.hasOwnProperty(speciesSet)) {
        settings.speciesSet = "";
        brokenSets = true;
    }
    if (brokenSets) {
        saveSettings();
        location.reload();
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

$("head").append('<link rel="stylesheet" ' +
                       'type="text/css" ' +
                       'href="./css/theming.php?' + settings.get("theme") + '">');

/*
    Configure the `IconSetOption` selector to use the correct user theme color.
*/
isc_opts.icons.colortheme = settings.get("theme");
isc_opts.species.colortheme = settings.get("theme");

/*
    Initialize the map.
*/
MapImpl.init("map", settings, updateVisiblePOIs);

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
