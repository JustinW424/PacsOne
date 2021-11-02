<?php
//
// study.php
//
// Module for displaying Study table
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
print pacsone_gettext("Study Information");
print "</title></head>";
print "<body>";
require_once 'header.php';

$dbcon = new MyConnection();
$username = $dbcon->username;
$origid = urldecode($_REQUEST['patientId']);
if (get_magic_quotes_gpc())
    $origid = stripslashes($origid);
if (preg_match("/[;\"]/", $origid)) {
    $error = pacsone_gettext("Invalid Patient ID");
    print "<h2><font color=red>$error</font></h2>";
    exit();
}
$toggle = 0;
$sort = "cmp_studyid";
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
$bindList = array($origid);
$dbcon->preparedStmt($query, $bindList);
$viewAccess = $dbcon->hasaccess("viewprivate", $username);
$rows = array();
if ($viewAccess) {
    $query = "SELECT * FROM study where patientid=?";
    $result = $dbcon->preparedStmt($query, $bindList);
    while ($row = $result->fetch(PDO::FETCH_ASSOC))
        $rows[] = $row;
} else {
    // all public studies
    $query = "SELECT * FROM study where private=0 and patientid=?";
    $result = $dbcon->preparedStmt($query, $bindList);
    while ($row = $result->fetch(PDO::FETCH_ASSOC))
        $rows[] = $row;
    // plus any private studies with matching referring or reading physician name
    $studies = $dbcon->getAccessibleStudies($origid, $username);
    foreach ($studies as $study)
        $rows[] = $study;
}
// sort the rows based on Study ID by default
my_usort($rows, $sort, $toggle);
$_SESSION['lastSort'] = $sort;
$_SESSION['sortToggle'] = $toggle;
$num_rows = sizeof($rows);

$url = "study.php?patientId=" . urlencode($origid);
$url .= "&sort=" . urlencode($sort) . "&toggle=$toggle";
$offset = 0;
if (isset($_REQUEST['offset']))
	$offset = $_REQUEST['offset'];
$all = 0;
if (isset($_REQUEST['all']))
	$all = $_REQUEST['all'];

global $CUSTOMIZE_PATIENT;
$preface = sprintf(pacsone_gettext("There are %d accessible studies for %s: <a href='patient.php?patientId=%s'>%s</a>"), $num_rows, $CUSTOMIZE_PATIENT, urlencode($origid), $dbcon->getPatientName($origid));
displayStudies($rows, $preface, $url, $offset, 0, $all);

require_once 'footer.php';
print "</table>";
print "</body>";
print "</html>";
?>
