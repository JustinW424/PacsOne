<?php
//
// oruReports.php
//
// Module for display observation reports from the received ORU message
//
// CopyRight (c) 2009-2020 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once 'database.php';
include_once 'sharedData.php';

// main
global $PRODUCT;
$dbcon = new MyConnection();

include_once 'checkUncheck.js';
global $PRODUCT;
global $BGCOLOR;
// display Manually Enter Worklist Data form
print "<html>\n";
print "<head><title>$PRODUCT - ";
print pacsone_gettext("Observation Reports");
print "</title></head>\n";
print "<body>\n";
require_once 'header.php';
$uid = $_REQUEST["uuid"];
$accession = $_REQUEST["accessionnum"];
print "<p>";
printf(pacsone_gettext("Observation reports for Accession Number: <u>%s</u>"), $accession);
print "<p>";
print "<table class='table table-hover table-bordered table-striped' width=100% border=1 cellspacing=0 cellpadding=0>\n";
$columns = array(
    pacsone_gettext("Type")         => "valuetype",
    pacsone_gettext("Observation")  => "value",
    pacsone_gettext("Status")       => "resultstatus",
);
print "<tr class='tableHeadForBGUp'>\n";
foreach (array_keys($columns) as $key) {
    print "\t<td><b>$key</b></td>\n";
}
print "</tr>";
$bindList = array($uid);
$result = $dbcon->preparedStmt("select * from hl7segobx where uuid=?", $bindList);
while ($result && ($row = $result->fetch(PDO::FETCH_ASSOC))) {
    print "<tr style='background-color:white;'>";
    foreach ($columns as $key => $field) {
        $value = $row[$field];
        print "<td>$value</td>";
    }
    print "</tr>";
}
print "</table>";

require_once 'footer.php';
print "</body>\n";
print "</html>\n";

