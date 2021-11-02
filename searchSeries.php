<?php
//
// searchSeries.php
//
// Module for searching series information
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once 'security.php';
include_once 'display.php';
require_once 'utils.php';

global $PRODUCT;
print "<html>";
print "<head>";
print "<title>$PRODUCT - ";
print pacsone_gettext("Series Search Results:");
print "</title></head>";
print "<body>";

require_once 'header.php';

$uid = $_REQUEST['uid'];
$compare = "";
if (isset($_REQUEST['compare']))
    $compare = $_REQUEST['compare'];
$modality = "";
if (isset($_REQUEST['modality']))
    $modality = $_REQUEST['modality'];
$description = "";
if (isset($_REQUEST['description']))
    $description = $_REQUEST['description'];
$date = 0;
if ((isset($_REQUEST['fromdate']) && strlen($_REQUEST['fromdate'])) ||
    (isset($_REQUEST['todate']) && strlen($_REQUEST['todate'])))
    $date = 1;

$dbcon = new MyConnection();
$error = searchSeries($uid, $modality, $compare, $date, $description);
if ($error) {
    print "<font color=red>$error</font>";
}
require_once 'footer.php';
print "</body>";
print "</html>";

function searchSeries($uid, $modality, $compare, $date, $description)
{
    global $dbcon;
	$error = "";
    $eurodate = $dbcon->isEuropeanDateFormat();
    // check if UID format is valid
    if (strlen($uid)) {
        if (!isUidValid($uid)) {
            return pacsone_gettext("Invalid Series UID");
        }
        // make sure first char of any search pattern is not a wild-card char
        if (isWildcardFirst($uid)) {
            return pacsone_gettext("First character of series UID search pattern cannot be wild-card chars like '*' or '?'");
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
    }
    // build query string based on form input
    $query = "SELECT * from series where ";
    $key = "";
    $value = "";
    $bindList = array();
    if (strlen($uid)) {
        if (strlen($key))
            $key .= " AND ";
        $key .= "uuid " . preparedStmtWildcard($uid, $value);
        $bindList[] = $value;
    }
    if (strlen($modality)) {
        if (strlen($key))
            $key .= " AND ";
        $key .= "modality " . preparedStmtWildcard($modality, $value);
        $bindList[] = $value;
    }
    if (strlen($description)) {
        if (strlen($key))
            $key .= " AND ";
        $key .= "description " . preparedStmtWildcard($description, $value);
        $bindList[] = $value;
    }
    if ($date) {
        $sqlfromdate = ($eurodate)? reverseDate($fromdate) : $fromdate;
        $sqltodate = ($eurodate)? reverseDate($todate) : $todate;
        if (strlen($key))
            $key .= " AND ";
        $key .= "(seriesdate ";
        if (strcasecmp($compare, "EQUAL") == 0) {
            $key .= "= ?";
            $bindList[] = $sqlfromdate;
        } else if (strcasecmp($compare, "BEFORE") == 0) {
            $key .= "< ?";
            $bindList[] = $sqlfromdate;
        } else if (strcasecmp($compare, "AFTER") == 0) {
            $key .= "> ?";
            $bindList[] = $sqlfromdate;
        } else if (strcasecmp($compare, "FROM") == 0) {
            $key .= ">= ? AND seriesdate <= ?";
            array_push($bindList, $sqlfromdate, $sqltodate);
        } else {
            print "<h2><font color=red>";
            printf(pacsone_gettext("Invalid Date Comparator: %s"), $compare);
            print "</font></h2>";
            exit();
        }
        $key .= ")";
    }
    if (!strlen($key)) {
        $error = "<br>";
        $error .= pacsone_gettext("You must enter a criteria for <a href='search.php'>Search By Series</a>");
        return $error;
    }
    $query .= $key;
    $offset = 0;
    if (isset($_REQUEST['offset']))
        $offset = $_REQUEST['offset'];
    $toggle = 0;
    $sort = "cmp_seriesnum";
    if (isset($_REQUEST['sort']) && isset($_REQUEST['toggle'])) {
        $sort = $_REQUEST['sort'];
        if (isset($_SESSION['lastSort']) && !strcasecmp($sort, $_SESSION['lastSort'])
            && ($toggle != $_REQUEST['toggle'])) {
            $toggle = 1 - $toggle;
        }
    }
    $all = 0;
    if (isset($_REQUEST['all']))
	    $all = $_REQUEST['all'];
    if (isset($query)) {
        if (count($bindList))
            $result = $dbcon->preparedStmt($query, $bindList);
        else
            $result = $dbcon->query($query);
        if ($result) {
            $rows = array();
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $rows[] = $row;
            }
            my_usort($rows, $sort, $toggle);
            $_SESSION['lastSort'] = $sort;
            $_SESSION['sortToggle'] = $toggle;
            $num_rows = sizeof($rows);
            $url = "searchSeries.php?uid=$uid";
            $url .= "&compare=" . urlencode($compare);
            $url .= "&date=$date";
            if ($date) {
                $url .= "&fromdate=" . urlencode($fromdate);
                $url .= "&todate=" . urlencode($todate);
            }
            $url .= "&modality=" . urlencode($modality);
            $url .= "&sort=" . urlencode($sort) . "&toggle=$toggle";
            if (strlen($description))
                $url .= "&description=" . urlencode($description);
            $preface = sprintf(pacsone_gettext("%d matching series found in PACS database."), $num_rows);
            displaySeries($rows, $preface, $url, $offset, $all, 0, 0);
        }
    }
    return $error;
}
?>
