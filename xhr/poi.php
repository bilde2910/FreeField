<?php

require_once("../includes/lib/global.php");
__require("xhr");
__require("db");

// TODO: Permissions

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    // List POIs
    try {
        $db = Database::getSparrow();
        $pois = $db
            ->from(Database::getTable("poi"))
            ->many();

        $poidata = array();

        foreach ($pois as $poi) {
            $poidata[] = array(
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
                "updated" => array(
                    "on" => strtotime($poi["last_updated"]),
                    "by" => $poi["updated_by"]
                )
            );
        }

        xhr::exitWith(200, array("pois" => $poidata));
    } catch (Exception $e) {
        XHR::exitWith(500, array("reason" => "xhr.failed.reason.database_error"));
    }

} elseif ($_SERVER["REQUEST_METHOD"] === "PUT") {
    // Add new POI
    $reqfields = array("name", "lat", "lon");
    $putdata = json_decode(file_get_contents("php://input"), true);

    foreach ($reqfields as $field) {
        if (!isset($putdata[$field])) {
            XHR::exitWith(400, array("reason" => "xhr.failed.reason.missing_fields"));
        }
    }

    $data = array(
        "name" => $putdata["name"],
        "latitude" => floatval($putdata["lat"]),
        "longitude" => floatval($putdata["lon"]),
        "updated_by" => "USERNAME", //TODO
        "objective" => "unknown",
        "obj_params" => json_encode(array()),
        "reward" => "unknown",
        "rew_params" => json_encode(array())
    );

    if ($data["name"] == "") {
        XHR::exitWith(400, array("reason" => "poi.add.failed.reason.name_empty"));
    }

    // TODO: Geofencing

    try {
        $db = Database::getSparrow();
        $db
            ->from(Database::getTable("poi"))
            ->insert($data)
            ->execute();
        $poi = $db
            ->from(Database::getTable("poi"))
            ->where($data)
            ->one();

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
            "updated" => array(
                "on" => strtotime($poi["last_updated"]),
                "by" => $poi["updated_by"]
            )
        );

        XHR::exitWith(201, array("poi" => $poidata));
    } catch (Exception $e) {
        XHR::exitWith(500, array("reason" => "xhr.failed.reason.database_error"));
    }

} elseif ($_SERVER["REQUEST_METHOD"] === "PATCH") {
    // Update research quest
    $reqfields = array("id", "objective", "reward");
    $patchdata = json_decode(file_get_contents("php://input"), true);

    // Check that required data is present
    foreach ($reqfields as $field) {
        if (!isset($patchdata[$field])) {
            XHR::exitWith(400, array("reason" => "xhr.failed.reason.missing_fields"));
        }
    }
    if (!is_array($patchdata["objective"]) || !isset($patchdata["objective"]["type"]) || !isset($patchdata["objective"]["params"]) || !is_array($patchdata["objective"]["params"])) {
        XHR::exitWith(400, array("reason" => "xhr.failed.reason.invalid_data"));
    }
    if (!is_array($patchdata["reward"]) || !isset($patchdata["reward"]["type"]) || !isset($patchdata["reward"]["params"]) || !is_array($patchdata["reward"]["params"])) {
        XHR::exitWith(400, array("reason" => "xhr.failed.reason.invalid_data"));
    }

    // Check validity of data
    __require("research");
    if (isset(Research::OBJECTIVES[$patchdata["objective"]["type"]])) {
        $objective = $patchdata["objective"]["type"];
        $params = $patchdata["objective"]["params"];
        $validParams = Research::OBJECTIVES[$objective]["params"];

        // Check that all required parameters are present
        foreach ($validParams as $param) {
            if (!isset($params[$param])) {
                XHR::exitWith(400, array("reason" => "xhr.failed.reason.invalid_data"));
            }
        }
        // Check that all present parameters are acceptable
        foreach ($params as $param => $data) {
            if (!in_array($param, $validParams)) {
                XHR::exitWith(400, array("reason" => "xhr.failed.reason.invalid_data"));
            }
        }
        // Check validity of parameters
        foreach ($params as $param => $data) {
            $class = Research::PARAMETERS[$param];
            $inst = new $class();
            if (!in_array("objectives", $inst->getAvailable()) || !$inst->isValid($data)) {
                XHR::exitWith(400, array("reason" => "xhr.failed.reason.invalid_data"));
            }
        }
        $objParams = $params;
    } else {
        XHR::exitWith(400, array("reason" => "xhr.failed.reason.invalid_data"));
    }

    if (isset(Research::REWARDS[$patchdata["reward"]["type"]])) {
        $reward = $patchdata["reward"]["type"];
        $params = $patchdata["reward"]["params"];
        $validParams = Research::REWARDS[$reward]["params"];

        // Check that all required parameters are present
        foreach ($validParams as $param) {
            if (!isset($params[$param])) {
                XHR::exitWith(400, array("reason" => "xhr.failed.reason.invalid_data"));
            }
        }
        // Check that all present parameters are acceptable
        foreach ($params as $param => $data) {
            if (!in_array($param, $validParams)) {
                XHR::exitWith(400, array("reason" => "xhr.failed.reason.invalid_data"));
            }
        }
        // Check validity of parameters
        foreach ($params as $param => $data) {
            $class = Research::PARAMETERS[$param];
            $inst = new $class();
            if (!in_array("rewards", $inst->getAvailable()) || !$inst->isValid($data)) {
                XHR::exitWith(400, array("reason" => "xhr.failed.reason.invalid_data"));
            }
        }
        $rewParams = $params;
    } else {
        XHR::exitWith(400, array("reason" => "xhr.failed.reason.invalid_data"));
    }

    // Data is valid from here on

    $data = array(
        "updated_by" => "USERNAME", //TODO
        "objective" => $objective,
        "obj_params" => json_encode($objParams),
        "reward" => $reward,
        "rew_params" => json_encode($rewParams)
    );

    try {
        $db = Database::getSparrow();
        $userdata = $db
            ->from(Database::getTable("poi"))
            ->where("id", $patchdata["id"])
            ->update($data)
            ->execute();

        XHR::exitWith(204, null);
    } catch (Exception $e) {
        XHR::exitWith(500, array("reason" => "xhr.failed.reason.database_error"));
    }

} else {
    XHR::exitWith(405, array("reason" => "xhr.failed.reason.http_405"));
}

?>
