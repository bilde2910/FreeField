<?php
/*
    FREEFIELD INSTALLATION STAGE 4

    This script is part of the FreeField installation wizard
    (/admin/install-wizard.php). Stage 4 of the installation lets the user
    select and set up one or more authentiation providers to log in with
    FreeField.
*/

if ($stage == 4 && (!$isPost || !$csrfPass)) {
    printHead($stage, "POST"); ?>
        <p>
            <?php echo I18N::resolveHTML("install.stage.{$stage}.info"); ?>
        </p>
        <?php
            /*
                Get a list of all implemented authentication providers, and the
                settings they require to be set up.
            */
            $providers = Auth::getProviderRequirements();

            /*
                Output a section for each provider, with the required fields to
                be filled in.
            */
            foreach ($providers as $provider => $requirements) {
                echo '<h2 class="content-subhead">';
                echo I18N::resolveHTML("admin.section.auth.{$provider}.name");
                echo '</h2>';

                /*
                    The /enabled setting is a checkbox that enables or disables
                    the given authentication provider.
                */
                $settings = array("auth/provider/{$provider}/enabled");
                foreach ($requirements as $requirement) {
                    $settings[] = "auth/provider/{$provider}/{$requirement}";
                }
                printSettingFields($settings, false);
            }
            printContinueButton();
        ?>
    <?php printTail();
} elseif ($stage == 4) {
    /*
        If this page is POSTed and CSRF validation passes, we can validate and
        store the authentication provider settings.
    */
    printHead($stage, "GET"); ?>
        <p>
            <?php echo I18N::resolveHTML("install.operation.done"); ?>
        </p>
        <pre><?php
            $r = echoAssert("install.stage.{$stage}.assert.valid_data", true, function() {
                /*
                    Verify that the given data is valid according to the setting
                    defitions in /includes/config/defs.php.
                */
                return validatePOSTFields();
            });
            $r += echoAssert("install.stage.{$stage}.assert.config_written", true, function() {
                /*
                    If valid, write the authentication provider settings to the
                    configuration file.
                */
                Config::set($_POST);
                return true;
            }, $r);
            $r += echoAssert("install.stage.{$stage}.assert.any_enabled", true, function() {
                /*
                    Ensure that at least one authentication provider is enabled.
                    An authentication provider is considered enabled only if all
                    of the following requirements are met:

                      - The checkbox that enables the authentication provider is
                        checked, and
                      - All required settings for the authentication provider
                        are populated with valid values.
                */
                return count(Auth::getEnabledProviders()) > 0;
            });
            $r += echoAssert("install.stage.{$stage}.assert.proceed_stage", true, function() {
                /*
                    Update the configuration to indicate that we proceed to
                    stage 5.
                */
                global $stage;
                Config::set(array(
                    "install/wizard" => array(
                        "stage" => $stage + 1,
                        /*
                            `authenticate-now` is a flag that indicates to the
                            auth library and login pages that an upcoming login
                            attempt is part of the installation process. While
                            this flag is set, registering users are
                            automatically granted full site host (super-
                            administrator) rights. This flag is unset as soon as
                            the user has verified that they can sign in using
                            their chosen authentication provider.

                            Please do a project-wide search for all occurrences
                            of "authenticate-now" for information on the usage
                            of this flag.
                        */
                        "authenticate-now" => true
                    )
                ));
                return true;
            }, $r);
        ?></pre>
        <p>
            <?php echo I18N::resolveHTML("install.stage.{$stage}.next"); ?>
        </p>
        <?php
            /*
                If the data is invalid or the assertations otherwise failed,
                prompt the user to return to the setup page to retry filling in
                the required data. Otherwise, let the user continue to stage 5,
                where they will verify that their authentication setup is
                working.
            */
            if ($r == 0) {
                printContinueButton();
            } else {
                printRetryButton();
            }
        ?>
    <?php printTail();
}

?>
