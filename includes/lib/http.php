<?php
/*
    This library file contains functions relating to outbound HTTP connections.
*/

__require("config");

class HTTP {
    /*
        Sets connection options for a cURL handler object.
    */
    public static function setOptions(&$ch) {
        curl_setopt($ch, CURLOPT_USERAGENT, "FreeField/".FF_VERSION." PHP/".phpversion());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        /*
            Set SSL/TLS connection options.
        */
        if (Config::get("security/curl/verify-certificates")->value()) {
            $cacert = Config::get("security/curl/cacert-path")->value();
            if (file_exists($cacert)) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                curl_setopt($ch, CURLOPT_CAINFO, $cacert);
            }
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }
    }
}

?>
