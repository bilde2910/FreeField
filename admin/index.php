<?php
/*
    This page is the administration interface for FreeField. All changes to
    configuration, users, groups, POIs and webhooks are configured here and then
    submitted to the relevant /admin/apply-*.php scripts for server-side
    processing.
*/

require_once("../includes/lib/global.php");
__require("config");

/*
Check if updates have been pulled directly from Git. Normally when updates
are installed, `PostUpgrade::finalizeUpgrade()` will perform checks on the
configuration file to ensure that it is compatible with the currently
installed version. If the source is pulled from Git, this check won't be
performed. To properly handle this case, the upgrade script in `PostUpgrade`
will write the version that the configuration file is compatible with in the
configuration file itself. If this page is visited and the version number
has not been visited, then the `PostUpgrade::finalizeUpgrade()` function has
not been called, thus we should call it.
*/
if (Config::getRaw("install/version-compatible") !== FF_VERSION) {
    include("../includes/setup/post-upgrade.php");
    PostUpgrade::finalizeUpgrade(
        Config::getRaw("install/version-compatible"), true
    );
}

__require("auth");
__require("i18n");
__require("theme");
__require("security");
__require("update");
__require("research");

Security::requireCSRFToken();

/*
    Check for software updates.
*/
if (Auth::getCurrentUser()->hasPermission("admin/updates/general")) {
    Update::autoCheckForUpdates();
}

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
    configuration options within the equivalent `domain` defined in
    /includes/config/defs.php. E.g. the "main" page will contain all of the
    settings with the `main` domain assigned in the definitions list in that
    file.

    Please see /includes/config/defs.php for detailed information on what
    settings each of the `custom-handler == false` domains represent.
*/
$domains = Config::listDomains();

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
    will be handled and rendered by this page. Load the list of sections
    assigned to the current domain from the configuration array in
    /includes/config/defs.php.
*/
if (!$domains[$domain]["custom-handler"]) {
    $sections = Config::listSectionsForDomain($domain);
}

/*
    Caching tokens. By appending timestamps to the end of URLs of content that
    can change often in development, we ensure that the content is cached, while
    at the same ensuring that it is up to date when used by the browser.
*/
$linkMod = array(
    "/css/main.css"         => filemtime("../css/main.css"),
    "/css/admin.css"        => filemtime("../css/admin.css"),
    "/css/dark.css"         => filemtime("../css/dark.css"),
    "/css/light.css"        => filemtime("../css/light.css"),
    "/js/ie-polyfill.js"    => filemtime("../js/ie-polyfill.js"),
    "/js/option.js"         => filemtime("../js/option.js")
);

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
            "page_title.admin",
            true,
            Config::get("site/name")->value(),
            I18N::resolve($di18n->getTitle())
        ); ?></title>

        <?php if (preg_match('/(MSIE|Trident)/', $_SERVER['HTTP_USER_AGENT'])) { ?>
            <!--
                Internet Explorer requires loading polyfills for certain
                JavaScript functionality such as `String.prototype.startsWith()`
                since it does not support ECMAScript 6.
            -->
            <script src="../js/ie-polyfill.js?t=<?php echo $linkMod["/js/ie-polyfill.js"]; ?>"></script>
        <?php } ?>

        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"
                integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8="
                crossorigin="anonymous"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/showdown/1.8.6/showdown.min.js"
                integrity="sha256-dwhppIrxD8qC6lNulndZgtIm4XBU9zoMd9OUoXzIDAE="
                crossorigin="anonymous"></script>
        <!--
            <input type="color"> polyfill:
        -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/spectrum/1.8.0/spectrum.min.js"
                integrity="sha256-ZdnRjhC/+YiBbXTHIuJdpf7u6Jh5D2wD5y0SNRWDREQ="
                crossorigin="anonymous"></script>
        <link rel="stylesheet"
              href="https://cdnjs.cloudflare.com/ajax/libs/spectrum/1.8.0/spectrum.min.css"
              integrity="sha256-f83N12sqX/GO43Y7vXNt9MjrHkPc4yi9Uq9cLy1wGIU="
              crossorigin="anonymous" />

        <script src="../js/clientside-i18n.php"></script>
        <script>
            /*
                Display options for `IconSetOptionBase` selectors; required by
                the `IconSetOption` event handler in /js/option.js.
            */
            var isc_opts = <?php
                echo json_encode(array(
                    "icons" => array(
                        "themedata" => IconSetOption::getIconSetDefinitions(),
                        "icons" => Theme::listIcons(),
                        "baseuri" => Config::getEndpointUri("/"),
                        "colortheme" => Config::get("themes/color/admin")->value()
                    ),
                    "species" => array(
                        "themedata" => SpeciesSetOption::getIconSetDefinitions(),
                        "highest" => ParamSpecies::getHighestSpecies(),
                        "baseuri" => Config::getEndpointUri("/"),
                        "colortheme" => Config::get("themes/color/admin")->value()
                    )
                ));
            ?>;
        </script>
        <script src="../js/option.js?t=<?php echo $linkMod["/js/option.js"]; ?>"></script>
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
              href="https://use.fontawesome.com/releases/v5.8.2/css/all.css"
              integrity="sha384-oS3vJWv+0UjzBfQzYUhtDYW+Pj2yciDJxpsK1OYPAYjqT085Qq/1cq5FLXAZQ7Ay"
              crossorigin="anonymous">
        <link rel="stylesheet" href="../css/main.css?t=<?php echo $linkMod["/css/main.css"]; ?>">
        <link rel="stylesheet" href="../css/admin.css?t=<?php echo $linkMod["/css/admin.css"]; ?>">
        <?php
            $adminThemeColor = Config::get("themes/color/admin")->valueHTML();
        ?>
        <link rel="stylesheet" href="../css/<?php echo $adminThemeColor ?>.css?t=<?php echo $linkMod["/css/{$adminThemeColor}.css"]; ?>">
        <link rel="stylesheet" href="../css/theming.php?<?php echo $adminThemeColor ?>">

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
                    <a class="pure-menu-heading" href=".."<?php
                        if (Config::get("site/header-style")->value() == "image-plain")
                            echo ' style="background: none !important; padding-bottom: 0;"';
                    ?>>
                        <?php
                            switch (Config::get("site/header-style")->value()) {
                                case "text":
                                    echo Config::get("site/menu-header")->valueHTML();
                                    break;
                                case "image":
                                case "image-plain":
                                    echo '<img src="../themes/sidebar-image.php">';
                                    break;
                            }
                        ?>
                    </a>

                    <?php if (Auth::isAuthenticated()) { ?>
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
                        <ul class="pure-menu-list">
                            <li class="pure-menu-item">
                                <a href="../auth/logout.php?<?php echo Security::getCSRFUrlParameter(); ?>"
                                   class="pure-menu-link">
                                    <i class="menu-fas fas fa-sign-in-alt"></i>
                                    <?php echo I18N::resolveHTML("sidebar.logout"); ?>
                                </a>
                            </li>
                        </ul>
                    <?php } else { ?>
                        <ul class="pure-menu-list">
                            <li class="pure-menu-item">
                                <a href="../auth/login.php" class="pure-menu-link">
                                    <i class="menu-fas fas fa-sign-in-alt"></i>
                                    <?php echo I18N::resolveHTML("sidebar.login"); ?>
                                </a>
                            </li>
                        </ul>
                    <?php } ?>
                    <div class="menu-spacer"></div>
                    <ul class="pure-menu-list">
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

                            $classes = "pure-menu-link";
                            if ($d == "updates" && Update::autoIsUpdateAvailable()) {
                                /*
                                    Highlight the "updates" link if a FreeField
                                    update is available.
                                */
                                $classes .= " menu-update-available";
                            }
                            echo '<a href="./?d='.$d.'" class="'.$classes.'">'.
                                    '<i class="menu-fas fas fa-'.$domaindata["icon"].'"></i> '.
                                    I18N::resolveHTML(Config::getDomainI18N($d)->getTitle()).
                                 '</a></li>';
                        }
                        ?>
                    </ul>
                    <div class="menu-spacer"></div>
                    <ul class="pure-menu-list">
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
                            <!--
                                Protection against CSRF
                            -->
                            <?php echo Security::getCSRFInputField(); ?>
                            <?php foreach ($sections as $section) { ?>
                                <?php
                                    /*
                                        Ensure that the user has permission to
                                        view and change settings in this
                                        section.
                                    */
                                    if (!Auth::getCurrentUser()->hasPermission(
                                        "admin/{$domain}/section/{$section}"
                                    )) continue;

                                    $settings = Config::listKeysForSection($domain, $section);
                                ?>
                                <h2 class="content-subhead">
                                    <?php echo I18N::resolveHTML($di18n->getSection($section)->getName()); ?>
                                </h2>
                                <?php
                                    /*
                                        Some configuration sections may have
                                        custom decriptions.
                                    */
                                    $sectionDesc = $di18n->getSection($section)->getLocalizedDescriptionHTML();
                                    if ($sectionDesc !== null) {
                                        echo $sectionDesc;
                                    }
                                ?>
                                <?php foreach ($settings as $setting) { ?>
                                    <?php
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
                                            Get a config entry for the given setting.
                                        */
                                        $entry = Config::get($setting);

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
                                        $option = $entry->getOption();

                                        // Current value of the setting
                                        $value = $entry->value();

                                        // Indentation of the setting
                                        $indent = "";
                                        $indentLevel = $entry->getIndentationLevel();
                                        if ($indentLevel > 0) {
                                            $indent = ' style="padding-left: '.($indentLevel * 5).'%;"';
                                        }
                                    ?>
                                    <div class="pure-g option-block-follows"<?php echo $indent; ?>>
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
                                                        setting in /includes/config/defs.php (i.e.
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
                                                    if (!$entry->isEnabled()) {
                                                        $attrs["disabled"] = true;
                                                    }
                                                    echo $option->getControl($value, $attrs);
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                    <?php
                                        /*
                                            Some controls (like IconSetOption) as additional HTML
                                            that should be included in a block following the
                                            setting input box itself. This appears underneath the
                                            setting input box. For IconSetOption, this is a box
                                            that previews the icons in an icon set upon selection
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
        <script src="../js/input-validation.js?t=<?php
            echo filemtime(__DIR__."/../js/input-validation.js");
        ?>"></script>
        <script>
            /*
                Track changes to the inputs on the form to stop data being
                accidentally discarded if the user tries to navigate away from
                the page without saving the settings. If the user does navigate
                away, confirm with them that they intend to discard the changes
                first.

                `unsavedChanges` is declared in /js/input-validation.js. Forms
                that use the `require-validation` class have a built-in handler
                that sets `unsavedChanges` to `false` on submit. Forms which do
                not use `require-validation` must set `unsavedChanges` to
                `false` manually. This is done on the relevant pages
                (/includes/admin/*.php) or their scripts (/admin/js/*.js) if the
                page has a form that does not use `require-validation`.
            */
            $("form").on("change", ":input", function() {
                if (!$(this).is("[data-do-not-track-changes]"))
                    unsavedChanges = true;
            });
            $(window).on("beforeunload", function() {
                if (unsavedChanges) {
                    return unsavedChangesMessage;
                }
            });

            /*
                Mobile: Hide the sidebar if any of its elements is clicked. This can be done
                by triggering a click on the hamburger menu icon if it is displayed on the
                page. (If it's not displayed, it's not considered a mobile client!)
            */
            $(".pure-menu-item > a").on("click", function() {
                if ($("#menuLink").is(":visible")) {
                    $("#menuLink").trigger("click");
                }
            });
        </script>
        <!-- Script which enables functionality for the Pure CSS menu -->
        <script src="../js/ui.js"></script>
    </body>
</html>
