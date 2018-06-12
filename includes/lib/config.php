<?php

class Config {
    private static $config = false;
    
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

    public static function getDefault($path) {
        $defaults = array(
            "auth/provider/discord/client-id" => null,
            "auth/provider/discord/client-secret" => null,
            "auth/session-length" => 315576000, // 10 years of 365.25 days
            "database/host" => "localhost",
            "database/database" => "fieldfree",
            "database/password" => "fieldfree",
            "database/port" => -1,
            "database/table-prefix" => "ffield_",
            "database/type" => "mysqli",
            "database/username" => "fieldfree",
            "permissions/level/access" => 0,
            "security/validate-lang" => true,
            "security/validate-ua" => true,
            "setup/uri" => null,
            "user/require-validation" => false
        );
        
        if (isset($defaults[$path])) return $defaults[$path];
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