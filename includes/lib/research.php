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

    The currently available research objective/reward parameters are:

    "min_tier"
        Adds a number box to the field research box promoting the user for the
        minimum raid level for the "level_raid" objective. This parameter is
        currently only used for "level_raid". This parameter will be stored as
        an integer in the parameters data in the database, as well as in network
        traffic.

    "quantity"
        Adds a number box to the field research box prompting the user for the
        quantity of items awarded in a reward/quantity of catches required for
        a catch quest, etc. This parameter is stored as an integer

    "species"
        Adds a selection box prompting the user for up to three different
        species (e.g. Bulbasaur, Charmander, Squirtle, Pikachu, etc.). This
        paramtered is stored as an array of strings.

    "type"
        Adds a selection box prompting the user for up to three species types
        (e.g. Water, Ice, Ground, Fire, etc.). This parameter is stored as an
        array of strings.


    INTERNATIONALIZATION GUIDE

    "categories" are internationalized as following:
        category.[objective|reward].<category_name>

    "params" are internationalized as following:
        - Placeholder values:
        parameter.<param>.placeholder
        - Labels as they appear in the research submission box:
        parameter.<param>.label
*/

class Research {
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
}

?>
