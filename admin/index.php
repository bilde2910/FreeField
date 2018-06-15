<?php

require_once("../includes/lib/global.php");
__require("config");
__require("auth");
__require("i18n");

// TODO: Kick users out of this page if they don't have admin perms
// TODO: User, groups, permissions, webhooks

if (!isset($_GET["d"])) {
    header("HTTP/1.1 307 Temporary Redirect");
    header("Location: ./?d=main");
    exit;
}

$domain = $_GET["d"];
$domains = array("main", "perms", "security", "auth");
$pages_icons = array(
    "main" => "cog",
    "users" => "users",
    "groups" => "user-shield",
    "perms" => "check-square",
    "security" => "shield-alt",
    "auth" => "lock",
    "hooks" => "link"
);

if (!isset($_GET["d"])) {
    header("HTTP/1.1 307 Temporary Redirect");
    header("Location: ./?d=main");
    exit;
}

$di18n = Config::getDomainI18N($domain);
if (in_array($domain, $domains)) {
    $sections = Config::getTreeDomain($domain);
}

?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex,nofollow">
        <title>FreeField Admin | <?php echo I18N::resolve($di18n->getName()); ?></title>
        <link rel="stylesheet" href="https://unpkg.com/purecss@1.0.0/build/pure-min.css" integrity="sha384-nn4HPE8lTHyVtfCBi5yW9d20FjT8BJwUXyWZT9InLYax14RDjBj46LmSztkmNP9w" crossorigin="anonymous">
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.0.13/css/all.css" integrity="sha384-DNOHZ68U8hZfKXOrtjWvjxusGo9WQnrNx2sqG0tfsghAvtVlRW3tvkXWZh58N9jp" crossorigin="anonymous">
        <link rel="stylesheet" href="./css/main.css">
        
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

            <div id="menu">
                <div class="pure-menu">
                    <a class="pure-menu-heading" href="..">Freefield</a>

                    <ul class="pure-menu-list">
                        <?php
                        
                        foreach ($pages_icons as $d => $icon) {
                            if ($d == $domain) {
                                echo '<li class="pure-menu-item menu-item-divided pure-menu-selected">';
                            } else {
                                echo '<li class="pure-menu-item">';
                            }
                            
                            echo '<a href="./?d='.$d.'" class="pure-menu-link"><i class="menu-fas fas fa-'.$icon.'"></i> '.I18N::resolve(Config::getDomainI18N($d)->getName()).'</a></li>';
                        }
                        
                        ?>
                        
                    </ul>
                </div>
            </div>

            <div id="main">
                <div class="header">
                    <h1><?php echo I18N::resolve($di18n->getName()); ?></h1>
                    <h2><?php echo I18N::resolve($di18n->getDescription()); ?></h2>
                </div>

                <div class="content">
                    <?php if (in_array($domain, $domains)) { ?>
                        <form action="apply-config.php?d=<?php echo $domain; ?>" method="POST" enctype="application/x-www-form-urlencoded">
                            <?php foreach ($sections as $section => $settings) { ?>
                                <h2 class="content-subhead"><?php echo I18N::resolve($di18n->getSection($section)->getName()); ?></h2>
                                <?php
                                    if (isset($settings["__hasdesc"]) && $settings["__hasdesc"]) {
                                        if (isset($settings["__descsprintf"])) {
                                            echo '<p>'.I18N::resolveArgs($di18n->getSection($section)->getDescription(), $settings["__descsprintf"]).'</p>';
                                        } else {
                                            echo '<p>'.I18N::resolve($di18n->getSection($section)->getDescription()).'</p>';
                                        }
                                    }
                                ?>
                                <?php foreach ($settings as $setting => $values) { ?>
                                    <?php
                                        if (substr($setting, 0, 2) === "__") continue;
                                        $si18n = Config::getSettingI18N($setting);
                                    ?>
                                    <div class="pure-g">
                                        <div class="pure-u-1-3 full-on-mobile">
                                            <p class="setting-name"><?php echo I18N::resolve($si18n->getName()); ?><span class="only-desktop">: <span class="tooltip"><i class="content-fas fas fa-question-circle"></i><span><?php echo I18N::resolve($si18n->getDescription()); ?></span></span></span></p>
                                            <p class="only-mobile"><?php echo I18N::resolve($si18n->getDescription()); ?></p>
                                        </div>
                                        <div class="pure-u-2-3 full-on-mobile">
                                            <p>
                                                <?php
                                                    $matches = array();
                                                    if (is_array($values["options"])) {
                                                        echo '<select class="pure-u-5-5" name="'.$setting.'">';
                                                        $value = Config::get($setting);
                                                        foreach ($values["options"] as $option) {
                                                            echo '<option value="'.$option.'"'.($value == $option ? ' selected' : '').'>'.I18N::resolve($si18n->getOption($option)).'</option>';
                                                        }
                                                        echo '</select>';
                                                        
                                                    } elseif (preg_match('/^int,([\d-]+),([\d-]+)$/', $values["options"], $matches)) {
                                                        echo '<input type="number" class="pure-u-5-5" name="'.$setting.'" min="'.$matches[1].'" max="'.$matches[2].'" value="'.Config::get($setting).'">';
                                                        
                                                    } else {
                                                        switch ($values["options"]) {
                                                            case "string":
                                                                echo '<input type="text" class="pure-u-5-5" name="'.$setting.'" value="'.Config::get($setting).'">';
                                                                break;
                                                            case "password":
                                                                echo '<input type="password" class="pure-u-5-5" name="'.$setting.'" value="'.Config::get($setting).'">';
                                                                break;
                                                            case "int":
                                                                echo '<input type="number" class="pure-u-5-5" name="'.$setting.'" value="'.Config::get($setting).'">';
                                                                break;
                                                            case "bool":
                                                                echo '<input type="hidden" name="'.$setting.'" value="off">'; // Detect unchecked checkbox - unchecked checkboxes aren't POSTed!
                                                                echo '<input type="checkbox" id="'.$setting.'" name="'.$setting.'"'.(Config::get($setting) ? ' checked' : '').'> <label for="'.$setting.'">'.I18N::resolve($si18n->getLabel()).'</label>';
                                                                break;
                                                        }
                                                    }
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php } ?>
                            <?php } ?>
                            <p class="buttons"><input type="submit" class="button-submit" value="<?php echo I18N::resolve("admin.button.save"); ?>"></p>
                        </form>
                    <?php } ?>
                </div>
            </div>
        </div>
        <script src="js/ui.js"></script>
    </body>
</html>
