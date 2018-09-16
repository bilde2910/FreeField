<?php
/*
    This file serves as an online check for FreeField PWA. When clients connect
    using PWA, they are launched onto a "Connecting" screen that makes a call to
    this file to check if the user's device is online. If this file cannot be
    reached, or the response received from the path this file does not consist
    of a JSON object with an "online" key set to true, the device is not
    considered to be online.

    This script is exclusively called from /pwa/launch.php.

    Use caching prevention techniques to ensure PWA does not cache this page.
*/

header("Expires: ".date("r", 0));
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Content-Type: application/json");

?>
{
    "online": true
}
