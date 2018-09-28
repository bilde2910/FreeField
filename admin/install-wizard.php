<?php
/*
    This script is a first-time setup guide for FreeField. It performs
    installation procedures to set the software up for first time use.
*/

require_once("../includes/lib/global.php");
__require("i18n");

//-----------------------------------------------------------------------------+
//     Start HTML templates                                                    |
//-----------------------------------------------------------------------------+
/*
    This function prints an HTML header for the given setup stage. It takes two
    parameters, the current `$stage` of the setup process (used to display the
    correct subtitle on the page), and the `$method` by which the form on the
    page should be submitted (GET or POST).
*/
function printHead($stage, $method) {
    $pageTitle = I18N::resolveHTML("page_title.setup");
    $pageSubtitle = I18N::resolveHTML("install.stage.{$stage}.title");
    $csrfField = "";
    /*
        Use CSRF only if CSRF functionality has been loaded. This functionality
        is not loaded until we at least know that OpenSSL is available for
        secure random number generation, which is required by the CSRF
        protection functionality in FreeField.
    */
    if (class_exists("Security")) {
        $csrfField = Security::getCSRFInputField();
    }
    echo <<<__END_STRING__
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex,nofollow">
        <title>{$pageTitle}</title>
        <link rel="stylesheet"
              href="https://unpkg.com/purecss@1.0.0/build/pure-min.css"
              integrity="sha384-nn4HPE8lTHyVtfCBi5yW9d20FjT8BJwUXyWZT9InLYax14RDjBj46LmSztkmNP9w"
              crossorigin="anonymous">
        <link rel="stylesheet"
              href="https://use.fontawesome.com/releases/v5.0.13/css/all.css"
              integrity="sha384-DNOHZ68U8hZfKXOrtjWvjxusGo9WQnrNx2sqG0tfsghAvtVlRW3tvkXWZh58N9jp"
              crossorigin="anonymous">
        <link rel="stylesheet" href="../css/main.css">
        <link rel="stylesheet" href="../css/dark.css">

        <!--[if lte IE 8]>
            <link rel="stylesheet" href="../css/layouts/side-menu-old-ie.css">
        <![endif]-->
        <!--[if gt IE 8]><!-->
            <link rel="stylesheet" href="../css/layouts/side-menu.css">
        <!--<![endif]-->
    </head>
    <body>
        <div id="main">
            <div class="header" style="border-bottom: none; margin-bottom: 50px;">
                <h1>
                    {$pageTitle}
                </h1>
                <h2>
                    {$pageSubtitle}
                </h2>
            </div>
            <div class="content">
                <form action="install-wizard.php"
                      method="{$method}"
                      class="pure-form require-validation"
                      enctype="multipart/form-data">
                    {$csrfField}
__END_STRING__;
}
/*
    This function outputs an HTML tail to the page, displayed after the rest of
    the content of the page has been printed.
*/
function printTail() {
    echo <<<__END_STRING__
            </div>
        </div>
        <script src="../js/ui.js"></script>
    </body>
</html>
__END_STRING__;
}
/*
    This function outputs a "Continue setup" form submission button to the page.
*/
function printContinueButton() {
    $contents = I18N::resolveHTML("install.button.continue");
    echo <<<__END_STRING__
        <p class="buttons">
            <input type="submit" class="button-submit" value="{$contents}">
        </p>
__END_STRING__;
}
/*
    This function outputs a "Finish setup" form submission button to the page.
*/
function printFinishButton() {
    $contents = I18N::resolveHTML("install.button.finish");
    echo <<<__END_STRING__
        <p class="buttons">
            <input type="submit" class="button-submit" value="{$contents}">
        </p>
__END_STRING__;
}
/*
    This function outputs a "Try again" form submission button to the page. When
    this button is clicked, the page is reloaded via GET to display the
    configuration page for the current stage.
*/
function printRetryButton() {
    $contents = I18N::resolveHTML("install.button.retry");
    echo <<<__END_STRING__
        <p class="buttons">
            <input type="button" class="button-submit" value="{$contents}"
                   onclick="location.href='install-wizard.php'">
        </p>
__END_STRING__;
}
/*
    This function outputs the given list of settings to the page for editing.

    $settings
        An array of setting paths to render on the page.

    $require
        Whether or not all of the fields in `$settings` must be required to be
        filled in in order for the form to be submitted and the settings to be
        processed and saved.
*/
function printSettingFields($settings, $require) {
    foreach ($settings as $setting) {
        $def = Config::get($setting);
        $current = $def->value();
        $i18n = new ConfigSettingI18N($setting);
        $attrs = array(
            "name" => $setting
        );
        if ($require) $attrs["required"] = true;
        ?>
            <div class="pure-g">
                <div class="pure-u-1-3 full-on-mobile">
                    <p class="setting-name">
                        <?php echo I18N::resolveHTML($i18n->getName()); ?>:
                    </p>
                </div>
                <div class="pure-u-2-3 full-on-mobile">
                    <p>
                        <?php echo $def->getOption()->getControl(
                            $current,
                            $attrs
                        ); ?>
                    </p>
                </div>
            </div>
        <?php
    }
}
//-----------------------------------------------------------------------------+
//     End HTML templates                                                      |
//-----------------------------------------------------------------------------+

/*
    This function performs an assertation of a given callback function and
    outputs the result to the page. Returns 0 if the assertation passed, or 1 if
    it failed.

    $i18n
        An I18N token that describes the assertation in human-readable form. The
        string corresponding to the I18N token, e.g. "Configuration file
        written", is output to the page.

    $critical
        Whether or not it is critical that the given assertation evaluates to
        true. If `$critical = true`, this function will return 1 if the
        assertation failed. If `$critical = false`, the assertation will be
        considered a pass even if it fails, and returns 0. Non-critical
        assertations are typically things that should evaluate to true, but will
        not break FreeField if they evaluate to false - such as presence of
        HTTPS, graphics extension loaded, etc. Critical assertations are things
        where, if failing, the installation cannot proceed and/or FreeField will
        not function - e.g. database connection failure.

    $assertion
        A callback function whose return value is evaluated. The function should
        return `true` if the assertation passed, `false` if it failed, or a
        string that explains the reasons for a failure. Returning a string is an
        implicit failure. The assertation function may throw exceptions. If an
        exception is thrown, the assertation is considered failed and the
        exception message is output to the page.

    $skip
        Whether or not the assertation should be skipped entirely. Useful if
        output is desired on the page to indicate to the user that further
        assertations would have been made, but they were not for whatever reason
        (e.g. a previous assertation failed).
*/
function echoAssert($i18n, $critical, $assertion, $skip = 0) {
    // Whether or not the assertation failed.
    $failed = 0;
    // An assertation callback exception, if any is thrown.
    $ex = null;
    // The result of the assertation.
    $result = false;

    if (!$skip) {
        /*
            Attempt to call the assertation callback.
        */
        try {
            $result = $assertion();
        } catch (Exception $e) {
            $ex = $e;
        }
        /*
            Output the result of the assertation.
        */
        if ($result === true) {
            // Green check mark
            echo '[<span style="color:lime;">&#x2713;</span>]';
        } elseif (!$critical) {
            // Yellow exclamation mark
            echo '[<span style="color:yellow;">!</span>]';
        } else {
            // Red X mark
            echo '[<span style="color:red;">&#x2717;</span>]';
            $failed = 1;
        }
    } else {
        /*
            If the assertation was skipped, output blank brackets.
        */
        echo '[ ]';
    }
    /*
        Echo the human readable string corresponding to the assertation.
    */
    echo " ".I18N::resolveHTML($i18n)."\n";
    /*
        If something went wrong, output that on a new line underneath the result
        box.
    */
    if ($ex !== null) {
        echo " &#x2ba1;  ".htmlspecialchars($ex->getMessage(), ENT_QUOTES)."\n\n";
    }
    if (is_string($result)) {
        echo " &#x2ba1;  ".htmlspecialchars($result, ENT_QUOTES)."\n\n";
    }
    /*
        If the assertation failed critically, return a status code of 1. This is
        accumulated and used to determine whether or not the user can proceed to
        the next stage. It can also be passed as `$skip` to this function.
    */
    return $failed;
}

/*
    This function validates all settings passed in the global $_POST variable.
    Returns `true` if everything is valid, `false` otherwise.
*/
function validatePOSTFields() {
    foreach ($_POST as $k => $v) {
        $def = Config::get($k);
        $opt = $def->getOption();
        $val = $opt->parseValue($v);
        if (!$opt->isValid($val)) {
            /*
                `PasswordOption` returns `false` if the passed value is the
                password mask. This is to ensure that the password mask is not
                written to the configuration file, as it is the default value of
                the password input box. We should consider the mask a valid
                value for the purposes of this script.
            */
            if (!($opt instanceof PasswordOption) || $v !== $opt->getMask()) {
                return "{$k} is invalid!";
            }
        }
    }
    return true;
}

/*
    Include stage 1 check:
    Checks for the existence of the configuration file if GET. Skips to stage 2
    if POST. This stage checks the environment in which FreeField is to be
    installed and checks for missing dependencies.
*/
include("../includes/setup/wizard/stage-1-env.php");

/*
    Include stage 2 check:
    If the configuration file is missing, generate one with default values. Also
    generates the AuthKeys class, used to encrypt and decrypt data for purposes
    that require so, like session cookies and sensitive configuration entries.
*/
__require("config");
include("../includes/setup/wizard/stage-2-conf.php");

/*
    Now that we know the configuration file exists, we can check to see if the
    setup process has already been completed. This is stored in the
    configuration file at the path "install/wizard/completed". If this key does
    not exists, the below `getRaw()` call returns `null`. If it explicitly
    returns true, then the wizard has definitively been completed. Redirect the
    user back to the admin pages in that case.
*/
if (Config::getRaw("install/wizard/completed") === true) {
    header("HTTP/1.1 303 See Other");
    header("Location: ".Config::getEndpointUri("/admin/"));
    exit;
}

/*
    Perform CSRF check for POST requests.
*/
__require("security");
Security::requireCSRFToken();
$csrfPass = Security::validateCSRF();
Security::unsetCSRFFields();

/*
    The current stage is stored in the "install/wizard/stage" configuration key.
    Each stage increments this value in the configuration file if successful.
*/
$stage = Config::getRaw("install/wizard/stage");
$isPost = $_SERVER["REQUEST_METHOD"] === "POST";

/*
    Include stage 3 check:
    Configures database access for FreeField and checks that it is working.
*/
include("../includes/setup/wizard/stage-3-db.php");

/*
    Include stage 4 check:
    Sets up authentication provider(s).
*/
__require("auth");
include("../includes/setup/wizard/stage-4-auth.php");

/*
    Include stage 5 check:
    Verifies that the authentication provider(s) set up in stage 4 are working
    by prompting the user to sign in. If the user successfully authenticates,
    proceed to stage 6.
*/
$cu = Auth::getCurrentUser();
include("../includes/setup/wizard/stage-5-login.php");

/*
    Include stage 6 check:
    Configures map providers and defaults. When complete, it flags the
    installation progress as completed and redirects the user to the
    administration pages.
*/
include("../includes/setup/wizard/stage-6-map.php");

?>
