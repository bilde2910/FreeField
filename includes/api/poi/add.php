<?php
__require("config");

/*
    PUT request will add a new POI.
*/
if (!$currentUser->hasPermission("submit-poi")) {
    XHR::exitWith(403, array("reason" => "access_denied"));
}

/*
    Required fields are the POI name and its latitude and longitude. Check that
    all of these fields are present in the received data.
*/
$reqfields = array("name", "lat", "lon");
$putdata = json_decode(file_get_contents("php://input"), true);
foreach ($reqfields as $field) {
    if (!isset($putdata[$field])) {
        XHR::exitWith(400, array("reason" => "missing_fields"));
    }
}

/*
    Create a database entry associative array containing the required data for
    storage of the POI in the database. Default to to "unknown" field research
    for the POI, since no research has been reported for it yet.
*/
$data = array(
    "name" => $putdata["name"],
    "latitude" => floatval($putdata["lat"]),
    "longitude" => floatval($putdata["lon"]),
    "created_by" => $currentUser->getUserID(),
    "updated_by" => $currentUser->getUserID(),
    "objective" => "unknown",
    "obj_params" => json_encode(array()),
    "reward" => "unknown",
    "rew_params" => json_encode(array())
);

/*
    If any of the users are null, unset the values as they default to null.
*/
if ($data["created_by"] === null) unset($data["created_by"]);
if ($data["updated_by"] === null) unset($data["updated_by"]);

/*
    Ensure that the POI has a name and is within the allowed geofence bounds for
    this FreeField instance.
*/
if ($data["name"] == "") {
    XHR::exitWith(400, array("reason" => "name_empty"));
}
$geofence = Config::get("map/geofence/geofence")->value();
if ($geofence !== null && !$geofence->containsPoint($data["latitude"], $data["longitude"])) {
    XHR::exitWith(400, array("reason" => "invalid_location"));
}

try {
    $db = Database::connect();
    $db
        ->from("poi")
        ->insert($data)
        ->execute();

    /*
        Re-fetch the newly created POI from the database and return details
        about the POI back to the submitting client.
    */
    $poi = $db
        ->from("poi")
        ->where($data)
        ->one();

    $updatedArray = array("on" => strtotime($poi["last_updated"]));
    if ($currentUser->hasPermission("find-reporter")) {
        $updatedArray["by"] = array(
            "nick" => $currentUser->getNickname(),
            "color" => "#".$currentUser->getColor()
        );
    }
    $poidata = array(
        "id" => intval($poi["id"]),
        "name" => $poi["name"],
        "latitude" => floatval($poi["latitude"]),
        "longitude" => floatval($poi["longitude"]),
        "objective" => array(
            "type" => $poi["objective"],
            "params" => json_decode($poi["obj_params"], true)
        ),
        "reward" => array(
            "type" => $poi["reward"],
            "params" => json_decode($poi["rew_params"], true)
        ),
        "updated" => $updatedArray
    );

    XHR::exitWith(201, array("poi" => $poidata));
} catch (Exception $e) {
    XHR::exitWith(500, array("reason" => "database_error"));
}
?>
