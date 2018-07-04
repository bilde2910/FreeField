<?php

require_once("../../includes/lib/global.php");
__require("config");
__require("auth");

$service = "telegram";

if (!Auth::isProviderEnabled($service)) {
    header("HTTP/1.1 307 Temporary Redirect");
    header("Location: ".Config::getEndpointUri("/auth/login.php"));
    exit;
}

if (!isset($_GET["hash"])) { ?>

<!DOCTYPE html>
<html>
    <head>
        <title>Authenticate with Telegram</title>
        <meta name="robots" content="noindex,nofollow">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <style type="text/css">
            * {
                font-family: sans-serif;
            }
            div {
                text-align: center;
            }
            h1 {
                font-size: 1.5em;
            }
        </style>
    </head>
    <body>
        <div>
            <h1>Authenticate with Telegram</h1>
            <p>Please click the button below to sign in using Telegram</p>
            <script async src="https://telegram.org/js/telegram-widget.js?4" data-telegram-login="<?php echo Config::get("auth/provider/{$service}/bot-username"); ?>" data-size="large" data-userpic="false" data-auth-url="<?php echo Config::getEndpointUri("/auth/oa2/telegram.php"); ?>"></script>
        </div>
    </body>
</html>

<?php exit; }

$fields = $_GET;
$hash = $_GET["hash"];
unset($fields["hash"]);
ksort($fields);

$urlfields = array();
foreach ($fields as $k => $v) {
    $urlfields[] = "{$k}={$v}";
}

$data = implode("\n", $urlfields);
$key = hash("SHA256", Config::get("auth/provider/{$service}/bot-token"), true);

$verify = hash_hmac("SHA256", $data, $key);

if ($verify !== $hash || time() - intval($_GET["auth_date"]) > 600) {
    header("303 See Other");
    header("Location: ".Config::getEndpointUri("/auth/failed.php?provider={$service}"));
    exit;
}

$userid = $_GET["id"];

try {
    $user = (isset($_GET["username"]) ? $_GET["username"] : null);
    if ($user === null) $user = (isset($_GET["first_name"]) && isset($_GET["last_name"]) ? $_GET["first_name"]." ".$_GET["last_name"] : null);
    if ($user === null) $user = "";

    $approved = Auth::setAuthenticatedSession("{$service}:".$userid, Config::get("auth/session-length"), $user);
    header("HTTP/1.1 303 See Other");
    if ($approved) {
        header("Location: ".Config::getEndpointUri("/"));
    } else {
        header("Location: ".Config::getEndpointUri("/auth/approval.php"));
    }
    exit;
} catch (Exception $e) {
    header("303 See Other");
    header("Location: ".Config::getEndpointUri("/auth/failed.php?provider={$service}"));
    exit;
}




?>
