<?php
/*
    This file is called by /admin/install-update.php after an upgrade has been
    performed. It finalizes the upgrade process by setting configuration values,
    updating database tables, etc. to ensure that the updated FreeField instance
    works properly.
*/

class PostUpgrade {
    /*
        Returns the URL relative to /admin/install-update.php that users should
        be redirected to after the upgrade is successful.
    */
    public static function getReturnPath() {
        return "./?d=updates";
    }

    /*
        Performs configuration updates and other steps needed to ensure that the
        updated version of FreeField works properly.
    */
    public static function finalizeUpgrade($fromVersion, $silent = false) {
        /*
            Included library files must be invalidated in the opcache, otherwise
            an older version of the library files will be parsed.
        */
        $libpath = __DIR__."/../lib";
        if (function_exists("opcache_invalidate")) {
            $libs = array_diff(scandir($libpath), array('..', '.'));
            foreach ($libs as $lib) {
                opcache_invalidate("$libpath/$lib");
            }
        }

        require_once("$libpath/global.php");
        __require("config");
        Config::set(array(
            "install/version-compatible" => FF_VERSION
        ), false, false);

        /*
            Add any new configuration options to the config file.
        */
        Config::populateWithDefaults();

        __require("db");
        $db = Database::connect();
        $prefix = Config::get("database/table-prefix")->value();

        /*
            Settings in the configuration that use FileOption have default
            templates stored in /includes/setup/templates/files. Copy these over
            to the /includes/userdata/files directory, where uploaded files for
            these settings are normally stored, if any of the files are missing.
        */
        $sourcePath = __DIR__."/templates/files";
        $targetPath = __DIR__."/../userdata/files";

        $files = array_diff(scandir($sourcePath), array('..', '.'));
        foreach ($files as $file) {
            if (!file_exists("{$targetPath}/{$file}")) {
                if (!$silent) echo "Copying new default file {$file} to userdata...";
                copy("{$sourcePath}/{$file}", "{$targetPath}/{$file}");
                if (!$silent) echo " ok\n";
            }
        }

        /*
            Perform step-by-step upgrades through each released FreeField
            version using a cascading `switch` block, starting at the version
            corresponding to the previously installed version and proceeding
            through all versions released since.
        */
        switch ($fromVersion) {
            case "0.99.1-dev":
            case "1.0-alpha.1":
            case "1.0-alpha.2":
            case "1.0-alpha.3":
                /*
                    Bugfix: Allow Unicode code points beyond 0xFFFD (such as
                    emoji) in any strings.
                */
                if (!$silent) echo "Altering table charsets...";
                $sql = <<<__END_STRING__
ALTER TABLE {$prefix}group CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE {$prefix}poi CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE {$prefix}user CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
__END_STRING__;
                $db->execute($sql);
                if (!$silent) echo " ok\n";

            case "1.0-alpha.4":
            case "1.0-alpha.5":
                /*
                    Bugfix: Empty timezone value breaks core functionality.
                    Default to UTC in those cases.
                */
                if (empty(Config::get("map/updates/tz")->value())) {
                    Config::set(array("map/updates/tz" => "UTC"));
                }
            case "1.0-alpha.6":
            case "1.0-alpha.7":
            case "1.0-alpha.8":
            case "1.0-alpha.9":
            case "1.0-beta.1":
            case "1.0-beta.2":
            case "1.0-rc.1":
            case "1.0-rc.2":
            case "1.0-rc.3":
            case "1.0":
            case "1.0.1":
            case "1.0.2":
            case "1.0.3":
            case "1.0.4":
            case "1.0.5":
            case "1.0.6":
            case "1.0.7":
            case "1.0.8":
                /*
                    Update webhooks to include species icon information.
                */
                if (!$silent) echo "Declaring default species icon sets in existing webhooks...";
                $hooklist = Config::getRaw("webhooks");
                if ($hooklist === null) $hooklist = array();
                for ($i = 0; $i < count($hooklist); $i++) {
                    $hooklist[$i]["species"] = "";
                    $hooklist[$i]["show-species"] = true;
                }
                Config::set(array("webhooks" => $hooklist));
                if (!$silent) echo " ok\n";

            case "1.1-alpha.1":
            case "1.1-alpha.2":
            case "1.1-alpha.3":
            case "1.1-alpha.4":
            case "1.1-alpha.5":
                /*
                    Add table to database for API clients.
                */
                if (!$silent) echo "Adding API clients table to database...";
                $sql = <<<__END_STRING__
CREATE TABLE {$prefix}api (
    id              int(11)         NOT NULL AUTO_INCREMENT,
    user_id         varchar(16)     DEFAULT NULL,
    name            varchar(64)     NOT NULL,
    color           char(6)         NOT NULL,
    token           char(64)        NOT NULL,
    access          varchar(1024)   NOT NULL,
    level           smallint(6)     NOT NULL,
    seen            timestamp       NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY level (user_id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
__END_STRING__;
            $db->execute($sql);
            if (!$silent) echo " ok\n";
        }
        /*
            Recheck for updates.
        */
        __require("update");
        Update::checkForUpdates();
    }
}

?>
