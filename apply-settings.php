<?php
/*
    This file handles changes of user settings that require server-side
    processing to apply (such as changing nicknames).
*/

require_once("./includes/lib/global.php");
__require("config");
__require("auth");
__require("db");

$user = Auth::getCurrentUser();
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
    If nobody is logged in, there are no server-side settings to apply.
*/
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
