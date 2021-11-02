<?php
//
// remoteSeries.php
//
// Module for querying remote series
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
$key["Institution Name"] = isset($_REQUEST['instname'])? $_REQUEST['instname'] : "";
$key["Study UID"] = isset($_REQUEST['uid'])? $_REQUEST['uid'] : "";
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
$modality = "";
$studyRoot = false;
if (isset($_REQUEST['modality'])){
	$studyRoot = true;
	$modality = $_REQUEST['modality'];
}
$key["Modality"] = $modality;
$date = "";
if (isset($_REQUEST['datetype']) || isset($_REQUEST['date'])) {
	$studyRoot = true;
	$type = $_REQUEST['datetype'];
	if ($type == 1) {
		$date = date("Ymd");
	} else if ($type == 2) {
		$yesterday = time() - 24*60*60;
		$date = date("Ymd", $yesterday);
	} else if ($type == 3) {
		$date = urldecode($_REQUEST['date']);
		$time = strtotime($date);
		$date = date("Ymd", $time);
	} else if ($type == 4) {
		// convert to "YYYYMMDD-YYYYMMDD" format
		$from = "";
		if (strlen($_REQUEST['from'])) {
			$from = urldecode($_REQUEST['from']);
			$time = strtotime($from);
			$from = date("Ymd", $time);
		}
		$to = "";
		if (strlen($_REQUEST['to'])) {
			$to = urldecode($_REQUEST['to']);
			$time = strtotime($to);
			$to = date("Ymd", $time);
		}
		$date = $from . "-" . $to;
	} else {
		die (sprintf(pacsone_gettext("Invalid date type: %s."), $type));
	}
}
$key["Series Date"] = $date;

$error = '';
$assoc = new Association($ipaddr, $hostname, $port, $aetitle, $mytitle, $tls);
$identifier = new CFindIdentifierStudy($key);
$matches = $assoc->find($identifier, $error, $studyRoot);
if (strlen($error)) {
    print '<br><font color=red>';
    printf(pacsone_gettext('find() failed: error = %s'), $error);
    print '</font><br>';
}
else {
	require_once "display.php";
	if (count($matches))
		displayRemoteSeries($aetitle, $identifier, $matches);
	else {
		print "<br>";
        printf(pacsone_gettext("No match found by remote AE <b>%s</b>."), $aetitle);
        print "<br>";
    }
	require_once 'footer.php';
}

?>
