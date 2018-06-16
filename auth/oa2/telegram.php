<?php

require_once("../../includes/lib/global.php");
__require("config");

$service = "telegram";

$require_config = array(
    "auth/provider/{$service}/bot-username",
    "auth/provider/{$service}/bot-token"
);
if (!Config::get("auth/provider/{$service}/enabled") || Config::ifAny($require_config, null)) {
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
    __require("auth");
    Auth::setAuthenticatedSession("{$service}:".$userid, Config::get("auth/session-length"));
    
    header("HTTP/1.1 303 See Other");
    header("Location: ".Config::getEndpointUri("/"));
    exit;
} catch (Exception $e) {
    header("303 See Other");
    header("Location: ".Config::getEndpointUri("/auth/failed.php?provider={$service}"));
    exit;
}




?>
