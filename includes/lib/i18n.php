<?php

class I18N {
    private const DEFAULT_LANG = "en-US";

    private static $i18ndata = null;
    private static $i18ndefault = null;

    public static function resolve($token) {
        if (self::$i18ndata === null) self::loadI18Ndata();
        if (isset(self::$i18ndata[$token])) return self::$i18ndata[$token];
        if (isset(self::$i18ndefault[$token])) return self::$i18ndefault[$token];
        return $token;
    }

    public static function resolveHTML($token) {
        return htmlspecialchars(self::resolve($token), ENT_QUOTES);
    }

    public static function resolveJS($token) {
        return json_encode($token);
    }

    public static function resolveArgs($token, ...$args) {
        $string = self::resolve($token);
        if (is_array($args[0])) $args = $args[0];
        for ($i = 0; $i < count($args); $i++) {
            $string = str_replace('{%'.($i+1).'}', $args[$i], $string);
        }
        return $string;
    }

    public static function resolveArgsHTML($token, $deep, ...$args) {
        if ($deep) {
            return htmlspecialchars(call_user_func_array("I18N::resolveArgs", array_merge(array($token), $args)), ENT_QUOTES);
        } else {
            $string = self::resolveHTML($token);
            if (is_array($args[0])) $args = $args[0];
            for ($i = 0; $i < count($args); $i++) {
                $string = str_replace('{%'.($i+1).'}', $args[$i], $string);
            }
            return $string;
        }
    }

    public static function resolveArgsJS($token, $deep, ...$args) {
        if ($deep) {
            return json_encode(call_user_func_array("I18N::resolveArgs", array_merge(array($token), $args)));
        } else {
            $string = self::resolveJS($token);
            if (is_array($args[0])) $args = $args[0];
            for ($i = 0; $i < count($args); $i++) {
                $string = str_replace('{%'.($i+1).'}', $args[$i], $string);
            }
            return $string;
        }
    }

    public static function resolveAll($tokenDomain) {
        if (self::$i18ndata === null) self::loadI18Ndata();
        if (substr($tokenDomain, -2) !== ".*") return array($tokenDomain => self::resolve($tokenDomain));
        $tokens = array();
        $domainlength = strlen($tokenDomain);
        foreach (self::$i18ndefault as $key => $value) {
            if (substr($key, 0, $domainlength - 1) == substr($tokenDomain, 0, -1)) {
                $tokens[$key] = self::resolve($key);
            }
        }
        return $tokens;
    }

    public static function resolveAllHTML($tokenDomain) {
        $tokens = self::resolveAll($tokenDomain);
        foreach ($tokens as $token => $string) {
            $tokens[$token] = htmlspecialchars($string, ENT_QUOTES);
        }
    }

    public static function resolveAllJS($tokenDomain) {
        $tokens = self::resolveAll($tokenDomain);
        foreach ($tokens as $token => $string) {
            $tokens[$token] = json_encode($string);
        }
    }

    private static function loadI18Ndata() {
        $requested = self::getAcceptedLanguages();
        $available = self::getAvailableLanguages();
        foreach ($requested as $lang => $q) {
            if (in_array($lang, $available)) {
                self::$i18ndata = parse_ini_file(__DIR__."/../i18n/$lang.ini");
                break;
            }
        }

        if (self::$i18ndata === null) self::$i18ndata = array();

        if ($lang == self::DEFAULT_LANG) {
            self::$i18ndefault = self::$i18ndata;
        } else {
            self::$i18ndefault = parse_ini_file(__DIR__."/../i18n/".self::DEFAULT_LANG.".ini");
        }
    }

    public static function getAcceptedLanguages() {
        $lang_parse = array();
        preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', $_SERVER['HTTP_ACCEPT_LANGUAGE'], $lang_parse);
        if (count($lang_parse[1])) {
            // Create a list like "en" => 0.8
            $langs = array_combine($lang_parse[1], $lang_parse[4]);

            // Set default to 1 for any without q factor
            foreach ($langs as $lang => $val) {
                if ($val === '') $langs[$lang] = 1;
            }

            // Capitalize country name
            foreach ($langs as $lang => $val) {
                $matches = array();
                if (preg_match('/([a-z]+)-([a-z]+)/', $lang, $matches)) {
                    unset($langs[$lang]);
                    $langs[$matches[1]."-".strtoupper($matches[2])] = $val;
                }
            }

            // Sort list based on value
            arsort($langs, SORT_NUMERIC);

            return $langs;
        }
        return array();
    }

    public static function getAvailableLanguages() {
        $files = array_diff(scandir(__DIR__."/../i18n"), array('..', '.'));
        for ($i = 2; $i < count($files) + 2; $i++) {
            // Cut off file extension
            $files[$i] = substr($files[$i], 0, -4);
        }
        return $files;
    }

    public static function getAvailableLanguagesWithNames() {
        $langs = self::getAvailableLanguages();
        $assoc = array();
        foreach ($langs as $lang) {
            $data = parse_ini_file(__DIR__."/../i18n/$lang.ini");
            $assoc[$lang] = $data["language.name_native"];
        }
        return $assoc;
    }
}

?>
