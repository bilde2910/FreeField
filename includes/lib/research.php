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

    toString($data, $allParams)
        A PHP handler for outputting the parameter to a text string. The
        variable `$data` is passed containing the data object. `$allParams` is
        also passed, and is an array containing all other parameters for the
        current research objective or reward along with their values, including
        `$data`.

    toStringJS()
        A JavaScript handler for outputting the parameter to a text string. The
        variable `data` is passed containing the data object. `allParams` is
        also passed, and is an object containing all other parameters for the
        current research objective or reward along with their values, including
        `data`.

    isValid($data)
        A PHP function for server-side validation of user data. Should return
        true if $data is a valid instance of the given parameter, and false
        otherwise.

    bestMatch($matchFunc)
        A PHP function that tries to find the research parameter value that best
        matches a given match string. `$matchFunc` is a callable that is called
        with a valid instance of the given parameter, and returns a percentage
        score that indicates how well the parameter matched against the match
        string. `bestMatch()` should brute force a reasonable number of
        parameter instances and then return the data instance that got the
        highest match score when passed to `$matchFunc()`.

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
        return 'var val = parseInt($("#'.$id.'").val());
                if (isNaN(val)) return null;
                return val;';
    }
    public function parseJS($id) {
        return '$("#'.$id.'").val(data);';
    }
    public function toString($data, $allParams) {
        return strval($data);
    }
    public function toStringJS() {
        return 'return data.toString();';
    }
    public function isValid($data) {
        return is_int($data) && $data >= 1;
    }
    public function bestMatch($matchFunc) {
        /*
            Search for the following values:

              - Every value from 1 to 100
              - Every ten values from 100 to 1000
              - Every hundred values from 1000 to 10000
        */
        $matches = array();
        for ($i =    1; $i <   100; $i +=   1) $matches[$i] = $matchFunc($i);
        for ($i =  100; $i <  1000; $i +=  10) $matches[$i] = $matchFunc($i);
        for ($i = 1000; $i < 10000; $i += 100) $matches[$i] = $matchFunc($i);

        /*
            The best match is the one that got the highest score from
            `$matchFunc()` above. Return this match.
        */
        $highestScore = 0;
        $bestMatch = 0;
        foreach ($matches as $data => $score) {
            if ($score > $highestScore) {
                $highestScore = $score;
                $bestMatch = $data;
            }
        }
        return $bestMatch;
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
        return 'var val = parseInt($("#'.$id.'").val());
                if (isNaN(val)) return null;
                return val;';
    }
    public function parseJS($id) {
        return '$("#'.$id.'").val(data);';
    }
    public function toString($data, $allParams) {
        return strval($data);
    }
    public function toStringJS() {
        return 'return data.toString();';
    }
    public function isValid($data) {
        return is_int($data) && $data >= 1 && $data <= 5;
    }
    public function bestMatch($matchFunc) {
        /*
            Search for all raid tiers between 1 and 5, which are the currently
            available raid tiers.
        */
        $matches = array();
        for ($i = 1; $i <= 5; $i++) {
            $matches[$i] = $matchFunc($i);
        }

        /*
            The best match is the one that got the highest score from
            `$matchFunc()` above. Return this match.
        */
        $highestScore = 0;
        $bestMatch = 0;
        foreach ($matches as $data => $score) {
            if ($score > $highestScore) {
                $highestScore = $score;
                $bestMatch = $data;
            }
        }
        return $bestMatch;
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
    const GENERATIONS_HIGHEST = array(151, 251, 386, 493);

    private static $highest_species = null;
    private static $last_species = null;

    public function __construct() {
        self::getHighestSpecies();
    }

    public static function getHighestSpecies() {
        /*
            `$highest_species` is an integer representing the highest number
            across all available generations.
        */
        if (self::$highest_species === null) {
            self::$highest_species = self::GENERATIONS_HIGHEST[count(self::GENERATIONS_HIGHEST) - 1];
        }
        return self::$highest_species;
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
    public function toString($data, $allParams) {
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
    public function bestMatch($matchFunc) {
        /*
            With 500 possible species, the number of total combinations between
            all species is 124.5M. Brute-forcing this many combinations for the
            best match is infeasible. To remedy this, we match each of the
            species individually instead, and then figure out which one(s) got
            the highest scores and are therefore likely part of the match
            string.
        */
        $bestSpecies = array();
        for ($i = 1; $i <= self::$highest_species; $i++) {
            $bestSpecies[$i] = $matchFunc(array($i));
        }

        /*
            Limit the search space to the top 15 matches. This reduces the
            search space from 124.5M to 2.9k possible combinations. The
            likelihood that the species in the match string are not all
            represented in this subset of species is negligible.
        */
        arsort($bestSpecies);
        $selection = array_keys(array_slice($bestSpecies, 0, 15, true));

        /*
            Search for one, two and three species combinations from the species
            subset defined above, avoiding duplicates in the combination. Each
            of these are rated against the matching function.
        */
        $matches = array();
        foreach ($selection as $spec1) {
            $arr = array($spec1);
            $matches[$matchFunc($arr)] = $arr;
            foreach ($selection as $spec2) {
                if ($spec1 == $spec2) continue;
                $arr = array($spec1, $spec2);
                $matches[$matchFunc($arr)] = $arr;
                foreach ($selection as $spec3) {
                    if ($spec1 == $spec3 || $spec2 == $spec3) continue;
                    $arr = array($spec1, $spec2, $spec3);
                    $matches[$matchFunc($arr)] = $arr;
                }
            }
        }

        /*
            The best match is the one that got the highest score from
            `$matchFunc()` above. Return this match.
        */
        $highestScore = 0;
        $bestMatch = 0;
        foreach ($matches as $score => $data) {
            if ($score > $highestScore) {
                $highestScore = $score;
                $bestMatch = $data;
            }
        }
        return $bestMatch;
    }
}
/*
    Adds a selection box prompting the user for up to three species types
    (e.g. Water, Ice, Ground, Fire, etc.). This parameter is stored as an
    array of strings.
*/
class ParamType {
    const TYPES = array(
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
    public function toString($data, $allParams) {
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
    public function bestMatch($matchFunc) {
        /*
            Search for one, two and three type combinations from the type list,
            avoiding duplicates in the combination. This is a total of 5.2k
            possible combinations to brute-force against the matching function.
        */
        $matches = array();
        foreach (self::TYPES as $type1) {
            $arr = array($type1);
            $matches[$matchFunc($arr)] = $arr;
            foreach (self::TYPES as $type2) {
                if ($type1 == $type2) continue;
                $arr = array($type1, $type2);
                $matches[$matchFunc($arr)] = $arr;
                foreach (self::TYPES as $type3) {
                    if ($type1 == $type3 || $type2 == $type3) continue;
                    $arr = array($type1, $type2, $type3);
                    $matches[$matchFunc($arr)] = $arr;
                }
            }
        }

        /*
            The best match is the one that got the highest score from
            `$matchFunc()` above. Return this match.
        */
        $highestScore = 0;
        $bestMatch = 0;
        foreach ($matches as $score => $data) {
            if ($score > $highestScore) {
                $highestScore = $score;
                $bestMatch = $data;
            }
        }
        return $bestMatch;
    }
}
/*
    Adds a selection box prompting the user for an item. This parameter is
    stored as a string. Items are picked from the rewards pool and filtered
    according to the categories they belong to, based on the
*/
class ParamReward {
    private static $allRewards = null;
    private $usableRewards = array();

    /*
        This class must be initialized with an array of supported reward
        categories. Pass this array to `$categories`.
    */
    public function __construct($categories) {
        if (self::$allRewards === null) {
            self::$allRewards = Research::listRewards();
        }
        /*
            Loop over every reward and check if each reward have any categories
            that match any of those specified in `$categories`.
        */
        foreach (self::$allRewards as $reward => $def) {
            if ($reward == "unknown") continue;

            $usableReward = false;
            foreach ($def["categories"] as $foundCategory) {
                if (in_array($foundCategory, $categories)) {
                    $usableReward = true;
                }
            }
            if ($usableReward) {
                /*
                    The reward has a matching category. Add it to the list of
                    usable rewards, in an array representing its category.
                */
                $this->usableRewards[$def["categories"][0]][] = $reward;
            }
        }
    }

    public function getAvailable() {
        return array("objectives");
    }
    public function html($id, $class) {
        __require("i18n");

        $output = '<p><select id="'.$id.'" class="'.$class.'">';
        foreach ($this->usableRewards as $category => $rewards) {
            $output .= '<optgroup label="'
                     . I18N::resolveHTML("category.reward.{$category}").'">';
            foreach ($rewards as $reward) {
                $output .= '<option value="'.$reward.'">'.
                           I18N::resolveHTML("reward.{$reward}.general").
                           '</option>';
            }
            $output .= '</optgroup>';
        }
        $output .= '</select></p>';
        return $output;
    }
    public function writeJS($id) {
        return
            'return $("#'.$id.'").val();';
    }
    public function parseJS($id) {
        return
            '$("#'.$id.'").val(data);';
    }
    public function toString($data, $allParams) {
        __require("i18n");
        $i18nstring = I18N::resolve("reward.{$data}.general");
        if (isset($allParams["quantity"])) {
            $quantity = $allParams["quantity"];
            if ($quantity != 1) {
                /*
                    If non-singular quantity of items, use the plural key of the
                    reward for I18N lookups, and pass an empty quantity
                    placeholder since the quantity is already expressed
                    immediately before the reward name.
                */
                $i18nstring = trim(
                    I18N::resolveArgs("reward.{$data}.plural", "")
                );
            }
        }
        return $i18nstring;
    }
    public function toStringJS() {
        return
            'var i18nstring = resolveI18N("reward." + data + ".general");
            if (allParams.hasOwnProperty("quantity")) {
                var quantity = allParams.quantity;
                if (quantity != 1) {
                    i18nstring = resolveI18N("reward." + data + ".plural", "").trim();
                }
            }
            return i18nstring;';
    }
    public function isValid($data) {
        if (is_array($data)) return false;
        foreach ($this->usableRewards as $category => $rewards) {
            if (in_array($data, $rewards)) return true;
        }

        return false;
    }
    public function bestMatch($matchFunc) {
        /*
            Find the best match against all items that this parameter supports.
            The total number of combinations to brute-force is low, as there is
            a limited number of items, and no combinations between them.
        */
        $matches = array();
        foreach ($this->usableRewards as $category => $rewards) {
            foreach ($rewards as $reward) {
                $matches[$reward] = $matchFunc($reward);
            }
        }

        /*
            The best match is the one that got the highest score from
            `$matchFunc()` above. Return this match.
        */
        $highestScore = 0;
        $bestMatch = 0;
        foreach ($matches as $data => $score) {
            if ($score > $highestScore) {
                $highestScore = $score;
                $bestMatch = $data;
            }
        }
        return $bestMatch;
    }
}
/*
    Adds a selection box prompting the user for an item that can be used during
    encounters. This parameter implements `ParamReward` with a list of item
    categories usable in encounters.
*/
class ParamEncounterItem extends ParamReward {
    public function __construct() {
        parent::__construct(array(
            "ball", "berry"
        ));
    }
}

class Research {
    const OBJECTIVES_FILE = __DIR__."/../data/objectives.yaml";
    const REWARDS_FILE = __DIR__."/../data/rewards.yaml";
    const COMMON_TASKS_FILE = __DIR__."/../data/common-tasks.yaml";

    const PARAMETERS = array(

        // CLass mappings for each parameter
        "quantity" => "ParamQuantity",
        "min_tier" => "ParamMinTier",
        "species" => "ParamSpecies",
        "type" => "ParamType",
        "encounter_item" => "ParamEncounterItem"

    );

    private static $objectives = null;
    private static $rewards = null;
    private static $commonTasks = null;

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
        Lists common research objectives and their associated parameters.
    */
    public static function listCommonObjectives() {
        if (self::$commonTasks === null) {
            __require("vendor/spyc");
            self::$commonTasks = Spyc::YAMLLoad(self::COMMON_TASKS_FILE);
        }
        return self::$commonTasks;
    }

    /*
        Checks whether or not the given objective is valid.

        `$lenient` can be set to true to allow missing parameters for the given
        reward type.
    */
    public static function isObjectiveValid($type, $params, $lenient = false) {
        $objdef = self::getObjective($type);
        if ($objdef !== null) {
            $validParams = $objdef["params"];

            // Check that all required parameters are present
            foreach ($validParams as $param) {
                if (!$lenient && !isset($params[$param])) {
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

        `$lenient` can be set to true to allow missing parameters for the given
        reward type.
    */
    public static function isRewardValid($type, $params, $lenient = false) {
        $rewdef = self::getReward($type);
        if ($rewdef !== null) {
            $validParams = $rewdef["params"];

            // Check that all required parameters are present
            foreach ($validParams as $param) {
                if (!$lenient && !isset($params[$param])) {
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

        This function performs lenient matching. This means that if there is a
        parameter present in `$params1` that is not present in `$params2`, this
        function can return true. However, if there is a parameter missing in
        `$params1` that is present in `$params2`, the function will fail the
        match and return false.
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
    public static function resolveObjective($type, $params, $forcePlural = false) {
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
            if ($forcePlural || $params["quantity"] != 1) {
                $i18nstring = I18N::resolve("objective.{$type}.plural");
            }
        }

        /*
            Resolve parameters and insert them into the localized string.
        */
        for ($i = 0; $i < count($objdef["params"]); $i++) {
            $param = $objdef["params"][$i];
            if (isset($params[$param])) {
                $i18nstring = str_replace(
                    "{%" . ($i + 1) . "}",
                    self::parameterToString(
                        $param, $params[$param], $params
                    ),
                    $i18nstring
                );
            }
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
            If the reward is an encounter, and it has the species parameter set,
            then it is known what species this reward may provide. Switch the
            type over to the "encounter_specific" I18N reward token that is
            specifically designed to display the name(s) of the possible species
            of the encounter reward.

            The "encounter_specific" reward is entirely virtual and only exists
            as an entry in the I18N files to provide this specific
            functionality.
        */
        if ($type == "encounter" && isset($params["species"])) {
            $type = "encounter_specific";
            $rewdef["params"] = array_merge(array("species"), $rewdef["params"]);
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
            if (isset($params[$param])) {
                $i18nstring = str_replace(
                    "{%" . ($i + 1) . "}",
                    self::parameterToString(
                        $param, $params[$param], $params
                    ),
                    $i18nstring
                );
            }
        }

        return $i18nstring;
    }

    /*
        This function attempts to match the given human-readable objective
        string to an objective type ID and parameter set. Please see
        `matchComponent()` for more information.
    */
    public static function matchObjective($str) {
        return self::matchComponent(
            $str, self::listObjectives(), "Research::resolveObjective"
        );
    }

    /*
        This function attempts to match the given human-readable reward string
        to a reward type ID and parameter set. Please see `matchComponent()` for
        more information.
    */
    public static function matchReward($str) {
        return self::matchComponent(
            $str, self::listRewards(), "Research::resolveReward"
        );
    }

    /*
        This function attempts to match the given human-readable `$str` to an
        objective or reward by brute-forcing objective and parameter
        combinations from `$defList` using a research string resolution function
        `$resolveFunc`. As of v1.1, this function attempts to brute-force the
        string against ~30k possible combinations, selected intelligently by
        each research parameter's class implementation to cut down on the amount
        of research task combinations available (which in total is technically
        infinite).
    */
    private static function matchComponent($str, $defList, $resolveFunc) {
        /*
            Ignore casing on the string by lowercasing everything.
        */
        $matchStr = strtolower($str);
        /*
            We will try to match the search string against every objective/
            reward available in objectives.yaml/rewards.yaml. We will find the
            parameter set that best matches the search string for each of these
            objectives, and at the end, pick the resulting objective that has
            the best overall match against the match string.
        */
        $bestForEach = array();
        foreach ($defList as $type => $defData) {
            /*
                For each objective/reward, loop over its required parameters to
                find the best match for each of them against the match string.
            */
            $params = array();
            $paramDef = $defData["params"];
            foreach ($paramDef as $param) {
                /*
                    Construct an instance of the parameter class in order to run
                    its `bestMatch()` function against the given string.
                */
                $paramClass = self::PARAMETERS[$param];
                $inst = new $paramClass();
                $params[$param] = $inst->bestMatch(
                    function ($try) use (
                        $matchStr, $type, $param, $params, &$resolveFunc
                    ) {
                        /*
                            `$try` is the parameter value proposed by the
                            instance's `bestMatch()` function as a possible
                            match against the string. The value should be
                            matched against the provided match string to
                            determine how well it matches when substituted into
                            the research task proposal. The returned score is a
                            percentage value from 0 to 100. `bestMatch()` uses
                            this score to rank the value proposals for the
                            parameter, and finally returns the best matching
                            parameter value.
                        */
                        $params[$param] = $try;
                        return self::scoreMatch(
                            $resolveFunc, $type, $params, $matchStr
                        );
                    }
                );
            }
            /*
                When the best possible values have been chosen for all
                parameters, we calculate the overall match score for this
                objective/reward and insert the entire objective/reward into the
                `$bestForEach` array for final ranking.
            */
            $overallScore = self::scoreMatch(
                $resolveFunc, $type, $params, $matchStr
            );
            $bestForEach[$overallScore] = array(
                "text" => $resolveFunc($type, $params),
                "type" => $type,
                "params" => $params
            );
        }

        /*
            Sort the `$bestForEach` array in order from highest to lowest score
            and pick the objective/reward that has the highest score. The
            objective/reward that is picked is the one that has the best overall
            match against the match string, and should be returned.
        */
        krsort($bestForEach);
        return reset($bestForEach);
    }

    /*
        This function resolves the given objective/reward `$type` ID and
        `$params` to a human-readable string using `$resolveFunc()` and checks
        its similarity against the given `$match` string. This function is used
        to rank research task candidates against a user-supplied match string to
        identify the best possible research task match against `$match`.
    */
    private static function scoreMatch($resolveFunc, $type, $params, $match) {
        /*
            Check against both singular and plural versions of the research task
            provided.
        */
        $strDefault = strtolower($resolveFunc($type, $params, false));
        $strPlural = strtolower($resolveFunc($type, $params, true));

        $perc1 = 0;
        similar_text($match, $strDefault, $perc1);

        if ($strDefault == $strPlural) {
            /*
                If the singular and plural variants resolve to the same string,
                only check one of them and return its result.
            */
            return $perc1;
        } else {
            /*
                Otherwise, compare similarity against both strings and return
                the highest score of the two. This is to ensure that e.g. "Catch
                a" and "Catch 1" are considered equivalent.
            */
            $perc2 = 0;
            similar_text($match, $strPlural, $perc2);
            return max($perc1, $perc2);
        }
    }

    /*
        Resolves a parameter to a human-readable string by calling the
        `toString()` function specific to the class of the parameter in
        question.
    */
    private static function parameterToString($param, $data, $allParams) {
        $class = self::PARAMETERS[$param];
        $inst = new $class();
        return $inst->toString($data, $allParams);
    }
}

?>
