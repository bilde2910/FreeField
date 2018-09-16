/*
    This is a service worker that handles PWA-related functionality for
    FreeField. It runs in the background on users' devices if PWA is enabled.
    HTTPS is required for service workers to work properly.
*/

/*
    Define a cache name and a list of URLs to cache through the service worker.
*/
var CACHE_NAME = 'ff-cache-sw1';
var urlsToCache = [
    /*
        The launcher script. This script must work offline. Its sole purpose is
        to check for an Internet connection and then redirect to the online map.
        The main app itself cannot be cached offline (it causes problems with
        authentiation and related functions), and the app still requires an
        internet connection to fetch and submit data.
    */
    "./pwa/launch.php"
];

/*
    Install the service worker. This adds the required URLs to the service
    worker cache so the "Connecting" screen works offline.
*/
self.addEventListener('install', function(event) {
    event.waitUntil(
        caches.open(CACHE_NAME).then(function(cache) {
            console.log("Opened cache - adding cache URLs");
            return cache.addAll(urlsToCache);
        })
    );
});

/*
    Called when fetching any resource for the page.
*/
self.addEventListener('fetch', function(event) {
    /*
        The "Connecting" screen (all URLs defined in `urlsToCache`) should be
        intercepted and served locally to ensure that it works offline.
        Specifically do not intercept any other URLs than those defined in
        `urlsToCache`, as that somehow causes the site to break on desktop
        clients.
    */
    var urlFound = false;
    for (var i = 0; i < urlsToCache.length; i++) {
        /*
            `.substring(1)` is called to eliminate the dot at the start of the
            URLs in `urlsToCache`.
        */
        if (event.request.url.endsWith(urlsToCache[i].substring(1)))
            urlFound = true;
    }
    if (urlFound) {
        /*
            Return the cached version of the requested content.
        */
        console.log("Returning cached content for " + event.request.url);
        event.respondWith(
            caches.match(event.request).then(
                function(response) {
                    if (response) {
                        return response;
                    }
                    return fetch(event.request);
                }
            )
        );
    }
});
