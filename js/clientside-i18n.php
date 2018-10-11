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
        If the objective parameters is an array, it is most likely empty.
        Convert it to an empty object instead.
    */
    if (objective.params.constructor === Array) {
        objective.params = {};
    }

    /*
        Defaults to the "objective.<type>.singular" key. If the objective
        accepts the "quantity" parameter, we'll instead resolve either
        "objective.<key>.singular" or "objective.<key>.plural" depending on the
        value of "quantity".
    */
    var i18nstring = resolveI18N("objective." + objective.type + ".singular");
    if (objdef.params.indexOf("quantity") != -1) {
        if (!objective.params.hasOwnProperty("quantity") || objective.params.quantity != 1) {
            i18nstring = resolveI18N("objective." + objective.type + ".plural");
        }
    }

    /*
        Resolve parameters and insert them into the localized string.
    */
    for (var i = 0; i < objdef.params.length; i++) {
        var param = objdef.params[i];
        i18nstring = i18nstring.split("{%" + (i + 1) + "}").join(
            objective.params.hasOwnProperty(param)
            ? parameterToString(param, objective.params[param], objective.params)
            : getParamPlaceholder(param)
        );
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
        If the reward parameters is an array, it is most likely empty. Convert
        it to an empty object instead.
    */
    if (reward.params.constructor === Array) {
        reward.params = {};
    }

    /*
        If the reward is an encounter, and it has the species parameter set,
        then it is known what species this reward may provide. Switch the type
        over to the "encounter_specific" I18N reward token that is specifically
        designed to display the name(s) of the possible species of the encounter
        reward.

        The "encounter_specific" reward is entirely virtual and only exists as
        an entry in the I18N files to provide this specific functionality.
    */
    if (reward.type == "encounter" && reward.params.hasOwnProperty("species")) {
        /*
            Ensure that copies are made of the reward and its definition to
            prevent cascading these changes, applied for localization purposes
            only, back up to the reward and rewards definition objects, as
            JavaScript uses call by sharing to pass arguments to functions.
            Changing these objects directly would otherwise break FreeField
            client-side until a reload of the page.
        */
        reward = $.extend(true, {}, reward);
        reward.type = "encounter_specific";
        rewdef = $.extend(true, {}, rewdef);
        rewdef.params.push("species");
    }

    /*
        Defaults to the "reward.<type>.singular" key. If the reward accepts the
        "quantity" parameter, we'll instead resolve either
        "reward.<key>.singular" or "reward.<key>.plural" depending on the value
        of "quantity".
    */
    var i18nstring = resolveI18N("reward." + reward.type + ".singular");
    if (rewdef.params.indexOf("quantity") != -1) {
        if (!reward.params.hasOwnProperty("quantity") || reward.params.quantity != 1) {
            i18nstring = resolveI18N("reward." + reward.type + ".plural");
        }
    }

    /*
        Resolve parameters and insert them into the localized string.
    */
    for (var i = 0; i < rewdef.params.length; i++) {
        var param = rewdef.params[i];
        i18nstring = i18nstring.split("{%" + (i + 1) + "}").join(
            reward.params.hasOwnProperty(param)
            ? parameterToString(param, reward.params[param], reward.params)
            : getParamPlaceholder(param)
        );
    }
    return i18nstring;
}

/*
    Resolves a parameter to a human-readable string by calling the
    `toStringJS()` function specific to the class of the parameter in question.
*/
function parameterToString(param, data, allParams) {
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

/*
    Resolves the placeholder string for the given parameter.
*/
function getParamPlaceholder(param) {
    switch (param) {
        <?php
            foreach (Research::PARAMETERS as $param => $class) {
                $inst = new $class();
                    echo "case '{$param}': return ";
                    echo I18N::resolveJS("parameter.{$param}.placeholder");
                    echo ";\n";
            }
        ?>
    }
}
