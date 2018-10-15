<?php
/*
    This file is called by /admin/install-update.php before version upgrades are
    performed. It performs checks to ensure that the target update version of
    FreeField has all the required dependencies to work.
*/

class PreUpgrade {
    /*
        Checks that the required dependencies for this version are fulfilled.
        Returns `true` if requirements are fulfilled, `false` otherwise.
    */
    public static function verifyDependencies($fromVersion = null) {
        /*
            cURL is required for authentication and updates.
        */
        echo "Checking for curl...";
        if (function_exists("curl_version")) {
            echo " ok\n";
        } else {
            echo " fail\n";
            return false;
        }

        /*
            gd is optional, but required for QR code generation for user
            approval.
        */
        echo "Checking for gd...";
        if (extension_loaded("gd")) {
            echo " ok\n";
        } else {
            echo " fail\n";
        }

        /*
            HTTPS is optional, but required for PWA and geolocation.
        */
        echo "Checking for HTTPS...";
        if (
            (
                !empty($_SERVER["HTTPS"]) &&
                $_SERVER["HTTPS"] !== "off"
            ) || (
                !empty($_SERVER["HTTP_X_FORWARDED_PROTO"]) &&
                strtolower($_SERVER["HTTP_X_FORWARDED_PROTO"]) == "https"
            ) ||
            $_SERVER["SERVER_PORT"] == 443
        ) {
            echo " ok\n";
        } else {
            echo " fail\n";
        }

        /*
            Early alpha versions of FreeField have a serious bug in their self-
            updating script that causes updates to fail and the installation to
            be partially deleted when performing an upgrade. If the user is
            upgrading from such a version, we should halt the installation
            process and prompt the user to install the update manually.
            Continuing the update would result in FreeField breaking.

            The older versions can be identified in that they do not pass a
            value for `$fromVersion` to this function. The updates bug was
            patched in v1.0-alpha.5, which is the first version that also sends
            the installed version number to this function. Thus, if the
            `$fromVersion` is null, the current installation is vulnerable to
            this bug.
        */
        echo "Determining if updater is broken...";
        if ($fromVersion === null) {
            echo "\n";
            echo <<<__END_STRING__
################################# FATAL ERROR ##################################
You are upgrading from a FreeField version with a broken auto-updater script.
FreeField versions v1.0-alpha.4 and earlier have a bug that will cause your
FreeField instance to be partially deleted during the update process without
completing the upgrade.

In order to protect your installation from destruction, this update has been
forcibly aborted. You are encouraged to download your target version manually
from GitHub and then overwriting your FreeField installation with the files from
the updated release. The auto-updater script has been patched and is safe to use
in versions v1.0-alpha.5 and above, though versions prior to that release must
be manually upgraded to a safe version first by following these instructions.

Your installation has not been upgraded and no changes have been made to your
installation. You may safely return to FreeField.
################################################################################
__END_STRING__;
            echo "\n";
            return false;
        } else {
            echo " no\n";
        }

        return true;
    }
}

?>
