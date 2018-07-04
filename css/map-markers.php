<?php

require_once("../includes/lib/global.php");
__require("theme");
__require("config");

header("Content-Type: text/css");

$icons = Theme::listIcons();

$themes = Theme::listIconSets();
$themejs = array();
$restrictiveLoadThemes = array(
    Config::get("themes/icons/default")
);
$variants = array("dark", "light");
foreach ($themes as $theme) {
    if (!Config::get("themes/icons/allow-personalization") && in_array($theme, $restrictiveLoadThemes)) return;

    $iconSet = Theme::getIconSet($theme);
    $iconKv = array();
    foreach ($icons as $icon) {
        foreach ($variants as $variant) {
            $iconSet->setVariant($variant);
            echo ".marker.{$theme}.{$icon}.{$variant} {background-image: url('".$iconSet->getIconUrl($icon)."');}\n";
        }
    }
}

?>
