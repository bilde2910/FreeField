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
            $groups = Auth::listPermissionLevels();

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
            <tbody>
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
                                           max="250"
                                           name="g<?php echo $gid; ?>[level]"
                                           value="<?php echo $group["level"]; ?>"
                                           <?php if (!Auth::getCurrentUser()->canChangeAtPermission($group["level"])) echo ' disabled'; ?>>
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
                                            <?php if (!Auth::getCurrentUser()->canChangeAtPermission($group["level"])) echo ' disabled'; ?>>
                                        <option value="none" selected>
                                            <?php echo I18N::resolveHTML("admin.section.groups.group_list.action.none"); ?>
                                        </option>
                                        <option value="delete">
                                            <?php echo I18N::resolveHTML("admin.section.groups.group_list.action.delete"); ?>
                                        </option>
                                    </select>
                                </td>
                            </td>
                        <?php
                    }
                ?>
            </tbody>
        </table>
        <script>
            /*
                Handle changes to the Actions down-down for groups. If the
                "delete" action is selected, the box should be re-styled to make
                it very obvious that the group will be deleted (i.e. it
                shouldn't be possible to do it by accident). Setting the border
                and text color to red should draw enough attention to the box
                that accidental deletions doesn't happen (or at least happens
                very rarely).
            */
            $(".group-actions").on("change", function() {
                if ($(this).val() == "delete") {
                    $(this).css("border", "1px solid red");
                    $(this).css("color", "red");
                    $(this).css("margin-right", "");
                } else {
                    $(this).css("border", "");
                    $(this).css("color", "");
                    $(this).css("margin-right", "");
                }
            });

            /*
                If the group color is changed, then the checkbox that sets the
                color to non-null should be set, since the user wants a color
                for the group. Vice versa, unchecking the checkbox should reset
                the color selector.
            */
            $(".group-color-selector > input[type=color]").on("change", function() {
                $(this).parent().find("input[type=checkbox]").prop("checked", true);
            });
            $(".group-color-selector > input[type=checkbox]").on("change", function() {
                if (!$(this).is(":checked")) {
                    $(this).parent().find("input[type=color]").val("#000000");
                }
            });
        </script>
        <p class="buttons">
            <input type="submit"
                   class="button-submit"
                   value="<?php echo I18N::resolveHTML("ui.button.save"); ?>">
        </p>
    </form>
</div>
