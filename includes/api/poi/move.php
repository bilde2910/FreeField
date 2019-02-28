<?php
/*
    A POI is being moved.
*/
if (!$currentUser->hasPermission("admin/pois/general")) {
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
    `move_to` must be an arrays with keys defined for `latitude` and
    `longitude`, both of which must be numbers within valid bounds.
*/
if (
    !is_array($patchdata["move_to"]) ||
    !isset($patchdata["move_to"]["latitude"]) ||
    !isset($patchdata["move_to"]["longitude"]) ||
    !is_numeric($patchdata["move_to"]["latitude"]) ||
    !is_numeric($patchdata["move_to"]["longitude"])
) {
    XHR::exitWith(400, array("reason" => "invalid_data"));
}

$latitude = floatval($patchdata["move_to"]["latitude"]);
$longitude = floatval($patchdata["move_to"]["longitude"]);
if (
    $latitude < -90 || $latitude > 90 ||
    $longitude < -180 || $longitude > 180
) {
    XHR::exitWith(400, array("reason" => "invalid_data"));
}

/*
    Validity is verified from here on.

    Create a database update array.
*/
$data = array(
    "updated_by" => $currentUser->getUserID(),
    "last_updated" => date("Y-m-d H:i:s"),
    "latitude" => $latitude,
    "longitude" => $longitude
);

/*
    If FreeField is configured to only accept POIs within a certain geofence
    boundary, and the POI is being moved to a location outside those bounds,
    there is no reason to allow the update.
*/
$geofence = Config::get("map/geofence/geofence")->value();

if (
    $geofence !== null &&
    !$geofence->containsPoint($latitude, $longitude)
) {
    XHR::exitWith(400, array("reason" => "invalid_location"));
}

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
