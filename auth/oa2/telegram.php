<?php
/*
    This script handles all stages of Telegram authentication.
*/

require_once("../../includes/lib/global.php");
__require("config");
__require("auth");
__require("i18n");
__require("security");

$service = "telegram";

if (!Auth::isProviderEnabled($service)) {
    header("HTTP/1.1 307 Temporary Redirect");
    header("Location: ".Config::getEndpointUri("/auth/login.php"));
    exit;
}

Security::requireCSRFToken();

/*
    AUTH STAGE 0
    Pre-authentication

    Telegram requires using a separate button to sign in. This button is loaded
    from Telegram's servers, and does not match the styling of the buttons for
    the rest of the authentication providers on the sign-in page. We therefore
    have to load a separate page that has this button together with a prompt for
    the user to click on it. This allows us to style the Telegram button on the
    main login page (/auth/login.php) however we want.

    Clicking on the sign-in button on this page launches auth stage I
    client-side.
*/
if (!isset($_GET["hash"])) {
    /*
        Execute X-Frame-Options same-origin policy.
    */
    Security::declareFrameOptionsHeader();
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(I18N::getLanguage(), ENT_QUOTES); ?>">
    <head>
        <title><?php echo I18N::resolveArgsHTML(
            "page_title.login.telegram",
            true,
            Config::get("site/name")->value()
        ); ?></title>
        <meta name="robots" content="noindex,nofollow">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <style>
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
            <h1><?php echo I18N::resolveHTML("login.telegram.title"); ?></h1>
            <p><?php echo I18N::resolveHTML("login.telegram.body"); ?></p>
            <script async
                    src="https://telegram.org/js/telegram-widget.js?4"
                    data-telegram-login="<?php echo Config::get("auth/provider/{$service}/bot-username")->valueHTML(); ?>"
                    data-size="large"
                    data-userpic="false"
                    data-auth-url="<?php
                        /*
                            If the client wishes to be redirected to a
                            particular path after authentication is complete, we
                            should set that path as our redirect location.
                        */
                        echo Config::getEndpointUri("/auth/oa2/telegram.php?") .
                             Security::getCSRFUrlParameter() . (
                                 isset($_GET["continue"])
                                 ? "&continue=" . urlencode($_GET["continue"])
                                 : ""
                             );
                    ?>">
            </script>
        </div>
    </body>
</html>

<?php exit; }

/*
    If the client wishes to be redirected to a particular path after
    authentication is complete, we should set that path as our redirect
    location.
*/
$continueUrl = isset($_GET["continue"]) ? $_GET["continue"] : "/";
$continueUrlSafe = urlencode($continueUrl);
// Unset to prevent conflict with hashing validation function.
unset($_GET["continue"]);

/*
    Stage I success
    -> AUTH Stage II
    Server-side validation

    Validate the data returned from Telegram according to the Telegram login
    widget documentation:

    https://core.telegram.org/widgets/login#checking-authorization
*/

/*
    Perform CSRF validation.
*/
if (!Security::validateCSRF()) {
    header("HTTP/1.1 303 See Other");
    header("Location: ".Config::getEndpointUri(
        "/auth/failed.php?provider={$service}&continue={$continueUrlSafe}"
    ));
    exit;
}

/*
    Create and verify `data-check-string`:
        1.  Sort all received GET parameters alphabetically by key name. Ignore
            `hash` because we're verifying the data against `hash`.
        2.  Create an array of the fields in `key=value` format.
        3.  Implode the array to a string with \n as delimiter.
        4.  Get a SHA256 hash of our Telegram bot token.
        5.  Create a verification hash of the `data-check-string` using
            HMAC-SHA256 with the bot token hash as the key.
*/

Security::unsetCSRFFields();
$fields = $_GET;
$hash = $_GET["hash"];
unset($fields["hash"]);
ksort($fields);

$urlfields = array();
foreach ($fields as $k => $v) {
    $urlfields[] = "{$k}={$v}";
}

$data = implode("\n", $urlfields);
$key = hash("SHA256", Config::get("auth/provider/{$service}/bot-token")->value(), true);

$verify = hash_hmac("SHA256", $data, $key);

/*
    If the verification string is not equal to the hash returned by Telegram,
    then the authentication request is forged and should be discarded. The bot
    token is only visible to the FreeField administrators who have access to the
    authentication providers settings page, as well as to Telegram itself. Since
    no other parties have access to the token, no other parties may create login
    requests with a valid hash, as creating a valid hash requires having access
    to the bot token, or its SHA256 hash.

    Also check that the authentication session hasn't timed out (10 minutes).
*/

if ($verify !== $hash || time() - intval($_GET["auth_date"]) > 600) {
    header("HTTP/1.1 303 See Other");
    header("Location: ".Config::getEndpointUri(
        "/auth/failed.php?provider={$service}&continue={$continueUrlSafe}"
    ));
    exit;
}

/*
    Stage II success
    -> AUTH Stage III
    Create session
*/

/*
    Since the request is verified to originate from Telegram, we can assume that
    all the GET fields are set according to the Telegram documentation, hence we
    do not need to check the `$_GET` values' existence with `isset()`.
*/

$userid = $_GET["id"];

try {
    /*
        Nickname and provider identity both default to username, then name, then
        user ID, depending on available data.
    */
    $user = (isset($_GET["username"]) ? $_GET["username"] : null);
    if ($user === null || $user == "") $user = (
        isset($_GET["first_name"]) && isset($_GET["last_name"])
        ? $_GET["first_name"]." ".$_GET["last_name"]
        : null
    );
    if ($user === null || $user == "") $user = (
        isset($_GET["first_name"])
        ? $_GET["first_name"]
        : null
    );
    if ($user === null || $user == "") $user = (
        isset($_GET["last_name"])
        ? $_GET["last_name"]
        : null
    );
    if ($user === null || $user == "") $user = $_GET["id"];

    $hid = (isset($_GET["username"]) ? "@".$_GET["username"] : null);
    if ($hid === null || $hid == "") $hid = (
        isset($_GET["first_name"]) && isset($_GET["last_name"])
        ? $_GET["first_name"]." ".$_GET["last_name"]
        : null
    );
    if ($hid === null || $hid == "") $hid = (
        isset($_GET["first_name"])
        ? $_GET["first_name"]
        : null
    );
    if ($hid === null || $hid == "") $hid = (
        isset($_GET["last_name"])
        ? $_GET["last_name"]
        : null
    );
    if ($hid === null || $hid == "") $hid = $_GET["id"];

    $approved = Auth::setAuthenticatedSession(
        "{$service}:".$userid,
        Config::get("auth/session-length")->value(),
        $hid,
        $user
    );
    header("HTTP/1.1 303 See Other");
    /*
        Unapproved users should be redirected to a page explaining that
        their account has not yet been approved, and that they should
        contact an administrator to approve their account.
    */
    if ($approved) {
        header("Location: ".Config::getEndpointUri($continueUrl));
    } else {
        header("Location: ".Config::getEndpointUri("/auth/approval.php"));
    }
    exit;
} catch (Exception $e) {
    header("HTTP/1.1 303 See Other");
    header("Location: ".Config::getEndpointUri(
        "/auth/failed.php?provider={$service}&continue={$continueUrlSafe}"
    ));
    exit;
}

?>
