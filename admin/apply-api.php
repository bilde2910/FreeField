<?php
/*
    This file handles submission of API client registrations from the
    administration interface.
*/

require_once("../includes/lib/global.php");
__require("config");
__require("auth");
__require("db");
__require("security");
__require("api");

$returnpath = "./?d=api";

/*
    As this script is for submission only, only POST is supported. If a user
    tries to GET this page, they should be redirected to the configuration UI
    where they can make their desired changes.
*/
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("HTTP/1.1 303 See Other");
    header("Location: {$returnpath}");
    exit;
}

/*
    Perform CSRF validation.
*/
if (!Security::validateCSRF()) {
    header("HTTP/1.1 303 See Other");
    header("Location: {$returnpath}");
    exit;
}

/*
    If the requesting user does not have permission to make changes here, they
    should be kicked out.
*/
if (!Auth::getCurrentUser()->hasPermission("admin/api/general")) {
    header("HTTP/1.1 303 See Other");
    header("Location: {$returnpath}");
    exit;
}

/*
    The client list we get from /includes/lib/api.php contains a list of clients
    in an indexed array. We'll convert it to an associative array where the
    client ID is the key, to make it easier to fetch the details for a client
    given its ID. This is because the updates POSTed from the browser contains
    changes where the client ID is the identifier for the API clients whose
    settings have changed.

    The `API::listClients()` function returns an array of `APIClient` class
    instances. Please refer to /includes/lib/api.php for the structure of this
    class.
*/
$clientlist = API::listClients();
$clients_assoc = array();

foreach ($clientlist as $client) {
    $clients_assoc[$client->getClientID()] = $client;
}

/*
    Create an array for updates, as well as an array for deletes, to be applied
    in one batch later. `$insertions` is an array containing INSERT arrays
    passed to the SQL server to add new clients.
*/
$insertions = array();
$updates = array();
$deletes = array();

foreach ($_POST as $client => $data) {
    /*
        Ensure that the POST field we're working on now is a client change
        field. These all have field names in the format "a<clientID>". If this
        matches, extract the client ID from the field name.
    */
    if (strlen($client) < 2 || substr($client, 0, 1) !== "a") continue;
    $id = substr($client, 1);

    /*
        New clients have `$_POST` IDs starting with "an_".
    */
    $newClient = substr($client, 1, 1) === "n";

    /*
        If the client is new, create an instance rather than fetching one from
        the clients array. Ensure that all of this client's fields are also in
        the updates array.
    */
    if ($newClient) {
        $updates[$id] = array(
            "name" => "",
            "color" => APIClient::DEFAULT_COLOR,
            "token" => API::generateAPIToken(),
            "access" => json_encode(array()),
            "level" => -1,
            "seen" => NULL
        );
        $clientInstance = new APIClient($updates[$id]);
    } else {
        $clientInstance = $clients_assoc[$id];
    }

    /*
        Users cannot make changes to clients at or above their own permission
        level. Enforce this by matching the current user's permission level
        against that of the client they are changing.
    */
    if (!Auth::getCurrentUser()->canChangeAtPermission($clientInstance->getPermissionLevel())) {
        continue;
    }

    /*
        If client deletion is requested, add it to the deletion queue and do not
        process further changes.
    */
    if (
        isset($data["action"]) &&
        $data["action"] === "delete"
    ) {
        $deletes[] = $id;
        continue;
    }

    /*
        Reset the access token for the API client.
    */
    if ($data["action"] === "reset") {
        $updates[$id]["token"] = API::generateAPIToken();
    }

    /*
        Handle changes to the client parameters, such as name and color. If
        there are changes, they should be added to the updates queue.
    */
    if ($clientInstance->getName() !== $data["name"]) {
        $updates[$id]["name"] = $data["name"];
    }

    /*
        The `color` field has the # sign in front of the RRGGBB hex color code
        selected. Strip this sign out of the color code before saving it to the
        database.
    */
    if ($clientInstance->getColor() !== substr($data["color"], -6)) {
        $updates[$id]["color"] = substr($data["color"], -6);
    }

    /*
        Detect permission changes. Permissions are compared as arrays, where
        each element in the array is a string representing the path to a
        particular permission (e.g. "admin/users/general").
    */
    $oldPerms = $clientInstance->getPermissionList();
    $newPerms = explode(",", $data["access"]);

    /*
        Only allow setting permissions which are implemented in FreeField.
    */
    for ($i = count($newPerms) - 1; $i >= 0; $i--) {
        if (!in_array($newPerms[$i], API::AVAILABLE_PERMS)) {
            unset($newPerms[$i]);
        }
    }
    $newPerms = array_values($newPerms);

    if (count($newPerms) == 1 && $newPerms[0] == "") $newPerms = array();
    if (
        count($oldPerms) != count($newPerms) ||
        !empty(array_diff($oldPerms, $newPerms))
    ) {
        $updates[$id]["access"] = APIClient::jsonizePermissionList($newPerms);
    }

    /*
        If the permission level has changed, validation should be done to ensure
        that the user is not at or above either the current level of the client,
        or the level that the client is being changed to. This is to stop
        privilege escalation attacks. If the user has permission to make the
        change, add the change to the updates queue.
    */
    if (
        isset($data["level"]) &&
        $clientInstance->getPermissionLevel() != $data["level"]
    ) {
        $old = intval($clientInstance->getPermissionLevel());
        $new = intval($data["level"]);
        $max = max($old, $new);
        if (Auth::getCurrentUser()->canChangeAtPermission($max)) {
            $updates[$id]["level"] = $new;
        }
    }

    /*
        New clients should be INSERTed rather than UPDATEd, so we'll remove it
        from the updates array and put it into a separate insertion array.
    */
    if ($newClient) {
        $insertions[] = $updates[$id];
        unset($updates[$id]);
    }
}

$db = Database::connect();
foreach ($updates as $clientid => $update) {
    /*
        Apply the updates queue to the database.
    */
    $db
        ->from("api")
        ->where("id", $clientid)
        ->update($update)
        ->execute();
}

foreach ($insertions as $client) {
    /*
        Apply the insertion queue to the database.
    */
    $db
        ->from("api")
        ->insert($client)
        ->execute();
    /*
        Grab the ID from the database for the newly created client and update
        the `user_id` field of the database to reflect this value.
    */
    $id = $db
        ->from("api")
        ->where("token", $client["token"])
        ->select(array("id"))
        ->one()["id"];
    $db
        ->from("api")
        ->where("id", $id)
        ->update(array("user_id" => APIClient::USER_ID_PREFIX.$id))
        ->execute();
}

/*
    When all other changes are committed, process the deletion queue and remove
    the clients it contains.
*/
foreach ($deletes as $clientid) {
    $db
        ->from("api")
        ->where("id", $clientid)
        ->delete()
        ->execute();
}

header("HTTP/1.1 303 See Other");
header("Location: {$returnpath}");

?>
