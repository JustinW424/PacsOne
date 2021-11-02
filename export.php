<?php
//
// export.php
//
// Module for processing entries submitted for exporting studies
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once "locale.php";
include_once 'database.php';

$dbcon = new MyConnection();
$username = $dbcon->username;
$action = "Export";
if (isset($_POST['actionvalue']))
    $action = $_POST['actionvalue'];
else if (isset($_POST['action']))
    $action = $_POST['action'];
$entry = array();
if (isset($_POST['entry']) && count($_POST['entry'])) {
    foreach ($_POST['entry'] as $uid) {
        if (!in_array($uid, $entry))
            $entry[] = $uid;
    }
}
$url = "exportStudy.php";
$zip = $_POST['zip'];
$viewer = $_POST['viewer'];
$purge = $_POST['purge'];
if (strcasecmp($action, "Update") == 0) {
    $_SESSION['ExportStudies'] = $entry;
    $url .= "?zip=$zip&viewer=$viewer";
    if (isset($_POST['sort']))
        $url .= "&sort=" . $_POST['sort'];
	// go back to the page
	header("Location: " . $url);
}
else if (strcasecmp($action, "Export") == 0) {
    require_once 'header.php';
    include_once 'sharedData.php';
    print "<p>\n";
    if (!isset($_POST['option']))
        $_SESSION['ExportStudies'] = $entry;
    $directory = $_SESSION['ExportDirectory'];
    // replace Windows-style slashes with Unix-syle
    $directory = str_replace("\\", "/", $directory);
    if (!file_exists($directory)) {
        print "<p><font color=red>";
        printf(pacsone_gettext("Error: Invalid export directory: <b>%s"), $directory);
        print "</b></font></p>";
        exit();
    }
    $label = $_SESSION['ExportMediaLabel'];
	if (!strlen($label)) {
	    print "<p><font color=red>";
        print pacsone_gettext("Error: A volume label of upto 16 characters need to be specified for exporting to external media.");
        print "</font></p>";
	    exit();
	}
    // create sub-folder named with the user-specified media label
    if (strcmp(substr($directory, strlen($directory)-1, 1), "/"))
    	$directory .= "/";
    $directory .= $label;
	if (!file_exists($directory) && !mkdir($directory)) {
	    print "<p><font color=red>";
        printf(pacsone_gettext("Export: Failed to create Sub-Directory [%s]."), $directory);
        print "</font></p>";
	    exit();
	}
    $tokens = explode(" - ", $_SESSION['ExportMedia']);
    $type = $tokens[0];
    global $EXPORT_MEDIA;
    $size = $EXPORT_MEDIA[$type][1];
    // check if there's any outstanding export to the same directory
    $bindList = array($directory);
    $result = $dbcon->preparedStmt("select id from dbjob where details=? and status!='success' and status!='failed'", $bindList);
    if ($result && $result->rowCount()) {
        $jobid = $result->fetchColumn();
        print "<p><font color=red>";
        printf(pacsone_gettext("Error: Job [%d] already active for exporting to local directory [%s]."), $jobid, $directory);
        print "<br>";
        exit();
    }
    // schedule a database job to perform the exporting task
    $level = "study";
    if (isset($_POST['option']))
        $level = $_POST['option'];
    $type = $zip? "exportZ" : "export";
    if ($purge)
        $type .= "P";
    $query = "insert into dbjob (username,aetitle,type,class,uuid,priority,status,details) values";
    $query .= "(?,'_$size','$type',?,?,?,'created',?)";
    $bindList = array($username, $level, $label, $viewer, $directory);
    if (!$dbcon->preparedStmt($query, $bindList)) {
        print "<p><font color=red>";
        print pacsone_gettext("Error: Failed to schedule database job for exporting studies.");
        print "<br>";
        printf(pacsone_gettext("Database Error: %s"), $dbcon->getError());
        print "</font></p>";
        exit();
    }
    $id = $dbcon->insert_id("dbjob");
    // fill the export table
    foreach ($entry as $uid) {
        if (strcasecmp($level, "Patient") == 0) {
            $uid = urldecode($uid);
        }
        $bindList = array($level, $uid);
        if ($dbcon->useOracle) {
            $subList = array($uid);
            $export = $dbcon->preparedStmt("select * from export where jobid=$id and uuid=?", $subList);
            if ($export && $export->rowCount()) {
                $dbcon->preparedStmt("update export set jobid=$id,class=? where jobid=$id and uuid=?", $bindList);
            } else {
                $dbcon->preparedStmt("insert into export (jobid,class,uuid) values($id,?,?)", $bindList);
            }
        } else {
            $dbcon->preparedStmt("replace export set jobid=$id,class=?,uuid=?", $bindList);
        }
        // log activity to system journal
        $dbcon->logJournal($username, "Export", $level, $uid);
    }
    // now the export job is ready to be picked up
    $q = "update dbjob set status='submitted',submittime=NOW() where id=$id";
    if ($dbcon->useOracle)
        $q = "update dbjob set status='submitted',submittime=SYSDATE where id=$id";
    $dbcon->query($q);
    $what = $level;
    if (count($entry) > 1) {
        $plural = "Unknown";
        global $PLURAL_TBL;
        if (isset($PLURAL_TBL[ strtoupper($level) ]))
            $plural = $PLURAL_TBL[ strtoupper($level) ];
        $what = $plural;
    }
    printf(pacsone_gettext("Job: [<b><a href='status.php'>%d</a></b>] has been scheduled to export the selected %d %s.<br>"), $id, count($entry), $what);
    print "<p>\n";
    require_once 'footer.php';
}

?>
