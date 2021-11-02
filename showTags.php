<?php
//
// showTags.php
//
// Module for displaying raw DICOM tags of stored instances
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once 'security.php';
include_once 'dicom.php';

global $PRODUCT;
print "<html>";
print "<head><title>$PRODUCT - ";
print pacsone_gettext("Raw Dicom Tags");
print "</title></head>";
print "<body>";
require_once 'header.php';

$uid = $_REQUEST['uid'];
$uid = urlClean($uid, 64);
if (!isUidValid($uid)) {
    $error = pacsone_gettext("Invalid SOP Instance UID");
    print "<h2><font color=red>$error</font></h2>";
    exit();
}
$dbcon = new MyConnection();
$query = "SELECT path FROM image where uuid=?";
$bindList = array($uid);
$result = $dbcon->preparedStmt($query, $bindList);
$path = $result->fetchColumn();
print "<p>" . pacsone_gettext("Image Path: ");
if (file_exists($path)) {
    $url = "file://$path";
    print "<a href=\"$url\">$path</a><p>";
    // display report content
    $dump = new RawTags($path);
    $dump->showHtml();
    //$dump->showDebug();
} else {
    print "<font color=red><b>$path</b></font> ";
    print pacsone_gettext("Does Not Exist!") . "<br>";
}
require_once 'footer.php';
print "</body>";
print "</html>";

?>
