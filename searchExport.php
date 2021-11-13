<?php
//
// searchExport.php
//
// Module for searching exported study information
//
// CopyRight (c) 2003-2020 RainbowFish Software, Inc.
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
print pacsone_gettext("Exported Studies:");
print "</title></head>";
print "<body>";

require_once 'header.php';

$uid = $_REQUEST['uid'];
$id = $_REQUEST['id'];
$compare = $_REQUEST['compare'];
$exportcompare = $_REQUEST['exportcompare'];
$date = 0;
if ( strlen($_REQUEST['fromdate']) || strlen($_REQUEST['todate']) ||
     (isset($_REQUEST['date']) && ($_REQUEST['date'] == 1)) )
    $date = 1;
$exportdate = 0;
if ( strlen($_REQUEST['exportfromdate']) || strlen($_REQUEST['exporttodate']) ||
     (isset($_REQUEST['exportdate']) && ($_REQUEST['exportdate'] == 1)) )
    $exportdate = 1;

$dbcon = new MyConnection();
$username = $dbcon->username;
$error = searchExport($username, $uid, $id, $compare, $date, $exportcompare, $exportdate);
if ($error) {
    print "<font color=red>$error</font>";
}
require_once 'footer.php';
print "</body>";
print "</html>";

function searchExport($username, $uid, $id, $compare, $date, $exportcompare, $exportdate)
{
    global $dbcon;
    $access = $dbcon->hasaccess("viewprivate", $username);
	$error = "";
    $eurodate = $dbcon->isEuropeanDateFormat();
    // check if UID format is valid
    if (strlen($uid) && !isUidValid($uid)) {
        return pacsone_gettext("Invalid Study UID");
    }
    // make sure first char of any search pattern is not a wild-card char
    if (strlen($id) && (isWildcardFirst($id) || isWildcardFirst($uid))) {
        return pacsone_gettext("First character of study search pattern cannot be wild-card chars like '*' or '?'");
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
    if ($exportdate) {
        $exportfromdate = $_REQUEST['exportfromdate'];
        $exporttodate = "";
        if (!strcasecmp($exportcompare, "FROM"))
            $exporttodate = $_REQUEST['exporttodate'];
        if (!isDateValid($exportfromdate, $eurodate))
            return sprintf(pacsone_gettext("Invalid FROM DATE specified for Export Date, please use %s format"),
                ($eurodate)? "DD-MM-YYYY" : "YYYY-MM-DD");
        if (!strcasecmp($exportcompare, "FROM") && !isDateValid($exporttodate, $eurodate))
            return sprintf(pacsone_gettext("Invalid TO DATE specified for Export Date, please use %s format"),
                ($eurodate)? "DD-MM-YYYY" : "YYYY-MM-DD");
        if ($eurodate) {
            $exportfromdate = reverseDate($exportfromdate);
            $exporttodate = reverseDate($exporttodate);
        }
    }
	$query = "SELECT * FROM exportedstudy where ";
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
    if (strlen($id)) {
        if (strlen($key))
            $key .= " AND ";
        $key .= "id" . preparedStmtWildcard($id, $value);
        $bindList[] = $value;
    }
    if ($date) {
        if (strlen($key))
            $key .= " AND ";
        $key .= "(studydate ";
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
            $key .= ">= ? AND studydate <= ?";
            array_push($bindList, $fromdate, $todate);
        } else {
            print "<h2><font color=red>";
            printf(pacsone_gettext("Invalid Date Comparator: %s"), $compare);
            print "</font></h2>";
            exit();
        }
        $key .= ")";
    }
    if ($exportdate) {
        if (strlen($key))
            $key .= " AND ";
        if (strcasecmp($exportcompare, "EQUAL") == 0) {
            $key .= "(TO_DAYS(exported) = TO_DAYS(?))";
            $bindList[] = $exportfromdate;
        } else if (strcasecmp($exportcompare, "BEFORE") == 0) {
            $key .= "(exported < ?)";
            $bindList[] = $exportfromdate;
        } else if (strcasecmp($exportcompare, "AFTER") == 0) {
            $key .= "(exported > ?)";
            $bindList[] = $exportfromdate;
        } else if (strcasecmp($exportcompare, "FROM") == 0) {
            $key .= "(exported >= ? AND exported <= ?)";
            array_push($bindList, $exportfromdate, $exporttodate);
        } else {
            print "<h2><font color=red>";
            printf(pacsone_gettext("Invalid Export Date Comparator: %s"), $exportcompare);
            print "</font></h2>";
            exit();
        }
    }
    if (strlen($key) == 0) {
        $error = "<br>";
        $error .= pacsone_gettext("You must enter a criteria for <a href='search.php'>Search Exported Studies</a>");
        return $error;
    }
    $query .= $key;
    if (count($bindList))
        $result = $dbcon->preparedStmt($query, $bindList);
    else
        $result = $dbcon->query($query);
    if ($result) {
        $num_rows = $result->rowCount();
        print "<P>";
        printf(pacsone_gettext("%d exported studies found in PACS database."), $num_rows);
        print "<br>";
        if ($num_rows) {
            global $CUSTOMIZE_PATIENT_NAME;
            $columns = array(
                "label"     => pacsone_gettext("Media Label"),
                "username"  => pacsone_gettext("Exported By User"),
                "exported"  => pacsone_gettext("When"),
                "patient"   => $CUSTOMIZE_PATIENT_NAME,
                "id"        => pacsone_gettext("Study ID"),
                "studydate"      => pacsone_gettext("Study Date"),
            );
            print "<p><table class='table table-hover table-bordered table-striped' width=100% border=0 cellpadding=5>\n";
            global $BGCOLOR;
            print "<tr class='tableHeadForBGUp'>\n";
            foreach ($columns as $field => $descrp)
                print "<td><b>$descrp</b></td>\n";
            print "</tr>\n";
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                print "<tr style='background-color:white;'>\n";
                foreach ($columns as $field => $descrp) {
                    $value = $row[$field];
                    print "<td>$value</td>\n";
                }
                print "</tr>\n";
            }
            print "</table>\n";
        }
    } else {
        $error = $dbcon->getError();
    }
    return $error;
}
?>
