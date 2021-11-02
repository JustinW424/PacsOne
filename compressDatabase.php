<?php
//
// compressDatabase.php
//
// Module for compressing all images stored in the database using one of the Dicom
// lossless transfer syntaxes supported
//
// CopyRight (c) 2015-2020 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once 'database.php';
include_once 'xferSyntax.php';

// main
global $PRODUCT;
$dbcon = new MyConnection();
$username = $dbcon->username;

print "<html>\n";
print "<head><title>$PRODUCT - ";
print pacsone_gettext("Compress Entire Database");
print "</title></head>\n";
print "<body>\n";
require_once 'header.php';
// check and make sure no database compression job is already running
$compress = $dbcon->query("select id from dbjob where type='CompressDatabase' and (status='processing' or status='submitted')");
if ($compress && $compress->rowCount()) {
    $id = $compress->fetchColumn();
    $msg = "<h3><font color=red>";
    $msg .= sprintf(pacsone_gettext("Another database compression job <a href=\"status.php\">%d</a> is already running!"), $id);
    $msg .= "<p>";
    $msg .= pacsone_gettext("Please wait for the current compression job to be completed before starting a new one.");
    $msg .= "</font></h3>";
    $url = "tools.php?page=" . urlencode(pacsone_gettext("Compress Entire Database"));
    $msg .= "<p><a href=\"$url\">";
    $msg .= pacsone_gettext("Back");
    $msg .= "</a>";
    die($msg);
}
global $LOSSLESS_SYNTAX_TBL;
$tokens = explode(" - ", $_POST['xfersyntax']);
$syntax = trim($tokens[0]);
// schedule database job to perform the database integrity check
$query = "insert into dbjob (username,aetitle,type,uuid,class,submittime,status,details) ";
$query .= "values(?,'_','CompressDatabase',?,'System',";
$query .= $dbcon->useOracle? "SYSDATE," : "NOW(),";
$query .= "'submitted',NULL)";
$bindList = array($username, $syntax);
$result = "";
if (!$dbcon || !$dbcon->preparedStmt($query, $bindList)) {
    $result = sprintf(pacsone_gettext("Failed to schedule database job to compress entire database.<p>Database error: %s"), $dbcon->getError());
} else {
    $jobid = $dbcon->insert_id("dbjob");
}
// log activity to system journal
$dbcon->logJournal($username, "CompressDatabase", "Image", "N/A");

if (empty($result) && isset($jobid)) {   // success
    print "<p>";
    printf(pacsone_gettext("<a href=\"%s\">Database Job %d</a> has been scheduled to compress the entire database."), "status.php", $jobid);
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
