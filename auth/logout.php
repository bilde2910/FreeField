<?php
/*
    This script is the logout script. Browsing to this page will unset the
    session cookie and redirect the user to the main page.
*/

require_once("../includes/lib/global.php");
__require("config");
__require("security");

header("HTTP/1.1 303 See Other");
if (Security::validateCSRF()) {
    setcookie("session", "", time() - 3600, "/");
}
header("Location: ".Config::getEndpointUri("/"));
exit;

?>
