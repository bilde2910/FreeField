<?php
/*
    This file acts as a proxy for the PWA app icons. The icon files are stored
    in the /includes/userdata/files directory as mobile.pwa.icon.<size>px.<ext>.
*/

/*
    The PWA uses 192px and 512px icons. Ensure that these user is requesting one
    of those sizes.
*/
$sizes = array("192", "512");
if (!isset($_GET["size"]) || !in_array($_GET["size"], $sizes)) {
    header("HTTP/1.1 404 Not Found");
    exit;
}

require_once("../includes/lib/global.php");
__require("config");

/*
    Output the icon.
*/
Config::get("mobile/pwa/icon/".$_GET["size"]."px")->value()->outputWithCaching();

exit;

?>
