<?php
/*
    This script outputs a stylesheet that defines theme colors for the site.
*/

require_once("../includes/lib/global.php");
__require("config");

header("Content-Type: text/css");

/*
    Determine whether or not we're using dark or light theme.
*/
if (!isset($_GET["light"]) && !isset($_GET["dark"])) {
    header("HTTP/1.1 400 Bad Request");
    exit;
}
$lightTheme = isset($_GET["light"]);

/*
    Define colors to use.
*/
$lightColor = Config::get("themes/color/site")->value();
list($r, $g, $b) = sscanf($lightColor, "#%02x%02x%02x");
$r /= 2; $g /= 2; $b /= 2;
$darkColor = sprintf("#%02x%02x%02x", floor($r), floor($g), floor($b));

$themeColor = $lightTheme ? $lightColor : $darkColor;
$inverseColor = $lightTheme ? $darkColor : $lightColor;

?>
/* The target of the webhook (domain name, group ID or other identifier)
   displayed in the header bar. */
.hook-domain {
    color: <?php echo $lightColor; ?>;
}

/* This styles the selected menu item `<li>`. */
#menu .pure-menu-selected, #menu .pure-menu-heading {
    background: <?php echo $lightColor; ?> !important;
}

/* Submit button on forms. Highlighted in a vibrant color to visually indicate
   that this button applies changes. */
.button-submit {
    background-color: <?php echo $themeColor; ?>;
}

/* Basic banner configuration for the map. */
.banner {
    background-color: <?php echo $lightColor; ?>;
}

a {
    color: <?php echo $inverseColor; ?>;
}
