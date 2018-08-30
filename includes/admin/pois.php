<?php
/*
    Displayed on the admin page /admin/index.php when listing and editing POIs.
*/
?>
<div class="content wide-content">
    <form action="apply-pois.php"
          method="POST"
          class="pure-form"
          enctype="application/x-www-form-urlencoded">
        <?php
            /*
                Get a list of available POIs from the database.

                This function returns an array of instances of the POI class
                from /includes/lib/geo.php. Please see that file for class
                implementation details.
            */
            $pois = Geo::listPOIs();

            /*
                Sort the POI list in ascending order by their name.
            */
            usort($pois, function($a, $b) {
                if ($a->getName() == $b->getName()) return 0;
                return strcmp($a->getName(), $b->getName()) < 0 ? -1 : 1;
            });
        ?>
        <h2 class="content-subhead">
            <?php echo I18N::resolveHTML("admin.section.pois.poi_list.name"); ?>
        </h2>
        <table class="pure-table force-fullwidth">
            <thead>
                <tr>
                    <!--
                        Table header defining columns for:

                            1.  Changing the group name (text box)
                            2.  Showing the date and time the POI was created
                            3.  Showing the name of the user who created the POI
                            4.  Showing the current research task reported
                            5.  Showing the date and time field research was
                                last reported on the POI
                            6.  Showing the name of the user who made that field
                                research report
                            7.  Showing the coordinates of the POI and a link to
                                display the POI on a mapping service
                            8.  Taking actions on the POI (e.g. deleting it)
                    -->
                    <th><?php echo I18N::resolveHTML("admin.table.pois.poi_list.column.poi_name.name"); ?></th>
                    <th><?php echo I18N::resolveHTML("admin.table.pois.poi_list.column.created_time.name"); ?></th>
                    <th><?php echo I18N::resolveHTML("admin.table.pois.poi_list.column.created_by.name"); ?></th>
                    <th><?php echo I18N::resolveHTML("admin.table.pois.poi_list.column.current_research.name"); ?></th>
                    <th><?php echo I18N::resolveHTML("admin.table.pois.poi_list.column.last_updated_time.name"); ?></th>
                    <th><?php echo I18N::resolveHTML("admin.table.pois.poi_list.column.last_updated_by.name"); ?></th>
                    <th><?php echo I18N::resolveHTML("admin.table.pois.poi_list.column.location.name"); ?></th>
                    <th><?php echo I18N::resolveHTML("admin.table.pois.poi_list.column.actions.name"); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                    foreach ($pois as $poi) {
                        $pid = $poi->getID();
                        $icons = Theme::getIconSet(null, Config::get("themes/color/admin"));
                        ?>
                            <tr>
                                <td>
                                    <input type="text"
                                           name="p<?php echo $pid; ?>[name]"
                                           value="<?php echo $poi->getNameHTML(); ?>">
                                </td>
                                <td><?php echo $poi->getTimeCreatedString(); ?></td>
                                <?php /*
                                    The identities of the creator and field
                                    research reporter are both displayed as the
                                    nickname, colored with the user's group's
                                    color, followed by a smaller string showing
                                    the authentication provider and provider
                                    identity (human readable identity of the
                                    user as it appears on their account at the
                                    authentication provider).
                                */ ?>
                                <td style="line-height: 1.2em;">
                                    <?php echo $poi->getCreator()->getNicknameHTML(); ?>
                                    <br />
                                    <span class="user-box-small no-wrap">
                                        <?php echo $poi->getCreator()->getProviderIdentityHTML(); ?>
                                    </span>
                                </td>
                                <?php /*
                                    The research objective and reward are
                                    displayed as icons, rather than the full
                                    human-readable text, to save space in the
                                    table. The table is pretty wide already, and
                                    requires at least a 1920px wide display to
                                    properly show all of its columns.

                                    Sidenote: This is a nightmare on mobile, and
                                    if anyone has any suggestions on how to
                                    improve this display on mobile clients,
                                    please let me know by making an issue/pull
                                    request.
                                */ ?>
                                <td class="no-wrap">
                                    <img class="poi-table-marker"
                                         src="<?php echo $icons->getIconUrl($poi->getCurrentObjective()["type"]); ?>">
                                    <img class="poi-table-marker"
                                         src="<?php echo $icons->getIconUrl($poi->getCurrentReward()["type"]); ?>">
                                </td>
                                <td><?php echo $poi->getLastUpdatedString(); ?></td>
                                <td style="line-height: 1.2em;">
                                    <?php echo $poi->getLastUser()->getNicknameHTML(); ?>
                                    <br />
                                    <span class="user-box-small no-wrap">
                                        <?php echo $poi->getLastUser()->getProviderIdentityHTML(); ?>
                                    </span>
                                </td>
                                <?php
                                    $naviUrl =
                                        str_replace("{%LAT%}", urlencode($poi->getLatitude()),
                                        str_replace("{%LON%}", urlencode($poi->getLongitude()),
                                        str_replace("{%NAME%}", urlencode($poi->getName()),
                                            Geo::listNavigationProviders()[
                                                Config::get("map/provider/directions")
                                            ]
                                        )));
                                ?>
                                <td>
                                    <a target="_blank" href="<?php echo $naviUrl; ?>">
                                        <?php echo Geo::getLocationString($poi->getLatitude(), $poi->getLongitude()); ?>
                                    </a>
                                </td>
                                <td><select class="poi-actions" name="p<?php echo $pid; ?>[action]">
                                    <option value="none" selected>
                                        <?php echo I18N::resolveHTML("admin.section.pois.poi_list.action.none"); ?>
                                    </option>
                                    <!-- "Clear" resets the field research back to "unknown" -->
                                    <option value="clear">
                                        <?php echo I18N::resolveHTML("admin.section.pois.poi_list.action.clear"); ?>
                                    </option>
                                    <option value="delete">
                                        <?php echo I18N::resolveHTML("admin.section.pois.poi_list.action.delete"); ?>
                                    </option>
                                </select></td>
                            </td>
                        <?php
                    }
                ?>
            </tbody>
        </table>
        <script>
            /*
                Handle changes to the Actions down-down for POIs. If the
                "delete" action is selected, the box should be re-styled to make
                it very obvious that the POI will be deleted (i.e. it shouldn't
                be possible to do it by accident). Setting the border and text
                color to red should draw enough attention to the box that
                accidental deletions doesn't happen (or at least happens very
                rarely). The same is done for the action that clears the field
                research task currently reported on the POI.
            */
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
        </script>
        <p class="buttons">
            <input type="submit"
                   class="button-submit"
                   value="<?php echo I18N::resolveHTML("ui.button.save"); ?>">
        </p>
    </form>
</div>
