<?php
//
// remoteStudy.php
//
// Module for querying remote studies
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
if (isset($_REQUEST['patientid']))
	$key["Patient ID"] = urldecode($_REQUEST['patientid']);
$key["Study ID"] = isset($_REQUEST['id'])? urldecode($_REQUEST['id']) : "";
$date = "";
if (isset($_REQUEST['datetype']) || isset($_REQUEST['date'])) {
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
$key["Study Date"] = $date;
$key["Accession Number"] = isset($_REQUEST['accession'])? urldecode($_REQUEST['accession']) : "";
$referdoc = "";
if (isset($_REQUEST['doclast']) && strlen($_REQUEST['doclast']))
	$referdoc .= urldecode($_REQUEST['doclast']);
if (isset($_REQUEST['docfirst']) && strlen($_REQUEST['docfirst']))
	$referdoc .= "^" . urldecode($_REQUEST['docfirst']);
$key["Referring Physician"] = $referdoc;
$key["Institution Name"] = isset($_REQUEST['instname'])? urldecode($_REQUEST['instname']) : "";
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
$identifier = isset($key["Patient ID"])? (new CFindIdentifierPatient($key)) : (new CFindIdentifierStudyRoot($key));
$matches = $assoc->find($identifier, $error);
if (strlen($error)) {
    print '<br><font color=red>';
    printf(pacsone_gettext('find() failed: error = %s'), $error);
    print '</font><br>';
}
else {
	require_once "display.php";
	if (count($matches))
		displayRemoteStudies($aetitle, $identifier, $matches);
	else {
		print "<br>";
        printf(pacsone_gettext("No match found by remote AE <b>%s</b>."), $aetitle);
        print "<br>";
    }
	require_once 'footer.php';
}

?>
