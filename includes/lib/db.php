<?php

__require("config");
__require("vendor/sparrow");

class Database {
    public static function getSparrow() {
        $db = new Sparrow();

        if (Config::get("database/type") == "sqlite" || Config::get("database/type") == "sqlite3") {
            $db->setDb(Config::get("database/type")."://".Config::get("database/database"));
        } else {
            $connarray = array(
                "type" => Config::get("database/type"),
                "hostname" => Config::get("database/hostname"),
                "database" => Config::get("database/database"),
                "username" => Config::get("database/username"),
                "password" => Config::get("database/password"),
            );
            
            if (Config::get("database/port") > 0)
                $connarray["port"] = Config::get("database/port");
            
            $db->setDb($connarray);
        }
        
        return $db;
    }
}

?>
