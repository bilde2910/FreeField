<?php
/*
    Configtree is a tree consisting of all config options and their possible values.
    The setup of the tree is of the form DOMAIN -> SECTION -> SETTING. Domain is the
    page on the administration interface the setting should show up on. Section is
    the section on that page where the settings would appear. Each setting is then
    listed in order of appearance. Each setting key must be globally unique.

    E.g. "setup/uri" is listed under the Access section on the main settings page.

    Each setting has two options:
    - "default" is the default value of the object
    - "options" specifies the type of data to store.

    Valid options are declared in /includes/config/types.php.

    I18N is handled with setting.<setting>.name and setting.<setting>.desc.
    For domains and sections, it's handled with admin.domain.<domain>.name and
    admin.domain.<domain>.desc, and admin.section.<domain>.<section>.name and
    admin.section.<domain>.<section>.desc respectively. Sections only have a
    description if __hasdesc is set. If __hasdesc is not true,
    admin.section.<domain>.<section>.desc is ignored. Section descriptions can
    be formatted similar to sprintf() with __descsprintf (optional). Example:

    Example I18N'd string: "Please set up {%1} authentication first!"
    If __descsprintf = array("Discord"); the string would be:
    "Please set up Discord authentication first!"

    Selection box contents are I18N'd with setting.<setting>.option.<option>.
    Dashes in all parts of the I18N string are replaced with underscores and
    slashes replaced with dots.

    E.g. for setting "setup/uri":
    - setting.setup.uri.name
    - setting.setup.uri.desc

    E.g. for setting "security/validate-ua":
    - setting.security.validate_ua.name
    - setting.security.validate_ua.desc
    - setting.security.validate_ua.option.no
    - setting.security.validate_ua.option.lenient
    - setting.security.validate_ua.option.strict

    E.g. for domain "main":
    - admin.domain.main.name
    - admin.domain.main.desc

    E.g. for section "database" in domain "main":
    - admin.section.main.database.name
    - admin.section.main.database.desc
*/

require_once(__DIR__."/../config/types.php");

class ConfigTree {
    public static function loadTree() {
        return array(
            /*
                SITE SETTINGS
                FreeField basic configuration
            */
            "main" => array(
                "access" => array(
                    /*
                        The base location of the FreeField installation.
                    */
                    "site/uri" => array(
                        "default" => "",
                        "option" => new StringOption('^https?\:\/\/')
                    ),
                    /*
                        The name of the FreeField instance (e.g. '[City Name]
                        FreeField')
                    */
                    "site/name" => array(
                        "default" => "FreeField",
                        "option" => new StringOption()
                    )
                ),
                "database" => array(
                    /*
                        The type of connection used to connect to the database.
                    */
                    "database/type" => array(
                        "default" => "mysqli",
                        "option" => new SelectOption(array("mysql", "mysqli", "pgsql", "sqlite", "sqlite3"))
                    ),
                    /*
                        The hostname or IP address of the database.

                        The regex here matches any string without spaces. A
                        regex query which properly matches IP addresses as well
                        as domain names would be needlessly complicated to
                        implement.
                    */
                    "database/host" => array(
                        "default" => "localhost",
                        "option" => new StringOption('^[^\s]+$')
                    ),
                    /*
                        The port used to connect to the database. Set to -1 for
                        default port.
                    */
                    "database/port" => array(
                        "default" => -1,
                        "option" => new IntegerOption(-1, 65535)
                    ),
                    /*
                        The username to login to the database server.
                    */
                    "database/username" => array(
                        "default" => "fieldfree",
                        "option" => new StringOption()
                    ),
                    /*
                        The password to login to the database server.
                    */
                    "database/password" => array(
                        "default" => "fieldfree",
                        "option" => new PasswordOption()
                    ),
                    /*
                        The name of the database FreeField should write data to.
                    */
                    "database/database" => array(
                        "default" => "fieldfree",
                        "option" => new StringOption()
                    ),
                    /*
                        A prefix used for all tables used by FreeField, to avoid
                        conflicts with other tables.
                    */
                    "database/table-prefix" => array(
                        "default" => "ffield_",
                        "option" => new StringOption()
                    )
                )
            ),
            /*
                PERMISSIONS
                Set up access control for functionality
            */
            "perms" => array(
                "default" => array(
                    /*
                        The default permissions level to assign all newly
                        registered users.

                        80 is equivalent to the default "Registered members"
                        group.
                    */
                    "permissions/default-level" => array(
                        "default" => 80,
                        "option" => new PermissionOption()
                    )
                ),
                "map-access" => array(
                    /*
                        Allows users to view the FreeField map and list of
                        POIs.
                    */
                    "permissions/level/access" => array(
                        "default" => 0,
                        "option" => new PermissionOption()
                    ),
                    /*
                        Allows users to report field research on POIs whose
                        current field research objective is unknown.
                    */
                    "permissions/level/report-research" => array(
                        "default" => 80,
                        "option" => new PermissionOption()
                    ),
                    /*
                        Allows users to report field research on all POIs, even
                        if another user has previously submitted a research
                        objective on the same day. Also requires the "Report
                        field research" permission.
                    */
                    "permissions/level/overwrite-research" => array(
                        "default" => 80,
                        "option" => new PermissionOption()
                    ),
                    /*
                        Allows users to submit new POIs to the map.
                    */
                    "permissions/level/submit-poi" => array(
                        "default" => 120,
                        "option" => new PermissionOption()
                    )
                ),
                "admin" => array(
                    /*
                        Allows users to change the site database and
                        installation settings.
                    */
                    "permissions/level/admin/main/general" => array(
                        "default" => 250,
                        "option" => new PermissionOption()
                    ),
                    /*
                        Allows users to approve, reject, manage, and delete
                        other users's accounts.
                    */
                    "permissions/level/admin/users/general" => array(
                        "default" => 160,
                        "option" => new PermissionOption()
                    ),
                    /*
                        Allows users to add, remove, and manage user groups.
                    */
                    "permissions/level/admin/groups/general" => array(
                        "default" => 200,
                        "option" => new PermissionOption()
                    ),
                    /*
                        Allows users to manage users' memberships in groups.
                        Also requires the "Manage users" permission.
                    */
                    "permissions/level/admin/users/groups" => array(
                        "default" => 160,
                        "option" => new PermissionOption()
                    ),
                    /*
                        Allows users to assign and remove other users from, and
                        manage the permissions of their own group. By default,
                        users with group membership permissions can only assign
                        users to and manage groups below themselves. This
                        permission is a dangerous permission to grant! Users
                        with this permission can appoint and delete other users
                        of the same rank as themselves. It is highly recommended
                        to leave this at the default unless there is an
                        extremely good reason to change it. Also requires the
                        "Manage group membership" permission.
                    */
                    "permissions/level/admin/groups/self-manage" => array(
                        "default" => 250,
                        "option" => new PermissionOption()
                    ),
                    /*
                        Allow users to manage and delete user-submitted POIs.
                    */
                    "permissions/level/admin/pois/general" => array(
                        "default" => 160,
                        "option" => new PermissionOption()
                    ),
                    /*
                        Allows users to change the permissions settings on this
                        page.
                    */
                    "permissions/level/admin/perms/general" => array(
                        "default" => 200,
                        "option" => new PermissionOption()
                    ),
                    /*
                        Allows users to change the security settings for the
                        site.
                    */
                    "permissions/level/admin/security/general" => array(
                        "default" => 200,
                        "option" => new PermissionOption()
                    ),
                    /*
                        Allows users to add, set up, enable, and disable various
                        authentication providers for user sign-in.
                    */
                    "permissions/level/admin/auth/general" => array(
                        "default" => 250,
                        "option" => new PermissionOption()
                    ),
                    /*
                        Allows users to manage default site-wide themes.
                    */
                    "permissions/level/admin/themes/general" => array(
                        "default" => 200,
                        "option" => new PermissionOption()
                    ),
                    /*
                        Allows users to manage map providers and default
                        settings for the map.
                    */
                    "permissions/level/admin/map/general" => array(
                        "default" => 250,
                        "option" => new PermissionOption()
                    ),
                    /*
                        Allows users to manage webhook integrations.
                    */
                    "permissions/level/admin/hooks/general" => array(
                        "default" => 200,
                        "option" => new PermissionOption()
                    )
                )
            ),
            /*
                SECURITY
                Secure user access and sessions
            */
            "security" => array(
                "user-creation" => array(
                    /*
                        If this is enabled, administrators must approve each
                        created account before the account can be used to access
                        FreeField.
                    */
                    "security/require-validation" => array(
                        "default" => false,
                        "option" => new BooleanOption()
                    )
                ),
                "sessions" => array(
                    /*
                        How long a user should stay logged in when they authenticate.
                    */
                    "auth/session-length" => array(
                        "default" => 315619200, // 10 years
                        "option" => new SelectOption(array(
                            86400, // 1 day
                            604800, // 7 days
                            2592000, // 30 days
                            7776000, // 90 days
                            15811200, // 6 months
                            31536000, // 1 year
                            63072000, // 2 years
                            157766400, // 5 years
                            315619200 // 10 years
                        ), "int")
                    ),
                    /*
                        Restricts each login session to the browser it was
                        created from. It is recommended to keep this enabled to
                        prevent session hijacking, as usage of a session in a
                        different browser than the one it was created from is
                        almost always malicious. Setting this to Strict will log
                        out the user if their browser receives an update, and
                        does not improve security much beyond Lenient, and is
                        thus not recommended.
                    */
                    "security/validate-ua" => array(
                        "default" => "lenient",
                        "option" => new SelectOption(array("no", "lenient", "strict"))
                    ),
                    /*
                        Requires each login session to maintain the same set of
                        browser languages every time it is used. This will
                        invalidate a user's session if they change their browser
                        or device languages. Enabling this helps against session
                        hijacking where the malicious actor uses the same
                        user-agent, but has configured their browser for a
                        different set of accepted languages, such as if they
                        live in a different country.
                    */
                    "security/validate-lang" => array(
                        "default" => true,
                        "option" => new BooleanOption()
                    )
                )
            ),
            /*
                AUTHENTICATION
                Authentication and third-party provider settings
            */
            "auth" => array(
                "discord" => array(
                    "__hasdesc" => true,
                    "__descsprintf" => array(
                        '<a target="_blank" href="https://github.com/bilde2910/FreeField/wiki/Authentication-providers/Discord">',
                        '</a>'
                    ),
                    /*
                        Enables usage of Discord for user authentication.
                    */
                    "auth/provider/discord/enabled" => array(
                        "default" => false,
                        "option" => new BooleanOption()
                    ),
                    /*
                        The client ID of your Discord API application.
                    */
                    "auth/provider/discord/client-id" => array(
                        "default" => "",
                        "option" => new StringOption('^\d+$')
                    ),
                    /*
                        The client secret of your Discord API application.
                    */
                    "auth/provider/discord/client-secret" => array(
                        "default" => "",
                        "option" => new StringOption()
                    )
                ),
                "telegram" => array(
                    "__hasdesc" => true,
                    "__descsprintf" => array(
                        '<a target="_blank" href="https://github.com/bilde2910/FreeField/wiki/Authentication-providers/Telegram">',
                        '</a>'
                    ),
                    /*
                        Enables usage of Telegram for user authentication.
                    */
                    "auth/provider/telegram/enabled" => array(
                        "default" => false,
                        "option" => new BooleanOption()
                    ),
                    /*
                        The username of your Telegram bot.
                    */
                    "auth/provider/telegram/bot-username" => array(
                        "default" => "",
                        "option" => new StringOption()
                    ),
                    /*
                        The bot token assigned to your Telegram bot by BotFather.
                    */
                    "auth/provider/telegram/bot-token" => array(
                        "default" => "",
                        "option" => new StringOption('^\d+:[A-Za-z\d]+$')
                    )
                )
            ),
            /*
                THEMES
                Appearance settings
            */
            "themes" => array(
                "color" => array(
                    /*
                        Select the color theme of the administration pages.
                    */
                    "themes/color/admin" => array(
                        "default" => "dark",
                        "option" => new SelectOption(array("light", "dark"))
                    ),
                    /*
                        Select the default color theme of users' settings pages.
                    */
                    "themes/color/user-settings/theme" => array(
                        "default" => "dark",
                        "option" => new SelectOption(array("light", "dark"))
                    ),
                    /*
                        Whether to allow users to set their own color theme for
                        their settings pages instead of the default for their
                        own account.
                    */
                    "themes/color/user-settings/allow-personalization" => array(
                        "default" => true,
                        "option" => new BooleanOption()
                    ),
                    /*
                        Select the default color theme of the map.
                    */
                    "themes/color/map/theme/mapbox" => array(
                        "default" => "basic",
                        "option" => new SelectOption(array("basic", "streets", "bright", "light", "dark", "satellite"))
                    ),
                    /*
                        Whether to allow users to set their own color theme for
                        map instead of the default for their own account.
                    */
                    "themes/color/map/allow-personalization" => array(
                        "default" => true,
                        "option" => new BooleanOption()
                    )
                ),
                "icons" => array(
                    /*
                        Select the style of map markers used by default on the map.
                    */
                    "themes/icons/default" => array(
                        "default" => "freefield-3d-compass",
                        "option" => new IconPackOption()
                    ),
                    /*
                        Whether to allow users to select their own map marker
                        pack instead of the default for their own account.
                    */
                    "themes/icons/allow-personalization" => array(
                        "default" => true,
                        "option" => new BooleanOption()
                    )
                )
            ),
            /*
                MAP SETTINGS
                Set up map settings and defaults
            */
            "map" => array(
                "provider" => array(
                    /*
                        Select which map provider to use.
                    */
                    "map/provider/source" => array(
                        "default" => "mapbox",
                        "option" => new SelectOption(array("mapbox"))
                    ),
                    /*
                        Access token obtained from Mapbox.
                    */
                    "map/provider/mapbox/access-token" => array(
                        "default" => "",
                        "option" => new StringOption()
                    ),
                    /*
                        Select the default navigation provider to launch when
                        users request directions to a given POI.
                    */
                    "map/provider/directions" => array(
                        "default" => "google",
                        "option" => new SelectOption(array("bing", "google", "here", "mapquest", "waze", "yandex"))
                    )
                ),
                "default" => array(
                    /*
                        The latitude portion of the default coordinates to
                        center the map at when it is loaded.
                    */
                    "map/default/center/latitude" => array(
                        "default" => 0.0,
                        "option" => new FloatOption(-90.0, 90.0)
                    ),
                    /*
                        The longitude portion of the default coordinates to
                        center the map at when it is loaded.
                    */
                    "map/default/center/longitude" => array(
                        "default" => 0.0,
                        "option" => new FloatOption(-180.0, 180.0)
                    ),
                    /*
                        The default zoom level of the map.
                    */
                    "map/default/zoom" => array(
                        "default" => 14.0,
                        "option" => new FloatOption(0.0, 20.0)
                    )
                ),
                "geofence" => array(
                    "__hasdesc" => true,
                    "__descsprintf" => array(
                        '<a target="_blank" href="https://github.com/bilde2910/FreeField/wiki/Geofencing">',
                        '</a>'
                    ),
                    /*
                        Limits submission of new Pokéstops to areas within the
                        given polygonal region as defined by this list of corner
                        coordinates. A `null` value disables geofencing and
                        allows submission of POIs (and by extension, their field
                        research) worldwide.
                    */
                    "map/geofence" => array(
                        "default" => null,
                        "option" => new GeofenceOption()
                    )
                )
            )
        );
    }
}

?>
