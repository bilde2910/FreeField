<?php
/*
    A POI is being renamed.
*/
if (!$currentUser->hasPermission("admin/pois/general")) {
    XHR::exitWith(403, array("reason" => "access_denied"));
}

$id = determineArena($patchdata);
if ($id === false) {
    XHR::exitWith(400, array("reason" => "missing_fields"));
} elseif (count($id) == 0) {
    XHR::exitWith(400, array("reason" => "no_arena_candidates"));
} elseif (count($id) > 1) {
    XHR::exitWith(400, array("reason" => "arena_ambiguous", "candidates" => $id));
} else {
    $id = $id[0];
}

/*
    `rename_to` must be a non-empty string.
*/
$newName = strval($patchdata["rename_to"]);
if ($newName == "") {
    XHR::exitWith(400, array("reason" => "missing_fields"));
}

/*
    Validity is verified from here on.

    Create a database update array.
*/
$data = array(
    "updated_by" => $currentUser->getUserID(),
    "last_updated" => date("Y-m-d H:i:s"),
    "name" => $newName
);

try {
    $db = Database::connect();
    $db
        ->from("arena")
        ->where("id", $id)
        ->update($data)
        ->execute();

} catch (Exception $e) {
    XHR::exitWith(500, array("reason" => "database_error"));
}

XHR::exitWith(204, null);
?>
