<?php

require_once("../includes/lib/global.php");
__require("config");
__require("auth");
__require("i18n");
__require("geo");
__require("theme");

$domains = array(
    "main" => array(
        "icon" => "cog",
        "custom-handler" => false
    ),
    "users" => array(
        "icon" => "users",
        "custom-handler" => true
    ),
    "groups" => array(
        "icon" => "user-shield",
        "custom-handler" => true
    ),
    "pois" => array(
        "icon" => "map-marker-alt",
        "custom-handler" => true
    ),
    "perms" => array(
        "icon" => "check-square",
        "custom-handler" => false
    ),
    "security" => array(
        "icon" => "shield-alt",
        "custom-handler" => false
    ),
    "auth" => array(
        "icon" => "lock",
        "custom-handler" => false
    ),
    "themes" => array(
        "icon" => "palette",
        "custom-handler" => false
    ),
    "map" => array(
        "icon" => "map",
        "custom-handler" => false
    ),
    "hooks" => array(
        "icon" => "link",
        "custom-handler" => true
    )
);

if (!isset($_GET["d"]) || !in_array($_GET["d"], array_keys($domains)) || !Auth::getCurrentUser()->hasPermission("admin/".$_GET["d"]."/general")) {
    $firstAuthorized = null;
    foreach ($domains as $page => $data) {
        if (Auth::getCurrentUser()->hasPermission("admin/{$page}/general")) {
            $firstAuthorized = urlencode($page);
            break;
        }
    }
    header("HTTP/1.1 307 Temporary Redirect");
    if ($firstAuthorized == null) {
        header("Location: ".Config::getEndpointUri("/"));
    } else {
        header("Location: ./?d={$firstAuthorized}");
    }
    exit;
}

$domain = $_GET["d"];

$di18n = Config::getDomainI18N($domain);
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
        <title>FreeField Admin | <?php echo I18N::resolveHTML($di18n->getName()); ?></title>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js" integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8=" crossorigin="anonymous"></script>
        <link rel="stylesheet" href="https://unpkg.com/purecss@1.0.0/build/pure-min.css" integrity="sha384-nn4HPE8lTHyVtfCBi5yW9d20FjT8BJwUXyWZT9InLYax14RDjBj46LmSztkmNP9w" crossorigin="anonymous">
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.0.13/css/all.css" integrity="sha384-DNOHZ68U8hZfKXOrtjWvjxusGo9WQnrNx2sqG0tfsghAvtVlRW3tvkXWZh58N9jp" crossorigin="anonymous">
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
                            <span class="user-box-small"><?php echo I18N::resolveHTML("sidebar.signed_in_as"); ?></span><br>
                            <span class="user-box-nick"><?php echo Auth::getCurrentUser()->getNicknameHTML(); ?></span><br />
                            <span class="user-box-small"><?php echo Auth::getCurrentUser()->getProviderIdentityHTML(); ?></span><br>
                        </div>
                        <li class="pure-menu-item"><a href="./auth/logout.php" class="pure-menu-link"><i class="menu-fas fas fa-sign-in-alt"></i> <?php echo I18N::resolveHTML("sidebar.logout"); ?></a></li>
                        <div class="menu-spacer"></div>
                        <?php

                        foreach ($domains as $d => $domaindata) {
                            if (!Auth::getCurrentUser()->hasPermission("admin/{$d}/general")) continue;
                            if ($d == $domain) {
                                echo '<li class="pure-menu-item menu-item-divided pure-menu-selected">';
                            } else {
                                echo '<li class="pure-menu-item">';
                            }

                            echo '<a href="./?d='.$d.'" class="pure-menu-link"><i class="menu-fas fas fa-'.$domaindata["icon"].'"></i> '.I18N::resolveHTML(Config::getDomainI18N($d)->getName()).'</a></li>';
                        }

                        ?>

                        <div class="menu-spacer"></div>
                        <li class="pure-menu-item"><a href=".." class="pure-menu-link"><i class="menu-fas fas fa-angle-double-left"></i> <?php echo I18N::resolveHTML("sidebar.return"); ?></a></li>
                    </ul>
                </div>
            </div>

            <div id="main">
                <div class="header">
                    <h1><?php echo I18N::resolveHTML($di18n->getName()); ?></h1>
                    <h2><?php echo I18N::resolveHTML($di18n->getDescription()); ?></h2>
                </div>

                <?php
                    if (!$domains[$domain]["custom-handler"]) {
                ?>
                    <div class="content">
                        <form action="apply-config.php?d=<?php echo urlencode($domain); ?>" method="POST" class="pure-form require-validation" enctype="application/x-www-form-urlencoded">
                            <?php foreach ($sections as $section => $settings) { ?>
                                <h2 class="content-subhead"><?php echo I18N::resolveHTML($di18n->getSection($section)->getName()); ?></h2>
                                <?php
                                    if (isset($settings["__hasdesc"]) && $settings["__hasdesc"]) {
                                        if (isset($settings["__descsprintf"])) {
                                            echo '<p>'.I18N::resolveArgsHTML($di18n->getSection($section)->getDescription(), false, $settings["__descsprintf"]).'</p>';
                                        } else {
                                            echo '<p>'.I18N::resolveHTML($di18n->getSection($section)->getDescription()).'</p>';
                                        }
                                    }
                                ?>
                                <?php foreach ($settings as $setting => $values) { ?>
                                    <?php
                                        if (substr($setting, 0, 2) === "__") continue;
                                        $si18n = Config::getSettingI18N($setting);
                                        $option = $values["option"];
                                        $value = Config::get($setting);
                                    ?>
                                    <div class="pure-g">
                                        <div class="pure-u-1-3 full-on-mobile">
                                            <p class="setting-name"><?php echo I18N::resolveHTML($si18n->getName()); ?><span class="only-desktop">: <span class="tooltip"><i class="content-fas fas fa-question-circle"></i><span><?php echo I18N::resolveHTML($si18n->getDescription()); ?></span></span></span></p>
                                            <p class="only-mobile"><?php echo I18N::resolveHTML($si18n->getDescription()); ?></p>
                                        </div>
                                        <div class="pure-u-2-3 full-on-mobile">
                                            <p>
                                                <?php
                                                    echo $option->getControl($value, $setting, $setting);
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                    <?php
                                        echo $option->getFollowingBlock();
                                    ?>
                                <?php } ?>
                            <?php } ?>
                            <p class="buttons"><input type="submit" class="button-submit" value="<?php echo I18N::resolveHTML("ui.button.save"); ?>"></p>
                        </form>
                    </div>
                <?php
                    } else {
                        include("../includes/admin/{$domain}.php");
                    }
                ?>
            </div>
        </div>
        <script>
            var validationFailedMessage = <?php echo I18N::resolveJS("admin.validation.validation_failed"); ?>;
            var unsavedChangesMessage = <?php echo I18N::resolveJS("admin.validation.unsaved_changes"); ?>;

            function validateInput(e) {
                if (e.is("[data-validate-as]")) {
                    var type = e.attr("data-validate-as");
                    var value = e.val();
                    switch (type) {
                        case "json":
                            try {
                                JSON.parse(value);
                            } catch (e) {
                                return false;
                            }
                            return true;
                        case "html":
                            var div = document.createElement("div");
                            var filtered = value.replace(/<%[^%\(]+(\([^\)]+\))?%>/g, "");
                            div.innerHTML = filtered;
                            return div.innerHTML === filtered;
                        case "http-uri":
                            return value.match(/^https?\:\/\//);
                        case "tg-uri":
                            return value.match(/^tg\:\/\/send\?to=?/);
                        case "regex-string":
                            return value.match(new RegExp(e.attr("data-validate-regex")));
                        case "geofence":
                            return value === "" || value.match(/^(\r\n?|\n\r?)*(-?(90|[1-8]?\d(\.\d+)?),-?(180|(1[0-7]\d|[1-9]?\d)(\.\d+)?)(\r\n?|\n\r?)*){3,}$/);
                        case "text":
                            return true;
                    }
                }
            }
            $("body").on("input", "[data-validate-as]", function() {
                if (validateInput($(this))) {
                    $(this).css("border", "");
                } else {
                    $(this).css("border", "1px solid red");
                }
            });
            $("form.require-validation").on("submit", function(e) {
                var valid = true;
                $(this).find("[data-validate-as]").each(function() {
                    if (validateInput($(this))) {
                        $(this).css("border", "");
                    } else {
                        valid = false;
                        $(this).css("border", "1px solid red");
                    }
                });
                if (!valid) {
                    e.preventDefault();
                    alert(validationFailedMessage);
                } else {
                    unsavedChanges = false;
                }
            });

            var unsavedChanges = false;
            $("form").on("change", ":input", function() {
                unsavedChanges = true;
            });
            $(window).on("beforeunload", function() {
                if (unsavedChanges) {
                    return unsavedChangesMessage;
                }
            });
        </script>
        <script src="../js/ui.js"></script>
    </body>
</html>
