<?php
/*
    Displayed on the admin page /admin/index.php when editing user groups.
*/
?>
<div class="content wide-content">
    <form action="apply-groups.php"
          method="POST"
          class="pure-form"
          enctype="application/x-www-form-urlencoded">
        <!--
            Protection against CSRF
        -->
        <?php echo Security::getCSRFInputField(); ?>
        <?php
            /*
                Get a list of available groups from the database.

                Each group is stored in a database with the following structure:

                  - `group_id` INT
                  - `level` SMALLINT
                  - `label` VARCHAR(64)
                  - `color` CHAR(6)

                That same structure is available in the arrays in `$groups`.
            */
            $groups = Auth::listGroups();

            /*
                Sort the group list in descending order by their permission
                level, so higher ranked groups appear first in the list.
            */
            usort($groups, function($a, $b) {
                if ($a["level"] == $b["level"]) return 0;
                return $a["level"] > $b["level"] ? -1 : 1;
            });
        ?>
        <h2 class="content-subhead">
            <?php echo I18N::resolveHTML("admin.section.groups.group_list.name"); ?>
        </h2>
        <table class="pure-table force-fullwidth">
            <thead>
                <tr>
                    <!--
                        Table header defining columns for:

                            1.  Displaying the group name
                            2.  Changing the group name (text box)
                            3.  Changing the permission level (select box)
                            4.  Changing or disabling the color (checkbox +
                                color picker)
                            5.  Taking actions on the group (e.g. deleting it)
                    -->
                    <th><?php echo I18N::resolveHTML("admin.table.groups.group_list.column.group_name.name"); ?></th>
                    <th><?php echo I18N::resolveHTML("admin.table.groups.group_list.column.change_name.name"); ?></th>
                    <th><?php echo I18N::resolveHTML("admin.table.groups.group_list.column.permission.name"); ?></th>
                    <th><?php echo I18N::resolveHTML("admin.table.groups.group_list.column.color.name"); ?></th>
                    <th><?php echo I18N::resolveHTML("admin.table.groups.group_list.column.actions.name"); ?></th>
                </tr>
            </thead>
            <tbody id="group-list">
                <?php
                    foreach ($groups as $group) {
                        $gid = $group["group_id"];
                        ?>
                            <tr>
                                <!--
                                    The group name may contain an I18N token,
                                    hence the name should be resolved by the
                                    `Auth::resolvePermissionLabelI18NHTML()`
                                    function from /includes/lib/auth.php. That
                                    function will replace {i18n:*} tokens with
                                    localized strings.
                                -->
                                <td<?php if ($group["color"] !== null) echo ' style="color: #'.$group["color"].';"'; ?>>
                                    <?php echo Auth::resolvePermissionLabelI18NHTML($group["label"]); ?>
                                </td>
                                <!--
                                    If the group has a permission level at or
                                    higher than the current user, the user
                                    should not be able to make changes to it.
                                -->
                                <td>
                                    <input type="text"
                                           name="g<?php echo $gid; ?>[label]"
                                           value="<?php echo $group["label"]; ?>"
                                           <?php if (!Auth::getCurrentUser()->canChangeAtPermission($group["level"])) echo ' disabled'; ?>>
                                </td>
                                <td>
                                    <input type="number"
                                           min="0"
                                           max="<?php
                                                /*
                                                    Cap the group level at the highest level the
                                                    current user can modify, or the group level
                                                    itself, whichever is higher.
                                                */
                                                echo max(
                                                $group["level"],
                                                Auth::getCurrentUser()->getPermissionLevel() - (
                                                    Auth::getCurrentUser()->hasPermission("admin/groups/self-manage") ? 0 : 1
                                                )
                                           ); ?>"
                                           name="g<?php echo $gid; ?>[level]"
                                           class="group-level"
                                           value="<?php echo $group["level"]; ?>"
                                           <?php if ($group["level"] == 0 || !Auth::getCurrentUser()->canChangeAtPermission($group["level"])) echo ' disabled'; ?>>
                                </td>
                                <td class="no-wrap group-color-selector" data-id="g<?php echo $gid; ?>">
                                    <!--
                                        The checkbox here allows specifying that
                                        the group should not have a color. Color
                                        inputs cannot be set to null (they'll be
                                        set to #000000 instead), so unchecking
                                        the checkbox will be treated server-side
                                        as a null value.
                                    -->
                                    <input type="checkbox"
                                           id="g<?php echo $gid; ?>-usecolor"
                                           name="g<?php echo $gid; ?>[usecolor]"
                                           <?php if ($group["color"] !== null) echo ' checked'; ?>
                                           <?php if (!Auth::getCurrentUser()->canChangeAtPermission($group["level"])) echo ' disabled'; ?>>
                                    <input type="color"
                                           name="g<?php echo $gid; ?>[color]"
                                           <?php if ($group["color"] !== null) echo ' value="#'.$group["color"].'"'; ?>
                                           <?php if (!Auth::getCurrentUser()->canChangeAtPermission($group["level"])) echo ' disabled'; ?>>
                                </td>
                                <td>
                                    <select class="group-actions"
                                            name="g<?php echo $gid; ?>[action]"
                                            <?php if ($group["level"] == 0 || !Auth::getCurrentUser()->canChangeAtPermission($group["level"])) echo ' disabled'; ?>>
                                        <option value="none" selected>
                                            <?php echo I18N::resolveHTML("admin.clientside.groups.group_list.action.none"); ?>
                                        </option>
                                        <option value="delete">
                                            <?php echo I18N::resolveHTML("admin.clientside.groups.group_list.action.delete"); ?>
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
                   id="group-new"
                   class="button-standard"
                   value="<?php echo I18N::resolveHTML("admin.section.groups.ui.add.name"); ?>">
            <input type="submit"
                   class="button-submit"
                   value="<?php echo I18N::resolveHTML("ui.button.save"); ?>">
        </p>
    </form>
</div>
<!--
    /admin/js/groups.js contains additional functionality for this page.
-->
<script src="./js/groups.js?t=<?php
    echo filemtime(__DIR__."/../../admin/js/groups.js");
?>"></script>
