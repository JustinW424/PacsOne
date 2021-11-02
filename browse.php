<?php
//
// browse.php
//
// Module for browsing all accessible records in the database
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once 'security.php';
include_once 'display.php';

// main
global $PRODUCT;
print "<html>";
print "<head><title>$PRODUCT</title></head>";
print "<body>";
require_once 'header.php';
include_once 'utils.php';

$dbcon = new MyConnection();
$username = $dbcon->username;
$toggle = 0;
$sort = "";
if (isset($_REQUEST['sort']) && isset($_REQUEST['toggle'])) {
    $sort = $_REQUEST['sort'];
    if (isset($_SESSION['lastSort']) && !strcasecmp($sort, $_SESSION['lastSort'])
        && ($toggle != $_REQUEST['toggle'])) {
        $toggle = 1 - $toggle;
    }
}
$viewAccess = $dbcon->hasaccess("viewprivate", $username);
set_time_limit(0);
$rows = array();
if ($viewAccess) {
    $query = "SELECT * FROM patient";
    $result = $dbcon->query($query);
    while ($row = $result->fetch(PDO::FETCH_ASSOC))
        $rows[] = $row;
} else {
    // all public patients
    $query = "SELECT * FROM patient where private=0";
    $result = $dbcon->query($query);
    while ($row = $result->fetch(PDO::FETCH_ASSOC))
        $rows[] = $row;
    // plus any private patients with matching referring or reading physician name
    $patients = $dbcon->getAccessiblePatients($username);
    foreach ($patients as $pid => $patient)
        $rows[] = $patient;
}
$studies = 0;
$series = 0;
$images = 0;
$bulk = 100;
$serieUids = array();
// it takes time to display other counters if there're large number of patients
$limit = 10000;
if (count($rows) < $limit) {
    if (strlen($sort) == 0)
        $sort = "cmp_received_opt";
    $index = 0;
    while ($index < sizeof($rows)) {
        $patient =& $rows[$index++];
        $patient['received'] = 0;
        $q = "SELECT uuid,UNIX_TIMESTAMP(received) FROM study WHERE patientid=?";
        if ($dbcon->useOracle)
            $q = "SELECT uuid,received FROM study WHERE patientid=?";
        $bindList = array($patient['origid']);
        $result = $dbcon->preparedStmt($q, $bindList);
        if (!$result || !$result->rowCount()) continue;
        while ($study = $result->fetch(PDO::FETCH_NUM)) {
            $studies++;
            $uid = $study[0];
            $received = $study[1];
            // sort patient by the last study received (default)
            if ($received > $patient['received'])
                $patient['received'] = $received;
            $sResult = $dbcon->query("SELECT uuid FROM series WHERE studyuid='$uid'");
            while ($uid = $sResult->fetchColumn()) {
                $series++;
                $serieUids[] = $uid;
            }
        }
    }
    $query = "";
    for ($i = 0; $i < $series; $i++) {
        $uid = $serieUids[$i];
        if (($i % $bulk) == 0) {
            $query = "SELECT COUNT(*) FROM image WHERE seriesuid in ('$uid',";
        } else {
            $query .= "'$uid',";
            if (($i % $bulk) == ($bulk - 1)) {
                // replace the last ',' with ')'
                $npos = strrpos($query, ",");
                $query = substr($query, 0, $npos) . ")";
                $iResult = $dbcon->query($query);
                $count = $iResult->fetch(PDO::FETCH_NUM);
                $images += $count[0];
                $query = "";
            }
        }
    }
    // catch any left-over series
    if (strlen($query)) {
        // replace the last ',' with ')'
        $npos = strrpos($query, ",");
        $query = substr($query, 0, $npos) . ")";
        $iResult = $dbcon->query($query);
        $images += $iResult->fetchColumn();
    }
}
// sort the rows based on the 'lastaccess' timestamp
if (strlen($sort)) {
    my_usort($rows, $sort, $toggle);
    $_SESSION['lastSort'] = $sort;
    $_SESSION['sortToggle'] = $toggle;
}
$num_rows = sizeof($rows);
$url = "browse.php?sort=$sort" . "&toggle=$toggle";
$offset = 0;
if (isset($_REQUEST['offset']))
	$offset = $_REQUEST['offset'];
$all = 0;
if (isset($_REQUEST['all']))
	$all = $_REQUEST['all'];
// display total number of patient records in database
global $CUSTOMIZE_PATIENTS;
if ($images) {
    $preface = sprintf(pacsone_gettext("There are %d accessible %s, %d Studies, %d Series and %d Images in PACS database."), $num_rows, $CUSTOMIZE_PATIENTS, $studies, $series, $images);
} else {
    $preface = sprintf(pacsone_gettext("There are %d accessible %s"), $num_rows, $CUSTOMIZE_PATIENTS);
}
displayPatients($rows, $preface, $url, $offset, $all);
require_once 'footer.php';
print "</body>";
print "</html>";

?>
