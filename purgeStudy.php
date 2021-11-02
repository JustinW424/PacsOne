<?php
//
// purgeStudy.php
//
// Tool for purging older studies based on criteria specified by the user
//
// CopyRight (c) 2012-2020 RainbowFish Software
//
if (!isset($_SESSION['authenticatedDatabase']))
    session_start();

include_once 'database.php';
include_once 'sharedData.php';
include_once 'security.php';

global $PRODUCT;
print "<html>";
print "<head><title>$PRODUCT - ";
print pacsone_gettext("Purge Older Dicom Studies By User-Specified Criteria");
print "</title></head>";
print "<body>";
require_once 'header.php';

$dbcon = new MyConnection();
$username = $dbcon->username;
if (!$dbcon->isAdministrator($username)) {
	print "<p><h3><font color=red>";
    print pacsone_gettext("You must login as <u>Administrator</u> to run this script");
    print "</font></h3>";
    exit();
}
global $CUSTOMIZE_PATIENT_DOB;
if (isset($_POST['update'])) {
    if ((!isset($_POST['studydate']) || !strlen($_POST['studydate'])) ||
        (!isset($_POST['dob']) || !strlen($_POST['dob']))) {
	    print "<p><h3><font color=red>";
        printf(pacsone_gettext("Both <u>Study Date</u> and <u>%s</u> must be specified!"), $CUSTOMIZE_PATIENT_DOB);
        print "</font></h3>";
        exit();
    }
    $studyDate = $_POST['studydate'];
    $dob = $_POST['dob'];
    if ($dbcon->isEuropeanDateFormat()) {
        $studyDate = reverseDate($studyDate);
        $dob = reverseDate($dob);
    }
    $studyDate = strtotime($studyDate);
    $dob = strtotime($dob);
    $studyDate = date("Ymd", $studyDate);
    $dob = date("Ymd", $dob);
    if ($dbcon->useOracle) {
        $studyDate = "TO_DATE('$studyDate','YYYYMMDD')";
        $dob = "TO_DATE('$dob','YYYYMMDD')";
    }
    // disable PHP timeout
    set_time_limit(0);
    $query = "select patient.origid,birthdate,uuid,id,studydate,referringphysician from patient left join study on patient.origid=study.patientid where studydate < ? group by uuid order by studydate asc";
    $bindList = array($studyDate);
    $result = $dbcon->preparedStmt($query, $bindList);
    if (!$result) {
	    print "<p><h3><font color=red>";
        print "Error running SQL query: [$query], error = " . $dbcon->getError();
        print "</font></h3>";
        exit();
    }
    $delete = isset($_POST['checkOnly'])? 0 : 1;
    $count = 0;
    $studies = array();
    global $BGCOLOR;
    print "<br><table width=100% border=1 cellpadding=3>";
    print "<tr class=listhead bgcolor=$BGCOLOR>\n";
    global $CUSTOMIZE_PATIENT_ID;
    global $CUSTOMIZE_REFERRING_DOC;
    print "<td>$CUSTOMIZE_PATIENT_ID</td>";
    print "<td>$CUSTOMIZE_PATIENT_DOB</td>";
    print "<td>" . pacsone_gettext("Study ID") . "</td>";
    print "<td>" . pacsone_gettext("Study Date") . "</td>";
    print "<td>$CUSTOMIZE_REFERRING_DOC</td>";
    print "<td>" . pacsone_gettext("Total Number of Instances") . "</td>";
    print "</tr>";
    while ($result && ($row = $result->fetch(PDO::FETCH_NUM))) {
        $count++;
        $pid = $row[0];
        $birthDate = $row[1];
        // further validation against DOB
        if (strlen($birthDate)) {
            $compare = $dbcon->useOracle? "TO_DATE('$birthDate','YYYYMMDD')" : "DATE('$birthDate')";
            $query = "select 1 from dual where $compare >= $dob";
            $subq = $dbcon->query($query);
            if ($subq && $subq->rowCount())
                continue;
        }
        if (!strlen($birthDate))
            $birthDate = pacsone_gettext("N/A");
        $studyUid = $row[2];
        $studyId = $row[3];
        if (!strlen($studyId))
            $studyId = pacsone_gettext("N/A");
        $sDate = $row[4];
        if (!strlen($sDate))
            $sDate = pacsone_gettext("N/A");
        $referdoc = $row[5];
        if (!strlen($referdoc))
            $referdoc = pacsone_gettext("N/A");
        $instances = $dbcon->getStudyInstanceCount($studyUid);
        print "<tr>";
        print "<td>$pid</td>";
        print "<td>$birthDate</td>";
        print "<td>$studyId</td>";
        print "<td>$sDate</td>";
        print "<td>$referdoc</td>";
        print "<td>$instances</td>";
        print "</tr>";
        if ($delete)
            $studies[] = $studyUid;
    }
    if ($delete && count($studies)) {
        deleteStudies($studies);
    }
    print "</table>";
    print "<p>";
    if ($delete)
        printf(pacsone_gettext("Total of %d studies deleted"), $count);
    else
        printf(pacsone_gettext("Total of %d studies found"), $count);
} else {
    print "<form method='POST' action='purgeStudy.php'>\n";
    print "<input type='hidden' name='update' value=1>\n";
    print "<p>";
    print pacsone_gettext("Purge older studies performed before this date: (YYYY-MM-DD)");
    print "&nbsp;<input type=text name='studydate' size=16 maxlength=32>";
    print "<p>";
    print pacsone_gettext("And patients must have date of birth (DOB) before this date: (YYYY-MM-DD)");
    print "&nbsp;<input type=text name='dob' size=16 maxlength=32>";
    print "<p><input type='checkbox' name='checkOnly' checked>&nbsp;";
    print pacsone_gettext("Find but do not delete the matching studies found");
    print "<p><input type=submit value='Purge' title='";
    print pacsone_gettext("Purge Matching Studies");
    print "'>\n";
    print "</form>\n";
}

require_once 'footer.php';
print "</body>";
print "</html>";
?>
