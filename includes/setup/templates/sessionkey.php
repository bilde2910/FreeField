<?php
/*
 * This file contains your randomly generated session encryption key. Keep this
 * secret. This file is overwritten with the random encryption key on install.
 */

class AuthSession {
    public static function getSessionKey() {
        // Replaced with a random key on install
        return base64_decode("");
    }
}

?>
