<?php
/*
    This library file contains functions relating to outbound HTTP connections.
*/

__require("config");

class HTTP {
    /*
        Sets connection options for a cURL handler object.
    */
    public static function setOptions(&$ch) {
        curl_setopt($ch, CURLOPT_USERAGENT, "FreeField/".FF_VERSION." PHP/".phpversion());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        /*
            Set SSL/TLS connection options.
        */
        if (Config::get("security/curl/verify-certificates")->value()) {
            $cacert = Config::get("security/curl/cacert-path")->value();
            if (file_exists($cacert)) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                curl_setopt($ch, CURLOPT_CAINFO, $cacert);
            }
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }
    }

    /*
        Post a webhook for an event.

        `$hook` = The webhook array.
        `$data` = A data object to pass on to the `$bodyPrepFunc`.
        `$tokenReplFunc` = Callback for token replacement, taking the arguments:

            `$token` = The token to replace.
            `$args` = An array of token arguments.
            `$data` = The `$data` object passed to `postWebhook()`.

        See e.g. /includes/api/poi/report.php for example usage.
    */
    public static function postWebhook($hook, $data, $tokenReplFunc) {
        try {
            switch ($hook["type"]) {
                case "json":
                    /*
                        Replace text replacement strings (e.g. <%COORDS%>) in
                        the webhook's payload body. This is handled through the
                        callback. We provide a function for escaping strings.
                    */
                    $body = self::replaceWebhookFields($hook["body"], $data, $tokenReplFunc, function($str) {
                        /*
                            String escaping for JSON Convert to JSON string and
                            remove leading and trailing quotation marks.
                        */
                        return substr(json_encode($str), 1, -1);
                    });

                    $ch = curl_init($hook["target"]);
                    self::setOptions($ch);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                        "Content-Type: application/json",
                        "Content-Length: ".strlen($body)
                    ));
                    curl_exec($ch);
                    curl_close($ch);
                    break;

                case "telegram":
                    /*
                        Replace text replacement strings (e.g. <%COORDS%>) in
                        the webhook's payload body. This is handled through the
                        callback. We provide a function for escaping strings.
                    */
                    $body = self::replaceWebhookFields($hook["body"], $data, $tokenReplFunc, function($str) {
                        /*
                            Escape any special Markdown or HTML characters in
                            the webhook body according to the format of the
                            message being sent.
                        */
                        global $hook;
                        switch ($hook["options"]["parse-mode"]) {
                            case "md":
                                // Markdown - escape \[*_`
                                return preg_replace("/([\\\[\*_`])/", "\\\\\\1", $str);
                            case "html":
                                // HTML - escape special HTML chars
                                return htmlspecialchars($str, ENT_QUOTES);
                            default:
                                // Plain text - do not escape strings
                                return $str;
                        }
                    });

                    /*
                        Extract the Telegram group ID from the target URL.
                    */
                    $matches = array();
                    preg_match('/^tg:\/\/send\?to=(-\d+)$/', $hook["target"], $matches);

                    /*
                        Create an array to be POSTed to the Telegram API.
                    */
                    $postArray = array(
                        "chat_id" => $matches[1],
                        "text" => $body,
                        "disable_web_page_preview" => $hook["options"]["disable-web-page-preview"],
                        "disable_notification" => $hook["options"]["disable-notification"]
                    );
                    switch ($hook["options"]["parse-mode"]) {
                        case "md":
                            $postArray["parse_mode"] = "Markdown";
                            break;
                        case "html":
                            $postArray["parse_mode"] = "HTML";
                            break;
                    }
                    $postdata = json_encode($postArray);

                    __require("security");
                    $botToken = Security::decryptArray(
                        $hook["options"]["bot-token"],
                        "config",
                        "token"
                    );

                    $ch = curl_init("https://api.telegram.org/bot".
                        urlencode($botToken)."/sendMessage");
                    self::setOptions($ch);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                        "Content-Type: application/json",
                        "Content-Length: ".strlen($postdata)
                    ));
                    curl_exec($ch);
                    curl_close($ch);
                    break;
            }
        } catch (Exception $e) {

        }
    }

    /*
        Recursively resolves replacement tokens in a webhook body.

        `$body` = The webhook body containing replacement tokens.
        `$data` = A data array provided by the script calling `postWebhook()`.
        `$callback` = The token replacement function given to `postWebhook()`.
        `$escapeFunc` = A function for escaping strings in the webhook body.
    */
    public static function replaceWebhookFields($body, $data, $callback, $escapeFunc) {
        /*
            The body likely contains substitution tokens, and our job in this
            function is to replace them with the values that they are supposed to
            represent. We need a way to reliably extract tokens that can take
            optional parameters. We can use regex to do this, by looking for a
            sequence of something like <%.*(.*)?%>.

            We need to ensure that it handles tags within tags properly, i.e. it
            doesn't do nonsense like this:

            <%TAG(<%NESTED_TAG(some argument)%>,some other argument)%>
            |--------------MATCH--------------|

            The solution is the following regex query:

            <%                  | Open substitution token tag
            (                   | Group 1: Substitution token name
              (                 | Group 2: Match either:
                [^\(%]          | Any character that is not ( or %
              |                 | Or:
                %(?!>)          | A % that is not followed by >
              )*                | .. and match any number of the preceding
            )                   |
            (                   | Group 3: Parameters wrapped in parentheses
              \(                | Opening parenthesis before parameter list
              (                 | Group 4: Parameters, not wrapped
                (               | Group 5: Match either:
                  [^<\)]        | Any character that is not < or )
                |               | Or:
                  \)(?!%>)      | A ) that is not followed by %>
                |               | Or:
                  <(?!%)        | A < that is not followed by %
                )*              | .. and match any number of the preceding
                (?=\)%>)        | ..as long as, and until, followed by sequence )%>
              )                 |
              \)                | Closing parenthesis after parameter list
            )?                  | Parameters are optional
            %>                  | Close substitution token tag

            This query string allows us to detect and handle tags within tags
            properly. You can test it here: https://www.regexpal.com/

            The output is a match array with the following usable indices:

                [0] => The whole tag
                [1] => The name of the substitution token (e.g. "I18N")
                [4] => A comma-separated list of parameters

            These can be used to insert the correct strings of text in the webhook
            body.
        */
        $regex = "/<%(([^\(%]|%(?!>))*)(\((([^<\)]|\)(?!%>)|<(?!%))*(?=\)%>))\))?%>/";

        /*
            When we substitute the tokens, there is no guarantee that the
            replacement does not contain a special character or sequence, such as
            '<%', '%>' or ','. In order to prevent injection attacks, we store the
            replacements for each token in an array `$replArray`. Each replacement
            is assigned a uniquely generated ID, and the replacement that is
            inserted into the body is this unique ID string. The actual value of the
            replacement is stored in `$replArray` at the key corresponding to the
            generated ID, and all of the replacements are processed together once
            all replacements have been made.
        */
        $replArray = array();

        $matches = array();
        preg_match_all($regex, $body, $matches, PREG_SET_ORDER);

        while (count($matches) > 0) {
            foreach ($matches as $match) {
                $tokenTag = $match[0];
                $tokenName = $match[1];
                $tokenArgString = count($match) >= 5 ? $match[4] : "";

                /*
                    Get a list of passed parameters.
                */
                if (strlen($tokenArgString) > 0) {
                    // The argument string is comma-delimited.
                    $tokenArgs = explode(",", $tokenArgString);
                } else {
                    $tokenArgs = array();
                }

                /*
                    Resolve any prior replacements in the argument strings.
                */
                for ($i = 0; $i < count($tokenArgs); $i++) {
                    foreach ($replArray as $id => $repl) {
                        $tokenArgs[$i] = str_replace($id, $repl, $tokenArgs[$i]);
                    }
                }

                /*
                    Attempt to resolve a replacement via the given callback.
                */
                $replacement = $callback($tokenName, $tokenArgs, $data);
                if ($replacement == null) $replacement = "";

                /*
                    Generate a random ID for this replacement and insert the real
                    replacement into `$replArray`.
                */
                $id = base64_encode(openssl_random_pseudo_bytes(16));
                $replArray[$id] = strval($replacement);

                /*
                    Replace the matched tag with the replacement.
                */
                $body = str_replace($tokenTag, $id, $body);
            }

            preg_match_all($regex, $body, $matches, PREG_SET_ORDER);
        }

        /*
            Resolve all replacement IDs in the body.
        */
        foreach ($replArray as $id => $repl) {
            $body = str_replace($id, $escapeFunc($repl), $body);
        }

        return $body;
    }

    /*
        A token replacement function that replaces tokens that are common across
        all webhook types and events.
    */
    public static function replaceCommonWebhookFields($token, $args) {
        $replacement = "";
        switch (strtoupper($token)) {
            /*
                Please consult the documentation for information about each of
                these substitution tokens.
            */

            case "FALLBACK":
                // <%FALLBACK(expr,fallback)%>
                // expr: String to return by default.
                // fallback: String to return instead of `expr` is empty.
                if (count($args) < 2) break;
                $replacement = $args[0] != "" ? $args[0] : $args[1];
                break;

            case "IF_EMPTY":
            case "IF_NOT_EMPTY":
                // <%IF_EMPTY(expr,ifTrue[,ifFalse])%>
                // <%IF_NOT_EMPTY(expr,ifTrue[,ifFalse])%>
                // expr: Expression to evaluate.
                // ifTrue: Output if expr == ""
                // ifFalse: Output if expr != "", empty string if not given
                if (count($args) < 2) break;
                $expr = $args[0];
                $ifTrue = $args[1];
                $ifFalse = count($args) >= 3 ? $args[2] : "";
                switch ($token) {
                    case "IF_EMPTY":
                        $eval = $expr == "";
                        break;
                    case "IF_NOT_EMPTY":
                        $eval = $expr != "";
                        break;
                }
                $replacement = $eval ? $ifTrue : $ifFalse;
                break;

            case "IF_EQUAL":
            case "IF_NOT_EQUAL":
            case "IF_LESS_THAN":
            case "IF_LESS_OR_EQUAL":
            case "IF_GREATER_THAN":
            case "IF_GREATER_OR_EQUAL":
                // <%IF_EQUAL(expr,value,ifTrue[,ifFalse])%>
                // <%IF_NOT_EQUAL(expr,value,ifTrue[,ifFalse])%>
                // <%IF_LESS_THAN(expr,value,ifTrue[,ifFalse])%>
                // <%IF_LESS_OR_EQUAL(expr,value,ifTrue[,ifFalse])%>
                // <%IF_GREATER_THAN(expr,value,ifTrue[,ifFalse])%>
                // <%IF_GREATER_OR_EQUAL(expr,value,ifTrue[,ifFalse])%>
                // expr: Expression to evaluate.
                // value: Value to evaluate the expression against.
                // ifTrue: Output if expression matches value as specified
                // ifFalse: Output otherwise, empty string if not given
                if (count($args) < 3) break;
                $expr = $args[0];
                $value = $args[1];
                $ifTrue = $args[2];
                $ifFalse = count($args) >= 4 ? $args[3] : "";
                switch ($token) {
                    case "IF_EQUAL":
                        $eval = $expr == $value;
                        break;
                    case "IF_NOT_EQUAL":
                        $eval = $expr != $value;
                        break;
                    case "IF_LESS_THAN":
                        $eval = floatval($expr) < floatval($value);
                        break;
                    case "IF_LESS_OR_EQUAL":
                        $eval = floatval($expr) <= floatval($value);
                        break;
                    case "IF_GREATER_THAN":
                        $eval = floatval($expr) > floatval($value);
                        break;
                    case "IF_GREATER_OR_EQUAL":
                        $eval = floatval($expr) >= floatval($value);
                        break;
                }
                $replacement = $eval ? $ifTrue : $ifFalse;
                break;

            case "I18N":
                // <%I18N(token[,arg1[,arg2...]])%>
                // token: Localization token
                // arg1..n: Argument to localization

                if (count($args) < 1) break;
                __require("i18n");
                $i18ntoken = $args[0];
                if (count($args) == 1) {
                    $replacement = call_user_func_array("I18N::resolve", $args);
                } else {
                    $replacement = call_user_func_array("I18N::resolveArgs", $args);
                }
                break;

            case "LENGTH":
                // <%LENGTH(string)%>
                if (count($args) < 1) break;
                $replacement = strlen($args[0]);
                break;

            case "LOWERCASE":
                // <%LOWERCASE(string)%>
                if (count($args) < 1) break;
                $replacement = strtolower($args[0]);
                break;

            case "PAD_LEFT":
            case "PAD_RIGHT":
                // <%PAD_LEFT(string,length[,padString])%>
                // <%PAD_RIGHT(string,length[,padString])%>
                if (count($args) < 2) break;
                $string = $args[0];
                $length = intval($args[1]);
                $padString = count($args) >= 3 ? $args[2] : " ";
                $padType = $token == "PAD_LEFT" ? STR_PAD_LEFT : STR_PAD_RIGHT;
                $replacement = str_pad($string, $length, $padString, $padType);
                break;

            case "SITEURL":
                // <%SITEURL%>
                $replacement = Config::getEndpointUri("/");
                break;

            case "SUBSTRING":
                // <%SUBSTRING(string,start[,length])%>
                if (count($args) < 2) break;
                $string = $args[0];
                $start = intval($args[1]);
                if (count($args) >= 3) {
                    $length = intval($args[2]);
                    $replacement = substr($string, $start, $length);
                } else {
                    $replacement = substr($string, $start);
                }
                if ($replacement === FALSE) $replacement = "";
                break;

            case "UPPERCASE":
                // <%UPPERCASE(string)%>
                if (count($args) < 1) break;
                $replacement = strtoupper($args[0]);
                break;
        }
        return $replacement;
    }

    /*
        Replace webhook fields for POI-related events.
    */
    public static function replaceWebhookFieldsForPOI($token, $args, $poi) {
        $replacement = null;
        switch (strtoupper($token)) {
            /*
                Please consult the documentation for information about each of
                these substitution tokens.
            */

            case "COORDS":
                // <%COORDS([precision])%>
                // precision: Number of decimals in output.
                $replacement = Geo::getLocationString(
                    $poi->getLatitude(),
                    $poi->getLongitude()
                );
                if (count($args) > 0) {
                    $replacement = Geo::getLocationString(
                        $poi->getLatitude(),
                        $poi->getLongitude(),
                        intval($args[0])
                    );
                }
                break;

            case "LAT":
                // <%LAT%>
                $replacement = $poi->getLatitude();
                break;

            case "LNG":
                // <%LNG%>
                $replacement = $poi->getLongitude();
                break;

            case "NAVURL":
                // <%NAVURL([provider])%>
                // provider: Navigation provider ("google", "bing", etc.)
                $naviprov = Geo::listNavigationProviders();
                $provider = Config::get("map/provider/directions")->value();
                if (count($args) > 0) $provider = $args[0];
                if (isset($naviprov[$provider])) {
                    $replacement =
                        str_replace("{%LAT%}", urlencode($poi->getLatitude()),
                        str_replace("{%LON%}", urlencode($poi->getLongitude()),
                        str_replace("{%NAME%}", urlencode($poi->getName()),
                            $naviprov[$provider]
                        )));
                }
                break;

            case "POI":
                // <%POI%>
                $replacement = $poi->getName();
                break;
        }
        return $replacement;
    }

    /*
        Replace webhook fields for report-related events.
    */
    public static function replaceWebhookFieldsForReport($token, $args, $user, $time) {
        $replacement = null;
        switch (strtoupper($token)) {
            /*
                Please consult the documentation for information about each of
                these substitution tokens.
            */

            case "REPORTER":
                // <%REPORTER%>
                $replacement = $user->getNickname();
                break;

            case "TIME":
                // <%TIME(format)%>
                // format: PHP date() format string
                if (count($args) < 1) break;
                $replacement = date($args[0], $time);
                break;
        }
        return $replacement;
    }
}

?>
