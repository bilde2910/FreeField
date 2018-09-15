<?php
/*
    This library file contains functions relating to authentication, users,
    groups and permissions.
*/

__require("config");
__require("db");
__require("security");

class Auth {
    /*
        Cache for the current authenticated user to prevent unnecessary database
        lookups due to repeat calls to `getCurrentUser()`.
    */
    private static $authSessionCache = null;
    /*
        Cache for available permission groups, for the same purpose.
    */
    private static $groupsCache = null;

    /*
        Returns an array of authentication providers and their configuration
        settings that must be set in order for the provider to work. (For
        example, Discord authentication requires that both client ID and client
        secret is set.)
    */
    private static function getProviderRequirements() {
        return array(
            "discord" => ["client-id", "client-secret"],
            "telegram" => ["bot-username", "bot-token"]
        );
    }

    /*
        Returns whether the given authentication provider has been enabled, and
        whether all of the options for the given provider in
        `getConfigRequirements()` are validly defined in the configuration file.
    */
    public static function isProviderEnabled($provider) {
        $providerRequirements = self::getProviderRequirements();

        // Undefined authentication provider
        if (!isset($providerRequirements[$provider])) return false;
        // Authentication provider exists, but is not enabled
        if (!Config::get("auth/provider/{$provider}/enabled")->value()) return false;

        /*
            Create an array of required configuration settings for the given
            authentication provider. All of these are empty strings by default.
            This means that if any of the settings in this array resolve to an
            empty string, that setting is not set, hence the provider script
            will fail to work, thus the provider should be considered disabled.
        */
        $conf = array();
        foreach ($providerRequirements[$provider] as $req) {
            $conf[] = "auth/provider/{$provider}/{$req}";
        }
        if (Config::ifAny($conf, "")) return false;

        return true;
    }

    /*
        Returns a list of all available authentication providers, including
        disabled ones.
    */
    public static function getAllProviders() {
        return array_keys(self::getProviderRequirements());
    }

    /*
        Returns a list of all enabled authentication providers.
    */
    public static function getEnabledProviders() {
        $providers = self::getAllProviders();
        for ($i = 0; $i < count($providers); $i++) {
            if (!self::isProviderEnabled($providers[$i])) {
                unset($providers[$i]);
            }
        }
        return $providers;
    }

    /*
        Gets the User-Agent without version numbers for identifying a specific
        browser type. This string is used to prevent session hijacking by
        limiting the validity of a session to a specific browser. The function
        removes version numbers from the string, meaning browser and system
        updates won't invalidate the session.
    */
    private static function getVersionlessUserAgent($uastring = null) {
        if ($uastring === null) {
            if (!isset($_SERVER["HTTP_USER_AGENT"])) return "";
            $uastring = $_SERVER["HTTP_USER_AGENT"];
        }
        return preg_replace('@/[^ ]+@', "", $uastring);
    }

    /*
        Fetches and decrypts the raw, unauthenticated session array from cookie
        data supplied by the browser. This will be further processed in other
        functions.
    */
    private static function getSession() {
        if (!isset($_COOKIE["session"])) return null;
        $session = $_COOKIE["session"];

        return Security::decryptArray($session, "session");
    }

    /*
        Writes authenticated session and validation data to a cookie. Called
        from the authentication providers when they have passed authentication
        stage III.
    */
    public static function setAuthenticatedSession($id, $expire, $providerIdentity, $suggestedNick) {
        $db = Database::getSparrow();
        $user = $db
            ->from(Database::getTable("user"))
            ->where("id", $id)
            ->select(array("token", "approved"))
            ->one();

        /*
            If there is no token, that means that the user is registering a new
            account on FreeField.
        */
        if ($user === null || count($user) <= 0) {
            /*
                If approval is required by the admins, the account should be
                flagged as "pending approval". The registering user will be
                given the same privileges as anonymous visitors until their
                account has been appoved.
            */
            $approved = !Config::get("security/approval/require")->value();
            /*
                The token is used to invalidate sessions. The cookie array
                contains a "token" value that must match the token value stored
                in the database. If they do not match, the session is considered
                invalid. This makes global session invalidation easy - simply
                generate a new token, and all existing sessions for that user
                will immediately be considered invalid due to the token
                mismatch.
            */
            $token = self::generateUserToken();

            $data = array(
                "id" => $id,
                "provider_id" => $providerIdentity,
                "nick" => $suggestedNick,
                "token" => $token,
                "permission" => Config::get("permissions/default-level")->value(),
                "approved" => ($approved ? 1 : 0)
            );
            $db
                ->from(Database::getTable("user"))
                ->insert($data)
                ->execute();
        } else {
            /*
                If approval is required by the admins, the account should be
                flagged as "pending approval". The user has the same privileges
                as anonymous visitors until their account has been appoved.
                Setting his boolean
            */
            $approved = $user["approved"];
            $token = $user["token"];

            /*
                If the user's provider identity has changed, update the user's
                record in the database.
            */
            if ($user["provider_id"] !== $providerIdentity) {
                $db
                    ->from(Database::getTable("user"))
                    ->where("id", $id)
                    ->update(array("provider_id" => $providerIdentity))
                    ->execute();
            }
        }

        /*
            This is the array that is stored in clients' session cookie. It
            identifies the logged in user, has the current user token as of the
            time of login, and a session expiration date.

            Storing the expiration date in the cookie rather than the creation
            date ensures that the session is valid for the given period of time
            specified by the session length setting in the security settings
            page on the administration pages, even if that value is later
            changed to a shorter duration that would otherwise invalidate it.

            Another way to implement this would be to store the creation date
            instead of the expiry date. This would cause all sessions that were
            created before the oldest allowed date to be invalid if the session
            length is changed to a shorter period of time. It would also cause
            problems with "dormant sessions" which are currently invalid because
            they have expired, but would suddenly become valid again if the
            session length is later updated to a longer time frame.

            The method used (expiry date vs creation date) might change or
            become changeable by administrators in a later update.
        */
        $session = array(
            "id" => $id,
            "token" => $token,
            "expire" => time() + $expire
        );

        /*
            As an additional security feature, sessions can be restricted to
            only be valid for a particular user-agent or language. This prevents
            session hijacking attacks where an attacker steals the session
            cookie and uses it on a machine running a different browser or
            system language.
        */
        switch (Config::get("security/validate-ua")->value()) {
            case "lenient":
                $session["http-ua"] = self::getVersionlessUserAgent();
                break;
            case "strict":
                $session["http-ua"] = isset($_SERVER["HTTP_USER_AGENT"])
                                      ? $_SERVER["HTTP_USER_AGENT"]
                                      : "";
                break;
        }
        if (Config::get("security/validate-lang")->value()) {
            $session["http-lang"] = isset($_SERVER["HTTP_ACCEPT_LANGUAGE"])
                                    ? $_SERVER["HTTP_ACCEPT_LANGUAGE"]
                                    : "";
        }

        self::setSession($session, $expire);
        return $approved;
    }

    /*
        Each user on FreeField has a token generated and stored in the database
        that is used for session validation. The token is stored in the session
        cookies for the user whenever they authenticate. If there is a mismatch
        between the token stored in the cookie and the one stored in the
        database, the session is considered invalid. This allows invalidating
        all of a user's sessions, logging out the user from all devices, simply
        by changing the token in the database. The user would have to
        reauthentiate to get a session with a valid token.
    */
    public static function generateUserToken() {
        return substr(base64_encode(openssl_random_pseudo_bytes(32)), 0, 32);
    }

    /*
        This function is the opposite of `getCookie()`. It takes a session data
        array, encrypts it, and puts it in a cookie on the client's browser.
    */
    private static function setSession($data, $expire) {
        $session = Security::encryptArray($data, "session");
        setcookie("session", $session, time() + $expire, "/");
    }

    /*
        Finds a user with the given user ID in the database and constructs a
        `User` instance for extracting information about that user.
    */
    public static function getUser($id) {
        $db = Database::getSparrow();
        $userdata = $db
            ->from(Database::getTable("user"))
            ->where("id", $id)
            ->leftJoin(Database::getTable("group"), array(
                Database::getTable("group").".level" => Database::getTable("user").".permission"
             ))
            ->one();

        return new User($userdata);
    }

    /*
        Fetches a list of all registered users from the database and returns an
        array of `User` instances used for extracting information about these
        users.
    */
    public static function listUsers() {
        $db = Database::getSparrow();
        $userdata = $db
            ->from(Database::getTable("user"))
            ->leftJoin(Database::getTable("group"), array(
                Database::getTable("group").".level" => Database::getTable("user").".permission"
             ))
            ->many();

        $users = array();
        foreach ($userdata as $data) {
            $users[] = new User($data);
        }
        return $users;
    }

    /*
        Authenticates the current cookie session data against the user database.
    */
    public static function getCurrentUser() {
        /*
            To avoid repeat database lookups resulting from repeatedly calling
            this function, we'll check if the results have been cached first,
            and if so, return the cached user.
        */
        if (self::$authSessionCache !== null) return self::$authSessionCache;

        /*
            Get the session cookie. If there is no session cookie, or the
            session has expired, the current user is `null` user (i.e.
            unauthenticated).
        */
        $session = self::getSession();
        if ($session === null) return self::setReturnUser(new User(null));
        if ($session["expire"] < time()) return self::setReturnUser(new User(null));

        /*
            Do additional validation of the user agent and/or browser language
            if the site admins have requested this for additional session
            security.
        */
        $selectors = array();
        switch (Config::get("security/validate-ua")->value()) {
            case "lenient":
                $selectors["http-ua"] = self::getVersionlessUserAgent();
                break;
            case "strict":
                $selectors["http-ua"] = isset($_SERVER["HTTP_USER_AGENT"])
                                      ? $_SERVER["HTTP_USER_AGENT"]
                                      : "";
                break;
        }
        if (Config::get("security/validate-lang")->value()) {
            $selectors["http-lang"] = isset($_SERVER["HTTP_ACCEPT_LANGUAGE"])
                                      ? $_SERVER["HTTP_ACCEPT_LANGUAGE"]
                                      : "";
        }

        /*
            Fetch the user from the server. If the user doesn't have the token
            specified in the session array, this will return `null`.
        */
        $db = Database::getSparrow();
        $userdata = $db
            ->from(Database::getTable("user"))
            ->where("id", $session["id"])
            ->where("token", $session["token"])
            ->leftJoin(Database::getTable("group"), array(
                Database::getTable("group").".level" => Database::getTable("user").".permission"
             ))
            ->one();

        foreach ($selectors as $selector => $expectedValue) {
            if ($session[$selector] != $expectedValue) {
                if (Config::get("security/selector-canary")->value()) {
                    if ($userdata !== null) {
                        /*
                            Session hijack canary triggered. Invalidate the
                            token stored in the database to sign out the user
                            globally.
                        */
                        $token = self::generateUserToken();
                        $data = array(
                            "token" => $token
                        );
                        $db
                            ->from(Database::getTable("user"))
                            ->where("id", $session["id"])
                            ->update($data)
                            ->execute();
                    }
                }
                return self::setReturnUser(new User(null));
            }
        }

        /*
            Create a `User` instance, cache it for future lookups, and return
            it.
        */
        return self::setReturnUser(new User($userdata));
    }

    /*
        This function takes an input `User` instance, assigns it to the
        authenticated user object cache, and returns the same instance. This is
        used so that in `getCurrentUser()`, we can write:

            return self::setReturnUser(new User($userdata));

        rather than:

            $user = new User($userdata);
            self::$authSessionCache = $user;
            return $user;
    */
    private static function setReturnUser($user) {
        self::$authSessionCache = $user;
        return $user;
    }

    /*
        Decrypts a user ID encrypted using `User::getEncryptedUserID()`.
    */
    public static function getDecryptedUserID($data) {
        $array = Security::decryptArray($data, "id-only");
        if ($array === null || $array === false) return null;
        return $array["id"];
    }

    /*
        Returns whether or not the user is logged in. If the current user is the
        `null` user (i.e. unauthenticated), the `User::exists()` function always
        returns false, while it always returns true if the corresponding user is
        valid and signed in with a session that has not expired.
    */
    public static function isAuthenticated() {
        return self::getCurrentUser()->exists();
    }

    /*
        Get a list of available groups from the database.

        Each group is stored in a database with the following structure:

          - `group_id` INT
          - `level` SMALLINT
          - `label` VARCHAR(64)
          - `color` CHAR(6)

        That same structure is the structure returned by this function.
    */
    public static function listGroups() {
        /*
            To avoid repeat database lookups resulting from repeatedly calling
            this function, we'll check if the results have been cached first,
            and if so, return the cached groups list.
        */
        if (self::$groupsCache !== null) return self::$groupsCache;

        $db = Database::getSparrow();
        $perms = $db
            ->from(Database::getTable("group"))
            ->many();

        /*
            The groups list is sorted in order of descending permission levels.
            This means that higher ranked groups appear first in this array.
        */
        usort($perms, function($a, $b) {
            if ($a["level"] == $b["level"]) return 0;
            return $a["level"] > $b["level"] ? -1 : 1;
        });

        /*
            Cache the groups list for future lookups.
        */
        self::$groupsCache = $perms;
        return $perms;
    }

    /*
        Get an HTML string representing the given group. It shows the name of
        the group and its permission level and color.
    */
    public static function getGroupHTML($level) {
        $group = null;

        if (self::$groupsCache !== null) {
            /*
                First, attempt to find the group in the groups cache.
            */
            foreach (self::$groupsCache as $groupItem) {
                if ($groupItem["level"] == $level) {
                    $group = $groupItem;
                    break;
                }
            }
        } else {
            /*
                If the groups cache is empty, look up the group in the database.
            */
            $db = Database::getSparrow();
            $perms = $db
                ->from(Database::getTable("group"))
                ->where("level", $level)
                ->one();

            if ($perms !== null) $group = $perms;
        }
        /*
            If group not found, return empty string.
        */
        if ($group === null) return "";

        return '<span'.
                    (
                        $group["color"] === null
                        ? ''
                        : ' style="color: #'.$group["color"].';"'
                    ).
                    '>'.
                    $group["level"].
                    " - ".
                    self::resolvePermissionLabelI18N($group["label"]).
                '</span>';
    }

    /*
        Groups may have label names containing I18N tokens. This enables
        FreeField to use a single label string for all supported languages. For
        example, the group with the label "Administrator" has the name/label
        "{i18n:group.level.admin}". This will display the group with the label
        "Administrators" to English users and whatever the user's localization
        of "Administrators" is if they use a different language. If the
        name/label of the group was configured as the string "Administrators"
        instead, the group would be named the English word "Administrators"
        regardless of the localization used by visiting users. The format of
        the permission label I18N token replacement strings is:

            {i18n:<i18n_token>}

        This means that e.g. "{i18n:group.level.admin}" would be substituted
        with the localization found for the I18N key "group.level.admin" for the
        current language in the localization files.
    */
    public static function resolvePermissionLabelI18N($label) {
        if (substr($label, 0, 6) == "{i18n:" && substr($label, -1, 1) == "}") {
            __require("i18n");
            $query = substr($label, 6, -1);
            return I18N::resolve($query);
        } else {
            return $label;
        }
    }

    /*
        This is a wrapper for `resolvePermissionLabelI18N()` which ensures that
        its output is suitable for outputting directly into an HTML document. It
        escapes special characters to avoid XSS attacks.
    */
    public static function resolvePermissionLabelI18NHTML($label) {
        return htmlspecialchars(self::resolvePermissionLabelI18N($label), ENT_QUOTES);
    }

    /*
        Returns a <select> element that can be used to select a group on the
        administration pages. This function is called from
        `PermissionOption::getControl()` in /includes/config/types.php. It takes
        parameters for the HTML attributes to apply to the <select> element, as
        well as the current permission level selected. The select box will have
        the current level pre-selected.
    */
    public static function getPermissionSelector($name = null, $id = null, $selectedLevel = 0) {
        $user = self::getCurrentUser();
        $perms = self::listGroups();
        $opts = "";

        // Current group
        $curperm = null;

        /*
            Loop over all available groups and check if its permission level
            equals the currently selected level for the control. Add the group
            to the options list as an HTML node. The current group, if found, is
            set aside, as we'll be adding a separate entry in the drop-down for
            that group in addition to its entry in the list of all groups.
        */
        foreach ($perms as $perm) {
            if ($perm["level"] == $selectedLevel) $curperm = $perm;
            $opts .= '<option value="'.$perm["level"].'"'.
                              ($perm["color"] !== null ? ' style="color: #'.$perm["color"].'"' : '').
                              ($user->canChangeAtPermission($perm["level"]) ? '' : ' disabled').'>'.
                                    $perm["level"].
                                    ' - '.
                                    self::resolvePermissionLabelI18NHTML($perm["label"]).
                     '</option>';
        }
        /*
            Add the currently selected group to a separate <optgroup> labeled
            "Current group" at the top of the drop-down. If the currently
            selected group doesn't exist (it has been removed), a temporary
            group is created labeled "Unknown" that will retain the permission
            level currently selected, even though no group for it exists in the
            database.
        */
        $curopt = '<option value="'.$selectedLevel.'" selected>'.
                        $selectedLevel.
                        ' - '.
                        self::resolvePermissionLabelI18NHTML(
                            (
                                $curperm === null
                                ? "{i18n:group.level.unknown}"
                                : $curperm["label"]
                            )
                        ).
                  '</option>';

        return '<select'.($name !== null ? ' name="'.$name.'"' : '').
                         ($id !== null ? ' id="'.$id.'"' : '').
                         ($user->canChangeAtPermission($selectedLevel) ? '' : ' disabled').'>
                                <optgroup label="'.I18N::resolveHTML("group.selector.current").'">
                                    '.$curopt.'
                                </optgroup>
                                <optgroup label="'.I18N::resolveHTML("group.selector.available").'">
                                    '.$opts.'
                                </optgroup>
                </select>';
    }
}

/*
    This class contains functions to get information about a particular user. It
    is constructed from and returned by `Auth::getUser()`.
*/
class User {
    /*
        User data array passed from `Auth::getUser()`.
    */
    private $data = null;

    /*
        Color of anonymous users.
    */
    private static $anonColor = null;

    function __construct($userdata) {
        $this->data = $userdata;
    }

    /*
        Gets whether or not the user exists. Anonymous users and users with an
        invalid session will have `null` assigned to `$this->data`. Therefore,
        we can just check whether that variable is null (and whether the user
        array actually contains any data) to see if the user exists.
    */
    public function exists() {
        return $this->data !== null &&
               count($this->data) > 0 &&
               strlen($this->data["id"]) > 0 &&
               $this->data["provider_id"] !== null;
    }

    /*
        Gets whether or not the user existed, but is now deleted. These users
        have a valid ID, but a `null` provider identity. This is used to
        identify user objects created from the POI list (i.e. from
        /includes/lib/geo.php).
    */
    public function isLikelyDeletedUser() {
        return $this->data !== null &&
               count($this->data) > 0 &&
               strlen($this->data["id"]) > 0 &&
               $this->data["provider_id"] === null;
    }

    /*
        Gets the nickname of the current user. This function is not HTML safe
        and may result in XSS attacks if not properly filtered before being
        output to a page.
    */
    public function getNickname() {
        if (!$this->exists()) {
            if ($this->isLikelyDeletedUser()) {
                return "<DeletedUser>";
            } else {
                return "<Anonymous>";
            }
        }
        return $this->data["nick"];
    }

    /*
        An HTML safe version of `getNickname()`. This function additionaly
        styles the nickname with the color of the group that the user is a
        member of.
    */
    public function getNicknameHTML() {
        //if (!$this->exists()) return htmlspecialchars($this->getNickname(), ENT_QUOTES);
        $color = self::getColor();
        return '<span'.($color !== null ? ' style="color: #'.$color.';"' : '').'>'.
                    htmlspecialchars(self::getNickname(), ENT_QUOTES).
               '</span>';
    }

    /*
        Returns the provider identity (human readable ID as provided by the
        user's authentication provider) of the user. This can be e.g.
        "Username#1234" if the user is authenticated with Discord. This function
        is not HTML safe and may result in XSS attacks if not properly filtered
        before being output to a page.
    */
    public function getProviderIdentity() {
        if (!$this->exists()) return $this->getNickname();
        return $this->data["provider_id"];
    }

    /*
        An HTML safe version of `getProviderIdentity()`. This function
        additionally prepends the logo of the authentication provider used by
        the user to the provider identity.
    */
    public function getProviderIdentityHTML() {
        if (!$this->exists()) return htmlspecialchars($this->getProviderIdentity(), ENT_QUOTES);
        $providerIcons = array(
            "discord" => "discord",
            "telegram" => "telegram-plane"
        );
        return '<span>
                    <i class="
                        auth-provider-'.$this->getProvider().'
                        fab
                        fa-'.$providerIcons[$this->getProvider()].'">
                    </i> '.htmlspecialchars($this->getProviderIdentity(), ENT_QUOTES).'
                </span>';
    }

    /*
        Gets the ID of the user in the form <provider>:<id>, where `provider` is
        the authentication provider used by the user (e.g. "discord") and `id`
        is the internal ID of the user at the provider. Note that this ID is not
        the same as the provider identity - whereas the provider identity is
        usually the username of the user, the ID is a unique, permanent
        identifier internally used by the provider to identify users, and does
        not change even if the provider identity changes.
    */
    public function getUserID() {
        if (!$this->exists()) return null;
        return $this->data["id"];
    }

    /*
        Returns the ID of the user as returned by `getUserID()`, encrypted with
        the `getIdOnlyKey()` from AuthKeys.
    */
    public function getEncryptedUserID() {
        if (!$this->exists()) return null;
        $data = array(
            "id" => $this->data["id"]
        );
        return Security::encryptArray($data, "id-only");
    }

    /*
        Gets the current group membership of the user as a numerical permission
        level value.
    */
    public function getPermissionLevel() {
        /*
            Anonymous/unauthenticated/unapproved users default to 0.
        */
        if (!$this->exists() || !$this->isApproved()) return 0;

        $perm = $this->data["permission"];

        /*
            Group permission levels may temporarily be set to 1000 higher than
            their intended value while the permission level of a group is being
            changed by an administrator. This is done to prevent collisions and
            deadlocks when updating the database, but could theoretically cause
            a privilege escalation attack vector while this update is being
            processed. To prevent this, permission values higher than 1000 are
            reset to their original value, so the permission level of the user
            in practice stays the same the whole time.

            More information on why this is necessary is commented in detail in
            /admin/apply-groups.php.
        */
        return $perm > 1000 ? $perm - 1000 : $perm;
    }

    /*
        Gets the color of the group this user is a member of.
    */
    public function getColor() {
        if (!$this->exists()) {
            if ($this->isLikelyDeletedUser()) {
                return null;
            }
            /*
                If the user does not exist, get the anonymous user color.
            */
            if (self::$anonColor !== null) return self::$anonColor;

            $db = Database::getSparrow();
            self::$anonColor = $db
                ->from(Database::getTable("group"))
                ->where("level", 0)
                ->value("color");

            return self::$anonColor;
        }
        return $this->data["color"];
    }

    /*
        Gets the date of the first login from this user in "YYYY-mm-dd HH:ii:ss"
        format.
    */
    public function getRegistrationDate() {
        if (!$this->exists()) return null;
        return $this->data["user_signup"];
    }

    /*
        Gets the authentication provider used by this user.
    */
    public function getProvider() {
        if (!$this->exists()) return null;
        if (strpos($this->data["id"], ":") !== false) {
            return substr($this->data["id"], 0, strpos($this->data["id"], ":"));
        } else {
            return null;
        }
    }

    /*
        Checks whether the user has been approved by an administrator. This
        defaults to false for new members if the site requires manual user
        account approval, and true otherwise.
    */
    public function isApproved() {
        return $this->data["approved"];
    }

    public function canAccessAdminPages() {
        $domains = Config::listDomains();

        foreach ($domains as $domain => $appearanceOptions) {
            if ($this->hasPermission("admin/{$domain}/general")) return true;
        }

        return false;
    }

    /*
        Checks whether the user has the given permission. Example:
            "admin/security/general"
    */
    public function hasPermission($permission) {
        if (!$this->exists()) {
            // TODO: Permission overries

            /*$explperms = explode(",", $this->data["overrides"]);
            foreach ($explperms as $perm) {
                if (substr($perm, 1) == $permission) {
                    if (substr($perm, 0, 1) == "+") return true;
                    if (substr($perm, 0, 1) == "-") return false;
                }
            }*/
        }

        /*
            Get the current permission level of the user.
        */
        $userperm = (
            $this->data === null || !self::isApproved()
            ? 0
            : $this->getPermissionLevel()
        );

        $perm = Config::get("permissions/level/{$permission}")->value();
        return $userperm >= $perm;
    }

    /*
        Checks whether the current user is authorized to make changes to an
        object that requires the given permission level. E.g. if the current
        user is permission level 80, and they try to change a group or user's
        permission level to 120, this function is called with 120 as its
        argument, and will return false, since it is higher than or equal to the
        current user's permission level. This is implemented to prevent
        privilege escalation attacks.
    */
    public function canChangeAtPermission($level) {
        if ($level < $this->getPermissionLevel()) {
            return true;
        }
        /*
            If the user has the "admin/groups/self-manage" permission, they are
            permitted to change the settings of users and groups at the same
            level as themselves. This is to ensure the "site host" group can
            make changes to their own group, since there is no group higher than
            themselves that they can consult for making changes on their behalf.

            If this was not implemented, permission settings currently set to
            the "site host" would not be possible to change to a lower value
            without manually editing the config JSON.
        */
        if (
            $this->hasPermission("admin/groups/self-manage") &&
            $level <= $this->getPermissionLevel()
        ) {
            return true;
        }
        return false;
    }

    /*
        Administrators can manually override the permissions for a single user
        of a group. This function checks whether such an override is in place
        for the given permission.
    */
    public function hasExplicitRights($permission) {
        // TODO: Implement
        /*if (!$this->exists()) return false;
        $explperms = explode(",", $this->data["overrides"]);
        foreach ($explperms as $perm) {
            if (substr($perm, 1) == $permission) return true;
        }*/
        return false;
    }
}

?>
