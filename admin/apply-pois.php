<?php
/*
    This file handles submission of POI changes from the administration
    interface.
*/

require_once("../includes/lib/global.php");
__require("auth");
__require("db");
__require("geo");
__require("security");

$returnpath = "./?d=pois";

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
    If the requesting user does not have permission to make changes here, they
    should be kicked out.
*/
if (!Auth::getCurrentUser()->hasPermission("admin/pois/general")) {
    header("HTTP/1.1 303 See Other");
    header("Location: {$returnpath}");
    exit;
}

/*
    The POI list we get from /includes/lib/geo.php contains a list of POIs in an
    indexed array. We'll convert it to an associative array where the POI ID is
    the key, to make it easier to fetch the details for a POI given its ID. This
    is because the updates POSTed from the client contains changes where the POI
    ID is the identifier for the POIs whose settings have changed.

    The `Geo::listPOIs()` function returns an array of `POI` class instances.
    Please refer to /includes/lib/geo.php for the structure of this class.
*/
$poilist = Geo::listPOIs();
$pois_assoc = array();

foreach ($poilist as $poi) {
    $pois_assoc[$poi->getID()] = $poi;
}

/*
    Create an array for updates, as well as an array for deletions, to be
    applied in one batch at the end of this script.
*/
$updates = array();
$deletes = array();

foreach ($_POST as $poi => $data) {
    /*
        Ensure that the POST field we're working on now is a POI change field.
        These all have field names in the format "p<poiID>". If this matches,
        extract the POI ID from the field name.
    */
    if (strlen($poi) < 1 || substr($poi, 0, 1) !== "p") continue;
    $pid = substr($poi, 1);

    if ($data["action"] === "delete") {
        /*
            If POI deletion is requested, add it to the deletion queue and do
            not process further changes.
        */
        $deletes[] = $pid;
        continue;

    } elseif ($data["action"] === "clear") {
        /*
            If the user requests clearing the research objective and reward
            currently active on the POI, the best way to do this is to set the
            active research objective and reward to "unknown" and clearing the
            parameter list for both.
        */
        if (
            !$pois_assoc[$pid]->isObjectiveUnknown() ||
            !$pois_assoc[$pid]->isRewardUnknown()
        ) {
            $updates[$pid]["objective"] = "unknown";
            $updates[$pid]["reward"] = "unknown";
            $updates[$pid]["obj_params"] = json_encode(array());
            $updates[$pid]["rew_params"] = json_encode(array());
            $updates[$pid]["updated_by"] = Auth::getCurrentUser()->getUserID();
            $updates[$pid]["last_updated"] = date("Y-m-d H:i:s");
        }
    }

    /*
        Handle changes to the POI parameters, such as the POI's name. If there
        are changes, they should be added to the updates queue.
    */
    if ($pois_assoc[$pid]->getName() !== $data["name"]) {
        $updates[$pid]["name"] = $data["name"];
    }
}

/*
    Apply the updates queue to the database, and then process deletions.
*/
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
