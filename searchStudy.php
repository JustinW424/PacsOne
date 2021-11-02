<?php
//
// searchStudy.php
//
// Module for searching study information
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once "locale.php";
include_once 'security.php';
include_once 'display.php';
require_once 'utils.php';

global $PRODUCT;
print "<html>";
print "<head>";
print "<title>$PRODUCT - " . pacsone_gettext("Study Search Results:") . "</title>";
print "</head>";
print "<body>";

require_once 'header.php';

$uid = $_REQUEST['uid'];
$id = $_REQUEST['id'];
$compare = $_REQUEST['compare'];
$rcompare = $_REQUEST['rcompare'];
$accession = $_REQUEST['accession'];
$date = 0;
if ( (isset($_REQUEST['fromdate']) && strlen($_REQUEST['fromdate'])) ||
     (isset($_REQUEST['todate']) && strlen($_REQUEST['todate'])) ||
     (isset($_REQUEST['rfromdate']) && strlen($_REQUEST['rfromdate'])) ||
     (isset($_REQUEST['rtodate']) && strlen($_REQUEST['rtodate'])) ||
     (isset($_REQUEST['date']) && ($_REQUEST['date'] == 1)) )
    $date = 1;
$sourceAe = "";
if (isset($_REQUEST['sourceae']))
    $sourceAe = $_REQUEST['sourceae'];
$dbcon = new MyConnection();
$username = $dbcon->username;
$error = searchStudy($username, $uid, $id, $compare, $date, $accession, $sourceAe, $rcompare);
if ($error) {
    print "<font color=red>$error</font>";
}
require_once 'footer.php';
print "</body>";
print "</html>";

function searchStudy($username, $uid, $id, $compare, $date, $accession, $sourceAe, $rcompare)
{
    global $dbcon;
    $access = $dbcon->hasaccess("viewprivate", $username);
	$error = "";
    $eurodate = $dbcon->isEuropeanDateFormat();
    $toggle = 0;
    $sort = "cmp_studyid";
    if (isset($_REQUEST['sort']) && isset($_REQUEST['toggle'])) {
        $sort = $_REQUEST['sort'];
        if (isset($_SESSION['lastSort']) && !strcasecmp($sort, $_SESSION['lastSort'])
            && ($toggle != $_REQUEST['toggle'])) {
            $toggle = 1 - $toggle;
        }
    }
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
        $rfromdate = $_REQUEST['rfromdate'];
        $todate = "";
        $rtodate = "";
        if (!strcasecmp($compare, "FROM"))
            $todate = $_REQUEST['todate'];
        if (!strcasecmp($rcompare, "FROM"))
            $rtodate = $_REQUEST['rtodate'];
        if ((strlen($fromdate) && !isDateValid($fromdate, $eurodate)) ||
            (strlen($rfromdate) && !isDateValid($rfromdate, $eurodate)))
            return sprintf(pacsone_gettext("Invalid FROM DATE, please use %s format"),
                ($eurodate)? "DD-MM-YYYY" : "YYYY-MM-DD");
        if ((!strcasecmp($compare, "FROM") && strlen($todate) && !isDateValid($todate, $eurodate)) ||
            (!strcasecmp($rcompare, "FROM") && strlen($rtodate) && !isDateValid($rtodate, $eurodate)))
            return sprintf(pacsone_gettext("Invalid TO DATE, please use %s format"),
                ($eurodate)? "DD-MM-YYYY" : "YYYY-MM-DD");
    }
    $key = "";
    $value = "";
    $bindList = array();
    $url = "searchStudy.php?uid=$uid&id=" . urlencode($id);
    $modalities = "";
    if (isset($_REQUEST['modalities']))
        $modalities = urldecode($_REQUEST['modalities']);
    $patientid = "";
    if (isset($_REQUEST['patientid'])) {
        $patientid = urldecode($_REQUEST['patientid']);
        if (get_magic_quotes_gpc())
            $patientid = stripslashes($patientid);
    }
    $lastname = "";
    if (isset($_REQUEST['lastname'])) {
        $lastname = urldecode($_REQUEST['lastname']);
        if (get_magic_quotes_gpc())
            $lastname = stripslashes($lastname);
    }
    $firstname = "";
    if (isset($_REQUEST['firstname'])) {
        $firstname = urldecode($_REQUEST['firstname']);
        if (get_magic_quotes_gpc())
            $firstname = stripslashes($firstname);
    }
    if (strlen($modalities)) {
        $query = "SELECT DISTINCT study.uuid as uuid,study.description as description,study.* FROM study,series,patient WHERE ";
        $key = "study.uuid=series.studyuid AND study.patientid=patient.origid AND series.modality" . preparedStmtWildcard($modalities, $value);
        $bindList[] = $value;
        $url .= "&modalities=" . urlencode($modalities);
    } else {
        $query = "SELECT DISTINCT * FROM study LEFT JOIN patient ON study.patientid=patient.origid WHERE ";
        if (strlen($firstname)) {
            if (strlen($key))
                $key .= " AND ";
            // automatically append wild-card character
            if (isset($_REQUEST['wildname']))
                $firstname .= "*";
            $key .= "firstname" . preparedStmtWildcard($firstname, $value);
            $bindList[] = $value;
            $url .= "&firstname=" . urlencode($firstname);
        }
        if (strlen($lastname)) {
            if (strlen($key))
                $key .= " AND ";
            // automatically append wild-card character
            if (isset($_REQUEST['wildname']))
                $lastname .= "*";
            $key .= "lastname" . preparedStmtWildcard($lastname, $value);
            $bindList[] = $value;
            $url .= "&lastname=" . urlencode($lastname);
        }
    }
    // build query string based on form input
    if (strlen($patientid)) {
        if (strlen($key))
            $key .= " AND ";
        // automatically append wild-card character
        if (isset($_REQUEST['wildid']))
            $patientid .= "*";
        $key .= "study.patientid" . preparedStmtWildcard($patientid, $value);
        $bindList[] = $value;
        $url .= "&patientid=" . urlencode($patientid);
    }
    if (strlen($uid)) {
        if (strlen($key))
            $key .= " AND ";
        $key .= "study.uuid" . preparedStmtWildcard($uid, $value);
        $bindList[] = $value;
    }
    if (strlen($id)) {
        if (strlen($key))
            $key .= " AND ";
        $key .= "study.id" . preparedStmtWildcard($id, $value);
        $bindList[] = $value;
    }
    if ($date) {
        if (strlen($fromdate)) {
            $sqlfromdate = ($eurodate)? reverseDate($fromdate) : $fromdate;
            $sqltodate = ($eurodate)? reverseDate($todate) : $todate;
            if (strlen($key))
                $key .= " AND ";
            $key .= "(studydate ";
            if (strcasecmp($compare, "EQUAL") == 0) {
                $key .= "= '$sqlfromdate'";
            } else if (strcasecmp($compare, "BEFORE") == 0) {
                $key .= "< '$sqlfromdate'";
            } else if (strcasecmp($compare, "AFTER") == 0) {
                $key .= "> '$sqlfromdate'";
            } else if (strcasecmp($compare, "FROM") == 0) {
                $key .= ">= '$sqlfromdate' AND studydate <= '$sqltodate'";
            } else {
                die (sprintf(pacsone_gettext("Invalid Date Comparator: %s"), $compare));
            }
            $key .= ")";
        }
        if (strlen($rfromdate)) {
            if (strlen($key))
                $key .= " AND ";
            $sqlfromdate = ($eurodate)? reverseDate($rfromdate) : $rfromdate;
            $sqltodate = ($eurodate)? reverseDate($rtodate) : $rtodate;
            $column = $dbcon->useOracle? "TRUNC(received)" : "DATE(received)";
            $key .= "($column ";
            if (strcasecmp($rcompare, "EQUAL") == 0) {
                $key .= "= '$sqlfromdate'";
            } else if (strcasecmp($rcompare, "BEFORE") == 0) {
                $key .= "< '$sqlfromdate'";
            } else if (strcasecmp($rcompare, "AFTER") == 0) {
                $key .= "> '$sqlfromdate'";
            } else if (strcasecmp($rcompare, "FROM") == 0) {
                $key .= ">= '$sqlfromdate' AND $column <= '$sqltodate'";
            } else {
                die (sprintf(pacsone_gettext("Invalid Date Comparator: %s"), $rcompare));
            }
            $key .= ")";
        }
    }
    if (strlen($accession)) {
        if (strlen($key))
            $key .= " AND ";
        $key .= "accessionnum" . preparedStmtWildcard($accession, $value);
        $bindList[] = $value;
    }
    if (strlen($sourceAe)) {
        if (strlen($key))
            $key .= " AND ";
        $sourceAe = urldecode($sourceAe);
        $key .= "sourceae" . preparedStmtWildcard($sourceAe, $value);
        $bindList[] = $value;
        $url .= "&sourceae=" . urlencode($sourceAe);
    }
    if (isset($_REQUEST['referdoc']) && strlen($_REQUEST['referdoc'])) {
        if (strlen($key))
            $key .= " AND ";
        $referdoc = urldecode($_REQUEST['referdoc']);
        $key .= "referringphysician" . preparedStmtWildcard($referdoc, $value);
        $bindList[] = $value;
        $url .= "&referdoc=" . urlencode($referdoc);
    }
    if (isset($_REQUEST['readingdoc']) && strlen($_REQUEST['readingdoc'])) {
        if (strlen($key))
            $key .= " AND ";
        $readoc = urldecode($_REQUEST['readingdoc']);
        $key .= "readingphysician" . preparedStmtWildcard($readoc, $value);
        $bindList[] = $value;
        $url .= "&readingdoc=" . urlencode($readoc);
    }
    if (isset($_REQUEST['description']) && strlen($_REQUEST['description'])) {
        if (strlen($key))
            $key .= " AND ";
        $descr = urldecode($_REQUEST['description']);
        $column = strlen($modalities)? "study.description" : "description";
        $key .= "$column" . preparedStmtWildcard($descr, $value);
        $bindList[] = $value;
        $url .= "&description=" . urlencode($descr);
    }
    if (strlen($key) == 0) {
        $error = "<br>";
        $error .= pacsone_gettext("You must enter a criteria for <a href='search.php'>Search By Study");
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
            if ($access || !$private || $dbcon->accessStudy($row['uuid'], $username))
                $rows[] = $row;
        }
        my_usort($rows, $sort, $toggle);
        $_SESSION['lastSort'] = $sort;
        $_SESSION['sortToggle'] = $toggle;
        $url .= "&compare=" . urlencode($compare);
        $url .= "&date=$date";
        if ($date) {
            if (strlen($fromdate)) {
                $url .= "&fromdate=" . urlencode($fromdate);
                $url .= "&todate=" . urlencode($todate);
            }
            if (strlen($rfromdate)) {
                $url .= "&rcompare=" . urlencode($rcompare);
                $url .= "&rfromdate=" . urlencode($rfromdate);
                $url .= "&rtodate=" . urlencode($rtodate);
            }
        }
        $url .= "&accession=" . urlencode($accession);
        $url .= "&sort=" . urlencode($sort) . "&toggle=$toggle";
        $num_rows = sizeof($rows);
        $preface = sprintf(pacsone_gettext("%d matching studies found in PACS database."), $num_rows);
        displayStudies($rows, $preface, $url, $offset, 1, $all);
    } else {
        print "<p>Error running query: <b>$query</b>";
        print "<p><h2><font color=red>" . $dbcon->getError() . "</font></h2>";
    }
    return $error;
}
?>
