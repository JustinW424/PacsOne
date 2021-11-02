<?php
//
// tempimage.php
//
// Module for automatically remove temporary images displayed to the browser
//
// CopyRight (c) 2004-2009 RainbowFish Software
//
require_once "utils.php";
ob_start();

$path = urldecode(cleanPostPath($_GET['path']));
if (!isset($_GET["antispam"])) {
    session_start();
    include_once 'security.php';
} else {
    // make sure this script can only stream anti-spam codes
    $scriptdir = dirname($_SERVER["SCRIPT_FILENAME"]) . "/antispam";
    $dir = dirname($path);
    // convert to Unix-style paths
    $dir = str_replace("\\", "/", $dir);
    $scriptdir = str_replace("\\", "/", $scriptdir);
    if (strcasecmp($dir, $scriptdir))
        die ("Invalid path: $path");
}
$ext = substr($path, -3);
if (strcasecmp($ext, "jpg") && strcasecmp($ext, "gif"))
    die ("Invalid path: $path");
$type = "Content-type: image/";
if (stristr($path, ".jpg")) {
    $type .= "jpg";
} else {
    $type .= "gif";
}
header($type);
$fp = fopen($path, "rb");
fpassthru($fp);
fclose($fp);
if (isset($_GET['purge']) && ($_GET['purge'] == 1)) {
    unlink($path);
}
ob_end_flush();

?>
