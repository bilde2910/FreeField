<?php
/*
    This file acts as a proxy for uploaded files. It also checks the permissions
    of the settings to verify that the current user can actually access the
    given file.
*/

require_once("../includes/lib/global.php");
__require("config");

/*
    If no path is specified, the request is invalid.
*/
if (!isset($_GET["path"])) {
    $status = "403 Bad Request";
    header("HTTP/1.1 {$status}");
    echo "<h1>{$status}</h1>";
    exit;
}

$path = $_GET["path"];
$entry = Config::get($path);

/*
    If the user is not explicitly allowed to access the setting, deny them
    access. This will be done both when the user does not have permission to
    view the setting, and when the requested setting doesn't exist in the first
    place. Also ensure that the setting actually exists.
*/
if ($entry === null || $entry->hasPermission() !== true) {
    $status = "403 Forbidden";
    header("HTTP/1.1 {$status}");
    echo "<h1>{$status}</h1>";
    exit;
}

/*
    If the requested setting isn't a type of `FileOption`, also deny the user
    access since the given setting doesn't actually represent a file. Throw a
    HTTP 403 here as well for intentional vagueness about the presence of the
    setting and its type.
*/
$opt = $entry->getOption();
if (!($opt instanceof FileOption)) {
    $status = "403 Forbidden";
    header("HTTP/1.1 {$status}");
    echo "<h1>{$status}</h1>";
    exit;
}

/*
    From here on, we know that the setting exists, represents a file, and that
    the user has access to view it. Output the file to the browser.
*/
$opt->applyToCurrent()->outputWithCaching();
exit;

?>
