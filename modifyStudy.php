<?php
//
// modifyStudy.php
//
// Module for modifying Study Table
//
// CopyRight (c) 2004-2020 RainbowFish Software
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
if (isset($_POST['modified'])) {
    global $STUDY_MODIFY_COLUMNS;
    ob_start();
    $uid = $_POST['uid'];
    $column = $_POST['column'];
    if (!in_array(strtolower($column), $STUDY_MODIFY_COLUMNS)) {
        $error = sprintf(pacsone_gettext("Column: [%s] of STUDY table cannot be modified"), $column);
        print "<h2><font color=red>$error</font></h2>";
        exit();
    }
    $modtype = isset($_POST['modtype'])? $_POST['modtype'] : 0;
    if ($modtype == 0) {
        $modified = $_POST['modified'];
    } else {
        $moduser = $_POST['moduser'];
        if ($dbcon->isAdministrator($username) && !strcasecmp($moduser, pacsone_gettext("Create a new user"))) {
            // create a new user
            header("Location: modifyUser.php?actionvalue=Add");
            exit();
        }
        $tokens = explode(" - ", $moduser);
        $tokens = explode(", ", $tokens[1]);
        $modified = $tokens[0] . "^" . $tokens[1];
    }
    if (!strcasecmp($column, "studydate") && $dbcon->isEuropeanDateFormat() && strlen($modified))
        $modified = reverseDate($modified);
    $query = "update study set $column=? where uuid=?";
    $bindList = array($modified, $uid);
    if ($dbcon && $dbcon->preparedStmt($query, $bindList)) {
        // schedule a database job to modify the raw Dicom files
        $title = "_" . $column;
        $query = "insert into dbjob (username,type,aetitle,class,uuid,details,submittime,status,priority) ";
        $query .= "values(?,'resolve',?,'study',?,?,";
        $bindList = array($username, $title, $uid, $modified);
        $query .= $dbcon->useOracle? "SYSDATE," : "NOW(),";
        $query .= "'submitted',1)";
        $dbcon->preparedStmt($query, $bindList);
        // log activity to system journal
        $dbcon->logJournal($username, "Modify", "Study", $uid);
        // back to the Study list page
        header("Location: study.php?patientId=" . urlencode($dbcon->getPatientIdByStudyUid($uid)));
        exit();
    } else {
        print "<h2><font color=red>Database Error: " . $dbcon->getError() . "</font></h2>";
    }
} else {
    // display Modify Study Table form
    print "<html>\n";
    print "<head><title>$PRODUCT - ";
    print pacsone_gettext("Modify Study Table");
    print "</title></head>\n";
    print "<body>\n";
    require_once 'header.php';
    $uid = $_REQUEST['uid'];
    if (!isUidValid($uid)) {
        $error = pacsone_gettext("Invalid Study Instance UID");
        print "<h2><font color=red>$error</font></h2>";
        exit();
    }
    $what = $_REQUEST['key'];
    $column = $_REQUEST['column'];
    $value = urldecode($_REQUEST['value']);
    print "<form method='POST' action='modifyStudy.php'>\n";
    print "<input type='hidden' name='uid' value='$uid'></input>\n";
    print "<input type='hidden' name='column' value='$column'></input>\n";
    $patientId = urlencode($dbcon->getPatientIdByStudyUid($uid));
    $id = $dbcon->getStudyId($uid);
    $url = "series.php?patientId=$patientId&studyId=$uid";
    $name = $dbcon->getPatientNameByStudyUid($uid);
    $patientUrl = "study.php?patientId=$patientId";
    print "<p>";
    print "<table width=60% border=0 cellspacing=0 cellpadding=5>\n";
    print "<tr><td>";
    global $CUSTOMIZE_PATIENT;
    printf(pacsone_gettext("Modify <b>%s</b> for Study <a href='%s'>%s</a> of %s <a href='%s'>%s</a>\n"), $what, $url, $id, $CUSTOMIZE_PATIENT, $patientUrl, $name);
    print "</td><td>";
    if (stristr($column, "physician"))
        print "<input type='radio' name='modtype' value=0 checked>";
    print pacsone_gettext("Use This Value:");
    print "&nbsp;<input type='text' size=64 maxlength=64 name='modified' value='$value'><p>\n";
    if (stristr($column, "physician")) {
        print "<input type='radio' name='modtype' value=1>";
        print pacsone_gettext("Assign To This User:");
        print "&nbsp;";
        $userList = array();
        $users = $dbcon->query("select username,lastname,firstname from privilege");
        while ($users && ($row = $users->fetch(PDO::FETCH_NUM))) {
            $value = $row[0];
            if ($dbcon->isAdministrator($value))
                continue;
            $value .= " - ";
            $value .= ucfirst($row[1]) . ", " . ucfirst($row[2]);
            $userList[] = $value;
        }
        if ($dbcon->isAdministrator($username))
            $userList[] = pacsone_gettext("Create a new user");
        print "<select name='moduser'>";
        foreach ($userList as $user) {
            print "<option>$user";
        }
        print "</select>";
    }
    print "</td></tr></table>";
    print "<p><input type='submit' value='";
    print pacsone_gettext("Modify");
    print "'></form>\n";
	require_once 'footer.php';
    print "</body>\n";
    print "</html>\n";
}

?>
