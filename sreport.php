<?php
//
// sreport.php
//
// Module for displaying DICOM Structured Reports
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once 'security.php';
include_once 'display.php';
include_once 'applet.php';

global $PRODUCT;
global $BGCOLOR;
print "<html>";
print "<head><title>$PRODUCT - ";
print pacsone_gettext("Structured Report");
print "</title></head>";
print "<body>";
require_once 'header.php';

$checkbox = 0;
$seriesId = $_REQUEST['seriesId'];
$seriesId = urlClean($seriesId, 64);
if (!isUidValid($seriesId)) {
    $error = pacsone_gettext("Invalid Series Instance UID");
    print "<h2><font color=red>$error</font></h2>";
    exit();
}
$dbcon = new MyConnection();
$username = $dbcon->username;
if (isset($_REQUEST['studyId'])) {
    $studyId = $_REQUEST['studyId'];
    $studyId = urlClean($studyId, 64);
    if (!isUidValid($studyId)) {
        $error = pacsone_gettext("Invalid Study Instance UID");
        print "<h2><font color=red>$error</font></h2>";
         exit();
    }
} else {
    $studyId = $dbcon->getStudyUidBySeriesUid($seriesId);
}
if (isset($_REQUEST['patientId'])) {
    $patientId = $_REQUEST['patientId'];
    $origid = urldecode($patientId);
    if (preg_match("/[;\"]/", $origid)) {
        $error = pacsone_gettext("Invalid Patient ID");
        print "<h2><font color=red>$error</font></h2>";
        exit();
    }
} else {
    $origid = $dbcon->getPatientIdByStudyUid($studyId);
}
// update 'lastaccess' timestamp for the patient
$now = $dbcon->useOracle? "SYSDATE" : "NOW()";
$query = "update patient set lastaccess=$now where origid=?";
$bindList = array($origid);
$dbcon->preparedStmt($query, $bindList);
// access control
$viewAccess = $dbcon->hasaccess("viewprivate", $username);
if (!$viewAccess && !$dbcon->accessStudy($studyId, $username)) {
    print "<p><font color=red>";
    print pacsone_gettext("This study is <b>Private</b>.");
    print "</font>";
    exit();
}
$query = "SELECT * FROM image where seriesuid=? ORDER BY instance ASC";
$bindList = array($seriesId);
$result = $dbcon->preparedStmt($query, $bindList);
$num_rows = $result->rowCount();

print "<table width=100% cellpadding=0 cellspacing=0 border=0>\n";
print "<tr><td>\n";
$patientId = urlencode($origid);
$header = "<br>";
global $CUSTOMIZE_PATIENT;
$header .= sprintf(pacsone_gettext("There are %d structured reports in Series Number: %d of Study: <a href='series.php?patientId=%s&studyId=%s&seriesId=%s'>%s</a> for %s: <a href='study.php?patientId=%s'>%s</a>"), $num_rows, $dbcon->getSeriesNumber($seriesId), $patientId, $studyId, $seriesId, $dbcon->getStudyId($studyId), $CUSTOMIZE_PATIENT, $patientId, $dbcon->getPatientName($origid));
$header .= "<P>";
echo $header;
print "</td></tr>\n";
// check user privileges
$access = 0;
$downloadAccess = $dbcon->hasaccess("download", $username);
if ($dbcon->hasaccess("modifydata", $username) && $num_rows) {
    $access = 1;
    $checkbox = 1;
}
if ($checkbox) {
    print "<form method='POST' action='actionItem.php'>\n";
}
// display the following columns: column name <=> database field
$columns = array(
    "Instance Number"          		=> array(pacsone_gettext("Instance Number"), "instance"),
    "Document Title"				=> array(pacsone_gettext("Document Title"), "uuid"),
    "Content Date"                  => array(pacsone_gettext("Content Date"), "contentdate"),
    "Content Time"                  => array(pacsone_gettext("Content Time"), "contenttime"),
    "Completion Flag"     			=> array(pacsone_gettext("Completion Flag"), "completion"),
	"Completion Flag Description"	=> array(pacsone_gettext("Completion Flag Description"), "description"),
	"Verification Flag"				=> array(pacsone_gettext("Verification Flag"), "verification"),
	"Observation Date & Time"		=> array(pacsone_gettext("Observation Date & Time"), "observationdatetime"),
);
print "<tr><td>\n";
print "<table class='table table-hover table-bordered table-striped' width=100% border=0 cellpadding=5>\n";
print "<tr class='tableHeadForBGUp'>\n";
if ($checkbox) {
    print "\t<td></td>\n";
}
foreach ($columns as $key => $field) {
    print "\t<td><b>" . $field[0] . "</b></td>\n";
}
print "</tr>\n";
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    $uid = $row['uuid'];
    print "<tr style='background-color:white;'>\n";
    if ($checkbox) {
        print "\t<td valign=center align=center width='1%'>\n";
        $data = $row['uuid'];
        print "\t\t<input type='checkbox' name='entry[]' value='$data'</td>\n";
    }
    foreach ($columns as $key => $field) {
        $value = $row[ $field[1] ];
        if (isset($value)) {
            global $MYFONT;
            if (strcasecmp($key, "Instance Number") == 0) {
                print "\t<td>$MYFONT<a href='showReport.php?id=$uid'>$value</a></font></td>\n";
            } else if (strcasecmp($field[1], "uuid") == 0) {
                $conceptName = $dbcon->query("SELECT meaning FROM conceptname WHERE uuid='$value'");
                $conceptRow = $conceptName->fetch(PDO::FETCH_NUM);
                $value = $conceptRow[0];
   		        print "\t<td>$MYFONT$value</font></td>\n";
            } else {
   		        print "\t<td>$MYFONT$value</font></td>\n";
            }
        }
        else
            print "\t<td>$MYFONT" . pacsone_gettext("N/A") . "</font></td>\n";
    }
    print "</tr>\n";
}
print "</table>\n";
print "</td></tr>\n";
if ($checkbox) {
    $check = pacsone_gettext("Check All");
    $uncheck = pacsone_gettext("Uncheck All");
    print "<tr><td>\n";
   	print "<p><table width=20% border=0 cellpadding=5>\n";
    print "<tr>\n";
    print "<td><input type=button value='$check' onClick='this.value=checkAll(this.form,\"entry\", \"$check\", \"$uncheck\")'</td>\n";
	if ($access) {
    	print "<td><input class='btn btn-primary' type=submit value='";
        print pacsone_gettext("Delete");
        print "' name='action' title='";
        print pacsone_gettext("Delete checked reports");
        print "' onclick='return confirm(\"";
        print pacsone_gettext("Are you sure?");
        print "\");'></td>\n";
    }
    print "<td><input type=hidden value='image' name='option'></td>\n";
    print "</tr>\n";
   	print "</table>\n";
    print "</td></tr>\n";
    print "</form>\n";
}
print "</table>\n";

require_once 'footer.php';
print "</body>";
print "</html>";

?>
