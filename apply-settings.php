<?php
/*
    This file handles changes of user settings that require server-side
    processing to apply (such as changing nicknames).
*/

require_once("./includes/lib/global.php");
__require("config");
__require("auth");
__require("db");
__require("security");

$returnpath = "./";

/*
    As this script is for submission only, only POST is supported. If a user
    tries to GET this page, they should be redirected back to the main page.
*/
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("HTTP/1.1 303 See Other");
    header("Location: {$returnpath}");
    exit;
}

/*
    Perform CSRF validation.
*/
if (!Security::validateCSRF()) {
    header("HTTP/1.1 303 See Other");
    header("Location: {$returnpath}");
    exit;
}

/*
    If nobody is logged in, there are no server-side settings to apply.
*/
$user = Auth::getCurrentUser();
if (!$user->exists()) {
    header("HTTP/1.1 303 See Other");
    header("Location: {$returnpath}");
    exit;
}

/*
    Create an array for updates. Updates here are applied at the end of the
    script.
*/
$updates = array();

/*
    Handle nickname changes.
*/
if (isset($_POST["nickname"]) && $user->hasPermission("self-manage/nickname")) {
    if ($_POST["nickname"] !== $user->getNickname()) {
        $updates["nick"] = $_POST["nickname"];
    }
}

/*
    If the user has requested that they are signed out from all devices, then
    that is the only change we should process (as this is a separate button from
    the standard submit button),
*/
if (isset($_POST["sign-out-everywhere"])) {
    $updates = array(
        "token" => Auth::generateUserToken()
    );
}

/*
    Apply the updates queue to the database.
*/
if (count($updates) > 0) {
    $db = Database::getSparrow();
    $db
        ->from(Database::getTable("user"))
        ->where("id", $user->getUserID())
        ->update($updates)
        ->execute();
}

/*
    Return the user to the map page.
*/
header("HTTP/1.1 303 See Other");
header("Location: {$returnpath}");

?>
