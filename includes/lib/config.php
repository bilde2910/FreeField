<?php

class Config {
    private const CONFIG_LOCATION = __DIR__."/../config.json";

    private static $config = false;
    private static $flattree = null;
    private static $configtree = null;

    public static function loadTree() {
        require_once(__DIR__."/../config/tree.php");
        self::$configtree = ConfigTree::loadTree();
    }

    public static function get($path) {
        if (self::$config === false) self::loadConfig();

        $conf = self::$config;

        $segments = explode("/", $path);
        foreach ($segments as $segment) {
            if (!isset($conf[$segment])) return self::getDefault($path);
            $conf = $conf[$segment];
        }
        return $conf;
    }

    public static function getHTML($path) {
        return htmlspecialchars(strval(self::get($path)), ENT_QUOTES);
    }

    public static function getJS($path) {
        return json_encode(self::get($path));
    }

    public static function set($options, $validatePermissions = false) {
        if (self::$config === false) self::loadConfig();
        $flat = self::getFlatTree();

        if ($validatePermissions) {
            __require("auth");
            $permissionsAssoc = array();
            foreach (self::$configtree as $domain => $sdata) {
                foreach ($sdata as $section => $perms) {
                    foreach ($perms as $perm => $data) {
                        $permissionsAssoc[$perm] = $domain;
                    }
                }
            }
        }

        $optDeny = array();
        foreach ($options as $option => $value_raw) {
            if ($validatePermissions) {
                /*
                        _
                       / \
                      / | \
                     /  |  \
                    /___o___\

                    !!! WARNING !!!

                    If `$value_raw` is an array of settings, permissions will
                    not be validated! If you're passing an array to this
                    function, you are responsible for manually performing
                    permissions validation!

                    AN EXCEPTION WILL BE THROWN IF THIS WARNING IS NOT HEEDED
                */
                $perm = "admin/".$permissionsAssoc[$option]."/general";
                if (!Auth::getCurrentUser()->hasPermission($perm)) {
                    $optDeny[] = $option;
                }
                if (!isset($flat[$option])) {
                    throw new Exception("Cannot verify permissions when setting an array as value for a configuration path!");
                    exit;
                }
                $values = $flat[$option];
                if (get_class($values["option"]) == "PermissionOption") {
                    $old = self::get($option);
                    $new = intval($value_raw);
                    $max = max($old, $new);
                    if (!Auth::getCurrentUser()->canChangeAtPermission($max)) {
                        $optDeny[] = $option;
                    }
                }
            }
        }

        foreach ($options as $option => $value_raw) {
            if (in_array($option, $optDeny)) continue;

            if (!isset($flat[$option]) && is_array($value_raw)) {
                $value = $value_raw;
            } else {
                if (!isset($flat[$option])) continue;
                $values = $flat[$option];
                $opt = $values["option"];

                if (is_string($value_raw)) {
                    $value = $opt->parseValue($value_raw);
                } else {
                    $value = $value_raw;
                }
                if (!$opt->isValid($value)) continue;
            }

            $s = explode("/", $option);
            switch (count($s)) {
                case 1:
                    self::$config[$s[0]] = $value;
                    break;
                case 2:
                    self::$config[$s[0]][$s[1]] = $value;
                    break;
                case 3:
                    self::$config[$s[0]][$s[1]][$s[2]] = $value;
                    break;
                case 4:
                    self::$config[$s[0]][$s[1]][$s[2]][$s[3]] = $value;
                    break;
                case 5:
                    self::$config[$s[0]][$s[1]][$s[2]][$s[3]][$s[4]] = $value;
                    break;
                case 6:
                    self::$config[$s[0]][$s[1]][$s[2]][$s[3]][$s[4]][$s[5]] = $value;
                    break;
                case 7:
                    self::$config[$s[0]][$s[1]][$s[2]][$s[3]][$s[4]][$s[5]][$s[6]] = $value;
                    break;
                case 8:
                    self::$config[$s[0]][$s[1]][$s[2]][$s[3]][$s[4]][$s[5]][$s[6]][$s[7]] = $value;
                    break;
                case 9:
                    self::$config[$s[0]][$s[1]][$s[2]][$s[3]][$s[4]][$s[5]][$s[6]][$s[7]][$s[8]] = $value;
                    break;
                case 10:
                    self::$config[$s[0]][$s[1]][$s[2]][$s[3]][$s[4]][$s[5]][$s[6]][$s[7]][$s[8]][$s[9]] = $value;
                    break;
            }
        }
        self::saveConfig();
    }

    public static function ifAny($paths, $value) {
        foreach ($paths as $path) {
            if (self::get($path) === $value) return true;
        }
        return false;
    }

    private static function getFlatTree() {
        if (self::$flattree !== null) return self::$flattree;
        self::$flattree = array();

        foreach (self::$configtree as $domain => $sections) {
            foreach ($sections as $section => $paths) {
                foreach ($paths as $path => $values) {
                    if (substr($path, 0, 2) !== "__") self::$flattree[$path] = $values;
                }
            }
        }

        return self::$flattree;
    }

    public static function getTreeDomain($domain) {
        return self::$configtree[$domain];
    }

    public static function getDefault($item) {
        $conf = self::getFlatTree();
        foreach ($conf as $path => $values) {
            if ($path == $item) return $values["default"];
        }
        return null;
    }

    public static function getSettingI18N($path) {
        return new ConfigSettingI18N($path, self::getFlatTree()[$path]);
    }

    public static function getSectionI18N($domain, $section) {
        return new ConfigSectionI18N($domain, $section);
    }

    public static function getDomainI18N($domain) {
        return new ConfigDomainI18N($domain);
    }

    public static function translatePathI18N($path) {
        return str_replace("/", ".", str_replace("-", "_", $path));
    }

    public static function getEndpointUri($endpoint) {
        $basepath = self::get("site/uri");
        return (substr($basepath, -1) == "/" ? substr($basepath, 0, -1) : $basepath).$endpoint;
    }

    private static function loadConfig() {
        if (!file_exists(self::CONFIG_LOCATION)) {
            self::$config = array();
        } else {
            self::$config = json_decode(file_get_contents(self::CONFIG_LOCATION), true);
        }
    }

    private static function saveConfig() {
        file_put_contents(self::CONFIG_LOCATION, json_encode(self::$config, JSON_PRETTY_PRINT));
    }
}

Config::loadTree();

class ConfigSettingI18N {
    private $path = null;
    private $setting = null;

    function __construct($path, $setting) {
        $this->path = $path;
        $this->setting = $setting;
    }

    public function getName() {
        return "setting.".Config::translatePathI18N($this->path).".name";
    }

    public function getDescription() {
        return "setting.".Config::translatePathI18N($this->path).".desc";
    }

    public function getOption($option) {
        return "setting.".Config::translatePathI18N($this->path).".option.".Config::translatePathI18N($option);
    }

    public function getLabel() {
        return "setting.".Config::translatePathI18N($this->path).".label";
    }
}

class ConfigSectionI18N {
    private $domain = null;
    private $section = null;

    function __construct($domain, $section) {
        $this->domain = $domain;
        $this->section = $section;
    }

    public function getName() {
        return "admin.section.".Config::translatePathI18N($this->domain).".".Config::translatePathI18N($this->section).".name";
    }

    public function getDescription() {
        return "admin.section.".Config::translatePathI18N($this->domain).".".Config::translatePathI18N($this->section).".desc";
    }
}

class ConfigDomainI18N {
    private $domain = null;

    function __construct($domain) {
        $this->domain = $domain;
    }

    public function getName() {
        return "admin.domain.".Config::translatePathI18N($this->domain).".name";
    }

    public function getDescription() {
        return "admin.domain.".Config::translatePathI18N($this->domain).".desc";
    }

    public function getSection($section) {
        return new ConfigSectionI18N($this->domain, $section);
    }
}

?>
