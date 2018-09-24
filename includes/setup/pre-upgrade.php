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
    public static function verifyDependencies() {
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
            (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ||
            $_SERVER["SERVER_PORT"] == 443
        ) {
            echo " ok\n";
        } else {
            echo " fail\n";
        }

        return true;
    }
}

?>
