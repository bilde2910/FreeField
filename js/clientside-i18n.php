<?php

require_once("../includes/lib/global.php");
__require("i18n");
__require("research");

header('Content-Type: application/javascript');

?>

var i18n = <?php
    $entries = array(
        "admin.clientside.*",
        "objective.*",
        "type.*",
        "multi.*",
        "throw_bonus.*",
        "reward.*",
        "poi.*",
        "xhr.*",
        "user_settings.*"
    );

    $i18nmap = array();
    foreach ($entries as $entry) {
        $i18nmap = array_merge($i18nmap, I18N::resolveAll($entry));
    }

    $i18nlist = array();
    foreach ($i18nmap as $key => $value) {
        $i18nlist[] = "'{$key}': ".json_encode($value);
    }

    echo json_encode($i18nmap, JSON_PRETTY_PRINT);
?>

function resolveI18N(key, ...args) {
    if (i18n.hasOwnProperty(key)) {
        var resolv = i18n[key];
        for (var i = 0; i < args.length; i++) {
            resolv = resolv.split("{%" + (i + 1) + "}").join(args[i]);
        }
        return resolv;
    } else {
        return key;
    }
}

function resolveObjective(objective) {
    var objdef = {
        "categories": null,
        "params": []
    };
    if (objectives.hasOwnProperty(objective.type)) {
        objdef = objectives[objective.type];
    }

    var i18nstring = resolveI18N("objective." + objective.type);
    if (objective.params.hasOwnProperty("quantity")) {
        if (objective.params.quantity == 1) {
            i18nstring = resolveI18N("objective." + objective.type + ".singular");
        } else {
            i18nstring = resolveI18N("objective." + objective.type + ".plural");
        }
    }
    if (objective.params.constructor !== Array) {
        for (var i = 0; i < objdef.params.length; i++) {
            var param = objdef.params[i];
            i18nstring = i18nstring.split("{%" + (i + 1) + "}").join(parameterToString(param, objective.params[param]));
        }
    }
    return i18nstring;
}

function resolveReward(reward) {
    var rewdef = {
        "categories": null,
        "params": []
    };
    if (rewards.hasOwnProperty(reward.type)) {
        rewdef = rewards[reward.type];
    }

    var i18nstring = resolveI18N("reward." + reward.type);
    if (reward.params.hasOwnProperty("quantity")) {
        if (reward.params.quantity == 1) {
            i18nstring = resolveI18N("reward." + reward.type + ".singular");
        } else {
            i18nstring = resolveI18N("reward." + reward.type + ".plural");
        }
    }
    if (reward.params.constructor !== Array) {
        for (var i = 0; i < rewdef.params.length; i++) {
            var param = rewdef.params[i];
            i18nstring = i18nstring.split("{%" + (i + 1) + "}").join(parameterToString(param, reward.params[param]));
        }
    }
    return i18nstring;
}

function parameterToString(param, data) {
    switch (param) {
        <?php
            foreach (Research::PARAMETERS as $param => $class) {
                $inst = new $class();
                    echo "case '{$param}':\n";
                    echo $inst->toStringJS()."\n";
                    echo "break;\n";
            }
        ?>
    }
    return data.toString();
}
