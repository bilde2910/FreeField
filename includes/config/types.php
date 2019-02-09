<?php
/*
    This script contains classes to simplify configuration parsing and saving on
    the administration pages. Each individual setting in
    /includes/config/defs.php accepts one specific type of input (e.g. integer
    values, strings, etc.). In order for these data types to be saved to the
    configuration file, the user must be restricted to inputing only values that
    can be correctly parsed to those data types. E.g. for integer inputs, only
    integer values should be accepted, and strings with a regex match
    requirement have to match the regex string to be valid. This should be
    enforced both client-side and server-side.

    To accomplish this, this file contains classes that can be instantiated in
    the configuration tree for each setting. The classes define the HTML control
    that is used to input data, and also offers a function for parsing the value
    server-side and checking its validity. Arguments passed to the constructors
    of the classes can determine e.g. the range of integers accepted, or a regex
    that a string must match. For example, if a setting requires that the user
    input an integer between 10 and 100, the setting's array in
    /includes/config/defs.php should have the following entry:

        "option" => new IntegerOption(10, 100)

    When that option is rendered on the administration pages, it will
    automatically output an `<input type="number" min="10" max="100">` element.

    Option classes have eight different functions:

    parseValue($data)
        This function takes a string input, the data that is submitted by the
        form on the administration pages, and should return a data object or
        array ready to be placed in the configuration file.

    isValid($data)
        This function takes the output of `parseValue($data)` and should return
        `true` or `false` depending on whether the argument data is valid and
        can be placed as-is into the configuration file.

    getControl($current, $attrs)
        This function takes an input value `$current` that represents the
        current, parsed value from the configuration file, as well as a list of
        HTML attributes, and should output an HTML input control of some sort
        where the input field itself has the attributes and the value filled in.
        The output of this function is what is displayed on the administration
        interface next to each setting's name.

    getFollowingBlock()
        Some options require additional space to display the current value of
        the option. Whatever is output here is displayed as a separate block
        underneath the setting name and input control on the administration
        pages. This is used for IconSetOption - whenever an icon set is selected
        by the user, a block should be displayed following the setting itself
        that previews the given icon set.

        The root element output by getFollowingBlock() should have the
        `option-following-block` class. This allows event handler scripts for
        the input fields themselves to reference the following block root
        element by searching for the next instance of it in the DOM:

            var followingBlock
                = $(this).closest(".option-block-follows")
                         .next(".option-following-block");

    encodeSavedValue($value)
        Some options may wish to store its value in the configuration file in
        a different format than the structure of the value as used on the
        administration pages. For example, passwords can be accessed in plain-
        text from the `Config` class, but may be stored encrypted in the
        configuration file on disk. This function encodes the in-memory `$value`
        of the setting to the format it would be stored in on disk. By default,
        this is the same format as the in-memory value, but specific options may
        choose to override this behavior by declaring this function.

    decodeSavedValue($value)
        This is the reverse operation of `encodeSavedValue()`. This function
        takes the value of the setting as it appears on disk and converts it to
        the format used for in-memory representations of its value. For example,
        passwords may be stored encrypted in the configuration file - this
        function would return the decrypted password, given the encrypted
        password from `$value`.

    isAuthorizedToChange($old, $new, $user)
        This function allows settings saving to check whether or not a user is
        authorized to make a specific change to a setting. The function is
        passed the old and new values of the setting, as well as an object
        representing the user that is making the change, and should return
        whether the change of the setting may proceed given these values. This
        is used in `PermissionOption` to ensure that a user cannot change the
        permission level of a setting to or from a value at or higher than their
        own permission level.

    preCommit($rawData, $parsedValue)
        This function is called after validation has passed, but before the
        setting value is committed to the configuration file. This function is
        used in `FileOption` to write a received file to disk, for example,
        after the settings saving script has confirmed that the received data is
        valid through `isValid()`. The purpose of this function is to prevent
        saving data if the calling script has no intention for data to be saved.
        I.e. a script may call both `parseValue()` and `isValid()` for a value,
        but never proceed to actually commit the value to the configuration
        file. If file saving in `FileOption` was done in `parseValue()` or
        `isValid()`, changes would be committed when they never should have
        been. This function can return false to abort the committment of the
        updated setting value to the configuration file. This ensures that an
        updated value is not stored for a setting if, say, writing a file to
        disk failed, and the updated value depends on the file being correctly
        written to disk.

        The function accepts two parameters:

        $rawData
            The raw data sent to `parseValue()` for parsing.

        $parsedData
            The parsed value returned by `parseValue()`.
*/

/*
    The `DefaultOption` is the base class for all of the option classes and
    sets default return values for the option class' functions. `DefaultOption`
    may not be assigned to an option by itself, but should be extended by
    another class to override those functions.
*/
abstract class DefaultOption {
    public function parseValue($data) {
        return $data;
    }

    public function isValid($data) {
        return true;
    }

    public function getFollowingBlock() {
        return "";
    }

    public function preCommit($rawData, $parsedValue) {
        return true;
    }

    public function encodeSavedValue($value) {
        return $value;
    }

    public function decodeSavedValue($value) {
        return $value;
    }

    public function isAuthorizedToChange($old, $new, $user) {
        return true;
    }

    /*
        Takes an array of attributes and converts it to an HTML attribute.
        string. For example:

            constructAttributes(array(
                "name" => "fieldName",
                "id" => "fieldID",
                "class" => "myClass"
            ))

        This returns:

            ' name="fieldName" id="fieldID" class="myClass"'
    */
    protected static function constructAttributes($attrArray) {
        $attrString = "";
        foreach ($attrArray as $attr => $value) {
            if ($value === true) {
                $attrString .= ' '.$attr;
            } else {
                $attrString .= ' '.$attr.'="'.htmlspecialchars($value, ENT_QUOTES).'"';
            }
        }
        return $attrString;
    }
}

/*
    This option is for settings which require a string input. The string may
    optionally match a regex pattern. If a regex pattern is not defined, any
    string will be accepted.
*/
class StringOption extends DefaultOption {
    private $regex;

    public function __construct($regex = null) {
        $this->regex = $regex;
    }

    public function getControl($current = null, $attrs = array()) {
        if ($current !== null) $attrs["value"] = $current;
        if ($this->regex !== null) {
            $attrs["data-validate-as"] = "regex-string";
            $attrs["data-validate-regex"] = $this->regex;
        }
        $attrString = parent::constructAttributes($attrs);
        return '<input type="text"'.$attrString.'>';
    }

    public function parseValue($data) {
        return strval($data);
    }

    public function isValid($data) {
        if (is_array($data)) return false;
        if ($this->regex !== null) {
            if (!preg_match('/'.$this->regex.'/', $data)) return false;
        }
        return true;
    }
}

/*
    This option is for settings which require a longer string input. The string
    may optionally match a text format. Acceptable text formats:

      - "md" = Markdown
*/
class ParagraphOption extends DefaultOption {
    private $format;

    public function __construct($format = null) {
        $this->format = $format;
    }

    public function getControl($current = "", $attrs = array()) {
        if ($this->format === "md") {
            $attrs["data-has-preview-for"] = "md";
        }

        $attrString = parent::constructAttributes($attrs);
        return '<textarea'.$attrString.'>'.$current.'</textarea>';
    }

    public function getFollowingBlock() {
        /*
            If this option takes Markdown input, a live preview will be
            displayed immediately undernath the input box.

            A disclaimer is displayed underneath the preview box to warn users
            that the output that is previewed in the box may vary from the
            actual rendered output when displayed on the page. This is because
            of differences in how the implementations of Showdown and Parsedown
            render Markdown to HTML. Showdown, for example, does not render
            stand-alone URLs as hyperlinks, which Parsedown does.
        */
        if ($this->format === "md") {
            return '<div class="option-following-block para-preview">
                        <div class="para-content markdown-content"></div>
                    </div>
                    <p class="para-disclaimer">
                        '.I18N::resolveHTML("admin.option.paragraph.disclaimer").'
                    </p>';
        }
    }

    public function parseValue($data) {
        return strval($data);
    }

    public function isValid($data) {
        if (is_array($data)) return false;
        return true;
    }
}

/*
    This option is for settings with confidential data. It is displayed as a
    password box. Otherwise, it functions more or less in the same way as
    `StringOption`. It does not accept regex filtering, since there is no good
    way for the user to correct mistakes in the input if they cannot see the
    input string itself (the text is masked).

    The current value of options using `PasswordOption` is never output to the
    page. Instead, a mask is used - by default, `PasswordOption::DEFAULT_MASK` -
    this is done to prevent sensitive information from being leaked out to the
    page, only allowing the user to enter new values without reading the current
    one. Doing so can prevent the sensitive information from being used
    maliciously elsewhere.
*/
class PasswordOption extends DefaultOption {
    /*
        Randomly generated 30-character string used as an "unchanged value"
        mask. Chosen to be extremely unlikely to collide with a real value.
    */
    const DEFAULT_MASK = "oqXb_&WkMrdHtRZ_@}qBM=?WheuO6Y";

    private $mask;

    public function __construct($mask = self::DEFAULT_MASK) {
        $this->mask = $mask;
    }

    public function getControl($current = null, $attrs = array()) {
        if ($current !== null && $current !== "") $attrs["value"] = $this->mask;
        $attrString = parent::constructAttributes($attrs);
        return '<input type="password"'.$attrString.'>';
    }

    public function parseValue($data) {
        return strval($data);
    }

    public function isValid($data) {
        if (is_array($data)) return false;
        /*
            If the received input is the mask itself, then the value is
            unchanged. By considering the mask as an invalid value, the settings
            updater script will skip updating the value of this setting in the
            configuration file, ensuring the setting remains unchanged.
        */
        if ($data == $this->mask) return false;
        return true;
    }

    /*
        Passwords should be stored encrypted on disk. The encryption key is
        stored in the AuthKeys userdata file at /includes/userdata/authkeys.php.
        This is done to ensure that attackers accessing config.json cannot
        extract sensitive data.
    */
    public function encodeSavedValue($data) {
        __require("security");
        return Security::encryptArray(array("password" => $data), "config");
    }
    public function decodeSavedValue($data) {
        __require("security");
        $decr = Security::decryptArray($data, "config", "password");
        if ($decr !== null) return $decr;
        return $data;
    }

    public function getMask() {
        return $this->mask;
    }
}

/*
    This option is for boolean values. It renders as a checkbox with a label
    next to it.
*/
class BooleanOption extends DefaultOption {
    public function getControl($current = null, $attrs = array(), $i18ntoken = null) {
        __require("i18n");

        $id = isset($attrs["id"]) ? $attrs["id"] : null;
        $name = isset($attrs["name"]) ? $attrs["name"] : null;
        $disabled = isset($attrs["disabled"]) && $attrs["disabled"];

        if ($current === true) $attrs["checked"] = true;
        $attrString = parent::constructAttributes($attrs);

        $labelAttrs = array();
        if ($id !== null) $labelAttrs["for"] = $id;
        $labelAttrString = parent::constructAttributes($labelAttrs);

        /*
            Since the checkbox should have a label next to it, the contents of
            this label should be passed to `getControl()`. This is done through
            `$i18ntoken`. An I18N key is passed that through that variable,
            which is then resolved here. If no token is given (`null`), it falls
            back to "setting.<html_name>.label", then "setting.<html_id>.label".
        */
        if ($i18ntoken !== null) {
            $label = I18N::resolveHTML($i18ntoken);
        } elseif ($name !== null) {
            $label = I18N::resolveHTML(
                "setting.".
                str_replace("-", "_", str_replace("/", ".", $name)).
                ".label"
            );
        } elseif ($id !== null) {
            $label = I18N::resolveHTML(
                "setting.".
                str_replace("-", "_", $id).
                ".label"
            );
        } else {
            $label = "";
        }

        /*
            When an HTML form POSTs, checked checkboxes are passed with the
            value "on", while unchecked checkboxes aren't passed at all. Since
            /includes/lib/config.php only saves the value of settings which are
            present in the `$_POST` array, unchecked checkboxes would be treated
            as if they were not present on the page, and would thus be possible
            to check, but not uncheck.

            To work around this, a fallback `<input type="hidden">` with a fixed
            value of "off" is output before the checkbox itself. If the checkbox
            is checked, the checkbox will override the hidden input (since it
            comes after the hidden input on the page), while if the checkbox is
            not checked, the hidden input box will take precedence. This ensures
            that a value for the checkbox is always passed to the configuration
            script on the server, regardless of whether or not the checkbox is
            checked or not. The two inputs must have the same name to enforce
            this behavior.
        */
        $fallbackAttrs = array();
        if ($name !== null) $fallbackAttrs["name"] = $name;
        if ($disabled) $fallbackAttrs["disabled"] = true;
        $fallbackAttrString = parent::constructAttributes($fallbackAttrs);

        $html = '<input type="hidden" value="off"'.$fallbackAttrString.'>';
        $html .= '<label'.$labelAttrString.'><input type="checkbox"'.$attrString.'> '.$label.'</label>';
        return $html;
    }

    public function parseValue($data) {
        if ($data == "on") return true;
        if ($data == "off") return false;
        return boolval($data);
    }

    public function isValid($data) {
        if (is_bool($data)) return true;
        return false;
    }
}

/*
    This option is for integer values. It renders as a numerical input box. A
    minimum and/or maximum value may be specified to restrict the values
    accepted by the input.
*/
class IntegerOption extends DefaultOption {
    private $min;
    private $max;

    public function __construct($min = null, $max = null) {
        $this->min = $min;
        $this->max = $max;
    }

    public function getControl($current = null, $attrs = array()) {
        if ($this->min !== null) $attrs["min"] = $this->min;
        if ($this->max !== null) $attrs["max"] = $this->max;
        if ($current !== null) $attrs["value"] = $current;

        $attrString = parent::constructAttributes($attrs);
        return '<input type="number"'.$attrString.'>';
    }

    public function parseValue($data) {
        return intval($data);
    }

    public function isValid($data) {
        if (!is_int($data)) return false;
        if ($this->min !== null && $data < $this->min) return false;
        if ($this->max !== null && $data > $this->max) return false;
        return true;
    }
}

/*
    This option is for decimal values. It is equal to `IntegerOption`, except
    that it takes a `$decimals` input that determines the number of decimals
    displayed on the value in the input box, and that it returns a `float` value
    rather than an `int` value.
*/
class FloatOption extends DefaultOption {
    private $min;
    private $max;

    public function __construct($min = null, $max = null) {
        $this->min = $min;
        $this->max = $max;
    }

    public function getControl($current = null, $attrs = array(), $decimals = 5) {
        if ($this->min !== null) $attrs["min"] = $this->min;
        if ($this->max !== null) $attrs["max"] = $this->max;
        if ($current !== null) $attrs["value"] = $current;
        if ($decimals >= 1) $attrs["step"] = "0.".str_repeat("0", $decimals - 1)."1";

        $attrString = parent::constructAttributes($attrs);
        return '<input type="number"'.$attrString.'>';
    }

    public function parseValue($data) {
        return floatval($data);
    }

    public function isValid($data) {
        if (!is_float($data)) return false;
        if ($this->min !== null && $data < $this->min) return false;
        if ($this->max !== null && $data > $this->max) return false;
        return true;
    }
}

/*
    This option is for inputs which require a geofence. Geofences are selected
    from a list of labeled geofences stored in the configuration file.
*/
class GeofenceOption extends DefaultOption {
    public function getControl($current = null, $attrs = array()) {
        __require("geo");
        $fences = Geo::listGeofences();

        $attrString = parent::constructAttributes($attrs);

        /*
            Create a <select> input with an <optgroup> under which geofences
            should be listed.
        */
        $html = '<select'.$attrString.'>
                    <option value="">
                        '.I18N::resolveHTML("ui.dropdown.none_selected").'
                    </option>
                    <optgroup label="'.I18N::resolveHTML("admin.option.geofence.available").'">';

        /*
            Add each geofence to the selection box.
        */
        foreach ($fences as $fence) {
            $html .= '<option value="'.$fence->getID().'"';
            if ($current !== null && $fence->getID() == $current->getID()) {
                $selected = true;
                $html .= ' selected';
            }
            $html .= '>'.$fence->getLabel().'</option>';
        }
        $html .= '</optgroup></select>';
        return $html;
    }

    public function parseValue($data) {
        __require("geo");
        if ($data === "") return null;

        return Geo::getGeofence($data);
    }

    public function isValid($data) {
        __require("geo");

        /*
            Null indicates that the geofence is disabled, and is thus valid.
        */
        if ($data === null) return true;
        if ($data instanceof Geofence) return true;

        return false;
    }

    /*
        `GeofenceOption` should return `Geofence` instances when
        `Config::get()->value()` is called, and should be stored as a string
        indicating the ID of the geofence, or null if none is selected.
    */
    public function encodeSavedValue($value) {
        if ($value === null) return null;
        return $value->getID();
    }
    public function decodeSavedValue($value) {
        __require("geo");
        return Geo::getGeofence($value);
    }
}

/*
    This option is used when the user should be prompted to select one of
    several pre-defined options. It outputs a <select> input with the available
    options taken from `$items` in the constructor of this option.

    The constructor allows specifying the data type of the options passed. The
    default is "string", but any of the following may be used:

      - "string": A string value
      - "int": An integer value

    The data type selected will be used to store the value in the configuration
    file.

    If `$ignoreI18N` is set to `true`, this option will make no attempt to
    internationalize the `SelectOption`s values.
*/
class SelectOption extends DefaultOption {
    private $items;
    private $type;
    private $ignoreI18N;

    public function __construct($items, $type = "string", $ignoreI18N = false) {
        $this->items = $items;
        $this->type = $type;
        $this->ignoreI18N = $ignoreI18N;
    }

    public function getControl($current = null, $attrs = array(), $i18ndomain = null) {
        $attrString = parent::constructAttributes($attrs);

        $html = '<select'.$attrString.'>';
        $selected = false;
        foreach ($this->items as $item) {
            $html .= '<option value="'.$item.'"';
            if ($item == $current) {
                $selected = true;
                $html .= ' selected';
            }

            /*
                Each of the options should have a label indicating its value.
            */
            $label = $this->getLabelI18N($item, $attrs, $i18ndomain);
            $html .= '>'.$label.'</option>';
        }
        $html .= '</select>';

        return $html;
    }

    public function parseValue($data) {
        switch ($this->type) {
            case "string":
                return strval($data);
            case "int":
                return intval($data);
        }
    }

    public function isValid($data) {
        if (is_array($data)) return false;
        return in_array($data, $this->items);
    }

    /*
        Returns a list of all items in the selection box.
    */
    public function getItems() {
        return $this->items;
    }

    /*
        Returns an I18N token representing the label for the given `$item` of
        the selection box.
    */
    public function getLabelI18N($item, $attrs, $i18ndomain = null) {
        __require("i18n");
        $id = isset($attrs["id"]) ? $attrs["id"] : null;
        $name = isset($attrs["name"]) ? $attrs["name"] : null;

        /*
            Each of the options should have a label indicating its value.
            This is taken from an I18N domain passed in `$i18ndomain`, where
            the label for each item is taken from the `$item` subkey from
            the domain. If `$i18ndomain` is not available, it falls back to
            "setting.<html_name>.option.<item_value>", then
            "setting.<html_id>.option.<item_value>", then the raw string
            value of the item itself if neither the domain, HTML ID or name
            is set.
        */
        if ($this->ignoreI18N) {
            return $item;
        } elseif ($i18ndomain !== null) {
            return I18N::resolveHTML(
                "{$i18ndomain}.".str_replace(",", "_", str_replace("-", "_", $item))
            );
        } elseif ($name !== null) {
            return I18N::resolveHTML(
                "setting.".
                str_replace(",", "_", str_replace("-", "_", str_replace("/", ".", $name))).
                ".option.".
                str_replace(",", "_", str_replace("-", "_", $item))
            );
        } elseif ($id !== null) {
            return I18N::resolveHTML(
                "setting.".
                str_replace("-", "_", $id).
                ".option.".
                str_replace(",", "_", str_replace("-", "_", $item))
            );
        } else {
            return $item;
        }
    }
}

/*
    This option is used when a permission level should be used to set a setting.
    Permission values are internally stored as an integer, corresponding to the
    permission level of the group selected by the user. Using the permission
    level rather than the ID of the group ensures that the permission level is
    maintained even if the corresponding group is deleted. The caveat is that
    every time the permission level of a group changes, all of the settings that
    use `PermissionOption` and are set to the permission level of that group
    have to change as well to reflect the updated permission level.
*/
class PermissionOption extends DefaultOption {
    /*
        Constants representing the default permission levels.
    */
    const LEVEL_HOST = 250;
    const LEVEL_ADMIN = 200;
    const LEVEL_MODERATOR = 160;
    const LEVEL_SUBMITTER = 120;
    const LEVEL_REGISTERED = 80;
    const LEVEL_READ_ONLY = 40;
    const LEVEL_ANONYMOUS = 0;

    public function getControl($current = 0, $attrs = array()) {
        /*
            The permission level selector is actually defined in
            /includes/lib/auth.php instead. Get the selector from there.
        */
        __require("auth");
        $id = isset($attrs["id"]) ? $attrs["id"] : null;
        $name = isset($attrs["name"]) ? $attrs["name"] : null;
        return Auth::getPermissionSelector($name, $id, $current);
    }

    public function parseValue($data) {
        return intval($data);
    }

    public function isValid($data) {
        if (!is_int($data)) return false;

        /*
            The valid range of permission levels is 0-250.
        */
        if ($data > 250 || $data < 0) return false;

        /*
            If the current user tries to select a group at or above their own
            rank, the request should be denied. This is to stop one user from
            preventing other users of their own or a higher rank from making
            changes to the setting.
        */
        __require("auth");
        if (!Auth::getCurrentUser()->canChangeAtPermission($data)) return false;

        return true;
    }

    public function isAuthorizedToChange($old, $new, $user) {
        /*
            Ensure that users cannot change the value of a permission setting to
            or from a permission level at or higher than their own level.
        */
        $max = max($old, $new);
        return $user->canChangeAtPermission($max);
    }
}

/*
    This option is used when a setting requires choosing an icon set of any
    type. Icon sets are collections of map markers, species icons etc. that are
    used on the FreeField map. This renders as a selection box with an optional
    default field. If a non-default option is selected, a preview of all of the
    icons in the selected icon set should be displayed directly underneath the
    setting on the page.

    This is extended by classes that specify the path ID of an icon set type.
    See e.g. `IconSetOption` or `SpeciesSetOption` classes below for examples.
*/
abstract class IconSetOptionBase extends DefaultOption {
    /*
        The `$packs` array is a list of available icon sets. This is declared
        `static` to prevent it from having to be populated once for every
        instance of `IconSetOption` on the page. The list is populated the first
        time this class is constructed, and reused for subsequent instances.
    */
    private static $packs = array();

    /*
        This class is a base class for various sub-types of icon sets. The
        `$type` of an icon set is the directory under which icon sets can be
        found in the /themes directory. For map marker icons, this is "icons,"
        for species markers, this is "species."
    */
    private $type;

    /*
        If a default (fallback) option is present, `$includeDefault` is set to
        the I18N token representing the default option label.
    */
    private $includeDefault;

    public function __construct($type, $includeDefault = null) {
        $this->type = $type;
        $this->includeDefault = $includeDefault;
        if (!isset(self::$packs[$type])) {
            self::getIconSetDefinitionsOfType($type);
        }
    }

    public function getControl($current = null, $attrs = array()) {
        __require("i18n");

        /*
            Add the type of icon set to the selection box for use with proper
            parsing in /js/option.js.
        */
        $attrs["data-icon-set-type"] = $this->type;

        /*
            Add the `icon-set-option-input` class to the control to enable it to
            have an event handler bound by /js/option.js.
        */
        if (isset($attrs["class"])) {
            $attrs["class"] .= " icon-set-option-input";
        } else {
            $attrs["class"] = "icon-set-option-input";
        }

        $attrString = parent::constructAttributes($attrs);

        $html = '<select'.$attrString.'>';
        if ($this->includeDefault !== null) {
            $html .= '<option value="">'.
                     I18N::resolveHTML($this->includeDefault).
                     '</option>';
        }

        foreach (self::$packs[$this->type] as $pack => $data) {
            /*
                Each option should use the name of the icon set and its author
                as its label in the selection box.
            */
            $html .= '<option value="'.$pack.'"';
            if ($pack == $current) $html .= ' selected';
            $html .= '>'.I18N::resolveArgsHTML(
                "theme.name_label",
                true,
                $data["name"],
                $data["author"]
            ).'</option>';
        }
        $html .= '</select>';
        return $html;
    }

    /*
        This function is called by the administration pages when an
        `IconSetOptionBase` child has been added to the page. The output of the
        function is rendered as a block underneath the setting line. It contains
        a preview of all of the icons in the icon set.
    */
    public function getFollowingBlock() {
        return '<div class="option-following-block"></div>';
    }

    /*
        Returns an array of installed icon sets of the given type, together with
        their pack.ini definitions.
    */
    protected static function getIconSetDefinitionsOfType($type) {
        /*
            Populate the list of installed icon sets. This function reads the
            content of all available pack.ini files (one for each icon set) and
            stores them in memory for use when displaying the control later. The
            ID of each icon set is the name of the directory in which the icon
            set resides.
        */
        if (!isset(self::$packs[$type])) {
            self::$packs[$type] = array();
            $themepath = __DIR__."/../../themes/".$type;
            $themes = array_diff(scandir($themepath), array('..', '.'));
            foreach ($themes as $theme) {
                if (!file_exists("{$themepath}/{$theme}/pack.ini")) continue;
                $data = parse_ini_file("{$themepath}/{$theme}/pack.ini", true);
                self::$packs[$type][$theme] = $data;
            }
        }
        return self::$packs[$type];
    }

    /*
        Returns an array of installed icon sets of the type specified by the
        extending class of `IconSetOptionBase`.
    */
    public abstract function getIconSetDefinitions();

    public function parseValue($data) {
        return strval($data);
    }

    public function isValid($data) {
        if (is_array($data)) return false;
        return isset(self::$packs[$this->type][$data]);
    }
}

/*
    This option is used when a setting requires choosing a map marker icon set.
    These are collections of map markers that represent each type of field
    research objective and reward. This renders as a selection box with an
    optional default field. If a non-default option is selected, a preview of
    all of the icons in the selected icon set should be displayed directly
    underneath the setting on the page.
*/
class IconSetOption extends IconSetOptionBase {
    const TYPE = "icons";

    public function __construct($includeDefault = null) {
        parent::__construct(self::TYPE, $includeDefault);
    }

    public function getIconSetDefinitions() {
        return parent::getIconSetDefinitionsOfType(self::TYPE);
    }
}

/*
    This option is used when a setting requires choosing a species icon set.
    These are collections of map markers that represent each type of species in
    the game. This renders as a selection box with an optional default field. If
    a non-default option is selected, a preview of all of the icons in the
    selected icon set should be displayed directly underneath the setting on the
    page.
*/
class SpeciesSetOption extends IconSetOptionBase {
    const TYPE = "species";

    public function __construct($includeDefault = null) {
        parent::__construct(self::TYPE, $includeDefault);
    }

    public function getIconSetDefinitions() {
        return parent::getIconSetDefinitionsOfType(self::TYPE);
    }
}

/*
    This option is for file uploads. It renders as a file selection input. The
    option requires passing the path of the implementing setting, as the path is
    used to determine the storage location of the uploaded file.

    `FileOption` allows restricting the file types that can be uploaded through
    the `$accept` array, as well as restricting the file size through
    `$maxLength`. `$maxLength` is the maximum number of bytes allowed to be
    uploaded, while `$accept` is an associative array between mime types and
    their corresponding file extensions. Example:

        $accept = array(
            "image/png" => "png",
            "image/gif" => "gif",
            "image/jpeg" => "jpg"
        );

    Uploaded files are stored with the file extension defined for the MIME type
    of the file in the `$accept` array. The name of the file as provided by the
    user is not trusted for local file system storage, but is stored in the
    configuration file for lookup when downloaded by a user.
*/
class FileOption extends DefaultOption {
    /*
        A path for previewing an uploaded file. The provided `$path` is appended
        to this path string.
    */
    const FILE_PREVIEW_PATH = "/admin/view-file.php?path=";
    /*
        The storage location on the local server file system for uploaded files.
    */
    const UPLOAD_DIRECTORY = __DIR__."/../userdata/files";
    /*
        The path of the setting this `FileOption` instance is assiged to. Used
        for generating the filename of the uploaded file, and for generating the
        correct file preview URL based on `FILE_PREVIEW_PATH`.
    */
    private $path;
    /*
        An array of accepted file MIME types and their corresponding file
        extensions.
    */
    private $accept;
    /*
        The maximum file size of the uploaded file in bytes.
    */
    private $maxLength;

    public function __construct($path, $accept = null, $maxLength = -1) {
        $this->path = $path;
        $this->accept = $accept;
        $this->maxLength = $maxLength;
    }

    public function getControl($current = null, $attrs = array()) {
        if ($this->accept !== null) $attrs["accept"] = implode(",", array_keys($this->accept));

        /*
            The name of the currently uploaded file, along with its size and a
            link to preview it, is included underneath the file selection input
            on the settings page.
        */
        $previewUrl = Config::getEndpointUri(self::FILE_PREVIEW_PATH.urlencode($this->path));

        /*
            Convert the file size in bytes to a human-readable string (e.g. if
            `$size = 262144`, the displayed size should be "256 KiB").
        */
        $size = $current->getLength();
        $prefixes = array("byte", "kilo", "mega", "giga");
        $prefixIdx = 0;
        /*
            For each order of magnitude of file size, cut the file size by 1024
            and use the next size prefix (e.g. bytes -> kibibytes). Ensure that
            the unit prefix index `$prefixIdx` never exceeds the length of the
            `$prefixes` array, and use the highest prefix in the `$prefixes`
            with a higher than 1024 value of `$size` in those cases. We could
            easily implement "tera" rather than "giga" as the highest prefix in
            `$prefixes`, but it's intentionally not included because there is no
            reasonable reason that files greater than 1 TiB would ever be
            uploaded to a website whose purpose isn't even to distribute files.
        */
        while (round($size) >= 1024 && $prefixIdx < count($prefixes) - 1) {
            $size /= 1024;
            $prefixIdx++;
        }

        $attrString = parent::constructAttributes($attrs);
        return '<input type="file"'.$attrString.'><br />'.
            I18N::resolveArgsHTML(
                "admin.option.file.current",
                false,
                "<code>",
                I18N::resolveArgsHTML(
                    "admin.option.file.display_format",
                    false,
                    '<a href="'.$previewUrl.'" target="_blank">',
                    '</a>',
                    htmlspecialchars($current->getUploadName(), ENT_QUOTES),
                    number_format($size, 2),
                    I18N::resolveHTML(
                        "admin.option.file.size_unit.".$prefixes[$prefixIdx]
                    )
                ),
                "</code>"
            );
    }

    public function parseValue($data) {
        if (!isset($_FILES[$data])) return null;
        /*
            `$data` is the key of the uploaded file array in `$_FILES`. Fetch
            the file to do further processing.
        */
        $fdata = $_FILES[$data];
        if ($fdata["error"] !== UPLOAD_ERR_OK) return null;
        $file = array(
            /*
                Don't trust the MIME type provided by `$fdata["type"]`, as this
                is set by the browser and is thus not trustworthy. Determine the
                MIME type server-side instead.
            */
            "type" => mime_content_type($fdata["tmp_name"]),
            /*
                The name of the file as provided by the browser is not trusted
                for local filesystem storage, but is kept in the configuration
                file for retrieval later.
            */
            "name" => $fdata["name"],
            /*
                File size, last modification time and SHA-256 checksum can be
                fetched using `filesize()`, `filemtime()` and `hash_file()`, but
                we'll store those in the configuration file as well to reduce
                the number of filesystem calls when the file is fetched.
            */
            "size" => $fdata["size"],
            "time" => time(),
            "sha256" => hash_file("sha256", $fdata["tmp_name"])
        );
        return $this->applyTo($file);
    }

    public function isValid($data) {
        if ($data === null) return false;
        if (!($data instanceof FileOptionValue)) return false;
        /*
            Require presence of MIME type and filesize for input validation,
            SHA-256 checksum and filename for later retrieval upon download.
        */
        if ($data->getMimeType() === null) return false;
        if ($data->getUploadName() === null) return false;
        if ($data->getLength() === null) return false;
        if ($data->getHexEncodedSHA256() === null) return false;
        /*
            Ensure that the file is of an accepted type, and that it is within
            the maximum allowed file size.
        */
        if (!isset($this->accept[$data->getMimeType()])) return false;
        if ($this->maxLength >= 0 && $data->getLength() > $this->maxLength) return false;

        return true;
    }

    public function preCommit($rawData, $parsedValue) {
        /*
            `$_FILES[$rawData]` not being set shouldn't hapen, but if it does,
            don't proceed.
        */
        if (!isset($_FILES[$rawData])) return false;
        $fdata = $_FILES[$rawData];

        /*
            Get a file helper object for the parsed value to simplify getting
            the filename and uploaded file path for saving the uploaded file.
        */
        $basename = $parsedValue->getFilename();
        $targetFile = $parsedValue->getPath();

        /*
            Attempt to save the uploaded file to the target file location.
        */
        $success = move_uploaded_file($fdata["tmp_name"], $targetFile);
        if (!$success) return false;

        /*
            Delete other uploaded files with different file extensions that
            correspond to the current setting.
        */
        $files = array_diff(scandir(self::UPLOAD_DIRECTORY), array('..', '.'));
        /*
            Get the index of the file extension in the filename string.
        */
        $baseExtIndex = strrpos($basename, ".");
        foreach ($files as $file) {
            $fileExtIndex = strrpos($file, ".");
            /*
                If the file has no extension, or it is a directory, skip it.
            */
            if (
                $fileExtIndex === false ||
                is_dir(self::UPLOAD_DIRECTORY."/{$file}")
            ) continue;
            /*
                If the file has the same base name, but different file names,
                the file is a file that is stored for the current setting but
                with a different file extension, and should thus be deleted so
                only the current version of the file remains.
            */
            if (
                substr($file, 0, $fileExtIndex + 1) === substr($basename, 0, $baseExtIndex + 1) &&
                $file !== $basename
            ) {
                unlink(self::UPLOAD_DIRECTORY."/{$file}");
            }
        }

        return true;
    }

    /*
        `FileOption` should return a `FileOptionValue` when
        `Config::get()->value()` is called for a setting which uses this option
        type. On disk, the file data is stored as an array with the MIME type,
        uploaded filename, file size and last modification time of the stored
        file.
    */
    public function encodeSavedValue($value) {
        return array(
            "type"   => $value->getMimeType(),
            "name"   => $value->getUploadName(),
            "size"   => $value->getLength(),
            "time"   => $value->getUploadTime(),
            "sha256" => $value->getHexEncodedSHA256()
        );
    }
    public function decodeSavedValue($value) {
        return $this->applyTo($value);
    }

    /*
        Gets a file helper instance for the given setting value.
    */
    public function applyTo($value) {
        return new FileOptionValue($value, self::UPLOAD_DIRECTORY, $this->path, $this->accept);
    }
}

/*
    This class is a helper object for `FileOption`. It is constructed by passing
    the value of a `FileOption` setting to `FileOption::applyTo()`. The purpose
    of the class is to make it easier to extract information such as file names,
    paths, extensions, sizes and types for a given `FileOption`s value.
*/
class FileOptionValue {
    /*
        The value of a setting that uses `FileOption`.
    */
    private $value;
    /*
        The directory where uploaded files are stored.
    */
    private $directory;
    /*
        The setting path (not file path) of the given setting in the
        configuration file.
    */
    private $path;
    /*
        An associative array of accepted file MIME types and their corresponding
        file extensions.
    */
    private $accept;

    public function __construct($value, $directory, $path, $accept) {
        $this->value = $value;
        $this->directory = $directory;
        $this->path = $path;
        $this->accept = $accept;
    }

    /*
        Retrieves the file extension of the file.
    */
    public function getExtension() {
        return $this->accept[$this->value["type"]];
    }

    /*
        Retrieves the name of the file as stored on the server's file system.
    */
    public function getFilename() {
        return str_replace("/", ".", $this->path).".".$this->getExtension();
    }

    /*
        Retrieves the name of the file as provided by the browser that was used
        to upload the file.
    */
    public function getUploadName() {
        if (!isset($this->value["name"])) return null;
        return $this->value["name"];
    }

    /*
        Retrieves the full path to the location of the uploaded file on the
        server's file system.
    */
    public function getPath() {
        return $this->directory."/".$this->getFilename();
    }

    /*
        Retrieves the MIME type of the file.
    */
    public function getMimeType() {
        if (!isset($this->value["type"])) return null;
        return $this->value["type"];
    }

    /*
        Retrieves the size of the file.
    */
    public function getLength() {
        if (!isset($this->value["size"])) return null;
        return $this->value["size"];
    }

    /*
        Retrieves a timestamp indicating the upload time of the file.
    */
    public function getUploadTime() {
        if (isset($this->value["time"])) {
            return $this->value["time"];
        } else {
            return filemtime($this->getPath());
        }
    }

    /*
        Retrieves a SHA-256 hash of the file as a hexadecimal string.
    */
    public function getHexEncodedSHA256() {
        if (isset($this->value["sha256"])) {
            return $this->value["sha256"];
        } else {
            return hash_file("sha256", $this->getPath());
        }
    }

    /*
        Retrieves a SHA-256 hash of the file as a base64-encoded string.
    */
    public function getBase64EncodedSHA256() {
        $sha256hex = $this->getHexEncodedSHA256();
        return base64_encode(hex2bin($sha256hex));
    }

    /*
        Sets the correct HTTP headers for the file and reads the file.
    */
    public function outputWithCaching() {
        /*
            Handle caching by `If-Modified-Since`.
        */
        $lastMod = $this->getUploadTime();
        if (isset($_SERVER["HTTP_IF_MODIFIED_SINCE"])) {
            $time = strtotime($_SERVER["HTTP_IF_MODIFIED_SINCE"]);
            if ($time >= $lastMod) {
                header("HTTP/1.1 304 Not Modified");
                exit;
            }
        }

        /*
            Output the requested file.
        */
        header("Content-Type: ".$this->getMimeType());
        header("Content-Length: ".$this->getLength());
        header("Digest: SHA-256=".$this->getBase64EncodedSHA256()); // RFC 5843
        header("Content-Disposition: inline; filename=\"".$this->getUploadName()."\"");
        header("Last-Modified: ".date("r", $lastMod));

        readfile($this->getPath());
        exit;
    }

    /*
        Returns the contents of the file as a base64-encoded data URI.
    */
    public function getDataURI() {
        return "data:".$this->getMIMEType().";base64,".
            base64_encode(file_get_contents($this->getPath()));
    }
}

/*
    This option is for settings which require an RGB color code. The stored
    value for this setting is of the format "#rrggbb".
*/
class ColorOption extends DefaultOption {
    public function getControl($current = "#000000", $attrs = array()) {
        $id = isset($attrs["id"]) ? $attrs["id"] : null;

        $attrs["value"] = $current;
        $attrString = parent::constructAttributes($attrs);

        /*
            The color selector should have a string next to it indicating the
            selected color.
        */
        list($r, $g, $b) = sscanf($current, "#%02x%02x%02x");
        $previewSpan = "<span>r={$r}, g={$g}, b={$b}</span>";

        return '<input class="color-option-input" type="color"'.$attrString.'> '.
            $previewSpan;
    }

    public function parseValue($data) {
        return strtolower(strval($data));
    }

    public function isValid($data) {
        if (is_array($data)) return false;
        /*
            Ensure that the value is a valid hexadecimal RGB color code.
        */
        if (!preg_match('/^#[0-9a-f]{6}$/', $data)) return false;
        return true;
    }
}

?>
