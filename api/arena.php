<?php
/*
    This script is an API endpoint for adding and retrieving arena data.
*/

require_once("../includes/lib/global.php");
__require("xhr");
__require("db");
__require("auth");
__require("geo");
__require("config");
__require("api");

/*
    Set correct timezone to ensure research resets at the proper time.
*/
date_default_timezone_set(Config::get("map/updates/tz")->value());

/*
    Disable all caching.
*/
header("Expires: ".date("r", 0));
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Content-Type: application/json");

/*
    Identify the current user or API client that is submitting requests to this
    API endpoint.
*/
$currentUser = API::getCurrentClient()->exists()
             ? API::getCurrentClient()
             : Auth::getCurrentUser();

/*
    This function determines the IDs of the POIs that match most closely to the
    data in the given data array. Order of matching:

        1.  Match by POI ID (returns that POI):
              - $data["id"] (required)
        2.  Match by coordinates (returns closest POI):
              - $data["latitude"] (required)
              - $data["longitude"] (required)
        3.  Match by POI name (returns list of best matches):
              - $data["name"] (required)
              - $data["match_exact"] (optional)
              - $data["match_case"] (optional)
*/
function determineArena($data) {
    /*
        1. Check if an ID has been supplied. If so, return it.
    */
    if (isset($data["id"])) return array(intval($data["id"]));
    /*
        Only API clients should be allowed to match by other things than ID.
    */
    global $currentUser;
    if ($currentUser->isRealUser()) return false;
    /*
        2. Check if a coordinate pair has been supplied. Find the closest POI
           and return it.
    */
    if (isset($data["latitude"]) && isset($data["longitude"])) {
        $lat = floatval($data["latitude"]);
        $lon = floatval($data["longitude"]);
        $pois = Geo::listArenas();
        if (count($pois) == 0) return array();
        /*
            Calculate the distances between the given point and all POIs.
        */
        $distances = array();
        foreach ($pois as $poi) {
            $distances[$poi->getID()] = $poi->getProximityTo($lat, $lon);
        }
        /*
            Sort the list by increasing distance and return the first element.
        */
        asort($distances);
        reset($distances);
        return array(key($distances));
    }
    /*
        3. Check if a POI name has been supplied. Find the closest match (or
           or exact match, if specified) from all POIs for the given name.
    */
    if (isset($data["name"])) {
        $name = $data["name"];
        $exactMatch = isset($data["match_exact"]) && !!$data["match_exact"];
        $caseSensitive = !isset($data["match_case"]) || !!$data["match_case"];
        $pois = Geo::listArenas();
        if (count($pois) == 0) return array();
        /*
            Calculate the similarity between the given name and the names of all
            POIs. Take into consideration whether or not the matching should be
            done in a case sensitive manner.
        */
        $distances = array();
        foreach ($pois as $poi) {
            $str1 = $poi->getName();
            $str2 = $name;
            if (!$caseSensitive) {
                $str1 = strtolower($poi->getName());
                $str2 = strtolower($name);
            }
            $perc1 = 0; $perc2 = 0;
            similar_text($str1, $str2, $perc1);
            similar_text($str2, $str1, $perc2);
            $distances[$poi->getID()] = $perc1 + $perc2;
        }
        /*
            Sort the list by decreasing similarity and return a list of
            candidates with the highest equal scores (multiple POIs may have the
            same name).
        */
        arsort($distances);
        $closest = reset($distances);
        $candidates = array();
        foreach ($distances as $poiId => $distance) {
            if ($distance == $closest) {
                if (!$exactMatch || $distance >= 200) $candidates[] = $poiId;
            } else {
                break;
            }
        }
        return $candidates;
    }
    /*
        Fall back to `false` if there were no matches.
    */
    return false;
}

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    include(__DIR__."/../includes/api/arena/list.php");
} elseif ($_SERVER["REQUEST_METHOD"] === "PUT") {
    include(__DIR__."/../includes/api/arena/add.php");
} elseif ($_SERVER["REQUEST_METHOD"] === "PATCH") {
    /*
        PATCH request will update the field research that is currently active on
        the POI, move the POI, or otherwise change it, depending on passed
        parameters.
    */
    $patchdata = json_decode(file_get_contents("php://input"), true);
    if (isset($patchdata["move_to"])) {
        include(__DIR__."/../includes/api/arena/move.php");
    } elseif (isset($patchdata["rename_to"])) {
        include(__DIR__."/../includes/api/arena/rename.php");
    }
    // Unsupported method, or data is missing
    XHR::exitWith(400, array("reason" => "missing_fields"));

} elseif ($_SERVER["REQUEST_METHOD"] === "DELETE") {
    include(__DIR__."/../includes/api/arena/delete.php");
} else {
    XHR::exitWith(405, array("reason" => "not_implemented"));
}

?>
