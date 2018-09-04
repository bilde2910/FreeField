<?php
/*
    This page is a shortcut for administrators for approving user accounts.
    Links to this page are displayed to end users, prompting them to send the
    URL to an administrator to have their user accounts approved.
*/

require_once("../includes/lib/global.php");
__require("config");
__require("auth");
__require("i18n");

/*
    This page requires an `euid` GET parameter containing an encrypted user ID
    representing the user to be approved.
*/
if (!isset($_GET["euid"])) {
    header("HTTP/1.1 307 Temporary Redirect");
    header("Location: ".Config::getEndpointUri("/"));
    exit;
}

/*
    Decrypt the user ID.
*/
$id = Auth::getDecryptedUserID($_GET["euid"]);

/*
    Check if the current user is the same user that the URL was generated for.
    If so, output an explanation that the user has to send the URL to an
    administrator rather than opening it themselves.
*/
if ($id === Auth::getCurrentUser()->getUserID()) {
    ?>
    <!DOCTYPE html>
    <html>
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="robots" content="noindex,nofollow">
            <meta name="theme-color" content="<?php echo Config::getHTML("themes/meta/color"); ?>">
            <title><?php echo I18N::resolveArgsHTML(
                "page_title.login.awaiting_approval",
                true,
                Config::get("site/name")
            ); ?></title>
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
                        <?php echo I18N::resolveHTML("approve_user.user.title"); ?>
                    </h1>
                    <h2>
                        <?php echo I18N::resolveHTML("approve_user.user.desc"); ?>
                    </h2>
                </div>
                <div class="content">
                    <p class="centered">
                        <?php echo I18N::resolveHTML("approve_user.user.info"); ?>
                    </p>
                </div>
            </div>
            <script src="../js/ui.js"></script>
        </body>
    </html>
    <?php
    exit;
}

/*
    If the page is for any other user, check their permissions to see if they
    are authorized to approve users.
*/
if (!Auth::getCurrentUser()->hasPermission("admin/users/general")) {
    header("HTTP/1.1 307 Temporary Redirect");
    header("Location: ".Config::getEndpointUri("/"));
    exit;
}

/*
    Fetch information about the user.
*/
$user = Auth::getUser($id);

/*
    If the user is already approved or rejected, let the adminstrator know.
*/
if (!$user->exists() || $user->isApproved()) {
    ?>
    <!DOCTYPE html>
    <html>
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="robots" content="noindex,nofollow">
            <meta name="theme-color" content="<?php echo Config::getHTML("themes/meta/color"); ?>">
            <title><?php echo I18N::resolveArgsHTML(
                "page_title.login.approve_user",
                true,
                Config::get("site/name")
            ); ?></title>
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
                        <?php echo I18N::resolveHTML("approve_user.approved.title"); ?>
                    </h1>
                    <h2>
                        <?php echo I18N::resolveHTML("approve_user.approved.desc"); ?>
                    </h2>
                </div>
                <div class="content">
                    <p class="centered">
                        <?php echo I18N::resolveHTML("approve_user.approved.info"); ?>
                    </p>
                </div>
            </div>
            <script src="../js/ui.js"></script>
        </body>
    </html>
    <?php
    exit;
}

/*
    From here on, the user exists, and the currently logged in user has the
    required privileges to approve or reject the user. Display the prompt.
*/

?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex,nofollow">
        <meta name="theme-color" content="<?php echo Config::getHTML("themes/meta/color"); ?>">
        <title><?php echo I18N::resolveArgsHTML(
            "page_title.login.approve_user",
            true,
            Config::get("site/name")
        ); ?></title>
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
        <link rel="stylesheet" href="../css/<?php echo Config::getHTML("themes/color/admin"); ?>.css">

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
                    <?php echo I18N::resolveHTML("approve_user.admin.title"); ?>
                </h1>
                <h2>
                    <?php echo I18N::resolveHTML("approve_user.admin.desc"); ?>
                </h2>
            </div>

            <div class="content">
                <p>
                    <?php echo I18N::resolveHTML("approve_user.admin.info"); ?>
                </p>
                <table class="pure-table approve-user-table"><tbody>
                    <tr>
                        <td>
                            <?php echo I18N::resolveHTML(
                                "admin.table.users.user_list.column.provider_identity.name"
                            ); ?>
                        </td>
                        <td>
                            <?php echo $user->getProviderIdentityHTML(); ?>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <?php echo I18N::resolveHTML(
                                "admin.table.users.user_list.column.provider.name"
                            ); ?>
                        </td>
                        <td>
                            <?php echo I18N::resolveHTML("admin.section.auth.".$user->getProvider().".name"); ?>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <?php echo I18N::resolveHTML(
                                "admin.table.users.user_list.column.auto_nickname.name"
                            ); ?>
                        </td>
                        <td>
                            <?php echo $user->getNicknameHTML(); ?>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <?php echo I18N::resolveHTML(
                                "admin.table.users.user_list.column.registered.name"
                            ); ?>
                        </td>
                        <td>
                            <?php echo $user->getRegistrationDate(); ?>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <?php echo I18N::resolveHTML(
                                "admin.table.users.user_list.column.group.name"
                            ); ?>
                        </td>
                        <td>
                            <?php echo Auth::getGroupHTML($user->getPermissionLevel()); ?>
                        </td>
                    </tr>
                </tbody></table>
                <div class="cover-button-spacer"></div>
                <div class="pure-g">
                    <div class="pure-u-1-2 right-align">
                        <form action="apply-users.php"
                              method="POST"
                              enctype="application/x-www-form-urlencoded">
                            <input type="hidden"
                                   name="<?php echo htmlspecialchars($user->getUserID(), ENT_QUOTES); ?>[action]"
                                   value="approve">
                            <input type="submit"
                                   class="button-standard button-green input-split-button button-spaced right"
                                   value="<?php echo I18N::resolveHTML("approve_user.button.approve"); ?>">
                        </form>
                    </div>
                    <div class="pure-u-1-2">
                        <form action="apply-users.php"
                              method="POST"
                              enctype="application/x-www-form-urlencoded">
                            <input type="hidden"
                                   name="<?php echo htmlspecialchars($user->getUserID(), ENT_QUOTES); ?>[action]"
                                   value="delete">
                            <input type="submit"
                                   class="button-standard button-red input-split-button button-spaced right"
                                   value="<?php echo I18N::resolveHTML("approve_user.button.reject"); ?>">
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <script src="../js/ui.js"></script>
    </body>
</html>
