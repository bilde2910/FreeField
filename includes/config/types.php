<?php
/*
    This script contains classes to simplify configuration parsing and saving on
    the administration pages. Each individual setting in
    /includes/config/tree.php accepts one specific type of input (e.g. integer
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
    /includes/config/tree.php should have the following entry:

        "option" => new IntegerOption(10, 100)

    When that option is rendered on the administration pages, it will
    automatically output an `<input type="number" min="10" max="100">` element.

    Option classes have five different functions:

    parseValue($data)
        This function takes a string input, the data that is submitted by the
        form on the administration pages, and should return a data object or
        array ready to be placed in the configuration file.

    isValid($data)
        This function takes the output of `parseValue($data)` and should return
        `true` or `false` depending on whether the argument data is valid and
        can be placed as-is into the configuration file.

    getControl($current, $name, $id, [$attrs..])
        This function takes an input value `$current` that represents the
        current, parsed value from the configuration file, as well as an HTML
        ID and field name, and should output an HTML input control of some sort
        where the input field itself has the given ID and name and the value
        filled in. The output of this function is what is displayed on the
        administration interface next to each setting's name.

    getFollowingBlock()
        Some options require additional space to display the current value of
        the option. Whatever is output here is displayed as a separate block
        underneath the setting name and input control on the administration
        pages. This is used for IconPackOption - whenever an icon pack is
        selected by the user, a block should be displayed following the setting
        itself that previews the given icon pack.
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

    public function getControl($current = null, $name = null, $id = null) {
        $attrs = "";
        if ($name !== null) {
            $attrs .= ' name="'.$name.'"';
        }
        if ($id !== null) {
            $attrs .= ' id="'.$id.'"';
        }
        if ($current !== null) {
            $attrs .= ' value="'.htmlspecialchars($current, ENT_QUOTES).'"';
        }
        if ($this->regex !== null) {
            $attrs .= ' data-validate-as="regex-string" data-validate-regex="'.$this->regex.'"';
        }
        return '<input type="text"'.$attrs.'>';
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
    This option is for settings with confidential data. It is displayed as a
    password box. Otherwise, it functions more or less in the same way as
    `StringOption`. It does not accept regex filtering, since there is no good
    way for the user to correct mistakes in the input if they cannot see the
    input string itself (the text is masked).
*/
class PasswordOption extends DefaultOption {
    public function getControl($current = null, $name = null, $id = null) {
        $attrs = "";
        if ($name !== null) {
            $attrs .= ' name="'.$name.'"';
        }
        if ($id !== null) {
            $attrs .= ' id="'.$id.'"';
        }
        if ($current !== null) {
            $attrs .= ' value="'.htmlspecialchars($current, ENT_QUOTES).'"';
        }
        return '<input type="password"'.$attrs.'>';
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
    This option is for boolean values. It renders as a checkbox with a label
    next to it.
*/
class BooleanOption extends DefaultOption {
    public function getControl($current = null, $name = null, $id = null, $i18ntoken = null) {
        __require("i18n");

        $attrs = "";
        if ($name !== null) {
            $attrs .= ' name="'.$name.'"';
        }
        if ($id !== null) {
            $attrs .= ' id="'.$id.'"';
        }
        if ($current === true) {
            $attrs .= ' checked';
        }

        $labelAttrs = "";
        if ($id !== null) {
            $labelAttrs .= ' for="'.$id.'"';
        }

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
            $label = $item;
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
        $fallbackAttrs = "";
        if ($name !== null) {
            $fallbackAttrs .= ' name="'.$name.'"';
        }

        $html = '<input type="hidden" value="off"'.$fallbackAttrs.'>';
        $html .= '<label'.$labelAttrs.'><input type="checkbox"'.$attrs.'> '.$label.'</label>';
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

    public function getControl($current = null, $name = null, $id = null) {
        $attrs = "";
        if ($name !== null) {
            $attrs .= ' name="'.$name.'"';
        }
        if ($id !== null) {
            $attrs .= ' id="'.$id.'"';
        }
        if ($this->min !== null) {
            $attrs .= ' min="'.$this->min.'"';
        }
        if ($this->max !== null) {
            $attrs .= ' max="'.$this->max.'"';
        }
        if ($current !== null) {
            $attrs .= ' value="'.htmlspecialchars($current, ENT_QUOTES).'"';
        }
        return '<input type="number"'.$attrs.'>';
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

    public function getControl($current = null, $name = null, $id = null, $decimals = 5) {
        $attrs = "";
        if ($name !== null) {
            $attrs .= ' name="'.$name.'"';
        }
        if ($id !== null) {
            $attrs .= ' id="'.$id.'"';
        }
        if ($this->min !== null) {
            $attrs .= ' min="'.$this->min.'"';
        }
        if ($this->max !== null) {
            $attrs .= ' max="'.$this->max.'"';
        }
        if ($current !== null) {
            $attrs .= ' value="'.htmlspecialchars($current, ENT_QUOTES).'"';
        }
        if ($decimals >= 1) {
            $attrs .= ' step="0.'.str_repeat("0", $decimals - 1).'1"';
        }
        return '<input type="number"'.$attrs.'>';
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
    This option is for inputs which require a geofence. A geofence is created by
    listing at least three coordinate pairs, one per line, where each line is in
    the format `LAT,LNG`. Empty lines are permitted in the input, but discarded.
*/
class GeofenceOption extends DefaultOption {
    public function getControl($current = null, $name = null, $id = null) {
        $attrs = "";
        if ($name !== null) {
            $attrs .= ' name="'.$name.'"';
        }
        if ($id !== null) {
            $attrs .= ' id="'.$id.'"';
        }

        $value = "";
        if ($current !== null) {
            foreach ($current as $point) {
                /*
                    $point is an array where:
                        0 => Latitude
                        1 => Longitude
                */
                $value .= $point[0] . "," . $point[1] . "\n";
            }
        }

        $value = trim($value);
        return '<textarea data-validate-as="geofence"'.$attrs.'>' . $value . '</textarea>';
    }

    public function parseValue($data) {
        if ($data === "") return null;
        $points = array();

        /*
            Split the input data into lines. Split by any combination of line
            feed and carriage return.
        */
        $lines = preg_split('/\r\n?|\n\r?/', $data);
        foreach ($lines as $line) {
            if ($line === "") continue;

            /*
                Convert all coordinates (comma-delimited) to floating point
                values. Validation of the resulting array is performed in
                `isValid()`.
            */
            $point = explode(",", $line);
            $entry = array();
            foreach ($point as $dimension) {
                $entry[] = floatval($dimension);
            }
            $points[] = $entry;
        }
        return count($points) > 0 ? $points : null;
    }

    public function isValid($data) {
        /*
            Null indicates that the geofence is disabled, and is thus valid.
        */
        if ($data === null) return true;

        if (!is_array($data)) return false;

        /*
            A geofence must consist of at least three corners for them to form a
            two-dimensional surface area. Three points creates a triangle. Two
            would only be sufficient to draw a line.
        */
        if (count($data) < 3) return false;

        foreach ($data as $point) {
            /*
                Each point must consist of a coordinate pair. If the pair is not
                an array, or it does not have exactly two coordinate axes, it is
                not a valid coordinate pair, and is thus invalid.
            */
            if (!is_array($point) || count($point) !== 2) return false;
            $lat = $point[0];
            $lon = $point[1];

            /*
                The coordinates must also be valid floating point values and be
                within the range of valid coordinates (-90 to 90 degrees
                latitude, and -180 to 180 degrees longitude).
            */
            if (!is_float($lat)) return false;
            if (!is_float($lon)) return false;
            if ($lat < -90 || $lat > 90) return false;
            if ($lon < -180 || $lon > 180) return false;
        }
        return true;
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
*/
class SelectOption extends DefaultOption {
    private $items;
    private $type;

    public function __construct($items, $type = "string") {
        $this->items = $items;
        $this->type = $type;
    }

    public function getControl($current = null, $name = null, $id = null, $i18ndomain = null) {
        __require("i18n");

        $attrs = "";
        if ($name !== null) {
            $attrs .= ' name="'.$name.'"';
        }
        if ($id !== null) {
            $attrs .= ' id="'.$id.'"';
        }

        $html = '<select'.$attrs.'>';
        $selected = false;
        foreach ($this->items as $item) {
            $html .= '<option value="'.$item.'"';
            if ($item == $current) {
                $selected = true;
                $html .= ' selected';
            }

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
            if ($i18ndomain !== null) {
                $label = I18N::resolveHTML("{$i18ndomain}.{$item}");
            } elseif ($name !== null) {
                $label = I18N::resolveHTML(
                    "setting.".
                    str_replace("-", "_", str_replace("/", ".", $name)).
                    ".option.{$item}"
                );
            } elseif ($id !== null) {
                $label = I18N::resolveHTML(
                    "setting.".
                    str_replace("-", "_", $id).
                    ".option.{$item}"
                );
            } else {
                $label = $item;
            }
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
        return in_array($data, $this->items);
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
    public function getControl($current = 0, $name = null, $id = null) {
        /*
            The permission level selector is actually defined in
            /includes/lib/auth.php instead. Get the selector from there.
        */
        __require("auth");
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
}

/*
    This option is used when a setting requires choosing an icon pack. Icon
    packs are collections of map markers that represent each type of field
    research objective and reward. This renders as a selection box with an
    optional default field. If a non-default option is selected, a preview of
    all of the icons in the selected icon pack should be displayed directly
    underneath the setting on the page.
*/
class IconPackOption extends DefaultOption {
    /*
        The `$packs` array is a list of available icon packs. This is declared
        `static` to prevent it from having to be populated once for every
        instance of `IconPackOption` on the page. The list is populated the
        first time this class is constructed, and reused for subsequent
        instances.
    */
    private static $packs = null;

    /*
        This variable is used to check if this instance of `IconPackOption` is
        the first one output on a page. This is because the script responsible
        for enabling `IconPackOption` functionality (the `viewTheme()` function)
        should only be present once on a page and then reused for subsequent
        instances.
    */
    private static $firstOnPage = true;

    /*
        If a default (fallback) option is present, `$includeDefault` is set to
        the I18N token representing the default option label.
    */
    private $includeDefault;

    /*
        `$id` is the ID of the selection box element itself, and is used when
        outputting the script that controls the functionality of the preview
        function in `getFollowingBlock()`. It has to be stored at the class
        level so that it is accessible to `getFollowingBlock()` as well (the ID
        is only passed to the `getControl()` function).
    */
    private $id;

    public function __construct($includeDefault = null) {
        $this->includeDefault = $includeDefault;

        /*
            Populate the list of installed icon packs. This function reads the
            content of all available pack.ini files (one for each icon pack) and
            stores them in memory for use when displaying the control later. The
            ID of each icon pack is the name of the directory in which the icon
            pack resides.
        */
        if (self::$packs === null) {
            self::$packs = array();
            $themepath = __DIR__."/../../themes/icons";
            $themes = array_diff(scandir($themepath), array('..', '.'));
            foreach ($themes as $theme) {
                if (!file_exists("{$themepath}/{$theme}/pack.ini")) continue;
                $data = parse_ini_file("{$themepath}/{$theme}/pack.ini", true);
                self::$packs[$theme] = $data;
            }
        }
    }

    public function getControl($current = null, $name = null, $id = null, $attributes = array()) {
        __require("i18n");

        $this->id = $id;
        $attrs = "";
        if ($name !== null) {
            $attrs .= ' name="'.$name.'"';
        }
        if ($id !== null) {
            $attrs .= ' id="'.$id.'"';
        }

        foreach ($attributes as $attr => $value) {
            $attrs .= ' '.$attr.'="'.$value.'"';
        }

        $html = '<select'.$attrs.'>';
        if ($this->includeDefault !== null) {
            $html .= '<option value="">'.
                     I18N::resolveHTML($this->includeDefault).
                     '</option>';
        }
        foreach (self::$packs as $pack => $data) {
            /*
                Each option should use the name of the icon pack and its author
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
        `IconPackOption` has been added to the page. The output of the function
        is rendered as a block underneath the setting line. It contains a
        preview of all of the icons in the icon pack. The block also contains
        the script files that ensure functionality (the main script) as well as
        the event binder script that attaches a selection event handler to the
        selection box so that the preview is updated whenever the selection
        changes (the selector script). These are both output by default, but can
        be suppressed using the two arguments to this function.
    */
    public function getFollowingBlock($includeMainScript = true, $includeSelectorScript = true) {
        $out = "";

        /*
            The main script defines a method that will update the icon pack
            preview box with a given ID based on the ID of a theme passed to it.
            This should only be output once on the page to avoid duplicate
            versions of this function, but can also be suppressed entirely.
        */
        if (self::$firstOnPage) {
            self::$firstOnPage = false;
            if ($includeMainScript) {
                $script = self::getScript();
                $out .= $script;
            }
        }

        /*
            The preview box can only be added to the page if the selection box
            has an ID associated with it. This is because the script uses IDs to
            identify which icon selector box is attached to which icon selector.
            If it has no ID then the script cannot idenfity which icon preview
            box is supposed to be updated in the event handler.
        */
        if ($this->id !== null) {
            $html = '<div style="width: 100%;" id="iconviewer-'.$this->id.'"></div>';
            if ($includeSelectorScript) $html .= '
            <script type="text/javascript">'.$this->getSelectorScript().'</script>';
            $out .= $html;
        }

        return $out;
    }

    /*
        This script ensures that the preview is displayed for the selected icon
        pack when the page loads, and also adds an event handler to update it if
        the icon pack selection changes.
    */
    public function getSelectorScript() {
        return 'viewTheme("'.$this->id.'", document.getElementById("'.$this->id.'").value);
        document.getElementById("'.$this->id.'").addEventListener("change", function() {
            viewTheme("'.$this->id.'", document.getElementById("'.$this->id.'").value);
        });';
    }

    /*
        The main script that is responsible for actually changing the icon pack
        preview. TODO: Move this to /js/main.js.
    */
    public static function getScript() {
        __require("theme");

        $script = '<script type="text/javascript">
            var themedata = '.json_encode(self::$packs, JSON_PRETTY_PRINT).';

            function viewTheme(selectorID, theme) {
                var box = document.getElementById("iconviewer-" + selectorID);
                box.innerHTML = "";

                if (theme === "") return;

                var variants = ["light", "dark"];
                var varbox = {};

                for (var i = 0; i < variants.length; i++) {
                    varbox[variants[i]] = document.createElement("div");
                    varbox[variants[i]].style.width = "calc(100% - 20px)";
                    varbox[variants[i]].style.padding = "10px";
                }

                varbox["light"].style.backgroundColor = "#ccc";
                varbox["dark"].style.backgroundColor = "#333";

                var tdata = themedata[theme];

                var icons = '.json_encode(Theme::listIcons()).';

                for (var i = 0; i < icons.length; i++) {
                    var uri = "'.Config::getEndpointUri("/").'themes/icons/" + theme + "/";
                    if (tdata.hasOwnProperty("vector") && tdata["vector"].hasOwnProperty(icons[i])) {
                        uri += tdata["vector"][icons[i]];
                    } else if (tdata.hasOwnProperty("raster") && tdata["raster"].hasOwnProperty(icons[i])) {
                        uri += tdata["raster"][icons[i]];
                    } else {
                        uri = null;
                    }

                    if (uri != null) {
                        for (var j = 0; j < variants.length; j++) {
                            var icobox = document.createElement("img");
                            icobox.src = uri.split("{%variant%}").join(variants[j]);
                            icobox.style.width = "68px";
                            icobox.style.height = "68px";
                            icobox.style.margin = "5px";
                            varbox[variants[j]].appendChild(icobox);
                        }
                    }
                }

                if (tdata.hasOwnProperty("logo")) {
                    var logo = document.createElement("img");
                    logo.src = "'.Config::getEndpointUri("/").'themes/icons/" + theme + "/" + tdata["logo"].split("{%variant%}").join('.Config::getJS("themes/color/admin").');
                    logo.style.width = "400px";
                    logo.style.maxWidth = "100%";
                    logo.marginTop = "20px";
                    box.appendChild(logo);
                }

                var name = document.createElement("h2");
                name.innerText = tdata.name;
                name.style.color = "#'.(Config::get("themes/color/admin") == "dark" ? "ccc" : "333").'";
                name.style.marginBottom = "0";
                box.appendChild(name);

                var author = document.createElement("p");
                author.innerText = "Authored by " + tdata.author;
                box.appendChild(author);

                for (var i = 0; i < variants.length; i++) {
                    box.appendChild(varbox[variants[i]]);
                }

            }
        </script>';
        return $script;
    }

    public function parseValue($data) {
        return strval($data);
    }

    public function isValid($data) {
        return isset(self::$packs[$data]);
    }
}

?>
