<?php
/*
    This file acts as a proxy for the site favicon. The favicon file is stored
    in the /includes/userdata/files directory as themes.favicon.<ext>.
*/

require_once("../includes/lib/global.php");
__require("config");

Config::get("themes/meta/favicon")->value()->outputWithCaching();

exit;

?>
