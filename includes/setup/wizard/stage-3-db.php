<?php
/*
    FREEFIELD INSTALLATION STAGE 3

    This script is part of the FreeField installation wizard
    (/admin/install-wizard.php). Stage 3 of the installation performs database
    setup and creates and populates tables.
*/

if ($stage == 3 && (!$isPost || !$csrfPass)) {
    printHead($stage, "POST"); ?>
        <p>
            <?php echo I18N::resolveHTML("install.stage.{$stage}.info"); ?>
        </p>
        <?php
            /*
                Edit settings related to database access.
            */
            $settings = array(
                "database/type",
                "database/host",
                "database/port",
                "database/username",
                "database/password",
                "database/database",
                "database/table-prefix"
            );
            printSettingFields($settings, true);
            printContinueButton();
        ?>
    <?php printTail();
} elseif ($stage == 3) {
    /*
        If this page is POSTed and CSRF validation passes, we can start
        populating the database.
    */
    __require("db");
    printHead($stage, "GET"); ?>
        <p>
            <?php echo I18N::resolveHTML("install.operation.done"); ?>
        </p>
        <pre><?php
            $db = null;
            $r = echoAssert("install.stage.{$stage}.assert.valid_data", true, function() {
                /*
                    Ensure that the supplied database details are valid - this
                    includes checking against regular expressions declared in
                    /includes/config/defs.php.
                */
                return validatePOSTFields();
            });
            $r += echoAssert("install.stage.{$stage}.assert.config_written", true, function() {
                /*
                    If the settings are valid, write the database access details
                    to the configuration file.
                */
                Config::set($_POST);
                return true;
            }, $r);
            $r += echoAssert("install.stage.{$stage}.assert.db_connected", true, function() {
                /*
                    Try to connect to the database. This assertion will fail by
                    exception if the connection parameters are incorrect (such
                    as if the username/password is wrong).
                */
                global $db;
                $db = Database::getSparrow();
                return true;
            }, $r);
            $r += echoAssert("install.stage.{$stage}.assert.exec_sql", true, function() {
                /*
                    If the connection was successful, execute the setup queries
                    in /includes/setup/db-init.sql.
                */
                global $db;
                $sql = file_get_contents(__DIR__."/../db-init.sql");

                /*
                    Replace the table prefix in the SQL queries. The prefix may
                    only be [A-Za-z0-9_], enforced by regular expressions in
                    /includes/config/defs.php in the valitity assertation above,
                    hence SQL injection is not an attack vector when
                    substituting the prefix into the tables names in the SQL
                    queries.
                */
                $prefix = $_POST["database/table-prefix"];
                $sql = str_replace("{%TablePrefix%}", $prefix, $sql);

                /*
                    Execute the queries.
                */
                $queries = explode(";", $sql);
                foreach ($queries as $query) {
                    if (trim($query) == "") continue;
                    $db
                        ->sql($query)
                        ->execute();
                }
                return true;
            }, $r);
            $r += echoAssert("install.stage.{$stage}.assert.proceed_stage", true, function() {
                /*
                    This assertation is just to verify that we can save the next
                    stage number to the configuration file, to proceed with
                    authentication in stage 4.
                */
                global $stage;
                Config::set(array(
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
            /*
                If successful, output a continue button. Otherwise, prompt the
                user to try again.
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
