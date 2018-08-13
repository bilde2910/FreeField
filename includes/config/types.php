<?php

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

class StringOption extends DefaultOption {
    private $regex;

    public function __construct($regex = null) {
        $this->regex = $regex;
    }

    public function getControl($current = null, $name = null, $id = null) {
        $attrs = "";
        if ($name !== null) $attrs .= ' name="'.$name.'"';
        if ($id !== null) $attrs .= ' id="'.$id.'"';
        if ($current !== null) $attrs .= ' value="'.htmlspecialchars($current, ENT_QUOTES).'"';
        if ($this->regex !== null) $attrs .= ' data-validate-as="regex-string" data-validate-regex="'.$this->regex.'"';
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

class PasswordOption extends DefaultOption {
    public function getControl($current = null, $name = null, $id = null) {
        $attrs = "";
        if ($name !== null) $attrs .= ' name="'.$name.'"';
        if ($id !== null) $attrs .= ' id="'.$id.'"';
        if ($current !== null) $attrs .= ' value="'.htmlspecialchars($current, ENT_QUOTES).'"';
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

class BooleanOption extends DefaultOption {
    public function getControl($current = null, $name = null, $id = null, $i18ntoken = null) {
        __require("i18n");

        $attrs = "";
        if ($name !== null) $attrs .= ' name="'.$name.'"';
        if ($id !== null) $attrs .= ' id="'.$id.'"';
        if ($current === true) $attrs .= ' checked';

        $labelAttrs = "";
        if ($id !== null) $labelAttrs .= ' for="'.$id.'"';

        $fallbackAttrs = "";
        if ($name !== null) $fallbackAttrs .= ' name="'.$name.'"';

        if ($i18ntoken !== null) {
            $label = I18N::resolveHTML($i18ntoken);
        } elseif ($name !== null) {
            $label = I18N::resolveHTML("setting.".str_replace("-", "_", str_replace("/", ".", $name)).".label");
        } elseif ($id !== null) {
            $label = I18N::resolveHTML("setting.".str_replace("-", "_", $id).".label");
        } else {
            $label = $item;
        }

        $html = '<input type="hidden" value="off"'.$fallbackAttrs.'>'; // Detect unchecked checkbox - unchecked checkboxes aren't POSTed!
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

class IntegerOption extends DefaultOption {
    private $min;
    private $max;

    public function __construct($min = null, $max = null) {
        $this->min = $min;
        $this->max = $max;
    }

    public function getControl($current = null, $name = null, $id = null) {
        $attrs = "";
        if ($name !== null) $attrs .= ' name="'.$name.'"';
        if ($id !== null) $attrs .= ' id="'.$id.'"';
        if ($current !== null) $attrs .= ' value="'.htmlspecialchars($current, ENT_QUOTES).'"';
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

class FloatOption extends DefaultOption {
    private $min;
    private $max;

    public function __construct($min = null, $max = null) {
        $this->min = $min;
        $this->max = $max;
    }

    public function getControl($current = null, $name = null, $id = null, $decimals = 5) {
        $attrs = "";
        if ($name !== null) $attrs .= ' name="'.$name.'"';
        if ($id !== null) $attrs .= ' id="'.$id.'"';
        if ($current !== null) $attrs .= ' value="'.htmlspecialchars($current, ENT_QUOTES).'"';
        if ($decimals >= 1) {
            $attrs .= ' step="0.'.str_repeat("0", $decimals - 1).'"';
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

class GeofenceOption extends DefaultOption {
    public function getControl($current = null, $name = null, $id = null) {
        $attrs = "";
        if ($name !== null) $attrs .= ' name="'.$name.'"';
        if ($id !== null) $attrs .= ' id="'.$id.'"';

        $value = "";
        if ($current !== null) {
            foreach ($current as $point) {
                $value .= $point[0] . "," . $point[1] . "\n";
            }
        }

        $value = trim($value);
        return '<textarea data-validate-as="geofence"'.$attrs.'>' . $value . '</textarea>';
    }

    public function parseValue($data) {
        if ($data === "") return null;
        $points = array();
        $lines = preg_split('/\r\n?|\n\r?/', $data);
        foreach ($lines as $line) {
            if ($line === "") continue;
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
        if ($data === null) return true;
        if (!is_array($data)) return false;
        if (count($data) < 3) return false;
        foreach ($data as $point) {
            if (!is_array($point) || count($point) !== 2) return false;
            $lat = $point[0];
            $lon = $point[1];
            if (!is_float($lat)) return false;
            if (!is_float($lon)) return false;
            if ($lat < -90 || $lat > 90) return false;
            if ($lon < -180 || $lon > 180) return false;
        }
        return true;
    }
}

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
        if ($name !== null) $attrs .= ' name="'.$name.'"';
        if ($id !== null) $attrs .= ' id="'.$id.'"';

        $html = '<select'.$attrs.'>';
        $selected = false;
        foreach ($this->items as $item) {
            $html .= '<option value="'.$item.'"';
            if ($item == $current) {
                $selected = true;
                $html .= ' selected';
            }
            if ($i18ndomain !== null) {
                $label = I18N::resolveHTML("{$i18ndomain}.{$item}");
            } elseif ($name !== null) {
                $label = I18N::resolveHTML("setting.".str_replace("-", "_", str_replace("/", ".", $name)).".option.{$item}");
            } elseif ($id !== null) {
                $label = I18N::resolveHTML("setting.".str_replace("-", "_", $id).".option.{$item}");
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

class PermissionOption extends DefaultOption {
    public function getControl($current = 0, $name = null, $id = null) {
        __require("auth");

        return Auth::getPermissionSelector($name, $id, $current);
    }

    public function parseValue($data) {
        return intval($data);
    }

    public function isValid($data) {
        if (!is_int($data)) return false;
        if ($data > 250 || $data < 0) return false;

        __require("auth");
        if (!Auth::getCurrentUser()->canChangeAtPermission($data)) return false;

        return true;
    }
}

class IconPackOption extends DefaultOption {
    private static $packs = null;
    private static $firstOnPage = true;

    private $includeDefault;

    private $id;

    public function __construct($includeDefault = null) {
        $this->includeDefault = $includeDefault;

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
        if ($name !== null) $attrs .= ' name="'.$name.'"';
        if ($id !== null) $attrs .= ' id="'.$id.'"';

        foreach ($attributes as $attr => $value) {
            $attrs .= ' '.$attr.'="'.$value.'"';
        }

        $html = '<select'.$attrs.'>';
        if ($this->includeDefault !== null) {
            $html .= '<option value="">'.I18N::resolveHTML($this->includeDefault).'</option>';
        }
        foreach (self::$packs as $pack => $data) {
            $html .= '<option value="'.$pack.'"';
            if ($pack == $current) $html .= ' selected';
            $html .= '>'.I18N::resolveArgsHTML("theme.name_label", true, $data["name"], $data["author"]).'</option>';
        }
        $html .= '</select>';
        return $html;
    }

    public function getFollowingBlock($includeMainScript = true, $includeSelectorScript = true) {
        $out = "";

        if (self::$firstOnPage) {
            self::$firstOnPage = false;
            if ($includeMainScript) {
                $script = self::getScript();
                $out .= $script;
            }
        }

        if ($this->id !== null) {
            $html = '<div style="width: 100%;" id="iconviewer-'.$this->id.'"></div>';
            if ($includeSelectorScript) $html .= '
            <script type="text/javascript">'.$this->getSelectorScript().'</script>';
            $out .= $html;
        }

        return $out;
    }

    public function getSelectorScript() {
        return 'viewTheme("'.$this->id.'", document.getElementById("'.$this->id.'").value);
        document.getElementById("'.$this->id.'").addEventListener("change", function() {
            viewTheme("'.$this->id.'", document.getElementById("'.$this->id.'").value);
        });';
    }

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
