<?php
//
// splitStudy.php
//
// Module for splitting existing study into a new patient
//
// CopyRight (c) 2020 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once 'database.php';
include_once 'sharedData.php';
include_once 'utils.php';

// main
global $PRODUCT;
$dbcon = new MyConnection();
$username = $dbcon->username;
if (isset($_POST['merge'])) {
    $uid = $_POST['uid'];
    $currentPid = urldecode($_POST['currentpid']);
    if ($_POST['merge']) {
        $tokens = explode(" <-> ", $_POST['mergepatient']);
        if (count($tokens) != 2) {
            $error = sprintf(pacsone_gettext("Invalid Merge Patient: [%s]"), $_POST['mergepatient']);
            print "<h2><font color=red>$error</font></h2>";
            exit();
        }
        $newPid = $tokens[0];
        $newName = $tokens[1];
    } else {
        // assign new Patient ID/Patient Names
        $newPid = $_POST['newpid'];
        $newName = $_POST['newname'];
        // check if newly assigned Patient ID exists or not
        $bindList = array($newPid);
        $result = $dbcon->preparedStmt("select origid from patient where origid=?", $bindList);
        if (!$result || $result->rowCount()) {
            $error = sprintf(pacsone_gettext("Newly Assigned Patient ID: [%s] Already Exists!"), $newPid);
            print "<h2><font color=red>$error</font></h2>";
            exit();
        }
        // create new patient record for newly assigned Patient ID/Patient Names
        $tokens = explode("^", $newName);
        if (count($tokens) < 2) {
            $error = sprintf(pacsone_gettext("Invalid New Patient Name: [%s]"), $newName);
            print "<h2><font color=red>$error</font></h2>";
            exit();
        }
        $query = "insert into patient (origid";
        $values = "?";
        $subList = array($newPid);
        $query .= ",lastname";
        $subList[] = $tokens[0];
        $values .= ",?";
        if (isset($tokens[1]) && strlen($tokens[1])) {
            $query .= ",firstname";
            $subList[] = $tokens[1];
            $values .= ",?";
        }
        if (isset($tokens[2]) && strlen($tokens[2])) {
            $query .= ",middlename";
            $subList[] = $tokens[2];
            $values .= ",?";
        }
        if (isset($tokens[3]) && strlen($tokens[3])) {
            $query .= ",prefix";
            $subList[] = $tokens[3];
            $values .= ",?";
        }
        if (isset($tokens[4]) && strlen($tokens[4])) {
            $query .= ",suffix";
            $subList[] = $tokens[4];
            $values .= ",?";
        }
        $query .= ") values($values)";
        if (!$dbcon->preparedStmt($query, $subList)) {
            printf(pacsone_gettext("Failed to add new patient: [%s], error = %s"), $query, $dbcon->getError());
            exit();
        }
    }
    $modified = $newPid . "<->" . $newName;
    $query = "update study set patientid=? where patientid=?";
    $bindList = array($newPid, $currentPid);
    // schedule a database job to modify the raw Dicom files
    if ($dbcon && $dbcon->preparedStmt($query, $bindList)) {
        $title = "_patientid";
        $query = "insert into dbjob (username,type,aetitle,class,uuid,details,submittime,status,priority) ";
        $query .= "values(?,'SplitStudy',?,'study',?,?,";
        $bindList = array($username, $title, $uid, $modified);
        $query .= $dbcon->useOracle? "SYSDATE," : "NOW(),";
        $query .= "'submitted',1)";
        $dbcon->preparedStmt($query, $bindList);
        // log activity to system journal
        $dbcon->logJournal($username, "Split", "Study", $uid);
        // back to the Study list page
        header("Location: study.php?patientId=" . urlencode($newPid));
    } else {
        print "<h2><font color=red>Database Error: " . $dbcon->getError() . "</font></h2>";
    }
    exit();
} else {
    // display Split Study form
    print "<html>\n";
    print "<head><title>$PRODUCT - ";
    print pacsone_gettext("Split Study");
    print "</title></head>\n";
    print "<body>\n";
    require_once 'header.php';
    $uid = $_REQUEST['uid'];
    if (!isUidValid($uid)) {
        $error = pacsone_gettext("Invalid Study Instance UID");
        print "<h2><font color=red>$error</font></h2>";
        exit();
    }
    $currentPid = $_REQUEST['patientId'];
    $xpid = urlencode($currentPid);
    print "<form method='POST' action='splitStudy.php'>\n";
    print "<input type='hidden' name='uid' value='$uid'></input>\n";
    print "<input type='hidden' name='currentpid' value='$xpid'></input>\n";
    $id = $dbcon->getStudyId($uid);
    $url = "series.php?patientId=" . urlencode($currentPid) . "&studyId=$uid";
    $name = $dbcon->getPatientNameByStudyUid($uid);
    $patientUrl = "study.php?patientId=" . urlencode($currentPid);
    print "<p>";
    print "<table width=80% border=0 cellspacing=0 cellpadding=5>\n";
    print "<tr><td>";
    global $CUSTOMIZE_PATIENT;
    printf(pacsone_gettext("Split Study <a href='%s'>%s</a> of %s <a href='%s'>%s</a>\n"), $url, $id, $CUSTOMIZE_PATIENT, $patientUrl, $name);
    print "</td><td style=\"LINE-HEIGHT:25px\">";
    print "<input type='radio' name='merge' value=1 checked>";
    print pacsone_gettext("Merge This Study with A Different Patient:");
    $patientList = array();
    $result = $dbcon->query("select origid,lastname,firstname from patient order by origid asc");
    while ($result && $row = $result->fetch(PDO::FETCH_NUM)) {
        if (strcasecmp($row[0], $currentPid))
            $patientList[ $row[0] ] = $row[1] . "^" . $row[2];
    }
    print "&nbsp;<select name='mergepatient'>";
    foreach ($patientList as $pid => $patientName) {
        $entry = $pid . " <-> " . $patientName;
        print "<option>$entry";
    }
    print "</select>";
    print "<br><input type='radio' name='merge' value=0>";
    print pacsone_gettext("Assign A New Patient ID for This Study:");
    print "&nbsp;<input type='text' size=64 maxlength=64 name='newpid'><br>\n";
    print "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
    print pacsone_gettext("Assign New Patient Name for This Study:");
    print "&nbsp;<input type='text' size=64 maxlength=64 name='newname'>\n";
    print pacsone_gettext(" via the Dicom Standard 5-Component Format (Last^First^Middle^Prefix^Suffix)");
    print "</td></tr></table>";
    print "<p><input type='submit' value='";
    print pacsone_gettext("Modify");
    print "'></form>\n";
	require_once 'footer.php';
    print "</body>\n";
    print "</html>\n";
}

?>
