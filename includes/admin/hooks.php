<div class="content">
    <form action="apply-hooks.php" method="POST" class="pure-form require-validation" enctype="application/x-www-form-urlencoded">
        <h2 class="content-subhead"><?php echo I18N::resolveHTML("admin.section.hooks.active.name"); ?></h2>
        <div class="hook-list" id="active-hooks-list">

        </div>
        <h2 class="content-subhead"><?php echo I18N::resolveHTML("admin.section.hooks.inactive.name"); ?></h2>
        <div class="hook-list" id="inactive-hooks-list">

        </div>
        <p class="buttons"><input type="button" id="hooks-add" class="button-standard" value="<?php echo I18N::resolveHTML("admin.section.hooks.ui.add.name"); ?>"> <input type="submit" class="button-submit" value="<?php echo I18N::resolveHTML("ui.button.save"); ?>"></p>
    </form>
</div>
<div id="hooks-update-objective-overlay" class="cover-box admin-cover-box">
    <div class="cover-box-inner">
        <div class="header">
            <h1 id="hooks-update-objective-overlay-title"></h1>
        </div>
        <div class="cover-box-content content pure-form">
            <div class="pure-g">
                <div class="pure-u-5-5 full-on-mobile"><p><select id="update-hook-objective">
                    <?php
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
                                    $i18n = str_replace("{%".($i+1)."}", I18N::resolve("parameter.".$data["params"][$i].".placeholder"), $i18n);
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
                    foreach (Research::PARAMETERS as $param => $class) {
                        $inst = new $class();
                        if (in_array("objectives", $inst->getAvailable())) {
                            ?>
                                <div id="update-hook-objective-param-<?php echo $param; ?>-box" class="pure-g research-parameter objective-parameter">
                                    <div class="pure-u-1-3 full-on-mobile"><p><?php echo I18N::resolveHTML("parameter.{$param}.label"); ?>:</p></div>
                                    <div class="pure-u-2-3 full-on-mobile"><p><?php echo $inst->html("update-hook-objective-param-{$param}-input", "parameter"); ?></p></div>
                                </div>
                            <?php
                        }
                    }
                ?>
                <script>
                    function getObjectiveParameter(param) {
                        switch (param) {
                            <?php
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
                <div class="pure-u-1-2 right-align"><span type="button" id="update-hook-objective-cancel" class="button-standard split-button button-spaced left"><?php echo I18N::resolveHTML("ui.button.cancel"); ?></span></div>
                <div class="pure-u-1-2"><span type="button" id="update-hook-objective-submit" class="button-submit split-button button-spaced right"><?php echo I18N::resolveHTML("ui.button.done"); ?></span></div>
            </div>
        </div>
    </div>
</div>
<div id="hooks-update-reward-overlay" class="cover-box admin-cover-box">
    <div class="cover-box-inner">
        <div class="header">
            <h1 id="hooks-update-reward-overlay-title"></h1>
        </div>
        <div class="cover-box-content content pure-form">
            <div class="pure-g">
                <div class="pure-u-5-5 full-on-mobile"><p><select id="update-hook-reward">
                    <?php
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
                                    $i18n = str_replace("{%".($i+1)."}", I18N::resolve("parameter.".$data["params"][$i].".placeholder"), $i18n);
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
                    foreach (Research::PARAMETERS as $param => $class) {
                        $inst = new $class();
                        if (in_array("rewards", $inst->getAvailable())) {
                            ?>
                                <div id="update-hook-reward-param-<?php echo $param; ?>-box" class="pure-g research-parameter reward-parameter">
                                    <div class="pure-u-1-3 full-on-mobile"><p><?php echo I18N::resolveHTML("parameter.{$param}.label"); ?>:</p></div>
                                    <div class="pure-u-2-3 full-on-mobile"><p><?php echo $inst->html("update-hook-reward-param-{$param}-input", "parameter"); ?></p></div>
                                </div>
                            <?php
                        }
                    }
                ?>
                <script>
                    function getRewardParameter(param) {
                        switch (param) {
                            <?php
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
                <div class="pure-u-1-2 right-align"><span type="button" id="update-hook-reward-cancel" class="button-standard split-button button-spaced left"><?php echo I18N::resolveHTML("ui.button.cancel"); ?></span></div>
                <div class="pure-u-1-2"><span type="button" id="update-hook-reward-submit" class="button-submit split-button button-spaced right"><?php echo I18N::resolveHTML("ui.button.done"); ?></span></div>
            </div>
        </div>
    </div>
</div>

<div id="hooks-tg-groups-overlay" class="cover-box admin-cover-box">
    <div class="cover-box-inner">
        <div class="header">
            <h1><?php echo I18N::resolveHTML("admin.hooks.popup.tg.select_group"); ?></h1>
        </div>
        <div class="cover-box-content content pure-form">
            <div class="pure-g">
                <div class="pure-u-1-3 full-on-mobile">
                    <p class="setting-name"><?php echo I18N::resolveHTML("setting.hooks.tg.groups.select.name"); ?>:</p>
                </div>
                <div class="pure-u-2-3 full-on-mobile">
                    <p><select id="select-tg-group-options"></select></p>
                </div>
            </div>
            <div class="cover-button-spacer"></div>
            <div class="pure-g">
                <div class="pure-u-1-2 right-align"><span type="button" id="select-tg-group-cancel" class="button-standard split-button button-spaced left"><?php echo I18N::resolveHTML("ui.button.cancel"); ?></span></div>
                <div class="pure-u-1-2"><span type="button" id="select-tg-group-submit" class="button-submit split-button button-spaced right"><?php echo I18N::resolveHTML("ui.button.select"); ?></span></div>
            </div>
        </div>
    </div>
</div>

<div id="hooks-tg-groups-working" class="cover-box admin-cover-box">
    <div class="cover-box-inner tiny">
        <div class="cover-box-content">
            <div><i class="fas fa-spinner loading-spinner spinner-large"></i></div>
            <p><?php echo I18N::resolveHTML("admin.hooks.popup.tg.searching_group"); ?></p>
        </div>
    </div>
</div>

<div id="hooks-add-overlay" class="cover-box admin-cover-box">
    <div class="cover-box-inner">
        <div class="header">
            <h1><?php echo I18N::resolveHTML("admin.clientside.hooks.popup.add_webhook"); ?></h1>
        </div>
        <div class="cover-box-content content pure-form">
            <div class="pure-g">
                <div class="pure-u-1-3 full-on-mobile">
                    <p class="setting-name"><?php echo I18N::resolveHTML("setting.hooks.add.type.name"); ?>:</p>
                </div>
                <div class="pure-u-2-3 full-on-mobile">
                    <p><select id="add-hook-type">
                        <option value="json"><?php echo I18N::resolveHTML("setting.hooks.add.type.option.json"); ?></option>
                        <option value="telegram"><?php echo I18N::resolveHTML("setting.hooks.add.type.option.telegram"); ?></option>
                    </select></p>
                </div>
            </div>
            <?php
                $presets = array();
                $path = __DIR__."/../../includes/hook-presets";
                $presetdirs = array_diff(scandir($path), array('..', '.'));
                foreach ($presetdirs as $type) {
                    if (is_dir("{$path}/{$type}")) {
                        $typepresets = array_diff(scandir("{$path}/{$type}"), array('..', '.'));
                        foreach ($typepresets as $preset) {
                            $presets[$type][$preset] = file_get_contents("{$path}/{$type}/{$preset}");
                        }
                    }
                }
            ?>
            <div class="pure-g hook-add-type-conditional hook-add-type-json">
                <div class="pure-u-1-3 full-on-mobile">
                    <p class="setting-name"><?php echo I18N::resolveHTML("setting.hooks.add.preset.name"); ?>:</p>
                </div>

                <div class="pure-u-2-3 full-on-mobile">
                    <p><select id="add-hook-json-preset">
                        <option value="none"><?php echo I18N::resolveHTML("setting.hooks.add.preset.option.none"); ?></option>
                        <?php
                            foreach ($presets["json"] as $name => $data) {
                                echo '<option value="'.$name.'">'.$name.'</option>';
                            }
                        ?>
                    </select></p>
                </div>
            </div>
            <div class="pure-g hook-add-type-conditional hook-add-type-telegram">
                <div class="pure-u-1-3 full-on-mobile">
                    <p class="setting-name"><?php echo I18N::resolveHTML("setting.hooks.add.preset.name"); ?>:</p>
                </div>

                <div class="pure-u-2-3 full-on-mobile">
                    <p><select id="add-hook-telegram-preset">
                        <option value="none"><?php echo I18N::resolveHTML("setting.hooks.add.preset.option.none"); ?></option>
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
                <div class="pure-u-1-2 right-align"><span type="button" id="add-hook-cancel" class="button-standard split-button button-spaced left"><?php echo I18N::resolveHTML("ui.button.cancel"); ?></span></div>
                <div class="pure-u-1-2"><span type="button" id="add-hook-submit" class="button-submit split-button button-spaced right"><?php echo I18N::resolveHTML("ui.button.done"); ?></span></div>
            </div>
        </div>
    </div>
</div>

<?php
    echo IconPackOption::getScript();
?>

<script type="text/javascript" src="../js/clientside-i18n.php"></script>
<script>
    function createHookNode(type, id) {
        <?php
            $langs = I18N::getAvailableLanguagesWithNames();
            $langopts = "";
            foreach ($langs as $code => $name) {
                $langopts .= '<option value="'.$code.'">'.htmlspecialchars($name, ENT_QUOTES).'</option>';
            }
            $opt = new IconPackOption("setting.hooks.hook_list.icons.option.default");

            $hookSummary = '
            <span class="hook-summary-text">'.I18N::resolveArgsHTML("poi.objective_text", false, '<span class="hook-head-objective-text">'.I18N::resolveHTML("admin.clientside.hooks.any_objective").'</span>', '<span class="hook-head-reward-text">'.I18N::resolveHTML("admin.clientside.hooks.any_reward").'</span>').'</span>';

            $hookActions = '
            <div class="pure-g">
                <div class="pure-u-1-3 full-on-mobile"><p>'.I18N::resolveHTML("setting.hooks.hook_list.actions.name").':</p></div>
                <div class="pure-u-2-3 full-on-mobile"><p><select class="hook-actions" name="hook_{%ID%}[action]">
                    <option value="none" selected>'.I18N::resolveHTML("setting.hooks.hook_list.actions.option.none").'</option>
                    <option value="enable">'.I18N::resolveHTML("setting.hooks.hook_list.actions.option.enable").'</option>
                    <option value="disable">'.I18N::resolveHTML("setting.hooks.hook_list.actions.option.disable").'</option>
                    <option value="delete">'.I18N::resolveHTML("setting.hooks.hook_list.actions.option.delete").'</option>
                </select></p></div>
            </div>';

            $hookCommonSettings = '
            <div class="pure-g">
                <div class="pure-u-1-3 full-on-mobile"><p>'.I18N::resolveHTML("setting.hooks.hook_list.language.name").':</p></div>
                <div class="pure-u-2-3 full-on-mobile">
                    <p><select class="hook-lang" name="hook_{%ID%}[lang]">'.$langopts.'</select></p>
                </div>
            </div>
            <div class="pure-g">
                <div class="pure-u-1-3 full-on-mobile"><p>'.I18N::resolveHTML("setting.hooks.hook_list.icons.name").':</p></div>
                <div class="pure-u-2-3 full-on-mobile">
                    <p>'.$opt->getControl(null, "hook_{%ID%}[iconSet]", "{%ID%}-icon-selector", array("class" => "hook-icon-set")).'</p>
                </div>
            </div>
            '.$opt->getFollowingBlock(false, false).'
            <div class="pure-g">
                <div class="pure-u-1-3 full-on-mobile"><p>'.I18N::resolveHTML("setting.hooks.hook_list.geofence.name").':</p></div>
                <div class="pure-u-2-3 full-on-mobile">
                    <p><textarea class="hook-geofence" name="hook_{%ID%}[geofence]" data-validate-as="geofence"></textarea></p>
                </div>
            </div>';

            $hookSyntaxHelp = '
            <p><a class="hook-show-help" href="#">'.I18N::resolveHTML("admin.clientside.hooks.syntax.show").'</a></p>
            <div class="hook-syntax-help hidden-by-default">
                <div class="hook-syntax-block full-on-mobile">
                    <h3>'.I18N::resolveHTML("admin.hooks.syntax.poi.title").'</h3>
                    '.I18N::resolveArgsHTML("admin.hooks.syntax.poi.poi", false, '<code>&lt;%POI%&gt;</code>').'<br />
                    '.I18N::resolveArgsHTML("admin.hooks.syntax.poi.lat", false, '<code>&lt;%LAT%&gt;</code>').'<br />
                    '.I18N::resolveArgsHTML("admin.hooks.syntax.poi.lng", false, '<code>&lt;%LNG%&gt;</code>').'<br />
                    '.I18N::resolveArgsHTML("admin.hooks.syntax.poi.coords", false, '<code>&lt;%COORDS%&gt;</code>').'<br />
                    '.I18N::resolveArgsHTML("admin.hooks.syntax.poi.navurl", false, '<code>&lt;%NAVURL%&gt;</code>').'
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
                    <h3>'.I18N::resolveHTML("admin.hooks.syntax.icons.title").'</h3>
                    '.I18N::resolveArgsHTML("admin.hooks.syntax.icons.objective_icon", false, '<code>&lt;%OBJECTIVE_ICON(format,variant)%&gt;</code>').'<br />
                    '.I18N::resolveArgsHTML("admin.hooks.syntax.icons.reward_icon", false, '<code>&lt;%REWARD_ICON(format,variant)%&gt;</code>').'
                </div>
                <div>
                    <h3>'.I18N::resolveHTML("admin.hooks.syntax.other.title").'</h3>
                    '.I18N::resolveArgsHTML("admin.hooks.syntax.other.i18n", false, '<code>&lt;%I18N(token[,arg1[,arg2...]])%&gt;</code>').'<br />
                </div>
            </div>';

            $hookFilters = '
            <div class="pure-g">
                <div class="pure-u-1-2 full-on-mobile hook-filter-objectives">
                    <h2>'.I18N::resolveArgsHTML("admin.section.hooks.objectives.name", false, '<a class="hook-objective-add hook-filter-add" href="#">', '</a>').'</h2>
                    <p>'.I18N::resolveHTML("setting.hooks.hook_list.filter_mode.name").':</p>
                    <p><select class="hook-mode-objective" name="hook_{%ID%}[filterModeObjective]" disabled>
                        <option value="whitelist">'.I18N::resolveHTML("setting.hooks.hook_list.filter_mode.objective.option.whitelist.name").'</option>
                        <option value="blacklist">'.I18N::resolveHTML("setting.hooks.hook_list.filter_mode.objective.option.blacklist.name").'</option>
                    </select></p>
                </div>
                <div class="pure-u-1-2 full-on-mobile hook-filter-rewards">
                    <h2>'.I18N::resolveArgsHTML("admin.section.hooks.rewards.name", false, '<a class="hook-reward-add hook-filter-add" href="#">', '</a>').'</h2>
                    <p>'.I18N::resolveHTML("setting.hooks.hook_list.filter_mode.name").':</p>
                    <p><select class="hook-mode-reward" name="hook_{%ID%}[filterModeReward]" disabled>
                        <option value="whitelist">'.I18N::resolveHTML("setting.hooks.hook_list.filter_mode.reward.option.whitelist.name").'</option>
                        <option value="blacklist">'.I18N::resolveHTML("setting.hooks.hook_list.filter_mode.reward.option.blacklist.name").'</option>
                    </select></p>
                </div>
            </div>';
        ?>
        var html;
        if (type == "json") {
            html = <?php
                $node = '
                    <div class="hook-instance" data-hook-id="{%ID%}">
                        <div class="hook-head">
                            <span class="hook-action">'.I18N::resolveHTML("setting.hooks.add.type.option.json").'</span> &rarr; <span class="hook-domain">'.I18N::resolveHTML("admin.clientside.domain.unknown").'</span><br />
                            '.$hookSummary.'
                        </div>
                        <div class="hook-body hidden-by-default">
                            <input type="hidden" name="hook_{%ID%}[type]" value="json">
                            '.$hookActions.'
                            <h2>'.I18N::resolveHTML("admin.section.hooks.settings.name").'</h2>
                            <div class="pure-g">
                                <div class="pure-u-1-3 full-on-mobile"><p>'.I18N::resolveHTML("setting.hooks.hook_list.webhook_url.name").':</p></div>
                                <div class="pure-u-2-3 full-on-mobile"><p><input type="text" class="hook-target" name="hook_{%ID%}[target]" data-uri-scheme="http" data-validate-as="http-uri"></p></div>
                            </div>
                            '.$hookCommonSettings.'
                            <h2>'.I18N::resolveHTML("admin.section.hooks.body.json.name").'</h2>
                            '.$hookSyntaxHelp.'
                            <textarea class="hook-payload" name="hook_{%ID%}[body]" rows="8" data-validate-as="json"></textarea>
                            '.$hookFilters.'
                        </div>
                    </div>
                ';
                echo json_encode($node);
            ?>;
        } else if (type == "telegram") {
            html = <?php
                $node = '
                    <div class="hook-instance" data-hook-id="{%ID%}">
                        <div class="hook-head">
                            <span class="hook-action">'.I18N::resolveHTML("setting.hooks.add.type.option.telegram").'</span> &rarr; <span class="hook-domain">'.I18N::resolveHTML("admin.clientside.domain.unknown").'</span><br />
                            '.$hookSummary.'
                        </div>
                        <div class="hook-body hidden-by-default">
                            <input type="hidden" name="hook_{%ID%}[type]" value="telegram">
                            '.$hookActions.'
                            <h2>'.I18N::resolveHTML("admin.section.hooks.settings.name").'</h2>
                            <div class="pure-g">
                                <div class="pure-u-1-3 full-on-mobile"><p>'.I18N::resolveHTML("setting.hooks.hook_list.tg.bot_token.name").':</p></div>
                                <div class="pure-u-2-3 full-on-mobile"><p><input type="text" class="hook-tg-bot-token" name="hook_{%ID%}[tg][bot_token]" data-validate-as="regex-string" data-validate-regex="^\d+:[A-Za-z\d]+$"></p></div>
                            </div>
                            <div class="pure-g">
                                <div class="pure-u-1-3 full-on-mobile"><p>'.I18N::resolveHTML("setting.hooks.hook_list.webhook_url.name").':</p></div>
                                <div class="pure-u-2-3 full-on-mobile"><p><select class="hook-target" name="hook_{%ID%}[target]" data-uri-scheme="tg" data-validate-as="tg-uri">
                                    <optgroup label="'.I18N::resolveHTML("setting.hooks.hook_list.tg.webhook_url.option.current").'" class="hook-target-current-group">
                                        <option value="" selected></option>
                                    </optgroup>
                                    <optgroup label="'.I18N::resolveHTML("setting.hooks.hook_list.tg.webhook_url.option.other").'">
                                        <option value="_select">&lt; '.I18N::resolveHTML("setting.hooks.hook_list.tg.webhook_url.option.select").' &gt;</option>
                                    </optgroup>
                                </select></p></div>
                            </div>
                            <div class="pure-g">
                                <div class="pure-u-1-3 full-on-mobile"><p>'.I18N::resolveHTML("setting.hooks.hook_list.tg.parse_mode.name").':</p></div>
                                <div class="pure-u-2-3 full-on-mobile"><p><select class="hook-tg-parse-mode" name="hook_{%ID%}[tg][parse_mode]">
                                    <option value="txt">'.I18N::resolveHTML("setting.hooks.hook_list.tg.parse_mode.option.txt").'</option>
                                    <option value="md">'.I18N::resolveHTML("setting.hooks.hook_list.tg.parse_mode.option.md").'</option>
                                    <option value="html">'.I18N::resolveHTML("setting.hooks.hook_list.tg.parse_mode.option.html").'</option>
                                </select></p></div>
                            </div>
                            <div class="pure-g">
                                <div class="pure-u-1-3 full-on-mobile"><p>'.I18N::resolveHTML("setting.hooks.hook_list.tg.disable_web_page_preview.name").':</p></div>
                                <div class="pure-u-2-3 full-on-mobile"><p><label for="hook-bool-disable_web_page_preview-{%ID%}"><input type="checkbox" id="hook-bool-disable_web_page_preview-{%ID%}" class="hook-tg-disable-web-page-preview" name="hook_{%ID%}[tg][disable_web_page_preview]"> '.I18N::resolveHTML("setting.hooks.hook_list.tg.disable_web_page_preview.label").'</label></p></div>
                            </div>
                            <div class="pure-g">
                                <div class="pure-u-1-3 full-on-mobile"><p>'.I18N::resolveHTML("setting.hooks.hook_list.tg.disable_notification.name").':</p></div>
                                <div class="pure-u-2-3 full-on-mobile"><p><label for="hook-bool-disable_notification-{%ID%}"><input type="checkbox" id="hook-bool-disable_notification-{%ID%}" class="hook-tg-disable-notification" name="hook_{%ID%}[tg][disable_notification]"> '.I18N::resolveHTML("setting.hooks.hook_list.tg.disable_notification.label").'</label></p></div>
                            </div>
                            '.$hookCommonSettings.'
                            <h2 class="hook-body-header">'.I18N::resolveHTML("admin.section.hooks.body.txt.name").'</h2>
                            '.$hookSyntaxHelp.'
                            <textarea class="hook-payload" name="hook_{%ID%}[body]" rows="8"></textarea>
                            '.$hookFilters.'
                        </div>
                    </div>
                ';
                echo json_encode($node);
            ?>;
        };

        html = html.split("{%ID%}").join(id);

        var node = $.parseHTML(html);
        return node;
    }

    var objectives = <?php echo json_encode(Research::OBJECTIVES); ?>;
    var rewards = <?php echo json_encode(Research::REWARDS); ?>;
    $(".hook-list").on("change", ".hook-actions", function() {
        if ($(this).val() == "delete") {
            $(this).css("border", "1px solid red");
            $(this).css("color", "red");
            $(this).css("margin-right", "");
        } else if ($(this).val() == "enable") {
            var color = <?php echo Config::getJS("themes/color/admin"); ?> == "dark" ? "lime" : "green";
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

    var hooks = <?php
        $hooks = Config::get("webhooks");
        if ($hooks === null) $hooks = array();

        echo json_encode($hooks);
    ?>

    var presets = <?php
        echo json_encode($presets);
    ?>
</script>
<script type="text/javascript" src="./js/hooks.js"></script>
