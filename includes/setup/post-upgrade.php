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
                break;
        }
        /*
            Recheck for updates.
        */
        __require("update");
        Update::checkForUpdates();
    }
}

?>
