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
__require("config");

/*
    Set correct timezone to ensure proper `date()` functionality.
*/
date_default_timezone_set(Config::get("map/updates/tz")->value());

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
    Create an array for updates, as well as an array for deletions and new
    insertions (for imported POIs), to be applied in one batch at the end of
    this script.
*/
$updates = array();
$deletes = array();
$inserts = array();

foreach ($_POST as $poi => $data) {
    /*
        Ensure that the POST field we're working on now is a POI change field.
        These all have field names in the format "p<poiID>". If this matches,
        extract the POI ID from the field name.
    */
    if (strlen($poi) < 1 || substr($poi, 0, 1) !== "p") continue;
    $pid = substr($poi, 1);

    /*
        Check if this is a new (imported) POI.
    */
    $imported = strlen($poi) >= 3 && substr($pid, 0, 2) == "n_";
    if ($imported) {
        /*
            Check if the user has permission to import POIs.
        */
        if (!Auth::getCurrentUser()->hasPermission("admin/pois/import")) continue;

        /*
            Check if all data is required and the POI is flagged for importing.
        */
        if (!isset($data["name"]) || $data["name"] == "") continue;
        if (!isset($data["latitude"]) || $data["latitude"] == "") continue;
        if (!isset($data["longitude"]) || $data["longitude"] == "") continue;
        if (!isset($data["include"]) || $data["include"] !== "yes") continue;

        /*
            Check that the latitude and longitude is valid.
        */
        if (!is_numeric($data["latitude"])) continue;
        if (!is_numeric($data["longitude"])) continue;

        /*
            Create a database entry associative array containing the required
            data for storage of the POI in the database. Default to to "unknown"
            field research for the POI, since no research has been reported for
            it yet.
        */
        $newPoi = array(
            "name" => $data["name"],
            "latitude" => floatval($data["latitude"]),
            "longitude" => floatval($data["longitude"]),
            "created_by" => Auth::getCurrentUser()->getUserID(),
            "updated_by" => Auth::getCurrentUser()->getUserID(),
            "objective" => "unknown",
            "obj_params" => json_encode(array()),
            "reward" => "unknown",
            "rew_params" => json_encode(array())
        );

        /*
            If any of the users are null, unset the values as they default to
            null.
        */
        if ($newPoi["created_by"] === null) unset($newPoi["created_by"]);
        if ($newPoi["updated_by"] === null) unset($newPoi["updated_by"]);

        $inserts[] = $newPoi;
    } else {
        /*
            POI is pre-existing.
        */
        if (isset($data["action"])) {
            if ($data["action"] === "delete") {
                /*
                    If POI deletion is requested, add it to the deletion queue
                    and do not process further changes.
                */
                $deletes[] = $pid;
                continue;

            } elseif ($data["action"] === "clear") {
                /*
                    If the user requests clearing the research objective and
                    reward currently active on the POI, the best way to do this
                    is to set the active research objective and reward to
                    "unknown" and clearing the parameter list for both.
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
        }

        /*
            Handle changes to the POI parameters, such as the POI's name. If
            there are changes, they should be added to the updates queue.
        */
        if (
            isset($data["name"]) &&
            $pois_assoc[$pid]->getName() !== $data["name"]
        ) {
            $updates[$pid]["name"] = $data["name"];
        }
    }
}

/*
    Apply the updates queue to the database, and then process deletions and
    insertions.
*/
$db = Database::connect();
foreach ($updates as $poiid => $update) {
    $userdata = $db
        ->from("poi")
        ->where("id", $poiid)
        ->update($update)
        ->execute();
}
foreach ($deletes as $poiid) {
    $db
        ->from("poi")
        ->where("id", $poiid)
        ->delete()
        ->execute();
}
foreach ($inserts as $data) {
    $db
        ->from("poi")
        ->insert($data)
        ->execute();
}

header("HTTP/1.1 303 See Other");
header("Location: {$returnpath}");

?>
