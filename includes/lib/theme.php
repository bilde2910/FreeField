<?php
/*
    This library file contains functions related to icon sets.
*/

__require("research");

class Theme {
    /*
        Lists all possible icon names. This works by looping over the objectives
        and rewards arrays from /includes/data/objectives.yaml and
        /includes/data/rewards.yaml, including the IDs of each objective and
        reward as well as a complete list of categories and sub-categories for
        each objective/reawrd.

        If an icon is not found for a specific objective or reward, it will back
        back through its categories and eventually "default" if no icons are
        found for any categories. This is detailed in
        /includes/lib/research.php.
    */
    public static function listIcons() {
        $icons = array();
        foreach (Research::listObjectives() as $objective => $data) {
            // Add the objective itself to the icon array
            $icons[] = $objective;

            // Loop over the categories and add each category as well
            foreach ($data["categories"] as $category) {
                $icons[] = $category;
            }
        }
        foreach (Research::listRewards() as $reward => $data) {
            // Add the reward itself to the icon array
            $icons[] = $reward;

            // Loop over the categories and add each category as well
            foreach ($data["categories"] as $category) {
                $icons[] = $category;
            }
        }

        /*
            Add the "default" icon to the end of the icons list. This icon is
            displayed if no more specific icon is found for any particular
            research objective or reward.
        */
        $icons[] = "default";

        /*
            The array may contain duplicates. Remove duplicates and reset the
            keys of the array (which would be all out of place otherwise).
        */
        return array_values(array_unique($icons));
    }

    /*
        Returns an array of all icon sets present in the FreeField installation.
        This function scans the icon set directory, checks if each subdirectory
        has the pack.ini file present (which contains icon set metadata), and if
        so, adds it to the list of installed icon sets.
    */
    public static function listIconSets() {
        $themelist = array();
        $themepaths = self::getPathsForType("icons");
        foreach ($themepaths as $themepath => $fetchpath) {
            $themedirs = array_diff(scandir($themepath), array('..', '.'));
            foreach ($themedirs as $theme) {
                if (!file_exists("{$themepath}/{$theme}/pack.ini")) continue;
                $themelist[] = $theme;
            }
        }
        return $themelist;
    }

    /*
        Returns an array of all species icon sets present in the FreeField
        installation. This function scans the species set directory, checks if
        each subdirectory has the pack.ini file present (which contains set
        metadata), and if so, adds it to the list of installed icon sets.
    */
    public static function listSpeciesSets() {
        $setlist = array();
        $setpaths = self::getPathsForType("species");
        foreach ($setpaths as $setpath => $fetchpath) {
            $setdirs = array_diff(scandir($setpath), array('..', '.'));
            foreach ($setdirs as $set) {
                if (!file_exists("{$setpath}/{$set}/pack.ini")) continue;
                $setlist[] = $set;
            }
        }
        return $setlist;
    }

    /*
        Returns an `IconSet` instance representing the given icon set and
        variant (dark or light). If no icon set is specified, use the default
        icon set from the configuration. If no variant is specified, return a
        variant-neutral `IconSet` with "{%variant%}" path placeholders intact.
    */
    public static function getIconSet($set = null, $variant = null) {
        if ($set === null) {
            __require("config");
            $set = Config::get("themes/icons/default")->value();
        }
        return new IconSet($set, $variant);
    }

    /*
        Returns a `SpeciesSet` instance representing the given species icon set
        and variant (dark or light). If no icon set is specified, use the
        default set from the configuration. If no variant is specified, return a
        variant-neutral `SpeciesSet` with "{%variant%}" path placeholders
        intact.
    */
    public static function getSpeciesSet($set = null, $variant = null) {
        if ($set === null) {
            __require("config");
            $set = Config::get("themes/species/default")->value();
        }
        return new SpeciesSet($set, $variant);
    }

    /*
        Returns a list of paths and corresponding client-side request URL base
        paths that should be searched for icon sets.
    */
    public static function getPathsForType($type) {
        $paths = array(
            __DIR__."/../../themes/{$type}"
                => "themes/{$type}/",
            __DIR__."/../userdata/themes/{$type}"
                => "themes/fetch-custom.php?type={$type}&path="
        );
        /*
            Check that each path exists, and remove those that do not from the
            path list.
        */
        foreach ($paths as $path => $fetch) {
            if (!file_exists($path) || !is_dir($path)) unset($paths[$path]);
        }
        return $paths;
    }
}

abstract class BaseIconSet {
    private $set = null;
    private $data = array();
    private $variant = null;
    private $type = null;
    private $fetchpath = null;

    protected function __construct($set, $variant, $type) {
        /*
            Store the name of the icon set and the chosen variant ("dark",
            "light" or null), and find and read the contents of the icon set
            metadata file pack.ini.
        */
        $this->set = $set;
        $this->variant = $variant;
        $this->type = $type;

        /*
            Identify which file system path this icon set is stored within, and
            update the object instance with the correct client-side request path
            to use when requesting assets from the icon set.
        */
        $basepaths = Theme::getPathsForType($type);
        foreach ($basepaths as $basepath => $fetchpath) {
            $packini = "{$basepath}/{$set}/pack.ini";
            if (file_exists($packini)) {
                $this->data = parse_ini_file($packini, true);
                $this->fetchpath = $fetchpath;
                break;
            }
        }
    }

    /*
        Returns the icon set variant ("dark", "light" or null) used for this
        `IconSet` instance.
    */
    public function getVariant() {
        return $this->variant;
    }

    /*
        Sets the icon set variant ("dark", "light" or null) used for this
        `IconSet` instance.
    */
    public function setVariant($variant) {
        $this->variant = $variant;
    }

    /*
        Returns the metadata array (from pack.ini) for this icon set.
    */
    protected function getData() {
        return $this->data;
    }

    /*
        Returns the full URL for the given icon resource path. If a variant is
        not set either via the constructor or via `setVariant()`, replacement
        tokens "{%variant%}" will not be replaced with the variant name ("light"
        or "dark") and replacement may have to be done elsewhere (e.g. client-
        side) for the URL to be valid.
    */
    protected function formatUrl($url) {
        __require("config");
        $pack = urlencode($this->set);
        if ($this->variant !== null) {
            $url = str_replace("{%variant%}", $this->variant, $url);
        }
        return Config::getEndpointUri("/{$this->fetchpath}{$pack}/{$url}");
    }
}

/*
    This class contains functions to get URLs from a particular icon set. It is
    constructed from and returned by `Theme::getIconSet()`.
*/
class IconSet extends BaseIconSet {
    /*
        Construct the icon set from set metadata.
    */
    public function __construct($set, $variant) {
        parent::__construct($set, $variant, "icons");
    }

    /*
        Gets the URL representing a particular icon for a research objective,
        reward, or a category of either. Returns a vector URL if possible, and
        falls back to a raster URL is none is found.
    */
    public function getIconUrl($icon) {
        /*
            Create an array of the icon and its fallback categories, if any.
        */
        $icarray = array($icon);
        $objdef = Research::getObjective($icon);
        if ($objdef !== null) {
            $icarray = array_merge($icarray, $objdef["categories"]);
        }
        /*
            Add the default icon as fallback if no icon resource is found for
            the specified icon.
        */
        $icarray[] = "default";
        /*
            Loop over the icons array and return the vector URL of the first
            icon found in the icon set.
        */
        foreach ($icarray as $entry) {
            $url = self::getExplicitIconUrl($entry);
            if ($url !== null) return $url;
        }

        /*
            If no URLs were found at all, return null.
        */
        return null;
    }

    /*
        Gets the URL representing a particular icon for a research objective or
        reward, with no fallbacks. Returns a vector URL if possible, and falls
        back to a raster URL if none is found. If the specified icon is not
        found as either vector or raster, `null` is returned.
    */
    private function getExplicitIconUrl($icon) {
        if (isset(parent::getData()["vector"][$icon])) {
            return parent::formatUrl(parent::getData()["vector"][$icon]);
        } else {
            return $this->getExplicitRasterUrl($icon);
        }
    }

    /*
        Gets the URL representing a particular icon for a research objective,
        reward, or a category of either. Returns a raster URL.
    */
    public function getRasterUrl($icon) {
        /*
            Create an array of the icon and its fallback categories, if any.
        */
        $icarray = array($icon);
        $objdef = Research::getObjective($icon);
        if ($objdef !== null) {
            $icarray = array_merge($icarray, $objdef["categories"]);
        }
        /*
            Add the default icon as fallback if no icon resource is found for
            the specified icon.
        */
        $icarray[] = "default";
        /*
            Loop over the icons array and return the vector URL of the first
            icon found in the icon set.
        */
        foreach ($icarray as $entry) {
            $url = self::getExplicitRasterUrl($entry);
            if ($url !== null) return $url;
        }

        /*
            If no URLs were found at all, return null.
        */
        return null;
    }

    /*
        Gets the URL representing a particular icon for a research objective or
        reward, with no fallbacks. Returns a raster URL, or `null` if the
        specified icon is not found.
    */
    private function getExplicitRasterUrl($icon) {
        if (isset(parent::getData()["raster"][$icon])) {
            return parent::formatUrl(parent::getData()["raster"][$icon]);
        } else {
            return null;
        }
    }
}

/*
    This class contains functions to get URLs from a particular icon set. It is
    constructed from and returned by `Theme::getIconSet()`.
*/
class SpeciesSet extends BaseIconSet {
    /*
        Construct the icon set from set metadata.
    */
    public function __construct($set, $variant) {
        parent::__construct($set, $variant, "species");
    }

    /*
        Find the icon range that holds the requested icon. Returns `null` if a
        suitable range is not found.
    */
    private function getRange($icon) {
        /*
            First, check for sections starting with the keyword "range." These
            hold block definitions for batches of icons.
        */
        foreach (parent::getData() as $key => $value) {
            if ($key == "range" || substr($key, 0, 6) == "range|") {
                if (
                    $value["range_start"] <= $icon &&
                    $value["range_end"] >= $icon
                ) {
                    return $value;
                }
            }
        }

        /*
            If no "range" block is found, use the fallback "default" block.
        */
        foreach (parent::getData() as $key => $value) {
            if ($key == "default") {
                return $value;
            }
        }

        /*
            If there is still no valid block containing the requested icon,
            return `null`.
        */
        return null;
    }

    /*
        Gets the URL representing a particular species' icon. Returns a vector
        URL if possible, and falls back to a raster URL or `null` if none is
        found.
    */
    public function getIconUrl($icon) {
        /*
            Get a range block that contains icon path definitions for this icon.
        */
        $range = self::getRange($icon);
        if ($range === null) return null;

        /*
            Return the vector URL if possible, falling back on the raster URL
            if not present.
        */
        if (isset($range["vector"])) {
            return parent::formatUrl(str_replace("{%n%}", $icon, $range["vector"]));
        } else {
            return self::getRasterUrl($icon);
        }
    }

    /*
        Gets the URL representing a particular species' icon. Returns a raster
        URL if possible, or `null` if no suitable icon is found.
    */
    public function getRasterUrl($icon) {
        /*
            Get a range block that contains icon path definitions for this icon.
        */
        $range = self::getRange($icon);
        if ($range === null) return null;

        /*
            Return the vector URL if possible, falling back on the raster URL
            if not present.
        */
        if (isset($range["raster"])) {
            return parent::formatUrl(str_replace("{%n%}", $icon, $range["raster"]));
        } else {
            return null;
        }
    }
}

?>
