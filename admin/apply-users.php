<?php
/*
    This file handles submission of user changes from the administration
    interface.
*/

require_once("../includes/lib/global.php");
__require("config");
__require("auth");
__require("db");
__require("security");

$returnpath = "./?d=users";

/*
    As this script is for submission only, only POST is supported. If a user
    tries to GET this page, they should be redirected to the configuration UI
    where they can make their desired changes.
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
    This script loops over `$_POST`, so the CSRF field must be unset from
    `$_POST` before processing.
*/
Security::unsetCSRFFields();

/*
    If the requesting user does not have permission to make changes here, they
    should be kicked out.
*/
if (!Auth::getCurrentUser()->hasPermission("admin/users/general")) {
    header("HTTP/1.1 303 See Other");
    header("Location: {$returnpath}");
    exit;
}

/*
    The user list we get from /includes/lib/auth.php contains a list of users in
    an indexed array. We'll convert it to an associative array where the user ID
    is the key, to make it easier to fetch the details for a user given its ID.
    This is because the updates POSTed from the client contains changes where
    the user ID is the identifier for the users whose settings have changed.

    The `Auth::listUsers()` function returns an array of `User` class instances.
    Please refer to /includes/lib/auth.php for the structure of this class.
*/
$userlist = Auth::listUsers();
$users_assoc = array();

foreach ($userlist as $user) {
    $users_assoc[$user->getUserID()] = $user;
}

/*
    Create an array for updates, as well as an array for deletions, to be
    applied in one batch at the end of this script.
*/
$updates = array();
$deletes = array();

foreach ($_POST as $user => $data) {
    /*
        Users cannot make changes to other users at or above their own
        permission level. Enforce this by matching the current user's permission
        level against that of the user they are changing.
    */
    if (!Auth::getCurrentUser()->canChangeAtPermission($users_assoc[$user]->getPermissionLevel())) {
        continue;
    }

    /*
        If user deletion is requested, add them to the deletion queue and do not
        process further changes.
    */
    if (isset($data["action"])) {
        if ($data["action"] === "delete") {
            $deletes[] = $user;
            continue;
        }
        if ($data["action"] === "approve") {
            /*
                If user approval is requested, add them to the approval queue.
                Do not process further changes as changes can only be made to
                users who are already approved.
            */
            $updates[$user]["approved"] = true;
            continue;
        } elseif ($data["action"] === "invalidate") {
            /*
                Reset the session token for the user. This will sign the user
                out of all of their devices.
            */
            $updates[$user]["token"] = Auth::generateUserToken();
        }
    }

    if (!$users_assoc[$user]->isApproved()) continue;

    /*
        Handle changes to the user's parameters, such as their nickname. If
        there are changes, they should be added to the updates queue.
    */
    if (
        isset($data["nick"]) &&
        $users_assoc[$user]->getNickname() !== $data["nick"]
    ) {
        $updates[$user]["nick"] = $data["nick"];
    }

    if (
        isset($data["group"]) &&
        $users_assoc[$user]->getPermissionLevel() !== $data["group"] &&
        Auth::getCurrentUser()->hasPermission("admin/users/groups")
    ) {
        $group = intval($data["group"]);
        /*
            If the group membership has changed, validation should be done to
            ensure that the target group permission level is not at or above the
            current level of the user making the changes. This is to stop
            privilege escalation attacks. If the user has permission to make the
            change, add the change to the list of changes.
        */
        if (Auth::getCurrentUser()->canChangeAtPermission($group)) {
            $updates[$user]["permission"] = $group;
        }
    }
}

/*
    Apply the updates queue to the database, and then process deletions.
*/
$db = Database::getSparrow();
foreach ($updates as $userid => $update) {
    $userdata = $db
        ->from(Database::getTable("user"))
        ->where("id", $userid)
        ->update($update)
        ->execute();
}
foreach ($deletes as $userid) {
    $db = Database::getSparrow();
    $db
        ->from(Database::getTable("user"))
        ->where("id", $userid)
        ->delete()
        ->execute();
}

header("HTTP/1.1 303 See Other");
header("Location: {$returnpath}");

?>
