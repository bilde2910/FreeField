<?php
/*
    Displayed on the admin page /admin/index.php when listing and editing users.
*/
?>
<div class="content wide-content">
    <form action="apply-users.php"
          method="POST"
          class="pure-form limit-inputs"
          enctype="application/x-www-form-urlencoded">
        <!--
            Protection against CSRF
        -->
        <?php echo Security::getCSRFInputField(); ?>
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
                                </tr>
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
                    $cu = Auth::getCurrentUser();
                    foreach ($users as $user) {
                        if (!$user->isApproved()) continue;
                        $uid = $user->getUserID();
                        $uidHTML = htmlspecialchars($uid, ENT_QUOTES);
                        /*
                            Disable some controls if the current user has
                            permission to change their own rank, as a precaution
                            against accidentally demoting or deleting their own
                            account.
                        */
                        $protectActions = $cu->getUserID() == $uid && $cu->canChangeAtPermission($cu->getPermissionLevel());
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
                                    <?php
                                        /*
                                            Disable control if the current user has permission
                                            to change their own rank, as a precaution against
                                            accidentally demoting themselves.
                                        */
                                        echo Auth::getPermissionSelector($uidHTML."[group]", null, $user->getPermissionLevel(), $protectActions);
                                        if ($protectActions) {
                                            ?>
                                                <small><a class="user-unlock">
                                                    <?php echo I18N::resolveArgsHTML(
                                                        "admin.section.users.user_list.unlock",
                                                        false,
                                                        '<i class="fas fa-unlock"></i>'
                                                    ); ?>
                                                </a></small>
                                            <?php
                                        }
                                    ?>
                                </td>
                                <td>
                                    <select class="account-actions"
                                            name="<?php echo $uidHTML; ?>[action]"
                                            <?php if ($protectActions || !$cu->canChangeAtPermission($user->getPermissionLevel())) echo ' disabled'; ?>>
                                        <option value="none" selected>
                                            <?php echo I18N::resolveHTML("admin.section.users.user_list.action.none"); ?>
                                        </option>
                                        <option value="delete">
                                            <?php echo I18N::resolveHTML("admin.section.users.user_list.action.delete"); ?>
                                        </option>
                                        <option value="invalidate">
                                            <?php echo I18N::resolveHTML("admin.section.users.user_list.action.invalidate"); ?>
                                        </option>
                                    </select>
                                    <?php
                                        if ($protectActions) {
                                            ?>
                                                <small><a class="user-unlock">
                                                    <?php echo I18N::resolveArgsHTML(
                                                        "admin.section.users.user_list.unlock",
                                                        false,
                                                        '<i class="fas fa-unlock"></i>'
                                                    ); ?>
                                                </a></small>
                                            <?php
                                        }
                                    ?>
                                </td>
                            </tr>
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
                } else if ($(this).val() == "invalidate") {
                    $(this).css("border", "1px solid darkorange");
                    $(this).css("color", "darkorange");
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

                This must be set manually on submit because the form on this
                page does not use `require-validation`. Forms that use
                `require-validation` have this handled automatically by the
                validation script. Please see the end of the /admin/index.php
                script for more information.
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
<!--
    This page contains a significant amount of input fields. We can (and should)
    cut down on the number of fields that are submitted to the server to avoid
    hitting the server-side `max_input_vars` limit of 1000.
-->
<script src="./js/limit-inputs.js"></script>
<!--
    /admin/js/users.js contains additional functionality for this page.
-->
<script src="./js/users.js"></script>
