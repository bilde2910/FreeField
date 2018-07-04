<?php

class XHR {
    public static function exitWith($code, $data) {
        $codes = array(
            200 => "OK",
            201 => "Created",
            204 => "No Content",
            400 => "Bad Request",
            403 => "Forbidden",
            405 => "Method Not Allowed",
            500 => "Internal Server Error"
        );

        header("HTTP/1.1 {$code} ".$codes[$code]);
        if ($data !== null) {
            header("Content-Type: application/json");
            echo json_encode($data, JSON_PRETTY_PRINT);
        }
        exit;
    }
}

?>
