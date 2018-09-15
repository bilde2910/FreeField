<?php
/*
    This script handles all stages of GroupMe authentication.
*/

require_once("../../includes/lib/global.php");
__require("config");
__require("auth");

$service = "groupme";

if (!Auth::isProviderEnabled($service)) {
    header("HTTP/1.1 307 Temporary Redirect");
    header("Location: ".Config::getEndpointUri("/auth/login.php"));
    exit;
}

/*
    Configure the OAuth client with the client ID and secret, as well as a
    redirect URI. Request that the provider redirects the user back to this
    script, where stage II of the authentication phase can be performed. Stage
    II makes an authenticated call to `identEndpoint` to identify the user.
*/

$opts = array(
    "clientId" => Config::get("auth/provider/{$service}/client-id")->value(),
    "authEndpoint" => "https://oauth.groupme.com/oauth/authorize?client_id=",
    "identEndpoint" => "https://api.groupme.com/v3/users/me"
);

if (!isset($_GET["access_token"])) {
    /*
        AUTH STAGE I
        Client-side prompt

        Redirect user to then authentication provider. We get a URL from the
        OAuth options and redirect the user there.
    */
    header("HTTP/1.1 303 See Other");
    header("Location: ".$opts["authEndpoint"].urlencode($opts["clientId"]));
    exit;
}

/*
    Stage I success
    -> AUTH Stage II
    Server-side validation

    An access token was returned that can be used to interact with the API to
    verify the identity of the user.
*/

$httpOpts = array(
    "http" => array(
        "method" => "GET",
        "header" => "User-Agent: FreeField/".FF_VERSION." PHP/".phpversion()
    )
);

/*
    Set an error handler that catches HTTP errors from GroupMe's API.
*/
$context = stream_context_create($httpOpts);
set_error_handler(function($no, $str, $file, $line, $context) {
    if (0 === error_reporting()) {
        return false;
    }
    /*
        Stage II failure

        The OAuth endpoint returned an error response code. Kick the user back
        to the "failed to authenticate" page and prompt them to try again.
    */
    header("303 See Other");
    header("Location: ".Config::getEndpointUri("/auth/failed.php?provider={$service}"));
    exit;
}, E_WARNING);

$resp = null;
try {
    $resp = json_decode(file_get_contents(
        $opts["identEndpoint"]."?token=".$_GET["access_token"], false, $context
    ), true);
} catch (Exception $e) {
    /*
        Stage II failure

        The connection to the OAuth endpoint failed. Kick the user back to the
        "failed to authenticate" page and prompt them to try again.
    */
    header("303 See Other");
    header("Location: ".Config::getEndpointUri("/auth/failed.php?provider={$service}"));
    exit;
}

if ($resp === null || $resp["meta"]["code"] !== 200) {
    /*
        Stage II failure

        The returned user object is null or invalid. Kick the user back to the
        "failed to authenticate" page and prompt them to try again.
    */
    header("303 See Other");
    header("Location: ".Config::getEndpointUri("/auth/failed.php?provider={$service}"));
    exit;
}

$user = $resp["response"];

/*
    Stage II success
    -> AUTH Stage III
    Create session

    We have a user array representing the current user and their identity on
    GroupMe. Proceed to set the session.
*/

$approved = Auth::setAuthenticatedSession(
    "{$service}:".$user["id"],
    Config::get("auth/session-length")->value(),
    $user["name"],
    $user["name"]
);
header("HTTP/1.1 303 See Other");

/*
    Unapproved users should be redirected to a page explaining that
    their account has not yet been approved, and that they should
    contact an administrator to approve their account.
*/
if ($approved) {
    header("Location: ".Config::getEndpointUri("/"));
} else {
    header("Location: ".Config::getEndpointUri("/auth/approval.php"));
}
exit;

?>
