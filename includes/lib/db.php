<?php

__require("config");
__require("vendor/sparrow");

class Database {
    public static function getSparrow() {
        $db = new Sparrow();

        if (Config::get("database/type") == "sqlite" || Config::get("database/type") == "sqlite3") {
            $db->setDb(Config::get("database/type")."://".Config::get("database/database"));
        } else {
            $type = Config::get("database/type");
            $host = Config::get("database/host");
            $database = Config::get("database/database");
            $user = Config::get("database/username");
            $pass = Config::get("database/password");

            if (Config::get("database/port") > 0) {
                $port = Config::get("database/port");
                $uri = "{$type}://{$user}:{$pass}@{$host}:{$port}/{$database}";
            } else {
                $uri = "{$type}://{$user}:{$pass}@{$host}/{$database}";
            }

            error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);

            $db->setDb($uri);
        }

        return $db;
    }

    public static function getTable($table) {
        return Config::get("database/table-prefix").$table;
    }
}

?>
