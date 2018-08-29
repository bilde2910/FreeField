<?php
/*
    This file handles submission of configuration updates from the
    administration interface.
*/

require_once("../includes/lib/global.php");
__require("config");
__require("auth");

/*
    If the current user does not have permission to view any of the admin pages
    (as determined by `admin/<domain>/general`) then the user does not have
    permission to change any of the settings on any pages either. Hence, we can
    kick them out of this script right away since we presume they're not admins.
*/
if (!Auth::getCurrentUser()->hasPermission("admin/?/general")) {
    header("HTTP/1.1 303 See Other");
    header("Location: ./");
    exit;
}

/*
    As this script is for submission only, only POST is supported. If a user
    tries to GET this page, they should be redirected to the configuration UI
    where they can make their desired changes.
*/
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("HTTP/1.1 303 See Other");
    header("Location: ./");
    exit;
}

/*
    Ignore all configuration entries in the `install` section, as these are used
    only for the setup procedure of FreeField. Setting these manually can be
    dangerous, and shouldn't ever happen from the admin pages, so we'll drop
    those changes if present.
*/
foreach ($_POST as $key => $value) {
    if (substr($key, 0, 8) === "install/") {
        unset($_POST[$key]);
    }
}

/*
    Update the configuration itself. This is handled entirely in
    /includes/lib/config.php.
*/
Config::set($_POST, true);

/*
    Users should be redirected to the page they came from when saving settings.
    Check to see if the `d` URL parameter - the parameter that indicates the
    domain of the page that triggered the call to this script - is set, and if
    so, redirect the user back to the page that `d` indicates. If not, redirect
    to the top-most available page as a fallback.
*/
$returnpath = "./";
if (isset($_GET["d"])) {
    $returnpath .= "?d=".urlencode($_GET["d"]);
}
header("HTTP/1.1 303 See Other");
header("Location: {$returnpath}");

?>
