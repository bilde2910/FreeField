<?php

require_once("../includes/lib/global.php");
__require("config");
__require("auth");
__require("db");
__require("research");

$returnpath = "./?d=hooks";

if (!Auth::getCurrentUser()->hasPermission("admin/hooks/general")) {
    header("HTTP/1.1 303 See Other");
    header("Location: {$returnpath}");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("HTTP/1.1 303 See Other");
    header("Location: {$returnpath}");
    exit;
}

$hooklist = Config::get("webhooks");
if ($hooklist === null) $hooklist = array();
$hooks = array();

foreach ($hooklist as $hook) {
    $hooks[$hook["id"]] = $hook;
}

$filterModes = array("whitelist", "blacklist");
$hookTypes = array("json", "telegram");

foreach ($_POST as $postid => $data) {
    if (strlen($postid) < 1 || substr($postid, 0, 5) !== "hook_") continue;
    $hookid = substr($postid, 5);

    if (!isset($hooks[$hookid])) {
        $hooks[$hookid] = array(
            "id" => $hookid,
            "type" => "",
            "target" => "",
            "active" => true,
            "language" => "en-US",
            "icons" => "",
            "body" => "",
            "filter-mode" => array(
                "objectives" => "whitelist",
                "rewards" => "whitelist"
            ),
            "objectives" => array(),
            "rewards" => array()
        );
    }

    $hook = $hooks[$hookid];

    if ($data["action"] === "delete") {
        unset($hooks[$hookid]);
        continue;
    } elseif ($data["action"] === "disable") {
        $hook["active"] = false;
    } elseif ($data["action"] === "enable") {
        $hook["active"] = true;
    }

    if ($hook["type"] !== $data["type"]) {
        $type = $data["type"];
        if (in_array($type, $hookTypes)) {
            $hook["type"] = $type;
        } else {
            continue;
        }
    }

    if ($hook["target"] !== $data["target"]) {
        $url = $data["target"];

        if ($hook["type"] === "telegram") {
            if (preg_match("/^tg\:\/\/send\?to=?/", $url)) {
                $hook["target"] = $url;
            }
        } else {
            if (preg_match("/^https?\:\/\//", $url)) {
                $hook["target"] = $url;
            }
        }
    }

    if ($hook["language"] !== $data["lang"]) {
        $lang = $data["lang"];
        if (preg_match("/^[a-z]{2}(-[A-Z]{2})?$/", $lang)) {
            $hook["language"] = $lang;
        }
    }

    if ($hook["body"] !== $data["body"]) {
        $body = $data["body"];
        $hook["body"] = $body;
    }

    if ($hook["icons"] !== $data["iconSet"]) {
        $hook["icons"] = $data["iconSet"];
    }

    $type = $data["type"];
    if ($type == "telegram") {
        if (!isset($hook["options"])) {
            $hook["options"] = array(
                "bot-token" => "",
                "parse-mode" => "text",
                "disable-web-page-preview" => false,
                "disable-notification" => false
            );
        }

        $hook["options"]["disable-web-page-preview"] = isset($data["tg"]["disable_web_page_preview"]);
        $hook["options"]["disable-notification"] = isset($data["tg"]["disable_notification"]);

        if ($hook["options"]["bot-token"] !== $data["tg"]["bot_token"]) {
            $botToken = $data["tg"]["bot_token"];
            $hook["options"]["bot-token"] = $botToken;
        }

        if ($hook["options"]["parse-mode"] !== $data["tg"]["parse_mode"]) {
            $parseMode = $data["tg"]["parse_mode"];
            $hook["options"]["parse-mode"] = $parseMode;
        }
    }

    if (isset($hook["filter-mode"]["objectives"]) && $hook["filter-mode"]["objectives"] !== $data["filterModeObjective"]) {
        $mode = $data["filterModeObjective"];
        if (in_array($mode, $filterModes)) {
            $hook["filter-mode"]["objectives"] = $mode;
        }
    }

    if (isset($hook["filter-mode"]["rewards"]) && $hook["filter-mode"]["rewards"] !== $data["filterModeReward"]) {
        $mode = $data["filterModeReward"];
        if (in_array($mode, $filterModes)) {
            $hook["filter-mode"]["rewards"] = $mode;
        }
    }

    $objectives = array();
    if (isset($data["objective"]) && is_array($data["objective"])) {
        foreach ($data["objective"] as $id => $value) {
            if (!isset($value["type"]) || !isset($value["params"])) continue;
            $type = $value["type"];
            $params = json_decode($value["params"], true);
            if ($params === null) continue;

            if (Research::isObjectiveValid($type, $params)) {
                $objectives[] = array(
                    "type" => $type,
                    "params" => $params
                );
            }
        }
    }

    $rewards = array();
    if (isset($data["reward"]) && is_array($data["reward"])) {
        foreach ($data["reward"] as $id => $value) {
            if (!isset($value["type"]) || !isset($value["params"])) continue;
            $type = $value["type"];
            $params = json_decode($value["params"], true);
            if ($params === null) continue;

            if (Research::isRewardValid($type, $params)) {
                $rewards[] = array(
                    "type" => $type,
                    "params" => $params
                );
            }
        }
    }

    $hook["objectives"] = $objectives;
    $hook["rewards"] = $rewards;

    $fenceOpt = new GeofenceOption();
    $fence = $fenceOpt->parseValue($data["geofence"]);
    $valid = $fenceOpt->isValid($fence);

    if (isset($hook["geofence"]) && $valid && $fence === null) {
        unset($hook["geofence"]);
    } elseif ($valid) {
        $hook["geofence"] = $fence;
    }

    $hooks[$hookid] = $hook;
}

$hooklist = array_values($hooks);
Config::set(array("webhooks" => $hooklist));

header("HTTP/1.1 303 See Other");
header("Location: {$returnpath}");

?>
