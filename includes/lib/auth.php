<?php

__require("config");
__require("db");

require_once(__DIR__."/../sessionkey.php");

class Auth {
    // Cache for the current authenticated user to prevent unnecessary database lookups.
    private static $authSessionCache = null;

    // Returns an array of authentication providers and their config requirements.
    private static function getProviderRequirements() {
        return array(
            "discord" => ["client-id", "client-secret"],
            "telegram" => ["bot-username", "bot-token"]
        );
    }

    // Returns whether the given provider satisfies all config requirements.
    public static function isProviderEnabled($provider) {
        $providerRequirements = self::getProviderRequirements();

        if (!isset($providerRequirements[$provider])) return false;
        if (!Config::get("auth/provider/{$provider}/enabled")) return false;

        $conf = array();
        foreach ($providerRequirements[$provider] as $req) {
            $conf[] = "auth/provider/{$provider}/{$req}";
        }

        if (Config::ifAny($conf, null)) return false;

        return true;
    }

    // Returns a list of authentication providers.
    public static function getAllProviders() {
        return array_keys(self::getProviderRequirements());
    }

    // Returns a list of enabled authentication providers.
    public static function getEnabledProviders() {
        $providers = self::getAllProviders();
        for ($i = 0; $i < count($providers); $i++) {
            if (!self::isProviderEnabled($providers[$i])) {
                unset($providers[$i]);
            }
        }
        return $providers;
    }

    // Gets the User-Agent without version numbers for identifying a specific browser type.
    private static function getUnversionedUserAgent() {
        if (!isset($_SERVER["HTTP_USER_AGENT"])) return "";
        return preg_replace('@/[^ ]+@', "", $_SERVER["HTTP_USER_AGENT"]);
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
        $db = Database::getSparrow();
        $token = $db
            ->from(Database::getTable("users"))
            ->where("id", $id)
            ->value("token");

        if ($token === null) {
            // New user

        }

        $session = array(
            "id" => $id,
            "token" => $token,
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

    // Get user by ID.
    public static function getUser($id) {
        $db = Database::getSparrow();
        $userdata = $db
            ->from(Database::getTable("users"))
            ->where("id", $session["id"])
            ->one();

        return new User($userdata);
    }

    // Authenticates the current cookie session data against the user database.
    private static function getCurrentUser() {
        if (self::$authSessionCache !== null) return self::$authSessionCache;

        $session = self::getSession();
        if ($session === null) return self::setReturnUser(new User(null));
        if ($session["expire"] < time()) return self::setReturnUser(new User(null));

        $selectors = array();

        if (Config::get("security/validate-ua")) $selectors["http-ua"] = self::getUnversionedUserAgent();
        if (Config::get("security/validate-lang")) $selectors["http-lang"] = isset($_SERVER["HTTP_ACCEPT_LANGUAGE"]) ? $_SERVER["HTTP_ACCEPT_LANGUAGE"] : "";

        foreach ($selectors as $selectors => $expectedValue) {
            if ($session[$selector] != $expectedValue) return self::setReturnUser(new User(null));
        }

        $db = Database::getSparrow();
        $userdata = $db
            ->from(Database::getTable("users"))
            ->where("id", $session["id"])
            ->where("token", $session["token"])
            ->one();

        return self::setReturnUser(new User($userdata));
    }

    // Sets the current user to the given user and returns it.
    private static function setReturnUser($user) {
        self::$authSessionCache = $user;
        return $user;
    }

    // Returns whether or not the user is authenticated
    public static function isAuthenticated() {
        return self::getCurrentUser()->exists();
    }
}

class User {
    private $data = null;

    function __construct($userdata) {
        $this->data = $userdata;
    }

    // Gets whether or not the user exists. If false, the user is not logged in.
    public function exists() {
        return $this->data !== null && count($this->data > 0);
    }

    // Gets the nickname of the current user.
    public function getNickname() {
        if (!$this->exists()) return null;
        return $this->data["nick"];
    }

    // Gets the current user ID.
    public function getUserID() {
        if (!$this->exists()) return null;
        return $this->data["id"];
    }

    // Gets the current user permission level.
    public function getPermissionLevel() {
        if (!$this->exists()) return 0;
        return $this->data["level"];
    }

    // Checks whether the user has the given permission.
    public function hasPermission($permission) {
        if (!$this->exists()) {
            $explperms = explode(",", $this->data["overrides"]);
            foreach ($explperms as $perm) {
                if (substr($perm, 1) == $permission) {
                    if (substr($perm, 0, 1) == "+") return true;
                    if (substr($perm, 0, 1) == "-") return false;
                }
            }
        }
        $perm = Config::get("permissions/level/{$permission}");
        return ($this->data === null ? 0 : $this->data["level"]) >= $perm;
    }

    // Checks whether the user has a user-level override for the given permission.
    public function hasExplicitRights($permission) {
        if (!$this->exists()) return false;
        $explperms = explode(",", $this->data["overrides"]);
        foreach ($explperms as $perm) {
            if (substr($perm, 1) == $permission) return true;
        }
        return false;
    }
}

?>
