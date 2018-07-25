<?php

class Geo {
    public static function getLocationString($lat, $lon, $precision = 5) {
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

    public static function getPOI($id) {
        __require("db");

        $db = Database::getSparrow();
        $poi = $db
            ->from(Database::getTable("poi"))

            // User who created POI
            ->leftJoin(Database::getTable("user c_user"), array(Database::getTable("poi").".created_by" => "c_user.id"))
            ->leftJoin(Database::getTable("group c_group"), array("c_user.permission" => "c_group.level"))
            // User who last updated POI
            ->leftJoin(Database::getTable("user u_user"), array(Database::getTable("poi").".updated_by" => "u_user.id"))
            ->leftJoin(Database::getTable("group u_group"), array("u_user.permission" => "u_group.level"))

            ->where(Database::getTable("poi").".id", $id)
            ->select(array(
                Database::getTable("poi").".*",
                "c_user.provider_id creator_provider_id",
                "c_user.nick creator_nick",
                "c_group.color creator_color",
                "u_user.provider_id updater_provider_id",
                "u_user.nick updater_nick",
                "u_group.color updater_color"
              ))
            ->one();

        return new POI($poi);
    }

    public static function listPOIs() {
        __require("db");

        $db = Database::getSparrow();
        $pois = $db
            ->from(Database::getTable("poi"))

            // User who created POI
            ->leftJoin(Database::getTable("user c_user"), array(Database::getTable("poi").".created_by" => "c_user.id"))
            ->leftJoin(Database::getTable("group c_group"), array("c_user.permission" => "c_group.level"))
            // User who last updated POI
            ->leftJoin(Database::getTable("user u_user"), array(Database::getTable("poi").".updated_by" => "u_user.id"))
            ->leftJoin(Database::getTable("group u_group"), array("u_user.permission" => "u_group.level"))

            ->select(array(
                Database::getTable("poi").".*",
                "c_user.provider_id creator_provider_id",
                "c_user.nick creator_nick",
                "c_group.color creator_color",
                "u_user.provider_id updater_provider_id",
                "u_user.nick updater_nick",
                "u_group.color updater_color"
              ))
            ->many();

        $poilist = array();
        foreach ($pois as $poi) {
            $poilist[] = new POI($poi);
        }
        return $poilist;
    }

    public static function wasPoiUpdatedToday($date) {
        return date("Y-m-d", strtotime($date)) === date("Y-m-d");
    }

    public static function isWithinGeofence($geofence, $lat, $lon) {
        if ($geofence === null) return true;

        $xVertices = array();
        $yVertices = array();
        $inside = false;
        $count = count($geofence);
        foreach ($geofence as $point) {
            $xVertices[] = $point[0];
            $yVertices[] = $point[1];
        }
        for ($cur = 0, $prev = $count - 1; $cur < $count; $prev = $cur++) {
            if ($yVertices[$cur] > $lon != ($yVertices[$prev] > $lon)) {
                if (($lat < ($xVertices[$prev] - $xVertices[$cur]) * ($lon - $yVertices[$cur]) / ($yVertices[$prev] - $yVertices[$cur]) + $xVertices[$cur])) {
                    $inside = !$inside;
                }
            }
        }
        return $inside;
    }
}

class POI {
    private $data = null;

    function __construct($poidata) {
        $this->data = $poidata;
    }

    public function getID() {
        return $this->data["id"];
    }

    public function getName() {
        return $this->data["name"];
    }

    public function getLatitude() {
        return floatval($this->data["latitude"]);
    }

    public function getLongitude() {
        return floatval($this->data["longitude"]);
    }

    public function isWithinGeofence($geofence) {
        return Geo::isWithinGeofence($geofence, $this->getLatitude(), $this->getLongitude());
    }

    public function getLastUpdatedString() {
        return $this->data["last_updated"];
    }

    public function getLastUpdatedTime() {
        return strtotime($this->data["last_updated"]);
    }

    public function getTimeCreatedString() {
        return $this->data["created_on"];
    }

    public function getTimeCreated() {
        return strtotime($this->data["created_on"]);
    }

    public function isUpdatedToday() {
        return date("Y-m-d", $this->getLastUpdatedTime()) == date("Y-m-d");
    }

    public function getLastObjective() {
        return array(
            "type" => $this->data["objective"],
            "params" => json_decode($this->data["obj_params"])
        );
    }

    public function getCurrentObjective() {
        if ($this->isUpdatedToday()) {
            return $this->getLastObjective();
        } else {
            return array(
                "type" => "unknown",
                "params" => array()
            );
        }
    }

    public function isObjectiveUnknown() {
        return $this->getCurrentObjective()["type"] === "unknown";
    }

    public function getLastReward() {
        return array(
            "type" => $this->data["reward"],
            "params" => json_decode($this->data["rew_params"])
        );
    }

    public function getCurrentReward() {
        if ($this->isUpdatedToday()) {
            return $this->getLastReward();
        } else {
            return array(
                "type" => "unknown",
                "params" => array()
            );
        }
    }

    public function isRewardUnknown() {
        return $this->getCurrentReward()["type"] === "unknown";
    }

    public function isResearchUnknown() {
        return $this->isObjectiveUnknown() && $this->isRewardUnknown();
    }

    public function getCreator() {
        __require("auth");
        return new User(array(
            "id" => $this->data["created_by"],
            "nick" => $this->data["creator_nick"],
            "color" => $this->data["creator_color"],
            "provider_id" => $this->data["creator_provider_id"]
        ));
    }

    public function getLastUser() {
        __require("auth");
        return new User(array(
            "id" => $this->data["updated_by"],
            "nick" => $this->data["updater_nick"],
            "color" => $this->data["updater_color"],
            "provider_id" => $this->data["updater_provider_id"]
        ));
    }
}

?>
