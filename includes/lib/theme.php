<?php

__require("research");

class Theme {
    public static function listIcons() {
        $icons = array();
        foreach (Research::OBJECTIVES as $objective => $data) {
            $icons[] = $objective;
            foreach ($data["categories"] as $category) {
                $icons[] = $category;
            }
        }
        foreach (Research::REWARDS as $reward => $data) {
            $icons[] = $reward;
            foreach ($data["categories"] as $category) {
                $icons[] = $category;
            }
        }
        return array_unique($icons);
    }

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

    public static function getIconSet($set = null, $variant = null) {
        if ($set === null) {
            __require("config");
            $set = Config::get("themes/icons/default");
        }
        return new IconSet($set, $variant);
    }
}

class IconSet {
    private $set = null;
    private $data = array();
    private $variant = null;

    public function __construct($set, $variant) {
        $this->set = $set;
        $this->variant = $variant;
        $packini = __DIR__."/../../themes/icons/{$set}/pack.ini";
        if (file_exists($packini)) {
            $this->data = parse_ini_file($packini, true);
        }
    }

    public function getVariant() {
        return $this->variant;
    }

    public function setVariant($variant) {
        $this->variant = $variant;
    }

    public function getIconUrl($icon) {
        $icarray = array($icon);
        if (isset(Research::OBJECTIVES[$icon])) {
            $icarray = array_merge($icarray, Research::OBJECTIVES[$icon]["categories"]);
        }
        $icarray[] = "default";
        foreach ($icarray as $entry) {
            $url = self::getExplicitIconUrl($entry);
            if ($url !== null) return $url;
        }
    }

    private function getExplicitIconUrl($icon) {
        if (isset($this->data["vector"][$icon])) {
            return $this->formatUrl($this->data["vector"][$icon]);
        } else {
            return $this->getExplicitRasterUrl($icon);
        }
    }

    public function getRasterUrl($icon) {
        $icarray = array($icon);
        if (isset(Research::OBJECTIVES[$icon])) {
            $icarray = array_merge($icarray, Research::OBJECTIVES[$icon]["categories"]);
        }
        $icarray[] = "default";
        foreach ($icarray as $entry) {
            $url = self::getExplicitRasterUrl($entry);
            if ($url !== null) return $url;
        }
    }

    private function getExplicitRasterUrl($icon) {
        if (isset($this->data["raster"][$icon])) {
            return $this->formatUrl($this->data["raster"][$icon]);
        } else {
            return null;
        }
    }

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
