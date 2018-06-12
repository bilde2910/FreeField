<?php

class Config {
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
        - "permission" for a permission tier
        - "bool" for a boolean
        - array() for a selection box with the array contents as options
        
        I18N is handled with setting.<setting>.name and setting.<setting>.desc.
        For domains and sections, it's handled with admin.domain.<domain>.name and
        admin.domain.<domain>.desc, and admin.section.<domain>.<section>.name and
        admin.section.<domain>.<section>.desc respectively.
        
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
                "setup/uri" => array(
                    "default" => null,
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
            "permissions" => array(
                "permissions/level/access" => array(
                    "default" => 0,
                    "options" => "permission"
                )
            ),
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
                    "default" => 315576000, // 10 years of 365.25 days
                    "options" => "int"
                )
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
                "auth/provider/discord/enabled" => array(
                    "default" => false,
                    "options" => bool
                ),
                "auth/provider/discord/client-id" => array(
                    "default" => null,
                    "options" => "string"
                ),
                "auth/provider/discord/client-secret" => array(
                    "default" => null,
                    "options" => "string"
                )
            )
        )
    );
    
    public static function get($path) {
        if (self::$config === false) self::loadConfig();
        
        $conf = clone self::config;
        
        $segments = explode("/", $path);
        foreach ($segments as $segment) {
            if (!isset($conf[$segment])) return self::getDefaultConfig($path);
            $conf = $conf[$segment];
        }
        
        return $conf;
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
                    self::$flattree[$path] = $values;
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
    
    public static function getEndpointUri($endpoint) {
        $basepath = self::get("setup/uri");
        return (substr($basepath, 0, 1) == "/" ? substr($basepath, 1) : $basepath).$endpoint;
    }
    
    private static function loadConfig() {
        $configLocation = __DIR__."/../config.json";
        
        if (!file_exists($configLocation)) return self::getDefaultConfig($path);
        self::$config = json_decode(file_get_contents($configLocation), true);
    }
}

?>