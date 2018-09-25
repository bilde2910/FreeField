<?php
/*
    This file handles initialization of updates.
*/

require_once("../includes/lib/global.php");
__require("auth");
__require("security");
__require("update");

$returnpath = "./?d=updates";
$pkgMetaPath = __DIR__."/../includes/userdata/updatepkg.json";
$pkgAuthCookie = "update-token";

/*
    As this script is for submission only, only POST is supported. If a user
    tries to GET this page, they should be redirected to the configuration UI
    where they can start the update process.
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
if (!Auth::getCurrentUser()->hasPermission("admin/updates/general")) {
    header("HTTP/1.1 303 See Other");
    header("Location: {$returnpath}");
    exit;
}

/*
    If the user only requested to check for new updates, do that then quit the
    script. Otherwise, assume the user wants to install an update.
*/
if (isset($_POST["check-updates-only"])) {
    Update::checkForUpdates();
    header("HTTP/1.1 303 See Other");
    header("Location: {$returnpath}");
    exit;
}

/*
    Ensure that the user has chosen an update and that they assume
    responsibility for performing it.
*/
if (!isset($_POST["to-version"]) || !isset($_POST["accepted-disclaimer"])) {
    header("HTTP/1.1 303 See Other");
    header("Location: {$returnpath}");
    exit;
}

/*
    Get the requested version and check that it exists in the cache.
*/
$release = Update::getVersionDataFor($_POST["to-version"]);
if ($release === null) {
    header("HTTP/1.1 303 See Other");
    header("Location: {$returnpath}");
    exit;
}

/*
    Save the version data as an update package metadata file and proceed with
    installing the update. Update installation is done in a separate script to
    ensure that components that are part of the old version are not `include`d
    or `require`d when installing the update, as the post installation script
    may require updated versions of these components to complete installation.

    Save an authorization token to the file as well as in a cookie. This token
    is used by /includes/install-update.php to verify that the user who applied
    the update is also the user that proceeds to install it. Saving a randomly
    generated token to a file and a cookie and then comparing them allows us to
    authenticate the user without having to look them up in the database. This
    saves us from having to include the auth and database libraries in the
    installation script.
*/
$token = base64_encode(openssl_random_pseudo_bytes(32));
$data = array(
    "source" => FF_VERSION,
    "version" => $release["version"],
    "tarball" => $release["tgz-url"],
    "token" => $token
);
file_put_contents($pkgMetaPath, json_encode($data, JSON_PRETTY_PRINT));
$cookieUrl = parse_url(Config::getEndpointUri("/admin/"), PHP_URL_PATH);

/*
    Redirect the user to the installation script to initiate and perform the
    version upgrade.
*/
header("HTTP/1.1 307 Temporary Redirect");
setcookie($pkgAuthCookie, $token, 0, $cookieUrl);
header("Location: ./install-update.php");
exit;

?>
