<?php
/*
    This is the main page of FreeField. It contains the map that the whole
    project revolves around.
*/

/*
    Disable all caching.
*/
header("Expires: ".date("r", 0));
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

/*
    Ensure that the configuration file exists. If not, proceed to site setup.
*/
if (!file_exists(__DIR__."/includes/userdata/config.json")) {
    header("HTTP/1.1 303 See Other");
    header("Location: ./admin/install-wizard.php");
    exit;
}

require_once("./includes/lib/global.php");
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
    include("./includes/setup/post-upgrade.php");
    PostUpgrade::finalizeUpgrade(
        Config::getRaw("install/version-compatible"), true
    );
}

__require("auth");
__require("i18n");
__require("security");
__require("update");

Security::requireCSRFToken();

/*
    Check for software updates.
*/
if (Auth::getCurrentUser()->hasPermission("admin/updates/general")) {
    Update::autoCheckForUpdates();
}

/*
    Check if the user currently has access to view the map. If they don't, and
    aren't logged in, prompt them to log in. If they are, tell them that they do
    not currently have permission to view the map, and prompt them to ask the
    admins for access.
*/
if (!Auth::getCurrentUser()->hasPermission("access")) {
    if (!Auth::isAuthenticated()) {
        header("HTTP/1.1 307 Temporary Redirect");
        header("Location: ".Config::getEndpointUri("/auth/login.php"));
        exit;
    } else {
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
                    <meta name="robots" content="<?php echo Config::get("spiders/robots-policy")->valueHTML(); ?>">
                    <meta name="theme-color" content="<?php echo Config::get("themes/meta/color")->valueHTML(); ?>">
                    <title><?php echo I18N::resolveArgsHTML(
                        "page_title.access_denied",
                        true,
                        Config::get("site/name")->value()
                    ); ?></title>
                    <link rel="shortcut icon"
                          href="./themes/favicon.php?t=<?php
                            /*
                                Force refresh the favicon by appending the last
                                changed time of the file to the path.
                                https://stackoverflow.com/a/7116701
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
                    <link rel="stylesheet" href="./css/main.css">
                    <link rel="stylesheet" href="./css/<?php echo Config::get("themes/color/user-settings/theme")->valueHTML(); ?>.css">
                    <link rel="stylesheet" href="./css/theming.php?<?php echo Config::get("themes/color/user-settings/theme")->valueHTML(); ?>">

                    <!--[if lte IE 8]>
                        <link rel="stylesheet" href="./css/layouts/side-menu-old-ie.css">
                    <![endif]-->
                    <!--[if gt IE 8]><!-->
                        <link rel="stylesheet" href="./css/layouts/side-menu.css">
                    <!--<![endif]-->
                </head>
                <body>
                    <div id="main">
                        <div class="header" style="border-bottom: none; margin-bottom: 50px;">
                            <h1 class="red"><?php echo I18N::resolveHTML("access_denied.title"); ?></h1>
                            <h2><?php echo I18N::resolveHTML("access_denied.desc"); ?></h2>
                        </div>

                        <div class="content">
                            <p>
                                <?php echo I18N::resolveArgsHTML(
                                    "access_denied.info",
                                    true,
                                    Auth::getCurrentUser()->getProviderIdentity()
                                ); ?>
                            </p>
                        </div>
                    </div>
                    <script src="./js/ui.js"></script>
                </body>
            </html>
        <?php
        exit;
    }
}

__require("config");
__require("theme");
__require("research");
__require("geo");
__require("vendor/parsedown");

/*
    A string identifying the chosen map provider for FreeField.
*/
$provider = Config::get("map/provider/source")->value();

/*
    Caching tokens. By appending timestamps to the end of URLs of content that
    can change often in development, we ensure that the content is cached, while
    at the same ensuring that it is up to date when used by the browser.
*/
$linkMod = array(
    "/css/main.css"         => filemtime("./css/main.css"),
    "/css/dark.css"         => filemtime("./css/dark.css"),
    "/css/light.css"        => filemtime("./css/light.css"),
    "/js/ie-polyfill.js"    => filemtime("./js/ie-polyfill.js"),
    "/js/main.js"           => filemtime("./js/main.js"),
    "/js/option.js"         => filemtime("./js/option.js"),
    "/pwa/register-sw.js"   => filemtime("./pwa/register-sw.js")
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
        <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
        <meta name="robots" content="<?php echo Config::get("spiders/robots-policy")->valueHTML(); ?>">
        <meta name="theme-color" content="<?php echo Config::get("themes/meta/color")->valueHTML(); ?>">
        <title><?php echo I18N::resolveArgsHTML(
            "page_title.main",
            true,
            Config::get("site/name")->value()
        ); ?></title>

        <?php if (preg_match('/(MSIE|Trident)/', $_SERVER['HTTP_USER_AGENT'])) { ?>
            <!--
                Internet Explorer requires loading polyfills for certain
                JavaScript functionality such as `String.prototype.startsWith()`
                since it does not support ECMAScript 6.
            -->
            <script src="./js/ie-polyfill.js?t=<?php echo $linkMod["/js/ie-polyfill.js"]; ?>"></script>
        <?php } ?>

        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"
                integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8="
                crossorigin="anonymous"></script>
        <script src="./js/clientside-i18n.php" async defer></script>
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
                        "colortheme" => Config::get("themes/color/user-settings/theme")->value()
                    ),
                    "species" => array(
                        "themedata" => SpeciesSetOption::getIconSetDefinitions(),
                        "highest" => ParamSpecies::getHighestSpecies(),
                        "baseuri" => Config::getEndpointUri("/"),
                        "colortheme" => Config::get("themes/color/user-settings/theme")->value()
                    )
                ));
            ?>;
            /*
                Determine if user is signed in or not.
            */
            function isAuthenticated() {
                return <?php echo Auth::isAuthenticated() ? "true" : "false"; ?>;
            }
        </script>
        <script src="./js/option.js?t=<?php echo $linkMod["/js/option.js"]; ?>" async defer></script>
        <link rel="shortcut icon"
              href="./themes/favicon.php?t=<?php
                /*
                    Force refresh the favicon by appending the last changed time
                    of the file to the path. https://stackoverflow.com/a/7116701
                */
                echo Config::get("themes/meta/favicon")->value()->getUploadTime();
              ?>">
        <style>
            body {
                background-color: #111;
            }
        </style>

        <?php
            /*
                Load the map provider stylesheep for the chosen map provider.
            */
            switch (Config::get("map/provider/source")->value()) {
                case "mapbox":
                    ?>
                        <link rel="stylesheet"
                              href="https://api.mapbox.com/mapbox-gl-js/v0.46.0/mapbox-gl.css"
                              media="none"
                              onload="if(media!=='all')media='all'">
                    <?php
                    break;
                case "thunderforest":
                    ?>
                        <link rel="stylesheet"
                              href="https://unpkg.com/leaflet@1.3.4/dist/leaflet.css"
                              integrity="sha512-puBpdR0798OZvTTbP4A8Ix/l+A4dHDD0DGqYW6RQ+9jxkRFclaxxQb/SJAWZfWAkuyeQUytO7+7N4QKrDh+drA=="
                              crossorigin=""
                              onload="if(media!=='all')media='all'">
                        <link rel="stylesheet"
                              href="https://cdn.jsdelivr.net/npm/leaflet.locatecontrol@0.63.0/dist/L.Control.Locate.min.css"
                              integrity="sha256-mvCapu8voeqbM31gwIzYjSiq79jiT4LdIEQSmjyRNoE="
                              crossorigin="anonymous"
                              onload="if(media!=='all')media='all'">
                    <?php
                    break;
            }
        ?>
        <link rel="stylesheet"
              href="https://unpkg.com/purecss@1.0.0/build/pure-min.css"
              integrity="sha384-nn4HPE8lTHyVtfCBi5yW9d20FjT8BJwUXyWZT9InLYax14RDjBj46LmSztkmNP9w"
              crossorigin="anonymous">
        <link rel="stylesheet"
              href="https://use.fontawesome.com/releases/v5.8.2/css/all.css"
              integrity="sha384-oS3vJWv+0UjzBfQzYUhtDYW+Pj2yciDJxpsK1OYPAYjqT085Qq/1cq5FLXAZQ7Ay"
              crossorigin="anonymous"
              media="none" onload="if(media!=='all')media='all'">
        <link rel="stylesheet"
              href="./css/main.css?t=<?php echo $linkMod["/css/main.css"]; ?>">
        <link rel="preload"
              href="./css/dark.css?t=<?php echo $linkMod["/css/dark.css"]; ?>"
              as="style">
        <link rel="preload"
              href="./css/light.css?t=<?php echo $linkMod["/css/light.css"]; ?>"
              as="style">
        <link rel="stylesheet" href="./css/map-markers.php"
              media="none"
              onload="if(media!=='all')media='all'">
        <!--
            Preload the default theme colors; this will be overridden once the
            page has finished loading depending on the color theme the user has
            chosen.
        -->
        <link rel="stylesheet"
              href="./css/theming.php?<?php echo Config::get("themes/color/user-settings/theme")->valueHTML(); ?>">
        <?php
            if (Config::get("mobile/pwa/enabled")->value()) {
                ?>
                    <link rel="manifest" href="./pwa/manifest.php">
                    <script src="./pwa/register-sw.js?t=<?php echo $linkMod["/pwa/register-sw.js"]; ?>"
                            async defer></script>
                <?php
            }
        ?>

        <!--[if lte IE 8]>
            <link rel="stylesheet" href="./css/layouts/side-menu-old-ie.css">
        <![endif]-->
        <!--[if gt IE 8]><!-->
            <link rel="stylesheet" href="./css/layouts/side-menu.css">
        <!--<![endif]-->
    </head>
    <body>
        <div id="layout">
            <!-- Menu toggle -->
            <a href="#menu" id="menuLink" class="menu-link">
                <!-- Hamburger icon -->
                <span></span>
            </a>
            <div id="corner-filter-link" class="filter-overlay-icon">
                <!-- Filter icon when active -->
                <i class="fas fa-filter"></i>
            </div>

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
                                    echo '<img src="./themes/sidebar-image.php">';
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
                                <a href="./auth/logout.php?<?php echo Security::getCSRFUrlParameter(); ?>"
                                   class="pure-menu-link">
                                    <i class="menu-fas fas fa-sign-in-alt"></i>
                                    <?php echo I18N::resolveHTML("sidebar.logout"); ?>
                                </a>
                            </li>
                        </ul>
                    <?php } else { ?>
                        <ul class="pure-menu-list">
                            <li class="pure-menu-item">
                                <a href="./auth/login.php" class="pure-menu-link">
                                    <i class="menu-fas fas fa-sign-in-alt"></i>
                                    <?php echo I18N::resolveHTML("sidebar.login"); ?>
                                </a>
                            </li>
                        </ul>
                    <?php } ?>
                    <div class="menu-spacer"></div>
                    <ul id="map-menu" class="pure-menu-list">
                        <?php if (Auth::getCurrentUser()->hasPermission("submit-poi")) { ?>
                            <li class="pure-menu-item">
                                <a href="#" id="add-poi-start" class="pure-menu-link">
                                    <i class="menu-fas fas fa-plus"></i>
                                    <?php echo I18N::resolveHTML("sidebar.add_poi"); ?>
                                </a>
                            </li>
                        <?php } ?>
                        <?php if (Auth::getCurrentUser()->hasPermission("submit-arena")) { ?>
                            <li class="pure-menu-item">
                                <a href="#" id="add-arena-start" class="pure-menu-link">
                                    <i class="menu-fas fas fa-plus"></i>
                                    <?php echo I18N::resolveHTML("sidebar.add_arena"); ?>
                                </a>
                            </li>
                        <?php } ?>
                        <li class="pure-menu-item">
                            <a href="#" id="menu-open-search" class="pure-menu-link">
                                <i class="menu-fas fas fa-search"></i>
                                <?php echo I18N::resolveHTML("sidebar.search"); ?>
                            </a>
                        </li>
                        <li class="pure-menu-item">
                            <a href="#" id="menu-open-filters" class="pure-menu-link">
                                <i class="menu-fas fas fa-filter"></i>
                                <?php echo I18N::resolveHTML("sidebar.filters"); ?>
                            </a>
                        </li>
                        <li class="pure-menu-item">
                            <a href="#" id="menu-open-settings" class="pure-menu-link">
                                <i class="menu-fas fas fa-wrench"></i>
                                <?php echo I18N::resolveHTML("sidebar.settings"); ?>
                            </a>
                        </li>
                        <?php
                            /* Check if user has permission to access any admin pages. */
                            if (Auth::getCurrentUser()->canAccessAdminPages()) {
                                ?>
                                    <li class="pure-menu-item">
                                        <a href="./admin/" class="pure-menu-link<?php
                                            /*
                                                Highlight "Manage site" link if
                                                an update is available and the
                                                current user has permission to
                                                update FreeField.
                                            */
                                            if (
                                                Auth::getCurrentUser()->hasPermission(
                                                    "admin/updates/general"
                                                ) &&
                                                Update::autoIsUpdateAvailable()
                                            ) {
                                                echo " menu-update-available";
                                            }
                                        ?>">
                                            <i class="menu-fas fas fa-angle-double-right"></i>
                                            <?php echo I18N::resolveHTML("sidebar.manage_site"); ?>
                                        </a>
                                    </li>
                                <?php
                            }
                        ?>
                        <?php
                            /* Check if the "Show MotD" button should be displayed. */
                            if (Config::get("motd/display-mode")->value() !== "never") {
                                ?>
                                    <li class="pure-menu-item">
                                        <a href="#" id="motd-open" class="pure-menu-link">
                                            <i class="menu-fas fas fa-bell"></i>
                                            <?php echo I18N::resolveHTML("sidebar.show_motd"); ?>
                                        </a>
                                    </li>
                                <?php
                            }
                        ?>
                    </ul>
                    <ul id="settings-menu" class="pure-menu-list hidden-by-default">
                        <li class="pure-menu-item">
                            <a href="#" id="menu-reset-settings" class="pure-menu-link">
                                <i class="menu-fas fas fa-undo"></i>
                                <?php echo I18N::resolveHTML("sidebar.reset"); ?>
                            </a>
                        </li>
                        <li class="pure-menu-item">
                            <a href="#" id="menu-close-settings" class="pure-menu-link">
                                <i class="menu-fas fas fa-angle-double-left"></i>
                                <?php echo I18N::resolveHTML("sidebar.cancel"); ?>
                            </a>
                        </li>
                    </ul>
                    <div class="menu-about-box">
                        <p>
                            <i class="fas fa-globe-americas"></i>
                            <?php echo I18N::resolveHTML("sidebar.language.label"); ?>
                        </p>
                        <p><select id="menu-language-select">
                            <optgroup label="<?php echo I18N::resolveHTML("sidebar.language.auto"); ?>">
                                <option value="">
                                    <?php echo I18N::resolveHTML("sidebar.language.device"); ?>
                                </option>
                            </optgroup>
                            <optgroup label="<?php echo I18N::resolveHTML("sidebar.language.select"); ?>">
                                <?php
                                    $curlang = !isset($_COOKIE["language"]) ? "" : $_COOKIE["language"];
                                    $langs = I18N::getAvailableLanguagesWithNames();
                                    foreach ($langs as $code => $name) {
                                        ?>
                                            <option value="<?php echo $code; ?>"<?php if ($code == $curlang) echo " selected"; ?>>
                                                <?php echo $name; ?>
                                            </option>
                                        <?php
                                    }
                                ?>
                            </optgroup>
                        </select></p>
                        <p>
                            <a href="https://github.com/bilde2910/FreeField" target="_blank">FreeField</a>
                            v<?php
                                /*
                                    The sidebar is narrow, so we'll replace
                                    release tags with shorter versions to fit
                                    everything on one line.
                                */
                                echo
                                    str_replace("-alpha.", "-a",
                                    str_replace("-beta.", "-b",
                                    str_replace("-rc.", "-rc",
                                        FF_VERSION
                                    )));
                            ?>
                        </p>
                    </div>
                </div>
            </div>

            <div id="main">
                <div id="map-container">
                    <!--
                        The banner container. Banners created by `spawnBanner()`
                        in /js/main.js are added here.
                    -->
                    <div id="dynamic-banner-container">
                    </div>
                    <!--
                        A special banner that shows up when the user is asked to
                        click on the map to select the location for a new POI.
                    -->
                    <div id="add-poi-banner" class="banner">
                        <div class="banner-inner">
                            <?php echo I18N::resolveArgsHTML(
                                "poi.add.instructions",
                                false,
                                '<a href="#" id="add-poi-cancel-banner">',
                                '</a>'
                            ); ?>
                        </div>
                    </div>
                    <!--
                        A special banner that shows up when the user is asked to
                        click on the map to select the location for a new arena.
                    -->
                    <div id="add-arena-banner" class="banner">
                        <div class="banner-inner">
                            <?php echo I18N::resolveArgsHTML(
                                "arena.add.instructions",
                                false,
                                '<a href="#" id="add-arena-cancel-banner">',
                                '</a>'
                            ); ?>
                        </div>
                    </div>
                    <!--
                        A special banner that shows up when the user is asked to
                        click on the map to select a new location for a POI.
                    -->
                    <div id="move-poi-banner" class="banner">
                        <div class="banner-inner">
                            <?php echo I18N::resolveArgsHTML(
                                "poi.move.instructions",
                                false,
                                '<a href="#" id="move-poi-cancel-banner">',
                                '</a>'
                            ); ?>
                        </div>
                    </div>
                    <!--
                        A special banner that shows up when the user is asked to
                        click on the map to select a new location for an arena.
                    -->
                    <div id="move-arena-banner" class="banner">
                        <div class="banner-inner">
                            <?php echo I18N::resolveArgsHTML(
                                "arena.move.instructions",
                                false,
                                '<a href="#" id="move-arena-cancel-banner">',
                                '</a>'
                            ); ?>
                        </div>
                    </div>
                    <!--
                        A banner that appears at the top of the map if the
                        amount of POIs within the map's bounding box exceeds the
                        amount that should reasonably be displayed at the same
                        time as requested by the user.
                    -->
                    <div id="clustering-active-banner" class="top-banner">
                        <div class="triangle triangle-left"></div>
                        <a href="https://freefield.readthedocs.io/en/latest/faq.html#why-are-some-markers-hidden-from-the-map"
                           target="_blank">
                            <div class="top-banner-inner">
                                <?php echo I18N::resolveArgsHTML(
                                    "clustering.banner",
                                    false,
                                    '<span id="clustering-active-count"></span>',
                                    '<span id="clustering-active-total"></span>',
                                    '<i class="fas fa-eye-slash"></i>'
                                ); ?>
                            </div>
                        </a>
                        <div class="triangle triangle-right"></div>
                    </div>
                    <!--
                        POI details overlay. Contains details such as the POI's
                        name, its current active field research, means of
                        reporting research to the POI (if permission is granted
                        to do so), and a button to get directions to the POI on
                        a turn-based navigation service. The overlay is opened
                        whenever the user clicks on a marker on the map.

                        This overlay is also used for displaying details about
                        arenas.
                    -->
                    <div id="poi-details" class="cover-box">
                        <div class="cover-box-inner">
                            <div class="header">
                                <h1 id="poi-name" class="head-small"></h1>
                            </div>
                            <div class="cover-box-content content">
                                <div class="only-for-poi">
                                    <div class="pure-g">
                                        <div class="pure-u-1-2 right-align">
                                            <img id="poi-objective-icon" src="about:blank" class="bigmarker">
                                        </div>
                                        <div class="pure-u-1-2">
                                            <img id="poi-reward-icon" src="about:blank" class="bigmarker">
                                        </div>
                                    </div>
                                    <p class="centered">
                                        <?php echo I18N::resolveArgsHTML(
                                            "poi.objective_text",
                                            false,
                                            '<strong id="poi-objective" class="strong-color"></strong>',
                                            '<strong id="poi-reward" class="strong-color"></strong>'
                                        ); ?>
                                    </p>
                                    <p class="centered" id="poi-flag-evil">
                                        <span class="poi-flag">
                                            <?php
                                                echo I18N::resolveHTML("poi.flag.evil.head");
                                            ?>
                                        </span>
                                        <br />
                                        <span id="poi-flag-evil-remain-normal">
                                            <?php
                                                echo I18N::resolveArgsHTML(
                                                    "poi.flag.evil.body.normal", false,
                                                    '<span id="poi-flag-evil-remain"></span>'
                                                );
                                            ?>
                                        </span>
                                        <span id="poi-flag-evil-remain-early">
                                            <?php
                                                echo I18N::resolveHTML("poi.flag.evil.body.early");
                                            ?>
                                        </span>
                                    </p>
                                </div>
                                <div class="only-for-arena">
                                    <div class="pure-g">
                                        <div class="pure-u-5-5 centered">
                                            <img id="poi-arena-icon" src="about:blank" class="bigmarker">
                                        </div>
                                    </div>
                                    <p class="centered poi-flag" id="poi-arena-flag-ex">
                                        <?php
                                            echo I18N::resolveHTML("arena.flag.ex");
                                        ?>
                                    </p>
                                </div>
                                <p class="centered">
                                    <span id="poi-last-time"></span>
                                    <span id="poi-last-user-box">
                                        <br />
                                        <span id="poi-last-user-text"></span>
                                    </span>
                                </p>
                                <div class="cover-button-spacer"></div>
                                <div class="only-for-poi">
                                    <div class="pure-g">
                                        <div class="pure-u-3-4">
                                            <span id="poi-add-report"
                                                  class="button-standard split-button button-spaced left">
                                                <?php echo I18N::resolveHTML("poi.report_research"); ?>
                                            </span>
                                        </div>
                                        <div class="pure-u-1-4">
                                            <span id="poi-add-evil"
                                                  class="button-standard split-button button-spaced right">
                                                 <span class="poi-button-evil">
                                                     (<span class="poi-evil-logo">R</span>)
                                                 </span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <?php
                                    if (Auth::getCurrentUser()->hasPermission("admin/pois/general")) {
                                        /*
                                            Buttons for moving and deleting POIs.
                                        */
                                        ?>
                                            <div class="pure-g" id="poi-action-buttons">
                                                <div class="pure-u-1-4">
                                                    <span id="poi-move"
                                                          class="button-standard split-button button-spaced left fas fa-arrows-alt"
                                                          title="<?php echo I18N::resolveHTML("poi.move"); ?>">
                                                    </span>
                                                </div>
                                                <div class="pure-u-1-4">
                                                    <span id="poi-rename"
                                                          class="button-standard split-button button-spaced fas fa-tag"
                                                          title="<?php echo I18N::resolveHTML("poi.rename"); ?>">
                                                    </span>
                                                </div>
                                                <div class="pure-u-1-4 only-for-poi">
                                                    <span id="poi-clear"
                                                          class="button-standard split-button button-spaced fas fa-broom"
                                                          title="<?php echo I18N::resolveHTML("poi.clear"); ?>">
                                                    </span>
                                                </div>
                                                <div class="pure-u-1-4">
                                                    <span id="poi-delete"
                                                          class="button-standard split-button button-spaced right fas fa-trash-alt"
                                                          title="<?php echo I18N::resolveHTML("poi.delete"); ?>">
                                                    </span>
                                                </div>
                                            </div>
                                        <?php
                                    }
                                ?>
                                <div class="pure-g">
                                    <div class="pure-u-1-2 right-align">
                                        <span id="poi-directions"
                                              class="button-standard split-button button-spaced left">
                                            <?php echo I18N::resolveHTML("poi.directions"); ?>
                                        </span>
                                    </div>
                                    <div class="pure-u-1-2">
                                        <span id="poi-close"
                                              class="button-standard split-button button-spaced right">
                                            <?php echo I18N::resolveHTML("ui.button.close"); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!--
                        New POI overlay that's shown when the user is adding a
                        new POI to the map, and they have clicked on the
                        location on the map where they wish the new POI to be
                        added. The overlay dialog asks for the name of the POI
                        to be added, and confirms its coordinates.
                    -->
                    <div id="add-poi-details" class="cover-box">
                        <div class="cover-box-inner">
                            <div class="header">
                                <h1 id="add-poi-text-title"></h1>
                            </div>
                            <div class="cover-box-content content pure-form">
                                <div class="pure-g">
                                    <div class="pure-u-1-3 full-on-mobile">
                                        <p><span id="add-poi-text-name"></span>:</p>
                                    </div>
                                    <div class="pure-u-2-3 full-on-mobile">
                                        <p><input type="text" id="add-poi-name"></p>
                                    </div>
                                </div>
                                <div class="pure-g">
                                    <div class="pure-u-1-3 full-on-mobile">
                                        <p><span id="add-poi-text-latitude"></span>:</p>
                                    </div>
                                    <div class="pure-u-2-3 full-on-mobile">
                                        <p><input type="text" id="add-poi-lat" readonly></p>
                                    </div>
                                </div>
                                <div class="pure-g">
                                    <div class="pure-u-1-3 full-on-mobile">
                                        <p><span id="add-poi-text-longitude"></span>:</p>
                                    </div>
                                    <div class="pure-u-2-3 full-on-mobile">
                                        <p><input type="text" id="add-poi-lon" readonly></p>
                                    </div>
                                </div>
                                <div class="cover-button-spacer"></div>
                                <div class="pure-g">
                                    <div class="pure-u-1-2 right-align">
                                        <span id="add-poi-cancel"
                                              class="button-standard split-button button-spaced left">
                                            <?php echo I18N::resolveHTML("ui.button.cancel"); ?>
                                        </span>
                                    </div>
                                    <div class="pure-u-1-2">
                                        <span id="add-poi-submit"
                                              class="button-submit split-button button-spaced right">
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!--
                        Field research reporting dialog. If a user wishes to
                        report field research on a POI, this dialog shows up. It
                        prompts the user for the type of objective and reward
                        that constitutes the research task, and requests
                        objective and reward metadata (parameters, such as a
                        quantity of typing) as well, if applicable.
                    -->
                    <div id="update-poi-details" class="cover-box">
                        <div class="cover-box-inner">
                            <div class="header">
                                <h1>
                                    <?php echo I18N::resolveHTML("poi.update.title"); ?>
                                </h1>
                            </div>
                            <div class="cover-box-content content pure-form">
                                <div class="pure-g">
                                    <div class="pure-u-1-3 full-on-mobile">
                                        <p><?php echo I18N::resolveHTML("poi.update.name"); ?>:</p>
                                    </div>
                                    <div class="pure-u-2-3 full-on-mobile">
                                        <p><input type="text" id="update-poi-name" readonly></p>
                                    </div>
                                </div>
                                <h2><?php echo I18N::resolveHTML("poi.update.objective"); ?></h2>
                                <div class="pure-g">
                                    <div class="pure-u-5-5 full-on-mobile"><p><select id="update-poi-objective">
                                        <?php
                                            /*
                                                Select box that contains a list of all possible research objectives.
                                            */

                                            // Sorts objectives and rewards alphabetically according to their translated strings.
                                            /*function sortByI18N($a, $b) {
                                                return strcmp($a["i18n"], $b["i18n"]);
                                            }*/

                                            /*
                                                First, get a list of all current research objectives.
                                            */
                                            $commonObjectives = Research::listCommonObjectives();

                                            /*
                                                Resolve their display text.
                                            */
                                            $commonObjectivesText = array();
                                            for ($i = 0; $i < count($commonObjectives); $i++) {
                                                $commonObjectivesText[$i] = htmlspecialchars(
                                                    Research::resolveObjective(
                                                        $commonObjectives[$i]["type"],
                                                        $commonObjectives[$i]["params"]
                                                    )
                                                );
                                            }

                                            /*
                                                Sort them alphabetically.
                                            */
                                            asort($commonObjectivesText);

                                            /*
                                                Echo them to the page in a "Current objectives" optgroup.
                                            */
                                            echo '<optgroup label="'.I18N::resolveHTML("category.objective.current").'">';
                                            foreach ($commonObjectivesText as $index => $task) {
                                                echo '<option value="_c_'.$index.'">'.$task.'</option>';
                                            }
                                            echo '</optgroup>';

                                            /*
                                                Now move on to the generic research objectives.

                                                We'll sort the research objectives by their first respective categories.
                                                Put all the research objectives into an array ($cats) of the structure
                                                $cats[CATEGORY][RESEARCH OBJECTIVE][PARAMETERS ETC.]
                                            */
                                            $cats = array();
                                            foreach (Research::listObjectives() as $objective => $data) {
                                                // Skip unknown since it shouldn't be displayed
                                                if ($objective === "unknown") continue;
                                                $cats[$data["categories"][0]][$objective] = $data;
                                            }

                                            /*
                                                After the objectives have been sorted into proper categories, we'll resolve
                                                the I18N string for each research objective, one category at a time.
                                            */
                                            foreach ($cats as $category => $categorizedObjectives) {
                                                foreach ($categorizedObjectives as $objective => $data) {
                                                    // Use the plural string of the objective by default
                                                    $i18n = I18N::resolve("objective.{$objective}.plural");
                                                    // If the objective is singular-only, use the singular string
                                                    if (!in_array("quantity", $data["params"]))
                                                        $i18n = I18N::resolve("objective.{$objective}.singular");
                                                    // Replace parameters (e.g. {%1}) with placeholders
                                                    for ($i = 0; $i < count($data["params"]); $i++) {
                                                        $i18n = str_replace(
                                                            "{%".($i+1)."}",
                                                            I18N::resolve("parameter.".$data["params"][$i].".placeholder"),
                                                            $i18n
                                                        );
                                                    }
                                                    // Now save the final localized string back into the objective
                                                    $categorizedObjectives[$objective]["i18n"] = htmlspecialchars($i18n, ENT_QUOTES);
                                                }

                                                //uasort($categorizedObjectives, "sortByI18N");

                                                /*
                                                    Create a group for each category of objectives, then output each of the
                                                    objectives within that category to the selection box.
                                                */
                                                echo '<optgroup label="'.I18N::resolveHTML("category.objective.{$category}").'">';
                                                foreach ($categorizedObjectives as $objective => $data) {
                                                    echo '<option value="'.$objective.'">'.$data["i18n"].'</option>';
                                                }
                                                echo '</optgroup>';
                                            }

                                            /*
                                            $objectives = Research::listObjectives();
                                            foreach ($objectives as $objective => $data) {
                                                if ($objective === "unknown") continue;
                                                $objectives[$objective]["i18n"] = I18N::resolve("objective.{$objective}.plural");
                                            }
                                            uasort($objectives, "sortByI18N");

                                            foreach ($objectives as $objective => $data) {
                                                if ($objective === "unknown") continue;
                                                $text = I18N::resolve("objective.{$objective}.plural");
                                                echo '<option value="'.$objective.'">'.$text.'</option>';
                                            }
                                            */
                                        ?>
                                    </select></p></div>
                                </div>
                                <div class="research-params objective-params">
                                    <?php
                                        /*
                                            Each objective may take one or more parameters. This
                                            part of the script ensures that all possible parameters
                                            have an input box representing them in the dialog.
                                            Parameters which are not in use will be hidden by
                                            default.
                                        */
                                        foreach (Research::PARAMETERS as $param => $class) {
                                            /*
                                                Each parameter type has a class corresponding to it,
                                                containing functions like value parsing, a function
                                                for returning an HTML node allowing users to set a
                                                value for the parameter, etc. These classes are
                                                listed and defined in
                                                /includes/data/objectives.yaml.

                                                We instantiate an instance of the class for each
                                                parameter so that we can check that the parameter
                                                is valid for objectives, and to get the HTML edit
                                                node for the parameter and output it to the page.

                                                The `html()` function of each parameter class is
                                                used to get this node, so we will call it here.
                                            */
                                            $inst = new $class();
                                            /*
                                                Each parameter can be used for objectives, rewards,
                                                or both. Ensure that the parameter can be used for
                                                objectives before we output it, to avoid unnecessary
                                                and unused elements on the page.
                                            */
                                            if (in_array("objectives", $inst->getAvailable())) {
                                                ?>
                                                    <div id="update-poi-objective-param-<?php echo $param; ?>-box"
                                                         class="pure-g research-parameter objective-parameter">
                                                        <div class="pure-u-1-3 full-on-mobile">
                                                            <p><?php echo I18N::resolveHTML("parameter.{$param}.label"); ?>:</p>
                                                        </div>
                                                        <div class="pure-u-2-3 full-on-mobile">
                                                            <?php echo $inst->html(
                                                                "update-poi-objective-param-{$param}-input",
                                                                "parameter"
                                                            ); ?>
                                                        </div>
                                                    </div>
                                                <?php
                                            }
                                        }
                                    ?>
                                    <script>
                                        function getObjectiveParameter(param) {
                                            switch (param) {
                                                <?php
                                                    /*
                                                        The parameter class also has a JavaScript
                                                        function to retrieve the value of the input
                                                        box(es) on for the parameter on the page,
                                                        and converting them to an object that
                                                        represents the parameter and can be stored
                                                        in a database or configuration file.

                                                        We will output all of these to a JavaScript
                                                        function called `getObjectiveParameter()`.
                                                        We can then call e.g.
                                                        `getObjectiveParameter("type")` and have it
                                                        return an object e.g. `["ice", "water"]`,
                                                        depending on the data input by the user in
                                                        the parameter's input box(es).

                                                        The ID of the input box is passed to the
                                                        `writeJS()` function so that the function
                                                        can extract data from the correct input box.
                                                    */
                                                    foreach (Research::PARAMETERS as $param => $class) {
                                                        $inst = new $class();
                                                        if (in_array("objectives", $inst->getAvailable())) {
                                                            echo "case '{$param}':\n";
                                                            echo $inst->writeJS("update-poi-objective-param-{$param}-input")."\n";
                                                        }
                                                    }
                                                ?>
                                            }
                                        }
                                        function parseObjectiveParameter(param, data) {
                                            switch (param) {
                                                <?php
                                                    /*
                                                        The `parseObjectiveParameter()` function
                                                        does the exact opposite of
                                                        `getObjectiveParameter()` - it takes a data
                                                        object, as parsed by the latter function,
                                                        and puts its value(s) into the HTML input
                                                        box(es) for the parameter on the page,
                                                        allowing the user to edit them before being
                                                        put back into a modified data object using
                                                        `getObjectiveParameter()`.
                                                    */
                                                    foreach (Research::PARAMETERS as $param => $class) {
                                                        $inst = new $class();
                                                        if (in_array("objectives", $inst->getAvailable())) {
                                                            echo "case '{$param}':\n";
                                                            echo $inst->parseJS("update-poi-objective-param-{$param}-input")."\n";
                                                            echo "break;\n";
                                                        }
                                                    }
                                                ?>
                                            }
                                        }

                                        /*
                                            When the objective is changed, the parameters used by
                                            that objective should be displayed, and all others
                                            should be hidden. This handler first ensures that all
                                            parameters are hidden (it loops over the server-side
                                            list of registered parameters and outputs a jQuery
                                            `hide()` statement for each of them), then loops over
                                            the list of parameters accepted by the currently
                                            selected objective and shows them.
                                        */
                                        $("#update-poi-objective").on("change", function() {
                                            <?php
                                                foreach (Research::PARAMETERS as $param => $class) {
                                                    $inst = new $class();
                                                    if (in_array("objectives", $inst->getAvailable())) {
                                                        echo "$('#update-poi-objective-param-{$param}-box').hide();";
                                                    }
                                                }
                                            ?>
                                            /*
                                                Determine whether the user selected a predefined
                                                common objective.
                                            */
                                            var selectedObjective = $("#update-poi-objective").val();
                                            if (selectedObjective.startsWith("_c_")) {
                                                /*
                                                    If they did, there is no need to display the
                                                    parameter input boxes. We can just put the
                                                    values for the parameters in directly, as the
                                                    parameters are defined in `commonObjectives`.
                                                */
                                                var commonIndex = parseInt(selectedObjective.substring(3));
                                                var objective = commonObjectives[commonIndex];
                                                jQuery.each(objective.params, function(key, value) {
                                                    parseObjectiveParameter(key, value);
                                                });
                                            } else {
                                                var show = objectives[selectedObjective].params;
                                                for (var i = 0; i < show.length; i++) {
                                                    $("#update-poi-objective-param-" + show[i] + "-box").show();
                                                }
                                            }
                                        });
                                    </script>
                                </div>
                                <h2><?php echo I18N::resolveHTML("poi.update.reward"); ?></h2>
                                <div class="pure-g">
                                    <div class="pure-u-5-5 full-on-mobile"><p><select id="update-poi-reward">
                                        <?php
                                            /*
                                                Select box that contains a list of all possible research rewards.
                                            */

                                            /*
                                                We'll sort the research rewards by their first respective categories.
                                                Put all the research rewards into an array ($cats) of the structure
                                                $cats[CATEGORY][RESEARCH REWARD][PARAMETERS ETC.]
                                            */
                                            $cats = array();
                                            foreach (Research::listRewards() as $reward => $data) {
                                                // Skip unknown since it shouldn't be displayed
                                                if ($reward === "unknown") continue;
                                                $cats[$data["categories"][0]][$reward] = $data;
                                            }

                                            /*
                                                After the rewards have been sorted into proper categories, we'll resolve
                                                the I18N string for each research reward, one category at a time.
                                            */
                                            foreach ($cats as $category => $categorizedRewards) {
                                                foreach ($categorizedRewards as $reward => $data) {
                                                    // Use the plural string of the reward by default
                                                    $i18n = I18N::resolve("reward.{$reward}.plural");
                                                    // If the reward is singular-only, use the singular string
                                                    if (!in_array("quantity", $data["params"]))
                                                        $i18n = I18N::resolve("reward.{$reward}.singular");
                                                    // Replace parameters (e.g. {%1}) with placeholders
                                                    for ($i = 0; $i < count($data["params"]); $i++) {
                                                        $i18n = str_replace(
                                                            "{%".($i+1)."}",
                                                            I18N::resolve("parameter.".$data["params"][$i].".placeholder"),
                                                            $i18n
                                                        );
                                                    }
                                                    // Now save the final localized string back into the reward
                                                    $categorizedRewards[$reward]["i18n"] = htmlspecialchars($i18n, ENT_QUOTES);
                                                }

                                                //uasort($categorizedRewards, "sortByI18N");

                                                /*
                                                    Create a group for each category of rewards, then output each of the
                                                    rewards within that category to the selection box.
                                                */
                                                echo '<optgroup label="'.I18N::resolveHTML("category.reward.{$category}").'">';
                                                foreach ($categorizedRewards as $reward => $data) {
                                                    echo '<option value="'.$reward.'">'.$data["i18n"].'</option>';
                                                }
                                                echo '</optgroup>';
                                            }

                                            /*
                                            $rewards = Research::listRewards();
                                            foreach ($rewards as $reward => $data) {
                                                if ($reward === "unknown") continue;
                                                $rewards[$reward]["i18n"] = I18N::resolve("reward.{$reward}.plural");
                                            }
                                            uasort($rewards, "sortByI18N");

                                            foreach ($rewards as $reward => $data) {
                                                if ($reward === "unknown") continue;
                                                $text = I18N::resolve("reward.{$reward}.plural");
                                                echo '<option value="'.$reward.'">'.$text.'</option>';
                                            }
                                            */
                                        ?>
                                    </select></p></div>
                                </div>
                                <div class="research-params reward-params">
                                    <?php
                                        /*
                                            Each reward may take one or more parameters. This part
                                            of the script ensures that all possible parameters have
                                            an input box representing them in the dialog. Parameters
                                            which are not in use will be hidden by default.
                                        */
                                        foreach (Research::PARAMETERS as $param => $class) {
                                            /*
                                                Each parameter type has a class corresponding to it,
                                                containing functions like value parsing, a function
                                                for returning an HTML node allowing users to set a
                                                value for the parameter, etc. These classes are
                                                listed and defined in /includes/data/rewards.yaml.

                                                We instantiate an instance of the class for each
                                                parameter so that we can check that the parameter
                                                is valid for rewards, and to get the HTML edit node
                                                for the parameter and output it to the page.

                                                The `html()` function of each parameter class is
                                                used to get this node, so we will call it here.
                                            */
                                            $inst = new $class();
                                            /*
                                                Each parameter can be used for objectives, rewards,
                                                or both. Ensure that the parameter can be used for
                                                rewards before we output it, to avoid unnecessary
                                                and unused elements on the page.
                                            */
                                            if (in_array("rewards", $inst->getAvailable())) {
                                                ?>
                                                    <div id="update-poi-reward-param-<?php echo $param; ?>-box"
                                                         class="pure-g research-parameter reward-parameter">
                                                        <div class="pure-u-1-3 full-on-mobile">
                                                            <p><?php echo I18N::resolveHTML("parameter.{$param}.label"); ?>:</p>
                                                        </div>
                                                        <div class="pure-u-2-3 full-on-mobile">
                                                            <?php echo $inst->html(
                                                                "update-poi-reward-param-{$param}-input",
                                                                "parameter"
                                                            ); ?>
                                                        </div>
                                                    </div>
                                                <?php
                                            }
                                        }
                                    ?>
                                    <script>
                                        function getRewardParameter(param) {
                                            switch (param) {
                                                <?php
                                                    /*
                                                        The parameter class also has a JavaScript
                                                        function to retrieve the value of the input
                                                        box(es) on for the parameter on the page,
                                                        and converting them to an object that
                                                        represents the parameter and can be stored
                                                        in a database or configuration file.

                                                        We will output all of these to a JavaScript
                                                        function called `getRewardParameter()`. We
                                                        can then call e.g.
                                                        `getRewardParameter("quantity")` and have it
                                                        return an object e.g. `5`, depending on the
                                                        data input by the user in the parameter's
                                                        input box(es).

                                                        The ID of the input box is passed to the
                                                        `writeJS()` function so that the function
                                                        can extract data from the correct input box.
                                                    */
                                                    foreach (Research::PARAMETERS as $param => $class) {
                                                        $inst = new $class();
                                                        if (in_array("rewards", $inst->getAvailable())) {
                                                            echo "case '{$param}':\n";
                                                            echo $inst->writeJS("update-poi-reward-param-{$param}-input")."\n";
                                                        }
                                                    }
                                                ?>
                                            }
                                        }
                                        function parseRewardParameter(param, data) {
                                            switch (param) {
                                                <?php
                                                    /*
                                                        The `parseRewardParameter()` function does
                                                        the exact opposite of `getRewardParameter()`
                                                        - it takes a data object, as parsed by the
                                                        latter function, and puts its value(s) into
                                                        the HTML input box(es) for the parameter on
                                                        the page, allowing the user to edit them
                                                        before being put back into a modified data
                                                        object using `getRewardParameter()`.
                                                    */
                                                    foreach (Research::PARAMETERS as $param => $class) {
                                                        $inst = new $class();
                                                        if (in_array("rewards", $inst->getAvailable())) {
                                                            echo "case '{$param}':\n";
                                                            echo $inst->parseJS("update-poi-reward-param-{$param}-input")."\n";
                                                            echo "break;\n";
                                                        }
                                                    }
                                                ?>
                                            }
                                        }
                                        /*
                                            When the reward is changed, the parameters used by that
                                            reward should be displayed, and all others should be
                                            hidden. This handler first ensures that all parameters
                                            are hidden (it loops over the server-side list of
                                            registered parameters and outputs a jQuery `hide()`
                                            statement for each of them), then loops over the list of
                                            parameters accepted by the currently selected reward and
                                            shows them.
                                        */
                                        $("#update-poi-reward").on("change", function() {
                                            <?php
                                                foreach (Research::PARAMETERS as $param => $class) {
                                                    $inst = new $class();
                                                    if (in_array("rewards", $inst->getAvailable())) {
                                                        echo "$('#update-poi-reward-param-{$param}-box').hide();";
                                                    }
                                                }
                                            ?>
                                            var show = rewards[$("#update-poi-reward").val()].params;
                                            for (var i = 0; i < show.length; i++) {
                                                $("#update-poi-reward-param-" + show[i] + "-box").show();
                                            }
                                        });
                                    </script>
                                </div>
                                <div class="cover-button-spacer"></div>
                                <div class="pure-g">
                                    <div class="pure-u-1-2 right-align">
                                        <span id="update-poi-cancel"
                                              class="button-standard split-button button-spaced left">
                                                    <?php echo I18N::resolveHTML("ui.button.cancel"); ?>
                                        </span>
                                    </div>
                                    <div class="pure-u-1-2">
                                        <span id="update-poi-submit"
                                              class="button-submit split-button button-spaced right">
                                                    <?php echo I18N::resolveHTML("poi.update.submit"); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!--
                        POI filtering dialog. If a user wishes to only display
                        some types of research tasks on the map, this dialog
                        shows up. It prompts the user for the type of objective
                        and reward that constitutes the research task, but does
                        not request task metadata (that level of granularity
                        isn't required and would make filtering needlessly
                        complicated).
                    -->
                    <div id="filters-poi" class="cover-box">
                        <div class="cover-box-inner">
                            <div class="header">
                                <h1>
                                    <?php echo I18N::resolveHTML("poi.filter.title"); ?>
                                </h1>
                            </div>
                            <div class="cover-box-content content pure-form">
                                <div class="pure-g">
                                    <div class="pure-u-1-3 full-on-mobile">
                                        <p>
                                            <?php echo I18N::resolveHTML("poi.filter.mode.title"); ?>
                                        </p>
                                    </div>
                                    <div class="pure-u-2-3 full-on-mobile">
                                        <p><select id="filter-poi-mode">
                                            <option value="only">
                                                <?php echo I18N::resolveHTML("poi.filter.mode.only"); ?>
                                            </option>
                                            <option value="except">
                                                <?php echo I18N::resolveHTML("poi.filter.mode.except"); ?>
                                            </option>
                                            <option value="unknown">
                                                <?php echo I18N::resolveHTML("poi.filter.mode.unknown"); ?>
                                            </option>
                                        </select></p>
                                    </div>
                                </div>
                                <div class="pure-g">
                                    <div class="pure-u-1-3 full-on-mobile">
                                        <p>
                                            <?php echo I18N::resolveHTML("poi.filter.objective.title"); ?>
                                        </p>
                                    </div>
                                    <div class="pure-u-2-3 full-on-mobile"><p><select id="filter-poi-objective">
                                        <!--
                                            Default settings for the filtering options.
                                        -->
                                        <option value="any">
                                            <?php echo I18N::resolveHTML("poi.filter.objective.any"); ?>
                                        </option>
                                        <?php
                                            /*
                                                Select box that contains a list of all possible research objectives.
                                            */

                                            /*
                                                We'll sort the research objectives by their first respective categories.
                                                Put all the research objectives into an array ($cats) of the structure
                                                $cats[CATEGORY][RESEARCH OBJECTIVE][PARAMETERS ETC.]
                                            */
                                            $cats = array();
                                            foreach (Research::listObjectives() as $objective => $data) {
                                                // Skip unknown since it has already been displayed
                                                if ($objective === "unknown") continue;
                                                $cats[$data["categories"][0]][$objective] = $data;
                                            }

                                            /*
                                                After the objectives have been sorted into proper categories, we'll resolve
                                                the I18N string for each research objective, one category at a time.
                                            */
                                            foreach ($cats as $category => $categorizedObjectives) {
                                                foreach ($categorizedObjectives as $objective => $data) {
                                                    // Use the plural string of the objective by default
                                                    $i18n = I18N::resolve("objective.{$objective}.plural");
                                                    // If the objective is singular-only, use the singular string
                                                    if (!in_array("quantity", $data["params"]))
                                                        $i18n = I18N::resolve("objective.{$objective}.singular");
                                                    // Replace parameters (e.g. {%1}) with placeholders
                                                    for ($i = 0; $i < count($data["params"]); $i++) {
                                                        $i18n = str_replace(
                                                            "{%".($i+1)."}",
                                                            I18N::resolve("parameter.".$data["params"][$i].".placeholder"),
                                                            $i18n
                                                        );
                                                    }
                                                    // Now save the final localized string back into the objective
                                                    $categorizedObjectives[$objective]["i18n"] = htmlspecialchars($i18n, ENT_QUOTES);
                                                }

                                                /*
                                                    Create a group for each category of objectives, then output each of the
                                                    objectives within that category to the selection box.
                                                */
                                                echo '<optgroup label="'.I18N::resolveHTML("category.objective.{$category}").'">';
                                                foreach ($categorizedObjectives as $objective => $data) {
                                                    echo '<option value="'.$objective.'">'.$data["i18n"].'</option>';
                                                }
                                                echo '</optgroup>';
                                            }
                                        ?>
                                    </select></p></div>
                                </div>
                                <div class="pure-g">
                                    <div class="pure-u-1-3 full-on-mobile">
                                        <p>
                                            <?php echo I18N::resolveHTML("poi.filter.reward.title"); ?>
                                        </p>
                                    </div>
                                    <div class="pure-u-2-3 full-on-mobile"><p><select id="filter-poi-reward">
                                        <!--
                                            Default settings for the filtering options.
                                        -->
                                        <option value="any">
                                            <?php echo I18N::resolveHTML("poi.filter.reward.any"); ?>
                                        </option>
                                        <?php
                                            /*
                                                Select box that contains a list of all possible research rewards.
                                            */

                                            /*
                                                We'll sort the research rewards by their first respective categories.
                                                Put all the research rewards into an array ($cats) of the structure
                                                $cats[CATEGORY][RESEARCH REWARD][PARAMETERS ETC.]
                                            */
                                            $cats = array();
                                            foreach (Research::listRewards() as $reward => $data) {
                                                // Skip unknown since it shouldn't be displayed
                                                if ($reward === "unknown") continue;
                                                $cats[$data["categories"][0]][$reward] = $data;
                                            }

                                            /*
                                                After the rewards have been sorted into proper categories, we'll resolve
                                                the I18N string for each research reward, one category at a time.
                                            */
                                            foreach ($cats as $category => $categorizedRewards) {
                                                foreach ($categorizedRewards as $reward => $data) {
                                                    // Use the plural string of the reward by default
                                                    $i18n = I18N::resolve("reward.{$reward}.plural");
                                                    // If the reward is singular-only, use the singular string
                                                    if (!in_array("quantity", $data["params"]))
                                                        $i18n = I18N::resolve("reward.{$reward}.singular");
                                                    // Replace parameters (e.g. {%1}) with placeholders
                                                    for ($i = 0; $i < count($data["params"]); $i++) {
                                                        $i18n = str_replace(
                                                            "{%".($i+1)."}",
                                                            I18N::resolve("parameter.".$data["params"][$i].".placeholder"),
                                                            $i18n
                                                        );
                                                    }
                                                    // Now save the final localized string back into the reward
                                                    $categorizedRewards[$reward]["i18n"] = htmlspecialchars($i18n, ENT_QUOTES);
                                                }

                                                /*
                                                    Create a group for each category of rewards, then output each of the
                                                    rewards within that category to the selection box.
                                                */
                                                echo '<optgroup label="'.I18N::resolveHTML("category.reward.{$category}").'">';
                                                foreach ($categorizedRewards as $reward => $data) {
                                                    echo '<option value="'.$reward.'">'.$data["i18n"].'</option>';
                                                }
                                                echo '</optgroup>';
                                            }
                                        ?>
                                    </select></p></div>
                                </div>
                                <div class="cover-button-spacer"></div>
                                <div class="pure-g">
                                    <div class="pure-u-1-2 right-align">
                                        <span id="filter-poi-reset"
                                              class="button-standard split-button button-spaced left">
                                                    <?php echo I18N::resolveHTML("poi.filter.reset"); ?>
                                        </span>
                                    </div>
                                    <div class="pure-u-1-2">
                                        <span id="filter-poi-submit"
                                              class="button-submit split-button button-spaced right">
                                                    <?php echo I18N::resolveHTML("poi.filter.submit"); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!--
                        The POI search overlay. The overlay is opened whenever
                        the user clicks on the "Search" button in the sidebar.
                    -->
                    <div id="search-poi" class="cover-box">
                        <div class="cover-box-inner">
                            <div class="cover-box-content content pure-form search-overlay-content">
                                <div class="pure-g">
                                    <div class="pure-u-5-5 full-on-mobile">
                                        <input type="text"
                                               id="search-overlay-input"
                                               placeholder="<?php echo I18N::resolveHTML("poi.search.placeholder"); ?>">
                                    </div>
                                </div>
                                <div class="cover-button-spacer"></div>

                                <!--
                                    Show up to 10 result rows.
                                -->
                                <?php for ($i = 0; $i < 10; $i++) { ?>
                                    <div class="pure-g search-overlay-result">
                                        <div class="pure-u-3-5 full-on-mobile search-overlay-name">?</div>
                                        <div class="pure-u-2-5 full-on-mobile search-overlay-pos">
                                            <span class="search-overlay-dir">&#x2794;</span>
                                            <span class="search-overlay-loc"></span>
                                        </div>
                                    </div>
                                <?php } ?>

                                <div class="cover-button-spacer"></div>
                                <div class="pure-g">
                                    <div class="pure-u-5-5">
                                        <span id="search-poi-close"
                                              class="button-standard split-button button-spaced left">
                                            <?php echo I18N::resolveHTML("ui.button.close"); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!--
                        The Message of the Day overlay. The overlay is opened
                        when the page is loaded, or whenever the user clicks on
                        the "Show MotD" button in the sidebar, depending on the
                        MotD display settings defined by the administrators.
                    -->
                    <?php
                        if (Config::get("motd/display-mode")->value() !== "never") {
                            ?>
                                <div id="motd-overlay" class="cover-box">
                                    <div class="cover-box-inner">
                                        <div class="header">
                                            <h1 class="head-small">
                                                <?php
                                                    $motdTitle = Config::get("motd/title")->valueHTML();
                                                    if ($motdTitle == "")
                                                        $motdTitle = I18N::resolveHTML("motd.title");

                                                    echo $motdTitle;
                                                ?>
                                            </h1>
                                        </div>
                                        <div class="cover-box-content content">
                                            <div id="motd-content">
                                                <?php
                                                    $parsedown = new Parsedown();
                                                    $parsedown->setSafeMode(true);
                                                    echo $parsedown->text(
                                                        Config::get("motd/content")->value()
                                                    );
                                                ?>
                                            </div>
                                            <div class="cover-button-spacer"></div>
                                            <?php
                                                if (Config::get("motd/display-mode")->value() === "always") {
                                                    ?>
                                                        <p class="motd-hide-paragraph">
                                                            <label for="motd-hide">
                                                                <input type="checkbox" id="motd-hide">
                                                                <?php echo I18N::resolveHTML("motd.hide"); ?>
                                                            </label>
                                                        </p>
                                                    <?php
                                                }
                                            ?>

                                            <div class="pure-g">
                                                <div class="pure-u-1-1 right-align">
                                                    <span id="motd-close"
                                                          class="button-standard split-button">
                                                        <?php echo I18N::resolveHTML("motd.close"); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php
                        }
                    ?>

                    <!--
                        "Working" indicator shown when adding a POI. Since
                        adding a POI involves a request to the server, which
                        might take some time, there should be some visual
                        indication that something is happening. This loading
                        indicator has a spinning loading icon that automatically
                        disappears when the server request is complete.
                    -->
                    <div id="poi-working-spinner" class="cover-box">
                        <div class="cover-box-inner tiny">
                            <div class="cover-box-content">
                                <div>
                                    <i class="fas fa-spinner loading-spinner spinner-large"></i>
                                </div>
                                <p id="poi-working-text"></p>
                            </div>
                        </div>
                    </div>
                    <!--
                        The container for the map itself.
                    -->
                    <div id="map" class="full-container"></div>
                </div>
                <!--
                    The user settings page. `#map` is hidden and this is shown
                    instead when the user opens the local settings menu.
                -->
                <div id="settings-container" class="full-container hidden-by-default">
                    <div class="header">
                        <h1>
                            <?php echo I18N::resolveHTML("user_settings.page.title") ?>
                        </h1>
                        <h2>
                            <?php echo I18N::resolveHTML("user_settings.page.subtitle") ?>
                        </h2>
                    </div>
                    <div class="content pure-form">
                        <form action="apply-settings.php"
                              id="user-settings-form"
                              method="POST"
                              enctype="application/x-www-form-urlencoded">
                            <!--
                                Protection against CSRF
                            -->
                            <?php echo Security::getCSRFInputField(); ?>
                            <h2 class="content-subhead">
                                <?php echo I18N::resolveHTML("user_settings.section.account") ?>
                            </h2>
                            <?php
                                if (
                                    Auth::getCurrentUser()->exists() &&
                                    Auth::getCurrentUser()->hasPermission("self-manage/nickname")
                                ) {
                                    ?>
                                        <!--
                                            Change nickname.
                                        -->
                                        <div class="pure-g">
                                            <div class="pure-u-1-3 full-on-mobile">
                                                <p class="setting-name">
                                                    <?php echo I18N::resolveHTML("user_setting.nickname.name"); ?>:
                                                </p>
                                            </div>
                                            <div class="pure-u-2-3 full-on-mobile">
                                                <p><input type="text"
                                                          name="nickname"
                                                          value="<?php echo htmlspecialchars(
                                                              Auth::getCurrentUser()->getNickname(),
                                                              ENT_QUOTES
                                                          ); ?>"></p>
                                            </div>
                                        </div>
                                    <?php
                                }
                            ?>
                            <!--
                                Sign out everywhere/invalidate sessions.
                            -->
                            <div class="pure-g">
                                <div class="pure-u-1-3 full-on-mobile">
                                    <p class="setting-name">
                                        <?php echo I18N::resolveHTML("user_setting.sign_out_everywhere.name"); ?>:
                                    </p>
                                </div>
                                <div class="pure-u-2-3 full-on-mobile">
                                    <p>
                                        <?php echo I18N::resolveHTML("user_setting.sign_out_everywhere.info"); ?>
                                    </p>
                                    <p><input type="button"
                                              id="sign-out-everywhere"
                                              name="sign-out-everywhere"
                                              class="button-standard"
                                              value="<?php echo I18N::resolveHTML(
                                                  "user_setting.sign_out_everywhere.button"
                                              ); ?>"></p>
                                </div>
                            </div>
                            <h2 class="content-subhead">
                                <?php echo I18N::resolveHTML("user_settings.section.map_providers") ?>
                            </h2>
                            <!--
                                Directions provider for navigation links.
                            -->
                            <div class="pure-g">
                                <div class="pure-u-1-3 full-on-mobile">
                                    <p class="setting-name">
                                        <?php echo I18N::resolveHTML("user_setting.directions_provider.name"); ?>:
                                    </p>
                                </div>
                                <div class="pure-u-2-3 full-on-mobile">
                                    <p><select class="user-setting" data-key="naviProvider">
                                        <option value=""><?php echo I18N::resolveHTML("user_settings.value.default"); ?></option>
                                        <?php
                                            $naviProviders = Geo::listNavigationProviders();
                                            foreach ($naviProviders as $naviProvider => $url) {
                                                echo '<option value="'.$naviProvider.'">
                                                        '.I18N::resolveHTML("setting.map.provider.directions.option.{$naviProvider}").'
                                                      </option>';
                                            }
                                        ?>
                                    </select></p>
                                </div>
                            </div>
                            <h2 class="content-subhead">
                                <?php echo I18N::resolveHTML("user_settings.section.appearance") ?>
                            </h2>
                            <?php
                                if (Config::get("themes/color/user-settings/allow-personalization")->value()) {
                                    ?>
                                        <!--
                                            User interface theme (dark or
                                            light.) This is separate from the
                                            map theme.
                                        -->
                                        <div class="pure-g">
                                            <div class="pure-u-1-3 full-on-mobile">
                                                <p class="setting-name">
                                                    <?php echo I18N::resolveHTML("user_setting.interface_theme.name"); ?>:
                                                </p>
                                            </div>
                                            <div class="pure-u-2-3 full-on-mobile">
                                                <p><select class="user-setting" data-key="theme">
                                                    <option value="">
                                                        <?php echo I18N::resolveHTML("user_settings.value.default"); ?>
                                                    </option>
                                                    <option value="light">
                                                        <?php echo I18N::resolveHTML("setting.themes.color.user_settings.theme.option.light"); ?>
                                                    </option>
                                                    <option value="dark">
                                                        <?php echo I18N::resolveHTML("setting.themes.color.user_settings.theme.option.dark"); ?>
                                                    </option>
                                                </select></p>
                                            </div>
                                        </div>
                                    <?php
                                }
                            ?>
                            <?php
                                if (Config::get("themes/color/map/allow-personalization")->value()) {
                                    ?>
                                        <div class="pure-g">
                                            <!--
                                                Map theme (i.e. color scheme for
                                                map elements).
                                            -->
                                            <div class="pure-u-1-3 full-on-mobile">
                                                <p class="setting-name"><?php echo I18N::resolveHTML("user_setting.map_theme.name"); ?>:</p>
                                            </div>
                                            <div class="pure-u-2-3 full-on-mobile">
                                                <?php
                                                    switch (Config::get("map/provider/source")->value()) {
                                                        case "mapbox":
                                                            $opt = Config::get("themes/color/map/theme/mapbox")->getOption();
                                                            ?>
                                                                <p><select class="user-setting" data-key="mapStyle-mapbox">
                                                                    <option value=""><?php echo I18N::resolveHTML("user_settings.value.default"); ?></option>
                                                                    <?php foreach ($opt->getItems() as $item) { ?>
                                                                        <option value="<?php echo $item; ?>"><?php echo $opt->getLabelI18N($item, null, "setting.themes.color.map.theme.mapbox.option"); ?></option>
                                                                    <?php } ?>
                                                                </select></p>
                                                            <?php
                                                            break;
                                                        case "thunderforest":
                                                            $opt = Config::get("themes/color/map/theme/thunderforest")->getOption();
                                                            ?>
                                                                <p><select class="user-setting" data-key="mapStyle-thunderforest">
                                                                    <option value=""><?php echo I18N::resolveHTML("user_settings.value.default"); ?></option>
                                                                    <?php foreach ($opt->getItems() as $item) { ?>
                                                                        <option value="<?php echo $item; ?>"><?php echo $opt->getLabelI18N($item, null, "setting.themes.color.map.theme.thunderforest.option"); ?></option>
                                                                    <?php } ?>
                                                                </select></p>
                                                            <?php
                                                            break;
                                                    }
                                                ?>
                                            </div>
                                        </div>
                                    <?php
                                }
                            ?>
                            <?php
                                if (Auth::getCurrentUser()->hasPermission("personalization/icons")) {
                                    $opt = new IconSetOption("user_settings.value.default");
                                    ?>
                                        <!--
                                            Icon set used for map markers.
                                        -->
                                        <div class="pure-g option-block-follows">
                                            <div class="pure-u-1-3 full-on-mobile">
                                                <p class="setting-name"><?php echo I18N::resolveHTML("user_setting.icons.name"); ?>:</p>
                                            </div>
                                            <div class="pure-u-2-3 full-on-mobile">
                                                <p>
                                                    <?php echo $opt->getControl(null, array(
                                                        "id" => "icon-selector",
                                                        "data-key" => "iconSet",
                                                        "class" => "user-setting"
                                                    )); ?>
                                                </p>
                                            </div>
                                        </div>
                                        <?php
                                            echo $opt->getFollowingBlock();
                                        ?>
                                    <?php
                                }
                            ?>
                            <?php
                                if (Auth::getCurrentUser()->hasPermission("personalization/species")) {
                                    $opt = new SpeciesSetOption("user_settings.value.default");
                                    ?>
                                        <!--
                                            Icon set used for species markers.
                                        -->
                                        <div class="pure-g option-block-follows">
                                            <div class="pure-u-1-3 full-on-mobile">
                                                <p class="setting-name"><?php echo I18N::resolveHTML("user_setting.species.name"); ?>:</p>
                                            </div>
                                            <div class="pure-u-2-3 full-on-mobile">
                                                <p>
                                                    <?php echo $opt->getControl(null, array(
                                                        "id" => "species-selector",
                                                        "data-key" => "speciesSet",
                                                        "class" => "user-setting"
                                                    )); ?>
                                                </p>
                                            </div>
                                        </div>
                                        <?php
                                            echo $opt->getFollowingBlock();
                                        ?>
                                    <?php
                                }
                            ?>
                            <!--
                                Which markers are displayed on the map (research
                                objectives or research rewards).
                            -->
                            <div class="pure-g option-block-follows">
                                <div class="pure-u-1-3 full-on-mobile">
                                    <p class="setting-name"><?php echo I18N::resolveHTML("user_setting.marker_component.name"); ?>:</p>
                                </div>
                                <div class="pure-u-2-3 full-on-mobile">
                                    <p><select class="user-setting" data-key="markerComponent">
                                        <option value=""><?php echo I18N::resolveHTML("user_settings.value.default"); ?></option>
                                        <option value="objective"><?php echo I18N::resolveHTML("setting.map.default.marker_component.option.objective"); ?></option>
                                        <option value="reward"><?php echo I18N::resolveHTML("setting.map.default.marker_component.option.reward"); ?></option>
                                    </select></p>
                                </div>
                            </div>
                            <h2 class="content-subhead">
                                <?php echo I18N::resolveHTML("user_settings.section.performance") ?>
                            </h2>
                            <div class="pure-g option-block-follows">
                                <div class="pure-u-1-3 full-on-mobile">
                                    <p class="setting-name"><?php echo I18N::resolveHTML("user_setting.cluster_limit.name"); ?>:</p>
                                </div>
                                <div class="pure-u-2-3 full-on-mobile">
                                    <p><select class="user-setting" data-key="clusteringLimit">
                                        <option value=""><?php echo I18N::resolveArgsHTML(
                                            "user_setting.cluster_limit.option.default",
                                            true,
                                            Config::get("map/default/cluster-limit")->value()
                                        ); ?></option>
                                        <option value="10">10</option>
                                        <option value="25">25</option>
                                        <option value="50">50</option>
                                        <option value="75">75</option>
                                        <option value="100">100</option>
                                        <option value="150">150</option>
                                        <option value="200">200</option>
                                        <option value="250">250</option>
                                        <option value="300">300</option>
                                        <option value="400">400</option>
                                        <option value="500">500</option>
                                        <option value="750">700</option>
                                        <option value="1000">1000</option>
                                        <option value="1250">1250</option>
                                        <option value="1500">1500</option>
                                        <option value="1750">1750</option>
                                        <option value="2000">2000</option>
                                        <option value="2500">2500</option>
                                        <option value="3000">3000</option>
                                        <option value="4000">4000</option>
                                        <option value="5000">5000</option>
                                        <option value="6000">6000</option>
                                        <option value="7500">7500</option>
                                        <option value="10000">10000</option>
                                    </select></p>
                                </div>
                            </div>
                            <p class="buttons">
                                <input type="submit"
                                       class="button-submit"
                                       value="<?php echo I18N::resolveHTML("ui.button.save"); ?>">
                            </p>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <script>
            /*
                Objectives and rewards directories. These are copied from
                /includes/data/objectives.yaml and /includes/data/*.yaml.
            */
            var objectives = <?php echo json_encode(Research::listObjectives()); ?>;
            var rewards = <?php echo json_encode(Research::listRewards()); ?>;
            var commonObjectives = <?php echo json_encode(Research::listCommonObjectives()); ?>;

            /*
                List of all navigation providers and their navigation URLs.
            */
            var naviProviders = <?php echo json_encode(Geo::listNavigationProviders()); ?>;

            /*
                Time interval (in milliseconds) between automatic refreshes of
                the marker list and active field research from the server.
            */
            var autoRefreshInterval = <?php echo (Config::get("map/updates/refresh-interval")->value() * 1000); ?>;

            /*
                Message of the Day settings. The `motdHash` is a SHA-256 hash of
                the contents of the MotD content. This is used to detect changes
                in the message in case the MotD should be displayed whenever the
                message is changed. `motdDisplay` is the Message of the Day
                display policy of the site.
            */
            var motdHash = <?php echo json_encode(hash("sha256", Config::get("motd/content")->value())); ?>;
            var motdDisplay = <?php echo Config::get("motd/display-mode")->valueJS(); ?>;

            /*
                Default local settings, used as fallback if a local setting is
                not explicitly set for each entry.
            */
            var defaults = {
                "iconSet": <?php echo Config::get("themes/icons/default")->valueJS(); ?>,
                "speciesSet": <?php echo Config::get("themes/species/default")->valueJS(); ?>,
                "mapProvider": "<?php echo $provider; ?>",
                "naviProvider": <?php echo Config::get("map/provider/directions")->valueJS(); ?>,
                "mapStyle-mapbox": <?php echo Config::get("themes/color/map/theme/mapbox")->valueJS(); ?>,
                "mapStyle-thunderforest": <?php echo Config::get("themes/color/map/theme/thunderforest")->valueJS(); ?>,
                "theme": <?php echo Config::get("themes/color/user-settings/theme")->valueJS(); ?>,
                "center": {
                    latitude: <?php echo Config::get("map/default/center/latitude")->valueJS(); ?>,
                    longitude: <?php echo Config::get("map/default/center/longitude")->valueJS(); ?>
                },
                "zoom": <?php echo Config::get("map/default/zoom")->valueJS(); ?>,
                "markerComponent": <?php echo Config::get("map/default/marker-component")->valueJS(); ?>,
                "motdCurrentHash": "",
                "motdDismissedHash": "",
                "clusteringLimit": "<?php echo Config::get("map/default/cluster-limit")->valueJS(); ?>"
            };

            /*
                Administrators may specify settings that should forcibly assume
                the default value and ignore user-specific preferences. The
                paths of all such settings in the settings tree are added to
                this array. The contents are determined through various settings
                on the administration pages.
            */
            var forceDefaults = [
                <?php
                    $forced = array('"mapProvider"');
                    if (!Config::get("themes/color/user-settings/allow-personalization")->value()) {
                        $forced[] = '"theme"';
                    }
                    if (!Config::get("themes/color/map/allow-personalization")->value()) {
                        $forced[] = '"mapStyle-mapbox"';
                        $forced[] = '"mapStyle-thunderforest"';
                    }
                    if (!Auth::getCurrentUser()->hasPermission("personalization/icons")) {
                        $forced[] = '"iconSet"';
                    }
                    echo implode(', ', $forced);
                ?>
            ];

            /*
                Make a clone (deep copy) of the defaults object to override with
                users' own values.
            */
            var settings = $.extend(true, {}, defaults);

            /*
                All local user settings that have `<select>` inputs have a
                "default" option to indicate that the value is inherited from
                the server-side default setting determined by the
                administrators. When a new user loads FreeField, their
                `settings` object are populated with a clone of the `defaults`
                object (see the line above).

                This means that the settings that are `<select>` inputs are pre-
                populated with the default values, rather than the "default"
                option of the selection box. Since the default option for all
                `<select>` options is an empty string (""), we can set the
                values of those settings in the `settings` object to empty
                strings before they are overwritten by any customized settings
                from localStorage.

                Below, we set the values of all settings that are set using
                `<select>` boxes to empty strings by default to ensure that the
                "default" option is selected for them in the selection boxes
                rather than the actual default values above. If an empty string
                is defined for any setting, the fallback will be used when the
                setting's value is called for, though the empty string itself is
                returned when the value is queried so that the correct value is
                chosen in the settings box.

                For example, if `theme` is set to "", and the server-side
                default is "dark", the theme of the page will be dark, but the
                selection box that allows users to choose the theme in the
                settings page will have the "default" setting selected rather
                than "dark".
            */
            $("select.user-setting").each(function() {
                /*
                    Find the setting key from the `data-key` attribute of the
                    select box, then declare the value of the setting that key
                    represents to be an empty string.
                */
                var s = $(this).attr("data-key").split("/");
                var value = "";
                /*
                    Push the setting change to `settings`. The process and
                    thinking behind these loops are described in detail in
                    /includes/lib/config.php, which uses the same saving
                    procedure for the server-side configuration file.
                */
                for (var i = s.length - 1; i >= 0; i--) {
                    /*
                        Loop over the segments and for every iteration, find the
                        parent array directly above the current `s[i]`.
                    */
                    var parent = settings;
                    for (var j = 0; j < i; j++) {
                        parent = parent[s[j]];
                    }
                    /*
                        Update the value of `s[i]` in the array. Store a copy of
                        this array as the value to assign to the next parent
                        segment.
                    */
                    parent[s[i]] = value;
                    value = parent;
                    /*
                        The next iteration finds the next parent above the
                        current parent and replaces the value of the key in that
                        parent which would hold the value of the current parent
                        array with the updated parent array that has the setting
                        change applied to it.
                    */
                }
            });

            /*
                A function for getting settings. This takes two arguments.

                key
                    The path to the setting that is being queried. For example,
                    "center/latitude".

                ignoreDefault
                    Whether or not "" should be resolved to the default value
                    (false) or if "" should be returned directly in those cases
                    (true). Optional - defaults to `false`.
            */
            settings.get = function(key, ignoreDefault) {
                /*
                    Set `ignoreDefault` to `false` if it is not currently set.
                */
                ignoreDefault = ignoreDefault || false;

                /*
                    Since the settings object is arranged a object with subkeys,
                    we have to iterate deeper into the object's tree structure
                    until we hit the setting we need. To do so, we split the
                    path of the setting we're looking for by the separator / to
                    get an array where each element is the next child of the
                    settings object. We make a copy of the `settings` object to
                    `value`. We then search `value` for the first item of the
                    path segments array. When found, `value` is replaced with
                    the value of `value[tree[i]]`, and the loop continues, but
                    this time searching for the first child of that object, i.e.
                    the second segment of the path. This continues until we've
                    found the correct setting, at which point `value` will be
                    the value we're looking for and that can be returned.
                */
                var tree = key.split("/");
                var value = settings;
                for (var i = 0; i < tree.length; i++) {
                    value = value[tree[i]];
                }

                /*
                    If `forceDefaults`, the list of keys that must forcibly set
                    to default independent of the user's preference, has an
                    entry for the current settings path, we must overwrite the
                    value we found from `settings` with the corresponding value
                    from `defaults`.

                    If the value we found was an empty string (""), and the
                    caller of this function didn't explicitly request for the
                    empty string to be returned through `ignoreDefault`, we will
                    also replace the value with the corresponding default value
                    from `defaults`.
                */
                if (
                    forceDefaults.indexOf(key) >= 0 ||
                    (!ignoreDefault && value == "")
                ) {
                    value = defaults;
                    for (var i = 0; i < tree.length; i++) {
                        value = value[tree[i]];
                    }
                }

                return value;
            };

            /*
                A reference to certain permissions that are used client-side.
                These are also validated server-side, but in order to provide a
                good user experience, some page elements may additionally be
                hidden client-side if some of these permissions are not granted.
            */
            var permissions =
                <?php
                    $clientside_perms = array(
                        /*
                            Allows the user to report field research. If this is
                            not granted, the button that the user would click on
                            to report field research is hidden.
                        */
                        "report-research",
                        /*
                            Allows the user to report field research even if
                            someone else has already done so on that POI earlier
                            the same day. "report-research" is required in
                            addition to this permission for this permission to
                            have any effect. If this permission is not granted,
                            it has the same effect as if "report-research" was
                            not granted, but only for POIs that already have
                            active field research tasks assigned to them.
                        */
                        "overwrite-research",
                        /*
                            Allows the user to report evilness on POIs. If this
                            is not allowed, the button is hidden.
                        */
                        "report-evil",
                        /*
                            Allows the user to manage POIs. If this is not
                            granted, the buttons that the user would click on to
                            move or delete POIs are hidden.
                        */
                        "admin/pois/general"
                    );

                    $permsJson = array();
                    foreach ($clientside_perms as $perm) {
                        $permsJson[$perm] = Auth::getCurrentUser()->hasPermission($perm);
                    }
                    echo json_encode($permsJson);
                ?>;

            /*
                A reference to all available icon sets and the URLs they provide
                for various icon graphics.
            */
            var iconSets = <?php
                /*
                    List all possible icons and all available icon sets.
                */
                $icons = Theme::listIcons();
                $themes = Theme::listIconSets();

                $output = array();

                /*
                    If the administrators have configured FreeField to deny
                    users selecting their own icon sets, then only the icon sets
                    defined in this array should be loaded. By default, all icon
                    sets are loaded.
                */
                $restrictiveLoadThemes = array(
                    Config::get("themes/icons/default")->value()
                );

                foreach ($themes as $theme) {
                    if (
                        !Auth::getCurrentUser()->hasPermission("personalization/icons") &&
                        !in_array($theme, $restrictiveLoadThemes)
                    ) {
                        return;
                    }

                    /*
                        Get an `IconSet` instance for each theme (from
                        /includes/lib/theme.php). Use this instance to grab an
                        URL for every icon defined in `$icons`.
                    */
                    $iconSet = Theme::getIconSet($theme);
                    foreach ($icons as $icon) {
                        $output[$theme][$icon] = $iconSet->getIconUrl($icon);
                    }
                }

                echo json_encode($output);
            ?>;

            /*
                Echo the `$linkMod` array for usage when requesting content in
                /js/main.js.
            */
            var linkMod = <?php echo json_encode($linkMod); ?>;

            /*
                Echo the current page language for usage in /js/main.js.
            */
            var currentLanguage = <?php echo json_encode(I18N::getLanguage()); ?>;

            /*
                Echo the default time that evilness lasts.
            */
            var defaultEvilDuration = <?php echo POI::EVIL_DURATION; ?>;

            /*
                The language selection menu in the sidebar.
            */
            $("#menu-language-select").on("change", function(e) {
                window.location.href = "./apply-language.php?<?php echo Security::getCSRFUrlParameter(); ?>&lang="
                                     + $(this).val();
            });
        </script>
        <script src="./js/ui.js"></script>
        <?php
            /*
                Load the map implementation script for the chosen map provider.
            */
            switch (Config::get("map/provider/source")->value()) {
                case "mapbox":
                    ?>
                        <script src="https://api.mapbox.com/mapbox-gl-js/v0.46.0/mapbox-gl.js"></script>
                        <script src="./js/map-impl/mapbox-impl.js?t=<?php echo filemtime("./js/map-impl/mapbox-impl.js"); ?>"></script>
                        <script>
                            MapImpl.preInit({
                                apiKey: <?php echo Config::get("map/provider/mapbox/access-token")->valueJS(); ?>
                            });
                        </script>

                    <?php
                    break;
                case "thunderforest":
                    ?>
                        <script src="https://unpkg.com/leaflet@1.3.4/dist/leaflet.js"
                                integrity="sha512-nMMmRyTVoLYqjP9hrbed9S+FzjZHW5gY1TWCHA5ckwXZBadntCNs8kEqAWdrb9O7rxbCaA4lKTIWjDXZxflOcA=="
                                crossorigin=""></script>
                        <script src="./js/map-impl/leaflet-impl.js?t=<?php echo filemtime("./js/map-impl/leaflet-impl.js"); ?>"></script>
                        <script src="https://cdn.jsdelivr.net/npm/leaflet.locatecontrol@0.63.0/dist/L.Control.Locate.min.js"
                                integrity="sha256-fDXZX7+frvNEVCWnwZ9MFVEn0ADry+xkmJC93dU9xKk="
                                crossorigin="anonymous"></script>
                        <script>
                            MapImpl.preInit({
                                url: 'https://tile.thunderforest.com/{providerTheme}/{z}/{x}/{y}{r}.png?apikey={apiKey}',
                                theme: 'mapStyle-thunderforest',
                                params: {
                                    attribution: '&copy; <a href="http://www.thunderforest.com/">Thunderforest</a>, &copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                                    apiKey: <?php echo Config::get("map/provider/thunderforest/api-key")->valueJS(); ?>,
                                    maxZoom: 20
                                }
                            });
                        </script>

                    <?php
                    break;
            }
        ?>
        <script src="./js/main.js?t=<?php echo $linkMod["/js/main.js"]; ?>"></script>
    </body>
</html>
