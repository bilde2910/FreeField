<?php
/*
    This file acts as a proxy for custom icon sets. The icon sets are stored in
    the /includes/userdata/themes directory.
*/

/*
    Ensure that required parameters about the type of icon set, and the path to
    an icon, are present.
*/
if (!isset($_GET["type"]) || !isset($_GET["path"])) {
    header("HTTP/1.1 400 Bad Request");
    exit;
}

$type = $_GET["type"];
$path = $_GET["path"];

/*
    Sanitize input data to prevent path traversal vulnerabilities.
*/
if ($type !== "icons" && $type !== "species") {
    header("HTTP/1.1 404 Not Found");
    exit;
}
if (
    !preg_match('/^[A-Za-z0-9-_\.\/]+$/', $path) ||
    substr($path, 0, 1) == "." ||
    substr($path, 0, 1) == "/" ||
    substr($path, -1, 1) == "." ||
    substr($path, -1, 1) == "/" ||
    strpos($path, "/../") !== false ||
    strpos($path, "/./") !== false ||
    strpos($path, "//") !== false
) {
    header("HTTP/1.1 404 Not Found");
    exit;
}

/*
    Check if the requested file actually exists.
*/
$filePath = "../includes/userdata/themes/{$type}/{$path}";
if (!file_exists($filePath)) {
    header("HTTP/1.1 404 Not Found");
    exit;
}

/*
    Set correct headers and return the file. Some browsers do not render images
    properly if these headers are not set.
*/
header("Content-Type: ".mime_content_type($filePath));
header("Content-Length: ".filesize($filePath));
header("Last-Modified: ".filemtime($filePath));
readfile($filePath);
exit;

?>
