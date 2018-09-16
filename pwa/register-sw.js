/*
    PWA service worker registration script. This script registers a service
    worker that lets users add the FreeField site to their device's home screens
    as a bookmark. It will by default appear as if it was a standalone app,
    giving more screen real estate to the map itself by hiding the address bar.
    Sign-ins in browser on mobile will persist across to the PWA and vice versa.
*/

if ("serviceWorker" in navigator) {
    window.addEventListener("load", function() {
        navigator.serviceWorker.register("./service-worker.js").then(function(registration) {
            console.log("ServiceWorker registered with scope: " + registration.scope);
        }, function(err) {
            console.log("Failed to register ServiceWorker: ", err);
        });
    });
}
