<?php
/*
    This library files contains geo-, navigation-, and POI-related functions.
*/

class Geo {
    /*
        This variable is a cache for available navigation providers.
    */
    private static $navigationProviders = null;

    /*
        Returns an array of all registered navigation providers. This is an
        array of the following structure:

            listNavigationProviders() == array(
                "prov1_name" => "https://go.example.com/?lat={%LAT%}&lon={%LON%}",
                "prov2_name" => "https://navigate.example.net/directions".
                                "?to={%LAT%},{%LON%}&targetName={%NAME%}"
            );

        The URLs in each value may have any of the following placeholders:

            {%LAT%} - Target latitude
            {%LON%} - Target longitude
            {%NAME%} - Name of the target location

        The purpose of this function is to centralize navigation providers to a
        single place in the code. Each provider offers a URL that, when
        placeholders are replaced, offers turn-based navigation to the given
        location.

        The URLs are used in various locations, such as when clicking on the
        "Directions" button on the POI details overlay on the map, when posting
        webhook messages with navigation URLs, and when clicking on the link to
        a POI's location in the list of POIs on the administration pages.

        Navigation providers are defined in /includes/data/navigators.ini.
    */
    public static function listNavigationProviders() {
        /*
            The navigation providers array is cached in memory to prevent
            unnecessary file reads when accessing this function multiple times
            in the same script.
        */
        if (self::$navigationProviders === null) {
            self::$navigationProviders = parse_ini_file(__DIR__."/../data/navigators.ini", true)["navigators"];
        }

        return self::$navigationProviders;
    }

    /*
        Converts a coordinate pair to a coordinate string in DD format. E.g.

            Geo::getLocationString(42.63445, -87.12012)
            ->  "42.63445°N, 87.12012°E"

        `$precision` is an optional parameter for specifying the desired
        precision in number of decimal digits.
    */
    public static function getLocationString($lat, $lon, $precision = 5) {
        __require("i18n");

        /*
            `$lat` and `$lon` may be passed either as strings or as floating-
            point numbers. If they were passed as the former, convert them to
            the latter.
        */
        if (is_string($lat)) $lat = floatval($lat);
        if (is_string($lon)) $lon = floatval($lon);

        /*
            `$ns` is the I18N token to use for latitude. For positive
            coordinates, this is the I18N token that corresponds to North. For
            negative ones, it is the token that corresponds to South. These
            tokens are resolved with the absolute value of the coordinates to
            ensure that coordinates are displayed as e.g. "87°E" rather than
            "-87°W".

            The same applies for `$ew`, the longitude I18N token.
        */
        $ns = "geo.direction.deg_north";
        $ew = "geo.direction.deg_east";
        if ($lat < 0) {
            $lat *= -1;
            $ns = "geo.direction.deg_south";
        }
        if ($lon < 0) {
            $lon *= -1;
            $ew = "geo.direction.deg_west";
        }

        return I18N::resolveArgs(
            "geo.location.string",
            I18N::resolveArgs($ns, round($lat, $precision)),
            I18N::resolveArgs($ew, round($lon, $precision))
        );
    }

    /*
        Returns a `POI` instance for the given POI, looked up by POI ID.
    */
    public static function getPOI($id) {
        __require("db");

        $db = Database::connect();
        $poi = $db
            /*
                Query the list of POIs
            */
            ->from("poi")

            /*
                Include the user who created POI

                Join table (`ffield_user` and load alias `c_user`) for the user
                who created the POI (`ffield_poi.created_by` matches
                `c_user.id`)

                Join table (`ffield_group` and load alias `c_group`) for the
                group membership of the user who created the POI
                (`c_user.permission` matches `c_group.level`)
            */
            ->leftJoin("user c_user", array(
                "poi.created_by" => "~c_user.id"
            ))
            ->leftJoin("group c_group", array(
                "~c_user.permission" => "~c_group.level"
            ))

            /*
                Do the same for the user who last updated the POI, with "u_"
                aliases for "updated" rather than "c_" for "created"
            */
            ->leftJoin("user u_user", array(
                "poi.updated_by" => "~u_user.id"
            ))
            ->leftJoin("group u_group", array(
                "~u_user.permission" => "~u_group.level"
            ))

            /*
                Pick the POI that matches the ID passed to the function
            */
            ->where(Database::getTable("poi").".id", $id)

            ->select(array(
                /*
                    All POI data
                */
                Database::getTable("poi").".*",
                /*
                    Provider identity of the user who created the POI
                    Alias `creator_provider_id`

                    Nickname of the user who created the POI
                    Alias `creator_nick`

                    Display color of the group that the creator is in
                    Alias `creator_color`
                */
                "c_user.provider_id creator_provider_id",
                "c_user.nick creator_nick",
                "c_group.color creator_color",
                /*
                    Provider identity of the user who last updated the POI
                    Alias `updater_provider_id`

                    Nickname of the user who last updated the POI
                    Alias `updater_nick`

                    Display color of the group that the last updater is in
                    Alias `updater_color`
                */
                "u_user.provider_id updater_provider_id",
                "u_user.nick updater_nick",
                "u_group.color updater_color"
              ))

            /*
                The array has the following keys:

                    array_keys($poi) == array(
                        "id", "name", "latitude", "Longitude", "created_on",
                        "created_by", "last_updated", "updated_by", "objective",
                        "obj_params", "reward", "rew_params",
                        "creator_provider_id", "creator_nick", "creator_color",
                        "updater_provider_id", "updater_nick", "updater_color"
                    );

                Please see /includes/lib/db.php for a list of what each of the
                keys on the first three lines above represent.
            */
            ->one();

        return new POI($poi);
    }

    /*
        Returns an array of `POI` instances representing each POI in the
        database.
    */
    public static function listPOIs() {
        __require("db");

        $db = Database::connect();
        $pois = $db
            /*
                Query the list of POIs
            */
            ->from("poi")

            /*
                Include the users who created POIs

                Join table (`ffield_user` and load alias `c_user`) for the users
                who created the POIs (`ffield_poi.created_by` matches
                `c_user.id`)

                Join table (`ffield_group` and load alias `c_group`) for the
                group memberships of the users who created the POIs
                (`c_user.permission` matches `c_group.level`)
            */
            ->leftJoin("user c_user", array(
                "poi.created_by" => "~c_user.id"
            ))
            ->leftJoin("group c_group", array(
                "~c_user.permission" => "~c_group.level"
            ))

            /*
                Do the same for the users who last updated the POI, with "u_"
                aliases for "updated" rather than "c_" for "created"
            */
            ->leftJoin("user u_user", array(
                "poi.updated_by" => "~u_user.id"
            ))
            ->leftJoin("group u_group", array(
                "~u_user.permission" => "~u_group.level"
            ))

            ->select(array(
                /*
                    All POI data
                */
                Database::getTable("poi").".*",
                /*
                    Provider identities of the users who created the POIs
                    Alias `creator_provider_id`

                    Nicknames of the users who created the POI
                    Alias `creator_nick`

                    Display colors of the groups that the creators are in
                    Alias `creator_color`
                */
                "c_user.provider_id creator_provider_id",
                "c_user.nick creator_nick",
                "c_group.color creator_color",
                /*
                    Provider identities of the users who last updated the POIs
                    Alias `updater_provider_id`

                    Nicknames of the users who last updated the POIs
                    Alias `updater_nick`

                    Display colors of the groups that the last updaters are in
                    Alias `updater_color`
                */
                "u_user.provider_id updater_provider_id",
                "u_user.nick updater_nick",
                "u_group.color updater_color"
              ))

            /*
                The array entries have the following keys:

                    array_keys($pois[0]) == array(
                        "id", "name", "latitude", "Longitude", "created_on",
                        "created_by", "last_updated", "updated_by", "objective",
                        "obj_params", "reward", "rew_params",
                        "creator_provider_id", "creator_nick", "creator_color",
                        "updater_provider_id", "updater_nick", "updater_color"
                    );

                Please see /includes/lib/db.php for a list of what each of the
                keys on the first three lines above represent.
            */
            ->many();

        $poilist = array();
        foreach ($pois as $poi) {
            $poilist[] = new POI($poi);
        }
        return $poilist;
    }

    /*
        Returns a `Geofence` instance for the given geofence, looked up by
        geofence ID.
    */
    public static function getGeofence($id) {
        /*
            Get geofence arrays from the configuration file.
        */
        $fences = Config::getRaw("geofences");
        if ($fences === null) $fences = array();

        /*
            Search the list of arrays for a geofence that has a matching ID.
        */
        foreach ($fences as $fence) {
            if ($fence["id"] == $id) return new Geofence($fence);
        }

        /*
            Return `null` if none were found.
        */
        return null;
    }

    /*
        Writes a `Geofence` instance to the configuration file. If a geofence
        with the same ID as the one passed to this function already exists, it
        is overwritten.
    */
    public static function storeGeofence($fenceObject) {
        /*
            Get the array that should be written to the configuration file.
        */
        $fenceArray = $fenceObject->toArray();

        /*
            Get a list of all geofences in the configuration file.
        */
        $fences = Config::getRaw("geofences");
        if ($fences === null) $fences = array();

        /*
            Search the list of arrays for a geofence that has a matching ID.
        */
        $overwrite = false;
        for ($i = 0; $i < count($fences); $i++) {
            if ($fences[$i]["id"] === $fenceObject->getID()) {
                /*
                    Overwrite the geofence.
                */
                $overwrite = true;
                $fences[$i] = $fenceArray;
            }
        }

        /*
            If no entry was overwritten, that means the geofence is new. Append
            it to the array instead.
        */
        if (!$overwrite) $fences[] = $fenceArray;

        /*
            Save the geofences array to the configuration file.
        */
        Config::set(array("geofences" => $fences));
    }

    /*
        Returns an array of `Geofence` instances representing each geofence in
        the configuration file.
    */
    public static function listGeofences() {
        /*
            Get geofence arrays from the configuration file.
        */
        $fences = Config::getRaw("geofences");
        if ($fences === null) $fences = array();

        /*
            Convert each array to a `Geofence` instance and return the instances
            as an array.
        */
        $instances = array();
        foreach ($fences as $fence) {
            $instances[] = new Geofence($fence);
        }

        return $instances;
    }

    /*
        Checks if the last time the research task was updated on the POI was
        today. This is done by converting the date in the database (which is in
        "Y-m-d H:i:s" format), and the current time, to "Y-m-d" format, to get
        the current date for both objects. If they mismatch, they're not the
        same date. Since the date in the database should never be in the future,
        we can assume that a discrepancy will always mean the database time is
        in the past. Comparing date strings is much easier than trying to work
        around UNIX timestamps.
    */
    public static function wasPoiUpdatedToday($date) {
        /*
            Set correct timezone to ensure proper `date()` functionality.
        */
        __require("config");
        date_default_timezone_set(Config::get("map/updates/tz")->value());
        return date("Y-m-d", strtotime($date)) === date("Y-m-d");
    }
}

/*
    This class is used to extract information about specific POIs. An instance
    can be obtained by POI ID from `Geo::getPOI($id)` or a complete list of all
    POIs via `Geo::listPOIs()`.
*/
class POI {
    /*
        The array entries have the following keys:

            array_keys($data) == array(
                "id", "name", "latitude", "Longitude", "created_on",
                "created_by", "last_updated", "updated_by", "objective",
                "obj_params", "reward", "rew_params",
                "creator_provider_id", "creator_nick", "creator_color",
                "updater_provider_id", "updater_nick", "updater_color"
            );

        Please see /includes/lib/db.php for a list of what each of the keys on
        the first three lines above represent. The rest is explained in
        `Geo::getPOI()`.
    */
    private $data = null;

    function __construct($poidata) {
        $this->data = $poidata;
    }

    /*
        Returns the ID of this POI used to uniquely identify the POI in the
        database.
    */
    public function getID() {
        return $this->data["id"];
    }

    /*
        Returns the name displayed on the POI. This function is not HTML safe.
    */
    public function getName() {
        return $this->data["name"];
    }

    /*
        An HTML safe version of `getName()`. Special HTML characters are escaped
        to prevent XSS attacks.
    */
    public function getNameHTML() {
        return htmlspecialchars($this->getName(), ENT_QUOTES);
    }

    /*
        Returns the latitude coordinate component for this POI.
    */
    public function getLatitude() {
        return floatval($this->data["latitude"]);
    }

    /*
        Returns the longitude coordinate component for this POI.
    */
    public function getLongitude() {
        return floatval($this->data["longitude"]);
    }

    /*
        Checks if the POI is within the given geofence.

        $geofence
            A `Geofence` instance.
    */
    public function isWithinGeofence($geofence) {
        if ($geofence === null) return true;
        return $geofence->containsPoint($this->getLatitude(), $this->getLongitude());
    }

    /*
        Returns a string representation of the last time research was reported
        on this POI.
    */
    public function getLastUpdatedString() {
        return $this->data["last_updated"];
    }

    /*
        Returns a UNIX timestamp representation of the last time research was
        reported on this POI.
    */
    public function getLastUpdatedTime() {
        return strtotime($this->data["last_updated"]);
    }

    /*
        Returns a string representation of the time this POI was initially added
        to the local POI database.
    */
    public function getTimeCreatedString() {
        return $this->data["created_on"];
    }

    /*
        Returns a UNIX timestamp representation of the time this POI was
        initially added to the local POI database.
    */
    public function getTimeCreated() {
        return strtotime($this->data["created_on"]);
    }

    /*
        Checks whether the last time research was reported on this POI was
        today.
    */
    public function isUpdatedToday() {
        return Geo::wasPoiUpdatedToday($this->getLastUpdatedString());
    }

    /*
        Returns the last known research objective on this POI. This may not
        necessarily be current. To get the current research objective, use
        `getCurrentObjective()`.
    */
    public function getLastObjective() {
        return array(
            "type" => $this->data["objective"],
            "params" => json_decode($this->data["obj_params"], true)
        );
    }

    /*
        Returns the current research objective active on the POI, if any.
    */
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

    /*
        Checks whether there is any known research objective currrently active
        on the POI.
    */
    public function isObjectiveUnknown() {
        return $this->getCurrentObjective()["type"] === "unknown";
    }

    /*
        Returns the last known research reward on this POI. This may not
        necessarily be current. To get the current research reward, use
        `getCurrentReward()`.
    */
    public function getLastReward() {
        return array(
            "type" => $this->data["reward"],
            "params" => json_decode($this->data["rew_params"])
        );
    }

    /*
        Returns the current research reward active on the POI, if any.
    */
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

    /*
        Checks whether there is any known research reward currrently active on
        the POI.
    */
    public function isRewardUnknown() {
        return $this->getCurrentReward()["type"] === "unknown";
    }

    /*
        Checks whether there is any known research objective and/or reward
        currently active on the POI.
    */
    public function isResearchUnknown() {
        return $this->isObjectiveUnknown() && $this->isRewardUnknown();
    }

    /*
        Returns a `User` instance representing the user who added this POI to
        the local POI database. The `User` instance returned can only be used to
        get the username, nickname, provider identity, color and ID of the user,
        and can not be used for group management or permission purposes.
    */
    public function getCreator() {
        __require("auth");
        /*
            This object will only be used for displaying information about the
            identity of the user, so querying the group to get permission/group
            information for the user is not necessary.
        */
        return new User(array(
            "id" => $this->data["created_by"],
            "nick" => $this->data["creator_nick"],
            "color" => $this->data["creator_color"],
            "provider_id" => $this->data["creator_provider_id"]
        ));
    }

    /*
        Returns a `User` instance representing the user who last reported
        research on this POI. The `User` instance returned can only be used to
        get the username, nickname, provider identity, color and ID of the user,
        and can not be used for group management or permission purposes.
    */
    public function getLastUser() {
        __require("auth");
        /*
            This object will only be used for displaying information about the
            identity of the user, so querying the group to get permission/group
            information for the user is not necessary.
        */
        return new User(array(
            "id" => $this->data["updated_by"],
            "nick" => $this->data["updater_nick"],
            "color" => $this->data["updater_color"],
            "provider_id" => $this->data["updater_provider_id"]
        ));
    }
}

/*
    This class is used to extract information about geofences. An instance can
    be obtained by geofence ID from `Geo::getGeofence($id)` or a complete list
    of all geofences via `Geo::listGeofences()`.
*/
class Geofence {
    /*
        The array havs the following keys:

        id
            A unique geofence identifier.

        label
            The label of the geofence as decided by the user.

        vertices
            The array of [LAT,LON] coordinate pairs that make up the geofence.
            Example vertices array:

            $data["vertices"] = array(
                [42.87442, -87.41975],
                [42.78579, -86.59578],
                [42.19848, -86.81001],
                [42.24322, -87.35933]
            );
    */
    private $data = null;

    function __construct($fencedata) {
        $this->data = $fencedata;
    }

    /*
        Returns the underlying data array of this geofence.
    */
    public function toArray() {
        return $this->data;
    }

    /*
        Retrieves the ID of the geofence.
    */
    public function getID() {
        if ($this->data === null) return null;
        return $this->data["id"];
    }

    /*
        Retrieves the user-defined name of the geofence.
    */
    public function getLabel() {
        if ($this->data === null) return null;
        return $this->data["label"];
    }

    /*
        Retrieves the list of this geofence's constituent vertices.
    */
    public function getVertices() {
        if ($this->data === null) return null;
        return $this->data["vertices"];
    }

    /*
        Retrieves this geofence's constituent vertices as a string.
    */
    public function getVerticesAsString() {
        if ($this->data === null) return null;
        $string = "";
        foreach ($this->data["vertices"] as $vertex) {
            /*
                $vertex is an array where:
                    0 => Latitude
                    1 => Longitude
            */
            $string .= $vertex[0].",".$vertex[1]."\n";
        }
        return trim($string);
    }

    /*
        Parses a string of vertices to an array of coordinate pairs. Returns
        `null` if the input data is invalid.
    */
    public static function parseVerticesString($data) {
        if ($data === "") return array();
        $vertices = array();

        /*
            Split the input data into lines. Split by any combination of line
            feed and carriage return.
        */
        $lines = preg_split('/\r\n?|\n\r?/', $data);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === "") continue;

            /*
                Convert all coordinates (comma-delimited) to floating point
                values.
            */
            $point = explode(",", $line);
            $entry = array();
            foreach ($point as $dimension) {
                $entry[] = floatval($dimension);
            }
            $vertices[] = $entry;
        }

        /*
            A geofence must consist of at least three corners for them to form a
            two-dimensional surface area. Three points creates a triangle. Two
            would only be sufficient to draw a line.
        */
        if (count($vertices) < 3) return null;

        foreach ($vertices as $vertex) {
            /*
                Each point must consist of a coordinate pair. If the pair does
                not have exactly two elements coordinate axes, it is not a valid
                coordinate pair, and is thus invalid.
            */
            if (count($vertex) !== 2) return null;
            $lat = $vertex[0];
            $lon = $vertex[1];

            /*
                The coordinates must also be valid floating point values and be
                within the range of valid coordinates (-90 to 90 degrees
                latitude, and -180 to 180 degrees longitude).
            */
            if (!is_float($lat)) return null;
            if (!is_float($lon)) return null;
            if ($lat < -90 || $lat > 90) return null;
            if ($lon < -180 || $lon > 180) return null;
        }

        return $vertices;
    }

    public function isValid($data) {
        /*
            Null indicates that the geofence is disabled, and is thus valid.
        */
        if ($data === null) return true;

        if (!is_array($data)) return false;


        return true;
    }

    /*
        Checks whether the given coordinate is within the given geofence. This
        will not work properly for geofences which cross the 180th meridian due
        to the change from positive to negative longitudes. This shouldn't
        become a problem unless FreeField is ever used specifically in Taveuni
        Island, Fiji, or certain North-Eastern Siberian towns (which are near,
        or cross, the 180th meridian). If you encounter a realistic (and
        reasonable) scenario in which this issue prevents you from setting up
        FreeField properly, please raise an issue on the GitHub page referencing
        "Geofence::containsPoint()" and the 180th meridian and explain your
        scenario in detail.

        $geofence
            An array consisting of pairs of latitudes and longitudes in float
            format. I.e. this is a two-dimensional array, where the outer array
            is a list of arrays, each of which represent one pair of latitude
            and longitude.

        $lat and $lon
            The coordinates that should be checked to see if they're in bounds.
    */
    public function containsPoint($lat, $lon) {
        /*
            If the points list is null, there is no geofence set, and any
            coordinate pair should be assumed valid.
        */
        if (!isset($this->data["vertices"])) return true;

        /*
            The algorithm we're using is adapted from W. Randolph Franklin's
            Point Inclusion in Polygon Test code described here:
            https://wrf.ecse.rpi.edu//Research/Short_Notes/pnpoly.html

            The algorithm works by drawing a vertical ray straight out from the
            given latitude and longitude point that should be tested. If the
            point is inside the polygon, when starting at the point and moving
            along the vertical ray towards Y=Infinity, one has to cross the
            boundary of the polygon at some point, according to the Jordan curve
            theorem.

            The vertical ray may cross the boundary several times; into and out
            of the polygon. Hence, we count the number of boundary crossings. If
            the ray crosses an even number of times, then the starting point
            lies outside of the polygon. If we cross the ray an odd number of
            times, the starting point lies inside the polygon. The following
            illustrates this:

                +----------------------------------+
                | Starting point = S               |
                | Direction along vertical rat = v |
                | Polygon corner = +               |
                | Polygon boundary = -, |          |
                | Boundary crossing = O            |
                +----------------------------------+

                EXAMPLE 1:                          EXAMPLE 2:
                Zero crossings:                     One crossing:
                Starting point outside poly         Starting point inside poly

                                      S
                                      v
                +---------------+     v             +---------------+
                |               |     v             |               |
                |               |     v             |               |
                |      +--------+     v             |      +--------+
                |      |              v             |  S   |
                |      +              v             |  v   +
                |       \             v             |  v    \
                |        \            v             |  v     \
                |         \           v             |  v      \
                +----------+          v             +--O-------+
                                      v                v

                EXAMPLE 3:                          EXAMPLE 4:
                Two crossings:                      Three crossings:
                Starting point outside poly         Starting point inside poly

                             S
                             v
                +------------O--+                   +---------------+
                |            v  |                   |        S      |
                |            v  |                   |        v      |
                |      +-----O--+                   |      +-O------+
                |      |     v                      |      | v
                |      +     v                      |      + v
                |       \    v                      |       \v
                |        \   v                      |        O
                |         \  v                      |        v\
                +----------+ v                      +--------O-+
                             v                               v

            Since the state of whether the point is inside or outside the
            polygon changes with each boundary crossing, we'll declare a boolean
            variable that will be flipped at every border crossing. It starts at
            `false` to indicate the point being outside the polygon at zero
            boundary crossings. At one crossing, this will flip to `true`, and
            at two crossings (it leaves the polygon) it's reset to `false`, then
            `true` for three crossings, etc.
        */
        $inside = false;
        /*
            To count boundary crossings, we'll have to check whether the ray
            drawn vertically from the starting point crosses every segment of
            the boundary. To do this, we loop over two variables.

            The boundary of the polygon is a continuous loop. This means that
            every point defining the boundary has neighboring points in two
            directions. Since `$geofence` is an array containing all the points,
            we can loop over that array and refer to each point as an index,
            where the preceding and succeeding indices for any particular point
            represent the neighboring points for that point:

                0---------------1
                |               |
                |               |
                |      3--------2
                |      |
                |      4
                |       \
                |        \
                |         \
                6----------5

            We need to check whether the ray crosses each of the segments in
            that polygon, where each segment connects two points A and B. Hence
            we need to check:

                +--------+
                | A -> B |
                +--------+
                | 6 -> 0 |
                | 0 -> 1 |
                | 1 -> 2 |
                | 2 -> 3 |
                | 3 -> 4 |
                | 4 -> 5 |
                | 5 -> 6 |
                +--------+

            As can be seen from the table, A is always the point index
            immediately preceding the B index, such that for each next iteration
            of the loop over the segments, the new A index will become what was
            the B index in the previous iteration. We can therefore write a loop
            with two variables, `$a` and `$b`, where `$b` starts at index 0 and
            `$a` starts at the point immediately preceding `$a`, i.e. index 6,
            the last index of the array. The loop runs until all indices have
            been iterated over, and for each iteration, `$a` is set to the value
            of `$b`, and `$b` is then incremented by 1.
        */
        $count = count($this->data["vertices"]);
        for ($b = 0, $a = $count - 1; $b < $count; $a = $b++) {
            /*
                We now have two indices representing points A and B in the
                `$geofences` array. To improve the readability of the following
                code, we'll extract the latitudes and longitudes of points A
                and B to separate variables.
            */
            $aLat = $this->data["vertices"][$a][0];
            $aLon = $this->data["vertices"][$a][1];
            $bLat = $this->data["vertices"][$b][0];
            $bLon = $this->data["vertices"][$b][1];
            /*
                Since we're only counting boundary crossings, we can safely
                ignore any segments which we do not cross. This is done by
                checking whether both `$aLon` and `$bLon` are both on the same
                side of `$lon`. If they are not on the same side, the ray
                crosses the boundary.

                There may be cases where the infinite ray crosses directly
                through a point/vertex. To handle this, we use inequalities to
                shift the ray from the point an infinitesimal distance to the
                right of the vertex, so that the infinite ray either clearly
                intersects or does not intersect the segment. E.g. consider a
                segment from lon=0 to lon=10, with the infinite ray at lon=10:

                    EXAMPLE 1: Segment on the left side of the ray

                    $lon = 10;
                    $aLon = 0
                    $bLon = 10;
                    $aLon > $lon == false;
                    $bLon > $lon == false;

                The greater-than inequality in practice moves the ray
                infinitesimally to the right, putting both A and B to the left
                of the ray, meaning the ray is just out of range of crossing the
                segment. Now consider a segment from lon=10 to lon=20 for the
                same ray:

                    EXAMPLE 2: Segment on the right side of the ray

                    $lon = 10;
                    $aLon = 10;
                    $bLon = 20;
                    $aLon > $lon == false;
                    $bLon > $lon == true;

                The inequality puts point A to the left and point B to the right
                of the infinite ray, creating an intersection. This allows us to
                handle all cases of vertex pass-through:

                    EXAMPLE 1:                              \  v
                    Segment on both sides:                   \ v
                    There is one crossing                     +O---
                    Result: `$inside` toggles,                 v
                    indicating a state change                  v

                    EXAMPLE 2:                              \  v
                    Two segments on left side:               \ v
                    There are no crossings                    +v
                    Results: `$inside` is                    / v
                    never changed                           /  v

                    EXAMPLE 3:                                 v/
                    Two segments on right side:                O
                    There are two crossings                   +v
                    Results: `$inside` is toggled              O
                    twice, hence no overall changes            v\
            */
            if ($aLon > $lon != $bLon > $lon) {
                /*
                    At this point, we have successfully identified that there
                    has been a change of state for the point's presence in the
                    polygon. The ray moved either into or out of the polygon.
                    Now we have to ensure that boundary crossings are only
                    counted on one side of the point (i.e. a ray starting at
                    the point and moving downwards only, rather than a line
                    going both directions). If the ray was in fact a line going
                    both directions, boundary crossings would in fact be counted
                    as if the line passed through the whole polygon from the
                    outside, rather than starting as a ray on the inside. The
                    result would be that the point would wrongly be reported as
                    outside the polygon in all possible cases.

                    The simple way to check this is to check whether the
                    starting point is actually below the current segment. We
                    need to find the intersection latitude between the current
                    segment and the vertical ray from the starting point. Given
                    the longitudes of the point and the endpoints of the two
                    segments, we can calculate a fraction F between the
                    horizontal displacements `$lon` -> `$bLon` and
                    `$aLon` -> `$bLon`:

                                [S]    Segment
                                 v       |
                                 v       v  ____[B]
                                 v  ____----     |
                            ____-v--             |
                        [A]-     v               |
                         X-------X---------------X
                          \_____/ \_____________/
                            1-F          F

                    F is the fraction of how far away from B the intersection of
                    the vertical ray from S with the segment AB is, relative to
                    the distance between A and B, on the horizontal axis. Since
                    AB is linear, the same is also true for the vertical axis.
                    Hence, F multiplied with the vertical displacement between B
                    and A gives the vertical displacement of the intersection
                    point of the ray from S with segment AB relative to B. If we
                    add the vertical displacement between B and 0 (i.e. the
                    latitude of B) to this displacement, we get the displacement
                    of the intersection point relative to 0, i.e. the latitude
                    of the displacement point.

                    We can then check if this latitude is below the latitude of
                    the point we are verifying against the polygon (`$lat`). If
                    it is, then the ray would intersect it on the way downwards
                    towards negative infinity.
                */
                if ($lat > ($aLat - $bLat) * ($lon - $bLon) / ($aLon - $bLon) + $bLat) {
                    $inside = !$inside;
                }
            }
        }
        return $inside;
    }
}

?>
