<?php

__require("config");
__require("db");

require_once(__DIR__."/../sessionkey.php");

class Auth {
    // Cache for the current authenticated user to prevent unnecessary database lookups.
    private static $authSessionCache = null;
    // Cache for available permission groups, for the same purpose.
    private static $groupsCache = null;

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
    private static function getVersionlessUserAgent() {
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
    public static function setAuthenticatedSession($id, $expire, $humanId, $suggestedNick) {
        $db = Database::getSparrow();
        $token = $db
            ->from(Database::getTable("user"))
            ->where("id", $id)
            ->value("token");

        $approved = true;
        if ($token === null) {
            // New user
            $approved = !Config::get("security/require-validation");
            $token = substr(base64_encode(openssl_random_pseudo_bytes(32)), 0, 32);
            $data = array(
                "id" => $id,
                "provider_id" => $humanId,
                "nick" => $suggestedNick,
                "token" => $token,
                "permission" => Config::get("permissions/default-level"),
                "approved" => $approved
            );
            $db
                ->from(Database::getTable("user"))
                ->insert($data)
                ->execute();
        }

        $session = array(
            "id" => $id,
            "token" => $token,
            "expire" => time() + $expire
        );

        if (Config::get("security/validate-ua")) $session["http-ua"] = self::getVersionlessUserAgent();
        if (Config::get("security/validate-lang")) $session["http-lang"] = isset($_SERVER["HTTP_ACCEPT_LANGUAGE"]) ? $_SERVER["HTTP_ACCEPT_LANGUAGE"] : "";

        self::setSession($session, $expire);
        return $approved;
    }

    // Writes the raw session array to a cookie.
    private static function setSession($data, $expire) {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length("AES-256-CBC"));
        $ciph = openssl_encrypt(json_encode($data), "AES-256-CBC", AuthSession::getSessionKey(), OPENSSL_RAW_DATA, $iv);
        $hmac = hash_hmac("SHA256", $ciph, AuthSession::getSessionKey(), true);
        $session = base64_encode($iv.$hmac.$ciph);
        setcookie("session", $session, time() + $expire, "/");
    }

    // Get user by ID.
    public static function getUser($id) {
        $db = Database::getSparrow();
        $userdata = $db
            ->from(Database::getTable("user"))
            ->where("id", $session["id"])
            ->leftJoin(Database::getTable("group"), array(Database::getTable("group").".level" => Database::getTable("user").".permission"))
            ->one();

        return new User($userdata);
    }

    // Get all users.
    public static function listUsers() {
        $db = Database::getSparrow();
        $userdata = $db
            ->from(Database::getTable("user"))
            ->leftJoin(Database::getTable("group"), array(Database::getTable("group").".level" => Database::getTable("user").".permission"))
            ->many();

        $users = array();
        foreach ($userdata as $data) {
            $users[] = new User($data);
        }
        return $users;
    }

    // Authenticates the current cookie session data against the user database.
    public static function getCurrentUser() {
        if (self::$authSessionCache !== null) return self::$authSessionCache;

        $session = self::getSession();
        if ($session === null) return self::setReturnUser(new User(null));
        if ($session["expire"] < time()) return self::setReturnUser(new User(null));

        $selectors = array();

        if (Config::get("security/validate-ua")) $selectors["http-ua"] = self::getVersionlessUserAgent();
        if (Config::get("security/validate-lang")) $selectors["http-lang"] = isset($_SERVER["HTTP_ACCEPT_LANGUAGE"]) ? $_SERVER["HTTP_ACCEPT_LANGUAGE"] : "";

        foreach ($selectors as $selector => $expectedValue) {
            if ($session[$selector] != $expectedValue) return self::setReturnUser(new User(null));
        }

        $db = Database::getSparrow();
        $userdata = $db
            ->from(Database::getTable("user"))
            ->where("id", $session["id"])
            ->where("token", $session["token"])
            ->leftJoin(Database::getTable("group"), array(Database::getTable("group").".level" => Database::getTable("user").".permission"))
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

    // Returns a list of permission levels
    public static function listPermissionLevels() {
        if (self::$groupsCache !== null) return self::$groupsCache;
        $db = Database::getSparrow();
        $perms = $db
            ->from(Database::getTable("group"))
            ->many();

        usort($perms, function($a, $b) {
            if ($a["level"] == $b["level"]) return 0;
            return $a["level"] > $b["level"] ? -1 : 1;
        });
        self::$groupsCache = $perms;
        return $perms;
    }

    // Resolves the I18N string of a permission label
    public static function resolvePermissionLabelI18N($label) {
        if (substr($label, 0, 6) == "{i18n:" && substr($label, -1, 1) == "}") {
            __require("i18n");
            $query = substr($label, 6, -1);
            return I18N::resolve($query);
        } else {
            return $label;
        }
    }

    // Returns an HTML control for selecting permission levels
    public static function getPermissionSelector($name, $id = null, $selectedLevel = 0) {
        $user = self::getCurrentUser();
        $perms = self::listPermissionLevels();
        $opts = "";
        $curperm = null;
        foreach ($perms as $perm) {
            if ($perm["level"] == $selectedLevel) $curperm = $perm;
            $opts .= '<option value="'.$perm["level"].'"'.($perm["color"] !== null ? ' style="color: #'.$perm["color"].'"' : '').($user->canChangeAtPermission($perm["level"]) ? '' : ' disabled').'>'.$perm["level"].' - '.self::resolvePermissionLabelI18N($perm["label"]).'</option>';
        }
        if ($curperm === null) {
            $curopt = '<option value="'.$selectedLevel.'" selected>'.$selectedLevel.' - '.self::resolvePermissionLabelI18N("{i18n:group.level.unknown}").'</option>';
        } else {
            $curopt = '<option value="'.$selectedLevel.'" style="color:" selected>'.$selectedLevel.' - '.self::resolvePermissionLabelI18N($curperm["label"]).'</option>';
        }
        return '<select name="'.$name.'"'.($id !== null ? ' id="'.$id.'"' : '').($user->canChangeAtPermission($selectedLevel) ? '' : ' disabled').'><optgroup label="Current group">'.$curopt.'</optgroup><optgroup label="Available groups">'.$opts.'</optgroup></select>';
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
        if (!$this->exists()) return "<Anonymous>";
        return $this->data["nick"];
    }

    // Gets the nickname for displaying in HTML with colors.
    public function getNicknameHTML() {
        if (!$this->exists()) return htmlencode("<Anonymous>");
        $color = self::getColor();
        return '<span'.($color !== null ? ' style="color: #'.$color.';"' : '').'>'.htmlentities(self::getNickname()).'</span>';
    }

    public function getProviderIdentity() {
        if (!$this->exists()) return "<Anonymous>";
        return $this->data["provider_id"];

    }

    // Gets the current user ID.
    public function getUserID() {
        if (!$this->exists()) return null;
        return $this->data["id"];
    }

    // Gets the current user permission level.
    public function getPermissionLevel() {
        if (!$this->exists()) return 0;
        $perm = $this->data["permission"];
        return $perm > 1000 ? $perm - 1000 : $perm;
    }

    // Gets the color this users should display as due to their permission gruop.
    public function getColor() {
        if (!$this->exists()) return 0;
        return $this->data["color"];
    }

    // Gets the date of the first login from this user.
    public function getRegistrationDate() {
        if (!$this->exists()) return null;
        return $this->data["user_signup"];
    }

    // Gets the authentication provider used by this user.
    public function getProvider() {
        if (!$this->exists()) return null;
        if (strpos($this->data["id"], ":") !== false) {
            return substr($this->data["id"], 0, strpos($this->data["id"], ":"));
        } else {
            return null;
        }
    }

    // Checks whether the user has been approved by an administrator, if approval is required.
    public function isApproved() {
        return $this->data["approved"];
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
        return ($this->data === null || !self::isApproved() ? 0 : $this->getPermissionLevel()) >= $perm;
    }

    // Checks whether the current user is authorized to make changes to an object that requires the given permission level.
    public function canChangeAtPermission($level) {
        if ($level < $this->getPermissionLevel()) {
            return true;
        }
        if ($this->hasPermission("admin/groups/self-manage") && $level <= $this->getPermissionLevel()) {
            return true;
        }
        return false;
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
