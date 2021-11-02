<?php
//
// markStudy.php
//
// Module for marking a study public or private
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once "locale.php";
include_once 'database.php';
include_once 'security.php';
include_once 'utils.php';

$dbcon = new MyConnection();
$id = $_REQUEST['id'];
if (!isUidValid($id)) {
	print "<font color=red>";
    printf(pacsone_gettext("Invalid Study Instance UID: <u>%s</u>"), $id);
    print "</font>";
    exit();
}
$current = $_REQUEST['current'];
// find patient id
$query = "select patientid from study where uuid=?";
$bindList = array($id);
$result = $dbcon->preparedStmt($query, $bindList);
$patientid = $result->fetchColumn();
// count the number of studies for this patient
$query = "select origid from patient where origid=?";
$bindList = array($patientid);
$result = $dbcon->preparedStmt($query, $bindList);
$count = $result->rowCount();
// toggle private/public flag
$value = 1 - $current;
$query = "update study set private=? where uuid=?";
$bindList = array($value, $id);
$result = $dbcon->preparedStmt($query, $bindList);
if (!$result) {
	print "<font color=red>";
    printf(pacsone_gettext("Error executing SQL query: %s, error = %s"), $query, $dbcon->getError());
    print "</font>";
    exit();
} else {
    // if this study is the only study for the patient, also update the privacy
    // attribute of the patient
    if ($count == 1) {
        $query = "update patient set private=? where origid=?";
        $bindList = array($value, $patientid);
        $result = $dbcon->preparedStmt($query, $bindList);
    }
    if (!$result) {
	    print "<font color=red>";
        printf(pacsone_gettext("Error executing SQL query: %s, error = %s"), $query, $dbcon->getError());
        print "</font>";
        exit();
    }
}
if ($result)
	header("Location: study.php?patientId=" . urlencode($patientid));
?>
