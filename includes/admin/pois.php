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
