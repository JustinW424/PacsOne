<?php
//
// resolveDup.php
//
// Tool page for resolving duplicate patient ids
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once 'security.php';
include_once 'database.php';
include_once 'sharedData.php';

global $CUSTOMIZE_PATIENT_ID;
global $CUSTOMIZE_PATIENT_NAME;

$dbcon = new MyConnection();
$username = $dbcon->username;
if (isset($_REQUEST['duplicate']))
    $dupid = urldecode($_REQUEST['duplicate']);
else
    die("<font color=red>" . sprintf(pacsone_gettext("Unknown duplicate %s"), $CUSTOMIZE_PATIENT_ID) . "</font>");
if (preg_match("/[';\"]/", $dupid)) {
    $error = pacsone_gettext("Invalid Duplicate Patient ID");
    print "<h2><font color=red>$error</font></h2>";
    exit();
}
if (isset($_POST['keep'])) {
    $origid = $_POST['origid'];
    if (preg_match("/[';\"]/", $origid)) {
        $error = pacsone_gettext("Invalid Original Patient ID");
        print "<h2><font color=red>$error</font></h2>";
        exit();
    }
    $keep = $_POST['keep'];
    $toResolve = array();
    if (($keep == 0) || ($keep == 3)) {           // keep the existing patient
        $dup = "patientname";
        if ($keep == 0) {
            $query = "select lastname,firstname,middlename from patient where origid=?";
            $bindList = array($origid);
            $result = $dbcon->preparedStmt($query, $bindList);
            $row = $result->fetch(PDO::FETCH_NUM);
            $name = "";
            if (strlen($row[0]))
                $name .= $row[0];
            if (strlen($row[1]))
                $name .= "^" . $row[1];
            if (strlen($row[2]))
                $name .= "^" . $row[2];
            $query = "select uuid from study where patientid=?";
            $bindList = array($dupid);
        } else if ($keep == 3) {                // use the Patient Name from worklist records
            $name = urldecode($_POST['worklistname']);
            $subq = "";
            $subList = array();
            $tokens = explode("^", $name);
            $lastname = $tokens[0];
            if (strlen($lastname)) {
                if (strlen($subq))
                    $subq .= ",";
                $subq .= "lastname=?";
                $subList[] = $lastname;
            }
            $firstname = $tokens[1];
            if (strlen($firstname)) {
                if (strlen($subq))
                    $subq .= ",";
                $subq .= "firstname=?";
                $subList[] = $firstname;
            }
            $middlename = $tokens[2];
            if (strlen($middlename)) {
                if (strlen($subq))
                    $subq .= ",";
                $subq .= "middlename=?";
                $subList[] = $middlename;
            }
            if (strlen($subq)) {
                $subq = "update patient set " . $subq;
                $subq .= " where origid=?";
                $subList[] = $origid;
                $dbcon->preparedStmt($subq, $subList);
            }
            $query = "select uuid from study where patientid=? or patientid=?";
            $bindList = array($origid, $dupid);
        }
        // tally all the studies that belongs to the wrong patient id
        $result = $dbcon->preparedStmt($query, $bindList);
        if ($result && $result->rowCount()) {
            while ($uid = $result->fetchColumn()) {
                $toResolve[ $uid ] = $name;
            }
        }
        $query = "update study set patientid=? where patientid=?";
        $bindList = array($origid, $dupid);
        $dbcon->preparedStmt($query, $bindList);
        $query = "delete from patient where origid=?";
        $bindList = array($dupid);
        $dbcon->preparedStmt($query, $bindList);
    } else if ($keep == 2) {    // assigned a new patient id
        $newid = $_POST['newid'];
        $dup = "patientid";
        // tally all the studies that belongs to the wrong patient id
        $query = "select uuid from study where patientid=?";
        $bindList = array($dupid);
        $result = $dbcon->preparedStmt($query, $bindList);
        if ($result && $result->rowCount()) {
            while ($uid = $result->fetchColumn()) {
                $toResolve[ $uid ] = $newid;
            }
        }
        $query = "update study set patientid=? where patientid=?";
        $bindList = array($newid, $dupid);
        $dbcon->preparedStmt($query, $bindList);
        // check if the newly assigned patient id already exists
        $query = "select count(*) from patient where origid=?";
        $bindList = array($newid);
        $result = $dbcon->preparedStmt($query, $bindList);
        if ($result) {
            $count = $result->fetchColumn();
            if ($count) {
                $query = "delete from patient where origid=?";
                $bindList = array($dupid);
                $dbcon->preparedStmt($query, $bindList);
            } else {
                $query = "update patient set origid=? where origid=?";
                $bindList = array($newid, $dupid);
                $dbcon->preparedStmt($query, $bindList);
            }
        }
    } else if ($keep == 1) {    // keep the duplicate
        $dup = "patientname";
        $query = "select lastname,firstname,middlename from patient where origid=?";
        $bindList = array($dupid);
        $result = $dbcon->preparedStmt($query, $bindList);
        $row = $result->fetch(PDO::FETCH_NUM);
        $name = "";
        if (strlen($row[0]))
            $name .= $row[0];
        if (strlen($row[1]))
            $name .= "^" . $row[1];
        if (strlen($row[2]))
            $name .= "^" . $row[2];
        // tally all the studies that belongs to the wrong patient id
        $query = "select uuid from study where patientid=?";
        $bindList = array($origid);
        $result = $dbcon->preparedStmt($query, $bindList);
        if ($result && $result->rowCount()) {
            while ($uid = $result->fetchColumn()) {
                $toResolve[ $uid ] = $name;
            }
        }
        $query = "update study set patientid=? where patientid=?";
        $bindList = array($origid, $dupid);
        $dbcon->preparedStmt($query, $bindList);
        $query = "delete from patient where origid=?";
        $bindList = array($origid);
        $dbcon->preparedStmt($query, $bindList);
        $query = "update patient set origid=? where origid=?";
        $bindList = array($origid, $dupid);
        $dbcon->preparedStmt($query, $bindList);
    }
    // schedule jobs to resolve duplicate patient ids in raw Dicom files
    if (count($toResolve)) {
        $when = $dbcon->useOracle? "SYSDATE" : "NOW()";
        foreach ($toResolve as $uid => $resolved) {
            $query = "insert into dbjob (username,type,aetitle,class,uuid,details,submittime,status) ";
            $query .= "values(?,'resolve','_$dup','study',?,?,$when,'submitted')";
            $bindList = array($username, $uid, $resolved);
            $dbcon->preparedStmt($query, $bindList);
        }
    }
    // back to the duplicates page
    $url = "tools.php?page=" . urlencode(sprintf(pacsone_gettext("Check Duplicate %s"), $CUSTOMIZE_PATIENT_ID));
    header("Location: $url");
    exit();
} else {
    global $PRODUCT;
    print "<html>";
    print "<head><title>$PRODUCT - ";
    printf(pacsone_gettext("Resolve Duplicate %s"), $CUSTOMIZE_PATIENT_ID);
    print "</title></head>";
    print "<body>";
    require_once 'header.php';
    include_once 'utils.php';

    // parse the original patient id
    $index = strpos($dupid, "[");
    if ($index === false) {
        print "<font color=red>";
        printf(pacsone_gettext("Not a duplicate %s: %s"), $CUSTOMIZE_PATIENT_ID, $dupid);
        print "</font>";
        exit();
    }
    $origid = substr($dupid, 0, $index);
    // query for the existing patient information
    $url = "resolveDup.php?duplicate=" . urlencode($dupid);
    print "<form method='POST' action='$url'>\n";
    print "<p>";
    printf(pacsone_gettext("Choose one of the following methods to resolve the duplicate %s: "), $CUSTOMIZE_PATIENT_ID);
    printf(pacsone_gettext("(\"<u>%s</u>\" <b>vs.</b> \"<u>%s</u>\")"), $origid, $dupid);
    print "<p>\n";
    print "<input type=hidden name='origid' value='$origid'>\n";
    $query = "select * from patient where origid=?";
    $bindList = array($origid);
    $result = $dbcon->preparedStmt($query, $bindList);
    $existRow = $result->fetch(PDO::FETCH_ASSOC);
    print "<DL>";
    print "<DT>";
    print "<input type=radio name='keep' value=0 checked>";
    printf(pacsone_gettext("Keep Existing %s: <u>%s</u> and Use This %s:"), $CUSTOMIZE_PATIENT_ID, $origid, $CUSTOMIZE_PATIENT_NAME);
    print "</DT>";
    print "<DD>" . pacsone_gettext("Firstname:");
    print " <b>" . $existRow['firstname'] . "</b>";
    if (strlen($existRow['middlename'])) {
        print pacsone_gettext(" Middlename:");
        print " <b>" . $existRow['middlename'] . "</b>";
    }
    print pacsone_gettext(" Lastname:");
    print " <b>" . $existRow['lastname'] . "</b>";
    global $CUSTOMIZE_PATIENT_DOB;
    if (strlen($existRow['birthdate'])) {
        printf(" %s:", $CUSTOMIZE_PATIENT_DOB);
        print " <b>" . $existRow['birthdate'] . "</b>";
    }
    print "</DD><br>\n";
    // find the duplicate
    $query = "select * from patient where origid=?";
    $bindList = array($dupid);
    $result = $dbcon->preparedStmt($query, $bindList);
    if ($result && ($row = $result->fetch(PDO::FETCH_ASSOC))) {
        print "<DT>";
        print "<input type=radio name='keep' value=1>";
        printf(pacsone_gettext("Keep Existing %s: <u>%s</u> and Use This %s:"), $CUSTOMIZE_PATIENT_ID, $origid, $CUSTOMIZE_PATIENT_NAME);
        print " </DT>";
        print "<DD>" . pacsone_gettext("Firstname:");
        print " <b>" . $row['firstname'] . "</b>";
        if (strlen($row['middlename'])) {
            print pacsone_gettext(" Middlename:");
            print " <b>" . $row['middlename'] . "</b>";
        }
        print pacsone_gettext(" Lastname:");
        print " <b>" . $row['lastname'] . "</b>";
        if (strlen($row['birthdate'])) {
            printf(" %s:", $CUSTOMIZE_PATIENT_DOB);
            print " <b>" . $row['birthdate'] . "</b>";
        }
        print "</DD><br>\n";
        // 3rd option is to assign a new patient id
        print "<DT>";
        print "<input type=radio name='keep' value=2>";
        printf(pacsone_gettext("Save Duplicate Using A New %s: "), $CUSTOMIZE_PATIENT_ID);
        print "<input type=text size=16 maxlength=64 name='newid'></DT><br>";
        // 4th option is to use the patient name from the modality worklist records
        $query = "select * from worklist where patientid=?";
        $bindList = array($origid);
        $result = $dbcon->preparedStmt($query, $bindList);
        if ($result && ($worklist = $result->fetch(PDO::FETCH_ASSOC))) {
            $worklistName = $worklist['patientname'];
            $worklistDob = isset($worklist['patientname'])? $worklist['birthdate'] : "";
            print "<DT>";
            print "<input type=radio name='keep' value=3>";
            printf(pacsone_gettext("Keep Existing %s: <u>%s</u> and Use This %s From Modality Worklist Record:"), $CUSTOMIZE_PATIENT_ID, $origid, $CUSTOMIZE_PATIENT_NAME);
            print " </DT>";
            print "<input type='hidden' name='worklistname' value='" . urlencode($worklistName) . "'>";
            $tokens = explode("^", $worklistName);
            $lastname = strlen($tokens[0])? $tokens[0] : pacsone_gettext("N/A");
            $firstname = strlen($tokens[1])? $tokens[1] : pacsone_gettext("N/A");
            $middlename = strlen($tokens[2])? $tokens[2] : pacsone_gettext("N/A");
            print "<DD>" . pacsone_gettext("Firstname:");
            print " <b>" . $firstname . "</b>";
            if (strlen($middlename)) {
                print pacsone_gettext(" Middlename:");
                print " <b>" . $middlename . "</b>";
            }
            print pacsone_gettext(" Lastname:");
            print " <b>" . $lastname . "</b>";
            if (strlen($worklistDob)) {
                printf(" %s:", $CUSTOMIZE_PATIENT_DOB);
                print " <b>" . $worklistDob . "</b>";
            }
            print "</DD><br>\n";
        }
        print "<p><input type=submit value='";
        print pacsone_gettext("Resolve");
        print "' title='";
        printf(pacsone_gettext("Resolve Duplicate %s"), $CUSTOMIZE_PATIENT_ID);
        print "'>\n";
    }
    print "</DL>\n";
    print "</form>\n";
    require_once 'footer.php';
    print "</body>";
    print "</html>";
}

?>
