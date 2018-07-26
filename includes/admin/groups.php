<div class="content wide-content">
    <form action="apply-groups.php" method="POST" class="pure-form" enctype="application/x-www-form-urlencoded">
        <?php
            $groups = Auth::listPermissionLevels();
            usort($groups, function($a, $b) {
                if ($a["level"] == $b["level"]) return 0;
                return $a["level"] > $b["level"] ? -1 : 1;
            });
        ?>
        <h2 class="content-subhead"><?php echo I18N::resolve("admin.section.groups.group_list.name"); ?></h2>
        <table class="pure-table force-fullwidth">
            <thead>
                <tr>
                    <th><?php echo I18N::resolve("admin.table.groups.group_list.column.group_name.name"); ?></th>
                    <th><?php echo I18N::resolve("admin.table.groups.group_list.column.change_name.name"); ?></th>
                    <th><?php echo I18N::resolve("admin.table.groups.group_list.column.permission.name"); ?></th>
                    <th><?php echo I18N::resolve("admin.table.groups.group_list.column.color.name"); ?></th>
                    <th><?php echo I18N::resolve("admin.table.groups.group_list.column.actions.name"); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                    foreach ($groups as $group) {
                        $gid = $group["group_id"];
                        ?>
                            <tr>
                                <td<?php if ($group["color"] !== null) echo ' style="color: #'.$group["color"].';"'; ?>><?php echo Auth::resolvePermissionLabelI18N($group["label"]); ?></td>
                                <td><input type="text" name="g<?php echo $gid; ?>[label]" value="<?php echo $group["label"]; ?>"<?php if (!Auth::getCurrentUser()->canChangeAtPermission($group["level"])) echo ' disabled'; ?>></td>
                                <td><input type="number" min="0" max="250" name="g<?php echo $gid; ?>[level]" value="<?php echo $group["level"]; ?>"<?php if (!Auth::getCurrentUser()->canChangeAtPermission($group["level"])) echo ' disabled'; ?>></td>
                                <td class="no-wrap group-color-selector" data-id="g<?php echo $gid; ?>">
                                    <input type="checkbox" id="g<?php echo $gid; ?>-usecolor" name="g<?php echo $gid; ?>[usecolor]"<?php if ($group["color"] !== null) echo ' checked'; ?><?php if (!Auth::getCurrentUser()->canChangeAtPermission($group["level"])) echo ' disabled'; ?>>
                                    <input type="color" name="g<?php echo $gid; ?>[color]"<?php if ($group["color"] !== null) echo ' value="#'.$group["color"].'"'; ?><?php if (!Auth::getCurrentUser()->canChangeAtPermission($group["level"])) echo ' disabled'; ?>>
                                </td>
                                <td><select class="group-actions" name="g<?php echo $gid; ?>[action]"<?php if (!Auth::getCurrentUser()->canChangeAtPermission($group["level"])) echo ' disabled'; ?>>
                                    <option value="none" selected><?php echo I18N::resolve("admin.section.groups.group_list.action.none"); ?></option>
                                    <option value="delete"><?php echo I18N::resolve("admin.section.groups.group_list.action.delete"); ?></option>
                                </select></td>
                            </td>
                        <?php
                    }
                ?>
            </tbody>
        </table>
        <script>
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
            $(".group-color-selector > input[type=color]").on("change", function() {
                $(this).parent().find("input[type=checkbox]").prop("checked", true);
            });
            $(".group-color-selector > input[type=checkbox]").on("change", function() {
                if (!$(this).is(":checked")) {
                    $(this).parent().find("input[type=color]").val("#000000");
                }
            });
        </script>
        <p class="buttons"><input type="submit" class="button-submit" value="<?php echo I18N::resolve("ui.button.save"); ?>"></p>
    </form>
</div>
