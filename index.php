<?php

require_once("./includes/lib/global.php");
__require("config");
__require("i18n");
__require("theme");
__require("research");

$provider = Config::get("map/provider/source");

?>

<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
        <meta name="robots" content="noindex,nofollow">
        <title><?php echo Config::get("site/name"); ?></title>
        <script src="https://api.mapbox.com/mapbox-gl-js/v0.46.0/mapbox-gl.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js" integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8=" crossorigin="anonymous"></script>
        <script src="./js/clientside-i18n.php"></script>
        <link rel="stylesheet" href="https://api.mapbox.com/mapbox-gl-js/v0.46.0/mapbox-gl.css">
        <link rel="stylesheet" href="https://unpkg.com/purecss@1.0.0/build/pure-min.css" integrity="sha384-nn4HPE8lTHyVtfCBi5yW9d20FjT8BJwUXyWZT9InLYax14RDjBj46LmSztkmNP9w" crossorigin="anonymous">
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.0.13/css/all.css" integrity="sha384-DNOHZ68U8hZfKXOrtjWvjxusGo9WQnrNx2sqG0tfsghAvtVlRW3tvkXWZh58N9jp" crossorigin="anonymous">
        <link rel="stylesheet" href="./css/main.css?v=<?php echo time(); ?>">
        <link rel="stylesheet" href="./css/<?php echo Config::get("themes/color/user-settings/theme"); ?>.css?v=<?php echo time(); ?>">

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
                    <a class="pure-menu-heading" href=".">FreeField</a>

                    <ul class="pure-menu-list">
                        <li class="pure-menu-item"><a href="./auth/login.php" class="pure-menu-link"><i class="menu-fas fas fa-sign-in-alt"></i> Sign in</a></li>
                    </ul>
                </div>
            </div>

            <div id="main">
                <div id="dynamic-banner-container">

                </div>
                <div id='map' style='width: 100%; height: 100vh;'></div>
                <script>
                    mapboxgl.accessToken = '<?php echo Config::get("map/provider/mapbox/access-token"); ?>';
                    var map = new mapboxgl.Map({
                        container: 'map',
                        style: 'mapbox://styles/mapbox/<?php echo Config::get("themes/color/map/theme/mapbox"); ?>-v9',
                        center: [<?php echo Config::get("map/default/center/longitude"); ?>, <?php echo Config::get("map/default/center/latitude"); ?>],
                        zoom: <?php echo Config::get("map/default/zoom"); ?>
                    });
                    map.addControl(new mapboxgl.NavigationControl());
                </script>
            </div>
        </div>

        <script>
            var objectives = <?php echo json_encode(Research::OBJECTIVES); ?>;
            var rewards = <?php echo json_encode(Research::REWARDS); ?>;

            var defaults = {
                iconSet: "<?php echo Config::get("themes/icons/default"); ?>",
                mapProvider: "<?php echo $provider; ?>",
                mapStyle: "<?php echo Config::get("themes/color/map/theme/{$provider}"); ?>",
                theme: "<?php echo Config::get("themes/color/user-settings/theme"); ?>"
            };

            var settings = defaults;

            var iconSets = {
                <?php
                    $icons = Theme::listIcons();

                    $themes = Theme::listIconSets();
                    $themejs = array();
                    $restrictiveLoadThemes = array(
                        Config::get("themes/icons/default")
                    );
                    foreach ($themes as $theme) {
                        if (!Config::get("themes/icons/allow-personalization") && in_array($theme, $restrictiveLoadThemes)) return;

                        $iconSet = Theme::getIconSet($theme);
                        $iconKv = array();
                        foreach ($icons as $icon) {
                            $iconKv[] = "'{$icon}': '".$iconSet->getIconUrl($icon)."'";
                        }
                        $themejs[] = "'{$theme}': {".implode(", ", $iconKv)."}";
                    }

                    echo implode(", ", $themejs);
                ?>
            };
        </script>
        <script src="./js/ui.js"></script>
        <script src="./js/main.js?t="<?php echo time(); ?>></script>
    </body>
</html>
