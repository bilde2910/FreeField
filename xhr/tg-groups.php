<?php
/*
    This XHR script lists all groups a particular Telegram bot is a member of.
    It is used to enumerate and select a target group for Telegram webhooks on
    the administration pages. This script exists because there is no easy way
    for users to get the ID of a group using the Telegram application. We have
    to query the API for this information. Blame Telegram for this poor
    implementation.

    See https://stackoverflow.com/q/32423837 for more information.
*/

require_once("../includes/lib/global.php");
__require("config");
__require("auth");
__require("xhr");

/*
    Users must have access to the webhook administration page, where this script
    is called, in order to run this script.
*/
if (!Auth::getCurrentUser()->hasPermission("admin/hooks/general")) {
    XHR::exitWith(403, array("reason" => "xhr.failed.reason.access_denied"));
}

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    XHR::exitWith(405, array("reason" => "xhr.failed.reason.http_405"));
}

/*
    A bot token is required to list the groups the token's corresponding bot
    belongs to.
*/
if (!isset($_GET["token"])) {
    XHR::exitWith(400, array("reason" => "xhr.failed.reason.missing_fields"));
}

$opts = array(
    "http" => array(
        "method" => "GET",
        "header" => "User-Agent: FreeField/".FF_VERSION." PHP/".phpversion()."\r\n".
                    "Accept: application/json",
        "timeout" => 15.0
    )
);
$context = stream_context_create($opts);
$time = time();

/*
    Gracefully handle upstream errors.
*/
set_error_handler(function($no, $str, $file, $line, $context) {
    if (0 === error_reporting()) {
        return false;
    }
    XHR::exitWith(502, array("reason" => "xhr.failed.reason.upstream_failed"));
}, E_WARNING);

try {
    $data = json_decode(file_get_contents("https://api.telegram.org/bot".urlencode($_GET["token"])."/getUpdates", false, $context), true);
} catch (Exception $e) {
    if (time() >= ($time + 14)) {
        /*
            15 second timeout exceeded.
        */
        XHR::exitWith(504, array("reason" => "xhr.failed.reason.timeout"));
    } else {
        /*
            Telegram servers don't like our request.
        */
        XHR::exitWith(502, array("reason" => "xhr.failed.reason.upstream_failed"));
    }
}

/*
    Check for errors in the returned data.
*/
if (!isset($data["ok"]) || $data["ok"] !== true) {
    XHR::exitWith(502, array("reason" => "xhr.failed.reason.upstream_failed"));
}

$groups = array();
foreach ($data["result"] as $result) {
    $msg = null;
    /*
        Get the `message` object. This can be in one of many keys. Look for the
        object in any of the keys below. The value is an array that contains the
        chat ID.
    */
    if (isset($result["message"])) $msg = $result["message"];
    if (isset($result["edited_message"])) $msg = $result["edited_message"];
    if (isset($result["channel_post"])) $msg = $result["channel_post"];
    if (isset($result["edited_channel_post"])) $msg = $result["edited_channel_post"];

    /*
        Get the `chat` component of the message, which contains the chat ID.
    */
    if (isset($msg["chat"])) {
        $chat = $msg["chat"];
        /*
            Ensure that the chat is not a private chat.
        */
        if ($chat["type"] != "private" && isset($chat["title"])) {
            $groups[$chat["id"]] = $chat["title"];
        }
    }
}

XHR::exitWith(200, array("groups" => $groups));

?>
