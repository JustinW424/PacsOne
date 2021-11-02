<?php
//
// searchImage.php
//
// Module for searching image information
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
print pacsone_gettext("Image Search Results:");
print "</title></head>";
print "<body>";

require_once 'header.php';

$uid = $_REQUEST['uid'];
$compare = isset($_REQUEST['compare'])? $_REQUEST['compare'] : "";
$date = 0;
if ((isset($_REQUEST['fromdate']) && strlen($_REQUEST['fromdate'])) ||
    (isset($_REQUEST['todate']) && strlen($_REQUEST['todate'])))
    $date = 1;
$photometric = "";
if (isset($_POST['photometric']))
    $photometric = $_POST['photometric'];

$dbcon = new MyConnection();
$error = searchImage($uid, $compare, $date, $photometric);
if ($error) {
    print "<font color=red>$error</font>";
}
require_once 'footer.php';
print "</body>";
print "</html>";

function searchImage($uid, $compare, $date, $photometric)
{
    global $dbcon;
    $error = "";
    $eurodate = $dbcon->isEuropeanDateFormat();
    // check if UID format is valid
    if (strlen($uid) && !isUidValid($uid)) {
        return pacsone_gettext("Invalid Image UID");
    }
    // make sure first char of any search pattern is not a wild-card char
    if (isWildcardFirst($uid)) {
        return pacsone_gettext("First character of image UID search pattern cannot be wild-card chars like '*' or '?'");
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
    $query = "SELECT * from image where ";
    $key = "";
    $value = "";
    $bindList = array();
    // build query string based on form input
    if (strlen($uid)) {
        if (strlen($key))
            $key .= " AND ";
        $key .= "uuid" . preparedStmtWildcard($uid, $value);
        $bindList[] = $value;
    }
    if ($date) {
        if (strlen($key))
            $key .= " AND ";
        $key .= "(instancedate ";
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
            $key .= ">= ? AND instancedate <= ?";
            array_push($bindList, $fromdate, $todate);
        } else {
            print "<h2><font color=red>";
            printf(pacsone_gettext("Invalid Date Comparator: %s"), $compare);
            print "</font></h2>";
            exit();
        }
        $key .= ")";
    }
    if (strlen($photometric)) {
        if (strlen($key))
            $key .= " AND ";
        $key .= "photometric" . preparedStmtWildcard($photometric, $value);
        $bindList[] = $value;
    }
    if (strlen($key) == 0) {
        $error = "<br>";
        $error .= pacsone_gettext("You must enter a criteria for <a href='search.php'>Search By Image</a>");
        return $error;
    }
    $query .= $key;
    $offset = 0;
    if (isset($_REQUEST['offset']))
        $offset = $_REQUEST['offset'];
    if (count($bindList))
        $result = $dbcon->preparedStmt($query, $bindList);
    else
        $result = $dbcon->query($query);
    if ($result) {
        $rows = array();
        while ($row = $result->fetch(PDO::FETCH_ASSOC))
            $rows[] = $row;
        $num_rows = sizeof($rows);
        $url = "searchImage.php?uid=$uid";
        $url .= "&compare=" . urlencode($compare);
        $url .= "&date=$date";
        if ($date) {
            $url .= "&fromdate=" . urlencode($fromdate);
            $url .= "&todate=" . urlencode($todate);
        }
        $preface = sprintf(pacsone_gettext("%d matching images found in PACS database."), $num_rows);
        displayImage($rows, $preface, $url, $offset, 0, 0);
    }
    return $error;
}

?>
