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

    private static $configtree = array(
        "main" => array(
            "access" => array(
                "site/uri" => array(
                    "default" => "",
                    "options" => "string"
                ),
                "site/name" => array(
                    "default" => "FreeField",
                    "options" => "string"
                )
            ),
            "database" => array(
                "database/type" => array(
                    "default" => "mysqli",
                    "options" => ["mysql", "mysqli", "pgsql", "sqlite", "sqlite3"]
                ),
                "database/host" => array(
                    "default" => "localhost",
                    "options" => "string"
                ),
                "database/port" => array(
                    "default" => -1,
                    "options" => "int,-1,65535"
                ),
                "database/username" => array(
                    "default" => "fieldfree",
                    "options" => "string"
                ),
                "database/password" => array(
                    "default" => "fieldfree",
                    "options" => "password"
                ),
                "database/database" => array(
                    "default" => "fieldfree",
                    "options" => "string"
                ),
                "database/table-prefix" => array(
                    "default" => "ffield_",
                    "options" => "string"
                )
            )
        ),
        "perms" => array(
            "default" => array(
                "permissions/default-level" => array(
                    "default" => 80,
                    "options" => "permission"
                )
            ),
            "map-access" => array(
                // TODO:
                "permissions/level/access" => array(
                    "default" => 0,
                    "options" => "permission"
                ),
                "permissions/level/report-research" => array(
                    "default" => 80,
                    "options" => "permission"
                ),
                "permissions/level/overwrite-research" => array(
                    "default" => 80,
                    "options" => "permission"
                ),
                "permissions/level/submit-poi" => array(
                    "default" => 120,
                    "options" => "permission"
                )
            ),
            "admin" => array(
                "permissions/level/admin/main/general" => array(
                    "default" => 250,
                    "options" => "permission"
                ),
                "permissions/level/admin/users/general" => array(
                    "default" => 160,
                    "options" => "permission"
                ),
                "permissions/level/admin/groups/general" => array(
                    "default" => 200,
                    "options" => "permission"
                ),
                "permissions/level/admin/users/groups" => array(
                    "default" => 160,
                    "options" => "permission"
                ),
                "permissions/level/admin/groups/self-manage" => array(
                    "default" => 250,
                    "options" => "permission"
                ),
                "permissions/level/admin/pois/general" => array(
                    "default" => 160,
                    "options" => "permission"
                ),
                "permissions/level/admin/perms/general" => array(
                    "default" => 200,
                    "options" => "permission"
                ),
                "permissions/level/admin/security/general" => array(
                    "default" => 200,
                    "options" => "permission"
                ),
                "permissions/level/admin/auth/general" => array(
                    "default" => 250,
                    "options" => "permission"
                ),
                "permissions/level/admin/themes/general" => array(
                    "default" => 200,
                    "options" => "permission"
                ),
                "permissions/level/admin/map/general" => array(
                    "default" => 250,
                    "options" => "permission"
                ),
                "permissions/level/admin/hooks/general" => array(
                    "default" => 200,
                    "options" => "permission"
                )
            )
        ),
        "security" => array(
            "user-creation" => array(
                "security/require-validation" => array(
                    "default" => false,
                    "options" => "bool"
                )
            ),
            "sessions" => array(
                "auth/session-length" => array(
                    "default" => 315619200, // 10 years
                    "options" => array(86400, 604800, 2592000, 7776000, 15811200, 31536000, 63072000, 157766400, 315619200)
                ),
                "security/validate-ua" => array(
                    "default" => "lenient",
                    "options" => array("no", "lenient", "strict")
                ),
                "security/validate-lang" => array(
                    "default" => true,
                    "options" => "bool"
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
                    "options" => "bool"
                ),
                "auth/provider/discord/client-id" => array(
                    "default" => "",
                    "options" => "string"
                ),
                "auth/provider/discord/client-secret" => array(
                    "default" => "",
                    "options" => "string"
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
                    "options" => "bool"
                ),
                "auth/provider/telegram/bot-username" => array(
                    "default" => "",
                    "options" => "string"
                ),
                "auth/provider/telegram/bot-token" => array(
                    "default" => "",
                    "options" => "string"
                )
            )
        ),
        "themes" => array(
            "color" => array(
                "themes/color/admin" => array(
                    "default" => "dark",
                    "options" => array("light", "dark")
                ),
                "themes/color/user-settings/theme" => array(
                    "default" => "dark",
                    "options" => array("light", "dark")
                ),
                "themes/color/user-settings/allow-personalization" => array(
                    "default" => true,
                    "options" => "bool"
                ),
                "themes/color/map/theme/mapbox" => array(
                    "default" => "basic",
                    "options" => array("basic", "streets", "bright", "light", "dark", "satellite")
                ),
                "themes/color/map/allow-personalization" => array(
                    "default" => true,
                    "options" => "bool"
                )
            ),
            "icons" => array(
                "themes/icons/default" => array(
                    "default" => "freefield-3d-compass",
                    "options" => "string",
                    "custom" => "icon-selector"
                ),
                "themes/icons/allow-personalization" => array(
                    "default" => true,
                    "options" => "bool"
                )
            )
        ),
        "map" => array(
            "provider" => array(
                "map/provider/source" => array(
                    "default" => "mapbox",
                    "options" => array("mapbox")
                ),
                "map/provider/mapbox/access-token" => array(
                    "default" => "",
                    "options" => "string"
                )
            ),
            "default" => array(
                "map/default/center/latitude" => array(
                    "default" => 0.0,
                    "options" => "float,-90,90"
                ),
                "map/default/center/longitude" => array(
                    "default" => 0.0,
                    "options" => "float,-180,180"
                ),
                "map/default/zoom" => array(
                    "default" => 14,
                    "options" => "float,0,20"
                )
            )
        )
    );

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
                $perm = "admin/".$permissionsAssoc[$option]."/general";
                if (!Auth::getCurrentUser()->hasPermission($perm)) {
                    $optDeny[] = $option;
                }
                $values = $flat[$option];
                if ($values["options"] == "permission") {
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

            if (!isset($flat[$option])) continue;
            $values = $flat[$option];

            $value = null;

            if (is_array($values["options"])) {
                $type = gettype($values["options"][0]);
                switch ($type) {
                    case "string":
                        $value = $value_raw;
                        break;
                    case "integer":
                        $value = intval($value_raw);
                        break;
                }
            } elseif (preg_match('/^int,([\d-]+),([\d-]+)$/', $values["options"], $matches)) {
                $min = intval($matches[1]);
                $max = intval($matches[2]);
                $value = intval($value_raw);
                if ($value < $min) $value = $min;
                if ($value > $max) $value = $max;
            } elseif (preg_match('/^float,([\d-]+),([\d-]+)$/', $values["options"], $matches)) {
                $min = floatval($matches[1]);
                $max = floatval($matches[2]);
                $value = floatval($value_raw);
                if ($value < $min) $value = $min;
                if ($value > $max) $value = $max;
            } else {
                switch ($values["options"]) {
                    case "string":
                        $value = $value_raw;
                        break;
                    case "password":
                        $value = $value_raw;
                        break;
                    case "int":
                        $value = intval($value_raw);
                        break;
                    case "float":
                        $value = floatval($value_raw);
                        break;
                    case "bool":
                        $value = ($value_raw == "on");
                        break;
                    case "permission":
                        $value = intval($value_raw);
                        if ($value < 0) $value = 0;
                        if ($value > 250) $value = 250;
                        break;
                }
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

class CustomControls {
    /*
        For rendering custom controls. $control can be:
        - field: The input field area.
        - after: A dedicated area below the configurator.
    */
    public static function getControl($control, $path, $current, $section, $attrs = array()) {
        switch ($control) {
            case "icon-selector":
                global $control_iconSelectorID;
                if ($section == "field") {
                    $themepath = __DIR__."/../../themes/icons";
                    $themes = array_diff(scandir($themepath), array('..', '.'));
                    $options = "";
                    $themedata = array();
                    $control_iconSelectorID = bin2hex(openssl_random_pseudo_bytes(4));
                    foreach ($themes as $theme) {
                        if (!file_exists("{$themepath}/{$theme}/pack.ini")) continue;
                        $data = parse_ini_file("{$themepath}/{$theme}/pack.ini", true);
                        $themedata[$theme] = $data;
                        $options .= '<option id="iconselector-'.$control_iconSelectorID.'" value="'.$theme.'"'.($current == $theme ? ' selected' : '').'>'.$data["name"].' (by '.$data["author"].')</option>';
                    }
                    return '
                        <select name="'.$path.'"'.(count($attrs) > 0 ? ' '.implode(' ', $attrs) : '').'>'.$options.'</select>
                        <script type="text/javascript">
                            var themedata = '.json_encode($themedata, JSON_PRETTY_PRINT).';

                            function viewTheme_'.$control_iconSelectorID.'(theme) {
                                var box = document.getElementById("iconviewer-'.$control_iconSelectorID.'");
                                box.innerHTML = "";

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

                                var icons = [
                                    "potion", "super_potion", "hyper_potion", "max_potion",
                                    "revive", "max_revive",
                                    "fast_tm", "charge_tm",
                                    "stardust", "rare_candy", "encounter",
                                    "battle", "raid",
                                    "catch", "throwing_skill", "hatch",
                                    "power_up", "evolve",
                                    "unknown"
                                ];

                                for (var i = 0; i < icons.length; i++) {
                                    var uri = "../themes/icons/" + theme + "/";
                                    if (tdata.hasOwnProperty("vector") && tdata["vector"].hasOwnProperty(icons[i])) {
                                        uri += tdata["vector"][icons[i]];
                                    } else if (tdata.hasOwnProperty("vector") && tdata["vector"].hasOwnProperty(icons[i])) {
                                        uri += tdata["raster"][icons[i]];
                                    } else {
                                        uri = "about:blank";
                                    }

                                    for (var j = 0; j < variants.length; j++) {
                                        var icobox = document.createElement("img");
                                        icobox.src = uri.split("{%variant%}").join(variants[j]);
                                        icobox.style.width = "68px";
                                        icobox.style.height = "68px";
                                        icobox.style.margin = "5px";
                                        varbox[variants[j]].appendChild(icobox);
                                    }
                                }

                                if (tdata.hasOwnProperty("logo")) {
                                    var logo = document.createElement("img");
                                    logo.src = "../themes/icons/" + theme + "/" + tdata["logo"].split("{%variant%}").join("'.Config::get("themes/color/admin").'");
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
                        </script>
                    ';
                } elseif ($section == "after") {
                    return '
                        <div style="width: 100%;" id="iconviewer-'.$control_iconSelectorID.'"></div>
                        <script type="text/javascript">
                            var selector_'.$control_iconSelectorID.' = document.getElementById("iconselector-'.$control_iconSelectorID.'");
                            viewTheme_'.$control_iconSelectorID.'(selector_'.$control_iconSelectorID.'.value);
                            selector_'.$control_iconSelectorID.'.addEventListener("select", function() {
                                viewTheme_'.$control_iconSelectorID.'(selector_'.$control_iconSelectorID.'.value);
                            });
                        </script>
                    ';
                }
        }
        return null;
    }
}

?>
