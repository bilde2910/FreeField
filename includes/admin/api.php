<?php
/*
    Displayed on the admin page /admin/index.php when managing API access.
*/

__require("api");

?>
<div class="content wide-content">
    <form action="apply-api.php"
          method="POST"
          class="pure-form"
          enctype="application/x-www-form-urlencoded">
        <!--
            Protection against CSRF
        -->
        <?php echo Security::getCSRFInputField(); ?>
        <?php
            /*
                Get a list of available clients from the database.

                This function returns an array of instances of the APIClient
                class from /includes/lib/api.php. Please see that file for class
                implementation details.
            */
            $clients = API::listClients();

            /*
                Sort the client list in descending order by their names.
            */
            usort($clients, function($a, $b) {
                return strnatcmp($a->getName(), $b->getName());
            });
        ?>
        <!--
            List of registered API clients
        -->
        <h2 class="content-subhead">
            <?php echo I18N::resolveHTML("admin.section.api.client_list.name"); ?>
        </h2>
        <table class="pure-table force-fullwidth">
            <thead>
                <tr>
                    <!--
                        Table header defining columns for:

                            1.  Changing the name of the client (text box)
                            2.  Changing the client's name color (color picker)
                            3.  Showing the access token of the client
                            4.  Showing when the client was last used
                            5.  Changing the permissions of the client (link)
                            6.  Taking actions on the client (e.g. resetting its
                                access token)
                    -->
                    <th data-sort-function="input-value"><?php echo I18N::resolveHTML("admin.table.api.client_list.column.name.name"); ?></th>
                    <th><?php echo I18N::resolveHTML("admin.table.api.client_list.column.token.name"); ?></th>
                    <th><?php echo I18N::resolveHTML("admin.table.api.client_list.column.color.name"); ?></th>
                    <th data-sort-function="alphanumeric"><?php echo I18N::resolveHTML("admin.table.api.client_list.column.seen.name"); ?></th>
                    <th><?php echo I18N::resolveHTML("admin.table.api.client_list.column.access.name"); ?></th>
                    <th><?php echo I18N::resolveHTML("admin.table.api.client_list.column.actions.name"); ?></th>
                </tr>
            </thead>
            <tbody id="client-list">
                <?php
                    foreach ($clients as $client) {
                        $id = $client->getClientID();
                        ?>
                            <tr class="api-table-client-row">
                                <td class="api-table-name-cell">
                                    <input type="text"
                                           name="a<?php echo $id; ?>[name]"
                                           value="<?php echo htmlspecialchars($client->getName(), ENT_QUOTES); ?>">
                                </td>
                                <td>
                                    <input type="button"
                                           class="api-table-view-token"
                                           data-token="<?php echo htmlspecialchars($client->getToken(), ENT_QUOTES); ?>"
                                           value="<?php echo I18N::resolveHTML("admin.table.api.client_list.column.token.mask"); ?>">
                                </td>
                                <td>
                                    <input type="color"
                                           name="a<?php echo $id; ?>[color]"
                                           value="#<?php echo $client->getColor(); ?>">
                                </td>
                                <td>
                                    <?php
                                        $date = $client->getLastSeenDate();
                                        if ($date === null) {
                                            echo I18N::resolveHTML("admin.clientside.api.client_list.seen.never");
                                        } else {
                                            echo $date;
                                        }
                                    ?>
                                </td>
                                <td>
                                    <div class="api-table-access-label">
                                        <p class="api-table-label-level-text">
                                            <?php echo I18N::resolveHTML("admin.clientside.api.client_list.access.level"); ?>
                                            <span class="api-table-label-level-value">
                                                <?php echo $client->getPermissionLevel(); ?>
                                            </span>
                                        </p>
                                        <p class="api-table-label-list">
                                            <?php
                                                /*
                                                    Output a human-readable representation of the
                                                    current permissions for each API client, or "No
                                                    permissions granted" if none are granted.
                                                */
                                                $perms = $client->getPermissionList();
                                                if (count($perms) == 0) {
                                                    echo I18N::resolveHTML(
                                                        "admin.clientside.api.client_list.access.none"
                                                    );
                                                } else {
                                                    $strings = array();
                                                    foreach ($perms as $perm) {
                                                        $perm = str_replace("/", ".", str_replace("-", "_", $perm));
                                                        $strings[] = I18N::resolveHTML(
                                                            "setting.permissions.level.{$perm}.name"
                                                        );
                                                    }
                                                    sort($strings);
                                                    echo implode("<br />", $strings);
                                                }
                                            ?>
                                        </p>
                                        <input type="hidden"
                                               data-perm-type="access"
                                               name="a<?php echo $id; ?>[access]"
                                               value="<?php echo htmlspecialchars(implode(",", $client->getPermissionList()), ENT_QUOTES); ?>">
                                        <input type="hidden"
                                               data-perm-type="level"
                                               name="a<?php echo $id; ?>[level]"
                                               value="<?php echo $client->getPermissionLevel(); ?>">
                                    </div>
                                </td>
                                <td>
                                    <select class="client-actions"
                                            name="a<?php echo $id; ?>[action]">
                                        <option value="none" selected>
                                            <?php echo I18N::resolveHTML("admin.clientside.api.client_list.action.none"); ?>
                                        </option>
                                        <option value="reset">
                                            <?php echo I18N::resolveHTML("admin.clientside.api.client_list.action.reset"); ?>
                                        </option>
                                        <option value="delete">
                                            <?php echo I18N::resolveHTML("admin.clientside.api.client_list.action.delete"); ?>
                                        </option>
                                    </select>
                                </td>
                            </tr>
                        <?php
                    }
                ?>
            </tbody>
        </table>
        <p class="buttons">
            <input type="button"
                   id="client-new"
                   class="button-standard"
                   value="<?php echo I18N::resolveHTML("admin.section.api.ui.add.name"); ?>">
            <input type="submit"
                   class="button-submit"
                   value="<?php echo I18N::resolveHTML("ui.button.save"); ?>">
        </p>
    </form>
</div>

<!--
    This div is an overlay which shows up whenever the user clicks on "View
    token" for an API client. It displays the access token for the API client.
    It is hidden by default and displayed by the event handler for
    `.api-table-view-token` in /admin/js/api.js.
-->
<div id="api-popup-token-box" class="cover-box admin-cover-box">
    <div class="cover-box-inner">
        <div class="header">
            <h1>
                <?php echo I18N::resolveHTML("admin.api.popup.view_token.title"); ?>
            </h1>
        </div>
        <div class="cover-box-content content pure-form">
            <div class="pure-g">
                <div class="pure-u-1-2 full-on-mobile">
                    <p class="left-align">
                        <?php echo I18N::resolveHTML("admin.api.popup.view_token.client_name"); ?>
                    </p>
                </div>
                <div class="pure-u-1-2 full-on-mobile">
                    <p>
                        <input type="text" id="api-popup-token-name" readonly>
                    </p>
                </div>
            </div>
            <div class="pure-g">
                <div class="pure-u-5-5 full-on-mobile">
                    <textarea id="api-popup-token-value"
                              class="monospaced"
                              readonly></textarea>
                </div>
            </div>
            <div class="cover-button-spacer"></div>
            <div class="pure-g">
                <div class="pure-u-1-2 right-align">
                    <span id="api-popup-token-copy"
                          class="button-standard split-button button-spaced left">
                                <?php echo I18N::resolveHTML("admin.section.api.ui.copy.name"); ?>
                    </span>
                </div>
                <div class="pure-u-1-2">
                    <span id="api-popup-token-close"
                          class="button-submit split-button button-spaced right">
                                <?php echo I18N::resolveHTML("ui.button.close"); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>
<!--
    This div is an overlay which shows up whenever the user clicks on the
    permission list for an API client. It displays and lets the user manage the
    permissions the API client has. It is hidden by default and displayed by the
    event handler for `.api-table-access-label` in /admin/js/api.js.
-->
<div id="api-popup-access-box" class="cover-box admin-cover-box">
    <div class="cover-box-inner">
        <div class="header">
            <h1>
                <?php echo I18N::resolveHTML("admin.api.popup.access_list.title"); ?>
            </h1>
        </div>
        <div class="cover-box-content content pure-form">
            <div class="pure-g">
                <div class="pure-u-1-2 full-on-mobile">
                    <p class="left-align">
                        <?php echo I18N::resolveHTML("admin.api.popup.access_list.client_name"); ?>
                    </p>
                </div>
                <div class="pure-u-1-2 full-on-mobile">
                    <p>
                        <input type="text" id="api-popup-access-name" readonly>
                    </p>
                </div>
            </div>
            <div class="pure-g">
                <div class="pure-u-1-2 full-on-mobile">
                    <p class="left-align">
                        <?php echo I18N::resolveHTML("admin.api.popup.access_list.level"); ?>
                    </p>
                </div>
                <div class="pure-u-1-2 full-on-mobile">
                    <p>
                        <input type="number"
                               min="0"
                               max="<?php echo Auth::getCurrentUser()->getPermissionLevel(); ?>"
                               id="api-popup-access-level">
                    </p>
                </div>
            </div>
            <div class="pure-g">
                <div class="pure-u-5-5 full-on-mobile">
                    <?php
                        /*
                            Output checkboxes for each of the currently
                            available implemented permissions, along with a
                            description of what the permission does.
                        */
                        __require("api");
                        foreach (API::AVAILABLE_PERMS as $perm) {
                            $permSafe = str_replace("/", ".", str_replace("-", "_", $perm));
                            ?>
                                <p><label for="perm-<?php echo $permSafe; ?>">
                                    <input type="checkbox"
                                           class="api-popup-access-checkbox"
                                           data-perm-safe="<?php echo $permSafe; ?>"
                                           id="perm-<?php echo $permSafe; ?>">
                                    <?php echo I18N::resolveHTML(
                                        "setting.permissions.level.{$permSafe}.name"
                                    ); ?>
                                </label>
                                <span class="tooltip">
                                    <i class="content-fas fas fa-question-circle"></i>
                                    <span>
                                        <?php echo I18N::resolveHTML(
                                            "setting.permissions.level.{$permSafe}.desc"
                                        ); ?>
                                    </span>
                                </span></p>
                            <?php
                        }
                    ?>
                </div>
            </div>
            <div class="cover-button-spacer"></div>
            <div class="pure-g">
                <div class="pure-u-1-2 right-align">
                    <span id="api-popup-access-cancel"
                          class="button-standard split-button button-spaced left">
                                <?php echo I18N::resolveHTML("ui.button.cancel"); ?>
                    </span>
                </div>
                <div class="pure-u-1-2">
                    <span id="api-popup-access-close"
                          class="button-submit split-button button-spaced right">
                                <?php echo I18N::resolveHTML("ui.button.save"); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    /*
        Default API client color for new API clients.
    */
    var defaultColor = "<?php echo APIClient::DEFAULT_COLOR; ?>";
</script>

<!--
    This page contains a potentially large table, so we should enable sorting
    for it.
-->
<script src="./js/table-utils.js?t=<?php
    echo filemtime(__DIR__."/../../admin/js/table-utils.js");
?>"></script>
<!--
    /admin/js/users.js contains additional functionality for this page.
-->
<script src="./js/api.js?t=<?php
    echo filemtime(__DIR__."/../../admin/js/api.js");
?>"></script>
