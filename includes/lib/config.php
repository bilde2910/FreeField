<?php

class Config {
    private const CONFIG_LOCATION = __DIR__."/../config.json";

    private static $config = false;
    private static $flattree = null;

    /*
        Configtree is a tree consisting of all config options and their possible values.
        The setup of the tree is of the form DOMAIN -> SECTION -> SETTING. Domain is the
        page on the administration interface the setting should show up on. Section is
        the section on that page where the settings would appear. Each setting is then
        listed in order of appearance. Each setting key must be globally unique.

        E.g. "setup/uri" is listed under the Access section on the main settings page.

        Each setting has two options:
        - default is the default value of the object
        - options specifies the type of data to store.

        Valid options:
        - "string" for a string
        - "password" for a password-type string
        - "int" for an integer
        - "int,x,y" for an integer between values x and y
        - "float" for a floating-point value
        - "float,x,y" for a floating-point value between values x and y
        - "permission" for a permission tier
        - "bool" for a boolean
        - array() for a selection box with the array contents as options

        I18N is handled with setting.<setting>.name and setting.<setting>.desc.
        For domains and sections, it's handled with admin.domain.<domain>.name and
        admin.domain.<domain>.desc, and admin.section.<domain>.<section>.name and
        admin.section.<domain>.<section>.desc respectively. Sections only have a
        description if __hasdesc is set. If __hasdesc is not true,
        admin.section.<domain>.<section>.desc is ignored. Section descriptions can
        be formatted similar to sprintf() with __descsprintf (optional). Example:

        Example I18N'd string: "Please set up {%1} authentication first!"
        If __descsprintf = array("Discord"); the string would be:
        "Please set up Discord authentication first!"

        Selection box contents are I18N'd with setting.<setting>.option.<option>.
        Dashes in all parts of the I18N string are replaced with underscores and
        slashes replaced with dots.

        E.g. for setting "setup/uri":
        - setting.setup.uri.name
        - setting.setup.uri.desc

        E.g. for setting "security/validate-ua":
        - setting.security.validate_ua.name
        - setting.security.validate_ua.desc
        - setting.security.validate_ua.option.no
        - setting.security.validate_ua.option.lenient
        - setting.security.validate_ua.option.strict

        E.g. for domain "main":
        - admin.domain.main.name
        - admin.domain.main.desc

        E.g. for section "database" in domain "main":
        - admin.section.main.database.name
        - admin.section.main.database.desc
    */

    private static $configtree = null;

    public static function loadTree() {
        self::$configtree = array(
            "main" => array(
                "access" => array(
                    "site/uri" => array(
                        "default" => "",
                        "option" => new StringOption()
                    ),
                    "site/name" => array(
                        "default" => "FreeField",
                        "option" => new StringOption()
                    )
                ),
                "database" => array(
                    "database/type" => array(
                        "default" => "mysqli",
                        "option" => new SelectOption(array("mysql", "mysqli", "pgsql", "sqlite", "sqlite3"))
                    ),
                    "database/host" => array(
                        "default" => "localhost",
                        "option" => new StringOption()
                    ),
                    "database/port" => array(
                        "default" => -1,
                        "option" => new IntegerOption(-1, 65535)
                    ),
                    "database/username" => array(
                        "default" => "fieldfree",
                        "option" => new StringOption()
                    ),
                    "database/password" => array(
                        "default" => "fieldfree",
                        "option" => new PasswordOption()
                    ),
                    "database/database" => array(
                        "default" => "fieldfree",
                        "option" => new StringOption()
                    ),
                    "database/table-prefix" => array(
                        "default" => "ffield_",
                        "option" => new StringOption()
                    )
                )
            ),
            "perms" => array(
                "default" => array(
                    "permissions/default-level" => array(
                        "default" => 80,
                        "option" => new PermissionOption()
                    )
                ),
                "map-access" => array(
                    // TODO:
                    "permissions/level/access" => array(
                        "default" => 0,
                        "option" => new PermissionOption()
                    ),
                    "permissions/level/report-research" => array(
                        "default" => 80,
                        "option" => new PermissionOption()
                    ),
                    "permissions/level/overwrite-research" => array(
                        "default" => 80,
                        "option" => new PermissionOption()
                    ),
                    "permissions/level/submit-poi" => array(
                        "default" => 120,
                        "option" => new PermissionOption()
                    )
                ),
                "admin" => array(
                    "permissions/level/admin/main/general" => array(
                        "default" => 250,
                        "option" => new PermissionOption()
                    ),
                    "permissions/level/admin/users/general" => array(
                        "default" => 160,
                        "option" => new PermissionOption()
                    ),
                    "permissions/level/admin/groups/general" => array(
                        "default" => 200,
                        "option" => new PermissionOption()
                    ),
                    "permissions/level/admin/users/groups" => array(
                        "default" => 160,
                        "option" => new PermissionOption()
                    ),
                    "permissions/level/admin/groups/self-manage" => array(
                        "default" => 250,
                        "option" => new PermissionOption()
                    ),
                    "permissions/level/admin/pois/general" => array(
                        "default" => 160,
                        "option" => new PermissionOption()
                    ),
                    "permissions/level/admin/perms/general" => array(
                        "default" => 200,
                        "option" => new PermissionOption()
                    ),
                    "permissions/level/admin/security/general" => array(
                        "default" => 200,
                        "option" => new PermissionOption()
                    ),
                    "permissions/level/admin/auth/general" => array(
                        "default" => 250,
                        "option" => new PermissionOption()
                    ),
                    "permissions/level/admin/themes/general" => array(
                        "default" => 200,
                        "option" => new PermissionOption()
                    ),
                    "permissions/level/admin/map/general" => array(
                        "default" => 250,
                        "option" => new PermissionOption()
                    ),
                    "permissions/level/admin/hooks/general" => array(
                        "default" => 200,
                        "option" => new PermissionOption()
                    )
                )
            ),
            "security" => array(
                "user-creation" => array(
                    "security/require-validation" => array(
                        "default" => false,
                        "option" => new BooleanOption()
                    )
                ),
                "sessions" => array(
                    "auth/session-length" => array(
                        "default" => 315619200, // 10 years
                        "option" => new SelectOption(array(86400, 604800, 2592000, 7776000, 15811200, 31536000, 63072000, 157766400, 315619200), "int")
                    ),
                    "security/validate-ua" => array(
                        "default" => "lenient",
                        "option" => new SelectOption(array("no", "lenient", "strict"))
                    ),
                    "security/validate-lang" => array(
                        "default" => true,
                        "option" => new BooleanOption()
                    )
                )
            ),
            "auth" => array(
                "discord" => array(
                    "__hasdesc" => true,
                    "__descsprintf" => array(
                        '<a target="_blank" href="https://github.com/bilde2910/FreeField/wiki/Authentication-providers/Discord">',
                        '</a>'
                    ),
                    "auth/provider/discord/enabled" => array(
                        "default" => false,
                        "option" => new BooleanOption()
                    ),
                    "auth/provider/discord/client-id" => array(
                        "default" => "",
                        "option" => new StringOption()
                    ),
                    "auth/provider/discord/client-secret" => array(
                        "default" => "",
                        "option" => new StringOption()
                    )
                ),
                "telegram" => array(
                    "__hasdesc" => true,
                    "__descsprintf" => array(
                        '<a target="_blank" href="https://github.com/bilde2910/FreeField/wiki/Authentication-providers/Telegram">',
                        '</a>'
                    ),
                    "auth/provider/telegram/enabled" => array(
                        "default" => false,
                        "option" => new BooleanOption()
                    ),
                    "auth/provider/telegram/bot-username" => array(
                        "default" => "",
                        "option" => new StringOption()
                    ),
                    "auth/provider/telegram/bot-token" => array(
                        "default" => "",
                        "option" => new StringOption()
                    )
                )
            ),
            "themes" => array(
                "color" => array(
                    "themes/color/admin" => array(
                        "default" => "dark",
                        "option" => new SelectOption(array("light", "dark"))
                    ),
                    "themes/color/user-settings/theme" => array(
                        "default" => "dark",
                        "option" => new SelectOption(array("light", "dark"))
                    ),
                    "themes/color/user-settings/allow-personalization" => array(
                        "default" => true,
                        "option" => new BooleanOption()
                    ),
                    "themes/color/map/theme/mapbox" => array(
                        "default" => "basic",
                        "option" => new SelectOption(array("basic", "streets", "bright", "light", "dark", "satellite"))
                    ),
                    "themes/color/map/allow-personalization" => array(
                        "default" => true,
                        "option" => new BooleanOption()
                    )
                ),
                "icons" => array(
                    "themes/icons/default" => array(
                        "default" => "freefield-3d-compass",
                        "option" => new IconPackOption()
                    ),
                    "themes/icons/allow-personalization" => array(
                        "default" => true,
                        "option" => new BooleanOption()
                    )
                )
            ),
            "map" => array(
                "provider" => array(
                    "map/provider/source" => array(
                        "default" => "mapbox",
                        "option" => new SelectOption(array("mapbox"))
                    ),
                    "map/provider/mapbox/access-token" => array(
                        "default" => "",
                        "option" => new StringOption()
                    ),
                    "map/provider/directions" => array(
                        "default" => "google",
                        "option" => new SelectOption(array("bing", "google", "here", "mapquest", "waze", "yandex"))
                    )
                ),
                "default" => array(
                    "map/default/center/latitude" => array(
                        "default" => 0.0,
                        "option" => new FloatOption(-90.0, 90.0)
                    ),
                    "map/default/center/longitude" => array(
                        "default" => 0.0,
                        "option" => new FloatOption(-180.0, 180.0)
                    ),
                    "map/default/zoom" => array(
                        "default" => 14.0,
                        "option" => new FloatOption(0.0, 20.0)
                    )
                )
            )
        );
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
                if (is_array($option)) {
                    throw new Exception("Cannot verify permissions when setting an array as value for a configuration path!");
                    exit;
                }
                $perm = "admin/".$permissionsAssoc[$option]."/general";
                if (!Auth::getCurrentUser()->hasPermission($perm)) {
                    $optDeny[] = $option;
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

            if (is_array($value_raw)) {
                $value = $value_raw;
            } else {
                if (!isset($flat[$option])) continue;
                $values = $flat[$option];
                $opt = $values["option"];

                $value = $opt->parseValue($value_raw);
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

class DefaultOption {
    public function parseValue($data) {
        return $data;
    }

    public function isValid($data) {
        return true;
    }

    public function getFollowingBlock() {
        return "";
    }
}

class StringOption extends DefaultOption {
    public function getControl($current = null, $name = null, $id = null) {
        $attrs = "";
        if ($name !== null) $attrs .= ' name="'.$name.'"';
        if ($id !== null) $attrs .= ' id="'.$id.'"';
        if ($current !== null) $attrs .= ' value="'.$current.'"';
        return '<input type="text"'.$attrs.'>';
    }

    public function parseValue($data) {
        return strval($data);
    }

    public function isValid($data) {
        if (is_array($data)) return false;
        return true;
    }
}

class PasswordOption extends DefaultOption {
    public function getControl($current = null, $name = null, $id = null) {
        $attrs = "";
        if ($name !== null) $attrs .= ' name="'.$name.'"';
        if ($id !== null) $attrs .= ' id="'.$id.'"';
        if ($current !== null) $attrs .= ' value="'.$current.'"';
        return '<input type="password"'.$attrs.'>';
    }

    public function parseValue($data) {
        return strval($data);
    }

    public function isValid($data) {
        if (is_array($data)) return false;
        return true;
    }
}

class BooleanOption extends DefaultOption {
    public function getControl($current = null, $name = null, $id = null, $i18ntoken = null) {
        __require("i18n");

        $attrs = "";
        if ($name !== null) $attrs .= ' name="'.$name.'"';
        if ($id !== null) $attrs .= ' id="'.$id.'"';
        if ($current === true) $attrs .= ' checked';

        $labelAttrs = "";
        if ($id !== null) $labelAttrs .= ' for="'.$id.'"';

        $fallbackAttrs = "";
        if ($name !== null) $fallbackAttrs .= ' name="'.$name.'"';

        if ($i18ntoken !== null) {
            $label = I18N::resolve($i18ntoken);
        } elseif ($name !== null) {
            $label = I18N::resolve("setting.".str_replace("-", "_", str_replace("/", ".", $name)).".label");
        } elseif ($id !== null) {
            $label = I18N::resolve("setting.".str_replace("-", "_", $id).".label");
        } else {
            $label = $item;
        }

        $html = '<input type="hidden" value="off"'.$fallbackAttrs.'>'; // Detect unchecked checkbox - unchecked checkboxes aren't POSTed!
        $html .= '<label'.$labelAttrs.'><input type="checkbox"'.$attrs.'> '.$label.'</label>';
        return $html;
    }

    public function parseValue($data) {
        if ($data == "on") return true;
        if ($data == "off") return false;
        return boolval($data);
    }

    public function isValid($data) {
        if (is_bool($data)) return true;
        return false;
    }
}

class IntegerOption extends DefaultOption {
    private $min;
    private $max;

    public function __construct($min = null, $max = null) {
        $this->min = $min;
        $this->max = $max;
    }

    public function getControl($current = null, $name = null, $id = null) {
        $attrs = "";
        if ($name !== null) $attrs .= ' name="'.$name.'"';
        if ($id !== null) $attrs .= ' id="'.$id.'"';
        if ($current !== null) $attrs .= ' value="'.$current.'"';
        return '<input type="number"'.$attrs.'>';
    }

    public function parseValue($data) {
        return intval($data);
    }

    public function isValid($data) {
        if (!is_int($data)) return false;
        if ($this->min !== null && $data < $this->min) return false;
        if ($this->max !== null && $data > $this->max) return false;
        return true;
    }
}

class FloatOption extends DefaultOption {
    private $min;
    private $max;

    public function __construct($min = null, $max = null) {
        $this->min = $min;
        $this->max = $max;
    }

    public function getControl($current = null, $name = null, $id = null, $decimals = 5) {
        $attrs = "";
        if ($name !== null) $attrs .= ' name="'.$name.'"';
        if ($id !== null) $attrs .= ' id="'.$id.'"';
        if ($current !== null) $attrs .= ' value="'.$current.'"';
        if ($decimals >= 1) {
            $attrs .= ' step="0.'.str_repeat("0", $decimals - 1).'"';
        }
        return '<input type="number"'.$attrs.'>';
    }

    public function parseValue($data) {
        return floatval($data);
    }

    public function isValid($data) {
        if (!is_float($data)) return false;
        if ($this->min !== null && $data < $this->min) return false;
        if ($this->max !== null && $data > $this->max) return false;
        return true;
    }
}

class SelectOption extends DefaultOption {
    private $items;
    private $type;

    public function __construct($items, $type = "string") {
        $this->items = $items;
        $this->type = $type;
    }

    public function getControl($current = null, $name = null, $id = null, $i18ndomain = null) {
        __require("i18n");

        $attrs = "";
        if ($name !== null) $attrs .= ' name="'.$name.'"';
        if ($id !== null) $attrs .= ' id="'.$id.'"';

        $html = '<select'.$attrs.'>';
        $selected = false;
        foreach ($this->items as $item) {
            $html .= '<option value="'.$item.'"';
            if ($item == $current) {
                $selected = true;
                $html .= ' selected';
            }
            if ($i18ndomain !== null) {
                $label = I18N::resolve("{$i18ndomain}.{$item}");
            } elseif ($name !== null) {
                $label = I18N::resolve("setting.".str_replace("-", "_", str_replace("/", ".", $name)).".option.{$item}");
            } elseif ($id !== null) {
                $label = I18N::resolve("setting.".str_replace("-", "_", $id).".option.{$item}");
            } else {
                $label = $item;
            }
            $html .= '>'.$label.'</option>';
        }
        $html .= '</select>';

        return $html;
    }

    public function parseValue($data) {
        switch ($this->type) {
            case "string":
                return strval($data);
            case "int":
                return intval($data);
        }
    }

    public function isValid($data) {
        return in_array($data, $this->items);
    }
}

class PermissionOption extends DefaultOption {
    public function getControl($current = 0, $name = null, $id = null) {
        __require("auth");

        return Auth::getPermissionSelector($name, $id, $current);
    }

    public function parseValue($data) {
        return intval($data);
    }

    public function isValid($data) {
        if (!is_int($data)) return false;
        if ($data > 250 || $data < 0) return false;

        __require("auth");
        if (!Auth::getCurrentUser()->canChangeAtPermission($data)) return false;

        return true;
    }
}

class IconPackOption extends DefaultOption {
    private static $packs = null;
    private static $firstOnPage = true;

    private $includeDefault;

    private $id;

    public function __construct($includeDefault = null) {
        $this->includeDefault = $includeDefault;

        if (self::$packs === null) {
            self::$packs = array();
            $themepath = __DIR__."/../../themes/icons";
            $themes = array_diff(scandir($themepath), array('..', '.'));
            foreach ($themes as $theme) {
                if (!file_exists("{$themepath}/{$theme}/pack.ini")) continue;
                $data = parse_ini_file("{$themepath}/{$theme}/pack.ini", true);
                self::$packs[$theme] = $data;
            }
        }
    }

    public function getControl($current = null, $name = null, $id = null, $attributes = array()) {
        __require("i18n");

        $this->id = $id;
        $attrs = "";
        if ($name !== null) $attrs .= ' name="'.$name.'"';
        if ($id !== null) $attrs .= ' id="'.$id.'"';

        foreach ($attributes as $attr => $value) {
            $attrs .= ' '.$attr.'="'.$value.'"';
        }

        $html = '<select'.$attrs.'>';
        if ($this->includeDefault !== null) {
            $html .= '<option value="">'.I18N::resolveArgs($this->includeDefault).'</option>';
        }
        foreach (self::$packs as $pack => $data) {
            $html .= '<option value="'.$pack.'"';
            if ($pack == $current) $html .= ' selected';
            $html .= '>'.I18N::resolveArgs("theme.name_label", $data["name"], $data["author"]).'</option>';
        }
        $html .= '</select>';
        return $html;
    }

    public function getFollowingBlock($includeMainScript = true, $includeSelectorScript = true) {
        $out = "";

        if (self::$firstOnPage) {
            self::$firstOnPage = false;
            if ($includeMainScript) {
                $script = self::getScript();
                $out .= $script;
            }
        }

        if ($this->id !== null) {
            $html = '<div style="width: 100%;" id="iconviewer-'.$this->id.'"></div>';
            if ($includeSelectorScript) $html .= '
            <script type="text/javascript">'.$this->getSelectorScript().'</script>';
            $out .= $html;
        }

        return $out;
    }

    public function getSelectorScript() {
        return 'viewTheme("'.$this->id.'", document.getElementById("'.$this->id.'").value);
        document.getElementById("'.$this->id.'").addEventListener("change", function() {
            viewTheme("'.$this->id.'", document.getElementById("'.$this->id.'").value);
        });';
    }

    public static function getScript() {
        __require("theme");

        $script = '<script type="text/javascript">
            var themedata = '.json_encode(self::$packs, JSON_PRETTY_PRINT).';

            function viewTheme(selectorID, theme) {
                var box = document.getElementById("iconviewer-" + selectorID);
                box.innerHTML = "";

                if (theme === "") return;

                var variants = ["light", "dark"];
                var varbox = {};

                for (var i = 0; i < variants.length; i++) {
                    varbox[variants[i]] = document.createElement("div");
                    varbox[variants[i]].style.width = "calc(100% - 20px)";
                    varbox[variants[i]].style.padding = "10px";
                }

                varbox["light"].style.backgroundColor = "#ccc";
                varbox["dark"].style.backgroundColor = "#333";

                var tdata = themedata[theme];

                var icons = ["'.implode('", "', Theme::listIcons()).'"];

                for (var i = 0; i < icons.length; i++) {
                    var uri = "'.Config::getEndpointUri("/").'themes/icons/" + theme + "/";
                    if (tdata.hasOwnProperty("vector") && tdata["vector"].hasOwnProperty(icons[i])) {
                        uri += tdata["vector"][icons[i]];
                    } else if (tdata.hasOwnProperty("raster") && tdata["raster"].hasOwnProperty(icons[i])) {
                        uri += tdata["raster"][icons[i]];
                    } else {
                        uri = null;
                    }

                    if (uri != null) {
                        for (var j = 0; j < variants.length; j++) {
                            var icobox = document.createElement("img");
                            icobox.src = uri.split("{%variant%}").join(variants[j]);
                            icobox.style.width = "68px";
                            icobox.style.height = "68px";
                            icobox.style.margin = "5px";
                            varbox[variants[j]].appendChild(icobox);
                        }
                    }
                }

                if (tdata.hasOwnProperty("logo")) {
                    var logo = document.createElement("img");
                    logo.src = "'.Config::getEndpointUri("/").'themes/icons/" + theme + "/" + tdata["logo"].split("{%variant%}").join("'.Config::get("themes/color/admin").'");
                    logo.style.width = "400px";
                    logo.style.maxWidth = "100%";
                    logo.marginTop = "20px";
                    box.appendChild(logo);
                }

                var name = document.createElement("h2");
                name.innerText = tdata.name;
                name.style.color = "#'.(Config::get("themes/color/admin") == "dark" ? "ccc" : "333").'";
                name.style.marginBottom = "0";
                box.appendChild(name);

                var author = document.createElement("p");
                author.innerText = "Authored by " + tdata.author;
                box.appendChild(author);

                for (var i = 0; i < variants.length; i++) {
                    box.appendChild(varbox[variants[i]]);
                }

            }
        </script>';
        return $script;
    }

    public function parseValue($data) {
        return strval($data);
    }

    public function isValid($data) {
        return isset(self::$packs[$data]);
    }
}

?>
