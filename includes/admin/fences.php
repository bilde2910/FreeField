<?php
/*
    Displayed on the admin page /admin/index.php when editing user geofences.
*/
?>
<div class="content">
    <form action="apply-fences.php"
          method="POST"
          class="pure-form require-validation"
          enctype="application/x-www-form-urlencoded">
        <!--
            Protection against CSRF
        -->
        <?php echo Security::getCSRFInputField(); ?>
        <?php
            /*
                Get a list of available geofences from the configuration file.
            */
            $fences = Geo::listGeofences();

            /*
                Sort the geofences list in ascending order by their names.
            */
            usort($fences, function($a, $b) {
                if ($a->getLabel() == $b->getLabel()) return 0;
                return strcmp($a->getLabel(), $b->getLabel()) < 0 ? -1 : 1;
            });
        ?>
        <h2 class="content-subhead">
            <?php echo I18N::resolveHTML("admin.section.fences.fence_list.name"); ?>
        </h2>
        <table class="pure-table force-fullwidth">
            <thead>
                <tr>
                    <!--
                        Table header defining columns for:

                            1.  Changing the geofence label (text box)
                            2.  Modifying the geofence vertex list (text area)
                            5.  Taking actions on the geofence (e.g. deleting
                                it)
                    -->
                    <th><?php echo I18N::resolveHTML("admin.table.fences.fence_list.column.label.name"); ?></th>
                    <th style="width: 50%;"><?php echo I18N::resolveHTML("admin.table.fences.fence_list.column.vertices.name"); ?></th>
                    <th><?php echo I18N::resolveHTML("admin.table.fences.fence_list.column.actions.name"); ?></th>
                </tr>
            </thead>
            <tbody id="fence-list">
                <?php
                    foreach ($fences as $fence) {
                        $fid = htmlspecialchars($fence->getID(), ENT_QUOTES);
                        ?>
                            <tr class="fence-instance"
                                data-fence-id="<?php echo $fid; ?>">
                                <td>
                                    <input type="text"
                                           name="fence_<?php echo $fid; ?>[label]"
                                           value="<?php echo $fence->getLabel(); ?>">
                                </td>
                                <td>
                                    <textarea data-validate-as="geofence"
                                              name="fence_<?php echo $fid; ?>[vertices]"
                                              ><?php echo $fence->getVerticesAsString(); ?></textarea>
                                </td>
                                <td>
                                    <select class="fence-actions"
                                            name="fence_<?php echo $fid; ?>[action]">
                                        <option value="none" selected>
                                            <?php echo I18N::resolveHTML("admin.clientside.fences.fence_list.action.none"); ?>
                                        </option>
                                        <option value="delete">
                                            <?php echo I18N::resolveHTML("admin.clientside.fences.fence_list.action.delete"); ?>
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
                   id="geofence-new"
                   class="button-standard"
                   value="<?php echo I18N::resolveHTML("admin.section.fences.ui.add.name"); ?>">
            <input type="submit"
                   class="button-submit"
                   value="<?php echo I18N::resolveHTML("ui.button.save"); ?>">
        </p>
    </form>
</div>
<script type="text/javascript" src="../js/clientside-i18n.php"></script>
<!--
    /admin/js/fences.js contains additional functionality for this page.
-->
<script type="text/javascript" src="./js/fences.js"></script>
