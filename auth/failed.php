<?php
/*
    This page is the page displayed when an authentication attempt failed. Users
    are prompted to attempt to sign in again, or to cancel and select another
    authentication provider.
*/

require_once("../includes/lib/global.php");
__require("config");
__require("auth");
__require("i18n");

/*
    If the user is already logged in, they shouldn't be here.
*/
if (Auth::isAuthenticated()) {
    header("HTTP/1.1 307 Temporary Redirect");
    header("Location: ".Config::getEndpointUri("/"));
    exit;
}

/*
    The authentication provider that was challenged.
*/
$provider = null;
if (isset($_GET["provider"])) {
    $provider = $_GET["provider"];
}

/*
    This function returns an array of all authentication providers.
*/
$providers = Auth::getAllProviders();

/*
    Ensure that the provider listed in `?provider=` actually exists. If not,
    redirect the user back to a generic "login failed" page.
*/
if ($provider !== null && !in_array($provider, $providers)) {
    header("HTTP/1.1 303 See Other");
    header("Location: ./failed.php");
    exit;
}

?>
<?php
/*
    Execute X-Frame-Options same-origin policy.
*/
Security::declareFrameOptionsHeader();
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(I18N::getLanguage(), ENT_QUOTES); ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex,nofollow">
        <meta name="theme-color" content="<?php echo Config::get("themes/meta/color")->valueHTML(); ?>">
        <title><?php echo I18N::resolveArgsHTML(
            "page_title.login.failed",
            true,
            Config::get("site/name")->value()
        ); ?></title>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"
                integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8="
                crossorigin="anonymous"></script>
        <link rel="shortcut icon"
              href="../themes/favicon.php?t=<?php
                /*
                    Force refresh the favicon by appending the last changed time
                    of the file to the path. https://stackoverflow.com/a/7116701
                */
                echo Config::get("themes/meta/favicon")->value()->getUploadTime();
              ?>">
        <link rel="stylesheet"
              href="https://unpkg.com/purecss@1.0.0/build/pure-min.css"
              integrity="sha384-nn4HPE8lTHyVtfCBi5yW9d20FjT8BJwUXyWZT9InLYax14RDjBj46LmSztkmNP9w"
              crossorigin="anonymous">
        <link rel="stylesheet"
              href="https://use.fontawesome.com/releases/v5.0.13/css/all.css"
              integrity="sha384-DNOHZ68U8hZfKXOrtjWvjxusGo9WQnrNx2sqG0tfsghAvtVlRW3tvkXWZh58N9jp"
              crossorigin="anonymous">
        <link rel="stylesheet" href="../css/main.css">
        <link rel="stylesheet" href="../css/<?php echo Config::get("themes/color/user-settings/theme")->valueHTML(); ?>.css">

        <!--[if lte IE 8]>
            <link rel="stylesheet" href="./css/layouts/side-menu-old-ie.css">
        <![endif]-->
        <!--[if gt IE 8]><!-->
            <link rel="stylesheet" href="../css/layouts/side-menu.css">
        <!--<![endif]-->
    </head>
    <body>
        <div id="main">
            <div class="header" style="border-bottom: none; margin-bottom: 50px;">
                <h1 class="red"><?php echo I18N::resolveHTML("login_failed.title"); ?></h1>
                <h2>
                    <?php echo I18N::resolveArgsHTML(
                        "login_failed.desc",
                        true,
                        I18N::resolve(
                            $provider == null
                            ? "login_failed.default_auth_provider"
                            : "admin.section.auth.{$provider}.name"
                        )
                    ); ?>
                </h2>
            </div>

            <div class="content">
                <p class="centered">
                    <?php echo I18N::resolveArgsHTML(
                        "login_failed.info",
                        true,
                        I18N::resolve(
                            $provider == null
                            ? "login_failed.default_auth_provider"
                            : "admin.section.auth.{$provider}.name"
                        )
                    ); ?>
                </p>
                <div class="cover-button-spacer"></div>
                <div class="pure-g">
                    <div class="pure-u-1-2 right-align full-on-mobile">
                        <span type="button"
                              id="login-failed-cancel"
                              class="button-standard split-button button-spaced">
                                    <?php echo I18N::resolveHTML("ui.button.cancel"); ?>
                        </span>
                    </div>
                    <div class="pure-u-1-2 full-on-mobile">
                        <span type="button"
                              id="login-failed-retry"
                              class="button-submit split-button button-spaced">
                                    <?php echo I18N::resolveHTML("ui.button.retry"); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <script src="../js/ui.js"></script>
        <script>
            /*
                Event handlers for the "cancel" and "try again" buttons.
            */
            $("#login-failed-cancel").on("click", function() {
                location.href = <?php
                    /*
                        If the failure happened as part of the FreeField setup
                        process, redirect the user back to the install wizard.
                    */
                    if (Config::getRaw("install/wizard/authenticate-now") !== true) {
                        echo json_encode(
                            isset($_GET["continue"])
                            ? "./login.php?continue=".urlencode($_GET["continue"])
                            : "./login.php"
                        );
                    } else {
                        echo json_encode("../admin/install-wizard.php?auth-failed");
                    }
                ?>;
            });
            $("#login-failed-retry").on("click", function() {
                location.href = <?php
                    echo json_encode(
                        isset($_GET["continue"])
                        ? "./oa2/{$provider}.php?continue=".urlencode($_GET["continue"])
                        : "./oa2/{$provider}.php"
                    );
                ?>;
            });
        </script>
    </body>
</html>
