<?php
//
// enterWorklist.php
//
// Module for manually enter worklist information
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once 'database.php';
include_once 'sharedData.php';
include_once 'utils.php';

global $CUSTOMIZE_PATIENT_ID;

$WORKLIST_REQ_FIELDS = array(
    "Patient Lastname"          => "lastname",
    "Patient Firstname"         => "firstname",
    $CUSTOMIZE_PATIENT_ID       => "patientid",
    "Accession Number"          => "accessionnum",
    "Scheduled AE Station"      => "aetitle",
    "Scheduled Start Date"      => "startdate",
    "Scheduled Start Time"      => "starttime",
    "Requested Procedure ID"    => "reqprocid",
);
$WORKLIST_TBL = array(
    "patientname"           => "lastname",
    "patientid"             => "patientid",
    "birthdate"             => "birthdate",
    "sex"                   => "sex",
    "accessionnum"          => "accessionnum",
    "referringphysician"    => "referringphysician",
    "requestingphysician"   => "requestingphysician",
    "admittingdiagnoses"    => "diagnose",
    "patienthistory"        => "history",
    "institution"           => "institution",
);
$WORKLIST_REQPROC_TBL = array(
    "id"                    => "reqprocid",
    "description"           => "reqprocdescription",
    "priority"              => "reqprocpriority",
);
$WORKLIST_SCHEDPS_TBL = array(
    "aetitle"               => "aetitle",
    "modality"              => "modality",
    "startdate"             => "startdate",
    "starttime"             => "starttime",
    "performingphysician"   => "performingphysician",
    "id"                    => "schedpsid",
    "description"           => "schedpsdescription",
    "location"              => "schedpsloc",
);
$WORKLIST_PROC_CODE_TBL = array(
    "value"                 => "procodevalue",
    "meaning"               => "procodemeaning",
    "schemedesignator"      => "procodeschemedesignator",
    "schemeversion"         => "procodeschemeversion",
);
$WORKLIST_PROTO_CODE_TBL = array(
    "value"                 => "protocodevalue",
    "meaning"               => "protocodemeaning",
    "schemedesignator"      => "protocodeschemedesignator",
    "schemeversion"         => "protocodeschemeversion",
);
$WORKLIST_ALL_TABLES = array(
    "worklist"              => $WORKLIST_TBL,
    "requestedprocedure"    => $WORKLIST_REQPROC_TBL,
    "scheduledps"           => $WORKLIST_SCHEDPS_TBL,
    "procedurecode"         => $WORKLIST_PROC_CODE_TBL,
    "protocolcode"          => $WORKLIST_PROTO_CODE_TBL,
);

// main
global $PRODUCT;
$dbcon = new MyConnection();
if (isset($_POST['action']))
   	$action = $_POST['actionvalue'];
$result = NULL;
if (isset($action) && strcasecmp($action, "Add") == 0) {
    addEntry($dbcon, $result);
} else if (isset($action) && strcasecmp($action, "Modify") == 0) {
    $uid = $_POST["uid"];
    modifyEntry($uid, $dbcon, $result);
    if (empty($result)) {   // success
        header('Location: worklist.php?uid=' . urlencode($uid));
        exit();
    }
}
else {
    $uid = isset($_REQUEST["uid"])? $_REQUEST["uid"] : "";
    if (strlen($uid) && !isUidValid($uid)) {
        $error = sprintf(pacsone_gettext("Invalid UID: %s"), $uid);
        print "<font color=red><h2>$error</h2></font>";
        exit();
    }
    entryForm($dbcon, $uid);
}
if (isset($result)) {       // display results
    if (empty($result)) {   // success
        header('Location: worklist.php');
    }
    else {                  // error
        print "<html>\n";
        print "<head><title>$PRODUCT - ";
        print pacsone_gettext("Modality Worklist Input Error");
        print "</title></head>\n";
        print "<body>\n";
        require_once 'header.php';
        print "<font color=red><h2>$result</h2></font>";
        require_once 'footer.php';
        print "</body>\n";
        print "</html>\n";
    }
}

function entryForm(&$dbcon, $uid)
{
    include_once 'checkUncheck.js';
    global $PRODUCT;
    global $AUTH_TBL;
    // display Manually Enter Worklist Data form
    print "<html>\n";
    print "<head><title>$PRODUCT - ";
    print pacsone_gettext("Manually Enter Worklist Data");
    print "</title></head>\n";
    print "<body>\n";
    require_once 'header.php';
    print "<p>";
    print pacsone_gettext("Enter the following information for worklist data: (<b>High-lighted fields are required</b>)") . "<p>";
    print "<form method='POST' action='enterWorklist.php'>\n";
    print "<input type='hidden' name='actionvalue'>\n";
    print "<p><table border=1 cellpadding=2 cellspacing=0>\n";
    $eurodate = $dbcon->isEuropeanDateFormat();
    if ($dbcon->useOracle) {
        // setup NLS date format
        $query = sprintf("alter session set NLS_DATE_FORMAT='%s'", $eurodate? "DD-MM-YYYY" : "YYYY-MM-DD");
        $dbcon->query($query);
    }
    print "<tr><td>";
    global $CUSTOMIZE_PATIENT_ID;
    print "<b>$CUSTOMIZE_PATIENT_ID:</b></td>\n";
    if (strlen($uid)) {
        $query = "select * from worklist where studyuid=?";
        $bindList = array($uid);
        $result = $dbcon->preparedStmt($query, $bindList);
        if ($result && $result->rowCount())
            $worklistRow = $result->fetch(PDO::FETCH_ASSOC);
    }
    $value = isset($worklistRow)? $worklistRow["patientid"] : "";
    if (!strlen($value))
        $value = isset($_POST["patientid"])? $_POST["patientid"] : "";
    print "<td class='RequiredField'><input type='text' size=32 maxlength=64 name='patientid' value='$value'>";
    print "&nbsp;<input class='btn btn-primary' type='submit' name='action' value='";
    print pacsone_gettext("Search");
    print "' onclick='switchText(this.form,\"actionvalue\",\"Search\")'>\n";
    print "</td></tr>\n";
    $lastname = "";
    $firstname = "";
    $middlename = "";
    $sex = "";
    $birthdate = "";
    if (isset($worklistRow)) {
        $tokens = explode("^", $worklistRow["patientname"]);
        if (isset($tokens[0]))
            $lastname = $tokens[0];
        if (isset($tokens[1]))
            $firstname = $tokens[1];
        if (isset($tokens[2]))
            $middlename = $tokens[2];
    } else if (isset($_POST["action"]) && strcasecmp($_POST["actionvalue"], "Search") == 0) {
        $patientid = isset($_POST["patientid"])? $_POST["patientid"] : "";
        if (strlen($patientid)) {
            $query = "select lastname,firstname,middlename,sex,birthdate from patient where origid=?";
            $bindList = array($patientid);
            $result = $dbcon->preparedStmt($query, $bindList);
            if ($result && ($row = $result->fetch(PDO::FETCH_NUM))) {
                $lastname = $row[0];
                $firstname = $row[1];
                $middlename = $row[2];
                if (isset($row[3]) && strlen($row[3]))
                    $sex = $row[3][0];
                if (isset($row[4]) && strlen($row[4]))
                    $birthdate = $row[4];
            }
        }
    }
    global $CUSTOMIZE_PATIENT_NAME;
    print "<tr><td><b>$CUSTOMIZE_PATIENT_NAME:</b></td>\n";
    print "<td class='RequiredField'>" . pacsone_gettext("Lastname: ");
    print "<input type='text' size=20 maxlength=64 name='lastname' value='$lastname'>&nbsp;";
    print pacsone_gettext("Firstname: ");
    print "<input type='text' size=20 maxlength=64 name='firstname' value='$firstname'>&nbsp;";
    print pacsone_gettext("Middlename: ");
    print "<input type='text' size=20 maxlength=64 name='middlename' value='$middlename'>&nbsp;";
    print "</td></tr>\n";
    global $CUSTOMIZE_PATIENT_SEX;
    print "<tr><td>$CUSTOMIZE_PATIENT_SEX:</td>\n";
    $male = "checked";
    $female = "";
    if (isset($worklistRow)) {
        $sex = $worklistRow["sex"];
    }
    if (strlen($sex)) {
        $male = strncasecmp($sex, "M", 1)? "" : "checked";
        $female = strncasecmp($sex, "M", 1)? "checked" : "";
    }
    print "<td><input type=radio name='sex' value='M' $male>";
    print pacsone_gettext("Male");
    print "&nbsp;<input type=radio name='sex' value='F' $female>";
    print pacsone_gettext("Female");
    print "</td></tr>";
    print "<tr><td>";
    print "<b>";
    global $CUSTOMIZE_PATIENT_DOB;
    printf("%s %s:", $CUSTOMIZE_PATIENT_DOB, $eurodate? pacsone_gettext("(DD-MM-YYYY)") : pacsone_gettext("(YYYY-MM-DD)"));
    print "</b></td>";
    if (isset($worklistRow))
        $birthdate = $worklistRow["birthdate"];
    if ($eurodate)
        $birthdate = reverseDate($birthdate);
    print "<td class='RequiredField'><input type='text' size=32 maxlength=32 name='birthdate' value='$birthdate'></td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Additional Patient History:") . "</b></td>";
    $history = (isset($worklistRow) && strlen($worklistRow["patienthistory"]))? $worklistRow["patienthistory"] : "";
    print "<td><textarea name='history' rows=25 cols=80 wrap='virtual'>$history</textarea>";
    print "</td></tr>";
    print "<tr><td>";
    print pacsone_gettext("Institution Name:") . "</td><td>";
    $value = isset($worklistRow)? $worklistRow["institution"] : "";
    print "<input type=text name='institution' size=64 maxlength=64 value='$value'>";
    print "</td></tr>\n";
    print "<tr><td>";
    print "<b>" . pacsone_gettext("Accession Number:") . "</b></td>";
    if (isset($worklistRow)) {
        $accession = $worklistRow["accessionnum"];
    } else {
        $accession = $eurodate? date("dmY-His") : date("Ymd-His");
    }
    print "<td class='RequiredField'><input type=text name='accessionnum' size=16 maxlength=16 value='$accession'>";
    print "</td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Admitting Diagnoses Description:") . "</td><td>";
    $diagnose = isset($worklistRow)? htmlentities($worklistRow["admittingdiagnoses"], ENT_QUOTES) : "";
    print "<input type=text name='diagnose' size=64 maxlength=64 value=\"$diagnose\">";
    print "</td></tr>\n";
    global $CUSTOMIZE_REFERRING_DOC;
    print "<tr><td>$CUSTOMIZE_REFERRING_DOC:</td><td>";
    $referdoc = isset($worklistRow)? $worklistRow["referringphysician"] : "";
    print "<input type=text name='referringphysician' size=64 maxlength=64 value='$referdoc'>";
    print "</td></tr>\n";
    global $CUSTOMIZE_REQUESTING_DOC;
    print "<tr><td>$CUSTOMIZE_REQUESTING_DOC</td><td>";
    $requestdoc = isset($worklistRow)? $worklistRow["requestingphysician"] : "";
    print "<input type=text name='requestingphysician' size=64 maxlength=64 value='$requestdoc'>";
    print "</td></tr>\n";
    if (strlen($uid)) {
        $query = "select * from requestedprocedure where studyuid=?";
        $bindList = array($uid);
        $result = $dbcon->preparedStmt($query, $bindList);
        if ($result && $result->rowCount())
            $reqprocRow = $result->fetch(PDO::FETCH_ASSOC);
    }
    print "<tr><td>";
    print "<b>" . pacsone_gettext("Requested Procedure ID:") . "</td><td class='RequiredField'>";
    $id = isset($reqprocRow)? $reqprocRow["id"] : "";
    print "<input type=text name='reqprocid' size=16 maxlength=16 value='$id'>";
    print "</td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Requested Procedure Description:") . "</td><td>";
    $desc = isset($reqprocRow)? $reqprocRow["description"] : "";
    print "<input type=text name='reqprocdescription' size=64 maxlength=64 value='$desc'>";
    print "</td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Requested Procedure Priority:") . "</td><td>";
    $priority = isset($reqprocRow)? $reqprocRow["priority"] : "";
    print "<input type=text name='reqprocpriority' size=16 maxlength=16 value='$priority'>";
    print "</td></tr>\n";
    if (strlen($uid)) {
        $query = "select * from scheduledps where studyuid=?";
        $bindList = array($uid);
        $result = $dbcon->preparedStmt($query, $bindList);
        if ($result && $result->rowCount())
            $scheduledpsRow = $result->fetch(PDO::FETCH_ASSOC);
    }
    print "<tr><td>";
    print "<b>" . pacsone_gettext("Scheduled AE Station:") . "</b></td>";
    $aetitle = isset($scheduledpsRow)? $scheduledpsRow["aetitle"] : "";
    print "<td class='RequiredField'><input type=text name='aetitle' size=16 maxlength=16 value='$aetitle'>";
    print "</td></tr>\n";
    print "<tr><td>";
    print "<b>" . pacsone_gettext("Modality:") . "</b></td>";
    $modality = isset($scheduledpsRow)? $scheduledpsRow["modality"] : "";
    print "<td class='RequiredField'><input type=text name='modality' size=16 maxlength=16 value='$modality'>";
    print "</td></tr>\n";
    print "<tr><td>";
    print "<b>";
    printf(pacsone_gettext("Scheduled Start Date %s:"), $eurodate? pacsone_gettext("(DD-MM-YYYY)") : pacsone_gettext("(YYYY-MM-DD)"));
    print "</b></td>";
    if (isset($scheduledpsRow)) {
        $startdate = $scheduledpsRow["startdate"];
        if ($eurodate)
            $startdate = reverseDate($startdate);
    } else {
        $startdate = $eurodate? date("d-m-Y") : date("Y-m-d");
    }
    print "<td class='RequiredField'><input type=text name='startdate' size=32 maxlength=32 value='$startdate'>";
    print "</td></tr>\n";
    print "<tr><td>";
    print "<b>" . pacsone_gettext("Scheduled Start Time (HH:mm:ss):") . "</td>";
    $starttime = isset($scheduledpsRow)? $scheduledpsRow["starttime"] : date("H:i:s");
    print "<td class='RequiredField'><input type=text name='starttime' size=32 maxlength=32 value='$starttime'>";
    print "</td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Performing Physician:") . "</td><td>";
    $performdoc = isset($scheduledpsRow)? $scheduledpsRow["performingphysician"] : "";
    print "<input type=text name='performingphysician' size=64 maxlength=64 value='$performdoc'>";
    print "</td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Scheduled Procedure ID:") . "</td><td>";
    $id = isset($scheduledpsRow)? $scheduledpsRow["id"] : "";
    print "<input type=text name='schedpsid' size=16 maxlength=16 value='$id'>";
    print "</td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Scheduled Procedure Description:") . "</td><td>";
    $desc = isset($scheduledpsRow)? $scheduledpsRow["description"] : "";
    print "<input type=text name='schedpsdescription' size=64 maxlength=64 value='$desc'>";
    print "</td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Scheduled Procedure Location:") . "</td><td>";
    $location = isset($scheduledpsRow)? $scheduledpsRow["location"] : "";
    print "<input type=text name='schedpsloc' size=16 maxlength=16 value='$location'>";
    print "</td></tr>\n";
    if (strlen($uid)) {
        $query = "select * from procedurecode where studyuid=?";
        $bindList = array($uid);
        $result = $dbcon->preparedStmt($query, $bindList);
        if ($result && $result->rowCount())
            $proccodeRow = $result->fetch(PDO::FETCH_ASSOC);
    }
    print "<tr><td>";
    print pacsone_gettext("Procedure Code Value:") . "</td><td>";
    $value = isset($proccodeRow)? $proccodeRow["value"] : "";
    print "<input type=text name='procodevalue' size=16 maxlength=16 value='$value'>";
    print "</td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Procedure Code Meaning:") . "</td><td>";
    $meaning = isset($proccodeRow)? $proccodeRow["meaning"] : "";
    print "<input type=text name='procodemeaning' size=64 maxlength=64 value='$meaning'>";
    print "</td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Procedure Code Scheme Designator:") . "</td><td>";
    $designator = isset($proccodeRow)? $proccodeRow["schemedesignator"] : "";
    print "<input type=text name='procodeschemedesignator' size=16 maxlength=16 value='$designator'>";
    print "</td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Procedure Code Scheme Version:") . "</td><td>";
    $version = isset($proccodeRow)? $proccodeRow["schemeversion"] : "";
    print "<input type=text name='procodeschemeversion' size=16 maxlength=16 value='$version'>";
    print "</td></tr>\n";
    if (strlen($uid)) {
        $query = "select * from protocolcode where studyuid=?";
        $bindList = array($uid);
        $result = $dbcon->preparedStmt($query, $bindList);
        if ($result && $result->rowCount())
            $protocodeRow = $result->fetch(PDO::FETCH_ASSOC);
    }
    print "<tr><td>";
    print pacsone_gettext("Protocol Code Value:") . "</td><td>";
    $value = isset($protocodeRow)? $protocodeRow["value"] : "";
    print "<input type=text name='protocodevalue' size=16 maxlength=16 value='$value'>";
    print "</td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Protocol Code Meaning:") . "</td><td>";
    $meaning = isset($protocodeRow)? $protocodeRow["meaning"] : "";
    print "<input type=text name='protocodemeaning' size=64 maxlength=64 value='$meaning'>";
    print "</td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Protocol Code Scheme Designator:") . "</td><td>";
    $designator = isset($protocodeRow)? $protocodeRow["schemedesignator"] : "";
    print "<input type=text name='protocodeschemedesignator' size=16 maxlength=16 value='$designator'>";
    print "</td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Protocol Code Scheme Version:") . "</td><td>";
    $version = isset($protocodeRow)? $protocodeRow["schemeversion"] : "";
    print "<input type=text name='protocodeschemeversion' size=16 maxlength=16 value='$version'>";
    print "</td></tr>\n";
    print "</table>\n";
    if (strlen($uid))
        print "<input type=hidden name='uid' value='$uid'>";
    print "<p><input class='btn btn-primary' type='submit' name='action' value='";
    $value = strlen($uid)? pacsone_gettext("Modify") : pacsone_gettext("Add");
    print $value;
    $value = strlen($uid)? "Modify" : "Add";
    print "' onclick='switchText(this.form,\"actionvalue\",\"$value\")'></form>\n";
    require_once 'footer.php';
    print "</body>\n";
    print "</html>\n";
}

function addEntry(&$dbcon, &$error)
{
    global $WORKLIST_REQ_FIELDS;
    global $WORKLIST_ALL_TABLES;
    $error = "";
    $eurodate = $dbcon->isEuropeanDateFormat();
    // check if all required keys are present
    foreach ($WORKLIST_REQ_FIELDS as $key => $field) {
        if (!isset($_POST[$field]) || !strlen($_POST[$field])) {
            $error = sprintf(pacsone_gettext("Required worklist data filed: <b>%s</b> is missing"), $key);
            return 0;
        }
    }
    $allOk = 0;
    $uid = MakeStudyUid();
    foreach ($WORKLIST_ALL_TABLES as $name => $table) {
        $empty = true;
        $query = "insert into $name (";
        $columns = array();
        $bindList = array();
        $columns[] = "studyuid";
        $values = "?";
        $bindList[] = $uid;
        foreach ($table as $coln => $postdata) {
            if (isset($_POST[ $postdata ])) {
                $value = $_POST[ $postdata ];
                // valid user input
                if (strstr($value, "<") || strstr($value, "</")) {
                    $error = pacsone_gettext("Invalid character in user input");
                    return 0;
                }
                if (!strcasecmp($name, "worklist") && !strcasecmp($coln, "patientname")) {
                    if (isset($_POST['firstname'])) {
                        $firstname = $_POST['firstname'];
                        $value .= "^" . $firstname;
                    }
                    if (isset($_POST['middlename'])) {
                        $middlename = $_POST['middlename'];
                        $value .= "^" . $middlename;
                    }
                    // valid user input
                    if (strstr($value, "<") || strstr($value, "</")) {
                        $error = pacsone_gettext("Invalid character in Patient Names");
                        return 0;
                    }
                } else if ($eurodate) {
                    if (!strcasecmp($coln, "startdate") || !strcasecmp($coln, "birthdate"))
                        $value = reverseDate($value);
                }
                if (strlen($value)) {
                    $columns[] = $coln;
                    $values .= ",";
                    if ($dbcon->useOracle && (!strcasecmp($coln, "startdate") ||
                        !strcasecmp($coln, "birthdate"))) {
                        $values .= sprintf("TO_DATE(?,'%s')", strstr($value, "-")? "YYYY-MM-DD" : "YYYYMMDD");
                    } else
                        $values .= "?";
                    $bindList[] = $value;
                    $empty = false;
                }
            }
        }
        if ($empty)
            continue;
        // fill in 'received' column for WORKLIST table
        if (!strcasecmp($name, "worklist") && !isset($columns['received'])) {
            $columns[] = "received";
            $values .= "," . ($dbcon->useOracle? "SYSDATE" : "NOW()");
        }
        for ($i = 0; $i < count($columns); $i++) {
            if ($i)
                $query .= ",";
            $query .= $columns[$i];
        }
        $query .= ") values($values)";
        if ($dbcon->preparedStmt($query, $bindList))
            $allOk++;
        else {
            $error .= pacsone_gettext("Error running query: ");
                $error .= $query;
            $error .= "<p>" . $dbcon->getError();
        }
    }
}

function modifyEntry($uid, &$dbcon, &$error)
{
    global $WORKLIST_REQ_FIELDS;
    global $WORKLIST_ALL_TABLES;
    $error = "";
    $eurodate = $dbcon->isEuropeanDateFormat();
    // check if all required keys are present
    foreach ($WORKLIST_REQ_FIELDS as $key => $field) {
        if (!isset($_POST[$field]) || !strlen($_POST[$field])) {
            $error = sprintf(pacsone_gettext("Required worklist data filed: <b>%s</b> is missing"), $key);
            return 0;
        }
    }
    $allOk = 0;
    foreach ($WORKLIST_ALL_TABLES as $name => $table) {
        $empty = true;
        $query = "update $name set ";
        $bindList = array();
        $updates = "";
        foreach ($table as $coln => $postdata) {
            if (isset($_POST[ $postdata ])) {
                $value = $_POST[ $postdata ];
                // valid user input
                if (strstr($value, "<") || strstr($value, "</")) {
                    $error = pacsone_gettext("Invalid character in user input");
                    return 0;
                }
                $value = get_magic_quotes_gpc()? $value : addslashes($value);
                if (!strcasecmp($name, "worklist") && !strcasecmp($coln, "patientname")) {
                    if (isset($_POST['firstname'])) {
                        $firstname = $_POST['firstname'];
                        $firstname = get_magic_quotes_gpc()? $firstname : addslashes($firstname);
                        $value .= "^" . $firstname;
                    }
                    if (isset($_POST['middlename'])) {
                        $middlename = $_POST['middlename'];
                        $middlename = get_magic_quotes_gpc()? $middlename : addslashes($middlename);
                        $value .= "^" . $middlename;
                    }
                    // valid user input
                    if (strstr($value, "<") || strstr($value, "</")) {
                        $error = pacsone_gettext("Invalid character in Patient Names");
                        return 0;
                    }
                } else if ($eurodate) {
                    if (!strcasecmp($coln, "startdate") || !strcasecmp($coln, "birthdate"))
                        $value = reverseDate($value);
                }
                if (strlen($value)) {
                    if (strlen($updates))
                        $updates .= ",";
                    if ($dbcon->useOracle && (!strcasecmp($coln, "startdate") ||
                        !strcasecmp($coln, "birthdate"))) {
                        $updates .= sprintf("%s=TO_DATE(?,'%s')", $coln, strstr($value, "-")? "YYYY-MM-DD" : "YYYYMMDD");
                    } else
                        $updates .= "$coln=?";
                    $bindList[] = $value;
                    $empty = false;
                }
            }
        }
        if ($empty)
            continue;
        $query .= $updates;
        $query .= " where studyuid=?";
        $bindList[] = $uid;
        if ($dbcon->preparedStmt($query, $bindList))
            $allOk++;
        else {
            $error .= pacsone_gettext("Error running query: ");
            $error .= $query;
            $error .= "<p>" . $dbcon->getError();
        }
    }
}

?>
