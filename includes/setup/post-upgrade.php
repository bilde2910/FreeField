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
        require_once(__DIR__."/../lib/global.php");
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
        }
        /*
            Recheck for updates.
        */
        __require("update");
        Update::checkForUpdates();
    }
}

?>
