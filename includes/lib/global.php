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

const FF_VERSION = "1.0.2";

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
            Enables security functions and handles data encryption and
            decryption.
        */
        case "security":
            require_once(__DIR__."/security.php");
            break;

        /*
            Checks for and installs updates.
        */
        case "update":
            include_once(__DIR__."/update.php");
            break;

        /*
            Parses and manages icon sets and handles URL lookups for those.
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
            Loads OAuth2 library.
        */
        case "vendor/oauth2":
            require_once(__DIR__."/../vendor/PHP-OAuth2/OAuth2/Client.php");
            require_once(__DIR__."/../vendor/PHP-OAuth2/OAuth2/GrantType/IGrantType.php");
            break;

        /*
            Loads Authorization Code OAuth2 method for the OAuth2 library.
        */
        case "vendor/oauth2/authcode":
            require(__DIR__."/../vendor/PHP-OAuth2/OAuth2/GrantType/AuthorizationCode.php");
            break;

        /*
            Loads the PHP QR Code library. This is not obtained via Composer.
        */
        case "vendor/phpqrcode":
            include_once(__DIR__."/../vendor/phpqrcode.php");
            break;

        /*
            Loads the Spyc YAML parser.
        */
        case "vendor/spyc":
            include_once(__DIR__."./../vendor/Spyc.php");
            break;

        /*
            Loads the Parsedown Markdown parser.
        */
        case "vendor/parsedown":
            include_once(__DIR__."./../vendor/Parsedown/Parsedown.php");
            break;
    }
}

?>
