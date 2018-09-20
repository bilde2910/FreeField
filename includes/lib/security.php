<?php
/*
    This file contains functionality for encrypting and decrypting data, such as
    session and configuration data, and functions used to improve the security
    of FreeField.
*/

__require("config");
require_once(__DIR__."/../userdata/authkeys.php");

class Security extends AuthKeys {
    private const CSRF_COOKIE_NAME = "csrf";
    private const CSRF_POST_FIELD_NAME = "_csrf";
    private const CSRF_GET_FIELD_NAME = "_csrf";

    /*
        The CSRF token for this browsing session. Set from `requireCSRFToken()`.
    */
    private static $csrfToken = null;

    /*
        Checks for CSRF validation failures. Returns the success of the
        validation. If validation fails and this function returns false, data
        processing should be aborted.
    */
    public static function validateCSRF() {
        /*
            Ensure that the CSRF token cookie exists.
        */
        if (!isset($_COOKIE[self::CSRF_COOKIE_NAME])) return false;
        $cookieToken = $_COOKIE[self::CSRF_COOKIE_NAME];

        /*
            Ensure that the CSRF token form field (POST or GET) exists.
        */
        $formToken = null;
        if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST)) {
            if (!isset($_POST[self::CSRF_POST_FIELD_NAME])) return false;
            $formToken = $_POST[self::CSRF_POST_FIELD_NAME];
        } else {
            if (!isset($_GET[self::CSRF_GET_FIELD_NAME])) return false;
            $formToken = $_GET[self::CSRF_GET_FIELD_NAME];
        }

        /*
            Ensure that the tokens match.
        */
        if ($cookieToken !== $formToken) return false;
        return true;
    }

    /*
        Unsets the CSRF checking form fields, if present. This is used in
        /auth/oa2/telegram.php because the validation method loops over all
        `$_GET` parameters to build a hash. The hash should not include the CSRF
        parameter.
    */
    public static function unsetCSRFFields() {
        if (isset($_POST[self::CSRF_POST_FIELD_NAME]))
            unset($_POST[self::CSRF_POST_FIELD_NAME]);
        if (isset($_GET[self::CSRF_GET_FIELD_NAME]))
            unset($_GET[self::CSRF_GET_FIELD_NAME]);
    }

    /*
        Declared at the top of the script for all pages that require the
        placement of a CSRF token field somewhere on the page. If this is not
        set in a script that calls `getCSRFToken()` or `getCSRFInputField()`, an
        exception will be thrown.
    */
    public static function requireCSRFToken() {
        if (isset($_COOKIE[self::CSRF_COOKIE_NAME])) {
            /*
                If a cookie already exists with a CSRF token, use that cookie's
                token value.
            */
            self::$csrfToken = $_COOKIE[self::CSRF_COOKIE_NAME];
        } else {
            /*
                If no cookie is set, set one. The CSRF token is generated as a
                base64-encoded string of 32 securely generated random bytes and
                is only valid for the current browsing session.
            */
            $url = parse_url(Config::getEndpointUri("/"), PHP_URL_PATH);
            $token = base64_encode(openssl_random_pseudo_bytes(32));
            setcookie(self::CSRF_COOKIE_NAME, $token, 0, $url);
            self::$csrfToken = $token;
        }
    }

    /*
        Returns the CSRF token for this browsing session. Scripts intending to
        use this function MUST first call `requireCSRFToken()` at the start of
        the script to ensure that the CSRF token cookie is set. The cookie
        header cannot be sent in the middle of a page if page content has
        already been sent to the client, hence it must be done before any output
        is sent.
    */
    public static function getCSRFToken() {
        if (self::$csrfToken === null) {
            throw new Exception("Users of getCSRFToken() must call requireCSRFToken() ".
                                "at the start of the script!");
            exit;
        } else {
            return self::$csrfToken;
        }
    }

    /*
        Returns a hidden <input> field that can be placed in a form to enable
        CSRF protection on the data sending side. CSRF validation must also be
        performed using `validateCSRF()` in the receiving script for this to
        have any effect.

        Scripts intending to use this function MUST first call
        `requireCSRFToken()` at the start of the script to ensure that the CSRF
        token cookie is set. The cookie header cannot be sent in the middle of a
        page if page content has already been sent to the client, hence it must
        be done before any output is sent.
    */
    public static function getCSRFInputField() {
        return '<input type="hidden"
                       name="'.self::CSRF_POST_FIELD_NAME.'"
                       value="'.self::getCSRFToken().'">';
    }

    /*
        Returns URL GET query parameter that can be placed on a link to enable
        CSRF protection on the data sending side. CSRF validation must also be
        performed using `validateCSRF()` in the receiving script for this to
        have any effect.

        Scripts intending to use this function MUST first call
        `requireCSRFToken()` at the start of the script to ensure that the CSRF
        token cookie is set. The cookie header cannot be sent in the middle of a
        page if page content has already been sent to the client, hence it must
        be done before any output is sent.
    */
    public static function getCSRFUrlParameter() {
        return self::CSRF_GET_FIELD_NAME.'='.urlencode(self::getCSRFToken());
    }

    /*
        Looks up the frame options policy of this site and outputs the
        corresponding frame options header.
    */
    public static function declareFrameOptionsHeader() {
        switch (Config::get("security/frame-options")->value()) {
            case "deny":
                header("X-Frame-Options: deny");
                break;
            case "sameorigin":
                header("X-Frame-Options: sameorigin");
                break;
        }
    }

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
