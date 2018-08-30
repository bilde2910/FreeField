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
    A bot token or webhook ID is required to list the groups the token's or
    webhook's corresponding bot belongs to.
*/
if (!isset($_GET["token"]) && !isset($_GET["forId"])) {
    XHR::exitWith(400, array("reason" => "xhr.failed.reason.missing_fields"));
}

/*
    When the list of webhooks is loaded on the administration pages, the server
    ensures that all Telegram bot tokens in all webhooks are substituted with a
    password mask/placeholder value rather than having the bot tokens being sent
    in plaintext. For example, a webhook may have a Telegram bot token stored
    internally in the configuration file, but when that hook is presented on the
    webhooks page in the administration interface, the bot token is replaced in
    the HTML code with a random string, so that the bot token itself is never
    sent back to the client.

    The reason for this is that Telegram for some reason decided that bot tokens
    are valid for sending messages (as is the point of a webhook), but are also
    in scope to perform user authentication. The bot token is used to verify
    that the authentication parameters passed from Telegram actually originate
    from Telegram servers. Telegram uses an HMAC hash to perform this
    verification, where the secret key of the HMAC hash is created from the bot
    token. See https://core.telegram.org/widgets/login#checking-authorization.

    The reason Telegram uses the bot token for this purpose is that they assume
    the bot token will be kept secret. After all, only the bot developer and
    Telegram themselves would know this token. Hence, anyone with the bot token
    will be able to craft a valid, signed HMAC hash that can be used to
    authenticate an arbitrary user.

    FreeField allows using Telegram both for webhooks and for authentication. It
    is likely that many installations will re-use the same bot token for both
    purposes. If a user on such an installation has access to the webhooks
    administration interface, they would be able to fetch the bot token from
    registered Telegram webhooks, if the bot token was sent in plaintext. They
    could then use that bot token to sign authentication data as if it was
    signed by Telegram itself. The server would have no reason to suspect
    anything unusual was going on, and as such would approve the authentication
    request.

    Users with access to the webhook administration page would be able to
    exploit this vulnerability to forge a valid authentication of a higher
    privileged user. This could even result in the user being able to assign
    themselves to a higher permission group using the compromised account as a
    tool. By never sending the bot token back to the web browser under any
    circumstances, and instead sending a random string mask, this privilege
    escalation attack vector is eliminated.

    Not sending the bot token to the client causes another issue to arise. When
    a user requests to enumerate the Telegram groups a bot is in in order to
    select the correct target group for their webhook's messages, the bot token
    is required in order to identify and authorize the bot against Telegram's
    API. Since this token can't be supplied by the client, it must be supplied
    by the server instead. If the user has input a bot token manually on the
    webhooks user interface, we can of course use that token to request the
    bot's group memberships. If the user didn't specify a bot token for the
    current session, we can instead pass the ID of the webhook and have the
    enumeration script look up the bot token from the configuration file given
    the ID of the webhook the bot token is registered in.

    /admin/js/hooks.js handles this selection for us and automatically
    determines whether to send a bot token (if available) or whether to send the
    ID of the webhook instead.
*/
$token = null;
if (isset($_GET["token"])) {
    /*
        A bot token was passed. Use it directly.
    */
    $token = $_GET["token"];
} else {
    /*
        A webhook ID was passed. Look up the webhook from the configuration file
        and extract the bot token from the webhook.
    */
    $hooks = Config::get("webhooks");
    if ($hooks === null) $hooks = array();
    foreach ($hooks as $hook) {
        if ($hook["id"] == $_GET["forId"]) {
            if (isset($hook["options"]["bot-token"])) {
                $token = $hook["options"]["bot-token"];
            }
            break;
        }
    }
}

/*
    This clause is triggered if a webhook ID was passed to this script, but no
    webhook was found by that ID, or no bot token is defined for the webhook.
*/
if ($token === null) {
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
    $data = json_decode(file_get_contents(
        "https://api.telegram.org/bot".urlencode($token)."/getUpdates",
        false,
        $context
    ), true);
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
