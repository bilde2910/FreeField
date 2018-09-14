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
        $themepath = __DIR__."/../../themes/icons";
        $themedirs = array_diff(scandir($themepath), array('..', '.'));
        $themelist = array();
        foreach ($themedirs as $theme) {
            if (!file_exists("{$themepath}/{$theme}/pack.ini")) continue;
            $themelist[] = $theme;
        }
        return $themelist;
    }

    /*
        Returns an `IconSet` instance representing the given icon set and
        variant (dark or light). If no icon set is specified, use the default
        icon pack from the configuration. If no variant is specified, return a
        variant-neutral `IconSet` with "{%variant%}" path placeholders intact.
    */
    public static function getIconSet($set = null, $variant = null) {
        if ($set === null) {
            __require("config");
            $set = Config::get("themes/icons/default")->value();
        }
        return new IconSet($set, $variant);
    }
}

/*
    This class contains functions to get URLs from a particular icon set. It is
    constructed from and returned by `Theme::getIconSet()`.
*/
class IconSet {
    private $set = null;
    private $data = array();
    private $variant = null;

    public function __construct($set, $variant) {
        /*
            Store the name of the icon set and the chosen variant ("dark",
            "light" or null), and find and read the contents of the icon set
            metadata file pack.ini.
        */
        $this->set = $set;
        $this->variant = $variant;
        $packini = __DIR__."/../../themes/icons/{$set}/pack.ini";
        if (file_exists($packini)) {
            $this->data = parse_ini_file($packini, true);
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
        if (isset($this->data["vector"][$icon])) {
            return $this->formatUrl($this->data["vector"][$icon]);
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
        if (isset($this->data["raster"][$icon])) {
            return $this->formatUrl($this->data["raster"][$icon]);
        } else {
            return null;
        }
    }

    /*
        Returns the full URL for the given icon resource path. If a variant is
        not set either via the constructor or via `setVariant()`, replacement
        tokens "{%variant%}" will not be replaced with the variant name ("light"
        or "dark") and replacement may have to be done elsewhere (e.g. client-
        side) for the URL to be valid.
    */
    private function formatUrl($url) {
        __require("config");
        $pack = urlencode($this->set);
        if ($this->variant !== null) {
            $url = str_replace("{%variant%}", $this->variant, $url);
        }
        return Config::getEndpointUri("/themes/icons/{$pack}/{$url}");
    }
}

?>
