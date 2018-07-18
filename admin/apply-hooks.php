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
$hookTypes = array("json");

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
        }
    }

    if ($hook["target"] !== $data["target"]) {
        $url = $data["target"];

        if (preg_match("/^https?\:\/\/?/", $url)) {
            $hook["target"] = $url;
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
        $bodyjson = json_decode($body);
        if (json_last_error() === JSON_ERROR_NONE) {
            $hook["body"] = $body;
        }
    }

    if ($hook["icons"] !== $data["iconSet"]) {
        $hook["icons"] = $data["iconSet"];
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

    $hooks[$hookid] = $hook;
}

$hooklist = array_values($hooks);
Config::set(array("webhooks" => $hooklist));

header("HTTP/1.1 303 See Other");
header("Location: {$returnpath}");

?>
