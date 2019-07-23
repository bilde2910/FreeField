<?php
/*
    This library file handles internationalization and translations for all
    user-visible strings in FreeField.
*/

class I18N {
    /*
        The default language. If a string token isn't found in the preferred
        language file, a lookup will be performed against the default language
        specified here.

        WARNING TO SITE OPERATORS:
        Do NOT change this variable in your local FreeField installation!!!
        Doing so will lead to a poor user experience in cases where strings are
        missing from the language specified here as there will be NO FALLBACK
        to a language that has these missing strings defined!
    */
    const DEFAULT_LANG = "en-US";

    /*
        `$i18ndata` holds the I18N array for the loaded language(s).
    */
    private static $i18ndata = null;

    /*
        `$currentLanguage` holds the language code of the most preferred
        language currently loaded to `$i18ndata`.
    */
    private static $currentLanguage = null;

    /*
        If languages are overridden via `changeLanguage()`, store the requested
        language array here.
    */
    private static $overrideLanguages = null;

    /*
        Cache of available languages in FreeField.
    */
    private static $langCache = null;

    /*
        Cache of loaded language files in FreeField.
    */
    private static $fileCache = null;

    /*
        List of .po files which have been read and merged into `$i18ndata`.
    */
    private static $appliedFiles = array();

    /*
        Resolves a localized string for the given I18N token. If the string
        isn't found in the preferred language array, the next language is loaded
        and search against. If the string isn't found in any loaded languages,
        the token itself is returned.
    */
    public static function resolve($token) {
        if (self::$i18ndata === null) self::loadI18Ndata();
        $td = substr($token, 0, strpos($token, "."));

        /*
            First, check if the key is already defined (and return the value in
            that case).
        */
        if (!empty(self::$i18ndata[$token])) return self::$i18ndata[$token];

        /*
            If not, remove one language at a time from the list of preferred
            languages and reapply, reading more and more language data to
            determine if the string can be found in any of them.
        */
        $languages = self::getAcceptedLanguages();
        while (count($languages) > 0) {
            /*
                Always ensure that the default language is in the array.
            */
            if (!isset($languages[self::DEFAULT_LANG])) {
                $languages[self::DEFAULT_LANG] = "0";
            }
            self::loadI18Ndata($td, $languages);
            if (!empty(self::$i18ndata[$token])) return self::$i18ndata[$token];
            /*
                If the string wasn't found, continue to the next language.
            */
            array_shift($languages);
        }
        return $token;
    }

    /*
        An HTML safe version of `resolve()`. Returns the localized string with
        special HTML characters escaped.
    */
    public static function resolveHTML($token) {
        return htmlspecialchars(self::resolve($token), ENT_QUOTES);
    }

    /*
        A JavaScript safe version of `resolve()`. Returns the localized string
        as a quoted JSON string.
    */
    public static function resolveJS($token) {
        return json_encode(self::resolve($token));
    }

    /*
        Some localized strings may contain argument placeholders, such as
        "{%1}", "{%2}", etc. This function will look up and localize the given
        token, then replace as many placeholders as possible according to this
        pattern:

            "{%1}" => $args[0]
            "{%2}" => $args[1]
            "{%3}" => $args[2]
            ...

        The function will try to use all replacement strings in `$args` if
        possible. If there are more placeholders in the string than elements in
        `$args`, the remaining placeholders will be ignored. For example:

            $string = "Hi, my name is {%1}! My favorite color is {%2}.";
            $args = array(
                "Alice"
            );

        Replacement of `$args` into `$string` would result in the output:

            $string = "Hi, my name is Alice! My favorite color is {%2}.";
    */
    public static function resolveArgs($token, ...$args) {
        $string = self::resolve($token);
        if (is_array($args[0])) $args = $args[0];
        for ($i = 0; $i < count($args); $i++) {
            $string = str_replace('{%'.($i+1).'}', $args[$i], $string);
        }
        return $string;
    }

    /*
        An HTML safe version of `resolveArgs()`. Returns the localized string
        with special HTML characters escaped.

        `$deep` is a boolean value specifying whether a "deep escape" should be
        performed. If false, only the localized string itself will be escaped,
        and replacement strings for placeholders will be inserted as-is. If
        true, the placeholder replacements are also escaped. For example:

            $string = 'Please like & share {%1}our new website!{%2}';
            $args = array(
                '<a href="http://example.com/">',
                '</a>'
            );

        With `$deep = false`:

            $string = 'Please like &amp; share <a href="http://example.com/">'.
                      'our new website!</a>';

        With `$deep = true`:

            $string = 'Please like &amp; share &lt;a'.
                      'href=&quot;http://example.com/&quot;&gt;our new '.
                      'website!&lt;/a&gt;';
    */
    public static function resolveArgsHTML($token, $deep, ...$args) {
        if ($deep) {
            return htmlspecialchars(call_user_func_array(
                "I18N::resolveArgs",
                array_merge(array($token), $args)
            ), ENT_QUOTES);
        } else {
            $string = self::resolveHTML($token);
            if (is_array($args[0])) $args = $args[0];
            for ($i = 0; $i < count($args); $i++) {
                $string = str_replace('{%'.($i+1).'}', $args[$i], $string);
            }
            return $string;
        }
    }

    /*
        A JavaScript safe version of `resolveArgs()`. Returns the localized
        string as a quoted JSON string.

        `$deep` is a boolean value specifying whether a "deep escape" should be
        performed. If false, only the localized string itself will be escaped,
        and replacement strings for placeholders will be inserted as-is. If
        true, the placeholder replacements are also escaped. For example:

            $string = 'Our new project "Foo", also known as {%1}';
            $args = array(
                'the "Bar" project'
            );

        With `$deep = false`:

            $string = '"Our new project \"Foo\", also known as the "Bar"'.
                      'project"';

        With `$deep = true`:

            $string = '"Our new project \"Foo\", also known as the \"Bar\"'.
                      'project"';
    */
    public static function resolveArgsJS($token, $deep, ...$args) {
        if ($deep) {
            return json_encode(call_user_func_array(
                "I18N::resolveArgs",
                array_merge(array($token), $args)
            ));
        } else {
            $string = self::resolveJS($token);
            if (is_array($args[0])) $args = $args[0];
            for ($i = 0; $i < count($args); $i++) {
                $string = str_replace('{%'.($i+1).'}', $args[$i], $string);
            }
            return $string;
        }
    }

    /*
        A special function for resolving a batch of I18N tokens at once.
        `$tokenDomain` is a token refering a group of translations and ending
        with ".*" to denote wildcard lookup. Example: "poi.*" - in this example,
        the function will return an array of all I18N tokens starting with
        "poi.", along with their corresponding localized strings.

        Passing a token that is not a wildcard token (without the ".*" suffix)
        is also accepted, and will return an array containing only that one
        token and its localization. For that purpose, though, the standard
        `resolve()` function is a much better approach.
    */
    public static function resolveAll($tokenDomain) {
        if (self::$i18ndata === null) self::loadI18Ndata();
        /*
            If a non-wildcard token is passed, return an array containing only
            that one specific token and its corresponding localized string.
        */
        if (substr($tokenDomain, -2) !== ".*") {
            return array(
                $tokenDomain => self::resolve($tokenDomain)
            );
        } else {
            $td = substr($tokenDomain, 0, strpos($tokenDomain, "."));
            if (!isset(self::$i18ndata[$td])) self::loadI18Ndata($td);
        }

        $tokens = array();
        $domainlength = strlen($tokenDomain);
        foreach (self::$i18ndata as $key => $value) {
            /*
                Loop over the entire default I18N array to check if any of its
                keys match the domain prefix passed to this function. The
                preferred language array may not contain all keys, while the
                default array is guaranteed to contain all I18N keys.

                A substring is used cut off the trailing asterisk from the
                domain when checking for matches.
            */
            if (substr($key, 0, $domainlength - 1) == substr($tokenDomain, 0, -1)) {
                $tokens[$key] = self::resolve($key);
            }
        }
        return $tokens;
    }

    /*
        An HTML safe version of `resolveAll()`. Returns the localized string
        with special HTML characters escaped.
    */
    public static function resolveAllHTML($tokenDomain) {
        $tokens = self::resolveAll($tokenDomain);
        foreach ($tokens as $token => $string) {
            $tokens[$token] = htmlspecialchars($string, ENT_QUOTES);
        }
    }

    /*
        A JavaScript safe version of `resolveAll()`. Returns the localized
        string as a quoted JSON string.
    */
    public static function resolveAllJS($tokenDomain) {
        $tokens = self::resolveAll($tokenDomain);
        foreach ($tokens as $token => $string) {
            $tokens[$token] = json_encode($string);
        }
    }

    /*
        Loads data into the I18N arrays if the I18N data is not currently
        loaded. This function is called by all I18N `resolve*()` functions if
        the I18N arrays are empty.
    */
    private static function loadI18Ndata($domain = "language", $languages = null) {
        /*
            Fetch a prioritized list of all languages accepted by the browser.
        */
        if ($languages === null) $languages = self::getAcceptedLanguages();

        /*
            Set the preferred language and load the I18N data.
        */
        self::setLanguages($languages, $domain);
    }

    /*
        Change the language currently active in FreeField.
    */
    public static function changeLanguage($language) {
        self::$i18ndata = null;
        self::$appliedFiles = null;
        self::$overrideLanguages = array(DEFAULT_LANG => "1");
        self::$overrideLanguages[$language] = "2";
    }

    private static function setLanguages($requested, $domain = "language") {
        /*
            Fetch a list of all languages installed in FreeField.
        */
        $available = self::getAvailableLanguages();

        /*
            Compare the list of accepted languages to the list of available
            languages. The highest priority language is checked first. The first
            language found in both arrays will be set as the preferred language,
            and will be used for localization.
        */
        if (self::$i18ndata === null) self::$i18ndata = array();
        foreach ($requested as $lang => $q) {
            $isAvailable = false;
            foreach ($available as $avail) {
                if (
                    $avail == $lang ||
                    substr($avail, 0, 2) == substr($lang, 0, 2)
                ) {
                    $isAvailable = true;
                    break;
                }
            }
            if ($isAvailable) {
                /*
                    If the given language is already loaded, don't load it
                    again.
                */
                $poFile = "{$avail}/{$domain}.po";
                if (!in_array($poFile, self::$appliedFiles)) {
                    self::$appliedFiles[] = $poFile;
                    /*
                        Read the language file into the file cache.
                    */
                    if (
                        !array_key_exists($poFile, self::$fileCache) &&
                        file_exists(__DIR__."/../i18n/{$poFile}")
                    ) {
                        self::$fileCache[$poFile] = self::parsePo(
                            __DIR__."/../i18n/{$poFile}"
                        );
                    }
                    if (isset(self::$fileCache[$poFile])) {
                        /*
                            Load the language file on top of the currently
                            loaded language, not replacing already defined
                            strings, if any.
                        */
                        foreach (self::$fileCache[$poFile] as $key => $value) {
                            if (empty(self::$i18ndata[$key])) {
                                self::$i18ndata[$key] = $value;
                            }
                        }
                    }
                }
                self::$currentLanguage = $avail;
                break;
            }
        }
    }

    /*
        Parses a gettext-compatible .po file and returns an array of the
        resulting string key-value pairs.
    */
    private static function parsePo($path) {
        $fh = fopen($path, "r");
        if ($fh) {
            $data = array();
            $lastId = "";
            while (($line = fgets($fh)) !== false) {
                $line = trim($line);
                if (substr($line, 0, 6) == "msgid ") {
                    $lastId = str_replace("\\\"", "\"", substr($line, 7, -1));
                } elseif (substr($line, 0, 7) == "msgstr ") {
                    $value = str_replace("\\\"", "\"", substr($line, 8, -1));
                    $data[$lastId] = $value;
                }
            }
            fclose($fh);
            return $data;
        } else {
            return array();
        }
    }

    /*
        Returns the currently loaded language.
    */
    public static function getLanguage() {
        if (self::$i18ndata === null) self::loadI18Ndata();
        return self::$currentLanguage;
    }

    /*
        Returns a prioritized list of languages accepted by the client, based on
        the Accept-Language header and `language` cookie (if any). Example:

            getAcceptedLanguages() == array(
                "en-US" => "1",
                "en-UK" => "0.9",
                "en" => "0.8",
                "es-ES" => "0.5",
                "es-AR" => "0.4"
            );

        This array is a list of the following languages where the first item in
        the list is the preferred language, followed by the second preference,
        the third preference, etc.

          - English (United States)
          - English (United Kingdom)
          - English
          - Spanish (Spain)
          - Spanish (Argentina)
    */
    public static function getAcceptedLanguages() {
        if (self::$overrideLanguages !== null) {
            return self::$overrideLanguages;
        }

        if (!isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) return array(
            self::DEFAULT_LANG => "1"
        );

        $lang_parse = array();
        /*
            This regex matches:

            [a-z]{1,8}
                A lower-case language code

            (-[a-z]{1,8})?
                An optional lower-case country code following the language code

            \s*
                Any amount of whitespace

            (;\s*q\s*=\s*(1|0\.[0-9]+))?
                An optional quality value (e.g. ";q=0.7"), with any amount of
                whitespace following the semi-colon, "q" or equals sign. The
                quality value can be 1, or a decimal value from 0.0 to 0.9.

            Accept-Language is parsed as defined by RFC 2616 section 14.2.
        */
        preg_match_all(
            '/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i',
            $_SERVER['HTTP_ACCEPT_LANGUAGE'],
            $lang_parse
        );

        if (count($lang_parse[1])) {
            /*
                Combine the language code (regex pattern group 1) with their
                corresponding quality values (group 4) to create an array of the
                syntax:

                    array(
                        "en-US" => "",
                        "en-UK" => "0.9",
                        "en" => "0.8",
                        "es-ES" => "0.5",
                        "es-AR" => "0.4"
                    );
            */
            $langs = array_combine($lang_parse[1], $lang_parse[4]);

            /*
                For all languages without an explicit quality value, assume
                quality value "1"
            */
            foreach ($langs as $lang => $val) {
                if ($val === '') $langs[$lang] = 1;
            }

            /*
                Capitalize the country code to match ISO 3166-1.
            */
            foreach ($langs as $lang => $val) {
                $matches = array();
                if (preg_match('/([a-z]+)-([a-z]+)/', $lang, $matches)) {
                    /*
                        Remove the array key with the uncapitalized country code
                        from the array and insert a new key where the country
                        code is capitalized.
                    */
                    unset($langs[$lang]);
                    $langs[$matches[1]."-".strtoupper($matches[2])] = $val;
                }
            }

            /*
                Add the default language to the bottom of the list, if not
                present already.
            */
            if (!isset($langs[self::DEFAULT_LANG])) $langs[self::DEFAULT_LANG] = "0";

            /*
                Add the user preferred language to the top of the list, if
                available.
            */
            if (
                isset($_COOKIE["language"]) &&
                preg_match('/^([a-z]+)-([A-Z]+)$/', $_COOKIE["language"])
            ) {
                $langs[$_COOKIE["language"]] = "2";
            }

            /*
                Sort the list in descending order by their quality value.
                `SORT_NUMERIC` has to be explicitly specified, since the quality
                values in the array are strings, not floats.
            */
            arsort($langs, SORT_NUMERIC);

            return $langs;
        }
        return array();
    }

    /*
        Scans the FreeField language files directory to obtain a list of all
        installed languages. Returns an array of language codes.
    */
    public static function getAvailableLanguages() {
        if (self::$langCache !== null) return self::$langCache;
        self::$langCache = array_diff(scandir(__DIR__."/../i18n"), array('..', '.'));
        return self::$langCache;
    }

    /*
        Returns a list of all installed languages in FreeField coupled with the
        name of each language as defined within the language files. Returns an
        associative array.
    */
    public static function getAvailableLanguagesWithNames() {
        $langs = self::getAvailableLanguages();
        $assoc = array();
        foreach ($langs as $lang) {
            $data = self::parsePo(__DIR__."/../i18n/{$lang}/language.po");
            $assoc[$lang] = $data["language.name_native"];
        }
        return $assoc;
    }
}

?>
