<?php
/*
    Configtree is a tree consisting of all config options and their possible values.
    The setup of the tree is of the form DOMAIN -> SECTION -> SETTING. Domain is the
    page on the administration interface the setting should show up on. Section is
    the section on that page where the settings would appear. Each setting is then
    listed in order of appearance. Each setting key must be globally unique.

    E.g. "setup/uri" is listed under the Access section on the main settings page.

    Each setting has two options:
    - default is the default value of the object
    - options specifies the type of data to store.

    Valid options:
    - "string" for a string
    - "password" for a password-type string
    - "int" for an integer
    - "int,x,y" for an integer between values x and y
    - "float" for a floating-point value
    - "float,x,y" for a floating-point value between values x and y
    - "permission" for a permission tier
    - "bool" for a boolean
    - array() for a selection box with the array contents as options

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
            "main" => array(
                "access" => array(
                    "site/uri" => array(
                        "default" => "",
                        "option" => new StringOption('^https?\:\/\/')
                    ),
                    "site/name" => array(
                        "default" => "FreeField",
                        "option" => new StringOption()
                    )
                ),
                "database" => array(
                    "database/type" => array(
                        "default" => "mysqli",
                        "option" => new SelectOption(array("mysql", "mysqli", "pgsql", "sqlite", "sqlite3"))
                    ),
                    "database/host" => array(
                        "default" => "localhost",
                        "option" => new StringOption('^[^\s]+$')
                    ),
                    "database/port" => array(
                        "default" => -1,
                        "option" => new IntegerOption(-1, 65535)
                    ),
                    "database/username" => array(
                        "default" => "fieldfree",
                        "option" => new StringOption()
                    ),
                    "database/password" => array(
                        "default" => "fieldfree",
                        "option" => new PasswordOption()
                    ),
                    "database/database" => array(
                        "default" => "fieldfree",
                        "option" => new StringOption()
                    ),
                    "database/table-prefix" => array(
                        "default" => "ffield_",
                        "option" => new StringOption()
                    )
                )
            ),
            "perms" => array(
                "default" => array(
                    "permissions/default-level" => array(
                        "default" => 80,
                        "option" => new PermissionOption()
                    )
                ),
                "map-access" => array(
                    "permissions/level/access" => array(
                        "default" => 0,
                        "option" => new PermissionOption()
                    ),
                    "permissions/level/report-research" => array(
                        "default" => 80,
                        "option" => new PermissionOption()
                    ),
                    "permissions/level/overwrite-research" => array(
                        "default" => 80,
                        "option" => new PermissionOption()
                    ),
                    "permissions/level/submit-poi" => array(
                        "default" => 120,
                        "option" => new PermissionOption()
                    )
                ),
                "admin" => array(
                    "permissions/level/admin/main/general" => array(
                        "default" => 250,
                        "option" => new PermissionOption()
                    ),
                    "permissions/level/admin/users/general" => array(
                        "default" => 160,
                        "option" => new PermissionOption()
                    ),
                    "permissions/level/admin/groups/general" => array(
                        "default" => 200,
                        "option" => new PermissionOption()
                    ),
                    "permissions/level/admin/users/groups" => array(
                        "default" => 160,
                        "option" => new PermissionOption()
                    ),
                    "permissions/level/admin/groups/self-manage" => array(
                        "default" => 250,
                        "option" => new PermissionOption()
                    ),
                    "permissions/level/admin/pois/general" => array(
                        "default" => 160,
                        "option" => new PermissionOption()
                    ),
                    "permissions/level/admin/perms/general" => array(
                        "default" => 200,
                        "option" => new PermissionOption()
                    ),
                    "permissions/level/admin/security/general" => array(
                        "default" => 200,
                        "option" => new PermissionOption()
                    ),
                    "permissions/level/admin/auth/general" => array(
                        "default" => 250,
                        "option" => new PermissionOption()
                    ),
                    "permissions/level/admin/themes/general" => array(
                        "default" => 200,
                        "option" => new PermissionOption()
                    ),
                    "permissions/level/admin/map/general" => array(
                        "default" => 250,
                        "option" => new PermissionOption()
                    ),
                    "permissions/level/admin/hooks/general" => array(
                        "default" => 200,
                        "option" => new PermissionOption()
                    )
                )
            ),
            "security" => array(
                "user-creation" => array(
                    "security/require-validation" => array(
                        "default" => false,
                        "option" => new BooleanOption()
                    )
                ),
                "sessions" => array(
                    "auth/session-length" => array(
                        "default" => 315619200, // 10 years
                        "option" => new SelectOption(array(86400, 604800, 2592000, 7776000, 15811200, 31536000, 63072000, 157766400, 315619200), "int")
                    ),
                    "security/validate-ua" => array(
                        "default" => "lenient",
                        "option" => new SelectOption(array("no", "lenient", "strict"))
                    ),
                    "security/validate-lang" => array(
                        "default" => true,
                        "option" => new BooleanOption()
                    )
                )
            ),
            "auth" => array(
                "discord" => array(
                    "__hasdesc" => true,
                    "__descsprintf" => array(
                        '<a target="_blank" href="https://github.com/bilde2910/FreeField/wiki/Authentication-providers/Discord">',
                        '</a>'
                    ),
                    "auth/provider/discord/enabled" => array(
                        "default" => false,
                        "option" => new BooleanOption()
                    ),
                    "auth/provider/discord/client-id" => array(
                        "default" => "",
                        "option" => new StringOption('^\d+$')
                    ),
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
                    "auth/provider/telegram/enabled" => array(
                        "default" => false,
                        "option" => new BooleanOption()
                    ),
                    "auth/provider/telegram/bot-username" => array(
                        "default" => "",
                        "option" => new StringOption()
                    ),
                    "auth/provider/telegram/bot-token" => array(
                        "default" => "",
                        "option" => new StringOption('^\d+:[A-Za-z\d]+$')
                    )
                )
            ),
            "themes" => array(
                "color" => array(
                    "themes/color/admin" => array(
                        "default" => "dark",
                        "option" => new SelectOption(array("light", "dark"))
                    ),
                    "themes/color/user-settings/theme" => array(
                        "default" => "dark",
                        "option" => new SelectOption(array("light", "dark"))
                    ),
                    "themes/color/user-settings/allow-personalization" => array(
                        "default" => true,
                        "option" => new BooleanOption()
                    ),
                    "themes/color/map/theme/mapbox" => array(
                        "default" => "basic",
                        "option" => new SelectOption(array("basic", "streets", "bright", "light", "dark", "satellite"))
                    ),
                    "themes/color/map/allow-personalization" => array(
                        "default" => true,
                        "option" => new BooleanOption()
                    )
                ),
                "icons" => array(
                    "themes/icons/default" => array(
                        "default" => "freefield-3d-compass",
                        "option" => new IconPackOption()
                    ),
                    "themes/icons/allow-personalization" => array(
                        "default" => true,
                        "option" => new BooleanOption()
                    )
                )
            ),
            "map" => array(
                "provider" => array(
                    "map/provider/source" => array(
                        "default" => "mapbox",
                        "option" => new SelectOption(array("mapbox"))
                    ),
                    "map/provider/mapbox/access-token" => array(
                        "default" => "",
                        "option" => new StringOption()
                    ),
                    "map/provider/directions" => array(
                        "default" => "google",
                        "option" => new SelectOption(array("bing", "google", "here", "mapquest", "waze", "yandex"))
                    )
                ),
                "default" => array(
                    "map/default/center/latitude" => array(
                        "default" => 0.0,
                        "option" => new FloatOption(-90.0, 90.0)
                    ),
                    "map/default/center/longitude" => array(
                        "default" => 0.0,
                        "option" => new FloatOption(-180.0, 180.0)
                    ),
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
