<div class="content wide-content">
    <form action="apply-users.php" method="POST" class="pure-form" enctype="application/x-www-form-urlencoded">
        <?php
            $users = Auth::listUsers();
            usort($users, function($a, $b) {
                if ($a->getPermissionLevel() == $b->getPermissionLevel()) return 0;
                return $a->getPermissionLevel() > $b->getPermissionLevel() ? -1 : 1;
            });
            $usersWithoutApproval = false;
            foreach ($users as $user) {
                if (!$user->isApproved()) $usersWithoutApproval = true;
            }
        ?>
        <?php if ($usersWithoutApproval) { ?>
            <h2 class="content-subhead"><?php echo I18N::resolve("admin.section.users.require_approval.name"); ?></h2>
            <table class="pure-table force-fullwidth">
                <thead>
                    <tr>
                        <th><?php echo I18N::resolve("admin.table.users.user_list.column.provider_identity.name"); ?></th>
                        <th><?php echo I18N::resolve("admin.table.users.user_list.column.provider.name"); ?></th>
                        <th><?php echo I18N::resolve("admin.table.users.user_list.column.auto_nickname.name"); ?></th>
                        <th><?php echo I18N::resolve("admin.table.users.user_list.column.registered.name"); ?></th>
                        <th><?php echo I18N::resolve("admin.table.users.user_list.column.actions.name"); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                        foreach ($users as $user) {
                            if ($user->isApproved()) continue;
                            $uid = $user->getUserID();
                            ?>
                                <tr>
                                    <td><?php echo $user->getProviderIdentity(); ?></td>
                                    <td><?php echo I18N::resolve("admin.section.auth.".$user->getProvider().".name"); ?></td>
                                    <td><?php echo htmlentities($user->getNickname()); ?></td>
                                    <td><?php echo $user->getRegistrationDate(); ?></td>
                                    <td><select class="account-actions" name="<?php echo $uid; ?>[action]">
                                        <option value="none" selected><?php echo I18N::resolve("admin.section.users.user_list.action.none"); ?></option>
                                        <option value="approve"><?php echo I18N::resolve("admin.section.users.user_list.action.approve"); ?></option>
                                        <option value="delete"><?php echo I18N::resolve("admin.section.users.user_list.action.reject"); ?></option>
                                    </select></td>
                                </td>
                            <?php
                        }
                    ?>
                </tbody>
            </table>
        <?php } ?>
        <h2 class="content-subhead"><?php echo I18N::resolve("admin.section.users.user_list.name"); ?></h2>
        <table class="pure-table force-fullwidth">
            <thead>
                <tr>
                    <th><?php echo I18N::resolve("admin.table.users.user_list.column.provider_identity.name"); ?></th>
                    <th><?php echo I18N::resolve("admin.table.users.user_list.column.provider.name"); ?></th>
                    <th><?php echo I18N::resolve("admin.table.users.user_list.column.nickname.name"); ?></th>
                    <!--<th>Last login</th>-->
                    <th><?php echo I18N::resolve("admin.table.users.user_list.column.group.name"); ?></th>
                    <th><?php echo I18N::resolve("admin.table.users.user_list.column.actions.name"); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                    foreach ($users as $user) {
                        if (!$user->isApproved()) continue;
                        $uid = $user->getUserID();
                        ?>
                            <tr>
                                <td<?php if ($user->getColor() !== null) echo ' style="color: #'.$user->getColor().';"'; ?>><?php echo $user->getProviderIdentity(); ?></td>
                                <td><?php echo I18N::resolve("admin.section.auth.".$user->getProvider().".name"); ?></td>
                                <td><input type="text" name="<?php echo $uid; ?>[nick]" value="<?php echo $user->getNickname(); ?>"<?php if (!Auth::getCurrentUser()->canChangeAtPermission($user->getPermissionLevel())) echo ' disabled'; ?>></td>
                                <!--<td><?php /*echo $user->getLastLoginDate();*/ ?></td>-->
                                <td><?php echo Auth::getPermissionSelector($uid."[group]", null, $user->getPermissionLevel()); ?></td>
                                <td><select class="account-actions" name="<?php echo $uid; ?>[action]"<?php if (!Auth::getCurrentUser()->canChangeAtPermission($user->getPermissionLevel())) echo ' disabled'; ?>>
                                    <option value="none" selected><?php echo I18N::resolve("admin.section.users.user_list.action.none"); ?></option>
                                    <option value="delete"><?php echo I18N::resolve("admin.section.users.user_list.action.delete"); ?></option>
                                </select></td>
                            </td>
                        <?php
                    }
                ?>
            </tbody>
        </table>
        <script>
            $(".account-actions").on("change", function() {
                if ($(this).val() == "delete") {
                    $(this).css("border", "1px solid red");
                    $(this).css("color", "red");
                    $(this).css("margin-right", "");
                } else if ($(this).val() == "approve") {
                    var color = "<?php echo Config::get("themes/color/admin"); ?>" == "dark" ? "lime" : "green";
                    $(this).css("border", "1px solid " + color);
                    $(this).css("color", color);
                    $(this).css("margin-right", "");
                } else {
                    $(this).css("border", "");
                    $(this).css("color", "");
                    $(this).css("margin-right", "");
                }
            });
        </script>
        <p class="buttons"><input type="submit" class="button-submit" value="<?php echo I18N::resolve("ui.button.save"); ?>"></p>
    </form>
</div>
