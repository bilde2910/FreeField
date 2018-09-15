<?php
/*
    This file handles submission of webhook changes from the administration
    interface.
*/

require_once("../includes/lib/global.php");
__require("config");
__require("auth");
__require("db");
__require("research");
__require("security");

$returnpath = "./?d=hooks";

/*
    As this script is for submission only, only POST is supported. If a user
    tries to GET this page, they should be redirected to the configuration UI
    where they can make their desired changes.
*/
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("HTTP/1.1 303 See Other");
    header("Location: {$returnpath}");
    exit;
}

/*
    Perform CSRF validation.
*/
if (!Security::validateCSRF()) {
    header("HTTP/1.1 303 See Other");
    header("Location: {$returnpath}");
    exit;
}

/*
    If the requesting user does not have permission to make changes here, they
    should be kicked out.
*/
if (!Auth::getCurrentUser()->hasPermission("admin/hooks/general")) {
    header("HTTP/1.1 303 See Other");
    header("Location: {$returnpath}");
    exit;
}

/*
    The webhooks list we get from the configuration file contains a list of
    webhooks in an indexed array. We'll convert it to an associative array where
    the webhook ID is the key, to make it easier to fetch the webhook details
    for a hook given its ID. This is because the updates POSTed from the client
    contains changes where the webhook ID is the identifier for the hooks whose
    settings have changed.

    There may not be any webhooks defined yet, in which case the returned list
    of webhooks will be `null`. In that case, create an empty webhook array to
    populate with new webhooks.
*/
$hooklist = Config::getRaw("webhooks");
if ($hooklist === null) $hooklist = array();
$hooks = array();
foreach ($hooklist as $hook) {
    $hooks[$hook["id"]] = $hook;
}

/*
    Store valid values for objective/reward filter types and webhook types here
    for easier changes, should this change in the future.
*/
$filterModes = array("whitelist", "blacklist");
$hookTypes = array("json", "telegram");
$tgBodyFormats = array("txt", "md", "html");

foreach ($_POST as $postid => $data) {
    /*
        Ensure that the POST field we're working on now is a webhook change
        field. These all have field names in the format "hook_<hookID>". If this
        matches, extract the webhook ID from the field name.
    */
    if (strlen($postid) < 1 || substr($postid, 0, 5) !== "hook_") continue;
    $hookid = substr($postid, 5);

    /*
        If a webhook with the given ID does not exist, create a new one and
        populate it with default values.
    */
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

    /*
        Handle actions such as webhook deletion, disabling and enabling hooks.
    */
    if ($data["action"] === "delete") {
        unset($hooks[$hookid]);
        continue;
    } elseif ($data["action"] === "disable") {
        $hook["active"] = false;
    } elseif ($data["action"] === "enable") {
        $hook["active"] = true;
    }

    /*
        This is where updates to the webhook fields are handled. Each field
        undergoes validation to make sure that the data that is set is valid
        before it is saved to the configuration file.
    */

    // Webhook type, i.e. JSON or Telegram
    if ($hook["type"] !== $data["type"]) {
        $type = $data["type"];
        if (in_array($type, $hookTypes)) {
            $hook["type"] = $type;
        } else {
            continue;
        }
    }

    // Webhook target, i.e. HTTP or Telegram URL
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

    // Language that is used in the webhook
    if ($hook["language"] !== $data["lang"]) {
        $lang = $data["lang"];
        /*
            This regex query matches a language code in the forms:

              - <ISO 639-1> (i.e. language code)
              - <ISO 639-1>-<ISO 3166> (i.e. language + country code)
        */
        if (preg_match("/^[a-z]{2}(-[A-Z]{2})?$/", $lang)) {
            $hook["language"] = $lang;
        }
    }

    // The body data posted by the webhook
    if ($hook["body"] !== $data["body"]) {
        /*
            As this field can have several different formats, validation is done
            client-side. (If the body is invalid, that is really the site
            operator's problem because their webhooks simply won't work. It
            cannot damage the rest of the FreeField installation in any way.)
        */
        $body = $data["body"];
        $hook["body"] = $body;
    }

    // The icon set used in the webhook
    if ($hook["icons"] !== $data["iconSet"]) {
        $hook["icons"] = $data["iconSet"];
    }

    /*
        Filter modes for objectives and rewards. This field will not be set if
        the selection boxes for these are `disabled` on the client (happens when
        there are no objective/reward filters on a webhook).
    */
    if (
        isset($hook["filter-mode"]["objectives"]) &&
        $hook["filter-mode"]["objectives"] !== $data["filterModeObjective"]
    ) {
        $mode = $data["filterModeObjective"];
        if (in_array($mode, $filterModes)) {
            $hook["filter-mode"]["objectives"] = $mode;
        }
    }

    if (
        isset($hook["filter-mode"]["rewards"]) &&
        $hook["filter-mode"]["rewards"] !== $data["filterModeReward"]
    ) {
        $mode = $data["filterModeReward"];
        if (in_array($mode, $filterModes)) {
            $hook["filter-mode"]["rewards"] = $mode;
        }
    }

    $type = $data["type"];
    if ($type == "telegram") {
        /*
            Telegram specific settings. Telegram webhooks have extra options,
            such as whether the messages called should trigger notifications,
            and the format that should be used when posting messages to a
            Telegram group (i.e. text/Markdown/HTML).

            If these options aren't set, initialize them with defaults.
        */
        if (!isset($hook["options"])) {
            $hook["options"] = array(
                "bot-token" => "",
                "parse-mode" => "txt",
                "disable-web-page-preview" => false,
                "disable-notification" => false
            );
        }

        /*
            Process updates to the Telegram-specific webhook fields from here.
        */

        // Whether or not link previews should be disabled
        $hook["options"]["disable-web-page-preview"] = isset(
            $data["tg"]["disable_web_page_preview"]
        );

        // Whether notifications should be pushed for messages by this webhook
        $hook["options"]["disable-notification"] = isset(
            $data["tg"]["disable_notification"]
        );

        // The Telegram bot token
        $botToken = Security::decryptArray(
            $hook["options"]["bot-token"],
            "config",
            "token"
        );
        if ($botToken !== $data["tg"]["bot_token"]) {
            $botToken = $data["tg"]["bot_token"];
            /*
                This regex query matches a Telegram bot token
            */
            if (preg_match("/^\d+:[A-Za-z\d]+$/", $botToken)) {
                $hook["options"]["bot-token"] = Security::encryptArray(
                    array("token" => $botToken), "config"
                );
            }
        }

        // The parse mode used for the message body text
        if ($hook["options"]["parse-mode"] !== $data["tg"]["parse_mode"]) {
            $parseMode = $data["tg"]["parse_mode"];
            if (in_array($parseMode, $tgBodyFormats)) {
                $hook["options"]["parse-mode"] = $parseMode;
            }
        }
    }

    /*
        Handle and update objective filters for the webhook. The `objective`
        field is an array with the structure:

            $objectives[objectiveID]["type"] -> Objective type
            $objectives[objectiveID]["params"] -> Parameters as JSON

        The objective ID exists solely to separate the objectives so that they
        can be looped over. They are randomly generated on the client and is
        never stored anywhere.

        Objectives are updated in the following way:
            1.  The list of objectives is cleared completely.
            2.  We loop over every objective with a unique ID in the list of
                objectives provided from the client.
            3.  The validity of each objective is checked.
            4.  If the objective and its parameters are valid, the objective
                is added to the list of objective filters in the `$objectives`
                array.
            5.  Continue step 3 and 4 until all objectives have been processed.

        Objective filters that have been deleted on the client will not be
        present in the objective list, and since the list of objectives is
        cleared in step 1 and then rebuilt, deleted objectives are never added
        back to the array.
    */
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

    /*
        Reward filters are handled in the same way as objective filters. Please
        see the above comment block for detailed information.
    */
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

    /*
        Overwrite the objective and reward filter lists in the webhook with
        those that were built above.
    */
    $hook["objectives"] = $objectives;
    $hook["rewards"] = $rewards;

    /*
        Geofences are parsed and validated by GeofenceOption in
        /includes/config/types.php. If the geofence is null (i.e. no fencing
        should be done), the `geofence` option of the webhook should be removed.
        Otherwise, if it is valid and not null, the fence should be saved with
        the data parsed in GeofenceOption.
    */
    $fenceOpt = new GeofenceOption();
    $fence = $fenceOpt->parseValue($data["geofence"]);
    $valid = $fenceOpt->isValid($fence);

    if (isset($hook["geofence"]) && $valid && $fence === null) {
        unset($hook["geofence"]);
    } elseif ($valid) {
        $hook["geofence"] = $fence;
    }

    /*
        Save the webhook.
    */
    $hooks[$hookid] = $hook;
}

/*
    Convert the associative `$hooks` array back into an indexed array before
    saving it to the configuration file.
*/
$hooklist = array_values($hooks);
Config::set(array("webhooks" => $hooklist));

header("HTTP/1.1 303 See Other");
header("Location: {$returnpath}");

?>
