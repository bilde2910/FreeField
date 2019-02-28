<?php
/*
    GET request will list all available POIs.
*/
if (!$currentUser->hasPermission("access")) {
    XHR::exitWith(403, array("reason" => "access_denied"));
}
try {
    $pois = Geo::listPOIs();
    $geofence = Config::get("map/geofence/geofence")->value();

    /*
        Complete list of POI data to send back to the browser.
    */
    $poidata = array();

    /*
        A list of IDs only, used to detect deletions when a limited range of
        updates are requested.
    */
    $poiIDs = array();

    /*
        In order to save bandwidth, only send POIs updated after a certain
        timestamp if the client requests it.
    */
    $today = strtotime("today midnight");
    if (isset($_GET["updatedSince"])) {
        $updatedSince = intval($_GET["updatedSince"]);
        if ($updatedSince < 0) $updatedSince += time();
    }

    foreach ($pois as $poi) {
        /*
            If FreeField is configured to hide POIs that are out of POI geofence
            bounds, the POI should not be added to the list of returned POIs if
            it lies outside of the POI geofence.
        */
        if (
            Config::get("map/geofence/hide-outside")->value() &&
            !$poi->isWithinGeofence($geofence)
        ) {
            continue;
        }

        /*
            In order to save bandwidth, only send POIs updated after a certain
            timestamp if the client requests it.
        */
        if (
            !isset($_GET["updatedSince"]) ||
            $updatedSince < $today ||
            $poi->getLastUpdatedTime() >= $updatedSince
        ) {
            /*
                Add the POI to the list of returned POIs.
            */
            $updatedArray = array("on" => $poi->getLastUpdatedTime());
            if ($currentUser->hasPermission("find-reporter")) {
                $updatedArray["by"] = array(
                    "nick" => $poi->getLastUser()->getNickname(),
                    "color" => "#".$poi->getLastUser()->getColor()
                );
            }
            $poidata[] = array(
                "id" => intval($poi->getID()),
                "name" => $poi->getName(),
                "latitude" => $poi->getLatitude(),
                "longitude" => $poi->getLongitude(),
                "objective" => $poi->getCurrentObjective(),
                "reward" => $poi->getCurrentReward(),
                "updated" => $updatedArray
            );
        }

        /*
            Add the ID of the POI to prove its existence.
        */
        $poiIDs[] = $poi->getID();
    }

    XHR::exitWith(200, array("pois" => $poidata, "idList" => $poiIDs));
} catch (Exception $e) {
    /*
        `Geo::listPOIs()` may fail with a database error and throw an exception.
    */
    XHR::exitWith(500, array("reason" => "database_error"));
}
?>
