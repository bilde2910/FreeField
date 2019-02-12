<?php
/*
    Displayed on the admin page /admin/index.php when listing and editing POIs.
*/

__require("geo");
__require("research");

?>
<div class="content wide-content">
    <form action="apply-pois.php"
          method="POST"
          class="pure-form limit-inputs"
          enctype="application/x-www-form-urlencoded">
        <!--
            Protection against CSRF
        -->
        <?php echo Security::getCSRFInputField(); ?>
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
        <table class="pure-table force-fullwidth paginate">
            <thead>
                <tr>
                    <!--
                        Table header defining columns for:

                            1.  Changing the POI name (text box)
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
                    <th data-sort-function="input-value">
                        <?php echo I18N::resolveHTML("admin.table.pois.poi_list.column.poi_name.name"); ?>
                    </th>
                    <th data-sort-function="alphanumeric">
                        <?php echo I18N::resolveHTML("admin.table.pois.poi_list.column.created_time.name"); ?>
                    </th>
                    <th data-sort-function="alphanumeric">
                        <?php echo I18N::resolveHTML("admin.table.pois.poi_list.column.created_by.name"); ?>
                    </th>
                    <th data-sort-function="poi-dual-marker">
                        <?php echo I18N::resolveHTML("admin.table.pois.poi_list.column.current_research.name"); ?>
                    </th>
                    <th data-sort-function="alphanumeric">
                        <?php echo I18N::resolveHTML("admin.table.pois.poi_list.column.last_updated_time.name"); ?>
                    </th>
                    <th data-sort-function="alphanumeric">
                        <?php echo I18N::resolveHTML("admin.table.pois.poi_list.column.last_updated_by.name"); ?>
                    </th>
                    <th data-sort-function="numeric">
                        <?php echo I18N::resolveHTML("admin.table.pois.poi_list.column.location.name"); ?>
                    </th>
                    <th>
                        <?php echo I18N::resolveHTML("admin.table.pois.poi_list.column.actions.name"); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php
                    foreach ($pois as $poi) {
                        $pid = $poi->getID();
                        $icons = Theme::getIconSet(null, Config::get("themes/color/admin")->value());
                        ?>
                            <tr>
                                <td>
                                    <input type="text"
                                           name="p<?php echo $pid; ?>[name]"
                                           class="poi-name"
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
                                <?php
                                    /*
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
                                    */
                                    $objString = Research::resolveObjective(
                                        $poi->getCurrentObjective()["type"],
                                        $poi->getCurrentObjective()["params"]
                                    );
                                    $rewString = Research::resolveReward(
                                        $poi->getCurrentReward()["type"],
                                        $poi->getCurrentReward()["params"]
                                    );
                                ?>
                                <td class="no-wrap">
                                    <img class="poi-table-marker"
                                         src="<?php echo $icons->getIconUrl($poi->getCurrentObjective()["type"]); ?>"
                                         title="<?php echo htmlspecialchars($objString, ENT_QUOTES); ?>"
                                         alt="<?php echo htmlspecialchars($objString, ENT_QUOTES); ?>"
                                         data-marker-id="<?php echo $poi->getCurrentObjective()["type"]; ?>">
                                    <img class="poi-table-marker"
                                         src="<?php echo $icons->getIconUrl($poi->getCurrentReward()["type"]); ?>"
                                         title="<?php echo htmlspecialchars($rewString, ENT_QUOTES); ?>"
                                         alt="<?php echo htmlspecialchars($rewString, ENT_QUOTES); ?>"
                                         data-marker-id="<?php echo $poi->getCurrentReward()["type"]; ?>">
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
                                                Config::get("map/provider/directions")->value()
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
                            </tr>
                        <?php
                    }
                ?>
            </tbody>
        </table>
        <div class="paginate-outer">
            <div class="paginate-inner"></div>
        </div>
        <!--
            ============================================================
                CLEAR RESEARCH DATA SECTION
            ============================================================
        -->
        <h2 class="content-subhead">
            <?php echo I18N::resolveHTML("admin.section.pois.clear.name"); ?>
        </h2>
        <p>
            <?php echo I18N::resolveHTML("admin.section.pois.clear.desc"); ?>
        </p>
        <div class="pure-g">
            <div class="pure-u-1-3 full-on-mobile">
                <p class="setting-name">
                    <?php echo I18N::resolveHTML("admin.section.pois.clear.perform.name"); ?><span class="only-desktop">:</span>
                </p>
            </div>
            <div class="pure-u-2-3 full-on-mobile">
                <p>
                    <input type="checkbox"
                           id="clear-all-research"
                           name="clear-all-research">
                    <label for="clear-all-research">
                        <?php echo I18N::resolveHTML(
                            "admin.section.pois.clear.perform.label"
                        ); ?>
                    </label>
                </p>
            </div>
        </div>
        <?php if (Auth::getCurrentUser()->hasPermission("admin/pois/import")) { ?>
            <!--
                ============================================================
                    POI IMPORTS SECTION
                ============================================================
            -->
            <h2 class="content-subhead">
                <?php echo I18N::resolveHTML("admin.section.pois.import.name"); ?>
            </h2>
            <!--
                File input box. The user selects a file that contains a list of
                POIs to be imported. Accepts CSV files only.
            -->
            <div class="pure-g">
                <div class="pure-u-1-3 full-on-mobile">
                    <p class="setting-name">
                        <?php echo I18N::resolveHTML("admin.section.pois.import.file.name"); ?><span class="only-desktop">:
                            <span class="tooltip">
                                <i class="content-fas fas fa-question-circle"></i>
                                <span>
                                    <?php echo I18N::resolveHTML("admin.section.pois.import.file.desc"); ?>
                                </span>
                            </span>
                        </span>
                    </p>
                    <p class="only-mobile">
                        <?php echo I18N::resolveHTML("admin.section.pois.import.file.desc"); ?>
                    </p>
                </div>
                <div class="pure-u-2-3 full-on-mobile">
                    <p>
                        <input type="file"
                               id="import-poi-file"
                               accept=".csv">
                    </p>
                </div>
            </div>
            <?php
                /*
                    An input box for each of the field headers required for
                    importing POIs. To import POIs, it is necessary to know
                    which data column contains the POI name, latitude and
                    longitude in the imported data. The user selects this using
                    these selection boxes.
                */
                $fields = array("name", "latitude", "longitude");
                foreach ($fields as $field) {
                    ?>
                        <div class="pure-g">
                            <div class="pure-u-1-3 full-on-mobile">
                                <p class="setting-name">
                                    <?php echo I18N::resolveHTML(
                                        "admin.section.pois.import.{$field}.name"
                                    ); ?><span class="only-desktop">:
                                        <span class="tooltip">
                                            <i class="content-fas fas fa-question-circle"></i>
                                            <span>
                                                <?php echo I18N::resolveHTML(
                                                    "admin.section.pois.import.{$field}.desc"
                                                ); ?>
                                            </span>
                                        </span>
                                    </span>
                                </p>
                                <p class="only-mobile">
                                    <?php echo I18N::resolveHTML("admin.section.pois.import.{$field}.desc"); ?>
                                </p>
                            </div>
                            <div class="pure-u-2-3 full-on-mobile">
                                <p>
                                    <select id="import-poi-field-<?php echo $field; ?>" class="import-poi-field" disabled>
                                        <option value="">
                                            <?php echo I18N::resolveHTML("admin.section.pois.import.selector.none"); ?>
                                        </option>
                                        <optgroup class="import-poi-optgroup" label="<?php
                                            echo I18N::resolveHTML("admin.section.pois.import.selector.available");
                                        ?>"></optgroup>
                                    </select>
                                </p>
                            </div>
                        </div>
                    <?php
                }
            ?>
            <!--
                The preview section for imported POIs. Contains a table that is
                populated with POIs that will be imported.
            -->
            <div id="import-poi-preview-section" class="hidden-by-default">
                <h2 class="content-subhead">
                    <?php echo I18N::resolveHTML("admin.section.pois.preview_table.name"); ?>
                </h2>
                <p id="import-poi-counter"></p>
                <table class="pure-table force-fullwidth">
                    <thead>
                        <tr>
                            <!--
                                Table header defining columns for:

                                    1.  Changing the POI name (text box)
                                    2.  Changing the latitude of the POI (text
                                        box)
                                    3.  Changing the longitude of the POI (text
                                        box)
                                    4.  Choosing whether or not to import the POI
                            -->
                            <th><?php echo I18N::resolveHTML("admin.table.pois.preview_table.column.poi_name.name"); ?></th>
                            <th><?php echo I18N::resolveHTML("admin.table.pois.preview_table.column.latitude.name"); ?></th>
                            <th><?php echo I18N::resolveHTML("admin.table.pois.preview_table.column.longitude.name"); ?></th>
                            <th><?php echo I18N::resolveHTML("admin.table.pois.preview_table.column.include.name"); ?></th>
                        </tr>
                    </thead>
                    <tbody id="import-poi-preview-rows">
                    </tbody>
                </table>
                <p id="import-poi-invalid-warning" class="poi-import-invalid hidden-by-default">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo I18N::resolveHTML("admin.section.pois.import.invalid_warning"); ?>
                </p>
            </div>
            <!--
                Importing ~250 or more POIs at the same time may cause the
                form to exceed the maximum number of allowed data fields per
                HTTP request. To mitigate this, the following field will contain
                a JSON-encoded representation of all the fields, and will be
                updated as the form is submitted.
            -->
            <input type="hidden" name="n_json" id="import-poi-json" data-changed>
            <!--
                ============================================================
                    POI EXPORTS SECTION
                ============================================================
            -->
            <h2 class="content-subhead">
                <?php echo I18N::resolveHTML("admin.section.pois.export.name"); ?>
            </h2>
            <p>
                <?php echo I18N::resolveHTML("admin.section.pois.export.info"); ?>
            </p>
            <p>
                <a href="./export-pois.php?<?php echo Security::getCSRFUrlParameter(); ?>">
                    <?php echo I18N::resolveHTML("admin.section.pois.export.do"); ?>
                </a>
            </p>
        <?php } ?>
        <p class="buttons">
            <input type="submit"
                   class="button-submit"
                   value="<?php echo I18N::resolveHTML("ui.button.save"); ?>">
        </p>
    </form>
</div>
<!--
    JavaScript library for CSV parsing.
-->
<script src="https://cdnjs.cloudflare.com/ajax/libs/PapaParse/4.6.0/papaparse.min.js"
        integrity="sha256-VEDmZKQGIjdl6PnqmhA5y0oCcqpcJPFm8qP1mEOBwHY="
        crossorigin="anonymous"></script>

<!--
    This page contains a significant amount of input fields. We can (and should)
    cut down on the number of fields that are submitted to the server to avoid
    hitting the server-side `max_input_vars` limit of 1000.
-->
<script src="./js/limit-inputs.js"></script>

<!--
    This page contains a potentially large table, so we should enable sorting
    and pagination for it.
-->
<script src="./js/table-utils.js"></script>

<!--
    /admin/js/pois.js contains additional functionality for this page.
-->
<script src="./js/pois.js?v=2"></script>
