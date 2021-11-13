<?php
//
// series.php
//
// Module for displaying Series table
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once 'security.php';
include_once 'display.php';
require_once 'utils.php';

global $PRODUCT;
print "<html>";
print "<head><title>$PRODUCT - ";
print pacsone_gettext("Series Information");
print "</title></head>";
print "<body>";
require_once 'header.php';
$dbcon = new MyConnection();
$username = $dbcon->username;
$studyId = urldecode($_REQUEST['studyId']);
$studyId = urlClean($studyId, 64);
if (!isUidValid($studyId)) {
    $error = pacsone_gettext("Invalid Study Instance UID");
    print "<h2><font color=red>$error</font></h2>";
    exit();
}
if (isset($_REQUEST['patientId'])) {
    $origid = urldecode($_REQUEST['patientId']);
    if (get_magic_quotes_gpc())
        $origid = stripslashes($origid);
    if (preg_match("/[;\"]/", $origid)) {
        $error = pacsone_gettext("Invalid Patient ID");
        print "<h2><font color=red>$error</font></h2>";
        exit();
    }
    $patientId = $origid;
} else {
    $patientId = $dbcon->getPatientIdByStudyUid($studyId);
}
$toggle = 0;
$sort = "cmp_seriesnum";
if (isset($_REQUEST['sort']) && isset($_REQUEST['toggle'])) {
    $sort = $_REQUEST['sort'];
    if (isset($_SESSION['lastSort']) && !strcasecmp($sort, $_SESSION['lastSort'])
        && ($toggle != $_REQUEST['toggle'])) {
        $toggle = 1 - $toggle;
    }
}
// update 'lastaccess' timestamp for the patient
$now = $dbcon->useOracle? "SYSDATE" : "NOW()";
$query = "update patient set lastaccess=$now where origid=?";
$bindList = array($patientId);
$dbcon->preparedStmt($query, $bindList);
// access control
$viewAccess = $dbcon->hasaccess("viewprivate", $username);
if (!$viewAccess && !$dbcon->accessStudy($studyId, $username)) {
    print "<p><font color=red>";
    print pacsone_gettext("This study is <b>Private</b>.");
    print "</font>";
    exit();
}
$tagged = 0;
$query = "SELECT * FROM series where studyuid=?"; // series table list source 
$bindList = array($studyId);
$result = $dbcon->preparedStmt($query, $bindList);
$rows = array();
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    $rows[] = $row;
    // count the number of tagged images
    $query = sprintf("select count(*) from image where tagged=1 and seriesuid='%s'", $row['uuid']);
    $tagResult = $dbcon->query($query);
    $tagged += $tagResult->fetchColumn();
}
// sort the rows based on Series Number by default
my_usort($rows, $sort, $toggle);
$_SESSION['lastSort'] = $sort;
$_SESSION['sortToggle'] = $toggle;
$num_rows = sizeof($rows);

$url = "series.php?patientId=" . urlencode($origid);
$url .= "&studyId=" . urlencode($studyId);
$url .= "&sort=" . urlencode($sort) . "&toggle=$toggle";
$offset = 0;
if (isset($_REQUEST['offset']))
	$offset = $_REQUEST['offset'];
$all = 0;
if (isset($_REQUEST['all']))
	$all = $_REQUEST['all'];

global $CUSTOMIZE_PATIENT;
$preface = sprintf(pacsone_gettext("There are %d series in Study: %s for %s: <a href='study.php?patientId=%s'>%s</a>"), $num_rows, $dbcon->getStudyId($studyId), $CUSTOMIZE_PATIENT, urlencode($origid), $dbcon->getPatientName($origid));
displaySeries($rows, $preface, $url, $offset, $all, $tagged, 1);

require_once 'footer.php';
print "</body>";
print "</html>";

?>
