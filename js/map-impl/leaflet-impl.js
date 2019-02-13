/*
    This script handles FreeField's implementation of Leaflet maps.
*/

/*
    The registered callback if the user clicks the map. Used by
    `MapImpl.bindMapClickHandler`.
*/
var leafletClickCallback = null;
/*
    The `L.map` instance.
*/
var leafletMap = null;
var leafletInitObject = null;

/*
    `MapImpl` is a proxy class that handles communication between Leaflet and
    the FreeField frontend.
*/
var MapImpl = {
    /*
        `preInit` is called from /index.php. A data object is passed that
        contains initialization parameters.
    */
    preInit: function(initData) {
        leafletInitObject = initData;
    },

    /*
        `init` is later called from /js/main.js. It is called when the Leaflet
        elements should be constructed on the page, and when the map should be
        loaded and displayed. `boundsChangeHandler` is a function that should be
        called whenever the map is zoomed or panned.
    */
    init: function(containerId, config, boundsChangeHandler) {
        /*
            Configure Leaflet.
        */
        leafletMap = L.map(containerId, {
            center: [config.get("center/latitude"), config.get("center/longitude")],
            zoom: config.get("zoom"),
            zoomControl: false // Added below to force top-right positioning
        });
        leafletMap.on("click", function(e) {
            if (leafletClickCallback != null) {
                leafletClickCallback(e);
            }
        });

        /*
            Set the theme, if available.
        */
        if (leafletInitObject.hasOwnProperty("theme")) {
            leafletInitObject.params["providerTheme"] = settings.get(leafletInitObject.theme);
        }

        /*
            Define the map layer parameters.
        */
        var layer = new L.TileLayer(leafletInitObject.url, leafletInitObject.params);
        leafletMap.addLayer(layer);

        /*
            Add controls for zooming and geolocation on the map.
        */
        L.control.zoom({
            position: "topright"
        }).addTo(leafletMap);

        L.control.locate({
            position: "topright",
            locateOptions: {
                maxZoom: 18
            }
        }).addTo(leafletMap);

        /*
            Add handlers for when the bounds of the map are changed.
        */
        leafletMap.on("moveend", function() {
            boundsChangeHandler();
        });
    },

    /*
        When the user clicks on the "Add POI" button, this function is called to
        bind an event handler that triggers on map click. The event handler
        should call a callback passed to this script. The provided callback
        accepts the arguments `lat` and `lon`, corresponding to the coordinates
        at which the map was clicked. It is used to determine where to add the
        POI.
    */
    bindMapClickHandler: function(callback) {
        /*
            Create an event handler callback for Leaflet that in turn calls the
            callback provided through the `callback` argument of this function.
        */
        leafletClickCallback = function(e) {
            callback(e.latlng.lat, e.latlng.lng);
        }
    },

    /*
        This function signals that the map click callback is no longer needed.
        This is called when the user cancels adding a new POI, or when they have
        clicked on the map and selected a location.
    */
    unbindMapClickHandler: function() {
        leafletClickCallback = null;
    },

    /*
        This function is called to add a marker to the map. The function takes
        six arguments - an HTML node representing the marker on the map, two
        position arguments indicating the location that the marker is to be
        added at on the map, as well as two callback functions - one that is
        triggered when the marker node on the map is clicked, i.e. opened, and
        one that is called when the marker is closed - plus the ID of the icon
        displayed for the marker, in the form of an objective or reward ID.
    */
    addMarker: function(markerNode, lat, lon, openCallback, closeCallback, iconID) {
        /*
            Declare and add the Leaflet marker to the map.
        */
        var divIcon = L.divIcon({
            html: markerNode.outerHTML,
            iconAnchor: [25, 25]
        });
        var marker = L.marker([lat, lon], {icon: divIcon}).on("click", function() {
            openCallback(closeCallback);
        }).addTo(leafletMap);
        /*
            Set `data-ff-icon`. This is used to layer the icons properly in CSS.
            Search for this attribute in /css/main.css for implementation.
        */
        marker._icon.setAttribute("data-ff-icon", iconID);
        return marker;
    },

    /*
        This function is called to move a marker to another place on the map.
        The function takes three arguments - a target latitude and longitude, as
        well as a reference to the marker object created and returned by
        `addMarker()`.
    */
    moveMarker: function(implObject, lat, lon) {
        implObject.setLatLng(new L.LatLng(lat, lon));
    },

    /*
        This function is called when the icon of a marker is updated. The
        function takes three arguments - a reference to the marker object
        created and returned by `addMarker()`, and the old and new icons
        assigned to the marker, in the form of objective or reward IDs.
    */
    updateMarker: function(implObject, oldIcon, newIcon) {
        /*
            Update `data-ff-icon`. This is used to layer the icons properly in
            CSS. Search for this attribute in /css/main.css for implementation.
        */
        implObject._icon.setAttribute("data-ff-icon", newIcon);
    },

    /*
        This function is called to remove a marker from the map. The function
        takes one argument - a reference to the marker object created and
        returned by `addMarker()`.
    */
    removeMarker: function(implObject) {
        leafletMap.removeLayer(implObject);
    },

    /*
        This function is called to close the given marker on the Leaflet map.
        The `closeCallback` is the closing callback passed to the
        `closeCallback` function callback in the `addMarker` function.
    */
    closeMarker: function(closeCallback) {
        closeCallback(null);
    },

    /*
        This function is called on page load to manually open the given marker
        on the Leaflet map. The `poiObj` is the POI object as stored in the
        `pois` array.
    */
    simulatePOIClick: function(poiObj) {
        $("#" + poiObj.elementId).trigger("click");
    },

    /*
        Pans the map to the given coordinates.
    */
    panTo: function(lat, lon) {
        leafletMap.flyTo([lat, lon]);
    },

    /*
        Returns the center of the current map view on Leaflet.
    */
    getCenter: function() {
        var center = leafletMap.getCenter();
        return {
            lat: center.lat,
            lon: center.lng
        };
    },

    /*
        Returns the current zoom level of the Leaflet map.
    */
    getZoomLevel: function() {
        return leafletMap.getZoom();
    },

    /*
        Returns the currently visible boundary of the map.
    */
    getBounds: function() {
        var bounds = leafletMap.getBounds();
        return {
            north: bounds.getNorth(),
            south: bounds.getSouth(),
            east: bounds.getEast(),
            west: bounds.getWest()
        };
    }
}
