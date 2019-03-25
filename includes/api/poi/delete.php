<?php
/*
    DELETE request will delete the given POI.
*/
$deletedata = json_decode(file_get_contents("php://input"), true);

if (!$currentUser->hasPermission("admin/pois/general")) {
    XHR::exitWith(403, array("reason" => "access_denied"));
}

/*
    Required fields are the POI ID. Ensure that it is present in the received
    data.
*/
$id = determinePOI($deletedata);
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

    Delete the POI.
*/
try {
    $db = Database::connect();
    $db
        ->from("poi")
        ->where("id", $id)
        ->delete()
        ->execute();

} catch (Exception $e) {
    XHR::exitWith(500, array("reason" => "database_error"));
}

XHR::exitWith(204, null);
?>
