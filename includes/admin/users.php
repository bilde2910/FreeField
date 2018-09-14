<?php
/*
    Displayed on the admin page /admin/index.php when listing and editing users.
*/
?>
<div class="content wide-content">
    <form action="apply-users.php"
          method="POST"
          class="pure-form"
          enctype="application/x-www-form-urlencoded">
        <?php
            /*
                Get a list of available users from the database.

                This function returns an array of instances of the User class
                from /includes/lib/auth.php. Please see that file for class
                implementation details.
            */
            $users = Auth::listUsers();

            /*
                Sort the user list in descending order by their group
                membership, so higher ranked users appear first in the list.
            */
            usort($users, function($a, $b) {
                if ($a->getPermissionLevel() == $b->getPermissionLevel()) return 0;
                return $a->getPermissionLevel() > $b->getPermissionLevel() ? -1 : 1;
            });

            /*
                Check if any users are not approved. Unapproved users are
                displayed in a separate list which should be hidden if there are
                no users that require approval.
            */
            $usersWithoutApproval = false;
            foreach ($users as $user) {
                if (!$user->isApproved()) $usersWithoutApproval = true;
            }
        ?>
        <?php if ($usersWithoutApproval) { ?>
            <!--
                List of users which require approval of their user account
            -->
            <h2 class="content-subhead">
                <?php echo I18N::resolveHTML("admin.section.users.require_approval.name"); ?>
            </h2>
            <table class="pure-table force-fullwidth">
                <thead>
                    <tr>
                        <!--
                            Table header defining columns for:

                                1.  Showing the provider identity (human
                                    readable ID as provided by the user's
                                    authentication provider) of the user
                                2.  Showing the authentication provider used by
                                    the user
                                3.  Showing the nickname suggested for the user
                                4.  Showing the registration date of the user on
                                    FreeField
                                5.  Taking actions on the user (e.g. approving
                                    or rejecting their account)
                        -->
                        <th><?php echo I18N::resolveHTML("admin.table.users.user_list.column.provider_identity.name"); ?></th>
                        <th><?php echo I18N::resolveHTML("admin.table.users.user_list.column.provider.name"); ?></th>
                        <th><?php echo I18N::resolveHTML("admin.table.users.user_list.column.nickname.name"); ?></th>
                        <th><?php echo I18N::resolveHTML("admin.table.users.user_list.column.registered.name"); ?></th>
                        <th><?php echo I18N::resolveHTML("admin.table.users.user_list.column.actions.name"); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                        foreach ($users as $user) {
                            /*
                                If users are already approved, they obviously
                                shouldn't appear here, so skip them
                            */
                            if ($user->isApproved()) continue;
                            $uid = $user->getUserID();
                            $uidHTML = htmlspecialchars($uid, ENT_QUOTES);
                            ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($user->getProviderIdentity(), ENT_QUOTES); ?>
                                    </td>
                                    <td>
                                        <?php echo I18N::resolveHTML("admin.section.auth.".$user->getProvider().".name"); ?>
                                    </td>
                                    <td>
                                        <?php echo $user->getNicknameHTML(); ?>
                                    </td>
                                    <td>
                                        <?php echo $user->getRegistrationDate(); ?>
                                    </td>
                                    <td>
                                        <select class="account-actions" name="<?php echo $uidHTML; ?>[action]">
                                            <option value="none" selected>
                                                <?php echo I18N::resolveHTML("admin.section.users.user_list.action.none"); ?>
                                            </option>
                                            <option value="approve">
                                                <?php echo I18N::resolveHTML("admin.section.users.user_list.action.approve"); ?>
                                            </option>
                                            <option value="delete">
                                                <?php echo I18N::resolveHTML("admin.section.users.user_list.action.reject"); ?>
                                            </option>
                                        </select>
                                    </td>
                                </td>
                            <?php
                        }
                    ?>
                </tbody>
            </table>
        <?php } ?>
        <!--
            List of users whose accounts are already approved or which did not
            require approval to register
        -->
        <h2 class="content-subhead">
            <?php echo I18N::resolveHTML("admin.section.users.user_list.name"); ?>
        </h2>
        <table class="pure-table force-fullwidth">
            <thead>
                <tr>
                    <!--
                        Table header defining columns for:

                            1.  Showing the provider identity (human
                                readable ID as provided by the user's
                                authentication provider) of the user
                            2.  Showing the authentication provider used by
                                the user
                            3.  Changing the nickname of the user (text box)
                            4.  Changing the group membership of the user
                                (select box)
                            5.  Taking actions on the user (e.g. deleting their
                                account)
                    -->
                    <th><?php echo I18N::resolveHTML("admin.table.users.user_list.column.provider_identity.name"); ?></th>
                    <th><?php echo I18N::resolveHTML("admin.table.users.user_list.column.provider.name"); ?></th>
                    <th><?php echo I18N::resolveHTML("admin.table.users.user_list.column.nickname.name"); ?></th>
                    <!-- UNUSED: <th>Last login</th> -->
                    <th><?php echo I18N::resolveHTML("admin.table.users.user_list.column.group.name"); ?></th>
                    <th><?php echo I18N::resolveHTML("admin.table.users.user_list.column.actions.name"); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                    foreach ($users as $user) {
                        if (!$user->isApproved()) continue;
                        $uid = $user->getUserID();
                        $uidHTML = htmlspecialchars($uid, ENT_QUOTES);
                        ?>
                            <tr>
                                <td<?php if ($user->getColor() !== null) echo ' style="color: #'.$user->getColor().';"'; ?>>
                                    <?php echo htmlspecialchars($user->getProviderIdentity(), ENT_QUOTES); ?>
                                </td>
                                <td>
                                    <?php echo I18N::resolveHTML("admin.section.auth.".$user->getProvider().".name"); ?>
                                </td>
                                <!--
                                    If this user has a permission level at or
                                    higher than the one making changes here, no
                                    changes should be allowed to this user to
                                    prevent privilege escalation attacks.
                                -->
                                <td>
                                    <input type="text"
                                           name="<?php echo $uidHTML; ?>[nick]"
                                           value="<?php echo htmlspecialchars($user->getNickname(), ENT_QUOTES); ?>"
                                           <?php if (!Auth::getCurrentUser()->canChangeAtPermission($user->getPermissionLevel())) echo ' disabled'; ?>>
                                </td>
                                <!-- UNUSED: <td><?php /*echo $user->getLastLoginDate();*/ ?></td> -->
                                <td>
                                    <?php echo Auth::getPermissionSelector($uidHTML."[group]", null, $user->getPermissionLevel()); ?>
                                </td>
                                <td>
                                    <select class="account-actions"
                                            name="<?php echo $uidHTML; ?>[action]"
                                            <?php if (!Auth::getCurrentUser()->canChangeAtPermission($user->getPermissionLevel())) echo ' disabled'; ?>>
                                        <option value="none" selected>
                                            <?php echo I18N::resolveHTML("admin.section.users.user_list.action.none"); ?>
                                        </option>
                                        <option value="delete">
                                            <?php echo I18N::resolveHTML("admin.section.users.user_list.action.delete"); ?>
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
                Handle changes to the Actions down-down for users. If the
                "delete" action is selected, the box should be re-styled to make
                it very obvious that the user's account will be deleted (i.e. it
                shouldn't be possible to do it by accident). Setting the border
                and text color to red should draw enough attention to the box
                that accidental deletions doesn't happen (or at least happens
                very rarely). The same is done for the action that approves and
                rejects users (the latter being the same option as "delete",
                just with a different label on it).
            */
            $(".account-actions").on("change", function() {
                if ($(this).val() == "delete") {
                    $(this).css("border", "1px solid red");
                    $(this).css("color", "red");
                    $(this).css("margin-right", "");
                } else if ($(this).val() == "approve") {
                    var color = <?php echo Config::get("themes/color/admin")->valueJS(); ?> == "dark" ? "lime" : "green";
                    $(this).css("border", "1px solid " + color);
                    $(this).css("color", color);
                    $(this).css("margin-right", "");
                } else {
                    $(this).css("border", "");
                    $(this).css("color", "");
                    $(this).css("margin-right", "");
                }
            });

            /*
                Changes to inputs on the form are tracked to stop data being
                accidentally discarded if the user tries to navigate away from
                the page without saving the settings. Ensure that the warning
                isn't displayed if the user clicks on the submit button.
            */
            $("form").on("submit", function() {
                unsavedChanges = false;
            });
        </script>
        <p class="buttons">
            <input type="submit"
                   class="button-submit"
                   value="<?php echo I18N::resolveHTML("ui.button.save"); ?>">
        </p>
    </form>
</div>
