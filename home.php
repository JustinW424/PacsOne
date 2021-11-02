<?php
//
// home.php
//
// Home page for displaying studies received today
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once "locale.php";
include_once 'security.php';
include_once 'display.php';
require_once 'utils.php';

$dbcon = new MyConnection();
$username = $dbcon->username;
$toggle = 0;
$sort = "cmp_received_opt";
if (isset($_REQUEST['sort']) && isset($_REQUEST['toggle'])) {
    $sort = $_REQUEST['sort'];
    if (isset($_SESSION['lastSort']) && !strcasecmp($sort, $_SESSION['lastSort'])
        && ($toggle != $_REQUEST['toggle'])) {
        $toggle = 1 - $toggle;
    }
}
$viewAccess = $dbcon->hasaccess("viewprivate", $username);
$filters = $dbcon->filterStudies($username);
$rows = array();
$order = "ORDER BY received DESC";
if ($viewAccess) {
    $query = "SELECT * FROM study LEFT JOIN patient ON study.patientid = patient.origid where ";
    if ($dbcon->useOracle)
        $where = strlen($filters)? $filters : "TRUNC(received)=TRUNC(SYSDATE)";
    else
        $where = strlen($filters)? $filters : "DATE(received)=CURDATE()";
    $query .= $where . " " . $order;
    $result = $dbcon->query($query);
    while ($result && ($row = $result->fetch(PDO::FETCH_ASSOC))) {
        $rows[] = $row;
    }
} else {
    // all public studies
    $query = "SELECT * FROM study LEFT JOIN patient ON study.patientid = patient.origid where study.private=0 and DATE(received)=CURDATE() ";
    if ($dbcon->useOracle) {
        $query = "SELECT * FROM study LEFT JOIN patient ON study.patientid = patient.origid where study.private=0 and TRUNC(received)=TRUNC(SYSDATE) ";
    }
    $query .= $order;
    $result = $dbcon->query($query);
    while ($result && ($row = $result->fetch(PDO::FETCH_ASSOC)))
        $rows[] = $row;
    // plus any private studies received today
    $studies = $dbcon->getTodayStudies($username);
    foreach ($studies as $study)
        $rows[] = $study;
}
$num_rows = sizeof($rows);
if ($viewAccess && strlen($filters)) {
    $preface = sprintf(pacsone_gettext("There are %d accessible studies<br>"), $num_rows);
    $title = pacsone_gettext("Filtered Studies");
} else {
    $preface = sprintf(pacsone_gettext("There are %d accessible studies received today: <b>%s</b><br>"), $num_rows, date("l F jS Y"));
    $title = pacsone_gettext("Studies Received Today");
}
if (!$num_rows && !strlen($filters)) {
    // display most-recently received 10 studies instead
    $query = "SELECT * FROM study LEFT JOIN patient ON study.patientid = patient.origid ";
    $order = "order by study.received desc";
    $key = "";
    if ($dbcon->useOracle) {
        $key .= strlen($key)? " and " : "where";
        $key .= " ROWNUM <= 100";
        $order = "order by study.received desc";
    } else {
        $order .= " limit 100";
    }
    $query .= $key . " " . $order;
    $result = $dbcon->query($query);
    $count = 1;
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $sourceae = $row['sourceae'];
        $uid = $row['uuid'];
        if ($viewAccess || ($row['private'] ==0) ||
            $dbcon->sourceaeAssignedToUser($sourceae, $username) ||
            $dbcon->accessStudy($uid, $username))
        {
            $rows[] = $row;
            $count++;
        }
        if ($count > 10)
            break;
    }
    $num_rows = sizeof($rows);
    if ($num_rows) {
        $preface = sprintf(pacsone_gettext("%d most-recently received accessible studies:<br>"), $num_rows);
        $title = pacsone_gettext("Studies Received Most Recently");
    }
}
$offset = 0;
if (isset($_REQUEST['offset']))
	$offset = $_REQUEST['offset'];
$all = 0;
if (isset($_REQUEST['all']))
	$all = $_REQUEST['all'];

global $PRODUCT;
print "<html>";
$refresh = $dbcon->getAutoRefresh($username);
print "<META HTTP-EQUIV=REFRESH CONTENT=$refresh>";
print "<head><title>$PRODUCT - $title</title></head>";
print "<body>";
require_once 'header.php';
// sort the rows based on Study ID by default
my_usort($rows, $sort, $toggle);
$_SESSION['lastSort'] = $sort;
$_SESSION['sortToggle'] = $toggle;
$url = "home.php?sort=" . urlencode($sort) . "&toggle=$toggle";
displayStudies($rows, $preface, $url, $offset, 1, $all, 1);
require_once 'footer.php';
print "</body>";
print "</html>";
?>
