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
    /lib/config/tree.php.
*/

class Config {
    private const CONFIG_LOCATION = __DIR__."/../config.json";

    /*
        `$config` holds the decoded JSON array of the config file. The
        `$configtree` variable is populated from /includes/config/tree.php on
        initialization. This is iterated over and the full keys extracted to a
        one-dimensional array in `$flattree`. Example:

            $configtree = array(
                "main" => array(
                    "access" => array(
                        "site/uri" => array(
                            "default" => "",
                            "option" => new StringOption('^https?\:\/\/')
                        )
                        "site/name" => array(
                            "default" => "FreeField",
                            "option" => new StringOption()
                        )
                    ),
                    "database" => array(
                        "database/type" => array(
                            "default" => "mysqli",
                            "option" => new SelectOption(array(
                                "mysql",
                                "mysqli",
                                "pgsql",
                                "sqlite",
                                "sqlite3"
                            ))
                        )
                    )
                )
            );

        This array becomes:

            $flattree = array(
                "site/uri" => array(
                    "default" => "",
                    "option" => new StringOption('^https?\:\/\/')
                ),
                "site/name" => array(
                    "default" => "FreeField",
                    "option" => new StringOption()
                ),
                "database/type" => array(
                    "default" => "mysqli",
                    "option" => new SelectOption(array(
                        "mysql",
                        "mysqli",
                        "pgsql",
                        "sqlite",
                        "sqlite3"
                    ))
                )
            );

        This makes it easy to look up defaults and options for each setting
        without knowing the setting's domain and section.
    */
    private static $config = false;
    private static $flattree = null;
    private static $configtree = null;

    /*
        Loads the config tree to a variable in this class. The configuration
        tree contains a list of all available settings and is defined in
        /lib/config/tree.php. The `ConfigTree::loadTree()` function simply
        returns the configuration tree array.
    */
    public static function loadTree() {
        require_once(__DIR__."/../config/tree.php");
        self::$configtree = ConfigTree::loadTree();
    }

    /*
        Gets the value of a setting in the configuration file. Falls back to the
        setting's default value.
    */
    public static function get($path) {
        if (self::$config === false) self::loadConfig();

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
            not defined in the configuration file, so we'll return the default
            value for the given setting path instead.
        */
        $conf = self::$config;
        $segments = explode("/", $path);
        foreach ($segments as $segment) {
            if (!isset($conf[$segment])) return self::getDefault($path);
            $conf = $conf[$segment];
        }
        return $conf;
    }

    /*
        The output of `get()` is not HTML safe. This function is a wrapper
        around `get()` that escapes special HTML characters to avoid an XSS
        attack vector associated with directly outputting the value of a
        configuration entry to a page.
    */
    public static function getHTML($path) {
        return htmlspecialchars(strval(self::get($path)), ENT_QUOTES);
    }

    /*
        The output of `get()` is not JavaScript safe. This function is a wrapper
        around `get()` that returns the JSON encoded value of a given setting to
        avoid an XSS attack vector associated with directly outputting the value
        of a configuration entry to a page.
    */
    public static function getJS($path) {
        return json_encode(self::get($path));
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
        /*
            Load the configuration file if not already loaded, and fetch a
            flattened version of the configuration definition tree for easy
            access to default values and input validation functions.
        */
        if (self::$config === false) self::loadConfig();
        $flat = self::getFlatTree();

        if ($validatePermissions) {
            /*
                If validation is requested, the authentication module is needed
                to check if the currently signed in user has permission to
                change any given setting.
            */
            __require("auth");

            /*
                We also need to build a lookup table for checking which
                permissions are required for each setting. This would be the
                "admin/<domain>/general" permission where `domain` is the domain
                in which the setting resides.
            */
            $permissionsAssoc = array();
            foreach (self::$configtree as $domain => $sdata) {
                foreach ($sdata as $section => $perms) {
                    foreach ($perms as $perm => $data) {
                        $permissionsAssoc[$perm] = $domain;
                    }
                }
            }
        }

        /*
            Create an array of options that are submitted, but which can not be
            saved due to lack of permissions.
        */
        $optDeny = array();
        foreach ($options as $option => $value_raw) {
            if ($validatePermissions) {
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
                $perm = "admin/".$permissionsAssoc[$option]."/general";
                if (!Auth::getCurrentUser()->hasPermission($perm)) {
                    $optDeny[] = $option;
                }
                if (!isset($flat[$option])) {
                    /*
                        There exists no settings path in `$flattree` for the
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

                /*
                    Values contains the definition of the given settings path
                    from the configuration tree:

                        $values = array(
                            "default" => default_value,
                            "option" => new DefaultOption()
                        );
                */
                $values = $flat[$option];
                if (get_class($values["option"]) == "PermissionOption") {
                    /*
                        If the given settings path is a permissions type option,
                        a check should be done to ensure that the user is higher
                        than the permission level required for both the previous
                        and the updated permission level. This is to prevent
                        users both from lowering the permission levels of
                        settings higher than their own level to one that grants
                        them access (privilege escalation), and to prevent users
                        locking themselves and others of the same or higher
                        ranks out from accessing functions due to changing the
                        required permission level to something higher than their
                        own permissions.
                    */
                    $old = self::get($option);
                    $new = intval($value_raw);
                    $max = max($old, $new);
                    if (!Auth::getCurrentUser()->canChangeAtPermission($max)) {
                        $optDeny[] = $option;
                    }
                }
            }
        }

        /*
            Now, it's time to save all valid settings.
        */
        foreach ($options as $option => $value_raw) {
            /*
                If we previously determined that the current user does not have
                permission to save this setting, skip it.
            */
            if (in_array($option, $optDeny)) continue;

            if (!isset($flat[$option]) && is_array($value_raw)) {
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
            } else {
                /*
                    If this block is reached and the given `$option` doesn't
                    exist as a key in `$flattree`, and the given value is not an
                    array of subkeys with corresponding values, then the caller
                    of this function is trying to set a setting that simply
                    doesn't exist in any form, so this key should be skipped.
                */
                if (!isset($flat[$option])) continue;

                /*
                    Values contains the definition of the given settings path
                    from the configuration tree:

                        $values = array(
                            "default" => default_value,
                            "option" => new DefaultOption()
                        );
                */
                $values = $flat[$option];

                /*
                    Get the `option` key to perform input validation.
                */
                $opt = $values["option"];

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
                    Skip the setting if the Option class for the setting
                    declares that the parsed value is invalid (e.g. out of
                    bounds).
                */
                if (!$opt->isValid($value)) continue;
            }

            /*
                Push the setting change to `$config`. This block of `case`
                statements allows saving settings up to 10 objects deep. There
                probably a much better way to do this in a recursive manner.
                TODO?
            */
            $s = explode("/", $option);
            switch (count($s)) {
                case 1:
                    self::$config[$s[0]] = $value;
                    break;
                case 2:
                    self::$config[$s[0]][$s[1]] = $value;
                    break;
                case 3:
                    self::$config[$s[0]][$s[1]][$s[2]] = $value;
                    break;
                case 4:
                    self::$config[$s[0]][$s[1]][$s[2]][$s[3]] = $value;
                    break;
                case 5:
                    self::$config[$s[0]][$s[1]][$s[2]][$s[3]][$s[4]] = $value;
                    break;
                case 6:
                    self::$config[$s[0]][$s[1]][$s[2]][$s[3]][$s[4]][$s[5]] = $value;
                    break;
                case 7:
                    self::$config[$s[0]][$s[1]][$s[2]][$s[3]][$s[4]][$s[5]][$s[6]] = $value;
                    break;
                case 8:
                    self::$config[$s[0]][$s[1]][$s[2]][$s[3]][$s[4]][$s[5]][$s[6]][$s[7]] = $value;
                    break;
                case 9:
                    self::$config[$s[0]][$s[1]][$s[2]][$s[3]][$s[4]][$s[5]][$s[6]][$s[7]][$s[8]] = $value;
                    break;
                case 10:
                    self::$config[$s[0]][$s[1]][$s[2]][$s[3]][$s[4]][$s[5]][$s[6]][$s[7]][$s[8]][$s[9]] = $value;
                    break;
            }
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
            The value to evaluate.
    */
    public static function ifAny($paths, $value) {
        foreach ($paths as $path) {
            if (self::get($path) === $value) return true;
        }
        return false;
    }

    /*
        Returns a flattened version of the configuration tree from
        /includes/config/tree.php. The justification for this function is
        declared at the start of this file, before the declaration of the
        `$flattree` variable.
    */
    public static function getFlatTree() {
        /*
            The result of `getFlatTree()` is cached for faster subsequent
            lookups. If `$flattree` has not been generated yet, create it as an
            empty array and then populate it with all settings definitions.
        */
        if (self::$flattree !== null) return self::$flattree;
        self::$flattree = array();

        /*
            Loop over all domains and their sections to get a list of setting
            definitions. The setting defitions, here declared as `$values`, has
            the following structure:

                $values = array(
                    "default" => default_value,
                    "option" => new DefaultOption()
                );

            The array defines a default value for the setting, as well as an
            Option class instance that determines the data type and data
            processing functions for its values.

            Please see /includes/config/tree.php to see the structure of
            `$configtree`.
        */
        foreach (self::$configtree as $domain => $sections) {
            foreach ($sections as $section => $paths) {
                foreach ($paths as $path => $values) {
                    /*
                        Per the `$configtree` definition, some sections may have
                        descriptions or other parameters - the fields declaring
                        these start with two underscores. No setting paths will
                        ever start with two underscores, so we skip all paths
                        that start with two underscores.
                    */
                    if (substr($path, 0, 2) !== "__") self::$flattree[$path] = $values;
                }
            }
        }

        return self::$flattree;
    }

    /*
        Returns a list of all sections and their associated settings for one
        particular domain from the configuration tree. This is used on the
        administration pages to determine which settings to display on the page
        when showing the settings for one particular domain on the page.
    */
    public static function getTreeDomain($domain) {
        return self::$configtree[$domain];
    }

    /*
        Returns the default value for the given setting, or `null` if the
        setting was not found. Note that the default value may also be `null`,
        hence checking for `null` is not a reliable way to check for the
        existence of a particular setting.
    */
    public static function getDefault($item) {
        $conf = self::getFlatTree();
        foreach ($conf as $path => $values) {
            if ($path == $item) return $values["default"];
        }
        return null;
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
        $basepath = self::get("site/uri");
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
}

/*
    When the `Config` class is included from a script that needs configuration
    entries, the configuration tree in `Config::$configtree` needs to be
    initialized first. Do this right away after declaring the class.
*/
Config::loadTree();

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
        configuration tree. Please see `/includes/config/tree.php` for more
        specific information regarding how this is defined for each section.
    */
    public function getDescription() {
        return "admin.section.".
               Config::translatePathI18N($this->domain).
               ".".
               Config::translatePathI18N($this->section).
               ".desc";
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
    public function getName() {
        return "admin.domain.".
               Config::translatePathI18N($this->domain).
               ".name";
    }

    /*
        Returns an I18N token representing a sub-title displayed underneath the
        main title on the page.
    */
    public function getDescription() {
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
