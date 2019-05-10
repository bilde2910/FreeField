<?php
/*
    DELETE request will delete the given POI.
*/
$deletedata = json_decode(file_get_contents("php://input"), true);

if (!$currentUser->hasPermission("admin/pois/general")) {
    XHR::exitWith(403, array("reason" => "access_denied"));
}

/*
    Required fields are the POI ID. Ensure that it is present in the received
    data.
*/
$id = determineArena($deletedata);
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
    Validity is verified from here on.

    Delete the arena.
*/
try {
    $db = Database::connect();
    $db
        ->from("arena")
        ->where("id", $id)
        ->delete()
        ->execute();

} catch (Exception $e) {
    XHR::exitWith(500, array("reason" => "database_error"));
}

XHR::exitWith(204, null);
?>
