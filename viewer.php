<?php 
//
// viewer.php
//
// Module for feeding raw DICOM data to Java Applet viewers
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

include_once 'database.php';
include_once 'security.php';

$EXT_TBL = array(
    "LOSSLESS"  => ".ls",
    "LOSSY"     => ".ly",
    "RLE"       => ".rle",
    "JPEG2000"  => ".j2k",
);
$uid = urldecode($_REQUEST['uid']);
if (!isUidValid($uid)) {
    $error = pacsone_gettext("Invalid SOP Instance UID");
    print "<h2><font color=red>$error</font></h2>";
    exit();
}
$compression = "";
if (isset($_REQUEST['compression']))
    $compression = strtoupper($_REQUEST['compression']);
$dbcon = new MyConnection();
$query = "SELECT path FROM image where uuid=?";
$bindList = array($uid);
$result = $dbcon->preparedStmt($query, $bindList);
$file = $result->fetchColumn();
if (!file_exists($file)) {
    $error = pacsone_gettext("Instance Path Does Not Exist!");
    print "<h2><font color=red>$error</font></h2>";
    exit();
}
// serve compressed image if available
if (strlen($compression) && array_key_exists($compression, $EXT_TBL)) {
    $compressed = $file . $EXT_TBL[$compression];
    if (file_exists($compressed) && filesize($compressed))
        $file = $compressed;
}
// Allow sufficient execution time to the script:
set_time_limit(0);

header("Pragma: public");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header('Content-Type: application/octet-stream');    
header('Content-Length: ' . filesize($file));
header('Content-Transfer-Encoding: binary');

//$fp = fopen($file, "rb");
//fpassthru($fp);
//fclose($fp);

ignore_user_abort(true);
// we must go at least at 1 KB / s...
if ($file = fopen($file, 'rb')) 
{
    while(!feof($file) && !connection_aborted())
    {
        $buffer = fread($file, 32 * 1024);
        print $buffer;
        ob_flush();
        flush(); 
    }
    fclose($file);
}

exit();
?> 
