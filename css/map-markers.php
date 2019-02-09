<?php
/*
    This script outputs a stylesheet that defines map markers and their icon
    locations for each installed icon set in the format:

        .marker.<icon_set_name>.<icon>.<color_theme{dark|light}> {
            background-image: url('<icon_url>');
        }
*/

require_once("../includes/lib/global.php");
__require("theme");
__require("config");
__require("research");

header("Content-Type: text/css");

/*
    Lists all icons that are valid for use in an icon set, including fallbacks
    and icon categories.
*/
$icons = Theme::listIcons();

/*
    Lists all installed icon sets.
*/
$themes = Theme::listIconSets();

/*
    List of themes to restrict loading to if personalization of themes is not
    allowed.
*/
$restrictiveLoadThemes = array(
    Config::get("themes/icons/default")->value()
);

$variants = array("dark", "light");
foreach ($themes as $theme) {
    /*
        If the site adminstrators have disabled personalization, then any themes
        which is not in the approved list of themes should be rejected and not
        displayed.
    */
    if (
        !Config::get("themes/icons/allow-personalization")->value() &&
        in_array($theme, $restrictiveLoadThemes)
    ) return;

    /*
        Loop over all of the markers and output a CSS rule for each of them.
    */
    $iconSet = Theme::getIconSet($theme);
    $iconKv = array();
    foreach ($icons as $icon) {
        foreach ($variants as $variant) {
            /*
                Switch between light and dark variants to ensure all icons are
                output.
            */
            $iconSet->setVariant($variant);
            echo ".marker.{$theme}.{$icon}.{$variant} {background-image: url('".$iconSet->getIconUrl($icon)."');}\n";
        }
    }
}

/*
    Lists all installed species sets.
*/
$spThemes = Theme::listSpeciesSets();

/*
    List of themes to restrict loading to if personalization of themes is not
    allowed.
*/
$restrictiveLoadThemes = array(
    Config::get("themes/species/default")->value()
);

foreach ($spThemes as $theme) {
    /*
        If the site adminstrators have disabled personalization, then any themes
        which is not in the approved list of themes should be rejected and not
        displayed.
    */
    if (
        !Config::get("themes/species/allow-personalization")->value() &&
        in_array($theme, $restrictiveLoadThemes)
    ) return;

    /*
        Loop over all of the markers and output a CSS rule for each of them.
    */
    $iconSet = Theme::getSpeciesSet($theme);
    $iconKv = array();
    for ($i = 1; $i <= ParamSpecies::getHighestSpecies(); $i++) {
        foreach ($variants as $variant) {
            /*
                Switch between light and dark variants to ensure all icons are
                output.
            */
            $iconSet->setVariant($variant);
            echo ".marker.{$theme}.sp-{$i}.{$variant} {background-image: url('".$iconSet->getIconUrl($i)."');}\n";
        }
    }
}

?>
