<?php
//
// exportzip.php
//
// Module for downloading zipped export content
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once "locale.php";
include_once 'database.php';
include_once 'security.php';

$id = urldecode($_GET['id']);
$volume = urldecode($_GET['volume']);
$dbcon = new MyConnection();
$bindList = array($id, $volume);
$result = $dbcon->preparedStmt("SELECT path FROM download where id=? AND volume=?", $bindList);
$filename = "";
if ($result)
    $filename = $result->fetchColumn();
if (!strlen($filename) || !file_exists($filename))
    die("<h3><font color=red>" . pacsone_gettext("No ZIP file found!") . "</font></h3>");
// MSIE handling of Content-Disposition
if (strstr($_SERVER["HTTP_USER_AGENT"], "MSIE")) {
    $disposition = "Content-disposition: file; filename=\"volume-$volume.zip\"";
} else {
    $disposition = "Content-disposition: attachment; filename=\"volume-$volume.zip\"";
}
header("Content-type: application/x-zip");
header($disposition);
header("Content-length: " . filesize($filename));
$fp = fopen($filename, "rb");
fpassthru($fp);
exit;
?>
