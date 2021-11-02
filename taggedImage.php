<?php
//
// taggedImage.php
//
// Module for displaying tagged images of a study
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once 'security.php';
include_once 'display.php';
include_once 'applet.php';

global $PRODUCT;
print "<html>";
print "<head><title>$PRODUCT - ";
print pacsone_gettext("Tagged Image Information");
print "</title></head>";
print "<body>";
require_once 'header.php';

$studyId = $_REQUEST['studyId'];
$studyId = urlClean($studyId, 64);
if (!isUidValid($studyId)) {
    $error = pacsone_gettext("Invalid Study Instance UID");
    print "<h2><font color=red>$error</font></h2>";
    exit();
}
$dbcon = new MyConnection();
$username = $dbcon->username;
// query the patient id
$query = "select patientid from study where uuid=?";
$bindList = array($studyId);
$result = $dbcon->preparedStmt($query, $bindList);
$patientId = $result->fetchColumn();
$xpid = urlencode($patientId);
// access control
$viewAccess = $dbcon->hasaccess("viewprivate", $username);
if (!$viewAccess && !$dbcon->accessStudy($studyId, $username)) {
    print "<p><h2><font color=red>";
    print pacsone_gettext("This study is <b>Private</b>.");
    print "</font></h2>";
    exit();
}
// query for all tagged images
$rows = array();
$query = "SELECT uuid FROM series WHERE studyuid=? ORDER BY seriesnumber ASC";
$result = $dbcon->preparedStmt($query, $bindList);
while ($seriesId = $result->fetchColumn()) {
    $tagResult = $dbcon->query("SELECT * FROM image where tagged=1 AND seriesuid='$seriesId' ORDER BY instance ASC");
    while ($row = $tagResult->fetch(PDO::FETCH_ASSOC))
	      $rows[] = $row;
}
$num_rows = count($rows);
$header = "<br>";
$header .= sprintf(pacsone_gettext("There are %d tagged images in Study: <a href='series.php?patientId=%s&studyId=%s'>%s</a> for Patient: <a href='study.php?patientId=%s'>%s</a>"), $num_rows, $xpid, urlencode($studyId), $dbcon->getStudyId($studyId), $xpid, $dbcon->getPatientName($patientId));
$url = "taggedImage.php?studyId=$studyId&tagged=1";
$offset = 0;
$all = 0;
if (isset($_REQUEST['offset']))
	$offset = $_REQUEST['offset'];
if (isset($_REQUEST['all']))
	$all = $_REQUEST['all'];
displayImage($rows, $header, $url, $offset, $all, 1);
// update 'lastaccess' timestamp for the patient
$now = $dbcon->useOracle? "SYSDATE" : "NOW()";
$bindList = array($patientId);
$dbcon->preparedStmt("update patient set lastaccess=$now where origid=?", $bindList);

require_once 'footer.php';
print "</table>";
print "</body>";
print "</html>";

?>
