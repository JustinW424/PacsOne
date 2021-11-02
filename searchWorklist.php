<?php
//
// searchWorklist.php
//
// Module for searching DMWL information
//
// CopyRight (c) 2006-2020 RainbowFish Software
//
session_start();

require_once "locale.php";
include_once 'security.php';
include_once 'display.php';
require_once 'utils.php';

global $PRODUCT;
print "<html>";
print "<head>";
print "<title>$PRODUCT - " . pacsone_gettext("Worklist Search Results:") . "</title>";
print "</head>";
print "<body>";

require_once 'header.php';

$uid = $_REQUEST['studyuid'];
$id = $_REQUEST['patientid'];
$name = $_REQUEST['patientname'];
$compare = $_REQUEST['compare'];
$accession = $_REQUEST['accession'];
$modality = $_REQUEST['modality'];
$date = 0;
if ( (isset($_REQUEST['fromdate']) && strlen($_REQUEST['fromdate'])) ||
     (isset($_REQUEST['todate']) && strlen($_REQUEST['todate'])) ||
     (isset($_REQUEST['date']) && ($_REQUEST['date'] == 1)) )
    $date = 1;
$scheduledAe = "";
if (isset($_REQUEST['scheduledae']))
    $scheduledAe = $_REQUEST['scheduledae'];
$dbcon = new MyConnection();
$username = $dbcon->username;
$error = searchWorklist($username, $uid, $id, $name, $compare, $date, $accession, $modality, $scheduledAe);
if ($error) {
    print "<font color=red>$error</font>";
}
require_once 'footer.php';
print "</body>";
print "</html>";

function searchWorklist($username, $uid, $id, $name, $compare, $date, $accession, $modality, $scheduledAe)
{
    global $dbcon;
    $access = $dbcon->hasaccess("viewprivate", $username);
	$error = "";
    $eurodate = $dbcon->isEuropeanDateFormat();
    // check if UID format is valid
    if (strlen($uid)) {
        if (!isUidValid($uid)) {
            return pacsone_gettext("Invalid Study UID");
        }
        // make sure first char of any search pattern is not a wild-card char
        if (isWildcardFirst($id) || isWildcardFirst($uid)) {
            return pacsone_gettext("First character of study search pattern cannot be wild-card chars like '*' or '?'");
        }
    }
    // check if valid date format
    if ($date) {
        $fromdate = $_REQUEST['fromdate'];
        $todate = "";
        if (!strcasecmp($compare, "FROM"))
            $todate = $_REQUEST['todate'];
        if (!isDateValid($fromdate, $eurodate))
            return sprintf(pacsone_gettext("Invalid FROM DATE, please use %s format"),
                ($eurodate)? "DD-MM-YYYY" : "YYYY-MM-DD");
        if (!strcasecmp($compare, "FROM") && !isDateValid($todate, $eurodate))
            return sprintf(pacsone_gettext("Invalid TO DATE, please use %s format"),
                ($eurodate)? "DD-MM-YYYY" : "YYYY-MM-DD");
        if ($eurodate) {
            $fromdate = reverseDate($fromdate);
            $todate = reverseDate($todate);
        }
    }
    $referdoc = (isset($_REQUEST['referdoc']) && strlen($_REQUEST['referdoc']))? $_REQUEST['referdoc'] : "";
    $requestdoc = (isset($_REQUEST['requestdoc']) && strlen($_REQUEST['requestdoc']))? $_REQUEST['requestdoc'] : "";
	$query = "SELECT DISTINCT * FROM worklist INNER JOIN scheduledps ON worklist.studyuid = scheduledps.studyuid where ";
    $key = "";
    $value = "";
    $bindList = array();
    // build query string based on form input
    if (strlen($uid)) {
        if (strlen($key))
            $key .= " AND ";
        $key .= "worklist.studyuid" . preparedStmtWildcard($uid, $value);
        $bindList[] = $value;
    }
    if (strlen($id)) {
        if (strlen($key))
            $key .= " AND ";
        $key .= "patientid" . preparedStmtWildcard($id, $value);
        $bindList[] = $value;
    }
    if (strlen($name)) {
        if (strlen($key))
            $key .= " AND ";
        $key .= "patientname" . preparedStmtWildcard($name, $value);
        $bindList[] = $value;
    }
    if ($date) {
        if (strlen($key))
            $key .= " AND ";
        $key .= "(scheduledps.startdate ";
        if (strcasecmp($compare, "EQUAL") == 0) {
            $key .= "= ?";
            $bindList[] = $fromdate;
        } else if (strcasecmp($compare, "BEFORE") == 0) {
            $key .= "< ?";
            $bindList[] = $fromdate;
        } else if (strcasecmp($compare, "AFTER") == 0) {
            $key .= "> ?";
            $bindList[] = $fromdate;
        } else if (strcasecmp($compare, "FROM") == 0) {
            $key .= ">= ? AND scheduledps.startdate <= ?";
            array_push($bindList, $fromdate, $todate);
        } else {
            die (sprintf(pacsone_gettext("Invalid Date Comparator: %s"), $compare));
        }
        $key .= ")";
    }
    if (strlen($accession)) {
        if (strlen($key))
            $key .= " AND ";
        $key .= "accessionnum" . preparedStmtWildcard($accession, $value);
        $bindList[] = $value;
    }
    if (strlen($modality)) {
        if (strlen($key))
            $key .= " AND ";
        $key .= "scheduledps.modality" . preparedStmtWildcard($modality, $value);
        $bindList[] = $value;
    }
    if (strlen($scheduledAe)) {
        if (strlen($key))
            $key .= " AND ";
        $key .= "scheduledps.aetitle" . preparedStmtWildcard($scheduledAe, $value);
        $bindList[] = $value;
    }
    if (strlen($referdoc)) {
        if (strlen($key))
            $key .= " AND ";
        $key .= "referringphysician" . preparedStmtWildcard($referdoc, $value);
        $bindList[] = $value;
    }
    if (strlen($requestdoc)) {
        if (strlen($key))
            $key .= " AND ";
        $key .= "requestingphysician" . preparedStmtWildcard($requestdoc, $value);
        $bindList[] = $value;
    }
    if (strlen($key) == 0) {
        $error = "<br>";
        $error .= pacsone_gettext("You must enter a criteria for <a href='search.php'>Search Modality Worklist");
        return $error;
    }
    if (isset($_REQUEST['sort']))
        $sort = urldecode($_REQUEST['sort']);
    $offset = 0;
    if (isset($_REQUEST['offset']))
	    $offset = $_REQUEST['offset'];
    $all = 0;
    if (isset($_REQUEST['all']))
	    $all = $_REQUEST['all'];
    $query .= $key;
    if (isset($sort)) {
        global $WORKLIST_SORT_COLUMNS;
        if (!in_array(strtolower($sort), $WORKLIST_SORT_COLUMNS)) {
            $error = "<br>";
            $error .= sprintf(pacsone_gettext("Invalid Modality Worklist sort column: %s"), $sort);
            return $error;
        }
        $query .= " order by $sort,worklist.status asc";
    } else
        $query .= " order by worklist.status asc";
    if (count($bindList))
        $result = $dbcon->preparedStmt($query, $bindList);
    else
        $result = $dbcon->query($query);
    if ($result) {
        $rows = array();
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = $row;
        }
        $num_rows = sizeof($rows);
        if ($num_rows > 1)
            $preface = sprintf(pacsone_gettext("%d matching worklist items found in PACS database."), $num_rows);
        else
            $preface = sprintf(pacsone_gettext("%d matching worklist item found in PACS database."), $num_rows);
        $url = "searchWorklist.php?studyuid=$uid&patientid=" . urlencode($id);
        $url .= "&patientname=" . urlencode($name);
        $url .= "&compare=" . urlencode($compare);
        $url .= "&date=$date";
        if ($date) {
            $url .= "&fromdate=" . urlencode($fromdate);
            $url .= "&todate=" . urlencode($todate);
        }
        $url .= "&accession=" . urlencode($accession);
        $url .= "&modality=" . urlencode($modality);
        $url .= "&scheduledae=" . urlencode($scheduledAe);
        if (strlen($referdoc))
            $url .= "&referdoc=" . urlencode($referdoc);
        if (strlen($requestdoc))
            $url .= "&requestdoc=" . urlencode($requestdoc);
        displayWorklist($preface, $rows, $url, $offset, $all);
    }
    return $error;
}
?>
