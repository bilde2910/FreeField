<?php
/*
    FreeField uses a module system to separate various branches of functionality
    into library files that can then be included using `include_once`. In order
    to simplify inclusion and keep file paths mostly in one single place, there
    exists this /includes/lib/global.php script that is loaded on every PHP page
    that requires one of the files in /vendor or /includes/lib.

    The files in question would call `require_once("path/to/global.php")` and
    then call the proper module using `__require()`.
*/

const FF_VERSION = "0.99.1-dev";

function __require($require) {
    switch ($require) {
        /*
            Manages saving, loading, querying, parsing and validation of the
            configuration file.
        */
        case "config":
            include_once(__DIR__."/config.php");
            break;

        /*
            Parses and manages icon packs and handles URL lookups for those.
        */
        case "theme":
            include_once(__DIR__."/theme.php");
            break;

        /*
            Handles internationalization and localization of all user-visible
            strings.
        */
        case "i18n":
            include_once(__DIR__."/i18n.php");
            break;

        /*
            Handles groups, users, authentication and permissions.
        */
        case "auth":
            include_once(__DIR__."/auth.php");
            break;

        /*
            Manages POIs and provides geo-related functions.
        */
        case "geo":
            include_once(__DIR__."/geo.php");
            break;

        /*
            Manages all database access.
        */
        case "db":
            include_once(__DIR__."/db.php");
            break;

        /*
            Provides a framework for XHR scripts.
        */
        case "xhr":
            include_once(__DIR__."/xhr.php");
            break;

        /*
            Lists all research objectives and rewards and more or less
            everything related to them, including I18N, parameters, validation
            and more.
        */
        case "research":
            include_once(__DIR__."/research.php");
            break;

        /*
            Loads Composer libraries.
        */
        case "vendor":
            require_once(__DIR__."/../../vendor/autoload.php");
            break;

        /*
            Loads Sparrow specifically. Used in /includes/lib/db.php.
        */
        case "vendor/sparrow":
            include_once(__DIR__."./../../vendor/mikecao/sparrow/sparrow.php");
            break;
    }
}

?>
