<?php
/*
    This library file contains functions relating to API access controls.
*/

__require("config");
__require("db");
__require("security");

class API {
    /*
        Whitelist of permissions for currently implemented API endpoints.
        Permissions not on this list are ignored.
    */
    const AVAILABLE_PERMS = array(
        "access",
        "find-reporter",
        "report-research",
        "overwrite-research",
        "submit-arena",
        "submit-poi",
        "admin/pois/general"
    );

    /*
        Cache for the current authenticated client to prevent unnecessary
        database lookups due to repeat calls to `getCurrentClient()`.
    */
    private static $apiSessionCache = null;

    /*
        Generates an access token for new clients. This token is 64 characters
        long and consists of random letters from the base64 alphabet. 64
        characters is enough to ensure that there are no collisions and that the
        token cannot be guessed.
    */
    public static function generateAPIToken() {
        return substr(base64_encode(openssl_random_pseudo_bytes(64)), 0, 64);
    }

    /*
        Finds a client with the given token in the database and constructs an
        `APIClient` instance for extracting information about that client.
    */
    public static function getClientByToken($token) {
        $db = Database::connect();
        $clientdata = $db
            ->from("api")
            ->where("token", $token)
            ->one();

        return new APIClient($clientdata);
    }

    /*
        Fetches a list of all registered clients from the database and returns
        an array of `APIClient` instances used for extracting information about
        these clients.
    */
    public static function listClients() {
        $db = Database::connect();
        $clientdata = $db
            ->from("api")
            ->many();

        $clients = array();
        foreach ($clientdata as $data) {
            $clients[] = new APIClient($data);
        }
        return $clients;
    }

    /*
        Authenticates the client access token against the client database.
    */
    public static function getCurrentClient() {
        /*
            To avoid repeat database lookups resulting from repeatedly calling
            this function, we'll check if the results have been cached first,
            and if so, return the cached user.
        */
        if (self::$apiSessionCache !== null) return self::$apiSessionCache;

        /*
            Get the access token header. If there is no such header, or it is
            empty, the current client is `null` client (i.e. non-existing).
        */
        if (empty($_SERVER["HTTP_X_ACCESS_TOKEN"])) {
            return self::setReturnClient(new APIClient(null));
        }

        /*
            Fetch the client from the server. If there is no client with the
            given access token, this returns null. Create an appropriate
            `APIClient` instance, cache it for future lookups, and return it.
        */
        $client = self::getClientByToken($_SERVER["HTTP_X_ACCESS_TOKEN"]);

        /*
            Update the "last seen" date of this API client.
        */
        $db = Database::connect();
        $db
            ->from("api")
            ->where("id", $client->getClientID())
            ->update(array("seen" => date("Y-m-d H:i:s")))
            ->execute();

        return self::setReturnClient($client);
    }

    /*
        This function takes an input `APIClient` instance, assigns it to the
        authenticated client object cache, and returns the same instance. This
        is used so that in `getCurrentClient()`, we can write:

            return self::setReturnUser(new APIClient($clientdata));

        rather than:

            $client = new APIClient($clientdata);
            self::$apiSessionCache = $client;
            return $client;
    */
    private static function setReturnClient($client) {
        self::$apiSessionCache = $client;
        return $client;
    }

    /*
        Returns whether or not the connecting client exists. If the current
        client is the `null` client (i.e. non-existing), the
        `APIClient::exists()` function always returns false, while it always
        returns true if the corresponding client is valid and authenticated.
    */
    public static function isAPIClientAuthenticated() {
        return self::getCurrentClient()->exists();
    }
}

/*
    This class contains functions to get information about a particular API
    client. It is constructed from and returned by `API::getClientByToken()`.
*/
class APIClient {
    /*
        Default display color for new API clients.
    */
    const DEFAULT_COLOR = "1F8DD6"; //"5B72A5"; TODO:REMOVE
    /*
        Pseudo-prefix for API clients, used to identify `User` objects as
        wrappers for API client instances.
    */
    const USER_ID_PREFIX = "api:";

    /*
        Client data array passed from `API::getClientByToken()`.
    */
    private $data = null;

    function __construct($clientdata) {
        $this->data = $clientdata;
    }

    /*
        Returns whether or not the API client is a human user. Always returns
        false.
    */
    public function isRealUser() {
        return false;
    }

    /*
        Gets whether or not the client exists. Invalid/non-existing clients will
        have `null` assigned to `$this->data`. Therefore, we can just check
        whether that variable is null (and whether the client data array
        actually contains any data) to see if the client exists.
    */
    public function exists() {
        return $this->data !== null &&
               count($this->data) > 0;
    }

    /*
        Gets the client name of the current user. This function is not HTML safe
        and may result in XSS attacks if not properly filtered before being
        output to a page.
    */
    public function getName() {
        if (!$this->exists()) {
            __require("i18n");
            return I18N::resolve("admin.table.users.api_deleted");
        }
        return $this->data["name"];
    }

    /*
        Alternate name for `getName()` required by /api/poi.php.
    */
    public function getNickname() {
        return $this->getName();
    }

    /*
        An HTML safe version of `getName()`. This function additionally styles
        the name with the color assigned to the API client.
    */
    public function getNameHTML() {
        if (!$this->exists()) return htmlspecialchars($this->getName(), ENT_QUOTES);
        return '<span style="color: #'.$this->data["color"].';">'.
                    htmlspecialchars(self::getName(), ENT_QUOTES).
               '</span>';
    }

    /*
        Gets the permanent ID of the API client.
    */
    public function getClientID() {
        if (!$this->exists()) return null;
        return $this->data["id"];
    }

    /*
        Gets a user ID-like ID string for this API client.
    */
    public function getUserID() {
        if (!$this->exists()) return null;
        return self::USER_ID_PREFIX.$this->data["id"];
    }

    /*
        Gets the access token for the API client.
    */
    public function getToken() {
        if (!$this->exists()) return null;
        return $this->data["token"];
    }

    /*
        Gets the color defined for this API client.
    */
    public function getColor() {
        if (!$this->exists()) return null;
        return $this->data["color"];
    }

    /*
        Gets the date this client was last seen in "YYYY-mm-dd HH:ii:ss" format.
        Returns `null` if the client has never been seen.
    */
    public function getLastSeenDate() {
        if (!$this->exists()) return null;
        return $this->data["seen"];
    }

    /*
        Gets the permission level for this API client. Permission levels are
        used in API clients to ensure that the client cannot make changes to
        users etc. above their own level, potentially resulting in privilege
        escalation attacks. The level is not used to determine individual
        permissions access.
    */
    public function getPermissionLevel() {
        if (!$this->exists()) return 0;
        return $this->data["level"];
    }

    /*
        Returns an array of all permissions granted explicitly to this API
        client.
    */
    public function getPermissionList() {
        if (!$this->exists()) return array();
        $node = json_decode($this->data["access"], true);
        $perms = self::getPermissionsForSubNode("", $node);
        sort($perms);
        return $perms;
    }

    /*
        Permissions are stored as a JSON array in order to save space in the
        database, similar to the structure of the configuration file. Since the
        JSON is arranged as objects with subkeys, we have to iterate deeper into
        the JSON tree structure in order to inflate the permissions list to an
        array of elements. This is done recursively by passing a path prefix and
        each object in the tree through this function to generate an array of
        inflated key paths.
    */
    private static function getPermissionsForSubNode($path, $node) {
        $perms = array();
        /*
            In order to save space in the database, permission keys are
            shortened and stored as objects with subkeys, similar to the
            configuration file. We have to iterate deeper into the JSON tree
            structure in order to enumerate the list of permissions
        */
        foreach ($node as $subKey => $subNode) {
            if (is_array($subNode)) {
                $perms = array_merge(
                    $perms,
                    self::getPermissionsForSubNode("{$path}{$subKey}/", $subNode)
                );
            } elseif ($subNode) {
                $perms[] = $path.$subKey;
            }
        }
        return $perms;
    }

    /*
        This function does the opposite of `getPermissionsForSubNode()`.

        What this does, is it starts at the deepest nesting level of the
        permission in the permissions array, finds the parent array of the
        setting, and adds the permission to that array. Example for
        "admin/pois/general": The `$value` is `1` to indicate that the
        permission is granted. The deepest nested item in the array for that
        permission is "general", as it is the last item in the path. The parent
        of "general" is "admin/pois".

        "admin/pois" is retrieved:

            $parent = array();

        ..and the value of "general" (the next item in the permission's path
        after "admin/pois") is set to `$value`. Note how the value of "general"
        has changed:

            $parent = array(
                "general" => 1
            );

        `$value` is now set to `$parent`, so `$value` becomes the above array.
        The loop now iterates to set "admin/pois" in the array. The parent of
        "admin/pois" (i.e. "admin") is retrieved:

            $parent = array();

        ..and the value of "pois" (the next item in the permission's path after
        "admin") is set to `$value`. Since `$value` is the "pois" array with the
        "general" setting patched to `1`, the entire "pois" array in `$parent`
        is overwritten:

            $parent = array(
                "pois" => array(
                    "general" => 1
                )
            );

        Again, note that the "pois/general" value has changed to reflect the
        update. `$value` is once again set to the value of `$parent` so that
        `$value` now holds the entire updated "admin" array. Next, the loop
        iterates to set "admin" itself. The parent of "admin" (i.e. the root
        array) is retrieved:

            $parent = array(
                "report-research" => 1
            );

        As before, the "admin" key is replaced with the updated "admin" element
        stored in `$value`:

            $parent = array(
                "report-research" => 1
                "admin" => array(
                    "pois" => array(
                        "general" => 1
                    )
                )
            );

        ..and again note that the value of "admin/pois/general" has been
        correctly updated to `1` in this array. `$value` is once again updated
        to the value of `$parent`.

        At this point, we have iterated over all the segments of the permission
        path ("admin", "pois" and "general"), so the loop ends. Since we
        retrieved the root array into `$parent`, `$value` now holds the patched
        root permissions array. We can overwrite the old permissions in
        `$jsonArray` with this updated array, and the permissions array used by
        this script will thus be the updated version where the value of
        "admin/pois/general" is granted.
    */
    public static function jsonizePermissionList($permissions) {
        $jsonArray = array();
        sort($permissions);
        foreach ($permissions as $perm) {
            $value = 1;
            $s = explode("/", $perm);
            for ($i = count($s) - 1; $i >= 0; $i--) {
                /*
                    Loop over the segments and for every iteration, find the
                    parent array directly above the current `$s[$i]`.
                */
                $parent = $jsonArray;
                for ($j = 0; $j < $i; $j++) {
                    if (isset($parent[$s[$j]])) {
                        $parent = $parent[$s[$j]];
                    } else {
                        $parent = array();
                    }
                }

                /*
                    Update the value of `$s[$i]` in the array. Store a copy of
                    this array as the value to assign to the next parent
                    segment.
                */
                $parent[$s[$i]] = $value;
                $value = $parent;
                /*
                    The next iteration finds the next parent above the current
                    parent and replaces the value of the key in that parent
                    which would hold the value of the current parent array with
                    the updated parent array that has the setting change applied
                    to it.
                */
            }
            $jsonArray = $value;
        }
        return json_encode($jsonArray);
    }

    /*
        Checks whether the API client has the given permission. Example:
            "admin/security/general"
    */
    public function hasPermission($permission) {
        if (!$this->exists()) return false;
        return in_array($permission, $this->getPermissionList());
    }

    /*
        Checks whether the API client is authorized to make changes to an object
        that is editable only to users of a certain given permission level. This
        applies e.g. if the client attempts to make changes to a user account or
        group. For example, if the client has permission level 80, and it tries
        to change a group or user's permission level to 120, this function is
        called with 120 as its argument, and will return false, since it is
        higher than the client's permission level. This is implemented to
        prevent rogue API clients taking over control of FreeField.
    */
    public function canChangeAtPermission($level) {
        if (!$this->exists()) return false;
        return $level <= $this->data["level"];
    }
}

?>
