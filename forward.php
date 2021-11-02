<?php
//
// forward.php
//
// Module for forwarding entries from database tables to remote C-STORE SCP
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once "locale.php";
include_once 'security.php';
include_once 'sharedData.php';

global $PRODUCT;
global $HOUR_TBL;
$option = $_POST['option'];
print "<html>\n";
print "<head><title>$PRODUCT - " . sprintf(pacsone_gettext("Forwarding %s"), $option) . "</title></head>\n";
print "<body>\n";
require_once 'header.php';

$dbcon = new MyConnection();
$username = $dbcon->username;
$tokens = explode(" - ", $_POST['aetitle']);
$aetitle = $tokens[0];
$entry = $_POST['entry'];
$schedule = -1;
if (isset($_POST['schedule'])) {
    $schedule = $_POST['schedule'];
    if (($schedule != -1) && isset($_POST['hour'])) {
        $hour = $_POST['hour'];
        if (isset($HOUR_TBL[$hour]))
            $schedule = $HOUR_TBL[$hour];
    }
}
$useraet = isset($_POST['useraet'])? $_POST['useraet'] : 0;
$sourceaet = "";
if ($useraet == 1 && isset($_POST['sourceaet']))
    $sourceaet = $_POST['sourceaet'];
else if ($useraet == 2)
    $sourceaet = "\$SOURCE\$";
foreach ($entry as $uid) {
    if (strcasecmp($option, "Patient") == 0) {
        $uid = urldecode($uid);
    }
    $query = "insert into dbjob ";
    $query .= "(username,aetitle,type,priority,class,uuid,submittime,schedule,status";
    if (strlen($sourceaet))
        $query .= ",details";
    $query .=") values(?,?,'Forward',1,?,?,";
    $query .= $dbcon->useOracle? "SYSDATE," : "NOW(),";
    $query .= "?,'submitted'";
    $bindList = array($username, $aetitle, $option, $uid, $schedule); 
    if (strlen($sourceaet)) {
        $query .= ",?";
        $bindList[] = "<<" . $sourceaet;
    }
    $query .= ")";
	if ($dbcon->preparedStmt($query, $bindList)) {
		print "Forwarding of $option: $uid has been scheduled successfully<br>\n";
    // log activity to system journal
    global $CUSTOMIZE_PATIENT;
    $option = strcasecmp($option, "Patient")? pacsone_gettext($option) : $CUSTOMIZE_PATIENT;
    $dbcon->logJournal($username, "Forward", $option, $uid);
	}
	else {
		$error = sprintf(pacsone_gettext("Failed to forward %s: %s. "), $option, $uid);
		$error .= "Database Error: " . $dbcon->getError();
		print "<h3><font color=red>$error</font></h3>";
	}
}
print "<p><a href='status.php' title='";
print pacsone_gettext("Check Forwarding Job Status");
print "'>" . pacsone_gettext("Forwarding Status") . "</a><p>\n";

print "</body>\n";
print "</html>\n";

require_once 'footer.php';
?>
