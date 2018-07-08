<?php

class Geo {
    public static function getLocationString($lat, $lon, $precision = 4) {
        __require("i18n");

        if (is_string($lat)) $lat = floatval($lat);
        if (is_string($lon)) $lon = floatval($lon);
        $ns = "geo.direction.deg_north";
        $ew = "geo.direction.deg_east";
        if ($lat < 0) {
            $lat *= -1;
            $ns = "geo.direction.deg_south";
        }
        if ($lng < 0) {
            $lng *= -1;
            $ew = "geo.direction.deg_west";
        }
        return I18N::resolveArgs("geo.location.string", I18N::resolveArgs($ns, round($lat, $precision)), I18N::resolveArgs($ew, round($lon, $precision)));
    }

    public static function listPOIs() {
        __require("db");

        $db = Database::getSparrow();
        return $db
            ->from(Database::getTable("poi"))
            ->leftJoin(Database::getTable("user"), array(Database::getTable("poi").".created_by" => Database::getTable("user").".id"))
            ->select(array(
                Database::getTable("poi").".*",
                Database::getTable("user").".nick"
              ))
            ->many();
    }
}

?>
