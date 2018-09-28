<?php
/*
    FREEFIELD INSTALLATION STAGE 1

    This script is part of the FreeField installation wizard
    (/admin/install-wizard.php). Stage 1 of the installation performs
    preliminary checks on the environment FreeField will be installed in. It
    then prompts the user to fix any errors or missing dependencies before they
    can proceed with the installation.
*/

$stage = 1;
/*
    We can determine whether or not we're in stage 1 by the existence of the
    configuration file. The configuration file is created by a POST to the
    install wizard page in stage 2. Hence, if the configuration file does not
    exist, and the request method of this page is not POST, we're in stage 1.
*/
if (!file_exists("../includes/userdata/config.json") && $_SERVER["REQUEST_METHOD"] !== "POST") {
    printHead($stage, "POST"); ?>
        <p>
            <?php echo I18N::resolveHTML("install.stage.{$stage}.info"); ?>
        </p>
        <pre><?php
            $r = echoAssert("install.stage.{$stage}.assert.https", false, function() {
                /*
                    Check for HTTPS support. This is highly recommended, but not
                    strictly required. Not enabling HTTPS will result in some
                    information being disabled, such as map geolocation and
                    progressive web apps (reliant on service workers, which in
                    turn require HTTPS).
                */
                return
                    (
                        !empty($_SERVER["HTTPS"]) &&
                        $_SERVER["HTTPS"] !== "off"
                    ) || (
                        !empty($_SERVER["HTTP_X_FORWARDED_PROTO"]) &&
                        strtolower($_SERVER["HTTP_X_FORWARDED_PROTO"]) == "https"
                    ) ||
                    $_SERVER["SERVER_PORT"] == 443;
            });
            $r += echoAssert("install.stage.{$stage}.assert.root_writable", false, function() {
                /*
                    Check whether or not the root directory of FreeField is
                    writable by the HTTP daemon user. Required for in-situ self-
                    updates.
                */
                return is_writable(__DIR__."/../..");
            });
            $r += echoAssert("install.stage.{$stage}.assert.userdata_writable", true, function() {
                /*
                    Check whether or not the /includes/userdata directory is
                    writable by the HTTP daemon user. This is required to store
                    the configuration file and related instance-specific data,
                    and FreeField will not function without this access.
                */
                return !file_exists(__DIR__."/../../userdata") ||
                       is_writable(__DIR__."/../../userdata");
            });
            $r += echoAssert("install.stage.{$stage}.assert.curl_available", true, function() {
                /*
                    Check for cURL extension support. Required for
                    authentication with most authentication provider
                    implementations in FreeField.
                */
                return function_exists("curl_version");
            });
            $r += echoAssert("install.stage.{$stage}.assert.url_fopen_allowed", true, function() {
                /*
                    Check if `fopen()` can be used on URLs. Required for
                    authentication with most authentication provider
                    implementations in FreeField.
                */
                return ini_get("allow_url_fopen") == "1";
            });
            $r += echoAssert("install.stage.{$stage}.assert.gd_available", false, function() {
                /*
                    Check for gd extension availability. Required for QR code
                    user approvals. FreeField will still work with this
                    disabled, albeit with QR code generation disabled.
                */
                return extension_loaded("gd");
            });
            $r += echoAssert("install.stage.{$stage}.assert.openssl_available", true, function() {
                /*
                    Check for OpenSSL extension availability. Required for
                    encryption and secure random byte generation functionality.
                    Vital to the security of FreeField. The software will not
                    work if OpenSSL is not available, and will fail already at
                    stage 2 of the installation.
                */
                return extension_loaded("openssl");
            });
            $r += echoAssert("install.stage.{$stage}.assert.phar_available", false, function() {
                /*
                    Check for PHAR availability. Required for unpacking updates.
                */
                return class_exists("PharData");
            });
        ?></pre>
        <p>
            <?php echo I18N::resolveHTML("install.stage.{$stage}.checks"); ?>
        </p>
        <p>
            <?php echo I18N::resolveHTML("install.stage.{$stage}.next"); ?>
        </p>
        <?php
            if ($r == 0) {
                /*
                    If no critical errors were found, allow the user to proceed.
                */
                printContinueButton();
            }
        ?>
    <?php printTail();
    exit;
}

?>
