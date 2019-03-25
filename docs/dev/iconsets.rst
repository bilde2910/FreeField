Icon sets
=========

Icon sets are sets of map marker assets that FreeField uses to display specific
types of icons on the map, in webhooks and various places in the user interface.
There are two types of assets - icons, which represent classes of objectives and
rewards, and species icons, which represent various species of Pokémon. This
document will explain the file structure of both types of assets, and where and
how to install them in FreeField.

Location
--------

Icon sets can be placed in two locations, relative to the root of the FreeField
installation:

themes/
   All icon sets that ship with FreeField are located here. This directory is
   deleted and re-created with the latest set of icons whenever FreeField is
   updated. Thus, changes made here are lost on every update.

includes/userdata/themes/
   All user-defined icon sets are located here. This directory does not exist by
   default, but it can be created, and custom icon sets installed in it by local
   administrators, and they will show up fully usable in FreeField. Icon sets
   placed here will persist between updates.

If you are making an icon set for inclusion in the public upstream FreeField
repository for others to use, it should be placed and staged in the root themes
directory. If you are making an icon set for personal use in your local
FreeField instance only, it should be placed in the userdata themes directory.

Within each themes directory, there are two subdirectories ``icons`` and
``species``. The former contains icon sets for objective and reward classes,
while the latter contains icon sets that represent Pokémon species.

File structure
--------------

Each icon set has its own root directory. This directory is located within the
``icons`` or ``species`` directories of either of the themes folder locations.
The name of this directory will be the ID of the icon set. **This ID must be
unique across both the root and the userdata themes directories.**

Within each icon set's root directory, there must be a file named "pack.ini"
containing declarations of the file tree and naming structure of the icon set.
The icons themselves are also located in this directory, either directly in the
root, or in one or more subdirectories.

Icon sets support usage of different variants of icons. For example, when using
dark theme, FreeField will attempt to use dark theme icons if the icon set
supports it. These can be distinguished by either putting them in separate
directories, or by appending "light" and "dark" to the file names of the assets
themselves.

The pack.ini metadata file
--------------------------

"pack.ini" is a metadata file containing a description of the file structure of
asset image files, as well as a description of the icon set itself. The format
is slightly different for icon sets and species sets. Both files share a common
header format, but have different body formats for the rest of the file.

Header
^^^^^^

``name``
   The name of the icon set as displayed to users in FreeField.

``author``
   The author of the icon set.

``link``
   A URL pointing to the origin/source location of the icons used in the icon
   set.

``supported_variants``
   A comma-separated list of supported icon color variants (as of the latest
   FreeField version, ``light`` and ``dark`` are the only valid options).

``logo`` *(optional)*
   A logo to display to users when this icon set is selected in FreeField. If
   omitted, no logo is displayed. You can use the ``{%variant%}`` placeholder to
   substitute "light" or "dark" into the file path.

Example header
""""""""""""""

This header will create an icon set named "FreeField 3D Compass" that supports
light and dark theme. The logo will be loaded from ./light/logo.svg if the end
user is using light theme, and ./dark/logo.svg if using dark theme, relative to
the root of the icon set.

.. code-block:: ini

   ; FreeField 3D Compass icon set
   ; Created by bilde2910 (github.com/bilde2910)

   name = "FreeField 3D Compass"
   author = "bilde2910"
   link = "https://github.com/bilde2910/FreeField"

   supported_variants = "light,dark"
   logo = "{%variant%}/logo.svg"

Body for icon sets
^^^^^^^^^^^^^^^^^^

The body follows the header, and contains two sections ``[vector]`` and
``[raster]`` under which icon declarations are made. The sections are the same,
except for the file paths you use. The ``[vector]`` section is optional, and if
omitted, or if any of the icons in ``[raster]`` are missing from ``[vector]``,
those icons will fall back to using the raster versions.

The ``[raster]`` section is required. Files listed in the ``[vector]`` section
must be SVG format, while ``[raster]`` allows PNG, GIF and JPEG. All file paths
can use the ``{%variant%}`` placeholder to substitute "light" or "dark" into the
file path depending on the color theme the user is using.

Under each section, key-value pairs are made that correspond an icon with a file
path to that icon in the icon set. For example, to assign an file to the
``ball`` icon, you could put something like ``ball = "ball.png"``. A list of all
usable icons is available under `List of icon IDs`_ below.

Example body
""""""""""""

This body provides a list of icons in both vector and raster format in light and
dark variants.

.. code-block:: ini

   [vector]

   ; Scalable vector versions

   poke_ball = "{%variant%}/reward/poke-ball.svg"
   great_ball = "{%variant%}/reward/great-ball.svg"
   ultra_ball = "{%variant%}/reward/ultra-ball.svg"

   razz_berry = "{%variant%}/reward/razz-berry.svg"
   nanab_berry = "{%variant%}/reward/nanab-berry.svg"
   pinap_berry = "{%variant%}/reward/pinap-berry.svg"
   golden_razz_berry = "{%variant%}/reward/golden-razz-berry.svg"
   silver_pinap_berry = "{%variant%}/reward/silver-pinap-berry.svg"

   potion = "{%variant%}/reward/potion.svg"
   super_potion = "{%variant%}/reward/super-potion.svg"
   hyper_potion = "{%variant%}/reward/hyper-potion.svg"
   max_potion = "{%variant%}/reward/max-potion.svg"
   revive = "{%variant%}/reward/revive.svg"
   max_revive = "{%variant%}/reward/max-revive.svg"

   sun_stone = "{%variant%}/reward/sun-stone.svg"
   kings_rock = "{%variant%}/reward/kings-rock.svg"
   metal_coat = "{%variant%}/reward/metal-coat.svg"
   dragon_scale = "{%variant%}/reward/dragon-scale.svg"
   up_grade = "{%variant%}/reward/up-grade.svg"
   sinnoh_stone = "{%variant%}/reward/sinnoh-stone.svg"

   fast_tm = "{%variant%}/reward/fast-tm.svg"
   charge_tm = "{%variant%}/reward/charge-tm.svg"
   stardust = "{%variant%}/reward/stardust.svg"
   rare_candy = "{%variant%}/reward/rare-candy.svg"
   encounter = "{%variant%}/reward/encounter.svg"

   battle = "{%variant%}/objective/battle.svg"
   raid = "{%variant%}/objective/raid.svg"
   catch = "{%variant%}/objective/catch.svg"
   throwing_skill = "{%variant%}/objective/throwing-skill.svg"

   hatch = "{%variant%}/objective/hatch.svg"
   buddy = "{%variant%}/objective/buddy.svg"
   explore = "{%variant%}/objective/explore.svg"

   power_up = "{%variant%}/objective/power-up.svg"
   evolve = "{%variant%}/objective/evolve.svg"
   trash = "{%variant%}/objective/trash.svg"
   item = "{%variant%}/objective/item.svg"

   social = "{%variant%}/objective/social.svg"

   unknown = "{%variant%}/unknown.svg"
   default = "{%variant%}/default.svg"

   [raster]

   ; Bitmap versions

   poke_ball = "{%variant%}/reward/poke-ball.png"
   great_ball = "{%variant%}/reward/great-ball.png"
   ultra_ball = "{%variant%}/reward/ultra-ball.png"

   razz_berry = "{%variant%}/reward/razz-berry.png"
   nanab_berry = "{%variant%}/reward/nanab-berry.png"
   pinap_berry = "{%variant%}/reward/pinap-berry.png"
   golden_razz_berry = "{%variant%}/reward/golden-razz-berry.png"
   silver_pinap_berry = "{%variant%}/reward/silver-pinap-berry.png"

   potion = "{%variant%}/reward/potion.png"
   super_potion = "{%variant%}/reward/super-potion.png"
   hyper_potion = "{%variant%}/reward/hyper-potion.png"
   max_potion = "{%variant%}/reward/max-potion.png"
   revive = "{%variant%}/reward/revive.png"
   max_revive = "{%variant%}/reward/max-revive.png"

   sun_stone = "{%variant%}/reward/sun-stone.png"
   kings_rock = "{%variant%}/reward/kings-rock.png"
   metal_coat = "{%variant%}/reward/metal-coat.png"
   dragon_scale ="{%variant%}/reward/dragon-scale.png"
   up_grade = "{%variant%}/reward/up-grade.png"
   sinnoh_stone = "{%variant%}/reward/sinnoh-stone.png"

   fast_tm = "{%variant%}/reward/fast-tm.png"
   charge_tm = "{%variant%}/reward/charge-tm.png"
   stardust = "{%variant%}/reward/stardust.png"
   rare_candy = "{%variant%}/reward/rare-candy.png"
   encounter = "{%variant%}/reward/encounter.png"

   battle = "{%variant%}/objective/battle.png"
   raid = "{%variant%}/objective/raid.png"
   catch = "{%variant%}/objective/catch.png"
   throwing_skill = "{%variant%}/objective/throwing-skill.png"

   hatch = "{%variant%}/objective/hatch.png"
   buddy = "{%variant%}/objective/buddy.png"
   explore = "{%variant%}/objective/explore.png"

   power_up = "{%variant%}/objective/power-up.png"
   evolve = "{%variant%}/objective/evolve.png"
   trash = "{%variant%}/objective/trash.png"
   item = "{%variant%}/objective/item.png"

   social = "{%variant%}/objective/social.png"

   unknown = "{%variant%}/unknown.png"
   default = "{%variant%}/default.png"

Body for species sets
^^^^^^^^^^^^^^^^^^^^^

The body follows the header, and contains one or more range declarations that
specify the location of icons for all or a subset of Pokémon species. The body
may consist of any number of ``[range|n]`` sections, where :math:`n` is a
counter, and a ``[default]`` fallback section for icons which are not covered by
a range.

``[range|n]``
"""""""""""""

Range blocks define icons for a range of Pokémon species. They have four
settings, three of which are required:

``range_start``
   The pokedex ID of the first Pokémon to be covered by the range.

``range_end``
   The pokedex ID of the last Pokémon to be covered by the range.

``vector`` *(optional)*
   A file path template to refer to vector graphics for icons in this range.
   ``{%n%}`` can be used to extract the pokedex ID, and ``{%variant%}`` to
   extract "light" or "dark" depending on the user theme. If omitted, FreeField
   will fall back to raster graphics for this icon range.

``raster``
   A file path template to refer to raster graphics for icons in this range.
   ``{%n%}`` can be used to extract the pokedex ID, and ``{%variant%}`` to
   extract "light" or "dark" depending on the user theme.

``[default]``
"""""""""""""

The default block is used as a catch-all or fallback for icons which have not
already been matched by a range block. This block has two settings, of which one
is required:

``vector`` *(optional)*
   A file path template to refer to vector graphics for icons in this range.
   ``{%n%}`` can be used to extract the pokedex ID, and ``{%variant%}`` to
   extract "light" or "dark" depending on the user theme. If omitted, FreeField
   will fall back to raster graphics for this icon range.

``raster``
   A file path template to refer to raster graphics for icons in this range.
   ``{%n%}`` can be used to extract the pokedex ID, and ``{%variant%}`` to
   extract "light" or "dark" depending on the user theme.

Example
"""""""

This body declares that icons should be separated into subfolders for each
generation, up to Generation II. All other icons from later generations will
show a fallback "unknown" icon instead.

.. code-block:: ini

   [range|1]

   range_start = 1
   range_end = 151
   vector = "{%variant%}/vector/gen1/{%n%}.svg"
   raster = "{%variant%}/raster/gen1/{%n%}.png"

   [range|2]

   range_start = 152
   range_end = 251
   vector = "{%variant%}/vector/gen2/{%n%}.svg"
   raster = "{%variant%}/raster/gen2/{%n%}.png"

   [default]

   vector = "{%variant%}/vector/unknown.svg"
   raster = "{%variant%}/raster/unknown.png"

List of icon IDs
----------------

Icons in FreeField are pulled from the objectives.yaml and rewards.yaml files
located in the `includes/data directory
<https://github.com/bilde2910/FreeField/tree/master/includes/data>`_. Objectives
and rewards are in turn organized into categories. You can define icons for any
objective, reward or category of these.

The following tree represents a list of all icons currently available in
FreeField. Declaring an icon for a node in this tree will also result in that
icon being applied to all children of that node, unless specifically overwritten
by a child node. The only icon that is required is ``default``, but for the icon
set to actually have some use, some individual icons and/or category icons must
be declared as well.

.. code-block:: text

   default
   ├─ unknown
   ├─ battle
   │  ├─ battle_gym
   │  ├─ win_gym
   │  ├─ raid
   │  │  ├─ battle_raid
   │  │  ├─ win_raid
   │  │  └─ level_raid
   │  └─ se_charge
   ├─ catch
   │  ├─ catch
   │  ├─ catch_weather
   │  ├─ catch_type
   │  ├─ catch_specific
   │  └─ catch_daily
   ├─ item
   │  ├─ use_berry
   │  └─ use_item_encounter
   ├─ buddy
   │  └─ buddy_candy
   ├─ hatch
   │  └─ hatch
   ├─ evolve
   │  ├─ evolve
   │  ├─ evolve_type
   │  ├─ evolve_evolution
   │  ├─ evolve_specific
   │  └─ evolve_item
   ├─ trash
   │  └─ transfer
   │     └─ transfer
   ├─ throwing_skill
   │  ├─ throw_simple_nice
   │  ├─ throw_simple_nice_chain
   │  ├─ throw_simple_great
   │  ├─ throw_simple_great_chain
   │  ├─ throw_simple_excellent
   │  ├─ throw_simple_excellent_chain
   │  ├─ throw_curve
   │  ├─ throw_curve_chain
   │  ├─ throw_curve_nice
   │  ├─ throw_curve_nice_chain
   │  ├─ throw_curve_great
   │  ├─ throw_curve_great_chain
   │  ├─ throw_curve_excellent
   │  └─ throw_curve_excellent_chain
   ├─ explore
   │  ├─ visit_poi
   │  ├─ new_poi
   │  └─ visit_daily
   ├─ social
   │  ├─ send_gift
   │  └─ trade
   ├─ encounter
   │  └─ encounter
   ├─ stardust
   │  └─ stardust
   ├─ candy
   │  └─ rare_candy
   ├─ ball
   │  ├─ poke_ball
   │  ├─ great_ball
   │  └─ ultra_ball
   ├─ berry
   │  ├─ razz_berry
   │  ├─ nanab_berry
   │  ├─ pinap_berry
   │  ├─ golden_razz_berry
   │  └─ silver_pinap_berry
   ├─ potion
   │  ├─ potion
   │  ├─ super_potion
   │  ├─ hyper_potion
   │  └─ max_potion
   ├─ revive
   │  ├─ revive
   │  └─ max_revive
   ├─ tm
   │  ├─ fast_tm
   │  └─ charge_tm
   └─ evolution_item
      ├─ sun_stone
      ├─ kings_rock
      ├─ metal_coat
      ├─ dragon_scale
      ├─ up_grade
      └─ sinnoh_stone

Example
^^^^^^^

In this example, we've declared the following in our pack.ini file:

.. code-block:: ini

   [raster]

   default = "default.png"
   battle = "battle.png"
   raid = "raid.png"
   level_raid = "level_raid.png"
   catch_type = "catch_type.png"

The icons that will be used in this case are:

.. role:: strike
   :class: strike

============== ============================= ================= =================
Icon           Inherits from                 Image path        Usage defined
============== ============================= ================= =================
default                                      default.png       **Explicitly**
unknown        **default**                   default.png       By inheritance
battle         default                       battle.png        **Explicitly**
battle_gym     **battle**, default           battle.png        By inheritance
win_gym        **battle**, default           battle.png        By inheritance
raid           battle, default               raid.png          **Explicitly**
battle_raid    **raid**, battle, default     raid.png          By inheritance
win_raid       **raid**, battle, default     raid.png          By inheritance
level_raid     raid, battle, default         level_raid.png    **Explicitly**
se_charge      **battle**, default           battle.png        By inheritance
catch          **default**                   default.png       By inheritance
catch_weather  :strike:`catch`, **default**  default.png       By inheritance
catch_type     :strike:`catch`, default      catch_type.png    **Explicitly**
catch_specific :strike:`catch`, **default**  default.png       By inheritance
catch_daily    :strike:`catch`, **default**  default.png       By inheritance
============== ============================= ================= =================

The other icons in the tree all fall back to ``default`` and are not listed
above for brevity.
