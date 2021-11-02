<?php
//
// integrityCheck.php
//
// Module for checking database integrity
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once 'database.php';

// main
global $PRODUCT;
$dbcon = new MyConnection();
$username = $dbcon->username;

print "<html>\n";
print "<head><title>$PRODUCT - ";
print pacsone_gettext("Database Integrity Check");
print "</title></head>\n";
print "<body>\n";
require_once 'header.php';
// schedule database job to perform the database integrity check
$query = "insert into dbjob (username,aetitle,type,uuid,class,priority,submittime,status,details) ";
$query .= "values(?,'_','IntegrityCheck',";
$query .= $dbcon->useOracle? "TO_CHAR(SYSDATE,'YYYY-MM-DD HH24:MI:SS')," : "NOW(),";
$entirefile = isset($_POST['entirefile'])? $_POST['entirefile'] : 0;
$query .= $entirefile? "'Image'," : "'ImageHeader',";
$query .= "?,";
$threads = isset($_POST['parallel'])? $_POST['threads'] : 0;
$bindList = array($username, $threads);
$query .= $dbcon->useOracle? "SYSDATE," : "NOW(),";
$query .= "'submitted',NULL)";
$result = "";
if (!$dbcon || !$dbcon->preparedStmt($query, $bindList)) {
    $result = sprintf(pacsone_gettext("Failed to schedule database job to check database integrity.<p>Database error: %s"), $dbcon->getError());
} else {
    $jobid = $dbcon->insert_id("dbjob");
}
// log activity to system journal
$dbcon->logJournal($username, "IntegrityCheck", "Image", "N/A");

if (empty($result) && isset($jobid)) {   // success
    print "<p>";
    printf(pacsone_gettext("<a href=\"%s\">Database Job %d</a> has been scheduled to perform the database integrity check."), "status.php", $jobid);
    print "<p>";
}
else {                                  // error
    print "<h3><font color=red>";
    print $result;
    print "</font></h3>";
}

require_once 'footer.php';
print "</body>\n";
print "</html>\n";

?>
