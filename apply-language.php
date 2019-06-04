<?php
/*
    This file handles changes of user language.
*/

require_once("./includes/lib/global.php");
__require("i18n");
__require("security");

$returnpath = "./";

/*
    As this script is for URL navigation only, only GET is supported. If a user
    tries to POST this page, they should be redirected back to the main page.
*/
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
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
    Check if the selected language is available in FreeField. A blank string
    means auto-detection should be used. FreeField will auto-detect when the
    language cookie is missing, so we unset the language cookie in that case.
*/
$selected = !isset($_GET["lang"]) ? "" : $_GET["lang"];
$langs = I18N::getAvailableLanguages();
if (!in_array($selected, $langs)) $selected = "";

/*
    Set/unset the language cookie and redirect.
*/
header("HTTP/1.1 303 See Other");
if ($selected == "") {
    setcookie("language", "", time() - 3600, "/");
} else {
    // Keep the cookie alive for as long as possible.
    setcookie("language", $selected, time() + (86400 * 365 * 10), "/");
}
header("Location: {$returnpath}");

?>
