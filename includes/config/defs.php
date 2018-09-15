<?php
/*
    This file contains a complete list of all configurable settings in
    FreeField. Each element in the array in `ConfigDefinitions` contains one
    setting. Each setting is identified by a globally unique key.

    Each setting may have the following options in its definition array:

    "domain" (required)
        In order for the setting to be editable, it has to show up somewhere on
        the administration pages. This element determines which page on the
        administration interface this setting appears on. For example, "main" is
        the "Site settings" page, and "security" is the "Security" page, etc.

    "section" (required)
        In addition to assigning each setting to a domain, each setting has to
        be assigned to a section within that specific page. A section is a sub-
        heading on a page. For example, if the `domain` is set to "main",
        `section` can be set to "spiders" to make the setting show up in the
        "Crawling" section of the "Site settings" page.

    "default" (required)
        The default value of the setting.

    "option" (required)
        Each setting can accept a certain type of data. The accepted data type
        is determined using this array key. The value of `option` must be an
        instance of any `Option` class as defined in /includes/config/types.php.

    "permissions" (optional)
        An array of permissions required to change this setting. If any of the
        permissions listed here are not granted for a user, that user will not
        be able to change the setting. Note that "admin/<domain>/general" and
        "admin/<domain>/section/<section>" are automatically added to this array
        as a minimum requirement regardless of whether or not this key is
        defined or lists any additional permissions.

    "enable-only-if" (optional)
        A boolean assertion that, if it evaluates to false, will disable the
        setting input on the administration pages, preventing it from being
        changed. Used if a setting requires some precondition to be satisfied in
        order to work properly.

    "value-if-disabled" (optional; only if "enable-only-if" is set)
        A value to return instead of the value in the configuration file if the
        assertion in "enable-only-if" fails. If this option is not set, the
        value in the configuration file is returned even if the assertion fails.

    I18N is handled with setting.<setting>.name and setting.<setting>.desc.
    For domains and sections, it's handled with admin.domain.<domain>.name and
    admin.domain.<domain>.desc, and admin.section.<domain>.<section>.name and
    admin.section.<domain>.<section>.desc respectively.

    NOTE: Descriptions for sections are not normally displayed! Sections only
    show descriptions if there is an entry for the given section in
    `ConfigSectionI18N` in /includes/lib/config.php. Please search that class
    for `SECTIONS_WITH_DESCRIPTIONS` for more information.

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

// For navigation providers (map/provider/directions)
__require("geo");

class ConfigDefinitions {
    public static function loadDefinitions() {
        return array(
            /*
================================================================================
    SITE SETTINGS
    FreeField basic configuration
================================================================================
            */
            /*
                ------------------------------------------------------------
                    ACCESS
                ------------------------------------------------------------
            */
            /*
                The base location of the FreeField installation.
            */
            "site/uri" => array(
                "domain" => "main",
                "section" => "access",
                "default" => "",
                "option" => new StringOption('^https?\:\/\/')
            ),
            /*
                ------------------------------------------------------------
                    INSTANCE OPTIONS
                ------------------------------------------------------------
            */
            /*
                The name of the FreeField instance (e.g. '[City Name]
                FreeField')
            */
            "site/name" => array(
                "domain" => "main",
                "section" => "instance",
                "default" => "FreeField",
                "option" => new StringOption()
            ),
            /*
                The text displayed at the top of the menu sidebar.
            */
            "site/menu-header" => array(
                "domain" => "main",
                "section" => "instance",
                "default" => "FREEFIELD",
                "option" => new StringOption()
            ),
            /*
                ------------------------------------------------------------
                    MESSAGE OF THE DAY
                ------------------------------------------------------------
            */
            /*
                Determines how the Message of the Day is displayed.
            */
            "motd/display-mode" => array(
                "domain" => "main",
                "section" => "motd",
                "default" => "never",
                "option" => new SelectOption(array(
                    "forced",
                    "always",
                    "on-change",
                    "on-request",
                    "never"
                ))
            ),
            /*
                The title on the Message of the Day popup box. Defaults to
                "Message of the Day" if left blank.
            */
            "motd/title" => array(
                "domain" => "main",
                "section" => "motd",
                "default" => "",
                "option" => new StringOption()
            ),
            /*
                A message displayed to all users in a popup box every time
                FreeField is opened. Markdown formatting accepted.
            */
            "motd/content" => array(
                "domain" => "main",
                "section" => "motd",
                "default" => "Welcome to FreeField!",
                "option" => new ParagraphOption("md")
            ),
            /*
                ------------------------------------------------------------
                    CRAWLING
                ------------------------------------------------------------
            */
            /*
                Declares how robots (such as search engines and other web
                scrapers) may crawl and index this site.
            */
            "spiders/robots-policy" => array(
                "domain" => "main",
                "section" => "spiders",
                "default" => "none",
                "option" => new SelectOption(array(
                    "all",
                    "nofollow",
                    "noindex",
                    "noindex,nofollow"
                ))
            ),
            /*
                ------------------------------------------------------------
                    DATABASE
                ------------------------------------------------------------
            */
            /*
                The type of connection used to connect to the database.
            */
            "database/type" => array(
                "domain" => "main",
                "section" => "database",
                "default" => "mysqli",
                "option" => new SelectOption(array(
                    "mysql",
                    "mysqli",
                    "pgsql",
                    "sqlite",
                    "sqlite3"
                ))
            ),
            /*
                The hostname or IP address of the database.

                The regex here matches any string without spaces. A regex query
                which properly matches IP addresses as well as domain names
                would be needlessly complicated to implement.
            */
            "database/host" => array(
                "domain" => "main",
                "section" => "database",
                "default" => "localhost",
                "option" => new StringOption('^[^\s]+$')
            ),
            /*
                The port used to connect to the database. Set to -1 for default
                port.
            */
            "database/port" => array(
                "domain" => "main",
                "section" => "database",
                "default" => -1,
                "option" => new IntegerOption(-1, 65535)
            ),
            /*
                The username to login to the database server.
            */
            "database/username" => array(
                "domain" => "main",
                "section" => "database",
                "default" => "fieldfree",
                "option" => new StringOption()
            ),
            /*
                The password to login to the database server.
            */
            "database/password" => array(
                "domain" => "main",
                "section" => "database",
                "default" => "fieldfree",
                "option" => new PasswordOption()
            ),
            /*
                The name of the database FreeField should write data to.
            */
            "database/database" => array(
                "domain" => "main",
                "section" => "database",
                "default" => "fieldfree",
                "option" => new StringOption()
            ),
            /*
                A prefix used for all tables used by FreeField, to avoid
                conflicts with other tables.
            */
            "database/table-prefix" => array(
                "domain" => "main",
                "section" => "database",
                "default" => "ffield_",
                "option" => new StringOption()
            ),
            /*
================================================================================
    PERMISSIONS
    Set up access control for functionality
================================================================================
            */
            /*
                ------------------------------------------------------------
                    DEFAULT SETTINGS
                ------------------------------------------------------------
            */
            /*
                The default permissions level to assign all newly registered
                users.

                80 is equivalent to the default "Registered members" group.
            */
            "permissions/default-level" => array(
                "domain" => "perms",
                "section" => "default",
                "default" => PermissionOption::LEVEL_REGISTERED,
                "option" => new PermissionOption()
            ),
            /*
                ------------------------------------------------------------
                    ACCOUNT SELF-MANAGEMENT
                ------------------------------------------------------------
            */
            /*
                Allows users of this level and higher to change their own
                nicknames.
            */
            "permissions/level/self-manage/nickname" => array(
                "domain" => "perms",
                "section" => "self-manage",
                "default" => PermissionOption::LEVEL_READ_ONLY,
                "option" => new PermissionOption()
            ),
            /*
                ------------------------------------------------------------
                    MAP ACCESS PERMISSIONS
                ------------------------------------------------------------
            */
            /*
                Allows users to view the FreeField map and list of POIs.
            */
            "permissions/level/access" => array(
                "domain" => "perms",
                "section" => "map-access",
                "default" => PermissionOption::LEVEL_ANONYMOUS,
                "option" => new PermissionOption()
            ),
            /*
                Allows users to report field research on POIs whose current
                field research objective is unknown.
            */
            "permissions/level/report-research" => array(
                "domain" => "perms",
                "section" => "map-access",
                "default" => PermissionOption::LEVEL_REGISTERED,
                "option" => new PermissionOption()
            ),
                /*
                    Allows users to report field research on all POIs, even if
                    another user has previously submitted a research objective
                    on the same day. Also requires the "Report field research"
                    permission.
                */
                "permissions/level/overwrite-research" => array(
                    "domain" => "perms",
                    "section" => "map-access",
                    "indentation" => 1,
                    "default" => PermissionOption::LEVEL_REGISTERED,
                    "option" => new PermissionOption()
                ),
            /*
                Allows users to submit new POIs to the map.
            */
            "permissions/level/submit-poi" => array(
                "domain" => "perms",
                "section" => "map-access",
                "default" => PermissionOption::LEVEL_SUBMITTER,
                "option" => new PermissionOption()
            ),
            /*
                ------------------------------------------------------------
                    ADMINISTRATIVE PERMISSIONS
                ------------------------------------------------------------
            */
            /*
                Allows users to change the site database and installation
                settings.
            */
            "permissions/level/admin/main/general" => array(
                "domain" => "perms",
                "section" => "admin",
                "default" => PermissionOption::LEVEL_ADMIN,
                "option" => new PermissionOption()
            ),
                /*
                    Allows users to change the access parameters, such as the
                    URL, of the site.
                */
                "permissions/level/admin/main/section/access" => array(
                    "domain" => "perms",
                    "section" => "admin",
                    "indentation" => 1,
                    "default" => PermissionOption::LEVEL_HOST,
                    "option" => new PermissionOption()
                ),
                /*
                    Allows users to change this FreeField instance's core
                    appearance options, such as the name of the site.
                */
                "permissions/level/admin/main/section/instance" => array(
                    "domain" => "perms",
                    "section" => "admin",
                    "indentation" => 1,
                    "default" => PermissionOption::LEVEL_ADMIN,
                    "option" => new PermissionOption()
                ),
                /*
                    Allows users to change and manage the Message of the Day for
                    this FreeField instance.
                */
                "permissions/level/admin/main/section/motd" => array(
                    "domain" => "perms",
                    "section" => "admin",
                    "indentation" => 1,
                    "default" => PermissionOption::LEVEL_ADMIN,
                    "option" => new PermissionOption()
                ),
                /*
                    Allows users to change the crawling settings for web
                    spiders.
                */
                "permissions/level/admin/main/section/spiders" => array(
                    "domain" => "perms",
                    "section" => "admin",
                    "indentation" => 1,
                    "default" => PermissionOption::LEVEL_ADMIN,
                    "option" => new PermissionOption()
                ),
                /*
                    Allows users to change the database settings.
                */
                "permissions/level/admin/main/section/database" => array(
                    "domain" => "perms",
                    "section" => "admin",
                    "indentation" => 1,
                    "default" => PermissionOption::LEVEL_HOST,
                    "option" => new PermissionOption()
                ),
            /*
                Allows users to approve, reject, manage, and delete other users'
                accounts.
            */
            "permissions/level/admin/users/general" => array(
                "domain" => "perms",
                "section" => "admin",
                "default" => PermissionOption::LEVEL_MODERATOR,
                "option" => new PermissionOption()
            ),
                /*
                    Allows users to manage users' memberships in groups. Also
                    requires the "Manage users" permission.
                */
                "permissions/level/admin/users/groups" => array(
                    "domain" => "perms",
                    "section" => "admin",
                    "indentation" => 1,
                    "default" => PermissionOption::LEVEL_MODERATOR,
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
                        "domain" => "perms",
                        "section" => "admin",
                        "indentation" => 2,
                        "default" => PermissionOption::LEVEL_HOST,
                        "option" => new PermissionOption()
                    ),
            /*
                Allows users to add, remove, and manage user groups.
            */
            "permissions/level/admin/groups/general" => array(
                "domain" => "perms",
                "section" => "admin",
                "default" => PermissionOption::LEVEL_ADMIN,
                "option" => new PermissionOption()
            ),
            /*
                Allow users to manage and delete user-submitted POIs.
            */
            "permissions/level/admin/pois/general" => array(
                "domain" => "perms",
                "section" => "admin",
                "default" => PermissionOption::LEVEL_MODERATOR,
                "option" => new PermissionOption()
            ),
            /*
                Allows users to change the permissions settings on this page.
            */
            "permissions/level/admin/perms/general" => array(
                "domain" => "perms",
                "section" => "admin",
                "default" => PermissionOption::LEVEL_ADMIN,
                "option" => new PermissionOption()
            ),
                /*
                    Allows users to change the default user group for newly
                    registered members.
                */
                "permissions/level/admin/perms/section/default" => array(
                    "domain" => "perms",
                    "section" => "admin",
                    "indentation" => 1,
                    "default" => PermissionOption::LEVEL_ADMIN,
                    "option" => new PermissionOption()
                ),
                /*
                    Allows users to manage permissions relating to account self-
                    management, such as granting users the right to change their
                    own nicknames, on this page.
                */
                "permissions/level/admin/perms/section/self-manage" => array(
                    "domain" => "perms",
                    "section" => "admin",
                    "indentation" => 1,
                    "default" => PermissionOption::LEVEL_ADMIN,
                    "option" => new PermissionOption()
                ),
                /*
                    Allows users to manage map access restrictions.
                */
                "permissions/level/admin/perms/section/map-access" => array(
                    "domain" => "perms",
                    "section" => "admin",
                    "indentation" => 1,
                    "default" => PermissionOption::LEVEL_ADMIN,
                    "option" => new PermissionOption()
                ),
                /*
                    Allows users to manage administrative permissions (such as
                    this one).
                */
                "permissions/level/admin/perms/section/admin" => array(
                    "domain" => "perms",
                    "section" => "admin",
                    "indentation" => 1,
                    "default" => PermissionOption::LEVEL_ADMIN,
                    "option" => new PermissionOption()
                ),
            /*
                Allows users to change the security settings for the site.
            */
            "permissions/level/admin/security/general" => array(
                "domain" => "perms",
                "section" => "admin",
                "default" => PermissionOption::LEVEL_ADMIN,
                "option" => new PermissionOption()
            ),
                /*
                    Allows users to change settings related to user account
                    creation.
                */
                "permissions/level/admin/security/section/user-creation" => array(
                    "domain" => "perms",
                    "section" => "admin",
                    "indentation" => 1,
                    "default" => PermissionOption::LEVEL_ADMIN,
                    "option" => new PermissionOption()
                ),
                /*
                    Allows users to change session security settings.
                */
                "permissions/level/admin/security/section/sessions" => array(
                    "domain" => "perms",
                    "section" => "admin",
                    "indentation" => 1,
                    "default" => PermissionOption::LEVEL_ADMIN,
                    "option" => new PermissionOption()
                ),
                /*
                    Allows users to change same-origin policy settings.
                */
                "permissions/level/admin/security/section/same-origin" => array(
                    "domain" => "perms",
                    "section" => "admin",
                    "indentation" => 1,
                    "default" => PermissionOption::LEVEL_ADMIN,
                    "option" => new PermissionOption()
                ),
            /*
                Allows users to add, set up, enable, and disable various
                authentication providers for user sign-in.
            */
            "permissions/level/admin/auth/general" => array(
                "domain" => "perms",
                "section" => "admin",
                "default" => PermissionOption::LEVEL_HOST,
                "option" => new PermissionOption()
            ),
                /*
                    Allows users to change authentication settings for Discord.
                */
                "permissions/level/admin/auth/section/discord" => array(
                    "domain" => "perms",
                    "section" => "admin",
                    "indentation" => 1,
                    "default" => PermissionOption::LEVEL_HOST,
                    "option" => new PermissionOption()
                ),
                /*
                    Allows users to change authentication settings for Telegram.
                */
                "permissions/level/admin/auth/section/telegram" => array(
                    "domain" => "perms",
                    "section" => "admin",
                    "indentation" => 1,
                    "default" => PermissionOption::LEVEL_HOST,
                    "option" => new PermissionOption()
                ),
                /*
                    Allows users to change authentication settings for Reddit.
                */
                "permissions/level/admin/auth/section/reddit" => array(
                    "domain" => "perms",
                    "section" => "admin",
                    "indentation" => 1,
                    "default" => PermissionOption::LEVEL_HOST,
                    "option" => new PermissionOption()
                ),
                /*
                    Allows users to change authentication settings for GroupMe.
                */
                "permissions/level/admin/auth/section/groupme" => array(
                    "domain" => "perms",
                    "section" => "admin",
                    "indentation" => 1,
                    "default" => PermissionOption::LEVEL_HOST,
                    "option" => new PermissionOption()
                ),
            /*
                Allows users to manage default site-wide themes.
            */
            "permissions/level/admin/themes/general" => array(
                "domain" => "perms",
                "section" => "admin",
                "default" => PermissionOption::LEVEL_ADMIN,
                "option" => new PermissionOption()
            ),
                /*
                    Allows users to change page metadata theming, such as the
                    favicon and the theme color for mobile browsers.
                */
                "permissions/level/admin/themes/section/meta" => array(
                    "domain" => "perms",
                    "section" => "admin",
                    "indentation" => 1,
                    "default" => PermissionOption::LEVEL_ADMIN,
                    "option" => new PermissionOption()
                ),
                /*
                    Allows users to change the default map and page color themes
                    and whether or not users are allowed to override those
                    defaults.
                */
                "permissions/level/admin/themes/section/color" => array(
                    "domain" => "perms",
                    "section" => "admin",
                    "indentation" => 1,
                    "default" => PermissionOption::LEVEL_ADMIN,
                    "option" => new PermissionOption()
                ),
                /*
                    Allows users to change the default map marker set and
                    whether or not users are allowed to override that default.
                */
                "permissions/level/admin/themes/section/icons" => array(
                    "domain" => "perms",
                    "section" => "admin",
                    "indentation" => 1,
                    "default" => PermissionOption::LEVEL_ADMIN,
                    "option" => new PermissionOption()
                ),
            /*
                Allows users to manage map providers and default settings for
                the map.
            */
            "permissions/level/admin/map/general" => array(
                "domain" => "perms",
                "section" => "admin",
                "default" => PermissionOption::LEVEL_ADMIN,
                "option" => new PermissionOption()
            ),
                /*
                    Allows users to change and configure which map provider this
                    FreeField instance uses.
                */
                "permissions/level/admin/map/section/provider" => array(
                    "domain" => "perms",
                    "section" => "admin",
                    "indentation" => 1,
                    "default" => PermissionOption::LEVEL_HOST,
                    "option" => new PermissionOption()
                ),
                /*
                    Allows users to change map defaults, such as default
                    coordinates and zoom level for first-time visitors.
                */
                "permissions/level/admin/map/section/default" => array(
                    "domain" => "perms",
                    "section" => "admin",
                    "indentation" => 1,
                    "default" => PermissionOption::LEVEL_ADMIN,
                    "option" => new PermissionOption()
                ),
                /*
                    Allows users to change the interval at which each FreeField
                    client requests an updated list of research tasks for all
                    Pokéstops.
                */
                "permissions/level/admin/map/section/updates" => array(
                    "domain" => "perms",
                    "section" => "admin",
                    "indentation" => 1,
                    "default" => PermissionOption::LEVEL_HOST,
                    "option" => new PermissionOption()
                ),
                /*
                    Allows users to change the geofence and associated behavior
                    for submission and display of Pokéstops.
                */
                "permissions/level/admin/map/section/geofence" => array(
                    "domain" => "perms",
                    "section" => "admin",
                    "indentation" => 1,
                    "default" => PermissionOption::LEVEL_ADMIN,
                    "option" => new PermissionOption()
                ),
            /*
                Allows users to manage geofences.
            */
            "permissions/level/admin/fences/general" => array(
                "domain" => "perms",
                "section" => "admin",
                "default" => PermissionOption::LEVEL_ADMIN,
                "option" => new PermissionOption()
            ),
            /*
                Allows users to manage webhook integrations.
            */
            "permissions/level/admin/hooks/general" => array(
                "domain" => "perms",
                "section" => "admin",
                "default" => PermissionOption::LEVEL_ADMIN,
                "option" => new PermissionOption()
            ),
            /*
================================================================================
    SECURITY
    Secure user access and sessions
================================================================================
            */
            /*
                ------------------------------------------------------------
                    USER CREATION
                ------------------------------------------------------------
            */
            /*
                If this is enabled, administrators must approve each created
                account before the account can be used to access FreeField.
            */
            "security/approval/require" => array(
                "domain" => "security",
                "section" => "user-creation",
                "default" => false,
                "option" => new BooleanOption()
            ),
            /*
                If this is enabled, users awaiting approval will be presented
                with a QR code that can be scanned by an administrator. Scanning
                the code will allow the administrator to approve or reject the
                user very quickly compared to finding the user in the list of
                registered users and approving them there. This function
                requires the GD extension to be loaded in PHP.
            */
            "security/approval/by-qr" => array(
                "domain" => "security",
                "section" => "user-creation",
                "default" => extension_loaded("gd"),
                "option" => new BooleanOption(),
                "enable-only-if" => extension_loaded("gd"),
                "value-if-disabled" => false
            ),
            /*
                ------------------------------------------------------------
                    SESSIONS
                ------------------------------------------------------------
            */
            /*
                How long a user should stay logged in when they authenticate.
            */
            "auth/session-length" => array(
                "domain" => "security",
                "section" => "sessions",
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
                Restricts each login session to the browser it was created from.
                It is recommended to keep this enabled to prevent session
                hijacking, as usage of a session in a different browser than the
                one it was created from is almost always malicious. Setting this
                to Strict will log out the user if their browser receives an
                update, and does not improve security much beyond Lenient, and
                is thus not recommended.
            */
            "security/validate-ua" => array(
                "domain" => "security",
                "section" => "sessions",
                "default" => "lenient",
                "option" => new SelectOption(array(
                    "no",
                    "lenient",
                    "strict"
                ))
            ),
            /*
                Requires each login session to maintain the same set of browser
                languages every time it is used. This will invalidate a user's
                session if they change their browser or device languages.
                Enabling this helps against session hijacking where the
                malicious actor uses the same user-agent, but has configured
                their browser for a different set of accepted languages, such as
                if they live in a different country.
            */
            "security/validate-lang" => array(
                "domain" => "security",
                "section" => "sessions",
                "default" => true,
                "option" => new BooleanOption()
            ),
            /*
                The session hijack canary is triggered when any of the above
                validation requirements fail for a user. While this could be
                caused by a session hijack, it may also be caused for completely
                legitimate reasons, such as a browser update causing strict
                user-agent validation to fail. Enabling the canary will cause
                the user to be signed out of FreeField on all of their devices
                if validation fails for one of them. In practice, this means
                that if an attacker obtains the user's session cookie, if they
                have e.g. the wrong user-agent or language on their browser,
                they will not be able to sign in with that cookie later even if
                they were to guess the correct user-agent or language because
                the cookie will be permanently invalidated.
            */
            "security/selector-canary" => array(
                "domain" => "security",
                "section" => "sessions",
                "default" => false,
                "option" => new BooleanOption()
            ),
            /*
                ------------------------------------------------------------
                    SAME-ORIGIN POLICY
                ------------------------------------------------------------
            */
            /*
                This setting declares the framing policy of this FreeField
                instance. Allowing framing means that other sites can insert
                this site as part of their own using iframes or framesets. It is
                recommended to leave this at the default \"deny\" setting unless
                you have a good reason for enabling it.
            */
            "security/frame-options" => array(
                "domain" => "security",
                "section" => "same-origin",
                "default" => "deny",
                "option" => new SelectOption(array(
                    "allow",
                    "sameorigin",
                    "deny"
                ))
            ),
            /*
================================================================================
    AUTHENTICATION
    Authentication and third-party provider settings
================================================================================
            */
            /*
                ------------------------------------------------------------
                    DISCORD
                ------------------------------------------------------------
            */
            /*
                Enables usage of Discord for user authentication.
            */
            "auth/provider/discord/enabled" => array(
                "domain" => "auth",
                "section" => "discord",
                "default" => false,
                "option" => new BooleanOption()
            ),
            /*
                The client ID of your Discord API application.
            */
            "auth/provider/discord/client-id" => array(
                "domain" => "auth",
                "section" => "discord",
                "default" => "",
                "option" => new StringOption('^\d+$')
            ),
            /*
                The client secret of your Discord API application.
            */
            "auth/provider/discord/client-secret" => array(
                "domain" => "auth",
                "section" => "discord",
                "default" => "",
                "option" => new PasswordOption()
            ),
            /*
                ------------------------------------------------------------
                    TELEGRAM
                ------------------------------------------------------------
            */
            /*
                Enables usage of Telegram for user authentication.
            */
            "auth/provider/telegram/enabled" => array(
                "domain" => "auth",
                "section" => "telegram",
                "default" => false,
                "option" => new BooleanOption()
            ),
            /*
                The username of your Telegram bot.
            */
            "auth/provider/telegram/bot-username" => array(
                "domain" => "auth",
                "section" => "telegram",
                "default" => "",
                "option" => new StringOption()
            ),
            /*
                The bot token assigned to your Telegram bot by BotFather.
            */
            "auth/provider/telegram/bot-token" => array(
                "domain" => "auth",
                "section" => "telegram",
                "default" => "",
                "option" => new PasswordOption()
            ),
            /*
                ------------------------------------------------------------
                    REDDIT
                ------------------------------------------------------------
            */
            /*
                Enables usage of Reddit for user authentication.
            */
            "auth/provider/reddit/enabled" => array(
                "domain" => "auth",
                "section" => "reddit",
                "default" => false,
                "option" => new BooleanOption()
            ),
            /*
                The client ID of your Reddit API application.
            */
            "auth/provider/reddit/client-id" => array(
                "domain" => "auth",
                "section" => "reddit",
                "default" => "",
                "option" => new StringOption()
            ),
            /*
                The client secret of your Reddit API application.
            */
            "auth/provider/reddit/client-secret" => array(
                "domain" => "auth",
                "section" => "reddit",
                "default" => "",
                "option" => new PasswordOption()
            ),
            /*
                ------------------------------------------------------------
                    GROUPME
                ------------------------------------------------------------
            */
            /*
                Enables usage of GroupMe for user authentication.
            */
            "auth/provider/groupme/enabled" => array(
                "domain" => "auth",
                "section" => "groupme",
                "default" => false,
                "option" => new BooleanOption()
            ),
            /*
                The client ID of your GroupMe API application.
            */
            "auth/provider/groupme/client-id" => array(
                "domain" => "auth",
                "section" => "groupme",
                "default" => "",
                "option" => new StringOption('^[A-Za-z0-9]+$')
            ),
            /*
================================================================================
    THEMES
    Appearance settings
================================================================================
            */
            /*
                ------------------------------------------------------------
                    HTML META SETTINGS
                ------------------------------------------------------------
            */
            /*
                The icon displayed for this site in the address bar. *.png,
                *.gif, *.ico, and *.jpg files are allowed. Must not exceed 256
                KiB.
            */
            "themes/meta/favicon" => array(
                "domain" => "themes",
                "section" => "meta",
                "default" => array(
                    "type"   => "image/png",
                    "name"   => "default-favicon.png",
                    "size"   => 3200,
                    "sha256" => "b380a36938dcf1199a2b43ce761f5aa5f15ce645f88895ced88a05857e8547ca"
                ),
                "option" => new FileOption(
                    "themes/meta/favicon",
                    array(
                        "image/png" => "png",
                        "image/gif" => "gif",
                        "image/x-icon" => "ico",
                        "image/jpeg" => "jpg"
                    ), 256 * 1024 // Max 256 KiB
                )
            ),
            /*
                The color displayed in the title and address bars for mobile
                browsers.
            */
            "themes/meta/color" => array(
                "domain" => "themes",
                "section" => "meta",
                "default" => "#08263a",
                "option" => new ColorOption()
            ),
            /*
                ------------------------------------------------------------
                    COLOR THEME
                ------------------------------------------------------------
            */
            /*
                Select the color theme of the administration pages.
            */
            "themes/color/admin" => array(
                "domain" => "themes",
                "section" => "color",
                "default" => "dark",
                "option" => new SelectOption(array(
                    "light",
                    "dark"
                ))
            ),
            /*
                Select the default color theme of users' settings pages.
            */
            "themes/color/user-settings/theme" => array(
                "domain" => "themes",
                "section" => "color",
                "default" => "dark",
                "option" => new SelectOption(array(
                    "light",
                    "dark"
                ))
            ),
            /*
                Whether to allow users to set their own color theme for their
                settings pages instead of the default for their own account.
            */
            "themes/color/user-settings/allow-personalization" => array(
                "domain" => "themes",
                "section" => "color",
                "default" => true,
                "option" => new BooleanOption()
            ),
            /*
                Select the default color theme of the map.
            */
            "themes/color/map/theme/mapbox" => array(
                "domain" => "themes",
                "section" => "color",
                "default" => "basic",
                "option" => new SelectOption(array(
                    "basic",
                    "streets",
                    "bright",
                    "light",
                    "dark",
                    "satellite"
                ))
            ),
            /*
                Whether to allow users to set their own color theme for
                map instead of the default for their own account.
            */
            "themes/color/map/allow-personalization" => array(
                "domain" => "themes",
                "section" => "color",
                "default" => true,
                "option" => new BooleanOption()
            ),
            /*
                ------------------------------------------------------------
                    MAP MARKERS
                ------------------------------------------------------------
            */
            /*
                Select the style of map markers used by default on the map.
            */
            "themes/icons/default" => array(
                "domain" => "themes",
                "section" => "icons",
                "default" => "freefield-3d-compass",
                "option" => new IconSetOption()
            ),
            /*
                Whether to allow users to select their own map marker set
                instead of the default for their own account.
            */
            "themes/icons/allow-personalization" => array(
                "domain" => "themes",
                "section" => "icons",
                "default" => true,
                "option" => new BooleanOption()
            ),
            /*
================================================================================
    MAP SETTINGS
    Set up map settings and defaults
================================================================================
            */
            /*
                ------------------------------------------------------------
                    MAP PROVIDER
                ------------------------------------------------------------
            */
            /*
                Select which map provider to use.
            */
            "map/provider/source" => array(
                "domain" => "map",
                "section" => "provider",
                "default" => "mapbox",
                "option" => new SelectOption(array(
                    "mapbox"
                ))
            ),
            /*
                Access token obtained from Mapbox.
            */
            "map/provider/mapbox/access-token" => array(
                "domain" => "map",
                "section" => "provider",
                "default" => "",
                "option" => new StringOption()
            ),
            /*
                Select the default navigation provider to launch when users
                request directions to a given POI.
            */
            "map/provider/directions" => array(
                "domain" => "map",
                "section" => "provider",
                "default" => "google",
                "option" => new SelectOption(array_keys(
                    Geo::listNavigationProviders()
                ))
            ),
            /*
                ------------------------------------------------------------
                    DEFAULTS
                ------------------------------------------------------------
            */
            /*
                The latitude portion of the default coordinates to
                center the map at when it is loaded.
            */
            "map/default/center/latitude" => array(
                "domain" => "map",
                "section" => "default",
                "default" => 0.0,
                "option" => new FloatOption(-90.0, 90.0)
            ),
            /*
                The longitude portion of the default coordinates to
                center the map at when it is loaded.
            */
            "map/default/center/longitude" => array(
                "domain" => "map",
                "section" => "default",
                "default" => 0.0,
                "option" => new FloatOption(-180.0, 180.0)
            ),
            /*
                The default zoom level of the map.
            */
            "map/default/zoom" => array(
                "domain" => "map",
                "section" => "default",
                "default" => 14.0,
                "option" => new FloatOption(0.0, 20.0)
            ),
            /*
                The default research task component to use for map markers.
            */
            "map/default/marker-component" => array(
                "domain" => "map",
                "section" => "default",
                "default" => "reward",
                "option" => new SelectOption(array(
                    "objective",
                    "reward"
                ))
            ),
            /*
                ------------------------------------------------------------
                    MAP UPDATES
                ------------------------------------------------------------
            */
            /*
                The amount of time in seconds between every time FreeField
                updates the list of POIs and active field research for connected
                clients. If you experience high load on your server from
                FreeField, try increasing this value. The total volume of
                requests to the REST API for connected clients is (c/i) per
                second where c=number of active clients and i=refresh interval.
            */
            "map/updates/refresh-interval" => array(
                "domain" => "map",
                "section" => "updates",
                "default" => 15,
                "option" => new IntegerOption(1, null)
            ),
            /*
                ------------------------------------------------------------
                    GEOFENCING
                ------------------------------------------------------------
            */
            /*
                Limits submission of new Pokéstops to areas within the given
                polygonal region as defined by this list of corner coordinates.
                A `null` value disables geofencing and allows submission of POIs
                (and by extension, their field research) worldwide.
            */
            "map/geofence/geofence" => array(
                "domain" => "map",
                "section" => "geofence",
                "default" => null,
                "option" => new GeofenceOption()
            ),
            /*
                Whether or not to hide Pokéstops from the map if they are
                outside of the geofence selected above.
            */
            "map/geofence/hide-outside" => array(
                "domain" => "map",
                "section" => "geofence",
                "default" => false,
                "option" => new BooleanOption()
            )
        );
    }
}

?>
