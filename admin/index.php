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
$domains = array("main", "perms", "security", "auth", "themes", "map");
$pages_icons = array(
    "main" => "cog",
    "users" => "users",
    "groups" => "user-shield",
    "perms" => "check-square",
    "security" => "shield-alt",
    "auth" => "lock",
    "themes" => "palette",
    "map" => "map",
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

class CustomControls {
    /*
        For rendering custom controls. $control can be:
        - field: The input field area.
        - after: A dedicated area below the configurator.
    */
    public static function getControl($control, $path, $current, $section) {
        switch ($control) {
            case "icon-selector":
                global $control_iconSelectorID;
                if ($section == "field") {
                    $themepath = __DIR__."/../themes/icons";
                    $themes = array_diff(scandir($themepath), array('..', '.'));
                    $options = "";
                    $themedata = array();
                    $control_iconSelectorID = bin2hex(openssl_random_pseudo_bytes(4));
                    foreach ($themes as $theme) {
                        if (!file_exists("{$themepath}/{$theme}/pack.ini")) continue;
                        $data = parse_ini_file("{$themepath}/{$theme}/pack.ini", true);
                        $themedata[$theme] = $data;
                        $options .= '<option id="iconselector-'.$control_iconSelectorID.'" value="'.$theme.'"'.($current == $theme ? ' selected' : '').'>'.$data["name"].' (by '.$data["author"].')</option>';
                    }
                    return '
                        <select name="'.$path.'">'.$options.'</select>
                        <script type="text/javascript">
                            var themedata = '.json_encode($themedata, JSON_PRETTY_PRINT).';

                            function viewTheme_'.$control_iconSelectorID.'(theme) {
                                var box = document.getElementById("iconviewer-'.$control_iconSelectorID.'");
                                box.innerHTML = "";

                                var variants = ["light", "dark"];
                                var varbox = {};

                                for (var i = 0; i < variants.length; i++) {
                                    varbox[variants[i]] = document.createElement("div");
                                    varbox[variants[i]].style.width = "calc(100% - 20px)";
                                    varbox[variants[i]].style.padding = "10px";
                                }

                                varbox["light"].style.backgroundColor = "#ccc";
                                varbox["dark"].style.backgroundColor = "#333";

                                var tdata = themedata[theme];

                                var icons = [
                                    "potion", "super_potion", "hyper_potion", "max_potion",
                                    "revive", "max_revive",
                                    "fast_tm", "charge_tm",
                                    "stardust", "rare_candy", "encounter",
                                    "battle", "raid",
                                    "catch", "throwing_skill", "hatch",
                                    "power_up", "evolve",
                                    "unknown"
                                ];

                                for (var i = 0; i < icons.length; i++) {
                                    var uri = "../themes/icons/" + theme + "/";
                                    if (tdata.hasOwnProperty("vector") && tdata["vector"].hasOwnProperty(icons[i])) {
                                        uri += tdata["vector"][icons[i]];
                                    } else if (tdata.hasOwnProperty("vector") && tdata["vector"].hasOwnProperty(icons[i])) {
                                        uri += tdata["raster"][icons[i]];
                                    } else {
                                        uri = "about:blank";
                                    }

                                    for (var j = 0; j < variants.length; j++) {
                                        var icobox = document.createElement("img");
                                        icobox.src = uri.split("{%variant%}").join(variants[j]);
                                        icobox.style.width = "68px";
                                        icobox.style.height = "68px";
                                        icobox.style.margin = "5px";
                                        varbox[variants[j]].appendChild(icobox);
                                    }
                                }

                                if (tdata.hasOwnProperty("logo")) {
                                    var logo = document.createElement("img");
                                    logo.src = "../themes/icons/" + theme + "/" + tdata["logo"].split("{%variant%}").join("'.Config::get("themes/color/admin").'");
                                    logo.style.width = "400px";
                                    logo.style.maxWidth = "100%";
                                    logo.marginTop = "20px";
                                    box.appendChild(logo);
                                }

                                var name = document.createElement("h2");
                                name.innerText = tdata.name;
                                name.style.color = "#'.(Config::get("themes/color/admin") == "dark" ? "ccc" : "333").'";
                                name.style.marginBottom = "0";
                                box.appendChild(name);

                                var author = document.createElement("p");
                                author.innerText = "Authored by " + tdata.author;
                                box.appendChild(author);

                                for (var i = 0; i < variants.length; i++) {
                                    box.appendChild(varbox[variants[i]]);
                                }

                            }
                        </script>
                    ';
                } elseif ($section == "after") {
                    return '
                        <div style="width: 100%;" id="iconviewer-'.$control_iconSelectorID.'"></div>
                        <script type="text/javascript">
                            var selector_'.$control_iconSelectorID.' = document.getElementById("iconselector-'.$control_iconSelectorID.'");
                            viewTheme_'.$control_iconSelectorID.'(selector_'.$control_iconSelectorID.'.value);
                            selector_'.$control_iconSelectorID.'.addEventListener("select", function() {
                                viewTheme_'.$control_iconSelectorID.'(selector_'.$control_iconSelectorID.'.value);
                            });
                        </script>
                    ';
                }
        }
        return null;
    }
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
        <link rel="stylesheet" href="../css/main.css">
        <link rel="stylesheet" href="../css/<?php echo Config::get("themes/color/admin"); ?>.css">

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
                        <form action="apply-config.php?d=<?php echo $domain; ?>" method="POST" class="pure-form" enctype="application/x-www-form-urlencoded">
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
                                                    if (isset($values["custom"])) {
                                                        $value = Config::get($setting);
                                                        echo CustomControls::getControl($values["custom"], $setting, $value, "field");
                                                    } else {
                                                        $matches = array();
                                                        if (is_array($values["options"])) {
                                                            echo '<select name="'.$setting.'">';
                                                            $value = Config::get($setting);
                                                            foreach ($values["options"] as $option) {
                                                                echo '<option value="'.$option.'"'.($value == $option ? ' selected' : '').'>'.I18N::resolve($si18n->getOption($option)).'</option>';
                                                            }
                                                            echo '</select>';

                                                        } elseif (preg_match('/^int,([\d-]+),([\d-]+)$/', $values["options"], $matches)) {
                                                            echo '<input type="number" name="'.$setting.'" min="'.$matches[1].'" max="'.$matches[2].'" value="'.Config::get($setting).'">';

                                                        } elseif (preg_match('/^float,([\d-]+),([\d-]+)$/', $values["options"], $matches)) {
                                                            echo '<input type="number" name="'.$setting.'" min="'.$matches[1].'" max="'.$matches[2].'" step="0.00001" value="'.Config::get($setting).'">';

                                                        } else {
                                                            switch ($values["options"]) {
                                                                case "string":
                                                                    echo '<input type="text" name="'.$setting.'" value="'.Config::get($setting).'">';
                                                                    break;
                                                                case "password":
                                                                    echo '<input type="password" name="'.$setting.'" value="'.Config::get($setting).'">';
                                                                    break;
                                                                case "int":
                                                                    echo '<input type="number" name="'.$setting.'" value="'.Config::get($setting).'">';
                                                                    break;
                                                                case "float":
                                                                    echo '<input type="number" name="'.$setting.'" step="0.00001" value="'.Config::get($setting).'">';
                                                                    break;
                                                                case "bool":
                                                                    echo '<input type="hidden" name="'.$setting.'" value="off">'; // Detect unchecked checkbox - unchecked checkboxes aren't POSTed!
                                                                    echo '<input type="checkbox" id="'.$setting.'" name="'.$setting.'"'.(Config::get($setting) ? ' checked' : '').'> <label for="'.$setting.'">'.I18N::resolve($si18n->getLabel()).'</label>';
                                                                    break;
                                                            }
                                                        }
                                                    }
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                    <?php
                                        if (isset($values["custom"])) {
                                            $control = CustomControls::getControl($values["custom"], $setting, $value, "after");
                                            if ($control !== null) {
                                                echo $control;
                                            }
                                        }
                                    ?>
                                <?php } ?>
                            <?php } ?>
                            <p class="buttons"><input type="submit" class="button-submit" value="<?php echo I18N::resolve("ui.button.save"); ?>"></p>
                        </form>
                    <?php } elseif ($domain == "users") { ?>

                    <?php } ?>
                </div>
            </div>
        </div>
        <script src="../js/ui.js"></script>
    </body>
</html>
