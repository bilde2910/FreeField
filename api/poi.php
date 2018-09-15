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

/*
    When the user enters a body payload for webhooks, they may choose to use
    text replacement fields, such as <%COORDS%> or <%POI%>. These should be
    replaced with the proper dynamic values before the webhook payload is posted
    to the target URL.

    This function accepts a `$body` payload, a `Theme` instance `$theme`
    representing the icon set selected for the webhook, and the `$time`stamp on
    which the field research was reported by the user. The timestamp is required
    because multiple webhooks may be triggered, and if one webhook takes a long
    time to execute, there is a risk that the research would be reported with
    different timestamps for each triggered webhook. `$time` is set once as the
    research is updated, and then re-used for each webhook, preventing this from
    happening.
*/
function replaceWebhookFields($time, $theme, $body) {
    __require("research");
    __require("config");

    /*
        Fetch required POI details from the calling function.
    */
    global $poidata;
    global $objective;
    global $objParams;
    global $reward;
    global $rewParams;

    /*
        Simple find-and-replace substitutions, i.e. <%POI%>, <%COORDS%>, etc.
        All instances of <%<array_key>%> are replaced with the corresponding
        <array_value>.
    */
    $replaces = array(
        "POI" => $poidata["name"],
        "LAT" => $poidata["latitude"],
        "LNG" => $poidata["longitude"],
        "COORDS" => Geo::getLocationString($poidata["latitude"], $poidata["longitude"]),
        "OBJECTIVE" => Research::resolveObjective($objective, $objParams),
        "REWARD" => Research::resolveReward($reward, $rewParams),
        "REPORTER" => Auth::getCurrentUser()->getNickname()
    );

    /*
        Generate icon set URLs. These are <%OBJECTIVE_ICON%> and <%REWARD_ICON%>
        where each tag can accept parameters for marker format ("vector" for SVG
        and "raster" for PNG), as well as the theme variant ("light" or "dark").
    */
    $variants = array("dark", "light");
    foreach ($variants as $variant) {
        $theme->setVariant($variant);
        $icons = array(
            "OBJECTIVE_ICON(vector,{$variant})" => $theme->getIconUrl($objective),
            "OBJECTIVE_ICON(raster,{$variant})" => $theme->getRasterUrl($objective),
            "REWARD_ICON(vector,{$variant})" => $theme->getIconUrl($reward),
            "REWARD_ICON(raster,{$variant})" => $theme->getRasterUrl($reward),
        );
        $replaces = array_merge($replaces, $icons);
    }

    /*
        The <%NAVURL%> tag - a link to a navigation provider providing turn-
        based navigation to the given POI.
    */
    $replaces["NAVURL"] =
        str_replace("{%LAT%}", urlencode($poidata["latitude"]),
        str_replace("{%LON%}", urlencode($poidata["longitude"]),
        str_replace("{%NAME%}", urlencode($poidata["name"]),
            Geo::listNavigationProviders()[
                Config::get("map/provider/directions")->value()
            ]
        )));
    /*
        <%TIME(format)%>. `format` is a date format compatible with PHP
        `date()`. Please see https://secure.php.net/manual/en/function.date.php
        for a list of accepted format string components.
    */
    $matches = array();
    preg_match_all('/<%TIME\(([^\)]+)\)%>/', $body, $matches, PREG_SET_ORDER);
    for ($i = 0; $i < count($matches); $i++) {
        $body = preg_replace(
            '/<%TIME\('.$matches[$i][1].'\)%>/',
            date($matches[$i][1], $time),
            $body,
            1
        );
    }

    /*
        <%COORDS(precision)%>. This is in addition to the basic <%COORDS%> tag.
        This tag allows specifying the number of decimals to output to each
        coordinate value.
    */
    $matches = array();
    preg_match_all('/<%COORDS\((\d+)\)%>/', $body, $matches, PREG_SET_ORDER);
    for ($i = 0; $i < count($matches); $i++) {
        $body = preg_replace(
            '/<%COORDS\('.$matches[$i][1].'\)%>/',
            Geo::getLocationString(
                $poidata["latitude"],
                $poidata["longitude"],
                intval($matches[$i][1])
            ),
            $body,
            1
        );
    }

    /*
        <%NAVURL(provider)%>. This is in addition to the basic <%NAVURL%> tag.
        This tag allows specifying the directions provider to use when creating
        a navigation URL.
    */
    $matches = array();
    preg_match_all('/<%NAVURL\(([a-z]+)\)%>/', $body, $matches, PREG_SET_ORDER);
    for ($i = 0; $i < count($matches); $i++) {
        $navurl = "";
        $naviprov = Geo::listNavigationProviders();
        if (isset($naviprov[$matches[$i][1]])) {
            $navurl = $naviprov[$matches[$i][1]];
        }
        $navurl =
            str_replace("{%LAT%}", urlencode($poidata["latitude"]),
            str_replace("{%LON%}", urlencode($poidata["longitude"]),
            str_replace("{%NAME%}", urlencode($poidata["name"]),
                $navurl
            )));
        $body = preg_replace(
            '/<%NAVURL\('.$matches[$i][1].'\)%>/',
            $navurl,
            $body,
            1
        );
    }

    /*
        <%I18N(token,arg1,arg2,...)%>. A translated string tag. It accepts a
        `token` to be looked up in the localization files, and an optional
        number of arguments to be passed to the I18N function.

        For example, `<%I18N(webhook.objective)%>` will resolve the
        "webhook.objective" I18N token and substitute the tag with the localized
        string. `<%I18N(webhook.reported_by,<%REPORTER%>)%>` will resolve the
        "webhook.reported_by" I18N token, and replace the {%1} tag in that
        token's localized string with the nickname of the person who reported
        field research.
    */
    $matches = array();
    /*
        This regex query matches:

        ([^\),]+)
            An I18N token.

        (,([^\)]+))?
            An optional comma-delimited list of I18N arguments.
    */
    preg_match_all('/<%I18N\(([^\),]+)(,([^\)]+))?\)%>/', $body, $matches, PREG_SET_ORDER);
    for ($i = 0; $i < count($matches); $i++) {
        if (count($matches[$i]) >= 4) {
            /*
                If there are four or more matches, that means the tag matched an
                I18N tag with arguments supplied. Extract the I18N token and its
                arguments, and call `I18N::resolveArgs()` with those parameters.
            */
            $args = array_merge(
                array($matches[$i][1]),
                explode(",", $matches[$i][3])
            );
            $body = preg_replace(
                '/<%I18N\('.$matches[$i][1].$matches[$i][2].'\)%>/',
                call_user_func_array("I18N::resolveArgs", $args),
                $body,
                1
            );
        } else {
            /*
                If there are fewer than four matches, replace a plain I18N token
                without arguments instead.
            */
            $body = preg_replace(
                '/<%I18N\('.$matches[$i][1].'\)%>/',
                call_user_func_array("I18N::resolve", array($matches[$i][1])),
                $body,
                1
            );
        }
    }

    /*
        Apply all the replacement tokens.
    */
    foreach ($replaces as $key => $value) {
        $body = str_replace("<%{$key}%>", $value, $body);
    }

    return $body;
}

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    /*
        GET request will list all available POIs.
    */
    if (!Auth::getCurrentUser()->hasPermission("access")) {
        XHR::exitWith(403, array("reason" => "access_denied"));
    }
    try {
        $pois = Geo::listPOIs();
        $geofence = Config::get("map/geofence/geofence")->value();

        $poidata = array();

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
                Add the POI to the list of returned POIs.
            */
            $poidata[] = array(
                "id" => intval($poi->getID()),
                "name" => $poi->getName(),
                "latitude" => $poi->getLatitude(),
                "longitude" => $poi->getLongitude(),
                "objective" => $poi->getCurrentObjective(),
                "reward" => $poi->getCurrentReward(),
                "updated" => array(
                    "on" => $poi->getLastUpdatedTime(),
                    "by" => $poi->getLastUser()->getUserID()
                )
            );
        }

        XHR::exitWith(200, array("pois" => $poidata));
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
    if (!Auth::getCurrentUser()->hasPermission("submit-poi")) {
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
        "created_by" => Auth::getCurrentUser()->getUserID(),
        "updated_by" => Auth::getCurrentUser()->getUserID(),
        "objective" => "unknown",
        "obj_params" => json_encode(array()),
        "reward" => "unknown",
        "rew_params" => json_encode(array())
    );

    /*
        If any of the users are null, unset the values as they default to null.
        Sparrow does not handle null values properly.
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
        $db = Database::getSparrow();
        $db
            ->from(Database::getTable("poi"))
            ->insert($data)
            ->execute();

        /*
            Re-fetch the newly created POI from the database and return details
            about the POI back to the submitting client.
        */
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
        XHR::exitWith(500, array("reason" => "database_error"));
    }

} elseif ($_SERVER["REQUEST_METHOD"] === "PATCH") {
    /*
        PATCH request will update the field research that is currently active on
        the POI.
    */
    if (!Auth::getCurrentUser()->hasPermission("report-research")) {
        XHR::exitWith(403, array("reason" => "access_denied"));
    }

    /*
        Obtain and lock a timestamp of when research was submitted by the user.
    */
    $reportedTime = time();

    /*
        Required fields are the POI ID and the reported objective and reward.
        Ensure that all of these fields are present in the received data.
    */
    $reqfields = array("id", "objective", "reward");
    $patchdata = json_decode(file_get_contents("php://input"), true);

    foreach ($reqfields as $field) {
        if (!isset($patchdata[$field])) {
            XHR::exitWith(400, array("reason" => "missing_fields"));
        }
    }

    /*
        `objective` and `reward` must both be arrays with keys defined for
        `type` and `params`. Params must additionally be an array or object.
    */
    if (
        !is_array($patchdata["objective"]) ||
        !isset($patchdata["objective"]["type"]) ||
        !isset($patchdata["objective"]["params"]) ||
        !is_array($patchdata["objective"]["params"])
    ) {
        XHR::exitWith(400, array("reason" => "invalid_data"));
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
        "updated_by" => Auth::getCurrentUser()->getUserID(),
        "last_updated" => date("Y-m-d H:i:s"),
        "objective" => $objective,
        "obj_params" => json_encode($objParams),
        "reward" => $reward,
        "rew_params" => json_encode($rewParams)
    );

    try {
        /*
            If FreeField is configured to hide POIs that are out of POI geofence
            bounds, and the POI that is being updated is outside those bounds,
            there is no reason to allow the update since the user shouldn't be
            able to see the POI on the map in the first place to perform the
            update.
        */
        $poi = Geo::getPOI($patchdata["id"]);
        $geofence = Config::get("map/geofence/geofence")->value();

        if (
            Config::get("map/geofence/hide-outside")->value() &&
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
            if (!Auth::getCurrentUser()->hasPermission("overwrite-research")) {
                XHR::exitWith(403, array("reason" => "access_denied"));
            }
        }

        $db = Database::getSparrow();
        $db
            ->from(Database::getTable("poi"))
            ->where("id", $patchdata["id"])
            ->update($data)
            ->execute();

        /*
            Re-fetch the newly created POI from the database. The information
            here is used to trigger webhooks for field research updates.
        */
        $poidata = $db
            ->from(Database::getTable("poi"))
            ->where("id", $patchdata["id"])
            ->one();

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

        /*
            Check that the POI is within the geofence of the webhook.
        */
        if (isset($hook["geofence"])) {
            if (!$poi->isWithinGeofence(Geo::getGeofence($hook["geofence"]))) {
                continue;
            }
        }

        /*
            Check if the objective matches the objective requirements specified
            in the webhook's settings, if any.
        */
        $eq = $hook["filter-mode"]["objectives"] == "whitelist";
        $match = false;
        foreach ($hook["objectives"] as $req) {
            if (Research::matches($objective, $objParams, $req["type"], $req["params"])) {
                $match = true;
                break;
            }
        }
        if ($match !== $eq) continue;
        /*
            Check if the reward matches the reward requirements specified in the
            webhook's settings, if any.
        */
        $eq = $hook["filter-mode"]["rewards"] == "whitelist";
        $match = false;
        foreach ($hook["rewards"] as $req) {
            if (Research::matches($reward, $rewParams, $req["type"], $req["params"])) {
                $match = true;
                break;
            }
        }
        if ($match !== $eq) continue;

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
            Replace text replacement strings (e.g. <%COORDS%>) in the webhook's
            payload body.
        */
        $body = replaceWebhookFields($reportedTime, $theme, $hook["body"]);

        /*
            Post the webhook.
        */
        try {
            switch ($hook["type"]) {
                case "json":
                    $opts = array(
                        "http" => array(
                            "method" => "POST",
                            "header" => "User-Agent: FreeField/".FF_VERSION." PHP/".phpversion()."\r\n".
                                        "Content-Type: application/json\r\n".
                                        "Content-Length: ".strlen($body),
                            "content" => $body
                        )
                    );
                    $context = stream_context_create($opts);
                    file_get_contents($hook["target"], false, $context);
                    break;

                case "telegram":
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

                    $opts = array(
                        "http" => array(
                            "method" => "POST",
                            "header" => "User-Agent: FreeField/".FF_VERSION." PHP/".phpversion()."\r\n".
                                        "Content-Type: application/json\r\n".
                                        "Content-Length: ".strlen($postdata),
                            "content" => $postdata
                        )
                    );
                    $context = stream_context_create($opts);
                    file_get_contents(
                        "https://api.telegram.org/bot".
                            urlencode($hook["options"]["bot-token"])."/sendMessage",
                        false,
                        $context
                    );
                    break;
            }
        } catch (Exception $e) {

        }
    }

    XHR::exitWith(204, null);
} else {
    /*
        Method not implemented.
    */
    XHR::exitWith(405, array("reason" => "http_405"));
}

?>
