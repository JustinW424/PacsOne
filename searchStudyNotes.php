<?php
//
// searchStudyNotes.php
//
// Module for searching study notes information
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
print pacsone_gettext("Study Notes Search Results:");
print "</title></head>";
print "<body>";

require_once 'header.php';

$author = $_REQUEST['author'];
$subject = $_REQUEST['subject'];
$compare = $_REQUEST['compare'];
$date = 0;
if ( strlen($_REQUEST['fromdate']) || strlen($_REQUEST['todate']) ||
     (isset($_REQUEST['date']) && ($_REQUEST['date'] == 1)) )
    $date = 1;

$dbcon = new MyConnection();
$username = $dbcon->username;
$error = searchStudyNotes($username, $author, $subject, $compare, $date);
if ($error) {
    print "<font color=red>$error</font>";
}
require_once 'footer.php';
print "</body>";
print "</html>";

function searchStudyNotes($username, $author, $subject, $compare, $date)
{
    global $dbcon;
    require_once "display.php";
    $access = $dbcon->hasaccess("viewprivate", $username);
    $error = "";
    $eurodate = $dbcon->isEuropeanDateFormat();
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
    $query = "SELECT * FROM studynotes where ";
    $key = "";
    $value = "";
    $bindList = array();
    // build query string based on form input
    if (strlen($author)) {
        if (strlen($key))
            $key .= " AND ";
        $key .= "username" . preparedStmtWildcard($author, $value);
        $bindList[] = $value;
    }
    if (strlen($subject)) {
        if (strlen($key))
            $key .= " AND ";
        $key .= "headline" . preparedStmtWildcard($subject, $value);
        $bindList[] = $value;
    }
    if ($date) {
        if (strlen($key))
            $key .= " AND ";
        if (strcasecmp($compare, "EQUAL") == 0) {
            $key .= "(TO_DAYS(created) = TO_DAYS(?))";
            $bindList[] = $fromdate;
        } else if (strcasecmp($compare, "BEFORE") == 0) {
            $key .= "(created < ?)";
            $bindList[] = $fromdate;
        } else if (strcasecmp($compare, "AFTER") == 0) {
            $key .= "(created > ?)";
            $bindList[] = $fromdate;
        } else if (strcasecmp($compare, "FROM") == 0) {
            $key .= "(created >= ? AND created <= ?)";
            array_push($bindList, $fromdate, $todate);
        } else {
            print "<h2><font color=red>";
            printf(pacsone_gettext("Invalid Date Comparator: %s"), $compare);
            print "</font></h2>";
            exit();
        }
    }
    if (strlen($key) == 0) {
        $error = "<br>";
        $error .= pacsone_gettext("You must enter a criteria for <a href='search.php'>Search By Study Notes</a>");
        return $error;
    }
    $query .= $key;
    if (count($bindList))
        $result = $dbcon->preparedStmt($query, $bindList);
    else
        $result = $dbcon->query($query);
    if ($result) {
        $rows = array();
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            if ($dbcon->accessStudy($row['uuid'], $username))
                $rows[] = $row;
        }
        $count = count($rows);
        $plural = 
        print "<p>";
        if ($count > 1)
            printf(pacsone_gettext("%d Matches found"), $count);
        else
            printf(pacsone_gettext("%d Match found"), $count);
        print "<p>";
        if ($count)
            displayNotes("studynotes", $rows, $username, "studyNotes.php", 0, 1);
    }
    return $error;
}
?>
