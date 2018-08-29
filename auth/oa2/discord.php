<?php
/*
    This script handles all stages of Discord authentication.
*/

require_once("../../includes/lib/global.php");
__require("config");
__require("auth");

$service = "discord";

if (!Auth::isProviderEnabled($service)) {
    header("HTTP/1.1 307 Temporary Redirect");
    header("Location: ".Config::getEndpointUri("/auth/login.php"));
    exit;
}

__require("vendor");

/*
    Configure the OAuth2 provider for Discord with the client ID and secret,
    required to identify this FreeField instance. Request that Discord redirects
    the user back to this script, where stage II of the authentication phase can
    be performed.
*/
$provider = new \Wohali\OAuth2\Client\Provider\Discord([
    "clientId" => Config::get("auth/provider/{$service}/client-id"),
    "clientSecret" => Config::get("auth/provider/{$service}/client-secret"),
    "redirectUri" => Config::getEndpointUri("/auth/oa2/{$service}.php")
]);

if (!isset($_GET["code"])) {
    /*
        AUTH STAGE I
        Client-side prompt

        Redirect user to then authentication provider. We get a URL from the
        OAuth2 provider and redirect the user there.
    */
    $authUrl = $provider->getAuthorizationUrl(array(
        "scope" => array("identify")
    ));
    header("HTTP/1.1 307 Temporary Redirect");
    // CSRF mitigation
    setcookie("oa2-{$service}-state", $provider->getState(), 0, strtok($_SERVER["REQUEST_URI"], "?"));
    header("Location: {$authUrl}");
    exit;
} elseif (empty($_GET["state"]) || !isset($_COOKIE["oa2-{$service}-state"]) || $_GET["state"] !== $_COOKIE["oa2-{$service}-state"]) {
    /*
        Stage I CSRF failure

        Stage I ensures that CSRF protection is in place by setting a `state`
        parameter in the OAuth2 request, which is returned by Discord and can be
        checked against the state value stored by stage I in a cookie. If either
        are missing, or there is a mismatch, assume that a CSRF attack is taking
        place and abort the authentication request.
    */
    header("303 See Other");

    // Unset CSRF state cookie as it is no longer required
    setcookie("oa2-{$service}-state", "", time() - 3600, strtok($_SERVER["REQUEST_URI"], "?"));

    header("Location: ".Config::getEndpointUri("/auth/failed.php?provider={$service}"));
    exit;
} else {
    /*
        Stage I success
        -> AUTH Stage II
        Server-side validation

        The state parameter matches and an authorization code was returned that
        can be exchanged for an access token, which in turn lets us interact
        with the API to verify the identity of the user.
    */
    try {
        $token = $provider->getAccessToken("authorization_code", array(
            "code" => $_GET["code"]
        ));
        $user = $provider->getResourceOwner($token);

        /*
            Stage II success
            -> AUTH Stage III
            Create session

            If we get this far, Discord has confirmed that the user that
            authenticated is who they claim to be. Now, we can spawn a session
            for this user and attach it to the user's current browsing session,
            signing them in.
        */

        $approved = Auth::setAuthenticatedSession(
            "{$service}:".$user->getId(),
            Config::get("auth/session-length"),
            $user->getUsername()."#".$user->getDiscriminator(),
            $user->getUsername()
        );
        header("HTTP/1.1 303 See Other");

        // Unset CSRF state cookie as it is no longer required
        setcookie("oa2-{$service}-state", "", time() - 3600, strtok($_SERVER["REQUEST_URI"], "?"));

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
    } catch (Exception $e) {
        /*
            Stage II failure

            Discord could not verify that the authenticating user is who they
            claim to be. This could be for several reasons:

            - Discord servers may currently be down
            - The authenticating user may have tried to forge login credentials
            - The authorization code may for whatever reason no longer be valid

            In any case, the authentication attempt should be rejected and the
            user redirected back to a page explaining that authentication
            failed, prompting them to try again or try another provider.
        */

        header("303 See Other");

        // Unset CSRF state cookie as it is no longer required
        setcookie("oa2-{$service}-state", "", time() - 3600, strtok($_SERVER["REQUEST_URI"], "?"));

        header("Location: ".Config::getEndpointUri("/auth/failed.php?provider={$service}"));
        exit;
    }
}

?>
