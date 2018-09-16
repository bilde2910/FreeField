<?php
/*
    This file contains functions to retrieve all available field research tasks
    and rewards currently implemented in FreeField.

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

    toString($data)
        A PHP handler for outputting the parameter to a text string. The
        variable `$data` is passed containing the data object.

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
    public function toString($data) {
        return strval($data);
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
    public function toString($data) {
        return strval($data);
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

    /*
        The number of the highest species from each generation.
    */
    private const GENERATIONS_HIGHEST = array(151, 251, 386);

    private static $highest_species = null;
    private static $last_species = null;

    public function __construct() {
        /*
            `$highest_species` is an integer representing the highest number
            across all available generations.
        */
        if (self::$highest_species === null) {
            self::$highest_species = self::GENERATIONS_HIGHEST[count(self::GENERATIONS_HIGHEST) - 1];
        }
    }

    public function getAvailable() {
        return array("objectives");
    }
    public function html($id, $class) {
        __require("i18n");

        /*
            Due to the sheer number of species currently available,
            `ParamSpecies` will query the POI database for a list of all
            research objectives that have been reported (and not overwritten by
            another objective) with the "species" parameter. If any species are
            found here, those species will be considered common species, and
            should be placed at the top of the list of species to make them
            easier to find for users. Hence, if one user reports some specific
            species connected to a research task, other users will much more
            easily find those species in the list if they encounter the same
            quest later.

            This caching mechanism can be cleared by clearing all research tasks
            from the POI database, i.e. resetting all objectives to "unknown".
            This will clear all objectives that have a "species" parameter in
            the database, making this function unable to find any results.
        */
        if (self::$last_species === null) {
            __require("geo");
            $pois = Geo::listPOIs();

            $previous_species = array();
            foreach ($pois as $poi) {
                $task = $poi->getLastObjective();
                if (isset($task["params"]["species"])) {
                    $species = $task["params"]["species"];
                    foreach ($species as $current) {
                        $previous_species[] = $current;
                    }
                }
            }

            /*
                There will almost certainly be duplicates, so we filter those
                out before sorting the array in ascending order by species
                number.
            */
            self::$last_species = array_unique($previous_species);
            sort(self::$last_species);
        }

        /*
            A variable that stores the <optgroup>s and <option>s within the
            species selection box. There will be three separate species
            selection boxes, hence using a variable and echoing it three times
            (once for each box) saves on code reuse.
        */
        $species_opts = '';

        /*
            If any recent species were found, we'll create a separate <optgroup>
            for them labeled "Recent species".
        */
        if (count(self::$last_species) > 0) {
            $species_opts .= '<optgroup label="'.I18N::resolveHTML("parameter.species.recent.label").'">';
            foreach (self::$last_species as $species) {
                $species_opts .= '<option value="'.$species.'">'.
                                 I18N::resolveHTML("species.{$species}.name").
                                 '</option>';
            }
            $species_opts .= '</optgroup>';
        }

        /*
            List all species' names, grouped by the generation they belong to.
        */
        $species_opts .= '<optgroup label="'.I18N::resolveHTML("generation.1.label").'">';
        $current_gen_idx = 0;
        for ($i = 1; $i <= self::$highest_species; $i++) {
            if ($i > self::GENERATIONS_HIGHEST[$current_gen_idx]) {
                $current_gen_idx++;
                $species_opts .= '</optgroup><optgroup label="'.
                                 I18N::resolveHTML("generation.".($current_gen_idx + 1).".label").
                                 '">';
            }
            $species_opts .= '<option value="'.$i.'">'.I18N::resolveHTML("species.{$i}.name").'</option>';
        }
        $species_opts .= '</optgroup>';

        /*
            The species parameter has three input boxes, as research objectives
            may require any of a set of species rather than only one particular
            species (up to three different species).
        */
        return
            '<p><select id="'.$id.'-1" class="'.$class.'">'.$species_opts.'</select></p>
            <p><select id="'.$id.'-2" class="'.$class.'">
                <option value="none">'.I18N::resolveHTML("ui.dropdown.none_selected").'</option>
                '.$species_opts.'
            </select></p>
            <p><select id="'.$id.'-3" class="'.$class.'">
                <option value="none">'.I18N::resolveHTML("ui.dropdown.none_selected").'</option>
                '.$species_opts.'
            </select></p>';
    }
    public function writeJS($id) {
        return
            'var out = [];
            for (var i = 1; i <= 3; i++) {
                var val = $("#'.$id.'-" + i).val();
                if (val !== "none") {
                    out.push(parseInt(val));
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
    public function toString($data) {
        __require("i18n");

        if (count($data) == 1) {
            return I18N::resolve(
                "species.".$data[0].".name"
            );
        } elseif (count($data) == 2) {
            return I18N::resolveArgs(
                "multi.species.double",
                I18N::resolve("species.".$data[0].".name"),
                I18N::resolve("species.".$data[1].".name")
            );
        } elseif (count($data) == 3) {
            return I18N::resolveArgs(
                "multi.species.triple",
                I18N::resolve("species.".$data[0].".name"),
                I18N::resolve("species.".$data[1].".name"),
                I18N::resolve("species.".$data[2].".name")
            );
        } else {
            return strval($data);
        }
    }
    public function toStringJS() {
        return
            'if (data.length == 1) {
                return resolveI18N(
                    "species." + data[0] + ".name"
                );
            } else if (data.length == 2) {
                return resolveI18N(
                    "multi.species.double",
                    resolveI18N("species." + data[0] + ".name"),
                    resolveI18N("species." + data[1] + ".name")
                );
            } else if (data.length == 3) {
                return resolveI18N(
                    "multi.species.triple",
                    resolveI18N("species." + data[0] + ".name"),
                    resolveI18N("species." + data[1] + ".name"),
                    resolveI18N("species." + data[2] + ".name")
                );
            } else {
                return data.toString();
            }';
    }
    public function isValid($data) {
        if (!is_array($data)) return false;
        if (count($data) == 0) return false;
        if (count($data) > 3) return false;

        foreach ($data as $species) {
            if (!is_int($species)) return false;
            if ($species < 1 || $species > self::$highest_species) return false;
        }

        return true;
    }
}
/*
    Adds a selection box prompting the user for up to three species types
    (e.g. Water, Ice, Ground, Fire, etc.). This parameter is stored as an
    array of strings.
*/
class ParamType {
    private const TYPES = array(
        "normal",   "fighting", "flying",
        "poison",   "ground",   "rock",
        "bug",      "ghost",    "steel",
        "fire",     "water",    "grass",
        "electric", "psychic",  "ice",
        "dragon",   "dark",     "fairy"
    );

    public function getAvailable() {
        return array("objectives");
    }
    public function html($id, $class) {
        __require("i18n");

        $output = "";
        /*
            The type parameter has three input boxes, as research objectives may
            require any of a set of types rather than only one particular type
            (up to three different types).
        */
        for ($i = 1; $i <= 3; $i++) {
            $output .= '<p><select id="'.$id.'-'.$i.'" class="'.$class.'">';
            if ($i >= 2) {
                $output .= '<option value="none">'.
                           I18N::resolveHTML("ui.dropdown.none_selected").
                           '</option>';
            }
            foreach (self::TYPES as $type) {
                $output .= '<option value="'.$type.'">'.
                           I18N::resolveHTML("type.{$type}").
                           '</option>';
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
    public function toString($data) {
        __require("i18n");

        if (count($data) == 1) {
            return I18N::resolveArgs(
                "multi.type.single",
                I18N::resolve("type.".$data[0])
            );
        } elseif (count($data) == 2) {
            return I18N::resolveArgs(
                "multi.type.double",
                I18N::resolve("type.".$data[0]),
                I18N::resolve("type.".$data[1])
            );
        } elseif (count($data) == 3) {
            return I18N::resolveArgs(
                "multi.type.triple",
                I18N::resolve("type.".$data[0]),
                I18N::resolve("type.".$data[1]),
                I18N::resolve("type.".$data[2])
            );
        } else {
            return strval($data);
        }
    }
    public function toStringJS() {
        return
            'if (data.length == 1) {
                return resolveI18N(
                    "multi.type.single",
                    resolveI18N("type." + data[0])
                );
            } else if (data.length == 2) {
                return resolveI18N(
                    "multi.type.double",
                    resolveI18N("type." + data[0]),
                    resolveI18N("type." + data[1])
                );
            } else if (data.length == 3) {
                return resolveI18N(
                    "multi.type.triple",
                    resolveI18N("type." + data[0]),
                    resolveI18N("type." + data[1]),
                    resolveI18N("type." + data[2])
                );
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
    private const OBJECTIVES_FILE = __DIR__."/../data/objectives.yaml";
    private const REWARDS_FILE = __DIR__."/../data/rewards.yaml";

    public const PARAMETERS = array(

        // CLass mappings for each parameter
        "quantity" => "ParamQuantity",
        "min_tier" => "ParamMinTier",
        "species" => "ParamSpecies",
        "type" => "ParamType"

    );

    private static $objectives = null;
    private static $rewards = null;

    /*
        Returns the complete list of objectives registered in FreeField.
    */
    public static function listObjectives() {
        if (self::$objectives === null) {
            __require("vendor/spyc");
            self::$objectives = Spyc::YAMLLoad(self::OBJECTIVES_FILE);
            /*
                Add the standard fallback objective "unknown" to the list.
            */
            self::$objectives["unknown"] = array(
                "params" => array(),
                "categories" => array("unknown")
            );
        }
        return self::$objectives;
    }

    /*
        Returns the defition of the given objective.
    */
    public static function getObjective($objective) {
        $objectives = self::listObjectives();
        if (isset($objectives[$objective])) {
            return $objectives[$objective];
        } else {
            return null;
        }
    }

    /*
        Returns the complete list of rewards registered in FreeField.
    */
    public static function listRewards() {
        if (self::$rewards === null) {
            __require("vendor/spyc");
            self::$rewards = Spyc::YAMLLoad(self::REWARDS_FILE);
            /*
                Add the standard fallback reward "unknown" to the list.
            */
            self::$rewards["unknown"] = array(
                "params" => array(),
                "categories" => array("unknown")
            );
        }
        return self::$rewards;
    }

    /*
        Returns the defition of the given reward.
    */
    public static function getReward($reward) {
        $rewards = self::listRewards();
        if (isset($rewards[$reward])) {
            return $rewards[$reward];
        } else {
            return null;
        }
    }

    /*
        Checks whether or not the given objective is valid.
    */
    public static function isObjectiveValid($type, $params) {
        $objdef = self::getObjective($type);
        if ($objdef !== null) {
            $validParams = $objdef["params"];

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
                if (
                    !in_array("objectives", $inst->getAvailable()) ||
                    !$inst->isValid($data)
                ) {
                    return false;
                }
            }

            return true;
        } else {
            return false;
        }
    }

    /*
        Checks whether or not the given reward is valid.
    */
    public static function isRewardValid($type, $params) {
        $rewdef = self::getReward($type);
        if ($rewdef !== null) {
            $validParams = $rewdef["params"];

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
                if (
                    !in_array("rewards", $inst->getAvailable()) ||
                    !$inst->isValid($data)
                ) {
                    return false;
                }
            }

            return true;
        } else {
            return false;
        }
    }

    /*
        Checks whether objective or reward 1 matches objective or reward 2.
    */
    public static function matches($type1, $params1, $type2, $params2) {
        if ($type1 !== $type2) return false;
        foreach ($params2 as $param => $value) {
            if (!isset($params1[$param])) return false;
            if ($params1[$param] !== $value) return false;
        }
        return true;
    }

    /*
        Localizes an objective object to a human-readable string representation.
    */
    public static function resolveObjective($type, $params) {
        __require("i18n");

        /*
            Get the objective definition from the list of available objectives.
            If the definition is not found, it falls back to a default array.
        */
        $objdef = self::getObjective($type);
        if ($objdef === null) {
            $objdef = self::getObjective("unknown");
        }

        /*
            Defaults to the "objective.<type>.singular" key. If the objective
            accepts the "quantity" parameter, we'll instead resolve either
            "objective.<key>.singular" or "objective.<key>.plural" depending on
            the value of "quantity".
        */
        $i18nstring = I18N::resolve("objective.{$type}.singular");
        if (isset($params["quantity"])) {
            if ($params["quantity"] != 1) {
                $i18nstring = I18N::resolve("objective.{$type}.plural");
            }
        }

        /*
            Resolve parameters and insert them into the localized string.
        */
        for ($i = 0; $i < count($objdef["params"]); $i++) {
            $param = $objdef["params"][$i];
            $i18nstring = str_replace("{%" . ($i + 1) . "}", self::parameterToString(
                $param, $params[$param]), $i18nstring
            );
        }

        return $i18nstring;
    }

    /*
        Localizes a reward object to a human-readable string representation.
    */
    public static function resolveReward($type, $params) {
        __require("i18n");

        /*
            Get the reward definition from the list of available rewards. If the
            definition is not found, it falls back to a default array.
        */
        $rewdef = self::getReward($type);
        if ($rewdef === null) {
            $rewdef = self::getReward("unknown");
        }

        /*
            Defaults to the "reward.<type>.singular" key. If the reward accepts
            the "quantity" parameter, we'll instead resolve either
            "reward.<key>.singular" or "reward.<key>.plural" depending on the
            value of "quantity".
        */
        $i18nstring = I18N::resolve("reward.{$type}.singular");
        if (isset($params["quantity"])) {
            if ($params["quantity"] != 1) {
                $i18nstring = I18N::resolve("reward.{$type}.plural");
            }
        }

        /*
            Resolve parameters and insert them into the localized string.
        */
        for ($i = 0; $i < count($rewdef["params"]); $i++) {
            $param = $rewdef["params"][$i];
            $i18nstring = str_replace("{%" . ($i + 1) . "}", self::parameterToString(
                $param, $params[$param]), $i18nstring
            );
        }

        return $i18nstring;
    }

    /*
        Resolves a parameter to a human-readable string by calling the
        `toString()` function specific to the class of the parameter in
        question.
    */
    private static function parameterToString($param, $data) {
        $class = self::PARAMETERS[$param];
        $inst = new $class();
        return $inst->toString($data);
    }
}

?>
