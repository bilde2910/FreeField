<?php

require_once("../includes/lib/global.php");
__require("config");
__require("auth");

// TODO: Kick users out of this page if they don't have admin perms

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("HTTP/1.1 303 See Other");
    header("Location: ./");
    exit;
}

$returnpath = "./";
if (isset($_GET["d"])) {
    $returnpath .= "?d=".$_GET["d"];
}

foreach ($_POST as $key => $value) {
    if (substr($key, 0, 8) === "install/") {
        unset($_POST[$key]);
    }
}

Config::set($_POST, true);

header("HTTP/1.1 303 See Other");
header("Location: {$returnpath}");

?>
