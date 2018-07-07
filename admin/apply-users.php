<?php

require_once("../includes/lib/global.php");
__require("config");
__require("auth");
__require("db");

$returnpath = "./?d=users";

if (!Auth::getCurrentUser()->hasPermission("admin/users/general")) {
    header("HTTP/1.1 303 See Other");
    header("Location: {$returnpath}");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("HTTP/1.1 303 See Other");
    header("Location: {$returnpath}");
    exit;
}

$userlist = Auth::listUsers();
$users_assoc = array();

foreach ($userlist as $user) {
    $users_assoc[$user->getUserID()] = $user;
}

$updates = array();
$deletes = array();

foreach ($_POST as $user => $data) {
    if (!Auth::getCurrentUser()->canChangeAtPermission($users_assoc[$user]->getPermissionLevel())) {
        continue;
    }
    if ($data["action"] === "delete") {
        $deletes[] = $user;
        continue;
    }
    if ($data["action"] === "approve") {
        $updates[$user]["approved"] = true;
        continue;
    }
    if (!$users_assoc[$user]->isApproved()) continue;
    if ($users_assoc[$user]->getNickname() !== $data["nick"]) {
        $updates[$user]["nick"] = $data["nick"];
    }
    if ($users_assoc[$user]->getPermissionLevel() !== $data["group"] && Auth::getCurrentUser()->hasPermission("admin/users/groups")) {
        $group = intval($data["group"]);
        if (Auth::getCurrentUser()->canChangeAtPermission($group)) {
            $updates[$user]["permission"] = $group;
        }
    }
}

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
