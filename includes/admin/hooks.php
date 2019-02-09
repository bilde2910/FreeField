<?php
/*
    Displayed on the admin page /admin/index.php when editing user groups.
*/
?>
<div class="content">
    <form action="apply-hooks.php"
          method="POST"
          class="pure-form require-validation"
          enctype="application/x-www-form-urlencoded">
        <!--
            Protection against CSRF
        -->
        <?php echo Security::getCSRFInputField(); ?>
        <!--
            The two hook lists are where webhooks are dynamically created by
            `createHookNode()` later in this file are appended. Active hooks are
            appended to `#active-hooks-list` and inactive ones to
            `#inactive-hooks-list`.
        -->
        <h2 class="content-subhead">
            <?php echo I18N::resolveHTML("admin.section.hooks.active.name"); ?>
        </h2>
        <div class="hook-list" id="active-hooks-list">
        </div>

        <h2 class="content-subhead">
            <?php echo I18N::resolveHTML("admin.section.hooks.inactive.name"); ?>
        </h2>
        <div class="hook-list" id="inactive-hooks-list">
        </div>

        <p class="buttons">
            <input type="button"
                   id="hooks-add"
                   class="button-standard"
                   value="<?php echo I18N::resolveHTML("admin.section.hooks.ui.add.name"); ?>">
            <input type="submit"
                   class="button-submit"
                   value="<?php echo I18N::resolveHTML("ui.button.save"); ?>">
        </p>
    </form>
</div>

<!--
    This div is an overlay which shows up whenever the user clicks on the Edit
    button for an objective filter. It allows the user to select an objective
    and edit its parameters. It is hidden by default and displayed by
    `editObjective()` in /admin/js/hooks.js.
-->
<div id="hooks-update-objective-overlay" class="cover-box admin-cover-box">
    <div class="cover-box-inner">
        <div class="header">
            <!--
                The title is dynamically set to either "Add objective" or "Edit
                objective" by the script whenever the overlay opens, so there is
                no need to pre-fill anything into this <h1>.
            -->
            <h1 id="hooks-update-objective-overlay-title"></h1>
        </div>
        <div class="cover-box-content content pure-form">
            <div class="pure-g">
                <!--
                    This <select> lists all available research objectives.
                -->
                <div class="pure-u-5-5 full-on-mobile"><p><select id="update-hook-objective">
                    <?php
                        /*
                            We'll sort the research objectives by their first respective categories.
                            Put all the research objectives into an array ($cats) of the structure
                            $cats[CATEGORY][RESEARCH OBJECTIVE][PARAMETERS ETC.]
                        */
                        $cats = array();
                        foreach (Research::listObjectives() as $objective => $data) {
                            // Skip unknown since it shouldn't be displayed as an option
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
                                <div id="update-hook-objective-param-<?php echo $param; ?>-box"
                                     class="pure-g research-parameter objective-parameter">
                                    <div class="pure-u-1-3 full-on-mobile">
                                        <p>
                                            <label for="update-hook-objective-param-<?php echo $param; ?>-enable">
                                                <input type="checkbox"
                                                       id="update-hook-objective-param-<?php echo $param; ?>-enable"
                                                       class="update-hook-param-checkbox"
                                                       checked>
                                                <?php echo I18N::resolveHTML("parameter.{$param}.label"); ?>:
                                            </label>
                                        </p>
                                    </div>
                                    <div class="pure-u-2-3 full-on-mobile">
                                        <?php echo $inst->html("update-hook-objective-param-{$param}-input", "parameter"); ?>
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
                                        echo $inst->writeJS("update-hook-objective-param-{$param}-input")."\n";
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
                                        echo $inst->parseJS("update-hook-objective-param-{$param}-input")."\n";
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
                    $("#update-hook-objective").on("change", function() {
                        <?php
                            foreach (Research::PARAMETERS as $param => $class) {
                                $inst = new $class();
                                if (in_array("objectives", $inst->getAvailable())) {
                                    echo "$('#update-hook-objective-param-{$param}-box').hide();";
                                }
                            }
                        ?>
                        var show = objectives[$("#update-hook-objective").val()].params;
                        for (var i = 0; i < show.length; i++) {
                            $("#update-hook-objective-param-" + show[i] + "-box").show();
                        }
                    });
                </script>
            </div>
            <div class="cover-button-spacer"></div>
            <div class="pure-g">
                <div class="pure-u-1-2 right-align">
                    <span id="update-hook-objective-cancel"
                          class="button-standard split-button button-spaced left">
                                <?php echo I18N::resolveHTML("ui.button.cancel"); ?>
                    </span>
                </div>
                <div class="pure-u-1-2">
                    <span id="update-hook-objective-submit"
                          class="button-submit split-button button-spaced right">
                                <?php echo I18N::resolveHTML("ui.button.done"); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!--
    This div is an overlay which shows up whenever the user clicks on the Edit
    button for a reward filter. It allows the user to select a reward and edit
    its parameters. It is hidden by default and displayed by `editReward()` in
    /admin/js/hooks.js.
-->
<div id="hooks-update-reward-overlay" class="cover-box admin-cover-box">
    <div class="cover-box-inner">
        <div class="header">
            <!--
                The title is dynamically set to either "Add reward" or "Edit
                reward" by the script whenever the overlay opens, so there is no
                need to pre-fill anything into this <h1>.
            -->
            <h1 id="hooks-update-reward-overlay-title"></h1>
        </div>
        <div class="cover-box-content content pure-form">
            <div class="pure-g">
                <!--
                    This <select> lists all available research rewards.
                -->
                <div class="pure-u-5-5 full-on-mobile"><p><select id="update-hook-reward">
                    <?php
                        /*
                            We'll sort the research rewards by their first respective categories.
                            Put all the research rewards into an array ($cats) of the structure
                            $cats[CATEGORY][RESEARCH REWARD][PARAMETERS ETC.]
                        */
                        $cats = array();
                        foreach (Research::listRewards() as $reward => $data) {
                            // Skip unknown since it shouldn't be displayed as an option
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
                                <div id="update-hook-reward-param-<?php echo $param; ?>-box"
                                     class="pure-g research-parameter reward-parameter">
                                    <div class="pure-u-1-3 full-on-mobile">
                                        <p>
                                            <label for="update-hook-reward-param-<?php echo $param; ?>-enable">
                                                <input type="checkbox"
                                                       id="update-hook-reward-param-<?php echo $param; ?>-enable"
                                                       class="update-hook-param-checkbox"
                                                       checked>
                                                <?php echo I18N::resolveHTML("parameter.{$param}.label"); ?>:
                                            </label>
                                        </p>
                                    </div>
                                    <div class="pure-u-2-3 full-on-mobile">
                                        <?php echo $inst->html("update-hook-reward-param-{$param}-input", "parameter"); ?>
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
                                        echo $inst->writeJS("update-hook-reward-param-{$param}-input")."\n";
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
                                        echo $inst->parseJS("update-hook-reward-param-{$param}-input")."\n";
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
                    $("#update-hook-reward").on("change", function() {
                        <?php
                            foreach (Research::PARAMETERS as $param => $class) {
                                $inst = new $class();
                                if (in_array("rewards", $inst->getAvailable())) {
                                    echo "$('#update-hook-reward-param-{$param}-box').hide();";
                                }
                            }
                        ?>
                        var show = rewards[$("#update-hook-reward").val()].params;
                        for (var i = 0; i < show.length; i++) {
                            $("#update-hook-reward-param-" + show[i] + "-box").show();
                        }
                    });
                </script>
            </div>
            <div class="cover-button-spacer"></div>
            <div class="pure-g">
                <div class="pure-u-1-2 right-align">
                    <span id="update-hook-reward-cancel"
                          class="button-standard split-button button-spaced left">
                                <?php echo I18N::resolveHTML("ui.button.cancel"); ?>
                    </span>
                </div>
                <div class="pure-u-1-2">
                    <span id="update-hook-reward-submit"
                          class="button-submit split-button button-spaced right">
                                <?php echo I18N::resolveHTML("ui.button.done"); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!--
    This div is an overlay which shows up whenever the user requests a list of
    all groups that a Telegram bot is in for a webhook, and after the response
    has been returned by Telegram's servers. It allows the user to select the
    Telegram group that the webhook's messages should be sent to. It is hidden
    by default and displayed by the `select[data-uri-scheme="tg"].hook-target`
    change event handler in /admin/js/hooks.js.
-->
<div id="hooks-tg-groups-overlay" class="cover-box admin-cover-box">
    <div class="cover-box-inner">
        <div class="header">
            <h1>
                <?php echo I18N::resolveHTML("admin.hooks.popup.tg.select_group"); ?>
            </h1>
        </div>
        <div class="cover-box-content content pure-form">
            <div class="pure-g">
                <div class="pure-u-1-3 full-on-mobile">
                    <p class="setting-name">
                        <?php echo I18N::resolveHTML("setting.hooks.tg.groups.select.name"); ?>:
                    </p>
                </div>
                <div class="pure-u-2-3 full-on-mobile">
                    <!--
                        This <select> is populated with available Telegram
                        groups when the overlay is displayed.
                    -->
                    <p><select id="select-tg-group-options"></select></p>
                </div>
            </div>
            <div class="cover-button-spacer"></div>
            <div class="pure-g">
                <div class="pure-u-1-2 right-align">
                    <span id="select-tg-group-cancel"
                          class="button-standard split-button button-spaced left">
                                <?php echo I18N::resolveHTML("ui.button.cancel"); ?>
                    </span>
                </div>
                <div class="pure-u-1-2">
                    <span id="select-tg-group-submit"
                          class="button-submit split-button button-spaced right">
                                <?php echo I18N::resolveHTML("ui.button.select"); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!--
    This div is an overlay which shows up whenever the user requests a list of
    all groups that a Telegram bot is in for a webhook, and the request to
    Telegram's servers is still pending. It is hidden by default and is
    temporarily displayed by the `select[data-uri-scheme="tg"].hook-target`
    change event handler in /admin/js/hooks.js, pending a response by Telegram's
    servers, and is hidden in favor of `#hooks-tg-groups-overlay` above when the
    request is complete.

    This div consists of a loading icon.
-->
<div id="hooks-tg-groups-working" class="cover-box admin-cover-box">
    <div class="cover-box-inner tiny">
        <div class="cover-box-content">
            <div>
                <i class="fas fa-spinner loading-spinner spinner-large"></i>
            </div>
            <p>
                <?php echo I18N::resolveHTML("admin.hooks.popup.tg.searching_group"); ?>
            </p>
        </div>
    </div>
</div>

<!--
    This div is an overlay which shows up whenever the user clicks on the button
    to add a new webhook. It allows the user to select what kind of webhook to
    add, and optionally pick a preset for the webhook's body. It is hidden by
    default and displayed by `#hooks-add`s click handler in /admin/js/hooks.js.
-->
<div id="hooks-add-overlay" class="cover-box admin-cover-box">
    <div class="cover-box-inner">
        <div class="header">
            <h1>
                <?php echo I18N::resolveHTML("admin.clientside.hooks.popup.add_webhook"); ?>
            </h1>
        </div>
        <div class="cover-box-content content pure-form">
            <div class="pure-g">
                <div class="pure-u-1-3 full-on-mobile">
                    <p class="setting-name">
                        <?php echo I18N::resolveHTML("setting.hooks.add.type.name"); ?>:
                    </p>
                </div>
                <div class="pure-u-2-3 full-on-mobile">
                    <!--
                        Webhooks can be either of the JSON or the Telegram type.
                    -->
                    <p><select id="add-hook-type">
                        <option value="json">
                            <?php echo I18N::resolveHTML("setting.hooks.add.type.option.json"); ?>
                        </option>
                        <option value="telegram">
                            <?php echo I18N::resolveHTML("setting.hooks.add.type.option.telegram"); ?>
                        </option>
                    </select></p>
                </div>
            </div>
            <?php
                /*
                    Hook presets are stored in the /includes/hook-presets
                    directory. That directory has subdirectories for each type
                    of webhook which has presets. JSON webhooks, for instance,
                    have webhooks located in /includes/hook-presets/json. The
                    easiest way to list these presets is to grab a directory
                    listing of the presets for each type of webhook.
                */
                $presets = array();
                $path = __DIR__."/../../includes/hook-presets";
                $presetdirs = array_diff(
                    scandir($path),
                    array('..', '.')
                );
                foreach ($presetdirs as $type) {
                    if (is_dir("{$path}/{$type}")) {
                        $typepresets = array_diff(
                            scandir("{$path}/{$type}"),
                            array('..', '.')
                        );
                        foreach ($typepresets as $preset) {
                            /*
                                Since the presets will be output and displayed
                                client-side on this page anyway, we might as
                                well grab the contents of all of the presets
                                while we're at it, so we don't have to loop over
                                all of the preset files again later.
                            */
                            $presets[$type][$preset] = file_get_contents("{$path}/{$type}/{$preset}");
                        }
                    }
                }
            ?>
            <!--
                JSON presets
            -->
            <div class="pure-g hook-add-type-conditional hook-add-type-json">
                <div class="pure-u-1-3 full-on-mobile">
                    <p class="setting-name">
                        <?php echo I18N::resolveHTML("setting.hooks.add.preset.name"); ?>:
                    </p>
                </div>

                <div class="pure-u-2-3 full-on-mobile">
                    <p><select id="add-hook-json-preset">
                        <option value="none">
                            <?php echo I18N::resolveHTML("setting.hooks.add.preset.option.none"); ?>
                        </option>
                        <?php
                            foreach ($presets["json"] as $name => $data) {
                                echo '<option value="'.$name.'">'.$name.'</option>';
                            }
                        ?>
                    </select></p>
                </div>
            </div>
            <!--
                Telegram presets
            -->
            <div class="pure-g hook-add-type-conditional hook-add-type-telegram">
                <div class="pure-u-1-3 full-on-mobile">
                    <p class="setting-name">
                        <?php echo I18N::resolveHTML("setting.hooks.add.preset.name"); ?>:
                    </p>
                </div>

                <div class="pure-u-2-3 full-on-mobile">
                    <p><select id="add-hook-telegram-preset">
                        <option value="none">
                            <?php echo I18N::resolveHTML("setting.hooks.add.preset.option.none"); ?>
                        </option>
                        <?php
                            foreach ($presets["telegram"] as $name => $data) {
                                echo '<option value="'.$name.'">'.$name.'</option>';
                            }
                        ?>
                    </select></p>
                </div>
            </div>
            <div class="cover-button-spacer"></div>
            <div class="pure-g">
                <div class="pure-u-1-2 right-align">
                    <span id="add-hook-cancel"
                          class="button-standard split-button button-spaced left">
                                <?php echo I18N::resolveHTML("ui.button.cancel"); ?>
                    </span>
                </div>
                <div class="pure-u-1-2">
                    <span id="add-hook-submit"
                          class="button-submit split-button button-spaced right">
                                <?php echo I18N::resolveHTML("ui.button.done"); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    /*
        Creates and returns an HTML node representing a webhook. Contains
        various fields that let users configure the webhook's behavior, ranging
        from essential information (the target URL of the webhook, for instance)
        to filter options (e.g. only trigger this webhook if a particular
        objective was reported, or if the POI the report was made on is within
        a particular geofence).
    */
    function createHookNode(type, id) {
        <?php
            /*
                Webhooks allow specifying a language that should be used to
                localize strings when the webhook body contains
                internationalized string tokens. List these languages with their
                names in a <select> box on each webhook.
            */
            $langs = I18N::getAvailableLanguagesWithNames();
            $langopts = "";
            foreach ($langs as $code => $name) {
                $langopts .= '<option value="'.$code.'">'.
                             htmlspecialchars($name, ENT_QUOTES).
                             '</option>';
            }

            /*
                Create an icon set option with a default element that is
                displayed as the string localized from the I18N token passed to
                the constructor of the class as its sole argument. This Option
                instance is used to draw the icon selector selection box and
                preview box, since the code to do so is readily available in
                this class.
            */
            $optIcon = new IconSetOption("setting.hooks.hook_list.icons.option.default");
            $optSpecies = new SpeciesSetOption("setting.hooks.hook_list.species.option.default");
            $optFence = new GeofenceOption();

            /*
                The summary text box in the header of the webhook.
            */
            $hookSummary = '
            <span class="hook-summary-text">'.
                I18N::resolveArgsHTML(
                    "poi.objective_text",
                    false,
                    '<span class="hook-head-objective-text">'.
                        I18N::resolveHTML("admin.clientside.hooks.any_objective").
                    '</span>',
                    '<span class="hook-head-reward-text">'.
                        I18N::resolveHTML("admin.clientside.hooks.any_reward").
                    '</span>'
                ).
            '</span>';

            /*
                Webhook quick actions. These allow users to enable, disable and
                delete webhooks.
            */
            $hookActions = '
            <div class="pure-g">
                <div class="pure-u-1-3 full-on-mobile">
                    <p>'.I18N::resolveHTML("setting.hooks.hook_list.actions.name").':</p>
                </div>
                <div class="pure-u-2-3 full-on-mobile"><p>
                    <select class="hook-actions" name="hook_{%ID%}[action]">
                        <option value="none" selected>'.
                            I18N::resolveHTML("setting.hooks.hook_list.actions.option.none").
                        '</option>
                        <option value="enable">'.
                            I18N::resolveHTML("setting.hooks.hook_list.actions.option.enable").
                        '</option>
                        <option value="disable">'.
                            I18N::resolveHTML("setting.hooks.hook_list.actions.option.disable").
                        '</option>
                        <option value="delete">'.
                            I18N::resolveHTML("setting.hooks.hook_list.actions.option.delete").
                        '</option>
                    </select>
                </p></div>
            </div>';

            /*
                Settings which are common for all webhook types. This includes
                the webhook language, its icon set, and geofence.
            */
            $hookCommonSettings = '
            <div class="pure-g">
                <div class="pure-u-1-3 full-on-mobile">
                    <p>'.I18N::resolveHTML("setting.hooks.hook_list.language.name").':</p>
                </div>
                <div class="pure-u-2-3 full-on-mobile">
                    <p><select class="hook-lang" name="hook_{%ID%}[lang]">'.$langopts.'</select></p>
                </div>
            </div>
            <div class="pure-g option-block-follows">
                <div class="pure-u-1-3 full-on-mobile">
                    <p>'.I18N::resolveHTML("setting.hooks.hook_list.icons.name").':</p>
                </div>
                <div class="pure-u-2-3 full-on-mobile">
                    <p>'.$optIcon->getControl(null, array(
                            "name" => "hook_{%ID%}[iconSet]",
                            "id" => "{%ID%}-icon-selector",
                            "class" => "hook-icon-set"
                    )).'</p>
                </div>
            </div>
            '.$optIcon->getFollowingBlock().'
            <div class="pure-g">
                <div class="pure-u-1-3 full-on-mobile">
                    <p>'.I18N::resolveHTML("setting.hooks.hook_list.show_species.name").':</p>
                </div>
                <div class="pure-u-2-3 full-on-mobile">
                    <p><label for="{%ID%}-show-species">
                        <input type="checkbox"
                               id="{%ID%}-show-species"
                               name="hook_{%ID%}[showSpecies]"
                               class="hook-show-species"
                               checked>
                        '.I18N::resolveHTML("setting.hooks.hook_list.show_species.label").'
                    </label></p>
                </div>
            </div>
            <div class="pure-g option-block-follows">
                <div class="pure-u-1-3 full-on-mobile">
                    <p>'.I18N::resolveHTML("setting.hooks.hook_list.species.name").':</p>
                </div>
                <div class="pure-u-2-3 full-on-mobile">
                    <p>'.$optSpecies->getControl(null, array(
                            "name" => "hook_{%ID%}[speciesSet]",
                            "id" => "{%ID%}-species-selector",
                            "class" => "hook-species-set"
                    )).'</p>
                </div>
            </div>
            '.$optIcon->getFollowingBlock().'
            <div class="pure-g">
                <div class="pure-u-1-3 full-on-mobile">
                    <p>'.I18N::resolveHTML("setting.hooks.hook_list.geofence.name").':</p>
                </div>
                <div class="pure-u-2-3 full-on-mobile">
                    <p>
                        '.$optFence->getControl(null, array(
                            "name" => "hook_{%ID%}[geofence]",
                            "class" => "hook-geofence",
                        )).'
                    </p>
                </div>
            </div>';

            /*
                Syntax help for replacement tags in the webhook body. The body
                may contain tokens/tags such as <%COORDS%> or <%POI%> which
                should be replaced with meaningful dynamic values whenever the
                webhook is triggered. A quick help guide should be listed on the
                webhook body (and this is done with this code block). More
                detailed information on the purpose of each one can be looked up
                in the documentation.
            */
            $hookSyntaxHelp = '
            <p><a class="hook-show-help" href="#">'.
                I18N::resolveHTML("admin.clientside.hooks.syntax.show").
            '</a></p>
            <div class="hook-syntax-help">
                <div class="hook-syntax-block full-on-mobile">
                    <h3>'.I18N::resolveHTML("admin.hooks.syntax.poi.title").'</h3>
                    '.I18N::resolveArgsHTML("admin.hooks.syntax.poi.poi", false, '<code>&lt;%POI%&gt;</code>').'<br />
                    '.I18N::resolveArgsHTML("admin.hooks.syntax.poi.lat", false, '<code>&lt;%LAT%&gt;</code>').'<br />
                    '.I18N::resolveArgsHTML("admin.hooks.syntax.poi.lng", false, '<code>&lt;%LNG%&gt;</code>').'<br />
                    '.I18N::resolveArgsHTML("admin.hooks.syntax.poi.coords", false, '<code>&lt;%COORDS%&gt;</code>').'
                </div>
                <div class="hook-syntax-block full-on-mobile">
                    <h3>'.I18N::resolveHTML("admin.hooks.syntax.research.title").'</h3>
                    '.I18N::resolveArgsHTML("admin.hooks.syntax.research.objective", false, '<code>&lt;%OBJECTIVE%&gt;</code>').'<br />
                    '.I18N::resolveArgsHTML("admin.hooks.syntax.research.reward", false, '<code>&lt;%REWARD%&gt;</code>').'<br />
                    '.I18N::resolveArgsHTML("admin.hooks.syntax.research.reporter", false, '<code>&lt;%REPORTER%&gt;</code>').'<br />
                    '.I18N::resolveArgsHTML("admin.hooks.syntax.research.time", false, '<code>&lt;%TIME(format)%&gt;</code>').'
                </div>
                <div class="hook-syntax-clear"></div>
                <div>
                    <h3>'.I18N::resolveHTML("admin.hooks.syntax.navigation.title").'</h3>
                    '.I18N::resolveArgsHTML("admin.hooks.syntax.navigation.navurl", false, '<code>&lt;%NAVURL%&gt;</code>').'<br />
                    '.I18N::resolveArgsHTML("admin.hooks.syntax.navigation.navurl_arg", false, '<code>&lt;%NAVURL(provider)%&gt;</code>').'
                </div>
                <div>
                    <h3>'.I18N::resolveHTML("admin.hooks.syntax.icons.title").'</h3>
                    '.I18N::resolveArgsHTML("admin.hooks.syntax.icons.objective_icon", false, '<code>&lt;%OBJECTIVE_ICON(format,variant)%&gt;</code>').'<br />
                    '.I18N::resolveArgsHTML("admin.hooks.syntax.icons.reward_icon", false, '<code>&lt;%REWARD_ICON(format,variant)%&gt;</code>').'
                </div>
                <div>
                    <h3>'.I18N::resolveHTML("admin.hooks.syntax.other.title").'</h3>
                    '.I18N::resolveArgsHTML("admin.hooks.syntax.other.i18n", false, '<code>&lt;%I18N(token[,arg1[,arg2...]])%&gt;</code>').'<br />
                </div>
            </div>';

            /*
                Webhook filter blocks. `#hook-filter-objectives` and
                `#hooks-filter-rewards` are the target containers for filter
                nodes added for obnjetives and rewards, respectively. I.e. when
                a new objective filter is added by the user, it is appended to
                `#hook-filter-objectives`. These divs also contain the selection
                boxes that let users toggle between filtering modes (i.e.
                blacklisting and whitelisting filter objectives).
            */
            $hookFilters = '
            <div class="pure-g">
                <div class="pure-u-1-2 full-on-mobile hook-filter-objectives">
                    <h2>'.
                        I18N::resolveArgsHTML(
                            "admin.section.hooks.objectives.name",
                            false,
                            '<a class="hook-objective-add hook-filter-add" href="#">',
                            '</a>'
                        ).
                    '</h2>
                    <p>'.I18N::resolveHTML("setting.hooks.hook_list.filter_mode.name").':</p>
                    <p><select class="hook-mode-objective" name="hook_{%ID%}[filterModeObjective]" disabled>
                        <option value="whitelist">'.
                            I18N::resolveHTML("setting.hooks.hook_list.filter_mode.objective.option.whitelist.name").
                        '</option>
                        <option value="blacklist">'.
                            I18N::resolveHTML("setting.hooks.hook_list.filter_mode.objective.option.blacklist.name").
                        '</option>
                    </select></p>
                </div>
                <div class="pure-u-1-2 full-on-mobile hook-filter-rewards">
                    <h2>'.
                        I18N::resolveArgsHTML(
                            "admin.section.hooks.rewards.name",
                            false,
                            '<a class="hook-reward-add hook-filter-add" href="#">',
                            '</a>'
                        ).
                    '</h2>
                    <p>'.I18N::resolveHTML("setting.hooks.hook_list.filter_mode.name").':</p>
                    <p><select class="hook-mode-reward" name="hook_{%ID%}[filterModeReward]" disabled>
                        <option value="whitelist">'.
                            I18N::resolveHTML("setting.hooks.hook_list.filter_mode.reward.option.whitelist.name").
                        '</option>
                        <option value="blacklist">'.
                            I18N::resolveHTML("setting.hooks.hook_list.filter_mode.reward.option.blacklist.name").
                        '</option>
                    </select></p>
                </div>
            </div>';
        ?>

        /*
            Create the webhook node. Its contents and structure depends on the
            type of webhook being added.
        */
        var html;
        if (type == "json") {
            html = <?php
                /*
                    All webhooks have the hook summary, actions, common
                    settings, syntax help and filter structure. They also have
                    fields for hook target hostname/ID (`.hook-domain`) and
                    an input field for the full target ( `.hook-target`), but
                    the implementation of these may vary between webhook types.
                    The common settings are placed in variables above to
                    minimize code reuse ($hookSummary, $hookActions, etc.). The
                    rest are defined separately below.
                */
                $node = '
                    <div class="hook-instance" data-hook-id="{%ID%}">
                        <div class="hook-head">
                            <span class="hook-action">'.
                                I18N::resolveHTML("setting.hooks.add.type.option.json").
                            '</span> &rarr; <span class="hook-domain">'.
                                I18N::resolveHTML("admin.clientside.domain.unknown").
                            '</span><br />
                            '.$hookSummary.'
                        </div>
                        <div class="hook-body">
                            <input type="hidden" name="hook_{%ID%}[type]" value="json">
                            '.$hookActions.'
                            <h2>'.I18N::resolveHTML("admin.section.hooks.settings.name").'</h2>
                            <div class="pure-g">
                                <div class="pure-u-1-3 full-on-mobile">
                                    <p>'.I18N::resolveHTML("setting.hooks.hook_list.webhook_url.name").':</p>
                                </div>
                                <div class="pure-u-2-3 full-on-mobile"><p>
                                    <input type="text"
                                           class="hook-target"
                                           name="hook_{%ID%}[target]"
                                           data-uri-scheme="http"
                                           data-validate-as="http-uri">
                                </p></div>
                            </div>
                            '.$hookCommonSettings.'
                            <h2>'.I18N::resolveHTML("admin.section.hooks.body.json.name").'</h2>
                            '.$hookSyntaxHelp.'
                            <textarea class="hook-payload"
                                      name="hook_{%ID%}[body]"
                                      rows="8"
                                      data-validate-as="json"></textarea>
                            '.$hookFilters.'
                        </div>
                    </div>
                ';
                echo json_encode($node);
            ?>;
        } else if (type == "telegram") {
            html = <?php
                /*
                    Telegram webhooks differ from JSON webhooks in that they:
                      - Have a different title in the UI
                      - Display the Telegram group ID as the target domain
                        rather than a hostname
                      - Require a bot token
                      - Offer settings to disable notifications and web previews
                      - Allow manually setting the parsing mode to plain text,
                        Markdown or HTML
                      - The "webhook URL" input is a drop-down instead of a text
                        box which would otherwise accept any user input. The
                        user should be displayed a list of valid groups rather
                        than be allowed to directly enter a group ID. There is
                        no way to obtain a group ID from the Telegram GUI, so
                        this method provides the best user experience.
                */
                $node = '
                    <div class="hook-instance" data-hook-id="{%ID%}">
                        <div class="hook-head">
                            <span class="hook-action">'.
                                I18N::resolveHTML("setting.hooks.add.type.option.telegram").
                            '</span> &rarr; <span class="hook-domain">'.
                                I18N::resolveHTML("admin.clientside.domain.unknown").
                            '</span><br />
                            '.$hookSummary.'
                        </div>
                        <div class="hook-body">
                            <input type="hidden" name="hook_{%ID%}[type]" value="telegram">
                            '.$hookActions.'
                            <h2>'.I18N::resolveHTML("admin.section.hooks.settings.name").'</h2>
                            <div class="pure-g">
                                <div class="pure-u-1-3 full-on-mobile">
                                    <p>'.I18N::resolveHTML("setting.hooks.hook_list.tg.bot_token.name").':</p>
                                </div>
                                <div class="pure-u-2-3 full-on-mobile"><p>
                                    <input type="password"
                                           class="hook-tg-bot-token"
                                           name="hook_{%ID%}[tg][bot_token]">
                                </p></div>
                            </div>
                            <div class="pure-g">
                                <div class="pure-u-1-3 full-on-mobile">
                                    <p>'.I18N::resolveHTML("setting.hooks.hook_list.webhook_url.name").':</p>
                                </div>
                                <div class="pure-u-2-3 full-on-mobile"><p>
                                    <select class="hook-target"
                                            name="hook_{%ID%}[target]"
                                            data-uri-scheme="tg"
                                            data-validate-as="tg-uri">
                                                <optgroup label="'.I18N::resolveHTML("setting.hooks.hook_list.tg.webhook_url.option.current").'" class="hook-target-current-group">
                                                    <option value="" selected></option>
                                                </optgroup>
                                                <optgroup label="'.I18N::resolveHTML("setting.hooks.hook_list.tg.webhook_url.option.other").'">
                                                    <option value="_select">
                                                        &lt; '.I18N::resolveHTML("setting.hooks.hook_list.tg.webhook_url.option.select").' &gt;
                                                    </option>
                                                </optgroup>
                                    </select>
                                </p></div>
                            </div>
                            <div class="pure-g">
                                <div class="pure-u-1-3 full-on-mobile">
                                    <p>'.I18N::resolveHTML("setting.hooks.hook_list.tg.parse_mode.name").':</p>
                                </div>
                                <div class="pure-u-2-3 full-on-mobile"><p>
                                    <select class="hook-tg-parse-mode" name="hook_{%ID%}[tg][parse_mode]">
                                        <option value="txt">'.
                                            I18N::resolveHTML("setting.hooks.hook_list.tg.parse_mode.option.txt").
                                        '</option>
                                        <option value="md">'.
                                            I18N::resolveHTML("setting.hooks.hook_list.tg.parse_mode.option.md").
                                        '</option>
                                        <option value="html">'.
                                            I18N::resolveHTML("setting.hooks.hook_list.tg.parse_mode.option.html").
                                        '</option>
                                    </select>
                                </p></div>
                            </div>
                            <div class="pure-g">
                                <div class="pure-u-1-3 full-on-mobile">
                                    <p>'.I18N::resolveHTML("setting.hooks.hook_list.tg.disable_web_page_preview.name").':</p>
                                </div>
                                <div class="pure-u-2-3 full-on-mobile"><p>
                                    <label for="hook-bool-disable_web_page_preview-{%ID%}">
                                        <input type="checkbox"
                                               id="hook-bool-disable_web_page_preview-{%ID%}"
                                               class="hook-tg-disable-web-page-preview"
                                               name="hook_{%ID%}[tg][disable_web_page_preview]"> '.
                                                    I18N::resolveHTML("setting.hooks.hook_list.tg.disable_web_page_preview.label").
                                    '</label>
                                </p></div>
                            </div>
                            <div class="pure-g">
                                <div class="pure-u-1-3 full-on-mobile">
                                    <p>'.I18N::resolveHTML("setting.hooks.hook_list.tg.disable_notification.name").':</p>
                                </div>
                                <div class="pure-u-2-3 full-on-mobile"><p>
                                    <label for="hook-bool-disable_notification-{%ID%}">
                                        <input type="checkbox"
                                               id="hook-bool-disable_notification-{%ID%}"
                                               class="hook-tg-disable-notification"
                                               name="hook_{%ID%}[tg][disable_notification]"> '.
                                                    I18N::resolveHTML("setting.hooks.hook_list.tg.disable_notification.label").
                                    '</label>
                                </p></div>
                            </div>
                            '.$hookCommonSettings.'
                            <h2 class="hook-body-header">'.
                                I18N::resolveHTML("admin.section.hooks.body.txt.name").
                            '</h2>
                            '.$hookSyntaxHelp.'
                            <textarea class="hook-payload" name="hook_{%ID%}[body]" rows="8"></textarea>
                            '.$hookFilters.'
                        </div>
                    </div>
                ';
                echo json_encode($node);
            ?>;
        };

        /*
            The HTML string may contain ID placeholders which should be replaced
            with the actual ID of the webhook before the node is added to the
            document.
        */
        html = html.split("{%ID%}").join(id);

        var node = $.parseHTML(html);
        return node;
    }

    /*
        Data objects which hold the current complete list of available research
        objectives and rewards as defined in /includes/data/objectives.yaml and
        /includes/data/rewards.yaml. These are needed to properly manage and
        display objective and reward filters.
    */
    var objectives = <?php echo json_encode(Research::listObjectives()); ?>;
    var rewards = <?php echo json_encode(Research::listRewards()); ?>;

    /*
        Handle changes to the Actions down-down for webhooks. If the "delete"
        action is selected, the box should be re-styled to make it very obvious
        that the webhook will be deleted (i.e. it shouldn't be possible to do it
        by accident). Setting the border and text color to red should draw
        enough attention to the box that accidental deletions doesn't happen (or
        at least happens very rarely). The same is done for actions enabling and
        disabling webhooks.
    */
    $(".hook-list").on("change", ".hook-actions", function() {
        if ($(this).val() == "delete") {
            $(this).css("border", "1px solid red");
            $(this).css("color", "red");
            $(this).css("margin-right", "");
        } else if ($(this).val() == "enable") {
            var color = <?php echo Config::get("themes/color/admin")->valueJS(); ?> == "dark" ? "lime" : "green";
            $(this).css("border", "1px solid " + color);
            $(this).css("color", color);
            $(this).css("margin-right", "");
        } else if ($(this).val() == "disable") {
            $(this).css("border", "1px solid darkorange");
            $(this).css("color", "darkorange");
            $(this).css("margin-right", "");
        } else {
            $(this).css("border", "");
            $(this).css("color", "");
            $(this).css("margin-right", "");
        }
    });

    /*
        Data object containing a complete list of webhooks and their parameters.
        This object is parsed by /admin/js/hooks.js, resulting in all of these
        webhooks being added to the page after page load is complete.
    */
    var hooks = <?php
        $hooks = Config::getRaw("webhooks");
        if ($hooks === null) $hooks = array();

        /*
            When the list of webhooks is loaded on the administration pages, the
            server ensures that all Telegram bot tokens in all webhooks are
            substituted with a password mask/placeholder value rather than
            having the bot tokens being sent in plaintext. For example, a
            webhook may have a Telegram bot token stored internally in the
            configuration file, but when that hook is presented on the webhooks
            page in the administration interface, the bot token is replaced in
            the HTML code with a random string, so that the bot token itself is
            never sent back to the client.

            The reason for this is that Telegram for some reason decided that
            bot tokens are valid for sending messages (as is the point of a
            webhook), but are also in scope to perform user authentication. The
            bot token is used to verify that the authentication parameters
            passed from Telegram actually originate from Telegram servers.
            Telegram uses an HMAC hash to perform this verification, where the
            secret key of the HMAC hash is created from the bot token. See
            https://core.telegram.org/widgets/login#checking-authorization.

            The reason Telegram uses the bot token for this purpose is that they
            assume the bot token will be kept secret. After all, only the bot
            developer and Telegram themselves would know this token. Hence,
            anyone with the bot token will be able to craft a valid, signed HMAC
            hash that can be used to authenticate an arbitrary user.

            FreeField allows using Telegram both for webhooks and for
            authentication. It is likely that many installations will re-use the
            same bot token for both purposes. If a user on such an installation
            has access to the webhooks administration interface, they would be
            able to fetch the bot token from registered Telegram webhooks, if
            the bot token was sent in plaintext. They could then use that bot
            token to sign authentication data as if it was signed by Telegram
            itself. The server would have no reason to suspect anything unusual
            was going on, and as such would approve the authentication request.

            Users with access to the webhook administration page would be able
            to exploit this vulnerability to forge a valid authentication of a
            higher privileged user. This could even result in the user being
            able to assign themselves to a higher permission group using the
            compromised account as a tool. By never sending the bot token back
            to the web browser under any circumstances, and instead sending a
            random string mask, this privilege escalation attack vector is
            eliminated.

            In this code block, we obtain the default replacement mask from
            `PasswordOption` in /includes/config/types.php. This value is sent
            instead of bot tokens to the client.
        */
        $mask = (new PasswordOption())->getMask();
        for ($i = 0; $i < count($hooks); $i++) {
            if (isset($hooks[$i]["options"]["bot-token"])) {
                $hooks[$i]["options"]["bot-token"] = $mask;
            }
        }

        echo json_encode($hooks);
    ?>

    /*
        Data object containing presets and their contents. This object has keys
        representing the type of webhook, with subkeys representing the preset
        filename. The value of those keys are the contents of the preset. This
        makes it easy for the client-side script to place the correct preset in
        the webhook's body field when creating a new webhook with a preset
        enabled. The script simply looks up the contents from this object and
        places it in the text box.
    */
    var presets = <?php
        echo json_encode($presets);
    ?>
</script>

<!--
    /admin/js/hooks.js contains additional functionality for this page. This
    script is loaded after the data objects above are in place, because it
    requires several of those objects (e.g. list of current webhooks) to
    function.
-->
<script src="./js/hooks.js"></script>
