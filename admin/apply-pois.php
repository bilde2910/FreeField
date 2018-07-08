<?php

require_once("../includes/lib/global.php");
__require("auth");
__require("db");
__require("geo");

$returnpath = "./?d=pois";

if (!Auth::getCurrentUser()->hasPermission("admin/pois/general")) {
    header("HTTP/1.1 303 See Other");
    header("Location: {$returnpath}");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("HTTP/1.1 303 See Other");
    header("Location: {$returnpath}");
    exit;
}

$poilist = Geo::listPOIs();
$pois_assoc = array();

foreach ($poilist as $poi) {
    $pois_assoc[$poi["id"]] = $poi;
}

$updates = array();
$deletes = array();

foreach ($_POST as $poi => $data) {
    if (strlen($poi) < 1 || substr($poi, 0, 1) !== "p") continue;
    $pid = substr($poi, 1);

    if ($data["action"] === "delete") {
        $deletes[] = $pid;
        continue;
    } elseif ($data["action"] === "clear") {
        $updates[$pid]["objective"] = "unknown";
        $updates[$pid]["reward"] = "unknown";
        $updates[$pid]["obj_params"] = json_encode(array());
        $updates[$pid]["rew_params"] = json_encode(array());
        $updates[$pid]["updated_by"] = Auth::getCurrentUser()->getUserID();
    }

    if ($pois_assoc[$pid]["name"] !== $data["name"]) {
        $updates[$pid]["name"] = $data["name"];
        $updates[$pid]["updated_by"] = Auth::getCurrentUser()->getUserID();
    }
}

$db = Database::getSparrow();
foreach ($updates as $poiid => $update) {
    $userdata = $db
        ->from(Database::getTable("poi"))
        ->where("id", $poiid)
        ->update($update)
        ->execute();
}
foreach ($deletes as $poiid) {
    $db
        ->from(Database::getTable("poi"))
        ->where("id", $poiid)
        ->delete()
        ->execute();
}

header("HTTP/1.1 303 See Other");
header("Location: {$returnpath}");

?>
