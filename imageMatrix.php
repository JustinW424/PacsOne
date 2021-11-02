<?php
//
// imageMatrix.php
//
// Module for displaying matrix of all images within a series
//
// CopyRight (c) 2010-2020 RainbowFish Software
//
session_start();

require_once "locale.php";
include_once 'security.php';
include_once 'display.php';
include_once 'applet.php';
include_once 'utils.php';

global $PRODUCT;
print "<html>";
print "<head><title>$PRODUCT - " . pacsone_gettext("Image Information") . "</title>";
print "<script language=\"javascript\">";
print "function onKeyDown(evt)";
print "{";
print "    evt = (evt) ? evt : ((event) ? event : null);";
print "    var charCode = (evt.which)? evt.which : event.keyCode;";
//print "    alert (\"The Unicode character code is: \" + charCode);";
print "    if (charCode == 33) {";
print "     window.location = document.getElementById(\"previous\").href;";
print "    } else if (charCode == 34) {";
print "     window.location = document.getElementById(\"next\").href;";
print "    }";
print "}";
print "function init()";
print "{";
print "document.onkeydown=onKeyDown;";
print "}";
print "window.onload=init;";
print "</script>";
print "</head>";
print "<body>";

$dbcon = new MyConnection();
$username = $dbcon->username;
$skipSeries = 0;
if (isset($_REQUEST['seriesId'])) {
    $seriesId = $_REQUEST['seriesId'];
    $seriesId = urlClean($seriesId, 64);
    if (!isUidValid($seriesId)) {
	    print "<p><font color=red>";
        printf(pacsone_gettext("Error: Invalid Series Instance UID: <b>[%s]</b>"), $seriesId);
        print "</font>";
	    exit();
    }
    // query the patient id and study uid
    $query = "select studyuid from series where uuid=?";
    $bindList = array($seriesId);
    $result = $dbcon->preparedStmt($query, $bindList);
    $studyId = $result->fetchColumn();
} else {
    $skipSeries = 1;
    $studyId = urlClean($_REQUEST['studyId'], 64);
}
if (!isUidValid($studyId)) {
    print "<p><font color=red>";
    printf(pacsone_gettext("Error: Invalid Study Instance UID: <b>[%s]</b>"), $studyId);
    print "</font>";
    exit();
}
$query = "select patientid from study where uuid=?";
$bindList = array($studyId);
$result = $dbcon->preparedStmt($query, $bindList);
$origid = $result->fetchColumn();
$urlId = urlencode($origid);
// update 'lastaccess' timestamp for the patient
$value = $dbcon->useOracle? "SYSDATE" : "NOW()";
$bindList = array($origid);
$dbcon->preparedStmt("update patient set lastaccess=$value where origid=?", $bindList);
// access control
$viewAccess = $dbcon->hasaccess("viewprivate", $username);
if (!$viewAccess && !$dbcon->accessStudy($studyId, $username)) {
    die ("<p><font color=red>" . pacsone_gettext("This study is <b>Private</b>.") . "</font>");
}
$rows = array();
global $CUSTOMIZE_PATIENT;
if ($skipSeries) {
    $query = "select uuid from series where studyuid=?";
    $bindList = array($studyId);
    $series = $dbcon->preparedStmt($query, $bindList);
    while ($seriesUid = $series->fetchColumn()) {
        $result = $dbcon->query("select * from image where seriesuid='$seriesUid' ORDER BY instance ASC");
        $num_rows = $result->rowCount();
        while ($row = $result->fetch(PDO::FETCH_ASSOC))
	        $rows[] = $row;
        $header = sprintf(pacsone_gettext("<br>There are %d images in Study: <a href='imageMatrix.php?patientId=%s&studyId=%s'>%s</a> for %s: <a href='study.php?patientId=%s'>%s</a>"), $num_rows, $urlId, $studyId, $dbcon->getStudyId($studyId), $CUSTOMIZE_PATIENT, $urlId, $dbcon->getPatientName($origid));
    }
    $url = "imageMatrix.php?studyId=" . urlencode($studyId);
} else {
    $query = "SELECT * FROM image where seriesuid=? ORDER BY instance ASC";
    $bindList = array($seriesId);
    $result = $dbcon->preparedStmt($query, $bindList);
    $num_rows = $result->rowCount();
    while ($row = $result->fetch(PDO::FETCH_ASSOC))
	    $rows[] = $row;
    $header = sprintf(pacsone_gettext("<br>There are %d images in <br>Series Number: %d of <br>Study: <a href='series.php?patientId=%s&studyId=%s&seriesId=%s'>%s</a> for <br>%s: <a href='study.php?patientId=%s'>%s</a>"), $num_rows, $dbcon->getSeriesNumber($seriesId), $urlId, $studyId, $seriesId, $dbcon->getStudyId($studyId), $CUSTOMIZE_PATIENT, $urlId, $dbcon->getPatientName($origid));
    $url = "imageMatrix.php?seriesId=$seriesId";
}
$offset = 0;
$all = 0;
if (isset($_REQUEST['offset']))
	$offset = $_REQUEST['offset'];
if (isset($_REQUEST['all']))
	$all = $_REQUEST['all'];
displayImageMatrix($rows, $header, $url, $offset, $all);

print "</body>";
print "</html>";

?>
