<?php
__require("config");

/*
    PUT request will add a new arena.
*/
if (!$currentUser->hasPermission("submit-arena")) {
    XHR::exitWith(403, array("reason" => "access_denied"));
}

/*
    Required fields are the arena name and its latitude and longitude. Check
    that all of these fields are present in the received data.
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
    storage of the arena in the database.
*/
$data = array(
    "name" => $putdata["name"],
    "latitude" => floatval($putdata["lat"]),
    "longitude" => floatval($putdata["lon"]),
    "created_by" => $currentUser->getUserID(),
    "updated_by" => $currentUser->getUserID()
);

/*
    If any of the users are null, unset the values as they default to null.
*/
if ($data["created_by"] === null) unset($data["created_by"]);
if ($data["updated_by"] === null) unset($data["updated_by"]);

/*
    Ensure that the arena has a name and is within the allowed geofence bounds
    for this FreeField instance.
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
        ->from("arena")
        ->insert($data)
        ->execute();

    /*
        Re-fetch the newly created arena from the database and return details
        about the arena back to the submitting client.
    */
    $poi = $db
        ->from("arena")
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
        "updated" => $updatedArray
    );

    XHR::exitWith(201, array("arena" => $poidata));
} catch (Exception $e) {
    XHR::exitWith(500, array("reason" => "database_error"));
}
?>
