<?php
/*
    A POI is being reported as evil.
*/
if (!$currentUser->hasPermission("report-evil")) {
    XHR::exitWith(403, array("reason" => "access_denied"));
}

/*
    When the user enters a body payload for webhooks, they may choose to use
    substitution tokens, such as <%COORDS%> or <%POI%>. These should be replaced
    with the proper dynamic values before the webhook payload is posted to the
    target URL.

    This function accepts a `$token` name and its `$args` arguments, along with
    a `Theme` instance `$theme` and `$spTheme` representing the icon set and
    species set selected for the webhook, and `$useSpecies`=true/false whether
    species icons should be displayed if possible.
*/
function replaceWebhookFields($tokenName, $tokenArgs) {
    __require("geo");

    /*
        Attempt to perform the replacement.
    */
    $replacement = null;
    switch (strtoupper($tokenName)) {
        /*
            Please consult the documentation for information about each of these
            substitution tokens.
        */

        case "MINUTES_REMAIN":
            // <%MINUTES_REMAIN%>
            $replacement = floor(POI::EVIL_DURATION / 60);
            break;
    }
    return $replacement;
}

/*
    Obtain and lock a timestamp of when research was submitted by the user. This
    is required because multiple webhooks may be triggered, and if one webhook
    takes a long time to execute, there is a risk that the evilness would be
    reported with different timestamps for each triggered webhook.
    `$reportedTime` is set once the evil status is updated, and then re-used for
    each webhook, preventing this from happening.
*/
$reportedTime = time();

$id = determinePOI($patchdata);
if ($id === false) {
    XHR::exitWith(400, array("reason" => "missing_fields"));
} elseif (count($id) == 0) {
    XHR::exitWith(400, array("reason" => "no_poi_candidates"));
} elseif (count($id) > 1) {
    XHR::exitWith(400, array("reason" => "poi_ambiguous", "candidates" => $id));
} else {
    $id = $id[0];
}

/*
    Validity is verified from here on.

    Create a database update array.
*/
$data = array(
    "updated_by" => $currentUser->getUserID(),
    "last_updated" => date("Y-m-d H:i:s"),
    "evil_reported" => $patchdata["set_evil"] == "true"
                       ? date("Y-m-d H:i:s") : null
);

try {
    $db = Database::connect();
    $db
        ->from("poi")
        ->where("id", $id)
        ->update($data)
        ->execute();

    /*
        Re-fetch the newly created POI from the database. The information here
        is used to trigger webhooks for field research updates.
    */
    $poi = Geo::getPOI($id);

} catch (Exception $e) {
    XHR::exitWith(500, array("reason" => "database_error"));
}

/*
    Call webhooks if the POI has become evil.
*/
if ($patchdata["set_evil"] == "true") {
    __require("config");

    /*
        Get a list of all webhooks and iterate over them to check eligibility of
        submissions.
    */
    $hooks = Config::getRaw("webhooks");
    if ($hooks === null) $hooks = array();
    foreach ($hooks as $hook) {
        if (!$hook["active"]) continue;
        if ($hook["for"] !== "evil") continue;

        /*
            Check that the POI is within the geofence of the webhook.
        */
        if (isset($hook["geofence"])) {
            if (!$poi->isWithinGeofence(Geo::getGeofence($hook["geofence"]))) {
                continue;
            }
        }

        /*
            Configure I18N with the language of the webhook.
        */
        __require("i18n");
        I18N::changeLanguage($hook["language"]);

        /*
            Post the webhook.
        */
        __require("http");
        HTTP::postWebhook($hook, array(
            "cu" => $currentUser,
            "pd" => $poi,
            "rt" => $reportedTime,
        ), function($token, $args, $data) {
            $repl = HTTP::replaceCommonWebhookFields(
                $token, $args
            );
            if ($repl == null) $repl = HTTP::replaceWebhookFieldsForReport(
                $token, $args, $data["cu"], $data["rt"]
            );
            if ($repl == null) $repl = HTTP::replaceWebhookFieldsForPOI(
                $token, $args, $data["pd"]
            );
            if ($repl == null) $repl = replaceWebhookFields(
                $token, $args
            );
            return $repl;
        });
    }
}

XHR::exitWith(204, null);
?>
