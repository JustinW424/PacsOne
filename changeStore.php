<?php
//
// changeStore.php
//
// Module for moving selected studies to user-specified storage location
//
// CopyRight (c) 2012-2020 RainbowFish Software
//
session_start();

require_once "locale.php";
include_once 'security.php';
include_once 'sharedData.php';

global $PRODUCT;
global $HOUR_TBL;
$option = $_POST['option'];
print "<html>\n";
print "<head><title>$PRODUCT - " . sprintf(pacsone_gettext("Moving %s"), $option) . "</title></head>\n";
print "<body>\n";
require_once 'header.php';

$dbcon = new MyConnection();
$username = $dbcon->username;
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
$newdir = $_POST['userdir']? $_POST['newdir'] : $_POST['selectdir'];
$newdir = cleanPostPath($newdir);
if (!strlen($newdir)) {
    print "<h3><font color=red>";
    print pacsone_gettext("A Valid Move Destination Directory must be specified!");
    print "</font></h3>";
} else if (!file_exists($newdir)) {
    print "<h3><font color=red>";
    printf(pacsone_gettext("Invalid Move Destination Directory: <u>%s</u>"), $newdir);
    print "</font></h3>";
} else {
    foreach ($entry as $uid) {
        $query = "insert into dbjob ";
        $query .= "(username,aetitle,type,priority,class,uuid,submittime,schedule,status,details";
        $query .=") values(?,'_','ChangeStore',1,?,?,";
        $query .= $dbcon->useOracle? "SYSDATE," : "NOW(),";
        $query .= "?,'submitted',?)";
        $bindList = array($username, $option, $uid, $schedule, $newdir);
        if ($dbcon->preparedStmt($query, $bindList)) {
            print "Moving of $option: $uid has been scheduled successfully<br>\n";
            // log activity to system journal
            $dbcon->logJournal($username, "Move", $option, $uid);
        }
        else {
            $error = sprintf(pacsone_gettext("Failed to move %s: %s. "), $option, $uid);
            $error .= "<br>" . sprintf(pacsone_gettext("Database Error: %s"), $dbcon->getError());
            print "<h3><font color=red>$error</font></h3>";
        }
    }
    print "<p><a href='status.php' title='";
    print pacsone_gettext("Check Moving Job Status");
    print "'>" . pacsone_gettext("Move Status") . "</a><p>\n";
}
print "</body>\n";
print "</html>\n";

require_once 'footer.php';
?>
