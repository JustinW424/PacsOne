<?php
//
// remotePatient.php
//
// Module for querying remote patients
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
$key["Patient ID"] = isset($_REQUEST['id'])? $_REQUEST['id'] : "";
$key["Last Name"] = isset($_REQUEST['lastname'])? $_REQUEST['lastname'] : "";
$key["First Name"] = isset($_REQUEST['firstname'])? $_REQUEST['firstname'] : "";
$key["Institution Name"] = isset($_REQUEST['instname'])? $_REQUEST['instname'] : "";
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
$identifier = new CFindIdentifierRoot($key);
$matches = $assoc->find($identifier, $error);
if (strlen($error)) {
    print '<br><font color=red>';
    printf(pacsone_gettext('find() failed: error = %s'), $error);
    print '</font><br>';
}
else {
	require_once "display.php";
	if (count($matches))
		displayRemotePatients($aetitle, $identifier, $matches);
	else {
		print "<br>";
        printf(pacsone_gettext("No match found by remote AE <b>%s</b>."), $aetitle);
        print "<br>";
    }
	require_once 'footer.php';
}

?>
