<?php
//
// storageFormat.php
//
// Module for modifying all images stored in the database to add the Dicom
// Part-10 Meta-Information Header
//
// CopyRight (c) 2018-2020 RainbowFish Software
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
print pacsone_gettext("Change Storage Format");
print "</title></head>\n";
print "<body>\n";
require_once 'header.php';
// check and make sure no prior change storage format database job is already running
$compress = $dbcon->query("select id from dbjob where type='StorageFormat' and (status='processing' or status='submitted')");
if ($compress && $compress->rowCount()) {
    $id = $compress->fetchColumn();
    $msg = "<h3><font color=red>";
    $msg .= sprintf(pacsone_gettext("Another change storage format database job <a href=\"status.php\">%d</a> is already running!"), $id);
    $msg .= "<p>";
    $msg .= pacsone_gettext("Please wait for the current job to be completed before starting a new one.");
    $msg .= "</font></h3>";
    $url = "tools.php?page=" . urlencode(pacsone_gettext("Change Storage Format"));
    $msg .= "<p><a href=\"$url\">";
    $msg .= pacsone_gettext("Back");
    $msg .= "</a>";
    die($msg);
}
$tokens = explode(" - ", $_POST['format']);
$format = trim($tokens[0]);
// schedule database job to perform the change of storage format
$query = "insert into dbjob (username,aetitle,type,uuid,class,submittime,status,details) ";
$query .= "values(?,'_','StorageFormat',?,'System',";
$bindList = array($username, $format);
$query .= $dbcon->useOracle? "SYSDATE," : "NOW(),";
$query .= "'submitted',NULL)";
$result = "";
if (!$dbcon || !$dbcon->preparedStmt($query, $bindList)) {
    $result = sprintf(pacsone_gettext("Failed to schedule database job to change storage format.<p>Database error: %s"), $dbcon->getError());
} else {
    $jobid = $dbcon->insert_id("dbjob");
}
// log activity to system journal
$dbcon->logJournal($username, "ChangeStorageFormat", "Image", "N/A");

if (empty($result) && isset($jobid)) {   // success
    print "<p>";
    printf(pacsone_gettext("<a href=\"%s\">Database Job %d</a> has been scheduled to change storage format for the entire database."), "status.php", $jobid);
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
