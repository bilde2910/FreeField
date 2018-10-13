<?php
/*
    This script performs update installation. It is called after
    /admin/apply-updates.php.

    THIS SCRIPT MAY BREAK FREEFIELD IF IT FAILS.
*/

$returnpath = "./?d=updates";
$basepath = dirname(__DIR__);
$pkgMetaPath = "{$basepath}/includes/userdata/updatepkg.json";
$pkgBasePath = "{$basepath}/includes/userdata/tmp-update";
$pkgExtractPath = "{$pkgBasePath}/content";
$pkgFilePath = "{$pkgBasePath}/updatepkg.tar.gz";
$pkgTarPath = "{$pkgBasePath}/updatepkg.tar";
$pkgAuthCookie = "update-token";

/*
    As this script is for submission only, only POST is supported. If a user
    tries to GET this page, they should be redirected to the configuration UI
    where they can start the update process.
*/
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("HTTP/1.1 303 See Other");
    header("Location: {$returnpath}");
    exit;
}

/*
    Check for the presence of the user authentication token. This token is
    generated and set as a cookie in the /admin/apply-updates.php file to
    authenticate the current user, and also stored in the updatepkg.json file.
    Matching these two allows us to authenticate the current user without having
    to include the authentication and database libraries and querying the user
    there.
*/
if (!isset($_COOKIE[$pkgAuthCookie])) {
    header("HTTP/1.1 303 See Other");
    header("Location: {$returnpath}");
    exit;
}

/*
    Ensure that the user has chosen an update and that they assume
    responsibility for performing it.
*/
if (!isset($_POST["to-version"]) || !isset($_POST["accepted-disclaimer"])) {
    header("HTTP/1.1 303 See Other");
    header("Location: {$returnpath}");
    exit;
}

/*
    Check that an update definitions file exists.
*/
if (!file_exists($pkgMetaPath)) {
    header("HTTP/1.1 303 See Other");
    header("Location: {$returnpath}");
    exit;
}

/*
    Attempt to read the file.
*/
$updateData = json_decode(file_get_contents($pkgMetaPath), true);
if ($updateData === null) {
    header("HTTP/1.1 303 See Other");
    header("Location: {$returnpath}");
    exit;
}

/*
    Check that the user is authorized to perform the update.
*/
if ($_COOKIE[$pkgAuthCookie] !== $updateData["token"]) {
    header("HTTP/1.1 303 See Other");
    header("Location: {$returnpath}");
    exit;
}

/*
    Proceed with the upgrade.
*/

/*
    Disable output buffering completely to allow the client browser to see a
    live update feed of installation progress.
*/
ob_implicit_flush();
set_time_limit(0);
while (ob_get_level()) ob_end_flush();

echo '<h1>Upgrading FreeField...</h1>';
echo '<p>Please wait - this may take a while. Do not close this page.</p>';
echo '<pre>';

/*
    This function is the progress handler assigned to the downloader cURL
    implementation.
*/
function progressUpdate($resource, $dSize, $cdSize, $uSize, $cuSize) {
    global $lastUpdate;
    if (time() <= $lastUpdate) return;
    $lastUpdate = time();
    $humanSize = sizeToHuman($cdSize);
    echo "Downloading update... ({$humanSize})\n";
}

/*
    This function writes data received from the update package download stream
    to a local file.
*/
function writeFile($cp, $data) {
    global $fh;
    return fwrite($fh, $data);
}

/*
    This function converts a filesize in bytes to a human readable string.
*/
function sizeToHuman($size) {
    $units = array("B", "KiB", "MiB", "GiB");
    $unitIndex = 0;
    /*
        Divide the size by 1024 as many times as possible, incrementing the size
        unit by one every time.
    */
    while (round($size) >= 1024 && $unitIndex < count($units) - 1) {
        $size /= 1024;
        $unitIndex++;
    }

    /*
        Convert the size to a string with two decimal points.
    */
    $size = number_format($size, 2);

    return "{$size} ".$units[$unitIndex];
}

/*
    This function will recursively delete a directory.
*/
function recursivelyDelete($path) {
    if (is_dir($path)) {
        /*
            If the given path is a directory, search for sub-directories and
            files. Delete every file and directory found.
        */
        $files = scandir($path);
        foreach ($files as $file) {
            if ($file != "." && $file != "..") {
                recursivelyDelete("{$path}/{$file}");
            }
        }
        /*
            Delete the directory itself after all of its contents are gone.
        */
        rmdir($path);
    } else {
        /*
            If the given path is a file, we can safely unlink it.
        */
        unlink($path);
    }
}

/*
    This function will move an entire directory and its contents.
*/
function recursivelyMove($src, $dst) {
    if (is_dir($src)) {
        /*
            If the given source path is a directory, create the corresponding
            target directory and search the source directory for sub-directories
            and files. Move every file and directory from the source to the
            target.
        */
        if (!file_exists($dst)) mkdir($dst);
        $files = scandir($src);
        foreach ($files as $file) {
            if ($file != "." && $file != "..") {
                recursivelyMove("{$src}/{$file}", "{$dst}/{$file}");
            }
        }
        /*
            After all of its contents have been moved, we can delete the source
            directory as it is now empty.
        */
        rmdir($src);
    } else {
        /*
            If the given path is a file, we can `rename()` it to move it to the
            new location.
        */
        rename($src, $dst);
    }
}

/*
    This function returns the full path of the /includes/setup directory within
    the given directory `$path`. If no /includes/setup path is found, this
    function returns null. This would happen if the downloaded archive is not a
    FreeField code repository.
*/
function findSetupDir($path) {
    if (is_dir($path)) {
        /*
            Search the directory for any sub-directories named "includes". The
            "includes" folder should contain the "setup" directory.
        */
        $files = scandir($path);
        foreach ($files as $file) {
            $filePath = "{$path}/{$file}";
            /*
                If the "includes/setup" directory is found, return its path.
            */
            if (
                $file == "includes" &&
                is_dir($filePath) &&
                file_exists("{$filePath}/setup")
            ) {
                return "{$filePath}/setup";
            }
        }
        /*
            If "includes/setup" was not found in this directory, try searching
            sub-directories.
        */
        foreach ($files as $file) {
            if ($file != "." && $file != "..") {
                $filePath = "{$path}/{$file}";
                $ret = findSetupDir($filePath);
                /*
                    If a value was returned, then the path was found. Cascade
                    the return value back up to the caller of this function.
                */
                if ($ret !== null) return $ret;
            }
        }
    }
    return null;
}

/*
    Commence the upgrade.
*/

try {
    /*
        Delete the updatepkg.json file right away. It has been read to memory
        anyway and thus isn't needed anymore.
    */
    echo "Deleting temporary updatepkg.json...";
    unlink($pkgMetaPath);

    /*
        The FreeField root directory must be writable, as FreeField will delete
        every file in this directory, save for the /includes/userdata folder, in
        order to perform the update.
    */
    echo " done\nEnsuring FreeField root is writable...";
    if (!is_writable($basepath)) {
        echo " fail\n";
        throw new Exception(
            "FreeField root directory is not writable!"
        );
    }

    /*
        If the /includes/userdata/tmp-update directory already exists, delete
        it. If it exists, it's remnants from a previous upgrade that was not
        cleaned up.
    */
    if (file_exists($pkgBasePath)) {
        echo " ok\nCleaning up previous upgrade attempt...";
        recursivelyDelete($pkgBasePath);
    }

    /*
        Prepare to download the update tarball. Open a file handler at the
        location we will be storing the update package.
    */
    echo " ok\nOpening file handler...";
    mkdir($pkgBasePath);
    $fh = fopen($pkgFilePath, "w+");

    /*
        Download the tarball itself.
    */
    echo " done\nPreparing to download update...\n";
    mkdir($pkgExtractPath);
    $curl = curl_init();
    $lastUpdate = time();
    curl_setopt($curl, CURLOPT_URL, $updateData["tarball"]);
    curl_setopt($curl, CURLOPT_FILE, $fh);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_USERAGENT, "User-Agent: FreeField-Updater PHP/".phpversion());
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_BINARYTRANSFER, true);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 900);
    curl_setopt($curl, CURLOPT_PROGRESSFUNCTION, "progressUpdate");
    curl_setopt($curl, CURLOPT_NOPROGRESS, false);
    curl_setopt($curl, CURLOPT_WRITEFUNCTION, "writeFile");
    curl_exec($curl);
    curl_close($curl);
    fclose($fh);
    $size = sizeToHuman(filesize($pkgFilePath));
    echo "Download completed (downloaded {$size}).\n";

    /*
        The downloaded file is a gzipped tarball. Decompress it first, then
        extract it using `PharData`.
    */
    echo "Decompressing archive...";
    $phar = new PharData($pkgFilePath);
    $phar->decompress();

    echo " done\nExtracting archive...";
    $phar = new PharData($pkgTarPath);
    $phar->extractTo($pkgExtractPath);

    /*
        This script will call some upgrade scripts during the installation of
        the update, taken from the downloaded update package. Ensure that these
        files are present before proceeding with the upgrade.
    */
    echo " done\nVerifying update scripts...";
    $setupDir = findSetupDir($pkgExtractPath);
    if ($setupDir === null) {
        echo " fail\n";
        throw new Exception(
            "Could not find setup directory in downloaded update file!"
        );
    }
    $preprocFile = "{$setupDir}/pre-upgrade.php";
    if (!file_exists($preprocFile)) {
        echo " fail\n";
        throw new Exception(
            "Could not find pre-upgrade.php in downloaded update file!"
        );
    }
    $postprocFile = "{$setupDir}/post-upgrade.php";
    if (!file_exists($postprocFile)) {
        echo " fail\n";
        throw new Exception(
            "Could not find post-upgrade.php in downloaded update file!"
        );
    }

    /*
        Call the /includes/setup/pre-upgrade.php.file to ensure that all
        dependencies of the downloaded upgrade are satisfied before installing.
        If this fails, the upgrade is halted.
    */
    echo " ok\nChecking dependencies...\n";
    include_once($preprocFile);
    $result = PreUpgrade::verifyDependencies();
    if (!$result) {
        throw new Exception(
            "Dependencies not satisfied!"
        );
    }

    /*
        After this point, the script will start deleting files.
    */
    echo "Warning: If the upgrade fails after this point, ";
    echo "your installation may become irreversibly broken!\n";

    /*
        Delete all files in the current installation. Keep the
        /includes/userdata directory as it contains the FreeField configuration,
        as well as the downloaded and extracted upgrade files. In order to keep
        that directory, we first delete all files that are not /includes, then
        we delete all files in /includes that are not userdata.
    */
    echo "Removing current installation...";
    $files = scandir($basepath);
    foreach ($files as $file) {
        if ($file != "." && $file != ".." && $file != "includes") {
            recursivelyDelete("{$basepath}/{$file}");
        }
    }
    $files = scandir("{$basepath}/includes");
    foreach ($files as $file) {
        if ($file != "." && $file != ".." && $file != "userdata") {
            recursivelyDelete("{$basepath}/includes/{$file}");
        }
    }

    /*
        Copy the extracted upgrade files out to the FreeField root. Again,
        ignore /includes/userdata.
    */
    echo " ok\nInstalling upgrade...";
    $extractBasePath = dirname(dirname($setupDir));
    $files = scandir($extractBasePath);
    foreach ($files as $file) {
        if ($file != "." && $file != ".." && $file != "includes") {
            recursivelyMove(
                "{$extractBasePath}/{$file}",
                "{$basepath}/{$file}"
            );
        }
    }
    $files = scandir("{$extractBasePath}/includes");
    foreach ($files as $file) {
        if ($file != "." && $file != ".." && $file != "userdata") {
            recursivelyMove(
                "{$extractBasePath}/includes/{$file}",
                "{$basepath}/includes/{$file}"
            );
        }
    }

    /*
        The upgrade itself was successful. Run the upgrade finalize function
        from /includes/setup/post-upgrade.php. This function updates the
        configuration file, changes database tables, and performs other post-
        upgrade triggers needed to ensure that the updated version of FreeField
        works properly.
    */
    echo " ok\nFinalizing upgrade...\n";
    $postprocFile = "{$basepath}/includes/setup/post-upgrade.php";
    include_once($postprocFile);
    PostUpgrade::finalizeUpgrade($updateData["source"]);
    // Fetch path that script will return to when setup is completed.
    $returnPath = PostUpgrade::getReturnPath();

    /*
        Clean up the upgrade. This involves deleting the
        /includes/userdata/tmp-update directory with all its contents.
    */
    echo "Cleaning up...";
    unset($phar);
    Phar::unlinkArchive($pkgTarPath);
    recursivelyDelete($pkgBasePath);

    /*
        We're all done! Direct the user back to the administration pages.
    */
    echo " ok\nInstallation completed.\n";
    echo "\n[✓] Upgrade was successful!\n";

    echo '</pre>';
    echo '<p>Update completed. <a href="'.$returnPath.'">Click here</a> to return to FreeField.</p>';

} catch (Exception $e) {
    /*
        Something broke. Hopefully this didn't cause any major issues, but since
        this script deletes and copies files, we can't be sure if there was any
        damage, and if so, the extent of it. Make sure to direct the user to the
        documentation for troubleshooting guidance.
    */
    echo "\n[!] Upgrade has failed: ".$e->getMessage()."\n";

    echo '</pre>';
    echo '<p>Update failed. Please check the documentation for ';
    echo 'troubleshooting steps before you return to FreeField.</p>';
}

?>
