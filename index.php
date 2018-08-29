<?php
/*
    This is the main page of FreeField. It contains the map that the whole
    project revolves around.
*/

require_once("./includes/lib/global.php");
__require("auth");
__require("i18n");

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
        ?>
            <!DOCTYPE html>
            <html>
                <head>
                    <meta charset="utf-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1">
                    <meta name="robots" content="noindex,nofollow">
                    <title><?php echo Config::get("site/name"); ?> | <?php echo I18N::resolveI18N("login.title"); ?></title>
                    <link rel="stylesheet"
                          href="https://unpkg.com/purecss@1.0.0/build/pure-min.css"
                          integrity="sha384-nn4HPE8lTHyVtfCBi5yW9d20FjT8BJwUXyWZT9InLYax14RDjBj46LmSztkmNP9w"
                          crossorigin="anonymous">
                    <link rel="stylesheet"
                          href="https://use.fontawesome.com/releases/v5.0.13/css/all.css"
                          integrity="sha384-DNOHZ68U8hZfKXOrtjWvjxusGo9WQnrNx2sqG0tfsghAvtVlRW3tvkXWZh58N9jp"
                          crossorigin="anonymous">
                    <link rel="stylesheet" href="./css/main.css">
                    <link rel="stylesheet" href="./css/<?php echo Config::getHTML("themes/color/user-settings/theme"); ?>.css">

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

/*
    A string identifying the chosen map provider for FreeField.
*/
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
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"
                integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8="
                crossorigin="anonymous"></script>
        <script src="./js/clientside-i18n.php"></script>
        <link rel="stylesheet"
              href="https://api.mapbox.com/mapbox-gl-js/v0.46.0/mapbox-gl.css">
        <link rel="stylesheet"
              href="https://unpkg.com/purecss@1.0.0/build/pure-min.css"
              integrity="sha384-nn4HPE8lTHyVtfCBi5yW9d20FjT8BJwUXyWZT9InLYax14RDjBj46LmSztkmNP9w"
              crossorigin="anonymous">
        <link rel="stylesheet"
              href="https://use.fontawesome.com/releases/v5.0.13/css/all.css"
              integrity="sha384-DNOHZ68U8hZfKXOrtjWvjxusGo9WQnrNx2sqG0tfsghAvtVlRW3tvkXWZh58N9jp"
              crossorigin="anonymous">
        <link rel="stylesheet" href="./css/main.css?v=<?php echo time(); ?>">
        <link rel="stylesheet" href="./css/map-markers.php">

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
                        <?php if (Auth::isAuthenticated()) { ?>
                            <div class="menu-user-box">
                                <span class="user-box-small">
                                    <?php echo I18N::resolveHTML("sidebar.signed_in_as"); ?>
                                </span><br>
                                <span class="user-box-nick">
                                    <?php echo Auth::getCurrentUser()->getNicknameHTML(); ?>
                                </span><br />
                                <span class="user-box-small">
                                    <?php echo Auth::getCurrentUser()->getProviderIdentityHTML(); ?>
                                </span><br>
                            </div>
                            <li class="pure-menu-item">
                                <a href="./auth/logout.php" class="pure-menu-link">
                                    <i class="menu-fas fas fa-sign-in-alt"></i>
                                    <?php echo I18N::resolveHTML("sidebar.logout"); ?>
                                </a>
                            </li>
                        <?php } else { ?>
                            <li class="pure-menu-item">
                                <a href="./auth/login.php" class="pure-menu-link">
                                    <i class="menu-fas fas fa-sign-in-alt"></i>
                                    <?php echo I18N::resolveHTML("sidebar.login"); ?>
                                </a>
                            </li>
                        <?php } ?>
                        <div class="menu-spacer"></div>
                        <div id="map-menu">
                            <?php if (Auth::getCurrentUser()->hasPermission("submit-poi")) { ?>
                                <li class="pure-menu-item">
                                    <a href="#" id="add-poi-start" class="pure-menu-link">
                                        <i class="menu-fas fas fa-plus"></i>
                                        <?php echo I18N::resolveHTML("sidebar.add_poi"); ?>
                                    </a>
                                </li>
                            <?php } ?>
                            <li class="pure-menu-item">
                                <a href="#" id="menu-open-settings" class="pure-menu-link">
                                    <i class="menu-fas fas fa-wrench"></i>
                                    <?php echo I18N::resolveHTML("sidebar.settings"); ?>
                                </a>
                            </li>
                            <?php
                                /* Check if user has permission to access any admin pages. */
                                if (Auth::getCurrentUser()->hasPermission("admin/?/general")) {
                                    ?>
                                        <li class="pure-menu-item">
                                            <a href="./admin/" class="pure-menu-link">
                                                <i class="menu-fas fas fa-angle-double-right"></i>
                                                <?php echo I18N::resolveHTML("sidebar.manage_site"); ?>
                                            </a>
                                        </li>
                                    <?php
                                }
                            ?>
                        </div>
                        <div id="settings-menu" class="hidden-by-default">
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
                        </div>
                    </ul>
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
                    POI details overlay. Contains details such as the POI's
                    name, its current active field research, means of reporting
                    research to the POI (if permission is granted to do so), and
                    a button to get directions to the POI on a turn-based
                    navigation service. The overlay is opened whenever the user
                    clicks on a marker on the map.
                -->
                <div id="poi-details" class="cover-box">
                    <div class="cover-box-inner">
                        <div class="header">
                            <h1 id="poi-name" class="head-small"></h1>
                        </div>
                        <div class="cover-box-content content">
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
                            <div class="cover-button-spacer"></div>
                            <div class="pure-g">
                                <div class="pure-u-1-1 right-align">
                                    <span type="button" id="poi-add-report"
                                          class="button-standard split-button">
                                        <?php echo I18N::resolveHTML("poi.report_research"); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="pure-g">
                                <div class="pure-u-1-2 right-align">
                                    <span type="button"
                                          id="poi-directions"
                                          class="button-standard split-button button-spaced left">
                                        <?php echo I18N::resolveHTML("poi.directions"); ?>
                                    </span>
                                </div>
                                <div class="pure-u-1-2">
                                    <span type="button"
                                          id="poi-close"
                                          class="button-standard split-button button-spaced right">
                                        <?php echo I18N::resolveHTML("ui.button.close"); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!--
                    New POI overlay that's shown when the user is adding a new
                    POI to the map, and they have clicked on the location on the
                    map where they wish the new POI to be added. The overlay
                    dialog asks for the name of the POI to be added, and
                    confirms its coordinates.
                -->
                <div id="add-poi-details" class="cover-box">
                    <div class="cover-box-inner">
                        <div class="header">
                            <h1><?php echo I18N::resolveHTML("poi.add.title"); ?></h1>
                        </div>
                        <div class="cover-box-content content pure-form">
                            <div class="pure-g">
                                <div class="pure-u-1-3 full-on-mobile">
                                    <p><?php echo I18N::resolveHTML("poi.add.name"); ?>:</p>
                                </div>
                                <div class="pure-u-2-3 full-on-mobile">
                                    <p><input type="text" id="add-poi-name"></p>
                                </div>
                            </div>
                            <div class="pure-g">
                                <div class="pure-u-1-3 full-on-mobile">
                                    <p><?php echo I18N::resolveHTML("poi.add.latitude"); ?>:</p>
                                </div>
                                <div class="pure-u-2-3 full-on-mobile">
                                    <p><input type="text" id="add-poi-lat" readonly></p>
                                </div>
                            </div>
                            <div class="pure-g">
                                <div class="pure-u-1-3 full-on-mobile">
                                    <p><?php echo I18N::resolveHTML("poi.add.longitude"); ?>:</p>
                                </div>
                                <div class="pure-u-2-3 full-on-mobile">
                                    <p><input type="text" id="add-poi-lon" readonly></p>
                                </div>
                            </div>
                            <div class="cover-button-spacer"></div>
                            <div class="pure-g">
                                <div class="pure-u-1-2 right-align">
                                    <span type="button"
                                          id="add-poi-cancel"
                                          class="button-standard split-button button-spaced left">
                                        <?php echo I18N::resolveHTML("ui.button.cancel"); ?>
                                    </span>
                                </div>
                                <div class="pure-u-1-2">
                                    <span type="button"
                                          id="add-poi-submit"
                                          class="button-submit split-button button-spaced right">
                                        <?php echo I18N::resolveHTML("poi.add.submit"); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!--
                    Field research reporting dialog. If a user wishes to report
                    field research on a POI, this dialog shows up. It prompts
                    the user for the type of objective and reward that
                    constitutes the research task, and requests objective and
                    reward metadata (parameters, such as a quantity of typing)
                    as well, if applicable.
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
                                            We'll sort the research objectives by their first respective categories.
                                            Put all the research objectives into an array ($cats) of the structure
                                            $cats[CATEGORY][RESEARCH OBJECTIVE][PARAMETERS ETC.]
                                        */
                                        $cats = array();
                                        foreach (Research::OBJECTIVES as $objective => $data) {
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
                                        $objectives = Research::OBJECTIVES;
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
                                            listed and defined in /includes/lib/research.php.

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
                                                        <p><?php echo $inst->html(
                                                            "update-poi-objective-param-{$param}-input",
                                                            "parameter"
                                                        ); ?></p>
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
                                        var show = objectives[$("#update-poi-objective").val()].params;
                                        for (var i = 0; i < show.length; i++) {
                                            $("#update-poi-objective-param-" + show[i] + "-box").show();
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
                                        foreach (Research::REWARDS as $reward => $data) {
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
                                        $rewards = Research::REWARDS;
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
                                            listed and defined in /includes/lib/research.php.

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
                                                        <p><?php echo $inst->html(
                                                            "update-poi-reward-param-{$param}-input",
                                                            "parameter"
                                                        ); ?></p>
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
                                    <span type="button"
                                          id="update-poi-cancel"
                                          class="button-standard split-button button-spaced left">
                                                <?php echo I18N::resolveHTML("ui.button.cancel"); ?>
                                    </span>
                                </div>
                                <div class="pure-u-1-2">
                                    <span type="button"
                                          id="update-poi-submit"
                                          class="button-submit split-button button-spaced right">
                                                <?php echo I18N::resolveHTML("poi.update.submit"); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!--
                    "Working" indicator shown when adding a POI. Since adding a
                    POI involves a request to the server, which might take some
                    time, there should be some visual indication that something
                    is happening. This loading indicator has a spinning loading
                    icon that automatically disappears when the server request
                    is complete.
                -->
                <div id="add-poi-working" class="cover-box">
                    <div class="cover-box-inner tiny">
                        <div class="cover-box-content">
                            <div>
                                <i class="fas fa-spinner loading-spinner spinner-large"></i>
                            </div>
                            <p>
                                <?php echo I18N::resolveHTML("poi.add.processing"); ?>
                            </p>
                        </div>
                    </div>
                </div>
                <!--
                    "Working" indicator for reporting field research. This is
                    functionally the same as `#add-poi-working`, but with a
                    different text label.
                -->
                <div id="update-poi-working" class="cover-box">
                    <div class="cover-box-inner tiny">
                        <div class="cover-box-content">
                            <div>
                                <i class="fas fa-spinner loading-spinner spinner-large"></i>
                            </div>
                            <p>
                                <?php echo I18N::resolveHTML("poi.update.processing"); ?>
                            </p>
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
                        <h1>Settings</h1>
                        <h2>Personalize your experience</h2>
                    </div>
                    <div class="content pure-form">
                        <h2 class="content-subhead">Map providers</h2>
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
                                        <option value="bing"><?php echo I18N::resolveHTML("setting.map.provider.directions.option.bing"); ?></option>
                                        <option value="google"><?php echo I18N::resolveHTML("setting.map.provider.directions.option.google"); ?></option>
                                        <option value="here"><?php echo I18N::resolveHTML("setting.map.provider.directions.option.here"); ?></option>
                                        <option value="mapquest"><?php echo I18N::resolveHTML("setting.map.provider.directions.option.mapquest"); ?></option>
                                        <option value="waze"><?php echo I18N::resolveHTML("setting.map.provider.directions.option.waze"); ?></option>
                                        <option value="yandex"><?php echo I18N::resolveHTML("setting.map.provider.directions.option.yandex"); ?></option>
                                    </select></p>
                                </div>
                            </div>
                        <h2 class="content-subhead">Appearance</h2>
                        <?php
                            if (Config::get("themes/color/user-settings/allow-personalization")) {
                                ?>
                                    <!--
                                        User interface theme (dark or light).
                                        This is separate from the map theme.
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
                            if (Config::get("themes/color/map/allow-personalization")) {
                                ?>
                                    <div class="pure-g">
                                        <!--
                                            Map theme (i.e. color scheme for map
                                            elements).
                                        -->
                                        <div class="pure-u-1-3 full-on-mobile">
                                            <p class="setting-name"><?php echo I18N::resolveHTML("user_setting.map_theme.name"); ?>:</p>
                                        </div>
                                        <div class="pure-u-2-3 full-on-mobile">
                                            <p><select class="user-setting" data-key="mapStyle/mapbox">
                                                <option value=""><?php echo I18N::resolveHTML("user_settings.value.default"); ?></option>
                                                <option value="basic"><?php echo I18N::resolveHTML("setting.themes.color.map.theme.mapbox.option.basic"); ?></option>
                                                <option value="streets"><?php echo I18N::resolveHTML("setting.themes.color.map.theme.mapbox.option.streets"); ?></option>
                                                <option value="bright"><?php echo I18N::resolveHTML("setting.themes.color.map.theme.mapbox.option.bright"); ?></option>
                                                <option value="light"><?php echo I18N::resolveHTML("setting.themes.color.map.theme.mapbox.option.light"); ?></option>
                                                <option value="dark"><?php echo I18N::resolveHTML("setting.themes.color.map.theme.mapbox.option.dark"); ?></option>
                                                <option value="satellite"><?php echo I18N::resolveHTML("setting.themes.color.map.theme.mapbox.option.satellite"); ?></option>
                                            </select></p>
                                        </div>
                                    </div>
                                <?php
                            }
                        ?>
                        <?php
                            if (Config::get("themes/icons/allow-personalization")) {
                                $opt = new IconPackOption("user_settings.value.default");
                                ?>
                                    <!--
                                        Icon set used for map markers.
                                    -->
                                    <div class="pure-g">
                                        <div class="pure-u-1-3 full-on-mobile">
                                            <p class="setting-name"><?php echo I18N::resolveHTML("user_setting.icons.name"); ?>:</p>
                                        </div>
                                        <div class="pure-u-2-3 full-on-mobile">
                                            <p>
                                                <?php echo $opt->getControl(
                                                    null,
                                                    null,
                                                    "icon-selector",
                                                    array("data-key" => "iconSet", "class" => "user-setting")
                                                ); ?>
                                            </p>
                                        </div>
                                    </div>
                                    <?php
                                        echo $opt->getFollowingBlock();
                                    ?>
                                <?php
                            }
                        ?>
                        <p class="buttons">
                            <input type="button"
                                   id="user-settings-save"
                                   class="button-submit"
                                   value="<?php echo I18N::resolveHTML("ui.button.save"); ?>">
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <script>
            /*
                Objectives and rewards directories. These are copied from
                /includes/lib/research.php.
            */
            var objectives = <?php echo json_encode(Research::OBJECTIVES); ?>;
            var rewards = <?php echo json_encode(Research::REWARDS); ?>;

            /*
                Default local settings, used as fallback if a local setting is
                not explicitly set for each entry.
            */
            var defaults = {
                iconSet: <?php echo Config::getJS("themes/icons/default"); ?>,
                mapProvider: "<?php echo $provider; ?>",
                naviProvider: <?php echo Config::getJS("map/provider/directions"); ?>,
                mapStyle: {
                    mapbox: <?php echo Config::getJS("themes/color/map/theme/{$provider}"); ?>
                },
                theme: <?php echo Config::getJS("themes/color/user-settings/theme"); ?>,
                center: {
                    latitude: <?php echo Config::getJS("map/default/center/latitude"); ?>,
                    longitude: <?php echo Config::getJS("map/default/center/longitude"); ?>
                },
                zoom: <?php echo Config::getJS("map/default/zoom"); ?>
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
                    $forced = array();
                    if (!Config::get("themes/color/user-settings/allow-personalization")) {
                        $forced[] = '"theme"';
                    }
                    if (!Config::get("themes/color/map/allow-personalization")) {
                        $forced[] = '"mapStyle/mapbox"';
                    }
                    if (!Config::get("themes/icons/allow-personalization")) {
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
                The settings below are set to empty strings by default to ensure
                that the "default" option is selected for them in the selection
                boxes rather than the actual default values above. If an empty
                string is defined for any setting, the fallback will be used
                when the setting's value is called for, though the empty string
                itself is returned when the value is queried so that the correct
                value is chosen in the settings box.

                For example, if `theme` is set to "", and the server-side
                default is "dark", the theme of the page will be dark, but the
                selection box that allows users to choose the theme in the
                settings page will have the "default" setting selected rather
                than "dark".
            */
            settings.theme = "";
            settings.mapStyle.mapbox = "";

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
            var permissions = {
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
                        "overwrite-research"
                    );

                    for ($i = 0; $i < count($clientside_perms); $i++) {
                        $clientside_perms[$i] = '"'.$clientside_perms[$i].'": '.
                                                (
                                                    Auth::getCurrentUser()->hasPermission($clientside_perms[$i])
                                                    ? "true"
                                                    : "false"
                                                );
                    }
                    echo implode(", ", $clientside_perms);
                ?>
            }

            /*
                A reference to all available icon sets and the URLs they provide
                for various icon graphics.
            */
            var iconSets = {
                <?php
                    /*
                        List all possible icons and all available icon sets.
                    */
                    $icons = Theme::listIcons();
                    $themes = Theme::listIconSets();

                    $themejs = array();

                    /*
                        If the administrators have configured FreeField to deny
                        users selecting their own icon sets, then only the icon
                        sets defined in this array should be loaded. By default,
                        all icon sets are loaded.
                    */
                    $restrictiveLoadThemes = array(
                        Config::get("themes/icons/default")
                    );

                    foreach ($themes as $theme) {
                        if (
                            !Config::get("themes/icons/allow-personalization") &&
                            in_array($theme, $restrictiveLoadThemes)
                        ) {
                            return;
                        }

                        /*
                            Get an `IconSet` instance for each theme (from
                            /includes/lib/theme.php). Use this instance to grab
                            an URL for every icon defined in `$icons`.
                        */
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
        <script>
            /*
                Attempt to read settings from `localStorage`. If successful,
                overwrite the relevant entries in `settings`.
            */
            if (hasLocalStorageSupport()) {
                var storedSettings = JSON.parse(localStorage.getItem("settings"));
                if (storedSettings !== null) {
                    var keys = Object.keys(storedSettings);
                    for (var i = 0; i < keys.length; i++) {
                        settings[keys[i]] = storedSettings[keys[i]];
                    }
                }
            }

            /*
                Grab a stylesheet for the "dark" or "light" themes depending on
                the user's selection.
            */
            $("head").append('<link rel="stylesheet" ' +
                                   'type="text/css" ' +
                                   'href="./css/' + settings.get("theme") +
                                         '.css?v=<?php echo time(); ?>">');

            /*
                Configure MapBox.
            */
            mapboxgl.accessToken = <?php echo Config::getJS("map/provider/mapbox/access-token"); ?>;
            var map = new mapboxgl.Map({
                container: 'map',
                style: 'mapbox://styles/mapbox/' + (settings.get("mapStyle/mapbox")) + '-v9',
                center: [settings.center.longitude, settings.center.latitude],
                zoom: settings.zoom
            });

            /*
                Add map controls to the MapBox instance.
            */
            map.addControl(new mapboxgl.NavigationControl());
            map.addControl(new mapboxgl.GeolocateControl({
                positionOptions: {
                    enableHighAccuracy: false,
                    timeout: 5000
                },
                trackUserLocation: true
            }));

            /*
                Automatically save the current center point and zoom level of
                the map to `localStorage` if the user pans or zooms on the map.
                This allows the map to retain the current view the next time the
                user visits this FreeField instance.
            */
            var lastCenter = map.getCenter();
            var lastZoom = map.getZoom();
            setInterval(function() {
                var center = map.getCenter();
                var zoom = map.getZoom();
                if (center != lastCenter || zoom != lastZoom) {
                    lastCenter = center;
                    lastZoom = zoom;
                    settings.center.longitude = center.lng;
                    settings.center.latitude = center.lat;
                    settings.zoom = zoom;
                    saveSettings();
                }
            }, 1000);
        </script>
    </body>
</html>
