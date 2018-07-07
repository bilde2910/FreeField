<?php

require_once("../includes/lib/global.php");
__require("config");
__require("auth");
__require("db");

$returnpath = "./?d=groups";

if (!Auth::getCurrentUser()->hasPermission("admin/groups/general")) {
    header("HTTP/1.1 303 See Other");
    header("Location: {$returnpath}");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("HTTP/1.1 303 See Other");
    header("Location: {$returnpath}");
    exit;
}

$grouplist = Auth::listPermissionLevels();
$groups_assoc = array();

foreach ($grouplist as $group) {
    $groups_assoc[$group["group_id"]] = $group;
}

$updates = array();
$deletes = array();

$levelchanges = array();

foreach ($_POST as $group => $data) {
    if (strlen($group) < 1 || substr($group, 0, 1) !== "g") continue;
    $gid = substr($group, 1);

    if (!Auth::getCurrentUser()->canChangeAtPermission($groups_assoc[$gid]["level"])) {
        continue;
    }

    if ($data["action"] === "delete") {
        $deletes[] = $gid;
        continue;
    }
    if ($groups_assoc[$gid]["label"] !== $data["label"]) {
        $updates[$gid]["label"] = $data["label"];
    }
    if ($groups_assoc[$gid]["level"] != $data["level"]) {
        // Level changes are applied separately, see below
        $old = intval($groups_assoc[$gid]["level"]);
        $new = intval($data["level"]);
        $max = max($old, $new);
        if (Auth::getCurrentUser()->canChangeAtPermission($max)) {
            $levelchanges[$old] = $new;
        }
    }
    if (isset($data["usecolor"]) && $groups_assoc[$gid]["color"] !== substr($data["color"], -6)) {
        $updates[$gid]["color"] = substr($data["color"], -6);
    }
    if (!isset($data["usecolor"]) && $groups_assoc[$gid]["color"] !== null) {
        $updates[$gid]["color"] = NULL;
    }
}

$db = Database::getSparrow();
foreach ($updates as $groupid => $update) {
    $query = $db
        ->from(Database::getTable("group"))
        ->where("group_id", $groupid)
        ->update($update)
        ->sql();

    $query = str_replace("color=''", "color=NULL", $query);
    $db
        ->sql($query)
        ->execute();
}

/*
    Queries with level changes have to be committed in a single transaction.
    In order to prevent privilege escalation attacks and race conditions, all
    changes related to group level updates and user group membership levels are
    committed at the same time, locking the table until the transaction is
    complete. This is because the permission is set temporarily to +1000 of its
    intended value in order to work around a deadlock issue caused by the way
    SQL handles value swap updates to UNIQUE columns. A more detailed
    explanation:

    Consider the following table:

        Table GROUPS
        +---------------+------------+
        | group         | permission |
        +---------------+------------+
        | User          |        100 |
        | Moderator     |        120 |
        | Administrator |        150 |
        +---------------+------------+

    Consider the case where an admin wants to swap the permission levels of
    Moderator and Administrator such that Moderator becomes 150 and
    Administrator becomes 120.

    The permission level of Moderator cannot be set to 150 directly because
    `permission` is UNIQUE and 150 would conflict with Administrator. Similarly,
    Administrator cannot be changed to 120 because it would conflict with
    Moderator.

    This is a standard deadlock. Either transaction cannot complete before the
    other one has completed because the UNIQUE values conflict with each other.

    The solution to this issue is to set a temporary value for the group
    permission level - the target permission level, +1000. Hence, the order of
    transactions is now as follows:

        Moderator:       120 -> 1150
        Administrator:   150 -> 1120
        Moderator:      1150 ->  150
        Administrator:  1120 ->  120

    Hence, the permission values will properly update to reflect the admin's
    request.

    The second issue that has to be solved is similar. When a permission level
    changes, users that have this permission level should also have their
    permissions updated to reflect this higher level.

    Consider the following table:

        Table USERS
        +---------+------------+
        | user    | permission |
        +---------+------------+
        | Alice   |        120 |
        | Bob     |        150 |
        | Charlie |        100 |
        +---------+------------+

    Alice is a Moderator and should thus have her permission changed to 150.
    Bob is an Administrator and should similarly have his permission changed to
    120. However, issuing sequential UPDATE WHERE `permission` statements for
    these rank changes will result in these users having the same rank. Consider
    the following sequence of transactions:

        UPDATE `users` SET `permission`=150 WHERE `permission`=120;
        UPDATE `users` SET `permission`=120 WHERE `permission`=150;

    This will first change the permission levels of everyone with permission 120
    to permission 150. However, it will also change the permission levels of
    everyone with permission 150 over to permission 120 - including those who
    just got permission 150 from the previous statement.

    This is solved in the same way as updating group levels. Each user is
    initially assigned the proper permission level, with 1000 added. The users
    are then re-assigned to their final permission level. The transaction
    sequence now looks like this:

        UPDATE `users` SET `permission`=1150 WHERE `permission`=120;
        UPDATE `users` SET `permission`=1120 WHERE `permission`=150;
        UPDATE `users` SET `permission`=150 WHERE `permission`=1150;
        UPDATE `users` SET `permission`=120 WHERE `permission`=1120;

    As an additional safeguard against privilege escalation, auth.php will
    subtract 1000 from a user's permission level if the level is over 1000.
    The standard cap for a group permission level is 250, so this will not cause
    conflicts with any custom roles that would have otherwised used real
    permission values over 1000. Care should be taken that this is considered if
    the group permission level cap is ever raised to 1000 or beyond in the
    future for any reason, though as of the time of writing, there is no reason
    for this to ever happen, given that there is no real-world situations in
    which 1000 unique groups would be considered even remotely reasonable.
*/

$queries = array();
foreach ($levelchanges as $old => $new) {
    $query = $db
        ->from(Database::getTable("group"))
        ->where("level", $old)
        ->update(array("level" => ($new + 1000)))
        ->sql();

    $queries[] = $query;
}

foreach ($levelchanges as $old => $new) {
    $query = $db
        ->from(Database::getTable("group"))
        ->where("level", ($new + 1000))
        ->update(array("level" => $new))
        ->sql();

    $queries[] = $query;
}

foreach ($levelchanges as $old => $new) {
    $query = $db
        ->from(Database::getTable("user"))
        ->where("permission", $old)
        ->update(array("permission" => ($new + 1000)))
        ->sql();

    $queries[] = $query;
}

foreach ($levelchanges as $old => $new) {
    $query = $db
        ->from(Database::getTable("user"))
        ->where("permission", ($new + 1000))
        ->update(array("permission" => $new))
        ->sql();

    $queries[] = $query;
}

if (count($queries) > 0) {
    $db
        ->sql("START TRANSACTION")
        ->execute();
    foreach ($queries as $query) {
        $db
            ->sql($query)
            ->execute();
    }
    $db
        ->sql("COMMIT")
        ->execute();
}

/*
    Level change transaction ends here. Proceed with group deletion requests in
    the same way user deletion requests are treated (no special considerations
    need to be taken for groups vs. users).
*/

foreach ($deletes as $groupid) {
    $db
        ->from(Database::getTable("group"))
        ->where("group_id", $groupid)
        ->delete()
        ->execute();
}

header("HTTP/1.1 303 See Other");
header("Location: {$returnpath}");

?>
