<?php
/*
    This file contains your randomly generated encryption keys. Keep this
    secret. This file is overwritten with random encryption keys on install.
*/

class AuthKeys {
    protected static function getKeys() {
        return array(
            /*
                The key used to encrypt session data.
            */
            "session"   => base64_decode(""),
            /*
                The key used to encrypt user approval URLs for QR-code-based
                user approval. Used to encrypt only the user ID.
            */
            "id-only"   => base64_decode(""),
            /*
                The key used to encrypt various settings in the configuration
                file (i.e. passwords and other sensitive data).
            */
            "config"    => base64_decode("")
        );
    }
}

?>
