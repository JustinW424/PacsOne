<?php
//
// importStudy.php
//
// Module for importing external studies into local database
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once "locale.php";
include_once 'security.php';
include_once 'display.php';

global $PRODUCT;
print "<html>";
print "<head><title>$PRODUCT - " . pacsone_gettext("Tools") . "</title></head>";
print "<body>";
require_once 'header.php';

$dbcon = new MyConnection();
$username = $dbcon->username;
$type = $_POST["type"];
$directory = cleanPostPath($_POST["directory"]);
$drive = cleanPostPath($_POST["drive"]);
$all = $_POST["all"];
if ($type == 1) {
    $directory = $_POST["destdir"];
    if (!strlen($directory) || !file_exists($directory)) {
	    print "<p><font color=red>";
        printf(pacsone_gettext("Invalid destination archive directory: <b>%s</b>."), $directory);
        print "</font>";
        exit();
    }
}

//print "dir1 = ".$directory."\n";

$directory = strtr($directory, "\\", "/");
$drive = strtr($drive, "\\", "/");
if ($directory[strlen($directory)-1] != '/')
	$directory .= "/";
if ($drive[strlen($drive)-1] != '/')
	$drive .= "/";
$dicomdir = (($type == 1)? $drive : $directory) . "DICOMDIR";
if (!file_exists($dicomdir)) {
    $message = sprintf(pacsone_gettext("WARNING: DICOM Directory Record File: %s does not exist"), $dicomdir);
    print "<script language=\"JavaScript\">\n";
    print "<!--\n";
    print "alert(\"$message\");";
    print "//-->\n";
    print "</script>\n";
} else {
    if (version_compare(PHP_VERSION, '7.0.0') < 0)
        set_magic_quotes_runtime(0);

    //print "type=".$type."  srcDir= ". $dicomdir. "\n";

	$handle = fopen($dicomdir, "rb");
	$data = fread($handle, 132);
    $data = substr($data, 128);
    if (strcmp($data, "DICM")) {
	    print "<p><font color=red>";
        printf(pacsone_gettext("Invalid DICOMDIR file: <b>%s</b>."), $dicomdir);
        print "</font>";
        exit();
    }
	fclose($handle);
}
$title = "_";
if ($type == 1)
    $title .= $drive;
$import = $all? "import" : "importscan";
// schedule a database job to perform the exporting task
$query = "insert into dbjob (username,aetitle,type,class,priority,schedule,uuid,submittime,status,details) ";
$query .= "values(?,?,'$import','study',1,-1,";
$bindList = array($username, $title); 
if ($dbcon->useOracle) {
    $query .= "TO_CHAR(SYSDATE, 'YYYY-MM-DD HH24:MI:SS'),SYSDATE,";
} else {
    $query .= "NOW(),NOW(),";
}
$query .= "'submitted',?)";
$bindList[] = $directory;

//print "query = ". $query. "\n";
//print "bindListA\n";
//print_r($bindList);
//print "\nbindListB";


if (!$dbcon->preparedStmt($query, $bindList)) {

    //print "\nimport fail\n";
    print "<p><font color=red>";
    printf(pacsone_gettext("Error: Failed to schedule database job for importing studies from <b>%s</b>.<br>"), $directory);
    print pacsone_gettext("Database Error: ") . $dbcon->getError() . "</font></p>";
} else {
    //print "\nimport ok\n";
	$id = $dbcon->insert_id("dbjob");
    $url = $all? "status.php" : "importScan.php?jobid=$id";
    //print " import-URL".$url."\n";
	printf(pacsone_gettext("Job: [<b><a href='$url'>%d</a></b>] has been scheduled to %s studies from: <b>%s</b>.<br>"), $id, ($all? "scan" : "import"), ($type == 1)? $drive : $directory);
    // log activity to system journal
    $dbcon->logJournal($username, "Import", $title, $directory);
}

require_once 'footer.php';
print "</table>";
print "</body>";
print "</html>";

?>
