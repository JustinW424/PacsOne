<?php
//
// purgeAgedWorklist.php
//
// Module for purging aged worklist records
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
if (!isset($argv))
    session_start();

include_once 'database.php';
include_once 'sharedData.php';
if (!isset($argv))
    include_once 'header.php';

// main
global $PRODUCT;
if (isset($argv) && count($argv)) {
    require_once "utils.php";
    $database = $argv[1];
    $username = $argv[2];
    $password = $argv[3];
    $aetitle = $argv[4];
    $hostname = getDatabaseHost($aetitle);
    $dbcon = new MyDatabase($hostname, $database, $username, $password, $aetitle);
} else {
    $dbcon = new MyConnection();
}
$aged = 30;
$result = $dbcon->query("select worklistage from config");
if ($result && ($row = $result->fetch(PDO::FETCH_NUM))) {
    $aged = $row[0];
}
$count = 0;
$worklistTables = array(
	"worklist",
	"scheduledps",
	"referencedpatient",
	"referencedstudy",
	"requestedprocedure",
	"procedurecode",
	"protocolcode",
	"referencedvisit",
	"referencedpps",
);
print "<p>";
printf(pacsone_gettext("Started Purging Aged Worklist Records on %s"), date("r"));
print "<p>";
$key = "(TO_DAYS(NOW()) - TO_DAYS(received)) > $aged";
if ($dbcon->useOracle)
    $key = "TRUNC(SYSDATE-$aged) > TRUNC(received)";
$result = $dbcon->query("select studyuid,received from worklist where $key");
while ($result && ($row = $result->fetch(PDO::FETCH_NUM))) {
    $uid = $row[0];
    $received = $row[1];
    foreach ($worklistTables as $table) {
        $dbcon->query("delete from $table where studyuid='$uid'");
    }
    printf(pacsone_gettext("Deleted Worklist Record <b>%s</b> Received On <u>%s</u>"), $uid, $received);
    print "<br>";
    $count++;
}
// purge aged MPPS messages
$dbcon->query("delete from performedps where $key");
print "<p>";
printf(pacsone_gettext("Finished Purging %d Aged Worklist Records on %s"), $count, date("r"));
print "<br>";
if (!isset($argv))
    include_once 'footer.php';

?>
