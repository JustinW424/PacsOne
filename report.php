<?php
//
// report.php
//
// Module for displaying statistics reports
//
// CopyRight (c) 2003-2015 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once 'security.php';
include_once 'sharedData.php';
require_once 'header.php';

global $PRODUCT;
print "<html>";
print "<head><title>$PRODUCT - ";
print pacsone_gettext("Statistics Report");
print "</title></head>";
print "<body>";
$dbcon = new MyConnection();
// main
require_once 'display.php';
require_once 'utils.php';
require_once 'statistics.php';

$images = 0;
$totalSize = 0;
$type = $_REQUEST['type'];
$from = isset($_POST['from'])? $_POST['from'] : "";
$from = isset($_REQUEST['from'])? $_REQUEST['from'] : $from;
$to = isset($_POST['to'])? $_POST['to'] : "";
$to = isset($_REQUEST['to'])? $_REQUEST['to'] : $to;
$sourceae = isset($_REQUEST['sourceae'])? $_REQUEST['sourceae'] : "";
$institution = isset($_REQUEST['institution'])? $_REQUEST['institution'] : "";
$reviewer = isset($_REQUEST['reviewer'])? $_REQUEST['reviewer'] : "";
$durTbl = array (
    pacsone_gettext("yesterday"),
    pacsone_gettext("this week"),
    pacsone_gettext("this month"),
    pacsone_gettext("this year"),
    sprintf(pacsone_gettext("from %s to %s"), $from, $to),
    sprintf(pacsone_gettext("from source AE: <u>%s</u>"), $sourceae),
    pacsone_gettext("from each source AE defined in <a href=\"applentity.php\">Dicom AE</a> page"),
    sprintf(pacsone_gettext("with this Institution Name: <u>%s</u>"), $institution),
    sprintf(pacsone_gettext("reviewed by this web user: <u>%s</u>"), $reviewer),
);
$duration = $durTbl[$type];
$rows = generateStats($dbcon, $type, $images, $totalSize, $from, $to, $sourceae, $institution, $reviewer);
// sort the rows based on Received Date by default
$toggle = 0;
$sort = ($type == 6)? "" : "cmp_received_opt";
if (isset($_REQUEST['sort']) && isset($_REQUEST['toggle'])) {
    $sort = $_REQUEST['sort'];
    if (isset($_SESSION['lastSort']) && !strcasecmp($sort, $_SESSION['lastSort'])
        && ($toggle != $_REQUEST['toggle'])) {
        $toggle = 1 - $toggle;
    }
}
$count = count($rows);
if ($count > 0) {
    my_usort($rows, $sort, $toggle);
    $_SESSION['lastSort'] = $sort;
    $_SESSION['sortToggle'] = $toggle;
}
if ($count > 1)
    $preface = sprintf(pacsone_gettext("There are %d studies received <b>%s</b>."), $count, $duration);
else
    $preface = sprintf(pacsone_gettext("There is %d study received <b>%s</b>."), $count, $duration);
$url = "report.php?type=$type";
if (strlen($from))
    $url .= "&from=$from";
if (strlen($to))
    $url .= "&to=$to";
if (strlen($sourceae))
    $url .= "&sourceae=" . urlencode($sourceae);
$url .= "&sort=" . urlencode($sort) . "&toggle=$toggle";
$offset = 0;
if (isset($_REQUEST['offset']))
	$offset = $_REQUEST['offset'];
$all = 0;
if (isset($_REQUEST['all']))
	$all = $_REQUEST['all'];
displayStatReport($rows, $preface, $url, $offset, $all, $type);
// display summary
print "<br>";
printf(pacsone_gettext("Total of %d studies, %d images of %s"), $count, $images, $dbcon->displayFileSize($totalSize));
print "<br>";

require_once 'footer.php';
print "</body>";
print "</html>";
?>
