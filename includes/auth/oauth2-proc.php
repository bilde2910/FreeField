<?php
/*
    This script is included from OAuth2 authentication scripts and performs the
    OAuth2 handshake and verification against the service.

    The following variables must be set by the invoking script:

    $service
        A string identifying the OAuth2 service provider (for example, "discord"
        for Discord authentication).

    $opts
        An options array with URLs and parameters for the OAuth2 session. It may
        look like this for Discord authentication, for example:

        $opts = array(
            "clientId" => Config::get("auth/provider/{$service}/client-id")->value(),
            "clientSecret" => Config::get("auth/provider/{$service}/client-secret")->value(),
            "redirectUri" => Config::getEndpointUri("/auth/oa2/{$service}.php"),
            "authEndpoint" => "https://discordapp.com/oauth2/authorize",
            "tokenEndpoint" => "https://discordapp.com/api/v6/oauth2/token",
            "identEndpoint" => "https://discordapp.com/api/v6/users/@me",
            "params" => array(
                "scope" => "identify",
                "state" => $state = bin2hex(openssl_random_pseudo_bytes(16))
            )
        );
*/

if (!isset($service) || !isset($opts)) {
    throw new Exception('$service and $opts must be defined to use the OAuth2 processor!');
    exit;
}

__require("vendor/oauth2");
__require("vendor/oauth2/authcode");

$client = new OAuth2\Client($opts["clientId"], $opts["clientSecret"], $opts["clientAuth"]);

if (!isset($_GET["code"])) {
    /*
        AUTH STAGE I
        Client-side prompt

        Redirect user to then authentication provider. We get a URL from the
        OAuth2 client, add the required parameters, and redirect the user there.
    */
    $authUrl = $client->getAuthenticationUrl($opts["authEndpoint"], $opts["redirectUri"])
             . "&" . http_build_query($opts["params"]);

    header("HTTP/1.1 307 Temporary Redirect");

    /*
        CSRF mitigation. We generate a state string that is returned to this
        script by the OAuth2 service upon successful authentication. If the
        state string returned does not match the state string we created, then
        the authentication may be invoked by a CSRF attack and can be rejected.
    */
    setcookie(
        "oa2-{$service}-state", $state, 0,
        strtok($_SERVER["REQUEST_URI"], "?")
    );
    header("Location: {$authUrl}");
    exit;
} elseif (
    empty($_GET["state"]) ||
    !isset($_COOKIE["oa2-{$service}-state"]) ||
    $_GET["state"] !== $_COOKIE["oa2-{$service}-state"]
) {
    /*
        Stage I CSRF failure

        Stage I ensures that CSRF protection is in place by setting a `state`
        parameter in the OAuth2 request, which is returned by Discord and can be
        checked against the state value stored by stage I in a cookie. If either
        are missing, or there is a mismatch, assume that a CSRF attack is taking
        place and abort the authentication request.
    */
    header("303 See Other");

    /*
        Unset CSRF state cookie as it is no longer required
    */
    setcookie(
        "oa2-{$service}-state", "", time() - 3600,
        strtok($_SERVER["REQUEST_URI"], "?")
    );

    header("Location: ".Config::getEndpointUri("/auth/failed.php?provider={$service}"));
    exit;
}

/*
    Stage I success
    -> AUTH Stage II
    Server-side validation

    The state parameter matches and an authorization code was returned that can
    be exchanged for an access token, which in turn lets us interact with the
    API to verify the identity of the user.
*/
$user = null;
$accessToken = null;
try {
    $resp = $client->getAccessToken($opts["tokenEndpoint"], "authorization_code", array(
        "code" => $_GET["code"],
        "redirect_uri" => $opts["redirectUri"]
    ));

    if (isset($resp["result"]["access_token"])) {
        $accessToken = $resp["result"]["access_token"];
    } elseif (isset($resp["access_token"])) {
        $accessToken = $resp["access_token"];
    }
    if ($accessToken === null) {
        /*
            Stage II failure

            No access token was returned by the OAuth2 endpoint. An access token
            is required to proceed, so we'll kick the user back to the "failed
            to authenticate" page and prompt them to try again.
        */
        header("303 See Other");

        /*
            Unset CSRF state cookie as it is no longer required
        */
        setcookie(
            "oa2-{$service}-state", "", time() - 3600,
            strtok($_SERVER["REQUEST_URI"], "?")
        );

        header("Location: ".Config::getEndpointUri("/auth/failed.php?provider={$service}"));
        exit;
    }

    /*
        Set the access token in the client and proceed to fetch the resource
        owner.
    */
    $client->setAccessToken($accessToken);
    if (strtolower($resp["result"]["token_type"]) === "bearer") {
        $client->setAccessTokenType(\OAuth2\Client::ACCESS_TOKEN_BEARER);
    }
    $response = $client->fetch($opts["identEndpoint"], array(), OAuth2\Client::HTTP_METHOD_GET, array("User-Agent" => "FreeField/".FF_VERSION." PHP/".phpversion()));
    if ($response["code"] !== 200 || !isset($response["result"])) {
        /*
            Stage II failure

            The OAuth2 service indicated that the identity request was
            unsuccessful. Redirect the user back to the "failed to authenticate"
            page and prompt them to try again.
        */
        header("303 See Other");

        /*
            Unset CSRF state cookie as it is no longer required
        */
        setcookie(
            "oa2-{$service}-state", "", time() - 3600,
            strtok($_SERVER["REQUEST_URI"], "?")
        );

        header("Location: ".Config::getEndpointUri("/auth/failed.php?provider={$service}"));
        exit;
    }
} catch (Exception $e) {
    /*
        Stage II failure

        The OAuth2 service could not verify that the authenticating user is who
        they claim to be. This could be for several reasons:

        - The OAuth2 service may currently be down
        - The authenticating user may have tried to forge login credentials
        - The authorization code may for whatever reason no longer be valid

        In any case, the authentication attempt should be rejected and the user
        redirected back to a page explaining that authentication failed,
        prompting them to try again or try another provider.
    */
    header("303 See Other");

    /*
        Unset CSRF state cookie as it is no longer required
    */
    setcookie(
        "oa2-{$service}-state", "", time() - 3600,
        strtok($_SERVER["REQUEST_URI"], "?")
    );

    header("Location: ".Config::getEndpointUri("/auth/failed.php?provider={$service}"));
    exit;
}

$user = $response["result"];

/*
    Stage II success
    -> AUTH Stage III
    Create session

    If we get this far, the OAuth2 service has confirmed that the user who
    authenticated is who they claim to be. Now, we can spawn a session for this
    user and attach it to the user's current browsing session, signing them in.

    The script calling __require() on this script handles stage III.
*/

?>
