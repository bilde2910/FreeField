<?php
/*
    A POI is being reported as evil.
*/
if (!$currentUser->hasPermission("report-evil")) {
    XHR::exitWith(403, array("reason" => "access_denied"));
}

$id = determinePOI($patchdata);
if ($id === false) {
    XHR::exitWith(400, array("reason" => "missing_fields"));
} elseif (count($id) == 0) {
    XHR::exitWith(400, array("reason" => "no_poi_candidates"));
} elseif (count($id) > 1) {
    XHR::exitWith(400, array("reason" => "poi_ambiguous", "candidates" => $id));
} else {
    $id = $id[0];
}

/*
    Validity is verified from here on.

    Create a database update array.
*/
$data = array(
    "updated_by" => $currentUser->getUserID(),
    "last_updated" => date("Y-m-d H:i:s"),
    "evil_reported" => $patchdata["set_evil"] == "true"
                       ? date("Y-m-d H:i:s") : null
);

try {
    $db = Database::connect();
    $db
        ->from("poi")
        ->where("id", $id)
        ->update($data)
        ->execute();

} catch (Exception $e) {
    XHR::exitWith(500, array("reason" => "database_error"));
}

XHR::exitWith(204, null);
?>
