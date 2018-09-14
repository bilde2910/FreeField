<?php
/*
    This file handles submission of configuration updates from the
    administration interface.
*/

require_once("../includes/lib/global.php");
__require("config");
__require("auth");
__require("security");

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
    Perform CSRF validation.
*/
if (!Security::validateCSRF()) {
    header("HTTP/1.1 303 See Other");
    header("Location: ./");
    exit;
}

/*
    If the current user does not have permission to view any of the admin pages
    then the user does not have permission to change any of the settings on any
    pages either. Hence, we can kick them out of this script right away since we
    presume they're not admins.
*/
if (!Auth::getCurrentUser()->canAccessAdminPages()) {
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
$settings = array();
foreach ($_POST as $key => $value) {
    if (substr($key, 0, 8) !== "install/") {
        $settings[$key] = $value;
    }
}

/*
    Files should also be processed, though they are in the `$_FILES` array
    instead of `$_POST`. Add the keys for each file submitted to the array of
    settings to set in the configuration file. `FileOption` will fetch the file
    data from `$_FILES` directly. It would be possible for malicious users to
    pass an array to an option directly (due to the way PHP works with GET query
    strings). If the array from `$_FILES` was fetched and returned as the value
    here, there would be no way to differentiate between a legitimate file and
    a maliciously inserted array. Letting `FileOption` handle the `$_FILES`
    array eliminates this issue.
*/
foreach ($_FILES as $key => $value) {
    if (substr($key, 0, 8) !== "install/") {
        $settings[$key] = $key;
    }
}

/*
    Update the configuration itself. This is handled entirely in
    /includes/lib/config.php.
*/
Config::set($settings, true);

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
