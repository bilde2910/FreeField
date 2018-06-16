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
    
    public static function resolveArgs($token, ...$args) {
        $string = self::resolve($token);
        if (is_array($args[0])) $args = $args[0];
        for ($i = 0; $i < count($args); $i++) {
            $string = str_replace('{%'.($i+1).'}', $args[$i], $string);
        }
        return $string;
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
}

?>