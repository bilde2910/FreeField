<?php
/*
    Field research is being updated.
*/

/*
    When the user enters a body payload for webhooks, they may choose to use
    substitution tokens, such as <%COORDS%> or <%POI%>. These should be replaced
    with the proper dynamic values before the webhook payload is posted to the
    target URL.

    This function accepts a `$token` name and its `$args` arguments, along with
    a `Theme` instance `$theme` and `$spTheme` representing the icon set and
    species set selected for the webhook, and `$useSpecies`=true/false whether
    species icons should be displayed if possible.
*/
function replaceWebhookFields($tokenName, $tokenArgs, $poidata, $theme, $spTheme, $useSpecies) {
    __require("research");

    /*
        Fetch required POI details for convenience.
    */
    $objective = $poidata->getCurrentObjective()["type"];
    $objParams = $poidata->getCurrentObjective()["params"];
    $reward = $poidata->getCurrentReward()["type"];
    $rewParams = $poidata->getCurrentReward()["params"];

    /*
        Attempt to perform the replacement.
    */
    $replacement = null;
    switch (strtoupper($tokenName)) {
        /*
            Please consult the documentation for information about each of these
            substitution tokens.
        */

        case "OBJECTIVE":
            // <%OBJECTIVE%>
            $replacement = Research::resolveObjective($objective, $objParams);
            break;

        case "REWARD":
            // <%REWARD%>
            $replacement = Research::resolveReward($reward, $rewParams);
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
                count($rewParams["species"]) == 1
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
    return $replacement;
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

if (!$currentUser->hasPermission("report-research")) {
    XHR::exitWith(403, array("reason" => "access_denied"));
}

/*
    Obtain and lock a timestamp of when research was submitted by the user. This
    is required because multiple webhooks may be triggered, and if one webhook
    takes a long time to execute, there is a risk that the research would be
    reported with different timestamps for each triggered webhook.
    `$reportedTime` is set once the research is updated, and then re-used for
    each webhook, preventing this from happening.
*/
$reportedTime = time();

/*
    Required fields are the POI ID and the reported objective and reward. Ensure
    that all of these fields are present in the received data.
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
    `objective` and `reward` must both be arrays with keys defined for `type`
    and `params`. Params must additionally be an array or object. Validate the
    research objective first.
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
    If FreeField is configured to hide POIs that are out of POI geofence bounds,
    and the POI that is being updated is outside those bounds, there is no
    reason to allow the update since the user shouldn't be able to see the POI
    on the map in the first place to perform the update.
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
    permission is required to allow users to overwrite field research tasks.
    This is required in addition to the permission allowing users to submit any
    kind of field research in the first place.
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
        Re-fetch the newly created POI from the database. The information here
        is used to trigger webhooks for field research updates.
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
    Get a list of all webhooks and iterate over them to check eligibility of
    submissions.
*/
$hooks = Config::getRaw("webhooks");
if ($hooks === null) $hooks = array();
foreach ($hooks as $hook) {
    if (!$hook["active"]) continue;
    if ($hook["for"] !== "research") continue;

    /*
        Check that the POI is within the geofence of the webhook.
    */
    if (isset($hook["geofence"])) {
        if (!$poi->isWithinGeofence(Geo::getGeofence($hook["geofence"]))) {
            continue;
        }
    }

    /*
        Check if the objective matches the objective requirements specified in
        the webhook's settings, if any.
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
        Check if the reward matches the reward requirements specified in the
        webhook's settings, if any.
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
    I18N::changeLanguage($hook["language"]);

    /*
        Get the icon set selected for the webhook. If none is selected, fall
        back to the default icon set.
    */
    if ($hook["icons"] !== "") {
        $theme = Theme::getIconSet($hook["icons"]);
    } else {
        $theme = Theme::getIconSet();
    }

    /*
        Get the species icon set selected for the webhook. If none is selected,
        fall back to the default icon set.
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
    HTTP::postWebhook($hook, array(
        "cu" => $currentUser,
        "pd" => $poidata,
        "rt" => $reportedTime,
        "it" => $theme,
        "st" => $spTheme,
        "us" => $useSpecies
    ), function($token, $args, $data) {
        $repl = HTTP::replaceCommonWebhookFields(
            $token, $args
        );
        if ($repl == null) $repl = HTTP::replaceWebhookFieldsForReport(
            $token, $args, $data["cu"], $data["rt"]
        );
        if ($repl == null) $repl = HTTP::replaceWebhookFieldsForPOI(
            $token, $args, $data["pd"]
        );
        if ($repl == null) $repl = replaceWebhookFields(
            $token, $args,
            $data["pd"], $data["it"], $data["st"], $data["us"],
            $body, $escapeCallback
        );
        return $repl;
    });
}

XHR::exitWith(204, null);
?>
