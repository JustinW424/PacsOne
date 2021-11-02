<?php
//
// remoteImage.php
//
// Module for querying remote images
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once "dicom.php";
require_once "header.php";

$aetitle = $_REQUEST['aetitle'];
if (preg_match("/[';\"]/", $aetitle)) {
    $error = pacsone_gettext("Invalid AE Title");
    print "<h2><font color=red>$error</font></h2>";
    exit();
}
$key = array();
$key["Patient ID"] = isset($_REQUEST['patientid'])? $_REQUEST['patientid'] : "";
$key["Study UID"] = isset($_REQUEST['studyuid'])? $_REQUEST['studyuid'] : "";
$key["Series UID"] = isset($_REQUEST['seriesuid'])? $_REQUEST['seriesuid'] : "";
if (isset($_REQUEST['uid']))
	$key["Instance UID"] = $_REQUEST['uid'];
$dbcon = new MyConnection();
$query = "SELECT * FROM applentity WHERE title=?";
$bindList = array($aetitle);
$result = $dbcon->preparedStmt($query, $bindList);
$row = $result->fetch(PDO::FETCH_ASSOC);
$ipaddr = $row['ipaddr'];
$hostname = $row['hostname'];
$port = $row['port'];
$tls = $row['tlsoption'];
$mytitle = $dbcon->getMyAeTitle();

$error = '';
$assoc = new Association($ipaddr, $hostname, $port, $aetitle, $mytitle, $tls);
$identifier = isset($key["Instance UID"])? (new CFindIdentifierImage($key)) : (new CFindIdentifierSeries($key));
$matches = $assoc->find($identifier, $error);
if (strlen($error)) {
    print '<br><font color=red>';
    printf(pacsone_gettext('find() failed: error = %s'), $error);
    print '</font><br>';
}
else {
	require_once "display.php";
	if (count($matches)) {
		if (!isset($uid))
			displayRemoteImage($aetitle, $identifier, $matches);
		else {
			displayRemoteImageDetails($aetitle, $identifier, $matches);
		}
	} else {
		print "<br>";
        printf(pacsone_gettext("No match found by remote AE <b>%s</b>."), $aetitle);
        print "<br>";
    }
	require_once 'footer.php';
}

?>
