<?php

require_once("../includes/lib/global.php");
__require("config");
__require("auth");
__require("i18n");
__require("geo");
__require("theme");

// TODO: Webhooks

if (!isset($_GET["d"])) {
    header("HTTP/1.1 307 Temporary Redirect");
    header("Location: ./?d=main");
    exit;
}

$domains = array("main", "perms", "security", "auth", "themes", "map");
$pages_icons = array(
    "main" => "cog",
    "users" => "users",
    "groups" => "user-shield",
    "pois" => "map-marker-alt",
    "perms" => "check-square",
    "security" => "shield-alt",
    "auth" => "lock",
    "themes" => "palette",
    "map" => "map",
    "hooks" => "link"
);

if (!isset($_GET["d"]) || !in_array($_GET["d"], array_keys($pages_icons)) || !Auth::getCurrentUser()->hasPermission("admin/".$_GET["d"]."/general")) {
    $firstAuthorized = null;
    foreach ($pages_icons as $page => $icon) {
        if (Auth::getCurrentUser()->hasPermission("admin/{$page}/general")) {
            $firstAuthorized = $page;
            break;
        }
    }
    header("HTTP/1.1 307 Temporary Redirect");
    if ($firstAuthorized == null) {
        header("Location: ".Config::getEndpointUri("/"));
    } else {
        header("Location: ./?d={$firstAuthorized}");
    }
    exit;
}

$domain = $_GET["d"];

$di18n = Config::getDomainI18N($domain);
if (in_array($domain, $domains)) {
    $sections = Config::getTreeDomain($domain);
}

?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex,nofollow">
        <title>FreeField Admin | <?php echo I18N::resolve($di18n->getName()); ?></title>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js" integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8=" crossorigin="anonymous"></script>
        <link rel="stylesheet" href="https://unpkg.com/purecss@1.0.0/build/pure-min.css" integrity="sha384-nn4HPE8lTHyVtfCBi5yW9d20FjT8BJwUXyWZT9InLYax14RDjBj46LmSztkmNP9w" crossorigin="anonymous">
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.0.13/css/all.css" integrity="sha384-DNOHZ68U8hZfKXOrtjWvjxusGo9WQnrNx2sqG0tfsghAvtVlRW3tvkXWZh58N9jp" crossorigin="anonymous">
        <link rel="stylesheet" href="../css/main.css">
        <link rel="stylesheet" href="../css/<?php echo Config::get("themes/color/admin"); ?>.css">

        <!--[if lte IE 8]>
            <link rel="stylesheet" href="../css/layouts/side-menu-old-ie.css">
        <![endif]-->
        <!--[if gt IE 8]><!-->
            <link rel="stylesheet" href="../css/layouts/side-menu.css">
        <!--<![endif]-->
    </head>
    <body>
        <div id="layout">
            <!-- Menu toggle -->
            <a href="#menu" id="menuLink" class="menu-link">
                <!-- Hamburger icon -->
                <span></span>
            </a>

            <div id="menu">
                <div class="pure-menu">
                    <a class="pure-menu-heading" href="..">Freefield</a>

                    <ul class="pure-menu-list">
                        <div class="menu-user-box">
                            <span class="user-box-small"><?php echo I18N::resolve("sidebar.signed_in_as"); ?></span><br>
                            <span class="user-box-nick"><?php echo Auth::getCurrentUser()->getNicknameHTML(); ?></span><br />
                            <span class="user-box-small"><?php echo Auth::getCurrentUser()->getProviderIdentityHTML(); ?></span><br>
                        </div>
                        <li class="pure-menu-item"><a href="./auth/logout.php" class="pure-menu-link"><i class="menu-fas fas fa-sign-in-alt"></i> <?php echo I18N::resolve("sidebar.logout"); ?></a></li>
                        <div class="menu-spacer"></div>
                        <?php

                        foreach ($pages_icons as $d => $icon) {
                            if (!Auth::getCurrentUser()->hasPermission("admin/{$d}/general")) continue;
                            if ($d == $domain) {
                                echo '<li class="pure-menu-item menu-item-divided pure-menu-selected">';
                            } else {
                                echo '<li class="pure-menu-item">';
                            }

                            echo '<a href="./?d='.$d.'" class="pure-menu-link"><i class="menu-fas fas fa-'.$icon.'"></i> '.I18N::resolve(Config::getDomainI18N($d)->getName()).'</a></li>';
                        }

                        ?>

                        <div class="menu-spacer"></div>
                        <li class="pure-menu-item"><a href=".." class="pure-menu-link"><i class="menu-fas fas fa-angle-double-left"></i> <?php echo I18N::resolve("sidebar.return"); ?></a></li>
                    </ul>
                </div>
            </div>

            <div id="main">
                <div class="header">
                    <h1><?php echo I18N::resolve($di18n->getName()); ?></h1>
                    <h2><?php echo I18N::resolve($di18n->getDescription()); ?></h2>
                </div>

                <?php if (in_array($domain, $domains)) { ?>
                    <div class="content">
                        <form action="apply-config.php?d=<?php echo $domain; ?>" method="POST" class="pure-form" enctype="application/x-www-form-urlencoded">
                            <?php foreach ($sections as $section => $settings) { ?>
                                <h2 class="content-subhead"><?php echo I18N::resolve($di18n->getSection($section)->getName()); ?></h2>
                                <?php
                                    if (isset($settings["__hasdesc"]) && $settings["__hasdesc"]) {
                                        if (isset($settings["__descsprintf"])) {
                                            echo '<p>'.I18N::resolveArgs($di18n->getSection($section)->getDescription(), $settings["__descsprintf"]).'</p>';
                                        } else {
                                            echo '<p>'.I18N::resolve($di18n->getSection($section)->getDescription()).'</p>';
                                        }
                                    }
                                ?>
                                <?php foreach ($settings as $setting => $values) { ?>
                                    <?php
                                        if (substr($setting, 0, 2) === "__") continue;
                                        $si18n = Config::getSettingI18N($setting);
                                    ?>
                                    <div class="pure-g">
                                        <div class="pure-u-1-3 full-on-mobile">
                                            <p class="setting-name"><?php echo I18N::resolve($si18n->getName()); ?><span class="only-desktop">: <span class="tooltip"><i class="content-fas fas fa-question-circle"></i><span><?php echo I18N::resolve($si18n->getDescription()); ?></span></span></span></p>
                                            <p class="only-mobile"><?php echo I18N::resolve($si18n->getDescription()); ?></p>
                                        </div>
                                        <div class="pure-u-2-3 full-on-mobile">
                                            <p>
                                                <?php
                                                    if (isset($values["custom"])) {
                                                        $value = Config::get($setting);
                                                        echo CustomControls::getControl($values["custom"], $setting, $value, "field");
                                                    } else {
                                                        $matches = array();
                                                        if (is_array($values["options"])) {
                                                            echo '<select name="'.$setting.'">';
                                                            $value = Config::get($setting);
                                                            foreach ($values["options"] as $option) {
                                                                echo '<option value="'.$option.'"'.($value == $option ? ' selected' : '').'>'.I18N::resolve($si18n->getOption($option)).'</option>';
                                                            }
                                                            echo '</select>';

                                                        } elseif (preg_match('/^int,([\d-]+),([\d-]+)$/', $values["options"], $matches)) {
                                                            echo '<input type="number" name="'.$setting.'" min="'.$matches[1].'" max="'.$matches[2].'" value="'.Config::get($setting).'">';

                                                        } elseif (preg_match('/^float,([\d-]+),([\d-]+)$/', $values["options"], $matches)) {
                                                            echo '<input type="number" name="'.$setting.'" min="'.$matches[1].'" max="'.$matches[2].'" step="0.00001" value="'.Config::get($setting).'">';

                                                        } else {
                                                            switch ($values["options"]) {
                                                                case "string":
                                                                    echo '<input type="text" name="'.$setting.'" value="'.Config::get($setting).'">';
                                                                    break;
                                                                case "password":
                                                                    echo '<input type="password" name="'.$setting.'" value="'.Config::get($setting).'">';
                                                                    break;
                                                                case "int":
                                                                    echo '<input type="number" name="'.$setting.'" value="'.Config::get($setting).'">';
                                                                    break;
                                                                case "float":
                                                                    echo '<input type="number" name="'.$setting.'" step="0.00001" value="'.Config::get($setting).'">';
                                                                    break;
                                                                case "bool":
                                                                    echo '<input type="hidden" name="'.$setting.'" value="off">'; // Detect unchecked checkbox - unchecked checkboxes aren't POSTed!
                                                                    echo '<input type="checkbox" id="'.$setting.'" name="'.$setting.'"'.(Config::get($setting) ? ' checked' : '').'> <label for="'.$setting.'">'.I18N::resolve($si18n->getLabel()).'</label>';
                                                                    break;
                                                                case "permission":
                                                                    echo Auth::getPermissionSelector($setting, null, Config::get($setting));
                                                                    break;
                                                            }
                                                        }
                                                    }
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                    <?php
                                        if (isset($values["custom"])) {
                                            $control = CustomControls::getControl($values["custom"], $setting, $value, "after");
                                            if ($control !== null) {
                                                echo $control;
                                            }
                                        }
                                    ?>
                                <?php } ?>
                            <?php } ?>
                            <p class="buttons"><input type="submit" class="button-submit" value="<?php echo I18N::resolve("ui.button.save"); ?>"></p>
                        </form>
                    </div>
                <?php } elseif ($domain == "users") { ?>
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
                <?php } elseif ($domain == "groups") { ?>
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
                <?php } elseif ($domain == "pois") { ?>
                    <div class="content wide-content">
                        <form action="apply-pois.php" method="POST" class="pure-form" enctype="application/x-www-form-urlencoded">
                            <?php
                                $pois = Geo::listPOIs();

                                usort($pois, function($a, $b) {
                                    if ($a->getName() == $b->getName()) return 0;
                                    return strcmp($a->getName(), $b->getName()) < 0 ? -1 : 1;
                                });
                            ?>
                            <h2 class="content-subhead"><?php echo I18N::resolve("admin.section.pois.poi_list.name"); ?></h2>
                            <table class="pure-table force-fullwidth">
                                <thead>
                                    <tr>
                                        <th><?php echo I18N::resolve("admin.table.pois.poi_list.column.poi_name.name"); ?></th>
                                        <th><?php echo I18N::resolve("admin.table.pois.poi_list.column.created_time.name"); ?></th>
                                        <th><?php echo I18N::resolve("admin.table.pois.poi_list.column.created_by.name"); ?></th>
                                        <th><?php echo I18N::resolve("admin.table.pois.poi_list.column.current_research.name"); ?></th>
                                        <th><?php echo I18N::resolve("admin.table.pois.poi_list.column.last_updated_time.name"); ?></th>
                                        <th><?php echo I18N::resolve("admin.table.pois.poi_list.column.last_updated_by.name"); ?></th>
                                        <th><?php echo I18N::resolve("admin.table.pois.poi_list.column.location.name"); ?></th>
                                        <th><?php echo I18N::resolve("admin.table.pois.poi_list.column.actions.name"); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                        foreach ($pois as $poi) {
                                            $pid = $poi->getID();
                                            $icons = Theme::getIconSet(null, Config::get("themes/color/admin"));
                                            ?>
                                                <tr>
                                                    <td><input type="text" name="p<?php echo $pid; ?>[name]" value="<?php echo $poi->getName(); ?>"></td>
                                                    <td><?php echo $poi->getTimeCreatedString(); ?></td>
                                                    <td style="line-height: 1.2em;"><?php echo $poi->getCreator()->getNicknameHTML(); ?><br /><span class="user-box-small no-wrap"><?php echo $poi->getCreator()->getProviderIdentityHTML(); ?></span></td>
                                                    <td class="no-wrap">
                                                        <img class="poi-table-marker" src="<?php echo $icons->getIconUrl($poi->getCurrentObjective()["type"]); ?>">
                                                        <img class="poi-table-marker" src="<?php echo $icons->getIconUrl($poi->getCurrentReward()["type"]); ?>">
                                                    </td>
                                                    <td><?php echo $poi->getLastUpdatedString(); ?></td>
                                                    <td style="line-height: 1.2em;"><?php echo $poi->getLastUser()->getNicknameHTML(); ?><br /><span class="user-box-small no-wrap"><?php echo $poi->getLastUser()->getProviderIdentityHTML(); ?></span></td>
                                                    <td><a target="_blank" href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($poi->getLatitude().",".$poi->getLongitude()); ?>"><?php echo Geo::getLocationString($poi->getLatitude(), $poi->getLongitude()); ?></td>
                                                    <td><select class="poi-actions" name="p<?php echo $pid; ?>[action]">
                                                        <option value="none" selected><?php echo I18N::resolve("admin.section.pois.poi_list.action.none"); ?></option>
                                                        <option value="clear"><?php echo I18N::resolve("admin.section.pois.poi_list.action.clear"); ?></option>
                                                        <option value="delete"><?php echo I18N::resolve("admin.section.pois.poi_list.action.delete"); ?></option>
                                                    </select></td>
                                                </td>
                                            <?php
                                        }
                                    ?>
                                </tbody>
                            </table>
                            <script>
                                $(".poi-actions").on("change", function() {
                                    if ($(this).val() == "delete") {
                                        $(this).css("border", "1px solid red");
                                        $(this).css("color", "red");
                                        $(this).css("margin-right", "");
                                    } else if ($(this).val() == "clear") {
                                        $(this).css("border", "1px solid darkorange");
                                        $(this).css("color", "darkorange");
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
                <?php } ?>
            </div>
        </div>
        <script src="../js/ui.js"></script>
    </body>
</html>
