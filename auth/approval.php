<?php
/*
    This page is the page displayed a user successfully registered or signed in,
    but their account has not been approved yet. This page will not be displayed
    for new users if the site is configured to not require manual administrative
    account approval.
*/

require_once("../includes/lib/global.php");
__require("config");
__require("auth");
__require("i18n");

/*
    If the user isn't logged in, or if they are, but their account is already
    approved, they shouldn't be here.
*/
if (!Auth::isAuthenticated() || Auth::getCurrentUser()->isApproved()) {
    header("HTTP/1.1 307 Temporary Redirect");
    header("Location: ".Config::getEndpointUri("/"));
    exit;
}

?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex,nofollow">
        <title><?php echo I18N::resolveArgsHTML(
            "page_title.login.awaiting_approval",
            true,
            Config::get("site/name")
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
                echo Config::getDefinition("themes/meta/favicon")["option"]
                     ->applyToCurrent()->getUploadTime();
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
        <link rel="stylesheet" href="../css/<?php echo Config::getHTML("themes/color/user-settings/theme"); ?>.css">

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
                <h1>
                    <?php echo I18N::resolveHTML("awaiting_approval.title"); ?>
                </h1>
                <h2>
                    <?php echo I18N::resolveHTML("awaiting_approval.desc"); ?>
                </h2>
            </div>

            <div class="content">
                <h2 class="section-header">
                    <?php echo I18N::resolveHTML("awaiting_approval.title"); ?>
                </h2>
                <p>
                    <?php echo I18N::resolveHTML("awaiting_approval.info"); ?>
                </p>
                <p class="buttons">
                    <input type="button"
                           id="approval-return"
                           class="button-submit"
                           value="<?php echo I18N::resolveHTML("awaiting_approval.return_button"); ?>">
                </p>
            </div>
        </div>
        <script src="../js/ui.js"></script>
        <script>
            /*
                Event handler for the "return to main page" button.
            */
            $("#approval-return").on("click", function() {
                location.href = "<?php echo Config::getEndpointUri("/") ?>";
            });
        </script>
    </body>
</html>
