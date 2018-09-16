<?php
/*
    This page is the launch screen for FreeField PWA. It consists of a
    "connecting" screen and loading spinner. This page is designed to work
    offline. On load, it checks if the device has an Internet connection. If
    not, the device registers an event handler that is called when the device
    next goes online.
*/

require_once("../includes/lib/global.php");
__require("config");
__require("i18n");

?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(I18N::getLanguage(), ENT_QUOTES); ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, user-scalable=no">
        <meta name="robots" content="noindex,nofollow">
        <title><?php echo I18N::resolveHTML("mobile.pwa.connecting"); ?></title>
        <style>
            /*
                Attempts were made to move this CSS into a separate file, but I
                could not get it work in a stable way with PWA. It would
                sometimes not load. To ensure that the stylesheet for this page
                is always loaded under all circumstances, the defitions are
                included directly in the document rather than in a linked
                stylesheet.
            */

            /*
                Set the color of the page and its text.
            */
            body {
                color: <?php echo Config::get("mobile/pwa/color/foreground")->value(); ?>;
                background-color: <?php echo Config::get("mobile/pwa/color/background")->value(); ?>;
            }
            /*
                The paragraph that contains the "Connecting" string.
            */
            p {
                font-family: sans-serif;
                font-size: 8vw;
                font-weight: 300;
                margin-top: 8vw;
                margin-bottom: 0;
            }
            /*
                The content of the page. This is vertically and horizontally
                centered.
            */
            div.content {
                position: absolute;
                width: 65%;
                top: 50%;
                left: 50%;
                transform: translateX(-50%) translateY(-50%);
                text-align: center;
            }
            /*
                A loading screen logo is displayed in an enclosing loading
                spinner.
            */
            .logo {
                display: inline-block;
                width: 20vw;
                height: 20vw;
                /*
                    Embed the logo image directly in the CSS. This is done for
                    the same reason as the reason to embed the stylesheet
                    itself.
                */
                background-image: url(<?php echo Config::get("mobile/pwa/icon/launch")->value()->getDataURI(); ?>);
                background-repeat: no-repeat;
                background-size: 100%;
                margin-top: 10vw;
            }
            /*
                A container for the logo and the enclosing loading spinner.
            */
            .loader {
                height: 40vw;
            }
            /*
                The box that contains the spinning quarter circles loading icon.
                Height is set to zero to enable the logo to display within the
                loading circle.
            */
            .spinbox {
                width: 40vw;
                height: 0;
                margin: auto;
                text-align: center;
            }
            /*
                The spinning loader itself. Uses the color defined for PWA
                foreground, along with the "Connecting" string. The loader is
                defined as a box with the top and bottom edges colored, but the
                left and right edges transparent. Adding a border radius of 50%
                makes the spinner circular, giving the impression of a circle
                with two spinning quarters.
            */
            .loading {
                width: 38vw;
                height: 38vw;
                border: 1vw solid <?php echo Config::get("mobile/pwa/color/foreground")->value(); ?>;
                border-radius: 50%;
                border-left-color: transparent;
                border-right-color: transparent;
                animation: spin 2000ms infinite linear;
                margin: 0;
            }
            /*
                Animation declaration that lets the loading spinner actually
                spin.
            */
            @keyframes spin {
                100% {
                    transform: rotate(360deg);
                    transform: rotate(360deg);
                }
            }
        </style>
    </head>
    <body>
        <div class="content">
            <div class="loader">
                <div class="spinbox">
                    <div class="loading"></div>
                </div>
                <div class="logo"></div>
            </div>
            <p><?php echo I18N::resolveHTML("mobile.pwa.connecting"); ?></p>
        </div>
        <script>
            /*
                This page uses `navigator.onLine` to check for online status,
                but that property alone is not a reliable indicator of a working
                Internet connection (see the following MDN article:
                https://developer.mozilla.org/en-US/docs/Web/API/NavigatorOnLine/onLine)

                To mitigate this, if `navigator.onLine` reports an Internet
                connection, we verify it by making a request to an online check
                script stored on the FreeField server (/pwa/online-check.php).
                If the request is successful, and the returned response is a
                JSON object with an "online" key set to `true`, then the
                connection to FreeField is working, and the user can be
                redirected to the application itself.
            */
            function tryConnect() {
                console.log("Device reported online - testing XHR");
                var xhr = new XMLHttpRequest();
                xhr.onload = function(resp) {
                    var respJSON = JSON.parse(xhr.responseText);
                    if (respJSON.hasOwnProperty("online") && respJSON.online) {
                        /*
                            A response was received and is OK - redirect the
                            user to the map.
                        */
                        console.log("Device online - redirecting");
                        location.href = "..";
                    } else {
                        /*
                            A response was received, but it was not as expected.
                            Retry in 3 seconds.
                        */
                        console.log("XHR response invalid - retrying");
                        setTimeout(function() {
                            tryConnect();
                        }, 3000);
                    }
                }
                xhr.onerror = function() {
                    /*
                        The request failed. The device is not online, or
                        requests are being intercepted. Retry in 3 seconds.
                    */
                    console.log("XHR failed - retrying");
                    setTimeout(function() {
                        tryConnect();
                    }, 3000);
                }
                xhr.open("GET", "./online-check.php", true);
                xhr.send();
            }

            window.addEventListener("load", function() {
                /*
                    If `navigator.onLine` reports online right away, try
                    connecting immediately. If not, register an event handler
                    that is called when the device detects it is online.
                */
                if (navigator.onLine) {
                    tryConnect();
                } else {
                    console.log("Device offline - waiting");
                    window.addEventListener("online", function() {
                        tryConnect();
                    });
                }
            });
        </script>
    </body>
</html>
