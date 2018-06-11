<?php

__require("config");
__require("db");

require_once(__DIR__."/../sessionkey.php");

class Auth {
    // Cache for the current authenticated user to prevent unnecessary database lookups.
    private static $authSessionCache = false;
    
    // Gets the User-Agent without version numbers for identifying a specific browser type.
    private static function getUnversionedUserAgent() {
        if (!isset($_SERVER["HTTP_USER_AGENT"])) return "";
        return preg_replace('@/[^ ]+@', "", $_SERVER["HTTP_USER_AGENT"]);
    }

    // Gets the nickname of the current user.
    public static function getNickname() {
        $session = self::getAuthenticatedSession();
        if ($session === null) return null;
        return $session["nick"];
    }
    
    // Gets the current user ID.
    public static function getUserID() {
        $session = self::getAuthenticatedSession();
        if ($session === null) return null;
        return $session["id"];
    }

    // Gets the raw, unauthenticated session array from cookie data.
    private static function getSession() {
        if (!isset($_COOKIE["session"])) return null;
        $session = $_COOKIE["session"];
        
        $c = base64_decode($session, true);
        if ($c === false) return null;
        
        $ivlen = openssl_cipher_iv_length("AES-256-CBC");
        if (strlen($c) < $ivlen + 32 + 1) return null;
        
        $iv = substr($c, 0, $ivlen);
        $hmac = substr($c, $ivlen, 32);
        $ciph = substr($c, $ivlen + 32);
        
        $data = openssl_decrypt($ciph, "AES-256-CBC", AuthSession::getSessionKey(), OPENSSL_RAW_DATA, $iv);
        if ($data === false) return null;
        
        return json_decode($data, true);
    }
    
    // Writes authenticated session and validation data to a cookie. Called from auth providers.
    public static function setAuthenticatedSession($id, $expire) {
        $user = dbSelect("token", "users",
            array(
                "id" => $id
            )
        );
        $session = array(
            "id" => $id,
            "token" => $user["token"],
            "expire" => time() + $expire
        );
        
        if (Config::get("security/validate-ua")) $session["http-ua"] = self::getUnversionedUserAgent();
        if (Config::get("security/validate-lang")) $session["http-lang"] = isset($_SERVER["HTTP_ACCEPT_LANGUAGE"]) ? $_SERVER["HTTP_ACCEPT_LANGUAGE"] : "";
        
        setSession($data, $expire);
    }

    // Writes the raw session array to a cookie.
    private static function setSession($data, $expire) {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length("AES-256-CBC"));
        $ciph = openssl_encrypt(json_encode($data), "AES-256-CBC", AuthSession::getSessionKey(), OPENSSL_RAW_DATA, $iv);
        $hmac = hash_hmac("SHA256", $ciph, AuthSession::getSessionKey(), true);
        $session = base64_encode($iv.$hmac.$ciph);
        setcookie("session", $session, time() + $expire);
    }

    // Authenticates the current cookie session data against the user database.
    private static function getAuthenticatedSession() {
        if (self::$authSessionCache !== false) return self::$authSessionCache;
        
        $session = self::getSession();
        if ($session === null) return null;
        if ($session["expire"] < time()) return null;
        
        $selectors = array();
        
        if (Config::get("security/validate-ua")) $selectors["http-ua"] = self::getUnversionedUserAgent();
        if (Config::get("security/validate-lang")) $selectors["http-lang"] = isset($_SERVER["HTTP_ACCEPT_LANGUAGE"]) ? $_SERVER["HTTP_ACCEPT_LANGUAGE"] : "";
        
        foreach ($selectors as $selectors => $expectedValue) {
            if ($session[$selector] != $expectedValue) return null;
        }
        
        $db = Database::getSparrow();
        $user = $db
            ->from(Database::getTable("users"))
            ->where("id", $session["id"])
            ->where("token", $session["token"])
            ->one();
        
        self::$authSessionCache = $user;
        return $user;
    }
}

?>
