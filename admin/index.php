<?php
/*
    This page is the administration interface for FreeField. All changes to
    configuration, users, groups, POIs and webhooks are configured here and then
    submitted to the relevant /admin/apply-*.php scripts for server-side
    processing.
*/

require_once("../includes/lib/global.php");
__require("config");
__require("auth");
__require("i18n");
__require("geo");
__require("theme");

/*
    The `$domains` array contains a list of pages (domains) to display on the
    user interface. Each domain entry in this array is an array with the
    following keys:

    `icon`
        The FontAwesome icon to display for this domain in the sidebar.

    `custom-handler`
        Boolean. True if the settings for the given domain should be rendered by
        an external script (/includes/admin/<domain>.php), false if it should
        render as a standard list of configuration options, as defined in this
        file.

    Each domain where `custom-handler` is set to false will contain a list of
    configuration options within the equivalent DOMAIN in
    /includes/config/tree.php. E.g. the "main" page will contain all of the
    settings under the `main` domain in the configuration tree in that file.

    Please see /includes/config/tree.php for detailed information on what
    settings each of the `custom-handler` == false domains represent.
*/
$domains = array(

    // Main settings (e.g. site URI, database connections)
    "main" => array(
        "icon" => "cog",
        "custom-handler" => false
    ),

    // User management
    "users" => array(
        "icon" => "users",
        "custom-handler" => true
    ),

    // Groups management
    "groups" => array(
        "icon" => "user-shield",
        "custom-handler" => true
    ),

    // POI management
    "pois" => array(
        "icon" => "map-marker-alt",
        "custom-handler" => true
    ),

    // Permissions
    "perms" => array(
        "icon" => "check-square",
        "custom-handler" => false
    ),

    // Security settings
    "security" => array(
        "icon" => "shield-alt",
        "custom-handler" => false
    ),

    // Authentication (sign-in) providers and setup
    "auth" => array(
        "icon" => "lock",
        "custom-handler" => false
    ),

    // Theme settings and defaults
    "themes" => array(
        "icon" => "palette",
        "custom-handler" => false
    ),

    // Map provider settings (e.g. map API keys)
    "map" => array(
        "icon" => "map",
        "custom-handler" => false
    ),

    // Geofence settings
    "fences" => array(
        "icon" => "expand",
        "custom-handler" => true
    ),

    // Webhooks
    "hooks" => array(
        "icon" => "link",
        "custom-handler" => true
    )

);

/*
    This page accepts a `d` GET query which indicates which of the domains (d is
    short for "domain") should be displayed by the page. E.g. if ?d=main, the
    page should display settings in the "main" domain. If ?d=users, the page
    should display a list of users and management tools for them.

    If there is no domain set in the URL, or the user does not have permission
    to access the domain given in `d`, the domain should fall back to the first
    domain the user has access to, starting from the first element of `$domains`
    and moving down.
*/
if (
    !isset($_GET["d"]) ||
    !in_array($_GET["d"], array_keys($domains)) ||
    !Auth::getCurrentUser()->hasPermission("admin/".$_GET["d"]."/general")
) {
    $firstAuthorized = null;
    foreach ($domains as $page => $data) {
        if (Auth::getCurrentUser()->hasPermission("admin/{$page}/general")) {
            $firstAuthorized = urlencode($page);
            break;
        }
    }
    header("HTTP/1.1 307 Temporary Redirect");
    if ($firstAuthorized == null) {
        /*
            If no domain was found that the user has access to, then they should
            not be on the admin pages. Redirect the user out of the admin pages.
        */
        header("Location: ".Config::getEndpointUri("/"));
    } else {
        header("Location: ./?d={$firstAuthorized}");
    }
    exit;
}

$domain = $_GET["d"];

/*
    `$di18n` is an instance of ConfigDomainI18N from /includes/lib/i18n.php and
    serves to return strings representing I18N keys for the domain in the
    language localization files. Please see /includes/lib/i18n.php for
    information on how this class works. The purpose of this variable is to
    centralize I18N tokens for every domain to a single file where they are
    easier to change (i.e. /includes/lib/i18n.php).
*/
$di18n = Config::getDomainI18N($domain);

/*
    If there is no custom handler for the current domain, the configuration page
    will be handled and rendered by this page. Load the list of settings
    assigned to the current domain from the configuration tree in
    /lib/config/tree.php. The returned array is associative and has page
    sections as keys and the settings within each section as values. E.g. for
    the "security" domain, `$sections` will have two keys "user-creation" and
    "sessions" (as these are the sections defined for the "security" domain in
    /lib/config/tree.php). These keys will have arrays for values, which contain
    the list of settings to display under each section on the page.
*/
if (!$domains[$domain]["custom-handler"]) {
    $sections = Config::getTreeDomain($domain);
}

?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex,nofollow">
        <meta name="theme-color" content="<?php echo Config::getHTML("themes/meta/color"); ?>">
        <title><?php echo I18N::resolveArgsHTML(
            "page_title.admin",
            true,
            Config::get("site/name"),
            I18N::resolve($di18n->getTitle())
        ); ?></title>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"
                integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8="
                crossorigin="anonymous"></script>
        <script>
            /*
                Display options for `IconPackOption` selectors; required by
                `viewTheme()` in /js/option.js.
            */
            var isc_opts = <?php
                echo json_encode(array(
                    "themedata" => IconPackOption::getIconSetDefinitions(),
                    "icons" => Theme::listIcons(),
                    "baseuri" => Config::getEndpointUri("/"),
                    "colortheme" => Config::get("themes/color/admin")
                ));
            ?>;
        </script>
        <script src="../js/option.js?t=<?php echo time(); ?>"></script>
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
        <link rel="stylesheet" href="../css/admin.css">
        <link rel="stylesheet" href="../css/<?php echo Config::getHTML("themes/color/admin"); ?>.css">

        <!--[if lte IE 8]>
            <link rel="stylesheet" href="../css/layouts/side-menu-old-ie.css">
        <![endif]-->
        <!--[if gt IE 8]><!-->
            <link rel="stylesheet" href="../css/layouts/side-menu.css">
        <!--<![endif]-->
    </head>
    <body>
        <div id="layout">
            <!-- Menu toggle -->
            <a href="#menu" id="menuLink" class="menu-link">
                <!-- Hamburger icon -->
                <span></span>
            </a>

            <div id="menu">
                <div class="pure-menu">
                    <a class="pure-menu-heading" href="..">Freefield</a>

                    <ul class="pure-menu-list">
                        <div class="menu-user-box">
                            <span class="user-box-small">
                                <?php echo I18N::resolveHTML("sidebar.signed_in_as"); ?>
                            </span><br>
                            <span class="user-box-nick">
                                <?php echo Auth::getCurrentUser()->getNicknameHTML(); ?>
                            </span><br>
                            <span class="user-box-small">
                                <?php echo Auth::getCurrentUser()->getProviderIdentityHTML(); ?>
                            </span><br>
                            <?php
                                if (!Auth::getCurrentUser()->isApproved()) {
                                    ?>
                                        <span class="user-box-small red">
                                            <?php echo I18N::resolveHTML("sidebar.approval_pending"); ?>
                                        </span>
                                    <?php
                                }
                            ?>
                        </div>
                        <li class="pure-menu-item">
                            <a href="../auth/logout.php" class="pure-menu-link">
                                <i class="menu-fas fas fa-sign-in-alt"></i>
                                <?php echo I18N::resolveHTML("sidebar.logout"); ?>
                            </a>
                        </li>
                        <div class="menu-spacer"></div>
                        <?php
                        /*
                            List all domains that the user has access to.
                        */
                        foreach ($domains as $d => $domaindata) {
                            if (!Auth::getCurrentUser()->hasPermission("admin/{$d}/general")) continue;
                            if ($d == $domain) {
                                echo '<li class="pure-menu-item menu-item-divided pure-menu-selected">';
                            } else {
                                echo '<li class="pure-menu-item">';
                            }

                            echo '<a href="./?d='.$d.'" class="pure-menu-link">'.
                                    '<i class="menu-fas fas fa-'.$domaindata["icon"].'"></i> '.
                                    I18N::resolveHTML(Config::getDomainI18N($d)->getTitle()).
                                 '</a></li>';
                        }
                        ?>
                        <div class="menu-spacer"></div>
                        <li class="pure-menu-item">
                            <a href=".." class="pure-menu-link">
                                <i class="menu-fas fas fa-angle-double-left"></i>
                                <?php echo I18N::resolveHTML("sidebar.return"); ?>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <div id="main">
                <div class="header">
                    <h1><?php echo I18N::resolveHTML($di18n->getTitle()); ?></h1>
                    <h2><?php echo I18N::resolveHTML($di18n->getSubtitle()); ?></h2>
                </div>

                <?php
                    if (!$domains[$domain]["custom-handler"]) {
                        /*
                            Domains which do not have a custom handler script
                            assigned to them will be rendered here.
                        */
                ?>
                    <div class="content">
                        <form action="apply-config.php?d=<?php echo urlencode($domain); ?>"
                              method="POST"
                              class="pure-form require-validation"
                              enctype="multipart/form-data">
                            <?php foreach ($sections as $section => $settings) { ?>
                                <h2 class="content-subhead">
                                    <?php echo I18N::resolveHTML($di18n->getSection($section)->getName()); ?>
                                </h2>
                                <?php
                                    /*
                                        Some configuration sections may have
                                        custom decriptions. If `__hasdesc` is
                                        set to `true` in this section, a
                                        description is present and should be
                                        displayed.
                                    */
                                    if (isset($settings["__hasdesc"]) && $settings["__hasdesc"]) {
                                        /*
                                            If `__descsprintf` is set, the I18N string contains
                                            arguments and should be passed to `resolveArgsHTML()`
                                            instead of `resolveHTML()`.
                                        */
                                        if (isset($settings["__descsprintf"])) {
                                            echo '<p>'.I18N::resolveArgsHTML(
                                                $di18n->getSection($section)->getDescription(),
                                                false,
                                                $settings["__descsprintf"]
                                            ).'</p>';
                                        } else {
                                            echo '<p>'.I18N::resolveHTML(
                                                $di18n->getSection($section)->getDescription()
                                            ).'</p>';
                                        }
                                    }
                                ?>
                                <?php foreach ($settings as $setting => $values) { ?>
                                    <?php
                                        /*
                                            Settings which start with "__" are meta descriptors for
                                            the current section (e.g. __hasdesc is not a setting,
                                            but a boolean that indicates whether or not the section
                                            has a description). Since they are reserved for this
                                            purpose, they should not be treated as settings.
                                        */
                                        if (substr($setting, 0, 2) === "__") continue;

                                        /*
                                            Similarly to how `$di18n` is a class instance for
                                            resolving keys for domains' localization keys, `$si18n`
                                            is set to class instanse responsible for resolving the
                                            localization keys of individual settings. It has
                                            functions like `getName()` and `getDescription()` that
                                            return a string that can be looked up in the L10N files
                                            for a translated string.
                                        */
                                        $si18n = Config::getSettingI18N($setting);

                                        /*
                                            `$option` is an instance of the class set as the input
                                            type of the setting. E.g. string settings have the
                                            `StringOption` class. These classes contain functions
                                            to assist in parsing values from the settings forms,
                                            and for outputting the HTML of the input box to the
                                            settings page. Please see /includes/config/types.php
                                            for a list of these classes and the purpose of each of
                                            its functions.
                                        */
                                        $option = $values["option"];

                                        // Current value of the setting
                                        $value = Config::get($setting);
                                    ?>
                                    <div class="pure-g">
                                        <div class="pure-u-1-3 full-on-mobile">
                                            <p class="setting-name">
                                                <?php echo I18N::resolveHTML($si18n->getName()); ?><span class="only-desktop">:
                                                    <span class="tooltip">
                                                        <i class="content-fas fas fa-question-circle"></i>
                                                        <span>
                                                            <?php echo I18N::resolveHTML($si18n->getDescription()); ?>
                                                        </span>
                                                    </span>
                                                </span>
                                            </p>
                                            <p class="only-mobile">
                                                <?php echo I18N::resolveHTML($si18n->getDescription()); ?>
                                            </p>
                                        </div>
                                        <div class="pure-u-2-3 full-on-mobile">
                                            <p>
                                                <?php
                                                    /*
                                                        This div should contain the input control
                                                        of the setting. This can be an input box,
                                                        a selection box, a checkbox or label, etc.
                                                        depending on the Option assigned to the
                                                        setting in /includes/config/tree.php (i.e.
                                                        the type of class in `$option`).
                                                    */
                                                    $attrs = array(
                                                        "name" => $setting,
                                                        "id" => str_replace("/", ".", $setting)
                                                    );
                                                    /*
                                                        Some settings may require certain
                                                        preconditions to work properly. Such
                                                        settings have a boolean assertion defined
                                                        in the "enable-only-if" array key in the
                                                        configuration tree. If that assertion
                                                        fails, the input control should be
                                                        disabled.
                                                    */
                                                    if (isset($values["enable-only-if"])) {
                                                        if (!$values["enable-only-if"]) {
                                                            $attrs["disabled"] = true;
                                                        }
                                                    }
                                                    echo $option->getControl($value, $attrs);
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                    <?php
                                        /*
                                            Some controls (like IconPackSelector) as additional
                                            HTML that should be included in a block following the
                                            setting input box itself. This appears underneath the
                                            setting input box. For IconPackSelector, this is a box
                                            that previews the icons in an icon pack upon selection
                                            by the user.
                                        */
                                        echo $option->getFollowingBlock();
                                    ?>
                                <?php } ?>
                            <?php } ?>
                            <p class="buttons">
                                <input type="submit"
                                       class="button-submit"
                                       value="<?php echo I18N::resolveHTML("ui.button.save"); ?>">
                            </p>
                        </form>
                    </div>
                <?php
                    } else {
                        /*
                            Settings which have a custom handler should call a
                            custom script file, whose output is included here.
                            This is done to reduce the length of this file to
                            improve its readability, as some of the custom
                            handlers have high line counts.
                        */
                        include("../includes/admin/{$domain}.php");
                    }
                ?>
            </div>
        </div>
        <script>
            var validationFailedMessage = <?php echo I18N::resolveJS("admin.validation.validation_failed"); ?>;
            var unsavedChangesMessage = <?php echo I18N::resolveJS("admin.validation.unsaved_changes"); ?>;
        </script>
        <!-- Script which offers client-side input validation -->
        <script src="../js/input-validation.js"></script>
        <script>
            /*
                Track changes to the inputs on the form to stop data being
                accidentally discarded if the user tries to navigate away from
                the page without saving the settings. If the user does navigate
                away, confirm with them that they intend to discard the changes
                first.
            */
            $("form").on("change", ":input", function() {
                unsavedChanges = true;
            });
            $(window).on("beforeunload", function() {
                if (unsavedChanges) {
                    return unsavedChangesMessage;
                }
            });
        </script>
        <!-- Script which enables functionality for the Pure CSS menu -->
        <script src="../js/ui.js"></script>
    </body>
</html>
