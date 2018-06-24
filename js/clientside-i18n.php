<?php

require_once("../includes/lib/global.php");
__require("i18n");

header('Content-Type: application/javascript');

?>

var i18n = <?php
    $entries = array(
        "objective.*",
        "reward.*",
        "poi.*"
    );
    
    $i18nmap = array();
    foreach ($entries as $entry) {
        $i18nmap = array_merge($i18nmap, I18N::resolveAll($entry));
    }
    
    $i18nlist = array();
    foreach ($i18nmap as $key => $value) {
        $i18nlist[] = "'{$key}': ".json_encode($value);
    }
    
    echo json_encode($i18nmap, JSON_PRETTY_PRINT);
?>

function resolveI18N(key, ...args) {
    if (i18n.hasOwnProperty(key)) {
        var resolv = i18n[key];
        for (var i = 0; i < args.length; i++) {
            resolv = resolv.split("{%" + (i + 1) + "}").join(args[i]);
        }
        return resolv;
    } else {
        return key;
    }
}
