<?php
/*
    This script is an API endpoint for adding and retrieving POI data and
    updating field research.
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
    When the user enters a body payload for webhooks, they may choose to use
    substitution tokens, such as <%COORDS%> or <%POI%>. These should be replaced
    with the proper dynamic values before the webhook payload is posted to the
    target URL.

    This function accepts a `$body` payload, a `Theme` instance `$theme`
    representing the icon set selected for the webhook, and the `$time`stamp on
    which the field research was reported by the user. The timestamp is required
    because multiple webhooks may be triggered, and if one webhook takes a long
    time to execute, there is a risk that the research would be reported with
    different timestamps for each triggered webhook. `$time` is set once as the
    research is updated, and then re-used for each webhook, preventing this from
    happening.

    In some situations it may be necessary to escape strings containing certain
    values. This is done by passing a closure to `$escapeStr` that escapes a
    string passed to it and returns the result.
*/
function replaceWebhookFields($currentUser, $poidata, $time, $theme, $spTheme, $useSpecies, $body, $escapeStr) {
    __require("research");

    /*
        Fetch required POI details for convenience.
    */
    $objective = $poidata->getCurrentObjective()["type"];
    $objParams = $poidata->getCurrentObjective()["params"];
    $reward = $poidata->getCurrentReward()["type"];
    $rewParams = $poidata->getCurrentReward()["params"];

    /*
        The body likely contains substitution tokens, and our job in this
        function is to replace them with the values that they are supposed to
        represent. We need a way to reliably extract tokens that can take
        optional parameters. We can use regex to do this, by looking for a
        sequence of something like <%.*(.*)?%>.

        We need to ensure that it handles tags within tags properly, i.e. it
        doesn't do nonsense like this:

        <%TAG(<%NESTED_TAG(some argument)%>,some other argument)%>
        |--------------MATCH--------------|

        The solution is the following regex query:

        <%                  | Open substitution token tag
        (                   | Group 1: Substitution token name
          (                 | Group 2: Match either:
            [^\(%]          | Any character that is not ( or %
          |                 | Or:
            %(?!>)          | A % that is not followed by >
          )*                | .. and match any number of the preceding
        )                   |
        (                   | Group 3: Parameters wrapped in parentheses
          \(                | Opening parenthesis before parameter list
          (                 | Group 4: Parameters, not wrapped
            (               | Group 5: Match either:
              [^<\)]        | Any character that is not < or )
            |               | Or:
              \)(?!%>)      | A ) that is not followed by %>
            |               | Or:
              <(?!%)        | A < that is not followed by %
            )*              | .. and match any number of the preceding
            (?=\)%>)        | ..as long as, and until, followed by sequence )%>
          )                 |
          \)                | Closing parenthesis after parameter list
        )?                  | Parameters are optional
        %>                  | Close substitution token tag

        This query string allows us to detect and handle tags within tags
        properly. You can test it here: https://www.regexpal.com/

        The output is a match array with the following usable indices:

            [0] => The whole tag
            [1] => The name of the substitution token (e.g. "I18N")
            [4] => A comma-separated list of parameters

        These can be used to insert the correct strings of text in the webhook
        body.
    */
    $regex = "/<%(([^\(%]|%(?!>))*)(\((([^<\)]|\)(?!%>)|<(?!%))*(?=\)%>))\))?%>/";

    /*
        When we substitute the tokens, there is no guarantee that the
        replacement does not contain a special character or sequence, such as
        '<%', '%>' or ','. In order to prevent injection attacks, we store the
        replacements for each token in an array `$replArray`. Each replacement
        is assigned a uniquely generated ID, and the replacement that is
        inserted into the body is this unique ID string. The actual value of the
        replacement is stored in `$replArray` at the key corresponding to the
        generated ID, and all of the replacements are processed together once
        all replacements have been made.
    */
    $replArray = array();

    $matches = array();
    preg_match_all($regex, $body, $matches, PREG_SET_ORDER);

    while (count($matches) > 0) {
        foreach ($matches as $match) {
            $tokenTag = $match[0];
            $tokenName = $match[1];
            $tokenArgString = count($match) >= 5 ? $match[4] : "";

            /*
                Get a list of passed parameters.
            */
            if (strlen($tokenArgString) > 0) {
                // The argument string is comma-delimited.
                $tokenArgs = explode(",", $tokenArgString);
            } else {
                $tokenArgs = array();
            }

            /*
                Resolve any prior replacements in the argument strings.
            */
            for ($i = 0; $i < count($tokenArgs); $i++) {
                foreach ($replArray as $id => $repl) {
                    $tokenArgs[$i] = str_replace($id, $repl, $tokenArgs[$i]);
                }
            }

            $replacement = "";
            switch (strtoupper($tokenName)) {
                /*
                    Please consult the documentation for information about each of
                    these substitution tokens.
                */

                case "COORDS":
                    // <%COORDS([precision])%>
                    // precision: Number of decimals in output.
                    $replacement = Geo::getLocationString(
                        $poidata->getLatitude(),
                        $poidata->getLongitude()
                    );
                    if (count($tokenArgs) > 0) {
                        $replacement = Geo::getLocationString(
                            $poidata->getLatitude(),
                            $poidata->getLongitude(),
                            intval($tokenArgs[0])
                        );
                    }
                    break;

                case "FALLBACK":
                    // <%FALLBACK(expr,fallback)%>
                    // expr: String to return by default.
                    // fallback: String to return instead of `expr` is empty.
                    if (count($tokenArgs) < 2) break;
                    $replacement = $tokenArgs[0] != "" ? $tokenArgs[0] : $tokenArgs[1];
                    break;

                case "IF_EMPTY":
                case "IF_NOT_EMPTY":
                    // <%IF_EMPTY(expr,ifTrue[,ifFalse])%>
                    // <%IF_NOT_EMPTY(expr,ifTrue[,ifFalse])%>
                    // expr: Expression to evaluate.
                    // ifTrue: Output if expr == ""
                    // ifFalse: Output if expr != "", empty string if not given
                    if (count($tokenArgs) < 2) break;
                    $expr = $tokenArgs[0];
                    $ifTrue = $tokenArgs[1];
                    $ifFalse = count($tokenArgs) >= 3 ? $tokenArgs[2] : "";
                    switch ($tokenName) {
                        case "IF_EMPTY":
                            $eval = $expr == "";
                            break;
                        case "IF_NOT_EMPTY":
                            $eval = $expr != "";
                            break;
                    }
                    $replacement = $eval ? $ifTrue : $ifFalse;
                    break;

                case "IF_EQUAL":
                case "IF_NOT_EQUAL":
                case "IF_LESS_THAN":
                case "IF_LESS_OR_EQUAL":
                case "IF_GREATER_THAN":
                case "IF_GREATER_OR_EQUAL":
                    // <%IF_EQUAL(expr,value,ifTrue[,ifFalse])%>
                    // <%IF_NOT_EQUAL(expr,value,ifTrue[,ifFalse])%>
                    // <%IF_LESS_THAN(expr,value,ifTrue[,ifFalse])%>
                    // <%IF_LESS_OR_EQUAL(expr,value,ifTrue[,ifFalse])%>
                    // <%IF_GREATER_THAN(expr,value,ifTrue[,ifFalse])%>
                    // <%IF_GREATER_OR_EQUAL(expr,value,ifTrue[,ifFalse])%>
                    // expr: Expression to evaluate.
                    // value: Value to evaluate the expression against.
                    // ifTrue: Output if expression matches value as specified
                    // ifFalse: Output otherwise, empty string if not given
                    if (count($tokenArgs) < 3) break;
                    $expr = $tokenArgs[0];
                    $value = $tokenArgs[1];
                    $ifTrue = $tokenArgs[2];
                    $ifFalse = count($tokenArgs) >= 4 ? $tokenArgs[3] : "";
                    switch ($tokenName) {
                        case "IF_EQUAL":
                            $eval = $expr == $value;
                            break;
                        case "IF_NOT_EQUAL":
                            $eval = $expr != $value;
                            break;
                        case "IF_LESS_THAN":
                            $eval = floatval($expr) < floatval($value);
                            break;
                        case "IF_LESS_OR_EQUAL":
                            $eval = floatval($expr) <= floatval($value);
                            break;
                        case "IF_GREATER_THAN":
                            $eval = floatval($expr) > floatval($value);
                            break;
                        case "IF_GREATER_OR_EQUAL":
                            $eval = floatval($expr) >= floatval($value);
                            break;
                    }
                    $replacement = $eval ? $ifTrue : $ifFalse;
                    break;

                case "I18N":
                    // <%I18N(token[,arg1[,arg2...]])%>
                    // token: Localization token
                    // arg1..n: Argument to localization
                    if (count($tokenArgs) < 1) break;
                    $i18ntoken = $tokenArgs[0];
                    if (count($tokenArgs) == 1) {
                        $replacement = call_user_func_array("I18N::resolve", $tokenArgs);
                    } else {
                        $replacement = call_user_func_array("I18N::resolveArgs", $tokenArgs);
                    }
                    break;

                case "LAT":
                    // <%LAT%>
                    $replacement = $poidata->getLatitude();
                    break;

                case "LENGTH":
                    // <%LENGTH(string)%>
                    if (count($tokenArgs) < 1) break;
                    $replacement = strlen($tokenArgs[0]);
                    break;

                case "LNG":
                    // <%LNG%>
                    $replacement = $poidata->getLongitude();
                    break;

                case "LOWERCASE":
                    // <%LOWERCASE(string)%>
                    if (count($tokenArgs) < 1) break;
                    $replacement = strtolower($tokenArgs[0]);
                    break;

                case "NAVURL":
                    // <%NAVURL([provider])%>
                    // provider: Navigation provider ("google", "bing", etc.)
                    $naviprov = Geo::listNavigationProviders();
                    $provider = Config::get("map/provider/directions")->value();
                    if (count($tokenArgs) > 0) $provider = $tokenArgs[0];
                    if (isset($naviprov[$provider])) {
                        $replacement =
                            str_replace("{%LAT%}", urlencode($poidata->getLatitude()),
                            str_replace("{%LON%}", urlencode($poidata->getLongitude()),
                            str_replace("{%NAME%}", urlencode($poidata->getName()),
                                $naviprov[$provider]
                            )));
                    }
                    break;

                case "OBJECTIVE":
                    // <%OBJECTIVE%>
                    $replacement = Research::resolveObjective($objective, $objParams);
                    break;

                case "PAD_LEFT":
                case "PAD_RIGHT":
                    // <%PAD_LEFT(string,length[,padString])%>
                    // <%PAD_RIGHT(string,length[,padString])%>
                    if (count($tokenArgs) < 2) break;
                    $string = $tokenArgs[0];
                    $length = intval($tokenArgs[1]);
                    $padString = count($tokenArgs) >= 3 ? $tokenArgs[2] : " ";
                    $padType = $tokenName == "PAD_LEFT" ? STR_PAD_LEFT : STR_PAD_RIGHT;
                    $replacement = str_pad($string, $length, $padString, $padType);
                    break;

                case "POI":
                    // <%POI%>
                    $replacement = $poidata->getName();
                    break;

                case "REPORTER":
                    // <%REPORTER%>
                    $replacement = $currentUser->getNickname();
                    break;

                case "REWARD":
                    // <%REWARD%>
                    $replacement = Research::resolveReward($reward, $rewParams);
                    break;

                case "SITEURL":
                    // <%SITEURL%>
                    $replacement = Config::getEndpointUri("/");
                    break;

                case "SUBSTRING":
                    // <%SUBSTRING(string,start[,length])%>
                    if (count($tokenArgs) < 2) break;
                    $string = $tokenArgs[0];
                    $start = intval($tokenArgs[1]);
                    if (count($tokenArgs) >= 3) {
                        $length = intval($tokenArgs[2]);
                        $replacement = substr($string, $start, $length);
                    } else {
                        $replacement = substr($string, $start);
                    }
                    if ($replacement === FALSE) $replacement = "";
                    break;

                case "TIME":
                    // <%TIME(format)%>
                    // format: PHP date() format string
                    if (count($tokenArgs) < 1) break;
                    $replacement = date($tokenArgs[0], $time);
                    break;

                case "UPPERCASE":
                    // <%UPPERCASE(string)%>
                    if (count($tokenArgs) < 1) break;
                    $replacement = strtoupper($tokenArgs[0]);
                    break;

                case "OBJECTIVE_ICON":
                case "REWARD_ICON":
                    // <%OBJECTIVE_ICON(format,variant)%>
                    // <%REWARD_ICON(format,variant)%>
                    // format: "vector" (SVG) or "raster" (bitmap; PNG etc.)
                    // variant: "light" or "dark"
                    foreach ($tokenArgs as $key => $value) {
                        $tokenArgs[$key] = trim($value);
                    }
                    if (count($tokenArgs) < 2) break;
                    if (!in_array($tokenArgs[1], array("dark", "light"))) break;
                    $theme->setVariant($tokenArgs[1]);
                    $spTheme->setVariant($tokenArgs[1]);
                    $icon = $tokenName == "OBJECTIVE_ICON" ? $objective : $reward;
                    switch ($tokenArgs[0]) {
                        case "vector":
                            $replacement = $theme->getIconUrl($icon);
                            break;
                        case "raster":
                            $replacement = $theme->getRasterUrl($icon);
                            break;
                    }
                    // Display species instead of encounter icon?
                    if (
                        $useSpecies &&
                        $tokenName == "REWARD_ICON" &&
                        $reward == "encounter" &&
                        isset($rewParams["species"]) &&
                        count($rewParams["species"] == 1)
                    ) {
                        switch ($tokenArgs[0]) {
                            case "vector":
                                $replacement = $spTheme->getIconUrl($rewParams["species"][0]);
                                break;
                            case "raster":
                                $replacement = $spTheme->getRasterUrl($rewParams["species"][0]);
                                break;
                        }
                    }
                    break;

                case "OBJECTIVE_PARAMETER":
                case "REWARD_PARAMETER":
                    // <%OBJECTIVE_PARAMETER(param[,index])
                    // <%REWARD_PARAMETER(param[,index])
                    // param: Parameter of reported objective or reward
                    // index: Requested index if parameter is array
                    if (count($tokenArgs) < 1) break;
                    $params = $tokenName == "OBJECTIVE_PARAMETER" ? $objParams : $rewParams;
                    $reqParam = $tokenArgs[0];
                    if (isset($params[$reqParam])) {
                        // If parameter exists, get parameter
                        $paramData = $params[$reqParam];
                        if (is_array($paramData)) {
                            // If parameter is array, check if index is defined
                            if (count($tokenArgs) >= 2) {
                                // If it is, get the index
                                $index = intval($tokenArgs[1]) - 1;
                                if ($index >= 0 && $index < count($paramData)) {
                                    // If index found, return index
                                    $replacement = $paramData[$index];
                                }
                            } else {
                                // If not, join array with semicolons
                                $replacement = implode(",", $paramData);
                            }
                        } else {
                            $replacement = $paramData;
                        }
                    }
                    break;

                case "OBJECTIVE_PARAMETER_COUNT":
                case "REWARD_PARAMETER_COUNT":
                    // <%OBJECTIVE_PARAMETER_COUNT(param)
                    // <%REWARD_PARAMETER_COUNT(param)
                    // param: Parameter of reported objective or reward
                    if (count($tokenArgs) < 1) break;
                    $params = $tokenName == "OBJECTIVE_PARAMETER_COUNT" ? $objParams : $rewParams;
                    $reqParam = $tokenArgs[0];
                    if (!isset($params[$reqParam])) {
                        $replacement = 0;
                    } elseif (!is_array($params[$reqParam])) {
                        $replacement = 1;
                    } else {
                        $replacement = count($params[$reqParam]);
                    }
                    break;
            }

            /*
                Generate a random ID for this replacement and insert the real
                replacement into `$replArray`.
            */
            $id = base64_encode(openssl_random_pseudo_bytes(16));
            $replArray[$id] = strval($replacement);

            /*
                Replace the matched tag with the replacement.
            */
            $body = str_replace($tokenTag, $id, $body);
        }

        preg_match_all($regex, $body, $matches, PREG_SET_ORDER);
    }

    /*
        Resolve all replacement IDs in the body.
    */
    foreach ($replArray as $id => $repl) {
        $body = str_replace($id, $escapeStr($repl), $body);
    }

    return $body;
}

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
function determinePOI($data) {
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
        $pois = Geo::listPOIs();
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
        $exactMatch = isset($data["matchExact"]) && !!$data["matchExact"];
        $caseSensitive = !isset($data["matchCase"]) || !!$data["matchCase"];
        $pois = Geo::listPOIs();
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

/*
    This function determines the research objectives/rewards that match most
    closely to the data in the given objective/reward data array. Order of
    matching:

        1.  Match by objective/reward data directly:
              - $data["type"] (required)
              - $data["params"] (required)
        2.  Fuzzy match by objective/reward localized string:
              - $data["match"] (required)
              - $data["match_algo"] (optional)
*/
function determineResearchComponent($taskdata, $component) {
    /*
        1. Check if valid objective/reward data has been supplied directly. If
           so, return it.
    */
    if (isset($taskdata["type"]) && isset($taskdata["params"])) return $taskdata;
    /*
        Only API clients should be allowed to match by other things than valid
        objective/reward data.
    */
    global $currentUser;
    if ($currentUser->isRealUser()) return false;
    /*
        2. Check if a string match can be performed against the objective/reward
           text. Return the closest match.
    */
    if (isset($taskdata["match"])) {
        // Determine the matching algorithm to use.
        $algo = isset($taskdata["match_algo"]) ? intval($taskdata["match_algo"]) : 2;
        switch ($component) {
            case "objective":
                switch ($algo) {
                    case 1: // Fuzzy match against common-objectives.yaml
                        return fuzzyMatchCommonObjective($taskdata["match"]);
                    case 2: // Search for closest match of any objectives/parameters
                        return Research::matchObjective($taskdata["match"]);
                    default:
                        return null;
                }
            case "reward":
                switch ($algo) {
                    case 2: // Search for closest match of any rewards/parameters
                        return Research::matchReward($taskdata["match"]);
                    default:
                        return null;
                }
        }
    }
    /*
        Fall back to `false` if there were no matches.
    */
    return false;
}

/*
    The following functions are wrappers for `determineResearchComponent()` for
    objective and rewards, respectively.
*/
function determineObjective($objData) {
    return determineResearchComponent($objData, "objective");
}
function determineReward($rewData) {
    return determineResearchComponent($rewData, "reward");
}

/*
    This function attempts to match the given user-provided string against the
    current list of common research objectives from common-objectives.yaml.
*/
function fuzzyMatchCommonObjective($string) {
    /*
        First, get a list of all current research objectives.
    */
    $commonObjectives = Research::listCommonObjectives();
    /*
        Resolve their display texts in lowercase. Resolve both singular and
        plural forms for those research tasks that are singular in quantity.
    */
    $stringMap = array();
    for ($i = 0; $i < count($commonObjectives); $i++) {
        $stringMap[strtolower(
            Research::resolveObjective(
                $commonObjectives[$i]["type"],
                $commonObjectives[$i]["params"]
            )
        )] = $commonObjectives[$i];
        $stringMap[strtolower(
            Research::resolveObjective(
                $commonObjectives[$i]["type"],
                $commonObjectives[$i]["params"],
                true
            )
        )] = $commonObjectives[$i];
    }
    /*
        Calculate the similarity between the given objective string and all of
        the common objective tasks.
    */
    $distances = array();
    foreach ($stringMap as $candidate => $data) {
        $perc1 = 0; $perc2 = 0;
        similar_text(strtolower($string), $candidate, $perc1);
        similar_text($candidate, strtolower($string), $perc2);
        $distances[$candidate] = $perc1 + $perc2;
    }
    /*
        Sort the list by decreasing similarity and return a list of
        candidates with the highest equal scores (multiple POIs may have the
        same name).
    */
    arsort($distances);
    reset($distances);
    $chosen = key($distances);
    return $stringMap[$chosen];
}

if ($_SERVER["REQUEST_METHOD"] === "GET") {
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
            In order to save bandwidth, only send POIs updated after a
            certain timestamp if the client requests it.
        */
        $today = strtotime("today midnight");
        if (isset($_GET["updatedSince"])) {
            $updatedSince = intval($_GET["updatedSince"]);
            if ($updatedSince < 0) $updatedSince += time();
        }

        foreach ($pois as $poi) {
            /*
                If FreeField is configured to hide POIs that are out of POI
                geofence bounds, the POI should not be added to the list of
                returned POIs if it lies outside of the POI geofence.
            */
            if (
                Config::get("map/geofence/hide-outside")->value() &&
                !$poi->isWithinGeofence($geofence)
            ) {
                continue;
            }

            /*
                In order to save bandwidth, only send POIs updated after a
                certain timestamp if the client requests it.
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
            `Geo::listPOIs()` may fail with a database error and throw an
            exception.
        */
        XHR::exitWith(500, array("reason" => "database_error"));
    }

} elseif ($_SERVER["REQUEST_METHOD"] === "PUT") {
    __require("config");

    /*
        PUT request will add a new POI.
    */
    if (!$currentUser->hasPermission("submit-poi")) {
        XHR::exitWith(403, array("reason" => "access_denied"));
    }

    /*
        Required fields are the POI name and its latitude and longitude. Check
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
        Create a database entry associative array containing the required data
        for storage of the POI in the database. Default to to "unknown" field
        research for the POI, since no research has been reported for it yet.
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
        Ensure that the POI has a name and is within the allowed geofence bounds
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

} elseif ($_SERVER["REQUEST_METHOD"] === "PATCH") {
    /*
        PATCH request will update the field research that is currently active on
        the POI, or move the POI, depending on passed parameters.
    */
    $patchdata = json_decode(file_get_contents("php://input"), true);

    if (isset($patchdata["objective"]) && isset($patchdata["reward"])) {
        /*
            Field research is being updated.
        */
        if (!$currentUser->hasPermission("report-research")) {
            XHR::exitWith(403, array("reason" => "access_denied"));
        }

        /*
            Obtain and lock a timestamp of when research was submitted by the
            user.
        */
        $reportedTime = time();

        /*
            Required fields are the POI ID and the reported objective and
            reward. Ensure that all of these fields are present in the received
            data.
        */
        $reqfields = array("objective", "reward");

        foreach ($reqfields as $field) {
            if (!isset($patchdata[$field])) {
                XHR::exitWith(400, array("reason" => "missing_fields"));
            }
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
            `objective` and `reward` must both be arrays with keys defined for
            `type` and `params`. Params must additionally be an array or object.
            Validate the research objective first.
        */
        if (
            !is_array($patchdata["objective"])
        ) {
            XHR::exitWith(400, array("reason" => "invalid_data"));
        }
        $patchdata["objective"] = determineObjective($patchdata["objective"]);
        if ($patchdata["objective"] === false) {
            XHR::exitWith(400, array("reason" => "missing_fields"));
        } elseif ($patchdata["objective"] === null) {
            XHR::exitWith(501, array("reason" => "match_mode_not_implemented"));
        }
        if (
            !isset($patchdata["objective"]["type"]) ||
            !isset($patchdata["objective"]["params"]) ||
            !is_array($patchdata["objective"]["params"])
        ) {
            XHR::exitWith(400, array("reason" => "invalid_data"));
        }

        /*
            Validate the research reward as well.
        */
        $patchdata["reward"] = determineReward($patchdata["reward"]);
        if ($patchdata["reward"] === false) {
            XHR::exitWith(400, array("reason" => "missing_fields"));
        } elseif ($patchdata["reward"] === null) {
            XHR::exitWith(501, array("reason" => "match_mode_not_implemented"));
        }
        if (
            !is_array($patchdata["reward"]) ||
            !isset($patchdata["reward"]["type"]) ||
            !isset($patchdata["reward"]["params"]) ||
            !is_array($patchdata["reward"]["params"])
        ) {
            XHR::exitWith(400, array("reason" => "invalid_data"));
        }

        /*
            Ensure that the submitted research data is valid.
        */
        __require("research");

        $objective = $patchdata["objective"]["type"];
        $objParams = $patchdata["objective"]["params"];
        if (!Research::isObjectiveValid($objective, $objParams)) {
            XHR::exitWith(400, array("reason" => "invalid_data"));
        }

        $reward = $patchdata["reward"]["type"];
        $rewParams = $patchdata["reward"]["params"];
        if (!Research::isRewardValid($reward, $rewParams)) {
            XHR::exitWith(400, array("reason" => "invalid_data"));
        }

        /*
            Validity is verified from here on.

            Create a database update array.
        */
        $data = array(
            "updated_by" => $currentUser->getUserID(),
            "last_updated" => date("Y-m-d H:i:s"),
            "objective" => $objective,
            "obj_params" => json_encode($objParams),
            "reward" => $reward,
            "rew_params" => json_encode($rewParams)
        );

        /*
            If FreeField is configured to hide POIs that are out of POI geofence
            bounds, and the POI that is being updated is outside those bounds,
            there is no reason to allow the update since the user shouldn't be
            able to see the POI on the map in the first place to perform the
            update.
        */
        $poi = Geo::getPOI($id);
        $geofence = Config::get("map/geofence/geofence")->value();

        if (
            Config::get("map/geofence/hide-outside")->value() &&
            $geofence !== null &&
            !$poi->isWithinGeofence($geofence)
        ) {
            XHR::exitWith(400, array("reason" => "invalid_data"));
        }

        /*
            If field research is already defined for the given POI, a separate
            permission is required to allow users to overwrite field research
            tasks. This is required in addition to the permission allowing users
            to submit any kind of field research in the first place.
        */
        if ($poi->isUpdatedToday() && !$poi->isResearchUnknown()) {
            if (!$currentUser->hasPermission("overwrite-research")) {
                XHR::exitWith(403, array("reason" => "access_denied"));
            }
        }

        try {
            $db = Database::connect();
            $db
                ->from("poi")
                ->where("id", $id)
                ->update($data)
                ->execute();

            /*
                Re-fetch the newly created POI from the database. The
                information here is used to trigger webhooks for field research
                updates.
            */
            $poidata = Geo::getPOI($id);

        } catch (Exception $e) {
            XHR::exitWith(500, array("reason" => "database_error"));
        }

        /*
            Call webhooks.
        */
        __require("config");
        __require("theme");
        __require("research");

        /*
            Get a list of all webhooks and iterate over them to check
            eligibility of submissions.
        */
        $hooks = Config::getRaw("webhooks");
        if ($hooks === null) $hooks = array();
        foreach ($hooks as $hook) {
            if (!$hook["active"]) continue;

            /*
                Check that the POI is within the geofence of the webhook.
            */
            if (isset($hook["geofence"])) {
                if (!$poi->isWithinGeofence(Geo::getGeofence($hook["geofence"]))) {
                    continue;
                }
            }

            /*
                Check if the objective matches the objective requirements
                specified in the webhook's settings, if any.
            */
            if (count($hook["objectives"]) > 0) {
                $eq = $hook["filter-mode"]["objectives"] == "whitelist";
                $match = false;
                foreach ($hook["objectives"] as $req) {
                    if (Research::matches($objective, $objParams, $req["type"], $req["params"])) {
                        $match = true;
                        break;
                    }
                }
                if ($match !== $eq) continue;
            }
            /*
                Check if the reward matches the reward requirements specified in
                the webhook's settings, if any.
            */
            if (count($hook["rewards"]) > 0) {
                $eq = $hook["filter-mode"]["rewards"] == "whitelist";
                $match = false;
                foreach ($hook["rewards"] as $req) {
                    if (Research::matches($reward, $rewParams, $req["type"], $req["params"])) {
                        $match = true;
                        break;
                    }
                }
                if ($match !== $eq) continue;
            }

            /*
                Configure I18N with the language of the webhook.
            */
            __require("i18n");
            I18N::setLanguages(array($hook["language"] => "1"));

            /*
                Get the icon set selected for the webhook. If none is selected,
                fall back to the default icon set.
            */
            if ($hook["icons"] !== "") {
                $theme = Theme::getIconSet($hook["icons"]);
            } else {
                $theme = Theme::getIconSet();
            }

            /*
                Get the species icon set selected for the webhook. If none is
                selected, fall back to the default icon set.
            */
            if (isset($hook["species"]) && $hook["species"] !== "") {
                $spTheme = Theme::getSpeciesSet($hook["species"]);
            } else {
                $spTheme = Theme::getSpeciesSet();
            }
            if (isset($hook["show-species"])) {
                $useSpecies = $hook["show-species"];
            } else {
                $useSpecies = true;
            }

            /*
                Post the webhook.
            */
            __require("http");
            try {
                switch ($hook["type"]) {
                    case "json":
                        /*
                            Replace text replacement strings (e.g. <%COORDS%>)
                            in the webhook's payload body.
                        */
                        $body = replaceWebhookFields($currentUser, $poidata, $reportedTime, $theme, $spTheme, $useSpecies, $hook["body"], function($str) {
                            /*
                                String escaping for JSON
                                Convert to JSON string and remove leading and
                                trailing quotation marks.
                            */
                            return substr(json_encode($str), 1, -1);
                        });

                        $ch = curl_init($hook["target"]);
                        HTTP::setOptions($ch);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                            "Content-Type: application/json",
                            "Content-Length: ".strlen($body)
                        ));
                        curl_exec($ch);
                        curl_close($ch);
                        break;

                    case "telegram":
                        /*
                            Replace text replacement strings (e.g. <%COORDS%>)
                            in the webhook's payload body.
                        */
                        $body = replaceWebhookFields($currentUser, $poidata, $reportedTime, $theme, $spTheme, $useSpecies, $hook["body"], function($str) {
                            /*
                                Escape any special Markdown or HTML characters
                                in the webhook body according to the format of
                                the message being sent.
                            */
                            global $hook;
                            switch ($hook["options"]["parse-mode"]) {
                                case "md":
                                    // Markdown - escape \[*_`
                                    return preg_replace("/([\\\[\*_`])/", "\\\\\\1", $str);
                                case "html":
                                    // HTML - escape special HTML chars
                                    return htmlspecialchars($str, ENT_QUOTES);
                                default:
                                    // Plain text - do not escape strings
                                    return $str;
                            }
                        });

                        /*
                            Extract the Telegram group ID from the target URL.
                        */
                        $matches = array();
                        preg_match('/^tg:\/\/send\?to=(-\d+)$/', $hook["target"], $matches);

                        /*
                            Create an array to be POSTed to the Telegram API.
                        */
                        $postArray = array(
                            "chat_id" => $matches[1],
                            "text" => $body,
                            "disable_web_page_preview" => $hook["options"]["disable-web-page-preview"],
                            "disable_notification" => $hook["options"]["disable-notification"]
                        );
                        switch ($hook["options"]["parse-mode"]) {
                            case "md":
                                $postArray["parse_mode"] = "Markdown";
                                break;
                            case "html":
                                $postArray["parse_mode"] = "HTML";
                                break;
                        }
                        $postdata = json_encode($postArray);

                        __require("security");
                        $botToken = Security::decryptArray(
                            $hook["options"]["bot-token"],
                            "config",
                            "token"
                        );

                        $ch = curl_init("https://api.telegram.org/bot".
                            urlencode($botToken)."/sendMessage");
                        HTTP::setOptions($ch);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                            "Content-Type: application/json",
                            "Content-Length: ".strlen($postdata)
                        ));
                        curl_exec($ch);
                        curl_close($ch);
                        break;
                }
            } catch (Exception $e) {

            }
        }

        XHR::exitWith(204, null);

    } elseif (isset($patchdata["moveTo"])) {
        /*
            A POI is being moved.
        */
        if (!$currentUser->hasPermission("admin/pois/general")) {
            XHR::exitWith(403, array("reason" => "access_denied"));
        }

        /*
            Required fields are the POI ID and the new coordinates. Ensure that
            all of these fields are present in the received data.
        */
        $reqfields = array("moveTo");

        foreach ($reqfields as $field) {
            if (!isset($patchdata[$field])) {
                XHR::exitWith(400, array("reason" => "missing_fields"));
            }
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
            `moveTo` must be an arrays with keys defined for `latitude` and
            `longitude`, both of which must be numbers within valid bounds.
        */
        if (
            !is_array($patchdata["moveTo"]) ||
            !isset($patchdata["moveTo"]["latitude"]) ||
            !isset($patchdata["moveTo"]["longitude"]) ||
            !is_numeric($patchdata["moveTo"]["latitude"]) ||
            !is_numeric($patchdata["moveTo"]["longitude"])
        ) {
            XHR::exitWith(400, array("reason" => "invalid_data"));
        }

        $latitude = floatval($patchdata["moveTo"]["latitude"]);
        $longitude = floatval($patchdata["moveTo"]["longitude"]);
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
            If FreeField is configured to only accept POIs within a certain
            geofence boundary, and the POI is being moved to a location outside
            those bounds, there is no reason to allow the update.
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

    } elseif (isset($patchdata["renameTo"])) {
        /*
            A POI is being renamed.
        */
        if (!$currentUser->hasPermission("admin/pois/general")) {
            XHR::exitWith(403, array("reason" => "access_denied"));
        }

        /*
            Required fields are the POI ID and the new name. Ensure that all of
            these fields are present in the received data.
        */
        $reqfields = array("renameTo");

        foreach ($reqfields as $field) {
            if (!isset($patchdata[$field])) {
                XHR::exitWith(400, array("reason" => "missing_fields"));
            }
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
            `renameTo` must be a non-empty string.
        */
        $newName = strval($patchdata["renameTo"]);
        if ($newName == "") {
            XHR::exitWith(400, array("reason" => "missing_fields"));
        }

        /*
            Validity is verified from here on.

            Create a database update array.
        */
        $data = array(
            "updated_by" => $currentUser->getUserID(),
            "last_updated" => date("Y-m-d H:i:s"),
            "name" => $newName
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

    } elseif (isset($patchdata["resetResearch"]) && $patchdata["resetResearch"]) {
        /*
            A POI is having its research cleared.
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
            Validity is verified from here on.

            Create a database update array.
        */
        $data = array(
            "updated_by" => $currentUser->getUserID(),
            "last_updated" => date("Y-m-d H:i:s"),
            "objective" => "unknown",
            "obj_params" => json_encode(array()),
            "reward" => "unknown",
            "rew_params" => json_encode(array())
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
    }

    /*
        Invalid request.
    */
    XHR::exitWith(400, array("reason" => "missing_fields"));

} elseif ($_SERVER["REQUEST_METHOD"] === "DELETE") {
    /*
        DELETE request will delete the given POI.
    */
    $deletedata = json_decode(file_get_contents("php://input"), true);

    if (!$currentUser->hasPermission("admin/pois/general")) {
        XHR::exitWith(403, array("reason" => "access_denied"));
    }

    /*
        Required fields are the POI ID. Ensure that it is present in the
        received data.
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

} else {
    /*
        Method not implemented.
    */
    XHR::exitWith(405, array("reason" => "http_405"));
}

?>
