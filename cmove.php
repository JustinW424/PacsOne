<?php
//
// cmove.php
//
// Module for sending C-MOVE requests as a SCU
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once "dicom.php";
require_once "header.php";

$dbcon = new MyConnection();
// parse move parameters
$level = $_POST['level'];
$entry = $_POST['entry'];
$source = urldecode($_POST['source']);
$dest = urldecode($_POST['dest']);
$count = count($entry);
$mytitle = $dbcon->getMyAeTitle();
$patients = array();
if (isset($_POST['patientids'])) {
    $values = array_values($_POST['patientids']);
    foreach ($values as $value) {
        $tokens = explode("=", urldecode($value));
        if (count($tokens) == 2)
            $patients[$tokens[0]] = $tokens[1];
    }
}
$studies = array();
if (isset($_POST['studyuids'])) {
    $values = array_values($_POST['studyuids']);
    foreach ($values as $value) {
        $tokens = explode("=", urldecode($value));
        if (count($tokens) == 2)
            $studies[$tokens[0]] = $tokens[1];
    }
}
$series = array();
if (isset($_POST['seriesuids'])) {
    $values = array_values($_POST['seriesuids']);
    foreach ($values as $value) {
        $tokens = explode("=", urldecode($value));
        if (count($tokens) == 2)
            $series[$tokens[0]] = $tokens[1];
    }
}
// obtain the source IP addr and port number
$query = "SELECT * FROM applentity WHERE title=?";
$bindList = array($source);
$result = $dbcon->preparedStmt($query, $bindList);
$row = $result->fetch(PDO::FETCH_ASSOC);
$ipaddr = $row['ipaddr'];
$hostname = $row['hostname'];
$port = $row['port'];
$tls = $row['tlsoption'];
// report results

global $BGCOLOR;
printf(pacsone_gettext("<p>Remote Application Entity: <b>%s</b> returned the following results for moving the items below to destination AE: <b>%s</b>"), $source, $dest);
$style = "style=\"background-color: $BGCOLOR;color: white; font-weight: bold\"";
print "<p><table border=1 width=100% cellpadding=6 cellspacing=0>";
print "<tr align=center><td $style>";
print pacsone_gettext("Move Level");
print "</td>";
print "<td $style>ID</td>";
print "<td $style>";
print pacsone_gettext("Result");
print "</td></tr>";
// disable PHP timeout
set_time_limit(0);
// start the C-MOVE session
for ($i = 0; $i < $count; $i++) {
	$type = $level{$i};
	$uid = urldecode($entry{$i});
	if (strcasecmp($type, "Patient") == 0) {
		$identifier = new CMoveIdentifierPatient($uid);
	} else if (strcasecmp($type, "Study") == 0) {
        $patientid = $patients[$uid];
		$identifier = new CMoveIdentifierStudy($patientid, $uid);
	} else if (strcasecmp($type, "Series") == 0) {
        $patientid = $patients[$uid];
		$studyuid = $studies[$uid];
		$identifier = new CMoveIdentifierSeries($patientid, $studyuid, $uid);
	} else if (strcasecmp($type, "Image") == 0) {
        $patientid = $patients[$uid];
		$studyuid = $studies[$uid];
		$seriesuid = $series[$uid];
		$identifier = new CMoveIdentifierImage($patientid, $studyuid, $seriesuid, $uid);
	} else {
		// invalid Query/Retrieve level
		continue;
	}
	print "<tr><td>$type</td><td>$uid</td>";
	$error = '';
	$assoc = new Association($ipaddr, $hostname, $port, $source, $mytitle, $tls);
	$result = $assoc->move($dest, $identifier, $error);
	if (strlen($error)) {
		print "<td><font color=red>";
        print pacsone_gettext("Error: ");
        print "$error</font></td>";
	} else if (count($result)) {
		print "<td><font color=red>";
        print pacsone_gettext("One or more items failed.");
		print pacsone_gettext("<br>List of failed SOP instances:");
		print "<br><UL>";
		foreach ($result as $item) {
			$list = $item->list;
			foreach ($list as $uid)
				print "<LI>$uid</LI>";
		}
		print "</UL></font></td>";
	} else {
		print "<td>";
        print pacsone_gettext("Success");
        print "</td>";
	}
	print "</tr>";
}
print "</table>";

require_once 'footer.php';
?>
