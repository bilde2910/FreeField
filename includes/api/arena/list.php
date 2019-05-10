<?php
/*
    GET request will list all available arenas.
*/
if (!$currentUser->hasPermission("access")) {
    XHR::exitWith(403, array("reason" => "access_denied"));
}
try {
    $arenas = Geo::listArenas();
    $geofence = Config::get("map/geofence/geofence")->value();

    /*
        Complete list of arena data to send back to the browser.
    */
    $arenadata = array();

    /*
        A list of IDs only, used to detect deletions when a limited range of
        updates are requested.
    */
    $arenaIDs = array();

    /*
        In order to save bandwidth, only send arenas updated after a certain
        timestamp if the client requests it.
    */
    if (isset($_GET["updatedSince"])) {
        $updatedSince = intval($_GET["updatedSince"]);
        if ($updatedSince < 0) $updatedSince += time();
    }

    foreach ($arenas as $arena) {
        /*
            If FreeField is configured to hide POIs that are out of POI geofence
            bounds, the arena should not be added to the list of returned arenas if
            it lies outside of the geofence.
        */
        if (
            Config::get("map/geofence/hide-outside")->value() &&
            !$arena->isWithinGeofence($geofence)
        ) {
            continue;
        }

        /*
            In order to save bandwidth, only send arenas updated after a certain
            timestamp if the client requests it.
        */
        if (
            !isset($_GET["updatedSince"]) ||
            $arena->getLastUpdatedTime() >= $updatedSince
        ) {
            /*
                Add the arena to the list of returned arenas.
            */
            $updatedArray = array("on" => $arena->getLastUpdatedTime());
            if ($currentUser->hasPermission("find-reporter")) {
                $updatedArray["by"] = array(
                    "nick" => $arena->getLastUser()->getNickname(),
                    "color" => "#".$arena->getLastUser()->getColor()
                );
            }
            $arenadata[] = array(
                "id" => intval($arena->getID()),
                "name" => $arena->getName(),
                "latitude" => $arena->getLatitude(),
                "longitude" => $arena->getLongitude(),
                "updated" => $updatedArray
            );
        }

        /*
            Add the ID of the arena to prove its existence.
        */
        $arenaIDs[] = $arena->getID();
    }

    XHR::exitWith(200, array("arenas" => $arenadata, "id_list" => $arenaIDs));
} catch (Exception $e) {
    /*
        `Geo::listArenas()` may fail with a database error and throw an exception.
    */
    XHR::exitWith(500, array("reason" => "database_error"));
}
?>
