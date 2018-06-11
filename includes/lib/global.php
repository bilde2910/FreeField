<?php

function __require($require) {
    switch ($require) {
        case "config":
            require_once(__DIR__."/config.php");
            break;
        case "auth":
            require_once(__DIR__."/auth.php");
            break;
    }
}

?>
