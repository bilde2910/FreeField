<?php
/*
    Displayed on the admin page /admin/index.php when checking for updates.
*/

__require("config");
__require("update");
__require("vendor/parsedown");

?>
<div class="content pure-form">
    <?php
        $checkData = Config::getRaw("install/update-check");

        $update = array(
            "current" => FF_VERSION,
            "last_check" => date("r", $checkData["last-check"]),
            "next_check" => date("r", $checkData["next-check"])
        );

        $available = Update::getUpdatesByChannel();
    ?>
    <h2 class="content-subhead">
        <?php echo I18N::resolveHTML("admin.section.updates.info.name"); ?>
    </h2>
    <?php
        $fields = array_keys($update);
        foreach ($fields as $field) {
            ?>
                <div class="pure-g">
                    <div class="pure-u-1-3 full-on-mobile">
                        <p class="setting-name">
                            <?php echo I18N::resolveHTML("setting.updates.{$field}.name"); ?><span class="only-desktop">:
                                <span class="tooltip">
                                    <i class="content-fas fas fa-question-circle"></i>
                                    <span>
                                        <?php echo I18N::resolveHTML("setting.updates.{$field}.desc"); ?>
                                    </span>
                                </span>
                            </span>
                        </p>
                        <p class="only-mobile">
                            <?php echo I18N::resolveHTML("setting.updates.{$field}.desc"); ?>
                        </p>
                    </div>
                    <div class="pure-u-2-3 full-on-mobile">
                        <p>
                            <input type="text" value="<?php echo htmlspecialchars($update[$field], ENT_QUOTES); ?>" readonly>
                        </p>
                    </div>
                </div>
            <?php
        }
    ?>

    <h2 class="content-subhead">
        <?php echo I18N::resolveHTML("admin.section.updates.available.name"); ?>
    </h2>
    <?php
        foreach ($available as $channel => $release) {
            ?>
                <div class="update-channel-head">
                    <h3 class="content-subhead release-header-<?php echo $channel; ?> update-release-type">
                        <?php echo I18N::resolve("release_type.{$channel}.name") ?>
                    </h3>
                    <h3 class="content-subhead update-release-version">
                        <?php echo htmlspecialchars($release["version"], ENT_QUOTES); ?>
                    </h3>
                    <p class="update-release-desc">
                        <?php echo I18N::resolve("release_type.{$channel}.desc") ?>
                    </p>
                </div>
                <div class="update-channel-body">
                    <h3 class="content-subhead update-release-notes">
                        <?php echo I18N::resolve("admin.section.updates.available.release_notes") ?>
                    </h3>
                    <?php
                        $parsedown = new Parsedown();
                        $parsedown->setSafeMode(true);
                        echo $parsedown->text($release["body"]);
                    ?>
                    <p class="buttons">
                        <input type="button"
                               class="button-submit install-button"
                               data-version="<?php echo htmlspecialchars($release["version"], ENT_QUOTES); ?>"
                               value="<?php echo I18N::resolveHTML("admin.section.updates.ui.update.name"); ?>">
                </div>
            <?php
        }
    ?>
    </p>
</div>

<!--
    This div is an overlay which shows up whenever the user clicks on the
    "Install update" button on an available update.
-->
<form action="apply-updates.php"
      method="POST"
      class="pure-form"
      enctype="application/x-www-form-urlencoded">
    <!--
        Protection against CSRF
    -->
    <?php echo Security::getCSRFInputField(); ?>
    <div id="updates-install-confirmation-overlay" class="cover-box admin-cover-box">
        <div class="cover-box-inner">
            <div class="header">
                <h1>
                    <?php echo I18N::resolveHTML("admin.updates.popup.confirm_install.title"); ?>
                </h1>
                <div class="cover-box-content content">
                    <p class="left-align">
                        <?php echo I18N::resolveHTML("admin.updates.popup.confirm_install.disclaimer"); ?>
                    </p>
                    <div class="pure-g">
                        <div class="pure-u-1-2 full-on-mobile">
                            <p class="left-align">
                                <?php echo I18N::resolveHTML("admin.updates.popup.confirm_install.target"); ?>
                            </p>
                        </div>
                        <div class="pure-u-1-2 full-on-mobile">
                            <p>
                                <input type="text" id="update-to-version" name="to-version" readonly>
                            </p>
                        </div>
                    </div>
                    <!--
                        The user must check a checkbox with a liability
                        statement next to it in order to proceed with the
                        installation.
                    -->
                    <p class="left-align">
                        <label for="update-disclaimer-checkbox">
                            <input type="checkbox" name="accepted-disclaimer" id="update-disclaimer-checkbox">
                            <?php echo I18N::resolveHTML("admin.updates.popup.confirm_install.liability_statement"); ?>
                        </label>
                    </p>
                    <div class="pure-g">
                        <div class="pure-u-1-2 right-align">
                            <input type="button"
                                   id="update-button-cancel-install"
                                   class="button-standard input-split-button split-button button-spaced left"
                                   value="<?php echo I18N::resolveHTML("ui.button.cancel"); ?>">
                        </div>
                        <div class="pure-u-1-2">
                            <input type="submit"
                                   id="update-button-confirm-install"
                                   class="button-submit input-split-button split-button button-spaced right"
                                   disabled="disabled"
                                   value="<?php echo I18N::resolveHTML("admin.section.updates.ui.install.name"); ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<!--
    /admin/js/updates.js contains additional functionality for this page.
-->
<script type="text/javascript" src="./js/updates.js"></script>
