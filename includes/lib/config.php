<?php

function getConfig($path) {
    $configLocation = __DIR__."/../config.json";
    
    if (!file_exists($configLocation)) return getDefaultConfig($path);
    $config = json_decode(file_get_contents($configLocation), true);
    
    $segments = explode("/", $path);
    foreach ($segments as $segment) {
        if (!isset($config[$segment])) return getDefaultConfig($path);
        $config = $config[$segment];
    }
    
    return $config;
}

function getDefaultConfig($path) {
    $defaults = array(
        "access/require-login" => false
    );
    
    if (isset($defaults[$path])) return $defaults[$path];
    return null;
}

?>