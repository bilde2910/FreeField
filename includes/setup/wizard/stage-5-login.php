<?php
/*
    FREEFIELD INSTALLATION STAGE 5

    This script is part of the FreeField installation wizard
    (/admin/install-wizard.php). Stage 5 of the installation requires that the
    user verifies that (at least one of) their chosen authentication providers
    are working.
*/

if ($stage == 5 && !$cu->exists() && !isset($_GET["auth-failed"])) {
    /*
        Redirect the user to the login page to execute the verification
        challenge.
    */
    header("HTTP/1.1 303 See Other");
    header("Location: ../auth/login.php");
    exit;
} elseif ($stage == 5 && isset($_GET["auth-failed"])) {
    /*
        If the user returns here with the URL parameter `auth-failed`, then the
        user gave up authentication, and they should return to stage 4 to re-
        declare their authentication provider settings.
    */
    Config::set(array(
        "install/wizard" => array(
            "stage" => $stage - 1
        )
    ));
    header("HTTP/1.1 303 See Other");
    header("Location: ./install-wizard.php");
    exit;
} elseif ($stage == 5) {
    /*
        If the user browsed to this URL with a valid session cookie representing
        a valid user (i.e. `$cu->exists()`), and the `auth-failed` flag is not
        set, then the authentication attempt was successful, and they can
        continue to stage 6 of the installation.
    */
    printHead($stage, "GET"); ?>
        <p>
            <?php echo I18N::resolveHTML("install.operation.done"); ?>
        </p>
        <pre><?php
            $r = echoAssert("install.stage.{$stage}.assert.auth_success", true, function() {
                /*
                    Tell the user that the authentication was successful.
                */
                return true;
            });
            $r += echoAssert("install.stage.{$stage}.assert.user_created", true, function() {
                /*
                    Also tell them that their identity with their chosen
                    authentication provider was registered in the user database,
                    and granted administrative privileges on FreeField.
                */
                return true;
            });
            $r += echoAssert("install.stage.{$stage}.assert.config_written", true, function() {
                /*
                    Update the configuration to point to the next stage of the
                    installation, and UNSET THE `authenticate-now` FLAG.
                    (Keeping it enabled would pose a severe security risk.)
                */
                global $stage;
                Config::set(array(
                    "install/wizard" => array(
                        "stage" => $stage + 1
                    )
                ));
                return true;
            });
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
}

?>
