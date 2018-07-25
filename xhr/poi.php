<?php

require_once("../includes/lib/global.php");
__require("xhr");
__require("db");
__require("auth");
__require("geo");

function replaceWebhookFields($time, $theme, $body) {
    __require("research");
    __require("config");

    global $poidata;
    global $objective;
    global $objParams;
    global $reward;
    global $rewParams;

    $replaces = array(
        "POI" => $poidata["name"],
        "LAT" => $poidata["latitude"],
        "LNG" => $poidata["longitude"],
        "COORDS" => Geo::getLocationString($poidata["latitude"], $poidata["longitude"]),
        "OBJECTIVE" => Research::resolveObjective($objective, $objParams),
        "REWARD" => Research::resolveReward($reward, $rewParams),
        "REPORTER" => Auth::getCurrentUser()->getNickname()
    );

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

    switch (Config::get("map/provider/directions")) {
        case "bing":
            $replaces["NAVURL"] = "https://www.bing.com/maps?rtp=~pos." . urlencode($poidata["latitude"] . "_" . $poidata["longitude"] . "_" . $poidata["name"]);
            break;
        case "google":
            $replaces["NAVURL"] = "https://www.google.com/maps/dir/?api=1&destination=" . urlencode($poidata["latitude"] . "," . $poidata["longitude"]);
            break;
        case "here":
            $replaces["NAVURL"] = "https://share.here.com/r/mylocation/" . urlencode($poidata["latitude"] . "," . $poidata["longitude"]) . "?m=d&t=normal";
            break;
        case "mapquest":
            $replaces["NAVURL"] = "https://www.mapquest.com/directions/to/near-" . urlencode($poidata["latitude"] . "," . $poidata["longitude"]);
            break;
        case "waze":
            $replaces["NAVURL"] = "https://waze.com/ul?ll=" . urlencode($poidata["latitude"] . "," . $poidata["longitude"]) . "&navigate=yes";
            break;
        case "yandex":
            $replaces["NAVURL"] = "https://yandex.ru/maps?rtext=~" . urlencode($poidata["latitude"] . "," . $poidata["longitude"]);
            break;
    }

    // <%TIME(format)%>
    $matches = array();
    preg_match_all('/<%TIME\(([^\)]+)\)%>/', $body, $matches, PREG_SET_ORDER);
    for ($i = 0; $i < count($matches); $i++) {
        $body = preg_replace('/<%TIME\('.$matches[$i][1].'\)%>/', date($matches[$i][1], $time), $body, 1);
    }

    // <%COORDS(precision)%>
    $matches = array();
    preg_match_all('/<%COORDS\((\d+)\)%>/', $body, $matches, PREG_SET_ORDER);
    for ($i = 0; $i < count($matches); $i++) {
        $body = preg_replace('/<%COORDS\('.$matches[$i][1].'\)%>/', Geo::getLocationString($poidata["latitude"], $poidata["longitude"], intval($matches[$i][1])), $body, 1);
    }

    // <%I18N(token,arg1,arg2,...)%>
    $matches = array();
    preg_match_all('/<%I18N\(([^\),]+)(,([^\)]+))?\)%>/', $body, $matches, PREG_SET_ORDER);
    for ($i = 0; $i < count($matches); $i++) {
        if (count($matches[$i]) >= 4) {
            $args = array_merge(
                array($matches[$i][1]),
                explode(",", $matches[$i][3])
            );
            $body = preg_replace('/<%I18N\('.$matches[$i][1].$matches[$i][2].'\)%>/', call_user_func_array("I18N::resolveArgs", $args), $body, 1);
        } else {
            $body = preg_replace('/<%I18N\('.$matches[$i][1].'\)%>/', call_user_func_array("I18N::resolve", array($matches[$i][1])), $body, 1);
        }
    }

    foreach ($replaces as $key => $value) {
        $body = str_replace("<%{$key}%>", $value, $body);
    }

    return $body;
}

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    // List POIs
    if (!Auth::getCurrentUser()->hasPermission("access")) {
        XHR::exitWith(403, array("reason" => "xhr.failed.reason.access_denied"));
    }
    try {
        $pois = Geo::listPOIs();

        $poidata = array();

        foreach ($pois as $poi) {
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
        XHR::exitWith(500, array("reason" => "xhr.failed.reason.database_error"));
    }

} elseif ($_SERVER["REQUEST_METHOD"] === "PUT") {
    __require("config");

    // Add new POI
    if (!Auth::getCurrentUser()->hasPermission("submit-poi")) {
        XHR::exitWith(403, array("reason" => "xhr.failed.reason.access_denied"));
    }
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
        "created_by" => Auth::getCurrentUser()->getUserID(),
        "updated_by" => Auth::getCurrentUser()->getUserID(),
        "objective" => "unknown",
        "obj_params" => json_encode(array()),
        "reward" => "unknown",
        "rew_params" => json_encode(array())
    );

    if ($data["name"] == "") {
        XHR::exitWith(400, array("reason" => "poi.add.failed.reason.name_empty"));
    }

    if (!Geo::isWithinGeofence(Config::get("map/geofence"), $data["lat"], $data["lon"])) {
        XHR::exitWith(400, array("reason" => "poi.add.failed.reason.invalid_location"));
    }

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
    if (!Auth::getCurrentUser()->hasPermission("report-research")) {
        XHR::exitWith(403, array("reason" => "xhr.failed.reason.access_denied"));
    }

    $reportedTime = time();

    // Check that required data is present
    $reqfields = array("id", "objective", "reward");
    $patchdata = json_decode(file_get_contents("php://input"), true);

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

    $objective = $patchdata["objective"]["type"];
    $objParams = $patchdata["objective"]["params"];
    if (!Research::isObjectiveValid($objective, $objParams)) {
        XHR::exitWith(400, array("reason" => "xhr.failed.reason.invalid_data"));
    }

    $reward = $patchdata["reward"]["type"];
    $rewParams = $patchdata["reward"]["params"];
    if (!Research::isRewardValid($reward, $rewParams)) {
        XHR::exitWith(400, array("reason" => "xhr.failed.reason.invalid_data"));
    }

    // Data is valid from here on

    $data = array(
        "updated_by" => Auth::getCurrentUser()->getUserID(),
        "last_updated" => date("Y-m-d H:i:s"),
        "objective" => $objective,
        "obj_params" => json_encode($objParams),
        "reward" => $reward,
        "rew_params" => json_encode($rewParams)
    );

    try {
        $poi = Geo::getPOI($patchdata["id"]);
        if ($poi->isUpdatedToday() && !$poi->isResearchUnknown()) {
            if (!Auth::getCurrentUser()->hasPermission("overwrite-research")) {
                XHR::exitWith(403, array("reason" => "xhr.failed.reason.access_denied"));
            }
        }

        $db = Database::getSparrow();
        $db
            ->from(Database::getTable("poi"))
            ->where("id", $patchdata["id"])
            ->update($data)
            ->execute();

        $poidata = $db
            ->from(Database::getTable("poi"))
            ->where("id", $patchdata["id"])
            ->one();

    } catch (Exception $e) {
        XHR::exitWith(500, array("reason" => "xhr.failed.reason.database_error"));
    }

    // Call webhooks
    __require("config");
    __require("theme");
    __require("research");

    $hooks = Config::get("webhooks");
    if ($hooks === null) $hooks = array();

    foreach ($hooks as $hook) {
        if (!$hook["active"]) continue;

        if (isset($hook["geofence"])) {
            if (!$poi->isWithinGeofence($hook["geofence"])) {
                continue;
            }
        }

        foreach ($hook["objectives"] as $req) {
            $eq = $hook["filter-mode"]["objectives"] == "blacklist";
            if (Research::matches($objective, $objParams, $req["type"], $req["params"]) === $eq) {
                continue 2;
            }
        }
        foreach ($hook["rewards"] as $req) {
            $eq = $hook["filter-mode"]["rewards"] == "blacklist";
            if (Research::matches($reward, $rewParams, $req["type"], $req["params"]) === $eq) {
                continue 2;
            }
        }

        if ($hook["icons"] !== "") {
            $theme = Theme::getIconSet($hook["icons"]);
        } else {
            $theme = Theme::getIconSet();
        }

        $body = replaceWebhookFields($reportedTime, $theme, $hook["body"]);

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
                    $matches = array();
                    preg_match('/^tg:\/\/send\?to=(-\d+)$/', $hook["target"], $matches);
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
                    file_get_contents("https://api.telegram.org/bot".urlencode($hook["options"]["bot-token"])."/sendMessage", false, $context);
                    break;
            }
        } catch (Exception $e) {

        }
    }

    XHR::exitWith(204, null);
} else {
    XHR::exitWith(405, array("reason" => "xhr.failed.reason.http_405"));
}

?>
