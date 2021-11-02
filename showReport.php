<?php
//
// showReport.php
//
// Module for displaying content of structured reports
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
print pacsone_gettext("Structured Report Content");
print "</title></head>";
print "<body>";
require_once 'header.php';

$id = $_REQUEST['id'];
$id = urlClean($id, 64);
if (!isUidValid($id)) {
    $error = pacsone_gettext("Invalid SOP Instance UID");
    print "<h2><font color=red>$error</font></h2>";
    exit();
}
$dbcon = new MyConnection();
$query = "SELECT * FROM image where uuid=?";
$bindList = array($id);
$result = $dbcon->preparedStmt($query, $bindList);
$row = $result->fetch(PDO::FETCH_ASSOC);
$path = $row['path'];
$seriesuid = $row['seriesuid'];
$instance = $row['instance'];
$completion = pacsone_gettext("N/A");
if (isset($row['completion']))
	$completion = $row['completion'];
$description = pacsone_gettext("N/A");
if (isset($row['description']))
	$description = $row['description'];
$verification = pacsone_gettext("N/A");
if (isset($row['verification']))
	$verification = $row['verification'];
$date = pacsone_gettext("N/A");
if (isset($row['contentdate']))
	$date = $row['contentdate'];
$time = pacsone_gettext("N/A");
if (isset($row['contenttime']))
	$time = $row['contenttime'];
if (isset($row['observationdatetime']))
	$observeDatetime = $row['observationdatetime'];
if (isset($row['verificationdatetime']))
	$verifyDatetime = $row['verificationdatetime'];
// query Series Number, Study ID and Patient Name
$result = $dbcon->query("SELECT seriesnumber FROM series WHERE uuid='$seriesuid'");
$seriesNum = $result->fetchColumn();
$query = "SELECT studyuid FROM series WHERE uuid='$seriesuid'";
$result = $dbcon->query($query);
$studyuid = $result->fetchColumn();
$result = $dbcon->query("SELECT id,patientid,referringphysician FROM study WHERE uuid='$studyuid'");
$row = $result->fetch(PDO::FETCH_NUM);
$studyId = $row[0];
if (!strlen($studyId))
	$studyId = pacsone_gettext("N/A");
$patientId = $row[1];
$referdoc = $row[2];
$patientName = $dbcon->getPatientName($patientId);
// display report headers
print "<table>";
print "<tr><td><b>";
global $CUSTOMIZE_PATIENT_ID;
printf("%s:</b></td><td>%s</td></tr>", $CUSTOMIZE_PATIENT_ID, $patientId);
print "<tr><td><b>";
global $CUSTOMIZE_PATIENT_NAME;
printf("%s:</b></td><td>%s</td></tr>", $CUSTOMIZE_PATIENT_NAME, $patientName);
print "<tr><td><b>";
global $CUSTOMIZE_REFERRING_DOC;
printf("%s:</b></td><td>%s</td></tr>", $CUSTOMIZE_REFERRING_DOC, $referdoc);
print "<tr><td><b>";
print pacsone_gettext("Study ID:") . "</b></td><td>$studyId</td></tr>";
print "<tr><td><b>";
print pacsone_gettext("Series Number:") . "</b></td><td>$seriesNum</td></tr>";
print "<tr><td><b>";
print pacsone_gettext("Instance Number:") . "</b></td><td>$instance</td></tr>";
print "<tr><td><b>";
print pacsone_gettext("Completion Flag:") . "</b></td><td>$completion</td></tr>";
print "<tr><td><b>";
print pacsone_gettext("Completion Flag Description:") . "</b></td><td>$description</td></tr>";
print "<tr><td><b>";
print pacsone_gettext("Verification Flag:") . "</b></td><td>$verification</td></tr>";
print "<tr><td><b>";
print pacsone_gettext("Content Date:") . "</b></td><td>$date</td></tr>";
print "<tr><td><b>";
print pacsone_gettext("Content Time:") . "</b></td><td>$time</td></tr>";
if (isset($observeDatetime)) {
	print "<tr><td><b>";
    print pacsone_gettext("Observation DateTime:");
    print "</b></td><td>$observeDatetime</td></tr>";
}
if (isset($verifyDatetime)) {
	print "<tr><td><b>";
    print pacsone_gettext("Observation DateTime:");
    print "</b></td><td>$verifyDatetime</td></tr>";
}
print "</table>";
print "<hr>";
// display report content
$report = new StructuredReport($path);
$report->showHtml();
//$report->showDebug();

require_once 'footer.php';
print "</body>";
print "</html>";

?>
