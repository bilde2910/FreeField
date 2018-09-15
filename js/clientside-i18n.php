<?php
/*
    This script file is called from various pages to perform client-side I18N
    lookups. It contains an array of strings resolved by the server in this
    script, which can then be used by calling functions on the client.
*/

require_once("../includes/lib/global.php");
__require("i18n");
__require("research");

header('Content-Type: application/javascript');

?>

var i18n = <?php
    /*
        The list of I18N domains to resolve and push to the client.
    */
    $entries = array(

        // Admin pages scripting
        "admin.clientside.*",

        // Setting options: Icon set and paragraph I18N
        "admin.option.icon_set.*",
        "admin.option.paragraph.*",

        // Webhook body format headers
        "admin.section.hooks.body.*",

        // Research objectives
        "objective.*",

        // Species types
        "type.*",

        // Multi-species and multi-type combiners
        "multi.*",

        // Research rewards
        "reward.*",

        // POI strings
        "poi.*",

        // XMLHttpRequest responses
        "xhr.*",

        // Client-side user settings
        "user_settings.*",

        // Species names
        "species.*"

    );

    /*
        Resolve all of the above domains and echo the resulting combined array
        to the script as a JSON object.
    */
    $i18nmap = array();
    foreach ($entries as $entry) {
        $i18nmap = array_merge($i18nmap, I18N::resolveAll($entry));
    }

    echo json_encode($i18nmap, JSON_PRETTY_PRINT);
?>

/*
    Resolves a localized string for the given I18N token.

    Some localized strings may contain argument placeholders, such as "{%1}",
    "{%2}", etc. This function will also replace as many placeholders as
    possible according to this pattern:

        "{%1}" => args[0]
        "{%2}" => args[1]
        "{%3}" => args[2]
        ...

    The function will try to use all replacement strings in `args` if possible.
    possible. If there are more placeholders in the string than elements in
    `args`, the remaining placeholders will be ignored. For example:

        var string = "Hi, my name is {%1}! My favorite color is {%2}.";
        var args = [
            "Alice"
        ];

    Replacement of `$args` into `$string` would result in the output:

        var string = "Hi, my name is Alice! My favorite color is {%2}.";
*/
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

/*
    Localizes an objective object to a human-readable string representation.
*/
function resolveObjective(objective) {
    /*
        Get the objective definition from the list of available objectives. If
        the definition is not found, it falls back to a default array.
    */
    var objdef = {
        "categories": null,
        "params": []
    };
    if (objectives.hasOwnProperty(objective.type)) {
        objdef = objectives[objective.type];
    }

    /*
        Defaults to the "objective.<type>" key. If the objective accepts the
        "quantity" parameter, we'll instead resolve either
        "objective.<key>.singular" or "objective.<key>.plural" depending on the
        value of "quantity".
    */
    var i18nstring = resolveI18N("objective." + objective.type);
    if (objective.params.hasOwnProperty("quantity")) {
        if (objective.params.quantity == 1) {
            i18nstring = resolveI18N("objective." + objective.type + ".singular");
        } else {
            i18nstring = resolveI18N("objective." + objective.type + ".plural");
        }
    }

    /*
        Resolve parameters and insert them into the localized string.
    */
    if (objective.params.constructor !== Array) {
        for (var i = 0; i < objdef.params.length; i++) {
            var param = objdef.params[i];
            i18nstring = i18nstring.split("{%" + (i + 1) + "}").join(
                parameterToString(param, objective.params[param])
            );
        }
    }
    return i18nstring;
}

/*
    Localizes a reward object to a human-readable string representation.
*/
function resolveReward(reward) {
    /*
        Get the reward definition from the list of available rewards. If the
        definition is not found, it falls back to a default array.
    */
    var rewdef = {
        "categories": null,
        "params": []
    };
    if (rewards.hasOwnProperty(reward.type)) {
        rewdef = rewards[reward.type];
    }

    /*
        Defaults to the "reward.<type>" key. If the reward accepts the
        "quantity" parameter, we'll instead resolve either
        "reward.<key>.singular" or "reward.<key>.plural" depending on the value
        of "quantity".
    */
    var i18nstring = resolveI18N("reward." + reward.type);
    if (reward.params.hasOwnProperty("quantity")) {
        if (reward.params.quantity == 1) {
            i18nstring = resolveI18N("reward." + reward.type + ".singular");
        } else {
            i18nstring = resolveI18N("reward." + reward.type + ".plural");
        }
    }

    /*
        Resolve parameters and insert them into the localized string.
    */
    if (reward.params.constructor !== Array) {
        for (var i = 0; i < rewdef.params.length; i++) {
            var param = rewdef.params[i];
            i18nstring = i18nstring.split("{%" + (i + 1) + "}").join(
                parameterToString(param, reward.params[param])
            );
        }
    }
    return i18nstring;
}

/*
    Resolves a parameter to a human-readable string by calling the
    `toStringJS()` function specific to the class of the parameter in question.
*/
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
