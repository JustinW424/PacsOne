<?php
//
// searchPatient.php
//
// Module for searching patients information
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once 'security.php';
include_once 'display.php';

global $PRODUCT;
print "<html>";
print "<head>";
print "<title>$PRODUCT - ";
print pacsone_gettext("Patient Search Results:");
print "</title></head>";
print "<body>";

require_once 'header.php';
include_once 'utils.php';

$id = urldecode($_REQUEST['id']);
$last = urldecode($_REQUEST['lastname']);
$first = urldecode($_REQUEST['firstname']);
$compare = "";
if (isset($_REQUEST['compare']))
    $compare = urldecode($_REQUEST['compare']);
$fromdate = "";
if (isset($_REQUEST['fromdate']))
    $fromdate = urldecode($_REQUEST['fromdate']);
$todate = "";
if (isset($_REQUEST['todate']))
    $todate = urldecode($_REQUEST['todate']);
$studydate = 0;
if ( (isset($_REQUEST['studyfromdate']) && strlen($_REQUEST['studyfromdate'])) ||
     (isset($_REQUEST['studytodate']) && strlen($_REQUEST['studytodate'])) ||
     (isset($_REQUEST['studydate']) && ($_REQUEST['studydate'] == 1)) )
    $studydate = 1;
$studycompare = "";
if (isset($_REQUEST['studycompare']))
    $studycompare = urldecode($_REQUEST['studycompare']);
$institution = "";
if (isset($_REQUEST['institution']))
    $institution = $_REQUEST['institution'];
$sort = "cmp_timestamp";
if (isset($_REQUEST['sort']))
    $sort = $_REQUEST['sort'];

$dbcon = new MyConnection();
$username = $dbcon->username;
// automatically append wild-card character
if (isset($_REQUEST['wildid']))
    $id .= "*";
if (isset($_REQUEST['wildname'])) {
    if (strlen($last))
        $last .= "*";
    if (strlen($first))
        $first .= "*";
}
$error = searchPatient($username, $id, $last, $first, $compare, $fromdate, $todate, $studydate, $institution, $sort);
if ($error) {
    print "<font color=red>$error</font>";
}
require_once 'footer.php';
print "</body>";
print "</html>";

function searchPatient($username, $id, $last, $first, $compare, $fromdate, $todate, $studydate, $institution, $sort)
{
    global $dbcon;
    $viewAccess = $dbcon->hasaccess("viewprivate", $username);
	$error = "";
    $eurodate = $dbcon->isEuropeanDateFormat();
    // make sure first char of any search pattern is not a wild-card char
    if (strcmp($id, "*") && (isWildcardFirst($id) || isWildcardFirst($last) ||
        isWildcardFirst($first))) {
        return pacsone_gettext("First character of patient search pattern cannot be wild-card chars like '*' or '?'");
    }
    if (strlen($fromdate) && !isDateValid($fromdate, $eurodate))
        return sprintf(pacsone_gettext("Invalid DATE: %s, please use %s format"),
            $fromdate,
            ($eurodate)? "DD-MM-YYYY" : "YYYY-MM-DD");
    if (strlen($todate) && !isDateValid($todate, $eurodate))
        return sprintf(pacsone_gettext("Invalid TO DATE: %s, please use %s format"),
            $todate,
            ($eurodate)? "DD-MM-YYYY" : "YYYY-MM-DD");
    if ($studydate) {
        $studycompare = $_REQUEST['studycompare'];
        $studyfromdate = $_REQUEST['studyfromdate'];
        $studytodate = "";
        if (!strcasecmp($studycompare, "FROM"))
            $studytodate = $_REQUEST['studytodate'];
        if (!isDateValid($studyfromdate, $eurodate))
            return sprintf(pacsone_gettext("Invalid FROM STUDY DATE, please use %s format"),
                ($eurodate)? "DD-MM-YYYY" : "YYYY-MM-DD");
        if (!strcasecmp($studycompare, "FROM") && !isDateValid($studytodate, $eurodate))
            return sprintf(pacsone_gettext("Invalid TO STUDY DATE, please use %s format"),
                ($eurodate)? "DD-MM-YYYY" : "YYYY-MM-DD");
    }
    if (!strcasecmp($compare, "From") && strlen($fromdate) && !strlen($todate))
        return pacsone_gettext("TO DATE must be specified for From-To Date Range");
    if ($studydate && !strcasecmp($studycompare, "From") && strlen($studyfromdate) && !strlen($studytodate))
        return pacsone_gettext("TO STUDY DATE must be specified for From-To Date Range");

    if ($studydate) {
	    $query = "SELECT DISTINCT * from patient JOIN study on patient.origid = study.patientid where ";
    } else {
	    $query = "SELECT * from patient where ";
    }
    $key = "";
    $value = "";
    $bindList = array();
    // build query string based on form input
    if (strlen($id)) {
        if (strlen($key))
            $key .= " AND ";
        $key .= "origid" . preparedStmtWildcard($id, $value);
        $bindList[] = $value;
    }
    if (strlen($last)) {
        if (strlen($key))
            $key .= " AND ";
        $key .= "lastname" . preparedStmtWildcard($last, $value);
        $bindList[] = $value;
    }
    if (strlen($first)) {
        if (strlen($key))
            $key .= " AND ";
        $key .= "firstname" . preparedStmtWildcard($first, $value);
        $bindList[] = $value;
    }
    if (isset($_REQUEST["fullname"]) && strlen($_REQUEST["fullname"])) {
        $fullname = $_REQUEST["fullname"];
        if (strlen($key))
            $key .= " AND ";
        $subkey = "LOCATE(?, CONCAT(firstname,' ',lastname)) != 0 OR ";
        $subkey .= "LOCATE(?, CONCAT(lastname,' ',firstname)) != 0 OR ";
        $subkey .= "LOCATE(?, CONCAT(firstname,' ',middlename,' ',lastname)) != 0 OR ";
        $subkey .= "LOCATE(?, CONCAT(lastname,' ',firstname,' ',middlename)) != 0";
        if ($dbcon->useOracle) {
            $subkey = "INSTR(UPPER(firstname||' '||lastname),UPPER(?)) != 0 OR ";
            $subkey .= "INSTR(UPPER(lastname||' '||firstname),UPPER(?)) != 0 OR ";
            $subkey .= "INSTR(UPPER(firstname||' '||middlename||' '||lastname),UPPER(?)) != 0 OR ";
            $subkey .= "INSTR(UPPER(lastname||' '||firstname||' '||middlename),UPPER(?)) != 0";
        }
        $key .= "($subkey)";
        array_push($bindList, $fullname, $fullname, $fullname, $fullname);
    }
    if (strlen($fromdate) || strlen($todate)) {
        $sqlfromdate = ($eurodate)? reverseDate($fromdate) : $fromdate;
        $sqltodate = ($eurodate)? reverseDate($todate) : $todate;
        if (strlen($key))
            $key .= " AND ";
        $key .= "(birthdate ";
        if (!strcasecmp($compare, "Equal")) {
            $key .= "= ?";
            $bindList[] = $sqlfromdate;
        } else if (!strcasecmp($compare, "Before")) {
            $key .= "< ?";
            $bindList[] = $sqlfromdate;
        }
        else if (!strcasecmp($compare, "After")) {
            $key .= "> ?";
            $bindList[] = $sqlfromdate;
        }
        else if (!strcasecmp($compare, "From")) {
            $key .= ">= ? AND birthdate <= ?";
            array_push($bindList, $sqlfromdate, $sqltodate);
        }
        $key .= ")";
    }
    if ($studydate) {
        $sqlfromdate = ($eurodate)? reverseDate($studyfromdate) : $studyfromdate;
        $sqltodate = ($eurodate)? reverseDate($studytodate) : $studytodate;
        if (strlen($key))
            $key .= " AND ";
        $key .= "(study.studydate ";
        if (strcasecmp($studycompare, "EQUAL") == 0) {
            $key .= "= ?";
            $bindList[] = $sqlfromdate;
        } else if (strcasecmp($studycompare, "BEFORE") == 0) {
            $key .= "< ?";
            $bindList[] = $sqlfromdate;
        } else if (strcasecmp($studycompare, "AFTER") == 0) {
            $key .= "> ?";
            $bindList[] = $sqlfromdate;
        } else if (strcasecmp($studycompare, "FROM") == 0) {
            $key .= ">= ? AND study.studydate <= ?";
            array_push($bindList, $sqlfromdate, $sqltodate);
        } else {
            die (sprintf(pacsone_gettext("Invalid Study Date Comparator: %s"), $studycompare));
        }
        $key .= ")";
    }
    if (strlen($institution)) {
        if (strlen($key))
            $key .= " AND ";
        $key .= "institution" . preparedStmtWildcard($institution, $value);
        $bindList[] = $value;
    }
    if (strlen($key) == 0) {
        $error = "<br>";
        global $CUSTIMIZE_SEARCH_BY_PATIENT;
        $error .= sprintf(pacsone_gettext("You must enter a criteria for <a href='search.php'>%s</a>"), $CUSTIMIZE_SEARCH_BY_PATIENT);
        return $error;
    }
    $query .= $key;
	$offset = 0;
	if (isset($_REQUEST['offset']))
		$offset = $_REQUEST['offset'];
    $all = 0;
    if (isset($_REQUEST['all']))
	    $all = $_REQUEST['all'];
    if (count($bindList))
        $result = $dbcon->preparedStmt($query, $bindList);
    else
        $result = $dbcon->query($query);
    if ($result) {
        $rows = array();
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $private = $row['private'];
            if ($viewAccess || !$private || $dbcon->accessPatient($row['origid'], $username))
                $rows[] = $row;
        }
        // sort the rows based on the 'lastaccess' timestamp
        $toggle = 0;
        if (isset($_SESSION['lastSort']) && !strcasecmp($sort, $_SESSION['lastSort']) && isset($_REQUEST['toggle'])
            && ($toggle != $_REQUEST['toggle'])) {
            $toggle = 1 - $toggle;
        }
        my_usort($rows, $sort, $toggle);
        $_SESSION['lastSort'] = $sort;
        $_SESSION['sortToggle'] = $toggle;
        $num_rows = sizeof($rows);
        $url = "searchPatient.php?id=" . urlencode($id) . "&lastname=";
        $url .= urlencode($last) . "&firstname=" . urlencode($first);
        $url .= "&compare=" . urlencode($compare) . "&fromdate=";
        $url .= urlencode($fromdate) . "&todate=" . urlencode($todate) . "&toggle=$toggle";
        if ($studydate) {
            $url .= "&studycompare=" . urlencode($studycompare);
            $url .= "&studyfromdate=" . urlencode($studyfromdate);
            $url .= "&studytodate=" . urlencode($studytodate);
        }
        if (strlen($institution)) {
            $url .= "&institution=" . urlencode($institution);
        }
        $url .= "&sort=$sort";
        $preface = sprintf(pacsone_gettext("%d accessible patients found in PACS database."), $num_rows);
        displayPatients($rows, $preface, $url, $offset, $all);
    }
    return $error;
}

?>
