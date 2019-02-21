<?php
/*
    This library file is a helper class for JSON XMLHttpRequest target scripts.
    XHR script files can call `XHR::exitWith()` with an HTTP status code and a
    JSON data object, and the function will handle the proper output HTTP
    headers and output serialization.
*/

class XHR {
    public static function exitWith($code, $data) {
        $codes = array(
            200 => "OK",
            201 => "Created",
            204 => "No Content",
            400 => "Bad Request",
            403 => "Forbidden",
            405 => "Method Not Allowed",
            500 => "Internal Server Error",
            501 => "Not Implemented",
            502 => "Bad Gateway",
            504 => "Gateway Timeout"
        );

        header("HTTP/1.1 {$code} ".$codes[$code]);
        if ($data !== null) {
            /*
                Ensure the FreeField version number is included to ensure third
                party clients can properly parse the response from the API.
            */
            $data = array_merge(array("ff-version" => FF_VERSION), $data);
            header("Content-Type: application/json");
            echo json_encode($data, JSON_PRETTY_PRINT);
        }
        exit;
    }
}

?>
