<?php

require_once("../includes/lib/global.php");
__require("config");
__require("auth");
__require("xhr");

if (!Auth::getCurrentUser()->hasPermission("admin/hooks/general")) {
    XHR::exitWith(403, array("reason" => "xhr.failed.reason.access_denied"));
}

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    XHR::exitWith(405, array("reason" => "xhr.failed.reason.http_405"));
}

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
        XHR::exitWith(504, array("reason" => "xhr.failed.reason.timeout"));
    } else {
        XHR::exitWith(502, array("reason" => "xhr.failed.reason.upstream_failed"));
    }
}

if (!isset($data["ok"]) || $data["ok"] !== true) {
    XHR::exitWith(502, array("reason" => "xhr.failed.reason.upstream_failed"));
}

$groups = array();
foreach ($data["result"] as $result) {
    $msg = null;
    if (isset($result["message"])) $msg = $result["message"];
    if (isset($result["edited_message"])) $msg = $result["edited_message"];
    if (isset($result["channel_post"])) $msg = $result["channel_post"];
    if (isset($result["edited_channel_post"])) $msg = $result["edited_channel_post"];

    if (isset($msg["chat"])) {
        $chat = $msg["chat"];
        if ($chat["type"] != "private" && isset($chat["title"])) {
            $groups[$chat["id"]] = $chat["title"];
        }
    }
}

XHR::exitWith(200, array("groups" => $groups));

?>
