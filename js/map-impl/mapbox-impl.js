/*
    This script handles FreeField's implementation of Mapbox.
*/

/*
    The registered callback if the user clicks the map. Used by
    `MapImpl.bindMapCLickHandler`.
*/
var mapboxClickCallback = null;
/*
    The `mapboxgl.Map` instance.
*/
var mapboxMap = null;

/*
    `MapImpl` is a proxy class that handles communication between Mapbox and the
    FreeField frontend.
*/
var MapImpl = {
    /*
        `preInit` is called from /index.php. A data object is passed that
        contains the access token for Mapbox.
    */
    preInit: function(initData) {
        mapboxgl.accessToken = initData.apiKey;
    },

    /*
        `init` is later called from /js/main.js. It is called when the Mapbox
        elements should be constructed on the page, and when the map should be
        loaded and displayed.
    */
    init: function(containerId, config) {
        /*
            Configure MapBox.
        */
        mapboxMap = new mapboxgl.Map({
            container: containerId,
            style: 'mapbox://styles/mapbox/' + (config.get("mapStyle/mapbox")) + '-v9',
            center: [config.get("center/longitude"), config.get("center/latitude")],
            zoom: config.get("zoom")
        });

        /*
            Add map controls to the MapBox instance.
        */
        mapboxMap.addControl(new mapboxgl.NavigationControl());
        mapboxMap.addControl(new mapboxgl.GeolocateControl({
            positionOptions: {
                enableHighAccuracy: false,
                timeout: 5000
            },
            trackUserLocation: true
        }));
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
            If there is already a bound event handler, unbind it.
        */
        if (mapboxClickCallback !== null) mapboxMap.off("click", mapboxClickCallback);
        /*
            Create an event handler callback for Mapbox that in turn calls the
            callback provided through the `callback` argument of this function.
        */
        mapboxClickCallback = function(e) {
            callback(e.lngLat.lat, e.lngLat.lng);
        }
        /*
            Bind the proxy callback function to the click event of the Mapbox
            `Map` instance.
        */
        mapboxMap.on("click", mapboxClickCallback);
    },

    /*
        This function signals that the map click callback is no longer needed.
        This is called when the user cancels adding a new POI, or when they have
        clicked on the map and selected a location.
    */
    unbindMapClickHandler: function() {
        mapboxMap.off("click", mapboxClickCallback);
        mapboxClickCallback = null;
    },

    /*
        This function is called to add a marker to the map. The function takes
        five arguments - an HTML node representing the marker on the map, two
        position arguments indicating the location that the marker is to be
        added at on the map, as well as two callback functions - one that is
        triggered when the marker node on the map is clicked, i.e. opened, and
        one that is called when the marker is closed.
    */
    addMarker: function(markerNode, lat, lon, openCallback, closeCallback) {
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
            openCallback(popup);
        });
        popup.on("close", function() {
            closeCallback(popup);
        });

        /*
            Declare and add the Mapbox marker to the map.
        */
        new mapboxgl.Marker(markerNode)
            .setLngLat([lon, lat])
            .setPopup(popup)
            .addTo(mapboxMap);
    },

    /*
        This function is called to close the given marker on the Mapbox map. The
        `markerObj` is the Mapbox popup instance passed to the `openCallback`
        function callback in the `addMarker` function.
    */
    closeMarker: function(markerObj) {
        markerObj._onClickClose();
    },

    /*
        Returns the center of the current map view on Mapbox.
    */
    getCenter: function() {
        var center = mapboxMap.getCenter();
        return {
            lat: center.lat,
            lon: center.lng
        };
    },

    /*
        Returns the current zoom level of the Mapbox map.
    */
    getZoomLevel: function() {
        return mapboxMap.getZoom();
    }
}
