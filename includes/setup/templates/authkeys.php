<?php
/*
    This file contains your randomly generated session encryption key. Keep this
    secret. This file is overwritten with the random encryption key on install.
*/

class AuthKeys {
    /*
        Gets the key used to encrypt session data.
    */
    public static function getSessionKey() {
        // Replaced with a random key on install
        return base64_decode("");
    }

    /*
        Gets the key used to encrypt user approval URLs for QR-code-based user
        approval. Used to encrypt only the user ID.
    */
    public static function getIdOnlyKey() {
        // Replaced with a random key on install
        return base64_decode("");
    }

    /*
        Gets the key used to encrypt various settings in the configuration file.
    */
    public static function getConfigurationKey() {
        // Replaced with a random key on install
        return base64_decode("");
    }
}

?>
