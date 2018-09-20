<?php
/*
    This file outputs a complete list of POIs in FreeField in CSV format.
*/

require_once("../includes/lib/global.php");
__require("auth");
__require("db");
__require("geo");
__require("security");
__require("i18n");

$returnpath = "./?d=pois";

/*
    As this script is for retrieval only, only GET is supported. If a user tries
    to POST to this page, they should be redirected to the configuration UI
    where they can make their desired changes.
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
    If the requesting user does not have permission to make changes here, they
    should be kicked out.
*/
if (
    !Auth::getCurrentUser()->hasPermission("admin/pois/general") ||
    !Auth::getCurrentUser()->hasPermission("admin/pois/import")
) {
    header("HTTP/1.1 303 See Other");
    header("Location: {$returnpath}");
    exit;
}

/*
    The `Geo::listPOIs()` function returns an array of `POI` class instances.
    Please refer to /includes/lib/geo.php for the structure of this class.
*/
$poilist = Geo::listPOIs();

/*
    Create a CSV header.
*/
$output = "\""
        . str_replace('"', '""', I18N::resolve(
            "admin.table.pois.preview_table.column.poi_name.name"
          ))
        . '","'
        . str_replace('"', '""', I18N::resolve(
            "admin.table.pois.preview_table.column.latitude.name"
          ))
        . '","'
        . str_replace('"', '""', I18N::resolve(
            "admin.table.pois.preview_table.column.longitude.name"
          ))
        . "\"\n";
/*
    Loop over all POIs in the database and add them to the output.
*/
foreach ($poilist as $poi) {
    $escapedName = str_replace('"', '""', $poi->getName());
    $output .= '"'
             . $escapedName
             . '",'
             . $poi->getLatitude()
             . ','
             . $poi->getLongitude()
             . "\n";
}

/*
    Output the file.
*/
$filename = "FFExportPoi_".Config::get("site/name")->value()."_".date("Y-m-d_H-i-s").".csv";
header("Content-Type: text/csv");
header("Content-Length: ".strlen($output));
header("Digest: SHA-256=".hash("sha256", $output)); // RFC 5843
header("Content-Disposition: attachment; filename=\"{$filename}\"");
header("Expires: ".date("r"));
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
echo $output;

?>
