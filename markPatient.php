<?php
//
// markPatient.php
//
// Module for marking a patient public or private
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once "locale.php";
include_once 'database.php';
include_once 'security.php';

$dbcon = new MyConnection();
$id = urldecode($_REQUEST['id']);
if (preg_match("/[;\"]/", $id) {
	print "<font color=red>";
    printf(pacsone_gettext("Invalid Patient ID: <u>%s</u>"), $id);
    print "</font>";
    exit();
}
$current = $_REQUEST['current'];
$value = 1 - $current;
$query = "update patient set private=? where origid=?";
$bindList = array($value, $id);
$result = $dbcon->preparedStmt($query, $bindList);
if (!$result) {
	print "<font color=red>";
    printf(pacsone_gettext("Error executing SQL query: %s, error = %s"), $query, $dbcon->getError());
    print "</font>";
    exit();
} else {
    // make all studies of this patient Public or Private subsequently
    $query = "update study set private=? where patientid=?";
    $result = $dbcon->preparedStmt($query, $bindList);
    if (!$result) {
	    print "<font color=red>";
        printf(pacsone_gettext("Error executing SQL query: %s, error = %s"), $query, $dbcon->getError());
        print "</font>";
        exit();
    }
}
if ($result)
    header("Location: browse.php");
?>
