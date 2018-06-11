<?php

class Config {
    private static $config = false;
    
    public static function get($path) {
        if (self::$config === false) self::loadConfig();
        
        $conf = self::config;
        
        $segments = explode("/", $path);
        foreach ($segments as $segment) {
            if (!isset($conf[$segment])) return self::getDefaultConfig($path);
            $conf = $conf[$segment];
        }
        
        return $conf;
    }

    public static function getDefault($path) {
        $defaults = array(
            "access/require-login" => false,
            "security/validate-lang" => true,
            "security/validate-ua" => true
        );
        
        if (isset($defaults[$path])) return $defaults[$path];
        return null;
    }
    
    private static function loadConfig() {
        $configLocation = __DIR__."/../config.json";
        
        if (!file_exists($configLocation)) return self::getDefaultConfig($path);
        self::$config = json_decode(file_get_contents($configLocation), true);
    }
}

?>