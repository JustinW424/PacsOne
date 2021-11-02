<?php
//
// unread.php
//
// Page for displaying unread studies
//
// CopyRight (c) 2004-2020 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once 'security.php';
include_once 'display.php';
require_once 'utils.php';

$dbcon = new MyConnection();
$username = $dbcon->username;

global $PRODUCT;
print "<html>";
$refresh = $dbcon->getAutoRefresh($username);
print "<META HTTP-EQUIV=REFRESH CONTENT=$refresh>";
print "<head><title>$PRODUCT - ";
print pacsone_gettext("Unread Studies");
print "</title></head>";
print "<body>";
require_once 'header.php';

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
$rows = array();
$query = "SELECT * FROM study LEFT JOIN patient ON study.patientid = patient.origid where reviewed is NULL order by received DESC";
$result = $dbcon->query($query);
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    if ($viewAccess) {
        $rows[] = $row;
    } else {
        $uid = $row['uuid'];
        if ($dbcon->accessStudy($uid, $username))
            $rows[] = $row;
    }
}
// sort the rows based on Study ID by default
my_usort($rows, $sort, $toggle);
$_SESSION['lastSort'] = $sort;
$_SESSION['sortToggle'] = $toggle;
$num_rows = sizeof($rows);

$url = "unread.php?sort=" . urlencode($sort) . "&toggle=$toggle";
$offset = 0;
if (isset($_REQUEST['offset']))
	$offset = $_REQUEST['offset'];
$all = 0;
if (isset($_REQUEST['all']))
	$all = $_REQUEST['all'];

$preface = sprintf(pacsone_gettext("There are %d unread studies: <b>%s</b>"), $num_rows, date("l F jS Y"));
$preface .= "<br>";
displayStudies($rows, $preface, $url, $offset, 1, $all);

require_once 'footer.php';
print "</body>";
print "</html>";
?>
