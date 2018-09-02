<?php
/*
    This file generates QR codes for user approval if approval by QR codes is
    enabled in the configuration.
*/

require_once("../includes/lib/global.php");
__require("config");
__require("auth");

/*
    Ensure that QR code approval is enabled and that its precondition (GD
    library being loaded) is satisfied.
*/
if (!Config::get("security/approval/by-qr")) {
    header("HTTP/1.1 501 Not Implemented");
    exit;
}

/*
    If the user isn't logged in, or if they are, but their account is already
    approved, they shouldn't be here.
*/
$user = Auth::getCurrentUser();
if (!$user->exists() || $user->isApproved()) {
    header("HTTP/1.1 400 Bad Request");
    exit;
}

/*
    Output the QR code. An encrypted URL is used to prevent others from knowing
    which user it belongs to if the user shares their code in public.
*/
__require("vendor/phpqrcode");
QRcode::png(Config::getEndpointUri("/admin/approve.php?euid=").urlencode($user->getEncryptedUserID()), false, QR_ECLEVEL_M, 4);

?>
