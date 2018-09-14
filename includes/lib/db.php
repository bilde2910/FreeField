<?php
/*
    This library file contains functions relating to database connections.

    FreeField uses the Sparrow library to manage database connections. Sparrow
    allows accessing MySQL, SQLite and PostgreSQL databases.

    There are three tables in use:

    [ffield_]user
        The table containing a list of all registered users. This table has the
        following columns:

        VARCHAR(64) ìd` PRIMARY
            Contains the ID and authentication provider of each registered user.
            Example: "discord:123456789012345678"

        VARCHAR(64) `provider_id`
            Contains the provider identity of the given user. The provider
            identity is a human-readable string identifying the user by their
            username or other identity on the authentication provider they
            choose to use. Example: "FooBarBaz#1234" or "@FooBarBaz". This
            property is transient and should never be used to uniquely identify
            any particular account in code - use the `id` field instead.

        VARCHAR(64) `nick`
            Contains the nickname used by this user on FreeField. Example:
            "FooBarBaz"

        CHAR(32) `token`
            A unique authentication token generated for each user that is stored
            in the session cookie data for each device the user is logged in to.
            A session is only valid if the token stored in the database matches
            the one stored in the session cookie. This allows an easy means for
            users to invalidate all of their sessions on all devices - simply
            request a new token to be created and saved in the database.
            Existing sessions will no longer have a matching token, hence the
            sessions are automatically invalidated.

        TINYINT(1) `approved`
            A boolean flag on whether or not the account is considered approved,
            if approval of new user accounts is required. If approval is not
            required, new users are considered already approved on account
            creation, and will have this value pre-set to 1.

        SMALLINT `permission`
            A number indicating the permission level of the current user. This
            level number will correspond to a group, but may also not correspond
            to a group if the group in question has been deleted. The value is
            used to calculate whether or not the user has permission to do
            various tasks on FreeField. Example: 80

        TIMESTAMP `user_signup`
            A timestamp indicating when the user's account was first seen on
            this instance of FreeField. There is no record stored for the last
            time the user was active or signed in, since sessions may last for
            years and updating the database every time someone was active for
            any reason on the site would be a lot of wasted `UPDATE` queries for
            little additional benefit for local FreeField administrators.

    [ffield_]group
        The table used to store a list of all groups currently active on
        FreeField. Groups are used to categorize users into different permission
        tiers. This table has the following columns:

        INT `group_id` PRIMARY AUTO_INCREMENT
            A unique numeric ID identifying any particular group. Automatically
            incremented by the database. Used when making changes to groups. For
            all other purposes, `level` is used to identify each group.

        SMALLINT `level` UNIQUE
            The permission level for this group. The permission level is unique
            for every group. It is used to identify the group in other places
            (such as the users table and in the configuration) and is used to
            determine whether the users within the group have permission to do
            various tasks on FreeField. Example: 80

        VARCHAR(64) `label`
            The name of the group. It may contain an I18N token - please see
            comments for `Auth::resolvePermissionLabelI18N()` in
            /includes/lib/auth.php for more information on how those work.
            Example: "Administrator" or "{i18n:group.level.admin}"

        CHAR(6) `color`
            The hexadecimal RGB color code that represents the color used for
            this group and its members. Can be `NULL` if no color is set.

    [ffield_]poi
        The table used to store a list of all POIs and their research tasks. The
        table has the following columns:

        INT `id` PRIMARY AUTO_INCREMENT
            A unique numeric ID identifying any particular POI. Automatically
            incremented by the database. Used when making all kinds of changes
            to the POI and when reporting research to the POI.

        VARCHAR(128) `name`
            The name of the POI. Example: "John Doe Statue"

        DOUBLE `latitude`
            A positive or negative latitude coordinate indicating this POI's
            North-South location. Example: 42.63445

        DOUBLE `longitude`
            A positive or negative longitude coordinate indicating this POI's
            East-West location. Combined with `latitude`, these fields make up
            the location of the POI. Example: -87.12012

        TIMESTAMP `created_on`
            The date and time this POI was added to FreeField.

        VARCHAR(64) `created_by`
            The ID user who added this POI to FreeField. Example:
            "discord:123456789012345678"

        TIMESTAMP `last_updated`
            The date and time of when someone last reported research to this
            POI. This is not ON UPDATE CURRENT_TIMESTAMP because editing other
            properties of the POI, such as its name, would also result in a
            timestamp update.

        VARCHAR(64) `updated_by`
            The ID user who last reported research on this POI. Example:
            "discord:123456789012345678"

        VARCHAR(32) `objective`
            The objective last reported on the POI. Example: "catch_type"

        VARCHAR(128) `obj_params`
            Parameters for the last reported objective in JSON format. Example:
            "{"species":[90],"quantity":2}"

        VARCHAR(32) `reward`
            The reward last reported on the POI. Example: "potion"

        VARCHAR(128) `rew_params`
            Parameters for the last reported objective in JSON format. Example:
            "{"quantity":4}"
*/

__require("config");
__require("vendor/sparrow");

class Database {
    /*
        Returns an initialized instance of Sparrow.
    */
    public static function getSparrow() {
        $db = new Sparrow();

        /*
            For some reason, initializing Sparrow with a connection array does
            not work properly. Therefore, we have to convert the connection to
            a URI.
        */

        if (
            Config::get("database/type")->value() == "sqlite" ||
            Config::get("database/type")->value() == "sqlite3"
        ) {
            $type = Config::get("database/type")->valueURL();
            $database = Config::get("database/database")->valueURL();

            $uri = "{$type}://{$database}";
            $db->setDb($uri);
        } else {
            $type = Config::get("database/type")->valueURL();
            $host = Config::get("database/host")->valueURL();
            $database = Config::get("database/database")->valueURL();
            $user = Config::get("database/username")->valueURL();
            $pass = Config::get("database/password")->valueURL();

            if (Config::get("database/port")->value() > 0) {
                $port = Config::get("database/port")->valueURL();
                // The format of the connection URI is as follows:
                $uri = "{$type}://{$user}:{$pass}@{$host}:{$port}/{$database}";
            } else {
                $uri = "{$type}://{$user}:{$pass}@{$host}/{$database}";
            }

            error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);

            $db->setDb($uri);
        }

        return $db;
    }

    /*
        Returns the full name of the given table (prefix + table name) for use
        when querying specific tables in the database.
    */
    public static function getTable($table) {
        return Config::get("database/table-prefix")->value().$table;
    }
}

?>
