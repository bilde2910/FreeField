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
    Output the manifest file.
*/
$output = json_encode($output, JSON_PRETTY_PRINT);
header("Content-Type: application/manifest+json");
header("Content-Length: " . strlen($output));
echo $output;
?>
