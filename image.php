<?php
//
// image.php
//
// Module for displaying the Image table
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once "locale.php";
include_once 'security.php';
include_once 'display.php';
include_once 'applet.php';
include_once 'utils.php';

global $PRODUCT;
print "<html>";
print "<head><title>$PRODUCT - " . pacsone_gettext("Image Information") . "</title></head>";
print "<body>";
require_once 'header.php';

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
if ($skipSeries) {
    $query = "select uuid from series where studyuid=?";
    $bindList = array($studyId);
    $series = $dbcon->preparedStmt($query, $bindList);
    while ($series && $seriesUid = $series->fetchColumn()) {
        $result = $dbcon->query("select * from image where seriesuid='$seriesUid' ORDER BY instance ASC");
        $num_rows = $result->rowCount();
        while ($row = $result->fetch(PDO::FETCH_ASSOC))
	        $rows[] = $row;
    }
    global $CUSTOMIZE_PATIENT;
    $header = sprintf(pacsone_gettext("<br>There are %d images in Study: <a href='image.php?patientId=%s&studyId=%s'>%s</a> for %s: <a href='study.php?patientId=%s'>%s</a>"), count($rows), $urlId, $studyId, $dbcon->getStudyId($studyId), $CUSTOMIZE_PATIENT, $urlId, $dbcon->getPatientName($origid));
    $url = "image.php?studyId=" . urlencode($studyId);
} else {
    $query = "SELECT * FROM image where seriesuid=? ORDER BY instance ASC";
    $bindList = array($seriesId);
    $result = $dbcon->preparedStmt($query, $bindList);
    $num_rows = $result->rowCount();
    while ($row = $result->fetch(PDO::FETCH_ASSOC))
	    $rows[] = $row;
    $header = sprintf(pacsone_gettext("<br>There are %d images in Series Number: %d of Study: <a href='series.php?patientId=%s&studyId=%s&seriesId=%s'>%s</a> for %s: <a href='study.php?patientId=%s'>%s</a>"), $num_rows, $dbcon->getSeriesNumber($seriesId), $urlId, $studyId, $seriesId, $dbcon->getStudyId($studyId), $CUSTOMIZE_PATIENT, $urlId, $dbcon->getPatientName($origid));
    $url = "image.php?seriesId=$seriesId";
}
$offset = 0;
$all = 0;
if (isset($_REQUEST['offset']))
	$offset = $_REQUEST['offset'];
if (isset($_REQUEST['all']))
	$all = $_REQUEST['all'];
displayImage($rows, $header, $url, $offset, $all, 1);

require_once 'footer.php';
print "</table>";
print "</body>";
print "</html>";

?>
