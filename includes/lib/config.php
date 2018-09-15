<?php
/*
    This library file contains functions relating to configuration. The
    configuration file for FreeField is in JSON format with a keys and subkeys
    structure. E.g. the setting "site/uri" is stored like this in the JSON:

        "site": {
            "uri": "https:\/\/10.0.0.44\/freefield\/git\/"
        }

    Take a look at the config.json file in your FreeField installation for
    more examples.

    All settings and their defaults and valid values are listed in
    /includes/config/defs.php.
*/

class Config {
    private const CONFIG_LOCATION = __DIR__."/../userdata/config.json";

    /*
        `$config` holds the decoded JSON array of the config file. The
        `$configDefs` variable is populated from /includes/config/defs.php on
        initialization.
    */
    private static $config = false;
    private static $configDefs = null;

    /*
        Loads the config definitions to a variable in this class. The
        definitions contains a list of all available settings and is defined in
        /includes/config/defs.php. The `ConfigDefinitions::loadDefinitions()`
        function simply returns the definitions array.
    */
    public static function loadDefinitions() {
        require_once(__DIR__."/../config/defs.php");
        self::$configDefs = ConfigDefinitions::loadDefinitions();
    }

    /*
        Gets the entry for a setting in the configuration file. Used to get the
        current value and parameters for a setting.
    */
    public static function get($path) {
        self::ensureLoaded();

        /*
            Check if the requested settings path is present in the list of
            setting definitions.
        */
        if (!isset(self::$configDefs[$path])) {
            return null;
        }

        /*
            The setting is present - create a `ConfigEntry` instance for it.
        */
        $def = new ConfigEntry(self::$configDefs[$path]);

        /*
            Since the configuration file is arranged as objects with subkeys, we
            have to iterate deeper into the JSON tree structure until we hit
            the object we need. To do so, we split the path of the setting we're
            looking for by the separator / to get an array where each element is
            the next child of the configuration object. We make a copy of the
            `$config` object to `$conf`. We then search `$conf` for the first
            item of the path segments array. When found, `$conf` is replaced
            with the value of `$conf[$segment]`, and the loop continues, but
            this time searching for the first child of that object, i.e. the
            second segment of the path. This continues until we've found the
            correct setting, at which point `$conf` will be the value we're
            looking for and that can be returned.

            If at any point a segment child is not found, then the setting is
            not defined in the configuration file, so we'll return the entry
            without any specified value - this makes the entry fall back to its
            default value.
        */
        $conf = self::$config;
        $segments = explode("/", $path);
        foreach ($segments as $segment) {
            if (!isset($conf[$segment])) {
                /*
                    Setting not found in configuration file. Return entry with
                    default value.
                */
                return $def;
            }
            $conf = $conf[$segment];
        }
        /*
            The setting was found in the configuration file! Parse the value
            from the settings array to a parsed value and store it as the value
            in the configuration. Please see /includes/config/defs.php to see
            why this parsing is being done.
        */
        $def->setStorageEncodedValue($conf);
        return $def;
    }

    /*
        Gets the raw array value for a setting in the configuration file. This
        can be used to get array elements in the configuration file which do not
        have an associated setting, such as the list of webhooks and geofences.
    */
    public function getRaw($path) {
        self::ensureLoaded();

        $conf = self::$config;
        $segments = explode("/", $path);
        foreach ($segments as $segment) {
            if (!isset($conf[$segment])) return $conf;
            $conf = $conf[$segment];
        }
        return $conf;
    }

    /*
        This function is used to set the values of settings and then saving the
        updated configuration to the config file. This function also does
        permissions checks to see if the user is allowed to make changes to the
        passed settings.

        $options
            An associative array consisting of the setting paths that are being
            set, and their respective values. The values may either be already
            parsed to the correct data type when passed to this function, or be
            a string representation that hasn't been parsed yet. This allows us
            to pass values directly from PHP in the correct data type, but also
            to pass a `$_POST` array directly, whose data will be string
            representations of the values. In the latter case, the value is
            parsed before its validity is checked. If the value type is not a
            string, it is assumed that the first case is true; parsing is
            skipped and we go straight to validity checking.

        $validatePermissions
            Whether or not permissions should be validated for the settings
            which are being updated.
    */
    public static function set($options, $validatePermissions = false) {
        self::ensureLoaded();

        foreach ($options as $option => $value_raw) {
            /*
                Attempt to get the definition of the given setting, along with
                its current value. If this is null, that means there is no
                setting by this name, and we assume that the script invoking
                this function is trying to set an entire array of options under
                this element's key.
            */
            $def = self::get($option);

            if ($def === null && is_array($value_raw)) {
                /*
                    If the value is an array, and the key it corresponds to is
                    not defined as a stand-alone entry in the configuration
                    definitions tree in `$configtree`/`$flattree`, we can assume
                    that the script calling this is doing its own validation,
                    and is trying to set a whole block of values at once, the
                    block being at the path of `$option`. E.g. if `$option` ==
                    "site", the entire "site" block in the configuratio file
                    will be overwritten with the contents of `$value_raw`.
                */
                $value = $value_raw;

                /*
                        _
                       / \
                      / | \
                     /  |  \
                    /___o___\

                    !!! WARNING !!!

                    If `$value_raw` is an array of settings, permissions will
                    not be validated! If you're passing an array to this
                    function, you are responsible for manually performing
                    permissions validation!

                    AN EXCEPTION WILL BE THROWN IF THIS WARNING IS NOT HEEDED
                */
                if ($validatePermissions) {
                    /*
                        There exists no settings path in `$configDefs` for the
                        given setting's path. This can happen if `$option`
                        represents an array of different settings (e.g. "site"
                        which is a block containing the settings "uri" and
                        "name" - there exists no settings path for "site"
                        itself, although "site/uri" and "site/name" are both
                        separately defined).
                    */
                    throw new Exception("Cannot verify permissions when setting an ".
                                        "array as value for a configuration path!");
                    exit;
                }
            } elseif ($def === null) {
                /*
                    If this block is reached and the given `$option` doesn't
                    exist as a key in `$configDefs`, and the given value is not
                    an array of subkeys with corresponding values, then the
                    caller of this function is trying to set a setting that
                    simple doesn't exist in any form, so this key should be
                    skipped.
                */
                continue;
            } else {
                /*
                    Get the data type of this setting to perform input
                    validation.
                */
                $opt = $def->getOption();

                /*
                    If the value of the given settings key is a string, we can
                    assume that the array passed to `set()` is from `$_POST` or
                    otherwise not already parsed, and as such contains raw
                    string equivalents for the values that should be stored for
                    the given setting. This means we'll have to parse the value
                    first before validating it. If the data type for the given
                    option is actually supposed to be a string, `parseValue()`
                    will just return the same string, making these two
                    comparison blocks effectively the same operation for string
                    settings.
                */
                if (is_string($value_raw)) {
                    $value = $opt->parseValue($value_raw);
                } else {
                    $value = $value_raw;
                }

                /*
                    Skip the setting if the `Option` class for the setting
                    declares that the parsed value is invalid (e.g. out of
                    bounds).
                */
                if (!$opt->isValid($value)) continue;

                /*
                    If permissions validation is requested, ensure that the user
                    has permission to change the setting before saving it.
                */
                if ($validatePermissions && !$def->isAuthorizedToChange($value)) {
                    continue;
                }

                /*
                    Run pre-commit callbacks for the given option.
                */
                if (!$opt->preCommit($value_raw, $value)) continue;

                /*
                    Encode the value for storage in the configuration array.
                */
                $value = $def->getStorageEncodedValue($value);
            }

            /*
                Push the setting change to `$config`:

                What this does, is it starts at the deepest nesting level of the
                setting in the configuration array, finds the parent array of
                the setting, and changes the setting in that array. Example for
                "security/approval/require": The `$value` is, say, `true`. The
                deepest nested item in the array for that setting is "require",
                as it is the last item in the path. The parent of "require" is
                "security/approval".

                "security/approval" is retrieved:

                    $parent = array(
                        "require" => false,
                        "by-qr" => true
                    );

                ..and the value of "require" (the next item in the setting's
                path after "security/approval") is set to `$value`. Note how the
                value of "require" has changed:

                    $parent = array(
                        "require" => true,
                        "by-qr" => true
                    );

                `$value` is now set to `$parent`, so `$value` becomes the above
                array. The loop now iterates to set "security/approval" in the
                array. The parent of "security/approval" (i.e. "security") is
                retrieved:

                    $parent = array(
                        "approval" => array(
                            "require" => false,
                            "by-qr" => true
                        ),
                        "validate-ua" => "lenient",
                        "validate-lang" => true
                    );

                ..and the value of "approval" (the next item in the setting's
                path after "security") is set to `$value`. Since `$value` is the
                "approval" array with the "require" setting patched to `true`,
                the entire "approval" array in `$parent` is overwritten:

                    $parent = array(
                        "approval" => array(
                            "require" => true,
                            "by-qr" => true
                        ),
                        "validate-ua" => "lenient",
                        "validate-lang" => true
                    );

                Again, note that the "approval/require" value has changed to
                reflect the update. `$value` is once again set to the value of
                `$parent` so that `$value` now holds the entire updated
                "security" array. Next, the loop iterates to set "security"
                itself. The parent of "security" (i.e. the root array) is
                retrieved:

                    $parent = array(
                        "security" => array(
                            "approval" => array(
                                "require" => false,
                                "by-qr" => true
                            ),
                            "validate-ua" => "lenient",
                            "validate-lang" => true
                        ),
                        "auth" => array(
                            ...
                        ),
                        ...
                    );

                As before, the "security" key is replaced with the updated
                "security" element stored in `$value`:

                    $parent = array(
                        "security" => array(
                            "approval" => array(
                                "require" => true,
                                "by-qr" => true
                            ),
                            "validate-ua" => "lenient",
                            "validate-lang" => true
                        ),
                        "auth" => array(
                            ...
                        ),
                        ...
                    );

                ..and again note that the value of "security/approval/require"
                has been correctly updated to `true` in this array. `$value` is
                once again updated to the value of `$parent`.

                At this point, we have iterated over all the segments of the
                settings path ("require", "approval" and "security"), so the
                loop ends. Since we retrieved the root array into `$parent`,
                `$value` now holds the patched root configuration array. We can
                overwrite the old configuration in `self::$config` with this
                updated array, and the configuration array used by this script
                will thus be the updated version where the value of
                "security/approval/require" is updated.
            */
            $s = explode("/", $option);
            for ($i = count($s) - 1; $i >= 0; $i--) {
                /*
                    Loop over the segments and for every iteration, find the
                    parent array directly above the current `$s[$i]`.
                */
                $parent = self::$config;
                for ($j = 0; $j < $i; $j++) {
                    $parent = $parent[$s[$j]];
                }
                /*
                    Update the value of `$s[$i]` in the array. Store a copy of
                    this array as the value to assign to the next parent
                    segment.
                */
                $parent[$s[$i]] = $value;
                $value = $parent;
                /*
                    The next iteration finds the next parent above the current
                    parent and replaces the value of the key in that parent
                    which would hold the value of the current parent array with
                    the updated parent array that has the setting change applied
                    to it.
                */
            }
            self::$config = $value;
        }
        /*
            Save the changed `$config` array to file.
        */
        self::saveConfig();
    }

    /*
        Checks if any one or more of the given settings is set to the given
        value.

        $paths
            An array of setting paths.

        $value
            The value to evaluate the settings against.
    */
    public static function ifAny($paths, $value) {
        foreach ($paths as $path) {
            if (self::get($path)->value() === $value) return true;
        }
        return false;
    }

    /*
        Returns a list of all settings keys available in FreeField.
    */
    public static function listAllKeys() {
        self::ensureLoaded();
        return array_keys(self::$configDefs);
    }

    /*
        Returns a list of all sections for one particular domain from the
        definitions list. This is used on the administration pages to determine
        which settings to display on the page when showing the settings for one
        particular domain on the page.
    */
    public static function listKeysForDomain($domain) {
        self::ensureLoaded();

        /*
            Loop over all setting definitions and check their assigned domain.
            If it matches the provided domain, add it to the list of keys for
            that domain.
        */
        $keys = array();
        foreach (self::$configDefs as $key => $def) {
            if ($def["domain"] == $domain) $keys[] = $key;
        }

        return $keys;
    }

    /*
        Returns a list of all available domains, along with the icons that
        should be used to display them in the sidebar on the administration
        pages, and whether rendering of the domain should be handled by a
        dedicated script or by /admin/index.php.
    */
    public static function listDomains() {
        /*
            The `$domains` array contains a list of pages (domains) to display
            on the user interface. Each domain entry in this array is an array
            with the following keys:

            `icon`
                The FontAwesome icon to display for this domain in the sidebar.

            `custom-handler`
                Boolean. True if the settings for the given domain should be
                rendered by an external script (/includes/admin/<domain>.php),
                false if it should render as a standard list of configuration
                options, as defined in /admin/index.php.

            Each domain where `custom-handler` is set to false will contain a
            list of configuration options within the equivalent `domain` set in
            /includes/config/defs.php. E.g. the "main" page will contain all of
            the settings that have the `main` domain assigned to them in the
            definitions list in that file.

            Please see /includes/config/defs.php for detailed information on
            what settings each of the `custom-handler` == false domains
            represent.
        */
        $domains = array(

            // Main settings (e.g. site URI, database connections)
            "main" => array(
                "icon" => "cog",
                "custom-handler" => false
            ),

            // User management
            "users" => array(
                "icon" => "users",
                "custom-handler" => true
            ),

            // Groups management
            "groups" => array(
                "icon" => "user-shield",
                "custom-handler" => true
            ),

            // POI management
            "pois" => array(
                "icon" => "map-marker-alt",
                "custom-handler" => true
            ),

            // Permissions
            "perms" => array(
                "icon" => "check-square",
                "custom-handler" => false
            ),

            // Security settings
            "security" => array(
                "icon" => "shield-alt",
                "custom-handler" => false
            ),

            // Authentication (sign-in) providers and setup
            "auth" => array(
                "icon" => "lock",
                "custom-handler" => false
            ),

            // Theme settings and defaults
            "themes" => array(
                "icon" => "palette",
                "custom-handler" => false
            ),

            // Map provider settings (e.g. map API keys)
            "map" => array(
                "icon" => "map",
                "custom-handler" => false
            ),

            // Geofence settings
            "fences" => array(
                "icon" => "expand",
                "custom-handler" => true
            ),

            // Webhooks
            "hooks" => array(
                "icon" => "link",
                "custom-handler" => true
            )

        );

        return $domains;
    }

    /*
        Returns a list of all sections available for a particular domain.
    */
    public static function listSectionsForDomain($domain) {
        self::ensureLoaded();

        /*
            Loop over all setting definitions and check their assigned domain
            and section. If they match the provided domain, add the section to
            the list of sections for that domain.
        */
        $sections = array();
        foreach (self::$configDefs as $key => $def) {
            if ($def["domain"] == $domain) $sections[] = $def["section"];
        }

        /*
            Remove duplicates before returning the array.
        */
        return array_values(array_unique($sections));
    }

    /*
        Returns a list of all settings keys for a particular section on a
        domain.
    */
    public static function listKeysForSection($domain, $section) {
        self::ensureLoaded();

        /*
            Loop over all setting definitions and check their assigned domain
            and section. If they match the provided domain and section, add it
            to the list of keys for that section.
        */
        $keys = array();
        foreach (self::$configDefs as $key => $def) {
            if ($def["domain"] == $domain && $def["section"] == $section)
                $keys[] = $key;
        }

        return $keys;
    }

    /*
        Returns a string representing the class name of the data type option for
        the given setting. Example: "StringOption".
    */
    public static function getOptionType($path) {
        self::ensureLoaded();

        /*
            Check if the setting has a definition. If not, return null.
        */
        if (isset(self::$configDefs[$path])) {
            return get_class(self::$configDefs[$path]["option"]);
        } else {
            return null;
        }
    }

    /*
        Returns an I18N helper object for the given setting. Please see the
        comments for `ConfigSettingI18N` below for information about the purpose
        of this class.
    */
    public static function getSettingI18N($path) {
        return new ConfigSettingI18N($path);
    }

    /*
        Returns an I18N helper object for the given admin page section. Please
        please see comments for `ConfigSectionI18N` below for information about
        the purpose of this class.
    */
    public static function getSectionI18N($domain, $section) {
        return new ConfigSectionI18N($domain, $section);
    }

    /*
        Returns an I18N helper object for the given admin page domain. Please
        see the comments for `ConfigDomainI18N` below for information about the
        purpose of this class.
    */
    public static function getDomainI18N($domain) {
        return new ConfigDomainI18N($domain);
    }

    /*
        Returns the given setting's path in I18N key format.

        I18N is handled with setting.<setting>.name and setting.<setting>.desc.
        However, setting paths have forward slash delimiters, and I18N tokens
        dot delimiters. The setting path should be translated to the I18N key
        format to obtain an I18N key for the given setting.

        For example, the setting "setup/uri" is resolved to the following keys:
          - setting.setup.uri.name
          - setting.setup.uri.desc

        This function converts "setup/uri" to "setup.uri" so that it can be used
        by `Config*I18N` classes to create full I18N keys. This function should
        never be called outside of this file - `Config*I18N` classes exist to
        make these conversions in a centralized place. Construct an instance of
        the `Config*I18N` class that corresponds to your desired path type
        (Setting, Section or Domain) and use that instance's functions to get
        the I18N key you need.
    */
    public static function translatePathI18N($path) {
        return str_replace("/", ".", str_replace("-", "_", $path));
    }

    /*
        Takes an endpoint path in FreeField and returns a full URL to that
        path's location on the web.

        For example:
            "/admin/index.php" -> "http://example.com/admin/index.php"

        Always use this function if you need the full URL of some object in the
        current installation of FreeField.
    */
    public static function getEndpointUri($endpoint) {
        $basepath = self::get("site/uri")->value();
        return (
            substr($basepath, -1) == "/"
            ? substr($basepath, 0, -1)
            : $basepath
        ).$endpoint;
    }

    /*
        Loads the configuration file from the filesystem and stores its contents
        in class-level `$config`.
    */
    private static function loadConfig() {
        if (!file_exists(self::CONFIG_LOCATION)) {
            self::$config = array();
        } else {
            self::$config = json_decode(file_get_contents(self::CONFIG_LOCATION), true);
        }
    }

    /*
        Saves the current configuration in `$config` to the filesystem.
    */
    private static function saveConfig() {
        file_put_contents(
            self::CONFIG_LOCATION,
            json_encode(self::$config, JSON_PRETTY_PRINT)
        );
    }

    /*
        Load the configuration file if not already loaded.
    */
    private static function ensureLoaded() {
        if (self::$config === false) self::loadConfig();
    }
}

/*
    When the `Config` class is included from a script that needs configuration
    entries, the configuration tree in `Config::$configDefs` needs to be
    initialized first. Do this right away after declaring the class.
*/
Config::loadDefinitions();

/*
    This class exists to provide common functions for configuration settings in
    FreeField. An instance of this class is returned when `Config::get()` is
    called. The class provides functions for returning the value of the setting
    in various formats, as well as handling permission checks and providing
    data type definitions.

    This class must only be constructed from the array in
    /includes/config/defs.php. Please see that file for information on how this
    class is constructed.
*/
class ConfigEntry {
    // The area of the administration pages this setting appears on.
    private $domain = null;
    // The section on the above page that this section appears underneath.
    private $section = null;
    // The indentation level of the setting on the administration pages.
    private $indentation = 0;
    // Permissions required to view and modify the setting.
    private $permissions = array();
    // The default value of the setting.
    private $default = null;
    // An `Option` data type class for the setting.
    private $option = null;
    // Whether or not this setting is enabled on the administration pages.
    private $isEnabled = true;
    // A value to return instead of the current value if disabled.
    private $valueIfDisabled = null;
    // The current value of the setting.
    private $value = null;

    public function __construct($definition) {
        if (isset($definition["domain"]))
            $this->domain           = $definition["domain"];
        if (isset($definition["section"]))
            $this->section          = $definition["section"];
        if (isset($definition["indentation"]))
            $this->indentation      = $definition["indentation"];
        if (isset($definition["permissions"]))
            $this->permissions      = $definition["permissions"];
        if (isset($definition["default"]))
            $this->default          = $definition["default"];
        if (isset($definition["option"]))
            $this->option           = $definition["option"];
        if (isset($definition["enable-only-if"]))
            $this->isEnabled        = $definition["enable-only-if"];
        if (isset($definition["value-if-disabled"]))
            $this->valueIfDisabled  = $definition["value-if-disabled"];

        /*
            Ensure that the "admin/<domain>/general" and
            "admin/<domain>/section/<section>" permissions are added by default
            to the required permission for the setting. This is a basic, minimal
            permission used to restrict access to various pages of the
            administration interface.
        */
        $this->permissions[] = "admin/".$this->domain."/general";
        $this->permissions[] = "admin/".$this->domain."/section/".$this->section;
    }

    /*
        Returns the domain on the administration pages on which this setting
        appears.
    */
    public function getDomain() {
        return $this->domain;
    }

    /*
        Returns the section on the page defined in `getDomain()` this setting
        appears underneath.
    */
    public function getSection() {
        return $this->section;
    }

    /*
        Returns the indentation level of this setting on the administration
        pages, relative to the other the settings on the page.
    */
    public function getIndentationLevel() {
        return $this->indentation;
    }

    /*
        Returns the default value of this setting.
    */
    public function getDefault() {
        return $this->option->decodeSavedValue($this->default);
    }

    /*
        Returns the `Option` class instance representing this setting. Used for
        parsing and validating data. Please see /includes/config/types.php for
        a list of available option classes and their purpose.
    */
    public function getOption() {
        return $this->option;
    }

    /*
        Whether or not this setting is enabled and editable on the
        administration pages.
    */
    public function isEnabled() {
        return $this->isEnabled;
    }

    /*
        Returns a list of permissions that a user must have in order to view and
        make changes to this setting.
    */
    public function getPermissions() {
        return $this->permissions;
    }

    /*
        Checks whether or not the given user has permission to view the current
        setting and make changes to it. Defaults to the currently logged in
        user. Note: If you want to check whether or not the user can make a
        specific change to the setting, please use `isAuthorizedToChange()`
        instead. This function only checks the user against the permissions
        declared in the permissions list for the setting, and does not attempt
        to validate the passed value to see if the user is allowed to make a
        specific change to the value, even if they have general access to it.
    */
    public function hasPermission($user = null) {
        __require("auth");
        if ($user === null) $user = Auth::getCurrentUser();
        $permissions = $this->getPermissions();
        foreach ($permissions as $permission) {
            if (!$user->hasPermission($permission)) {
                return false;
            }
        }
        return true;
    }

    /*
        Checks whether or not the given user has permission to set the value of
        this setting to the given value. Defaults to the currently logged in
        user. This setting first checks `$this->hasPermission()` to check if the
        user has general access to the setting, then checks if the user has
        permission to update the setting to the given value. For example, a user
        may have permission to change a given `PermissionOption`, but may not be
        allowed to change its value to a higher value than their own current
        permission level.
    */
    public function isAuthorizedToChange($newValue, $user = null) {
        __require("auth");
        if ($user === null) $user = Auth::getCurrentUser();

        if (!$this->hasPermission($user)) return false;
        if (!$this->getOption()->isAuthorizedToChange($this->value(), $newValue, $user)) return false;
        return true;
    }

    /*
        Sets the value of this `ConfigEntry` instance to the given value. This
        function takes a value as it would appear in the configuration JSON
        array and parses it according to the setting's `Option` class.
    */
    public function setStorageEncodedValue($value) {
        $this->value = $this->getOption()->decodeSavedValue($value);
    }

    /*
        Converts the given value to a format that can be stored in the
        configuration file in JSON format, according to the setting's `Option`
        class.
    */
    public function getStorageEncodedValue($value) {
        return $this->getOption()->encodeSavedValue($value);
    }

    /*
        Returns the value of this setting, or the default if no value is set.
    */
    public function value() {
        /*
            Some settings may require certain preconditions to work properly.
            Such settings have a boolean assertion defined in the
            "enable-only-if" array key in the definitions array. If that
            assertion fails, a default value should be returned instead of the
            actual value set in the configuration file.
        */
        if (!$this->isEnabled() && $this->valueIfDisabled === null) {
            return $this->valueIfDisabled;
        } else {
            if ($this->value === null) {
                return $this->getDefault();
            } else {
                return $this->value;
            }
        }
    }

    /*
        The output of `value()` is not HTML safe. This function is a wrapper
        around `value()` that escapes special HTML characters to avoid an XSS
        attack vector associated with directly outputting the value of a
        configuration entry to a page.
    */
    public function valueHTML() {
        return htmlspecialchars(strval($this->value()), ENT_QUOTES);
    }

    /*
        The output of `value()` is not JavaScript safe. This function is a
        wrapper around `value()` that returns the JSON encoded value of a given
        setting to avoid an XSS attack vector associated with directly
        outputting the value of a configuration entry to a page.
    */
    public function valueJS() {
        return json_encode($this->value());
    }

    /*
        The output of `value()` is not URL safe. This function is a wrapper
        around `value()` that returns the URL encoded value of a given setting
        to avoid URL hijack attack vector associated with directly outputting
        the value of a configuration entry to a URL.
    */
    public function valueURL() {
        return urlencode($this->value());
    }
}

/*
    THE `Config*I18N` CLASSES

    In order to centralize internationalization for settings, domains and
    sections, three helper classes exist to handle lookup of I18N tokens for
    these objects:

      - `ConfigSettingI18N`
      - `ConfigSectionI18N`
      - `ConfigDomainI18N`

    These classes have functions that return the I18N tokens for the given
    setting path, admin page section and domain respectively - this includes
    setting/section/domain names/labels, as well as descriptions where
    available.
*/

/*
    `ConfigSettingI18N` provides I18N tokens for settings, including their
    names, decriptions, and options and labels if applicable. The class is
    constructed with the setting's path, and an instance can be obtained using
    `Config::getSettingI18N()`.
*/
class ConfigSettingI18N {
    private $path = null;

    function __construct($path) {
        $this->path = $path;
    }

    /*
        Returns an I18N token representing the name of the setting.
    */
    public function getName() {
        return "setting.".
               Config::translatePathI18N($this->path).
               ".name";
    }

    /*
        Returns an I18N token representing a description of the setting's
        purpose - may include information on what it is used for, what kind of
        values are valid, how it should be set, and considerations regarding
        changing the setting.
    */
    public function getDescription() {
        return "setting.".
               Config::translatePathI18N($this->path).
               ".desc";
    }

    /*
        For selection boxes - returns the label of any given option in the drop-
        down list of items selectable in the box.
    */
    public function getOption($option) {
        return "setting.".
               Config::translatePathI18N($this->path).
               ".option.".
               Config::translatePathI18N($option);
    }

    /*
        For booleans/checkboxes - returns the label that should be displayed
        next to the checkbox itself. (Without this label, there would only be a
        plain checkbox on the page, with no label next to it apart from the name
        of the setting.)
    */
    public function getLabel() {
        return "setting.".
               Config::translatePathI18N($this->path).
               ".label";
    }
}

/*
    `ConfigSectionI18N` provides I18N tokens for specific sections on any page
    on the administration pages, including their names and decriptions if
    applicable. The class is constructed with the ID of the section and the
    domain it is contained in (e.g. "main" domain and "database" section), and
    an instance can be obtained using `Config::getSectionI18N($domain,
    $section)` or `ConfigDomainI18N::getSection($section)`.
*/
class ConfigSectionI18N {
    private $domain = null;
    private $section = null;

    /*
        Some sections have descriptions to guide the user on how to configure
        various settings. These are displayed immediately underneath the header
        for that section on the administration pages. Only sections listed in
        this array will display descriptions. The syntax for the array is:

            SECTIONS_WITH_DESCRIPTIONS = array(
                "<domain>/<section>" => array(
                    "{%1}-replacement-token",
                    "{%2}-replacement-token",
                    ...
                )
            );

        Each section is declared as an array. The array can be empty, or it may
        contain replacement strings. Consider e.g. a section which has the
        description string "Please read {%1}the documentation{%2}." The
        replacement tokens {%1} and {%2} will be replaced with the contents of
        the 0th and 1st items in the array for that section below, respectively.

        Let's say the same setting has the following array:

            array(
                '<a href="http://example.com/">',
                '</a>'
            );

        The output string would now be:

            Please read <a href="http://example.com/">the documentation</a>.
    */
    private const SECTIONS_WITH_DESCRIPTIONS = array(
        "security/sessions" => array(
            // admin.section.security.sessions.desc
        ),
        "auth/discord" => array(
            // admin.section.auth.discord.desc
            '<a target="_blank" href="https://github.com/bilde2910/FreeField/wiki/Authentication-providers/Discord">',
            '</a>'
        ),
        "auth/telegram" => array(
            // admin.section.auth.telegram.desc
            '<a target="_blank" href="https://github.com/bilde2910/FreeField/wiki/Authentication-providers/Telegram">',
            '</a>'
        ),
        "auth/reddit" => array(
            // admin.section.auth.reddit.desc
            '<a target="_blank" href="https://github.com/bilde2910/FreeField/wiki/Authentication-providers/Reddit">',
            '</a>'
        ),
        "auth/groupme" => array(
            // admin.section.auth.groupme.desc
            '<a target="_blank" href="https://github.com/bilde2910/FreeField/wiki/Authentication-providers/GroupMe">',
            '</a>'
        ),
        "map/geofence" => array(
            // admin.section.map.geofence.desc
            '<a target="_blank" href="https://github.com/bilde2910/FreeField/wiki/Geofencing">',
            '</a>'
        )
    );

    function __construct($domain, $section) {
        $this->domain = $domain;
        $this->section = $section;
    }

    /*
        Returns an I18N token representing the name of the section.
    */
    public function getName() {
        return "admin.section.".
               Config::translatePathI18N($this->domain).
               ".".
               Config::translatePathI18N($this->section).
               ".name";
    }

    /*
        Returns an I18N token representing a description displayed underneath
        the header for the section on the page. Not all sections have
        descriptions - those that do have the "__hasdesc" setting defined in the
        configuration tree. Please see `/includes/config/defs.php` for more
        specific information regarding how this is defined for each section.
    */
    public function getLocalizedDescriptionHTML() {
        if (isset(self::SECTIONS_WITH_DESCRIPTIONS[$this->domain."/".$this->section])) {
            __require("i18n");
            $replacements = self::SECTIONS_WITH_DESCRIPTIONS[$this->domain."/".$this->section];
            $token = "admin.section.".
                     Config::translatePathI18N($this->domain).
                     ".".
                     Config::translatePathI18N($this->section).
                     ".desc";

            if (count($replacements) > 0) {
                return '<p>'.I18N::resolveArgsHTML(
                    $token,
                    false,
                    $replacements
                ).'</p>';
            } else {
                return '<p>'.I18N::resolveHTML(
                    $token
                ).'</p>';
            }
        } else {
            return null;
        }
    }
}

/*
    `ConfigDomainI18N` provides I18N tokens for specific domains (pages) of the
    administration pages, including their names and decriptions. The class is
    constructed with the ID of the domain (e.g. "main" or "security"), and an
    instance can be obtained using `Config::getDomainI18N()`.
*/
class ConfigDomainI18N {
    private $domain = null;

    function __construct($domain) {
        $this->domain = $domain;
    }

    /*
        Returns an I18N token representing the name of the domain/page.
    */
    public function getTitle() {
        return "admin.domain.".
               Config::translatePathI18N($this->domain).
               ".name";
    }

    /*
        Returns an I18N token representing a sub-title displayed underneath the
        main title on the page.
    */
    public function getSubtitle() {
        return "admin.domain.".
               Config::translatePathI18N($this->domain).
               ".desc";
    }

    /*
        This function returns an I18N helper instance for any given section
        contained within this domain. This function is here to make it easier to
        get a section I18N helper when you already have an instance of the
        relevant domain I18N helper. It is possible to get a section helper
        using fewer arguments and less code this way than to call the
        `Config::getSectionI18N()` function, which takes two arguments rather
        than the one required for this function.
    */
    public function getSection($section) {
        return new ConfigSectionI18N($this->domain, $section);
    }
}

?>
