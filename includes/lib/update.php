<?php
/*
    This library files contains self-updating functionality.
*/

__require("config");

class Update {

    const UPDATE_SEARCH_URL = "https://api.github.com/repos/bilde2910/FreeField/releases";
    const CURRENT_VERSION = FF_VERSION;
    const CACHE_PATH = __DIR__."/../userdata/updates-cache.json";
    const UPDATE_CHECKING_INTERVAL = 86400;

    /*
        This function checks whether or not the current FreeField installation
        was made by cloning from GitHub rather than installing via releases.
    */
    public static function isSelfClonedFromGit() {
        return file_exists(__DIR__."/../../.git");
    }

    /*
        This function reads the update cache file from disk. The cache file
        contains details about available releases, such as their version
        numbers, release notes and download URLs.
    */
    public static function getUpdateInfo() {
        if (!file_exists(self::CACHE_PATH)) return null;
        return json_decode(file_get_contents(self::CACHE_PATH), true);
    }

    /*
        This function retrieves the version information from the update info
        cache file for the given version. Returns `null` if the given version
        was not found.
    */
    public static function getVersionDataFor($version) {
        $cache = self::getUpdateInfo();
        if ($cache === null || !isset($cache["releases"])) return null;
        $releases = $cache["releases"];

        foreach ($releases as $release) {
            if ($release["version"] == $version) return $release;
        }

        return null;
    }

    /*
        This function returns a list of available releases with version numbers
        higher than the current installation.
    */
    public static function getUpdates() {
        $cache = self::getUpdateInfo();
        if ($cache === null || !isset($cache["releases"])) return array();
        $releases = $cache["releases"];

        $updates = array();
        foreach ($releases as $release) {
            if (version_compare($release["version"], self::CURRENT_VERSION) > 0) {
                $updates[] = $release;
            }
        }

        return $updates;
    }

    /*
        This function returns the latest available release for each release
        channel (dev, alpha, beta, rc and stable), if a newer version exists in
        each channel. It returns an array where each key is a channel, with
        values corresponding to the data of the releases. Channels may be
        missing from the output array if there is no version in a release
        channel that is newer than the currently installed version.
    */
    public static function getUpdatesByChannel() {
        $releases = self::getUpdates();

        /*
            Create an array `$rel` with information about the latest releases
            from each channel. It is initially filled with placeholder data.
        */
        $rel = array(
            "stable" => array("version" => "0"),
            "rc" => array("version" => "0"),
            "beta" => array("version" => "0"),
            "alpha" => array("version" => "0"),
            "dev" => array("version" => "0")
        );

        /*
            Put the newest release from each channel into the `$rel` array.
        */
        foreach ($releases as $release) {
            $type = self::getReleaseChannel($release["version"]);
            if (version_compare($release["version"], $rel[$type]["version"]) > 0) {
                $rel[$type] = $release;
            }
        }

        /*
            If any of the items in the `$rel` array are still placeholders, then
            no updates in the corresponding channels were found. Unset the
            channels with no updates from the `$rel` array.
        */
        foreach ($rel as $type => $release) {
            if ($rel[$type]["version"] === "0") unset($rel[$type]);
        }

        /*
            If a more stable release exists that is newer than another less
            stable release, then the less stable release should be removed,
            since a newer release exists in a more stable channel.
        */
        $relTypes = array_reverse(array_keys($rel));
        for ($i = 0; $i < count($relTypes); $i++) {
            $version = $rel[$relTypes[$i]]["version"];
            for ($j = $i + 1; $j < count($relTypes); $j++) {
                $compareVersion = $rel[$relTypes[$j]]["version"];
                if (version_compare($compareVersion, $version) >= 0) {
                    unset($rel[$relTypes[$i]]);
                    break;
                }
            }
        }

        return $rel;
    }

    /*
        Returns the release channel of the given release version.
    */
    public static function getReleaseChannel($version) {
        $parts = explode("-", $version);
        if (count($parts) > 1) {
            return strtok($parts[1], ".");
        }
        return "stable";
    }

    /*
        Forces a check for updates.
    */
    public static function checkForUpdates() {
        /*
            Check rate limits.
        */
        $ui = self::getUpdateInfo();
        if ($ui !== null) {
            if (
                isset($ui["rate-limit"]) &&
                $ui["rate-limit"]["reset"] <= time() &&
                $ui["rate-limit"]["remaining"] <= 0
            ) {
                return;
            }
        }

        try {
            /*
                Check the releases URL for updates and save rate limit
                restrictions.
            */
            __require("http");
            $ratelimit = array("reset" => time(), "remaining" => 0);
            $ch = curl_init(self::UPDATE_SEARCH_URL);
            HTTP::setOptions($ch);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                "Accept: application/vnd.github.v3+json"
            ));
            curl_setopt($ch, CURLOPT_HEADERFUNCTION,
                function($curl, $headLine) use (&$ratelimit) {
                    $headerLen = strlen($headLine);
                    $header = explode(":", $headLine, 2);
                    if (count($header) < 2) return $headerLen;
                    $k = strtolower(trim($header[0]));
                    $v = trim($header[1]);
                    switch ($k) {
                        case "x-ratelimit-reset":
                            $ratelimit["reset"] = intval($v);
                            break;
                        case "x-ratelimit-remaining":
                            $ratelimit["remaining"] = intval($v);
                            break;
                    }
                    return $headerLen;
                }
            );
            $data = json_decode(curl_exec($ch), true);

            if (curl_error($ch) || $data === null) return;
            curl_close($ch);

            /*
                Build an array of available releases to cache in the releases
                cache file.
            */
            $releases = array();
            foreach ($data as $release) {
                $versionNumber = $release["tag_name"];
                if (substr($versionNumber, 0, 1) == "v") {
                    $versionNumber = substr($versionNumber, 1);
                }
                $version = array(
                    "version" => $versionNumber,
                    "html-url" => $release["html_url"],
                    "tgz-url" => $release["tarball_url"],
                    "published" => strtotime($release["published_at"]),
                    "body" => $release["body"]
                );
                $releases[] = $version;
            }

            /*
                Create a data array to store in the releases cache file, and
                save the data to the file.
            */
            $updates = array(
                "releases" => $releases,
                "rate-limit" => $ratelimit
            );
            file_put_contents(
                self::CACHE_PATH,
                json_encode($updates, JSON_PRETTY_PRINT)
            );

            /*
                Update the update check timestamps in the configuration file, as
                well as the state of whether or not a stable update is
                available.
            */
            $channeles = self::getUpdatesByChannel();
            Config::set(array("install/update-check" => array(
                "last-check" => time(),
                "next-check" => time() + self::UPDATE_CHECKING_INTERVAL,
                "update-found" => isset($channeles["stable"])
            )));
        } catch (Exception $e) {
            return;
        }
    }

    /*
        Determines whether or not a periodic update check is due, and performs
        an update check if that is the case.
    */
    public static function autoCheckForUpdates() {
        $plannedCheck = Config::getRaw("install/update-check/next-check");
        if ($plannedCheck <= time()) {
            self::checkForUpdates();
        }
    }

    /*
        Checks the update check cache to determine whether an update was
        available at the time of the last update check.
    */
    public static function autoIsUpdateAvailable() {
        return Config::getRaw("install/update-check/update-found");
    }
}

?>
