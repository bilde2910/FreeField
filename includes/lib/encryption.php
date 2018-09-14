<?php
/*
    This file contains functionality for encrypting and decrypting data, such as
    session and configuration data.
*/

require_once(__DIR__."/../userdata/authkeys.php");

class Encryption extends AuthKeys {
    /*
        Encrypts an array using the specified encryption key.
    */
    public static function encryptArray($data, $keyName) {
        $keys = parent::getKeys();
        if (!isset($keys[$keyName])) return null;
        $key = $keys[$keyName];

        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length("AES-256-CBC"));
        $ciph = openssl_encrypt(
            json_encode($data),
            "AES-256-CBC",
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        $hmac = hash_hmac("SHA256", $ciph, $key, true);
        return base64_encode($iv.$hmac.$ciph);
    }
    /*
        Decrypts an array using the specified encryption key. An optional
        `$subkey` may be supplied. If given, this function returns the `$subkey`
        element of the decrypted array, or null if decryption failed or the key
        was not found. If `$subkey` is not given, the entire array is returned.
    */
    public static function decryptArray($data, $keyName, $subkey = null) {
        $keys = parent::getKeys();
        if (!isset($keys[$keyName])) return null;
        $key = $keys[$keyName];

        $c = base64_decode($data, true);
        if ($c === false) return null;

        $ivlen = openssl_cipher_iv_length("AES-256-CBC");
        if (strlen($c) < $ivlen + 32 + 1) return null;

        $iv = substr($c, 0, $ivlen);
        $hmac = substr($c, $ivlen, 32);
        $ciph = substr($c, $ivlen + 32);

        $data = openssl_decrypt(
            $ciph,
            "AES-256-CBC",
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        if ($data === false) return null;
        $array = json_decode($data, true);

        if ($subkey === null) {
            return $array;
        } else {
            if ($array === null) return null;
            if (!isset($array[$subkey])) return null;
            return $array[$subkey];
        }
    }
}

?>
