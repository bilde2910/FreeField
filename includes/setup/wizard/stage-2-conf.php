<?php
/*
    FREEFIELD INSTALLATION STAGE 2

    This script is part of the FreeField installation wizard
    (/admin/install-wizard.php). Stage 2 of the installation generates
    encryption keys for use in FreeField and writes a default configuration
    file.
*/

$stage = 2;
if (!file_exists("../includes/userdata/config.json")) {
    printHead($stage, "GET"); ?>
        <p>
            <?php echo I18N::resolveHTML("install.operation.done"); ?>
        </p>
        <pre><?php
            $r = echoAssert("install.stage.{$stage}.assert.copy_templates", true, function() {
                /*
                    Settings in the configuration that use FileOption have
                    default templates stored in /includes/setup/templates/files.
                    Copy these over to the /includes/userdata/files directory,
                    where uploaded files for these settings are normally stored.
                */
                @mkdir(__DIR__."/../../userdata");
                $sourcePath = __DIR__."/../templates/files";
                $targetPath = __DIR__."/../../userdata/files";
                @mkdir($targetPath);

                $files = array_diff(scandir($sourcePath), array('..', '.'));
                foreach ($files as $file) {
                    copy("{$sourcePath}/{$file}", "{$targetPath}/{$file}");
                }

                return true;
            });
            $r += echoAssert("install.stage.{$stage}.assert.authkeys_written", true, function() {
                /*
                    The AuthKeys file contains encryption keys which are used
                    to encrypt session data, configuration file entries, etc.
                    The keys are randomly generated using OpenSSL's random byte
                    sequence generation function.
                */
                $authkeys = file_get_contents(__DIR__."/../templates/authkeys.php");
                while (strpos($authkeys, "<%GENERATED_KEY%>") !== false) {
                    /*
                        Create one key at a time until the AuthKeys file no
                        longer contains the <%GENERATED_KEY%> replacement
                        string.
                    */
                    $key = base64_encode(openssl_random_pseudo_bytes(32));
                    $authkeys = preg_replace(
                        "/".preg_quote("<%GENERATED_KEY%>")."/",
                        $key, $authkeys, 1
                    );
                }
                file_put_contents(__DIR__."/../../userdata/authkeys.php", $authkeys);
                return true;
            });
            $r += echoAssert("install.stage.{$stage}.assert.config_written", true, function() {
                /*
                    Write a configuration file. The configuration that is
                    written here consists solely of defaults from
                    /includes/config/defs.php.
                */
                global $stage;

                /*
                    Get the full request URL the user is currently at, including
                    the protocol (HTTP or HTTPS).
                */
                $proto = "http";
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
                    $proto = "https";
                }
                $siteUri = $proto."://".$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"];

                /*
                    Remove the "admin/install-wizard.php" suffix to get the root
                    path of the FreeField installation.
                */
                $siteUri = substr($siteUri, 0, strrpos($siteUri, "admin/install-wizard.php"));

                /*
                    Populate the configuration with defaults first, then
                    overwrite the site URI with the one we determined above.
                */
                Config::populateWithDefaults();
                Config::set(array(
                    "site/uri" => $siteUri,
                    /*
                        If successful, increment the current stage of the
                        installation so that we can proceed to stage 3 on next
                        page reload.
                    */
                    "install/wizard" => array(
                        "stage" => $stage + 1
                    )
                ));

                return true;
            }, $r);
        ?></pre>
        <p>
            <?php echo I18N::resolveHTML("install.stage.{$stage}.next"); ?>
        </p>
        <?php
            if ($r == 0) {
                printContinueButton();
            }
        ?>
    <?php printTail();
    exit;
}

?>
