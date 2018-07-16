<?php

/*
    This file contains a list of all available field research tasks and rewards
    currently implemented in FreeField. Each array element in OBJECTIVES and
    REWARDS represents one objective/reward respectively, and contains two
    fields "categories" and "params".

    The "categories" field is an array of categories the objective/reward
    satisfies in decreasing order of specificity. The first item in this array
    is used to organize objectives/rewards into groups (e.g. "battle" =
    objectives related to Gym battles). The whole array is also used as map
    marker/icon fallbacks for when a specific icon is not available for the
    given objective/reward in an icon pack. For example, "win_raid" specifies
    the categories "raid" and "battle" in decreasing order of specificity. This
    means that if an icon pack does not have a specific icon with the label
    "win_raid", it will look for one with the label "raid" instead. If it does
    not have a "raid" icon either, it falls back to "battle", i.e. the next item
    in the categories array. If none of the icons specified here are found, it
    will fall back to "default". If none of the icons, including "default", are
    present in an icon pack, the marker will not be rendered. Hence, it is very
    important that at the very least "default" is available. For an icon pack to
    have any meaningful purpose beyond just a single "default" marker for all
    map POIs, it is also very strongly recommended that icon packs implement
    an icon for all of the categories, to better distinguish objectives/rewards
    from each other on the map. Implementing specific icons for each icon pack
    is optional.

    The "params" field is a list of parameters each research objective/reward
    takes. This can be for example the quantity of items awared by a reward
    (e.g. "5 Potions"), or the type of species required for a specific quest
    (e.g. "Evolve 2 Shellder"). The "params" array closely ties in to the I18N
    strings for each objective/reward, and the order of the items in this array
    corresponds to the order of indices in the I18N strings for the objectives/
    rewards. Consider the example of "level_raid". It is internationalized the
    following way by en-US.ini:

        objective.level_raid.plural = "Win {%2} level {%1} or higher raids

    In the OBJECTIVES array of this file, the same objective has declared the
    folowing "params" array:

        "params" => array("min_tier", "quantity")

    This indicates that the first I18N token of the string {%1} corresponds to
    the first item of the array ("min_tier"), the second {%2} corresponds to the
    second item ("quantity"), and so on.

    The different "params" options have special meanings in how the parameters
    are filled in by map users. E.g. using the "quantity" parameter will add a
    number selection box to the field research task submission form, with a
    label identifying that the input box corresponds to the quantity of items
    awarded/required quantity of evolutions/catches etc.

    The currently available research objective/reward parameters are defined in
    the PARAMETERS array.

    ## THE PARAMETERS ARRAY ##

    The PARAMETERS array contains a list of all valid parameter types with code
    that specifies how the parameter input should be rendered and processed by
    the client. Each entry in the PARAMETERS array represents one parameter.

    Each entry in the array points to a class name that defines how the
    parameter should be processed. This class must contain the following
    functions:

    getAvailable()
        An array of scopes for the parameter type. Valid options are
        "objective", "reward", or both. It makes no sense to specify "min_tier"
        as a reward parameter, for example, so "min_tier" only lists
        "objective". The "quantity" parameter, however, should be available for
        use for both objectives and rewards and thus lists both scopes.

    html($id, $class)
        How the input should be rendered in HTML. This will be used together
        with a corresponding JavaScript processing function.

    writeJS($id)
        A JavaScript handler for writing the user data from the parameter to
        an object or variable. The statement should return this object/variable.

    parseJS($id)
        A JavaScript handler for parsing "js_write" output into the form input
        boxes. The variable `data` is passed containing the data object.

    toStringJS()
        A JavaScript handler for outputting the parameter to a text string. The
        variable `data` is passed containing the data object.

    isValid($data)
        A PHP function for server-side validation of user data. Should return
        true if $data is a valid instance of the given parameter, and false
        otherwise.

    html(), writeJS() and parseJS() will be given an argument $id that should be
    used to designate the ID of the input fields used in HTML and JavaScript and
    is set aside for that parameter. $id is replaced with the correct ID at
    runtime. Do not use HTML IDs that are not based on $id as multiple instances
    of each parameter input may be available on the same page. If the HTML/JS
    code requires multiple objects with unique IDs, consider using IDs such as
    $id.'-1', $id.'-2', etc.

    html() is also passed a $class argument. This argument must be put in the
    class parameter of all input fields in the outputted HTML.

    ## INTERNATIONALIZATION GUIDE ##

    "categories" are internationalized as following:
        category.[objective|reward].<category_name>

    "params" are internationalized as following:
        - Placeholder values:
        parameter.<param>.placeholder
        - Labels as they appear in the research submission box:
        parameter.<param>.label
*/


/*
    Adds a number box to the field research box prompting the user for the
    quantity of items awarded in a reward/quantity of catches required for
    a catch quest, etc. This parameter is stored as an integer
*/
class ParamQuantity {
    public function getAvailable() {
        return array("objectives", "rewards");
    }
    public function html($id, $class) {
        return '<p><input id="'.$id.'" class="'.$class.'" type="number" min="1"></p>';
    }
    public function writeJS($id) {
        return 'return parseInt($("#'.$id.'").val());';
    }
    public function parseJS($id) {
        return '$("#'.$id.'").val(data);';
    }
    public function toStringJS() {
        return 'return data.toString();';
    }
    public function isValid($data) {
        return is_int($data) && $data >= 1;
    }
}
/*
    Adds a number box to the field research box promoting the user for the
    minimum raid level for the "level_raid" objective. This parameter is
    currently only used for "level_raid". This parameter will be stored as
    an integer in the parameters data in the database, as well as in network
    traffic.
*/
class ParamMinTier {
    public function getAvailable() {
        return array("objectives");
    }
    public function html($id, $class) {
        return '<p><input id="'.$id.'" class="'.$class.'" type="number" min="1" max="5"></p>';
    }
    public function writeJS($id) {
        return 'return parseInt($("#'.$id.'").val());';
    }
    public function parseJS($id) {
        return '$("#'.$id.'").val(data);';
    }
    public function toStringJS() {
        return 'return data.toString();';
    }
    public function isValid($data) {
        return is_int($data) && $data >= 1 && $data <= 5;
    }
}
/*
    Adds a selection box prompting the user for up to three different
    species (e.g. Bulbasaur, Charmander, Squirtle, Pikachu, etc.). This
    paramtered is stored as an array of strings.
*/
class ParamSpecies {
    public function getAvailable() {
        return array("objectives");
    }
    public function html($id, $class) {
        // TODO: List all species
        return
            '<p><select id="'.$id.'-1" class="'.$class.'"></select></p>
            <p><select id="'.$id.'-2" class="'.$class.'"></select></p>
            <p><select id="'.$id.'-3" class="'.$class.'"></select></p>';
    }
    public function writeJS($id) {
        return
            'var out = [];
            for (var i = 1; i <= 3; i++) {
                var val = $("#'.$id.'-" + i).val();
                if (val !== "none") {
                    out.push(val);
                }
            }
            return out;';
    }
    public function parseJS($id) {
        return
            'for (var i = 1; i <= 3; i++) {
                if (data.length < i) {
                    $("#'.$id.'-" + i).val("none");
                } else {
                    $("#'.$id.'-" + i).val(data[i - 1]);
                }
            }';
    }
    public function toStringJS() {
        return
            'if (data.length == 1) {
                return data[0];
            } else if (data.length == 2) {
                return resolveI18N("multi.catch.double", data[0], data[1]);
            } else if (data.length == 3) {
                return resolveI18N("multi.catch.triple", data[0], data[1], data[2]);
            } else {
                return data.toString();
            }';
    }
    public function isValid($data) {
        return false;
    }
}
/*
    Adds a selection box prompting the user for up to three species types
    (e.g. Water, Ice, Ground, Fire, etc.). This parameter is stored as an
    array of strings.
*/
class ParamType {
    private const TYPES = array(
        "normal", "fighting", "flying",
        "poison", "ground", "rock",
        "bug", "ghost", "steel",
        "fire", "water", "grass",
        "electric", "psychic", "ice",
        "dragon", "dark", "fairy"
    );

    public function getAvailable() {
        return array("objectives");
    }
    public function html($id, $class) {
        __require("i18n");

        $output = "";
        for ($i = 1; $i <= 3; $i++) {
            $output .= '<p><select id="'.$id.'-'.$i.'" class="'.$class.'">';
            if ($i >= 2) {
                $output .= '<option value="none">'.I18N::resolve("ui.dropdown.none_selected").'</option>';
            }
            foreach (self::TYPES as $type) {
                $output .= '<option value="'.$type.'">'.I18N::resolve("type.{$type}").'</option>';
            }
            $output .='</select></p>';
        }
        return $output;
    }
    public function writeJS($id) {
        return
            'var out = [];
            for (var i = 1; i <= 3; i++) {
                var val = $("#'.$id.'-" + i).val();
                if (val !== "none") {
                    out.push(val);
                }
            }
            return out;';
    }
    public function parseJS($id) {
        return
            'for (var i = 1; i <= 3; i++) {
                if (data.length < i) {
                    $("#'.$id.'-" + i).val("none");
                } else {
                    $("#'.$id.'-" + i).val(data[i - 1]);
                }
            }';
    }
    public function toStringJS() {
        return
            'if (data.length == 1) {
                return resolveI18N("multi.type.single", resolveI18N("type." + data[0]));
            } else if (data.length == 2) {
                return resolveI18N("multi.type.double", resolveI18N("type." + data[0]), resolveI18N("type." + data[1]));
            } else if (data.length == 3) {
                return resolveI18N("multi.type.triple", resolveI18N("type." + data[0]), resolveI18N("type." + data[1]), resolveI18N("type." + data[2]));
            } else {
                return data.toString();
            }';
    }
    public function isValid($data) {
        if (!is_array($data)) return false;
        if (count($data) == 0) return false;
        if (count($data) > 3) return false;

        foreach ($data as $type) {
            if (!in_array($type, self::TYPES)) return false;
        }

        return true;
    }
}

class Research {
    public const PARAMETERS = array(

        // CLass mappings for each parameter
        "quantity" => "ParamQuantity",
        "min_tier" => "ParamMinTier",
        "species" => "ParamSpecies",
        "type" => "ParamType"

    );

    public const OBJECTIVES = array(

        // Gym objectives
        "battle_gym" => array(
            "categories" => array("battle"),
            "params" => array("quantity")
        ),
        "win_gym" => array(
            "categories" => array("battle"),
            "params" => array("quantity")
        ),
        "battle_raid" => array(
            "categories" => array("raid", "battle"),
            "params" => array("quantity")
        ),
        "win_raid" => array(
            "categories" => array("raid", "battle"),
            "params" => array("quantity")
        ),
        "level_raid" => array(
            "categories" => array("raid", "battle"),
            "params" => array("min_tier", "quantity")
        ),
        "se_charge" => array(
            "categories" => array("battle"),
            "params" => array("quantity")
        ),

        // Catch objectives
        "catch" => array(
            "categories" => array("catch"),
            "params" => array("quantity")
        ),
        "catch_weather" => array(
            "categories" => array("catch"),
            "params" => array("quantity")
        ),
        "catch_type" => array(
            "categories" => array("catch"),
            "params" => array("type", "quantity")
        ),
        "catch_specific" => array(
            "categories" => array("catch"),
            "params" => array("species", "quantity")
        ),
        "use_berry" => array(
            "categories" => array("item"),
            "params" => array("quantity")
        ),

        // Walking objectives
        "buddy_candy" => array(
            "categories" => array("buddy"),
            "params" => array("quantity")
        ),
        "hatch" => array(
            "categories" => array("hatch"),
            "params" => array("quantity")
        ),

        // Evolution and power-up objectives
        "evolve" => array(
            "categories" => array("evolve"),
            "params" => array("quantity")
        ),
        "evolve_type" => array(
            "categories" => array("evolve"),
            "params" => array("type", "quantity")
        ),
        "evolve_specific" => array(
            "categories" => array("evolve"),
            "params" => array("species", "quantity")
        ),
        "power_up" => array(
            "categories" => array("power_up"),
            "params" => array("quantity")
        ),

        // Throwing skill objectives
        "throw_simple_nice" => array(
            "categories" => array("throwing_skill"),
            "params" => array("quantity")
        ),
        "throw_simple_nice_chain" => array(
            "categories" => array("throwing_skill"),
            "params" => array("quantity")
        ),
        "throw_simple_great" => array(
            "categories" => array("throwing_skill"),
            "params" => array("quantity")
        ),
        "throw_simple_great_chain" => array(
            "categories" => array("throwing_skill"),
            "params" => array("quantity")
        ),
        "throw_simple_excellent" => array(
            "categories" => array("throwing_skill"),
            "params" => array("quantity")
        ),
        "throw_simple_excellent_chain" => array(
            "categories" => array("throwing_skill"),
            "params" => array("quantity")
        ),
        "throw_curve_nice" => array(
            "categories" => array("throwing_skill"),
            "params" => array("quantity")
        ),
        "throw_curve_nice_chain" => array(
            "categories" => array("throwing_skill"),
            "params" => array("quantity")
        ),
        "throw_curve_great" => array(
            "categories" => array("throwing_skill"),
            "params" => array("quantity")
        ),
        "throw_curve_great_chain" => array(
            "categories" => array("throwing_skill"),
            "params" => array("quantity")
        ),
        "throw_curve_excellent" => array(
            "categories" => array("throwing_skill"),
            "params" => array("quantity")
        ),
        "throw_curve_excellent_chain" => array(
            "categories" => array("throwing_skill"),
            "params" => array("quantity")
        ),

        // Exploration objectives
        "visit_poi" => array(
            "categories" => array("explore"),
            "params" => array("quantity")
        ),
        "new_poi" => array(
            "categories" => array("explore"),
            "params" => array("quantity")
        ),

        // Unknown objective
        "unknown" => array(
            "categories" => array("unknown"),
            "params" => array()
        ),
    );

    public const REWARDS = array(

        // Ball rewards
        "poke_ball" => array(
            "categories" => array("ball"),
            "params" => array("quantity")
        ),
        "great_ball" => array(
            "categories" => array("ball"),
            "params" => array("quantity")
        ),
        "ultra_ball" => array(
            "categories" => array("ball"),
            "params" => array("quantity")
        ),

        // Berry rewards
        "razz_berry" => array(
            "categories" => array("berry"),
            "params" => array("quantity")
        ),
        "nanab_berry" => array(
            "categories" => array("berry"),
            "params" => array("quantity")
        ),
        "pinap_berry" => array(
            "categories" => array("berry"),
            "params" => array("quantity")
        ),
        "golden_razz_berry" => array(
            "categories" => array("berry"),
            "params" => array("quantity")
        ),

        // Potion and revive rewards
        "potion" => array(
            "categories" => array("potion"),
            "params" => array("quantity")
        ),
        "super_potion" => array(
            "categories" => array("potion"),
            "params" => array("quantity")
        ),
        "hyper_potion" => array(
            "categories" => array("potion"),
            "params" => array("quantity")
        ),
        "max_potion" => array(
            "categories" => array("potion"),
            "params" => array("quantity")
        ),
        "revive" => array(
            "categories" => array("revive"),
            "params" => array("quantity")
        ),
        "max_revive" => array(
            "categories" => array("revive"),
            "params" => array("quantity")
        ),

        // Other rewards
        "fast_tm" => array(
            "categories" => array("tm"),
            "params" => array("quantity")
        ),
        "charge_tm" => array(
            "categories" => array("tm"),
            "params" => array("quantity")
        ),
        "stardust" => array(
            "categories" => array("stardust"),
            "params" => array("quantity")
        ),
        "rare_candy" => array(
            "categories" => array("candy"),
            "params" => array("quantity")
        ),
        "encounter" => array(
            "categories" => array("encounter"),
            "params" => array("quantity")
        )
    );

    public static function isObjectiveValid($type, $params) {
        if (isset(self::OBJECTIVES[$type])) {
            $validParams = self::OBJECTIVES[$type]["params"];

            // Check that all required parameters are present
            foreach ($validParams as $param) {
                if (!isset($params[$param])) {
                    return false;
                }
            }
            // Check that all present parameters are acceptable
            foreach ($params as $param => $data) {
                if (!in_array($param, $validParams)) {
                    return false;
                }
            }
            // Check validity of parameters
            foreach ($params as $param => $data) {
                $class = self::PARAMETERS[$param];
                $inst = new $class();
                if (!in_array("objectives", $inst->getAvailable()) || !$inst->isValid($data)) {
                    return false;
                }
            }

            return true;
        } else {
            return false;
        }
    }

    public static function isRewardValid($type, $params) {
        if (isset(self::REWARDS[$type])) {
            $validParams = self::REWARDS[$type]["params"];

            // Check that all required parameters are present
            foreach ($validParams as $param) {
                if (!isset($params[$param])) {
                    return false;
                }
            }
            // Check that all present parameters are acceptable
            foreach ($params as $param => $data) {
                if (!in_array($param, $validParams)) {
                    return false;
                }
            }
            // Check validity of parameters
            foreach ($params as $param => $data) {
                $class = self::PARAMETERS[$param];
                $inst = new $class();
                if (!in_array("rewards", $inst->getAvailable()) || !$inst->isValid($data)) {
                    return false;
                }
            }

            return true;
        } else {
            return false;
        }
    }
}

?>
