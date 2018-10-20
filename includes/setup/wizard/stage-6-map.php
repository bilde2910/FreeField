<?php
/*
    FREEFIELD INSTALLATION STAGE 6

    This script is part of the FreeField installation wizard
    (/admin/install-wizard.php). Stage 6 of the installation lets the user
    choose a map provider, and declare defaults for the FreeField map, such as
    default coordinates to load the map at.
*/

if ($stage == 6 && (!$isPost || !$csrfPass)) {
    printHead($stage, "POST"); ?>
        <h2 class="content-subhead">
            <?php echo I18N::resolveHTML("admin.section.map.provider.name"); ?>
        </h2>
        <p>
            <?php echo I18N::resolveHTML("install.stage.{$stage}.provider_info"); ?>
        </p>
        <p>
            <?php echo I18N::resolveHTML("install.stage.{$stage}.disclaimer"); ?>
        </p>
        <?php
            /*
                Setting for choosing a map provider.
            */
            $settings = array(
                "map/provider/source"
            );
            printSettingFields($settings, false);
        ?>
        <p>
            <?php echo I18N::resolveHTML("install.stage.{$stage}.field_guide"); ?>
        </p>
        <?php
            /*
                Map provider-specific settings.
            */
            $settings = array(
                "map/provider/mapbox/access-token",
                "map/provider/thunderforest/api-key"
            );
            printSettingFields($settings, false);
        ?>
        <h2 class="content-subhead">
            <?php echo I18N::resolveHTML("admin.section.map.default.name"); ?>
        </h2>
        <p>
            <?php echo I18N::resolveHTML("install.stage.{$stage}.default_info"); ?>
        </p>
        <?php
            /*
                Settings for map defaults (e.g. default coordinates).
            */
            $settings = array(
                "map/updates/tz",
                "map/default/center/latitude",
                "map/default/center/longitude"
            );
            printSettingFields($settings, false);
            printContinueButton();
        ?>
    <?php printTail();
} elseif ($stage == 6) {
    /*
        If this page is POSTed and CSRF validation passes, we can validate and
        store the map settings.
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
                    Write the data to the configuration file.
                */
                global $stage;
                Config::set($_POST);
                /*
                    Declare that the setup process is completed.
                */
                Config::set(array(
                    "install/wizard" => array(
                        "completed" => true
                    )
                ));
                return true;
            }, $r);
        ?></pre>
        <p>
            <?php echo I18N::resolveHTML("install.stage.{$stage}.next"); ?>
        </p>
        <h2 class="content-subhead">
            <?php echo I18N::resolveHTML("install.stage.{$stage}.post-install.head"); ?>
        </h2>
        <p>
            <?php echo I18N::resolveArgsHTML(
                "install.stage.{$stage}.post-install.body", false,
                '<a href="https://freefield.readthedocs.io/en/latest/setup.html#post-installation-steps" target="_blank">',
                '</a>'
            ); ?>
        </p>
        <?php
        /*
            If the data is invalid or the assertations otherwise failed,
            prompt the user to return to the setup page to retry filling in
            the required data. Otherwise, let the user finish the setup wizard.
        */
            if ($r == 0) {
                printFinishButton();
            } else {
                printRetryButton();
            }
        ?>
    <?php printTail();
}

?>
