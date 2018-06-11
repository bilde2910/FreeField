<?php

function getConfig($path, $default = null) {
    $configLocation = __DIR__."/../config.json";
    
    if (!file_exists($configLocation)) return $default;
    $config = json_decode(file_get_contents($configLocation), true);
    
    $segments = explode("/", $path);
    foreach ($segments as $segment) {
        if (!isset($config[$segment])) return $default;
        $config = $config[$segment];
    }
    
    return $config;
}

?>