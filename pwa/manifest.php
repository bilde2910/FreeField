<?php
/*
    This file outputs FreeField's PWA manifest. For specification details, see
    https://developer.mozilla.org/en-US/docs/Web/Manifest and
    https://developers.google.com/web/fundamentals/web-app-manifest/.
*/

require_once("../includes/lib/global.php");
__require("config");

/*
    Ensure PWA is enabled.
*/
if (!Config::get("mobile/pwa/enabled")->value()) {
    header("HTTP/1.1 501 Not Implemented");
    exit;
}

/*
    Create the manifest object. Please see the links at the top of this page
    regarding the structure of the manifest.
*/
$output = array(
    "name" => Config::get("mobile/pwa/name")->value(),
    "short_name" => Config::get("mobile/pwa/short-name")->value(),
    "description" => Config::get("mobile/pwa/description")->value(),
    "display" => Config::get("mobile/pwa/display")->value(),
    "theme_color" => Config::get("themes/meta/color")->value(),
    "background_color" => Config::get("mobile/pwa/color/background")->value(),
    "scope" => parse_url(Config::getEndpointUri("/"), PHP_URL_PATH),
    "start_url" => parse_url(Config::getEndpointUri("/pwa/launch.php"), PHP_URL_PATH),
    "icons" => array(
        array(
            "src" => parse_url(Config::getEndpointUri("/pwa/icon.php"), PHP_URL_PATH)."?size=192",
            "type" => Config::get("mobile/pwa/icon/192px")->value()->getMIMEType(),
            "sizes" => "192x192"
        ),
        array(
            "src" => parse_url(Config::getEndpointUri("/pwa/icon.php"), PHP_URL_PATH)."?size=512",
            "type" => Config::get("mobile/pwa/icon/512px")->value()->getMIMEType(),
            "sizes" => "512x512"
        )
    )
);

/*
    iOS Safari's PWA implementation breaks the OAuth authentication flow. When
    a user tries to authenticate, they are redirected off the browser context of
    the PWA since the authentication domain is out-of-scope. However, unlike
    most Android implementations, the user is not redirected back to the PWA
    browser context when the user returns into scope after completing
    authentication. This means that users aren't signed in in the PWA instance,
    but in Safari instead. There is no known stable workaround for this issue,
    so it is fixed in FreeField by forcing browser display mode for PWA on iOS
    clients. This causes the PWA to open in Safari, where users are able to
    follow the OAuth authentication flow properly.
*/
if (isset($_SERVER["HTTP_USER_AGENT"])) {
    if (
        stristr($_SERVER["HTTP_USER_AGENT"], "iPhone") !== FALSE ||
        stristr($_SERVER["HTTP_USER_AGENT"], "iPad") !== FALSE ||
        stristr($_SERVER["HTTP_USER_AGENT"], "iPod") !== FALSE
    ) {
        $output["display"] = "browser";
    }
}

/*
    Output the manifest file.
*/
$output = json_encode($output, JSON_PRETTY_PRINT);
header("Content-Type: application/manifest+json");
header("Content-Length: " . strlen($output));
echo $output;
?>
