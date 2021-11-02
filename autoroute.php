<?php
//
// autoroute.php
//
// Module for managing the automatic routing table
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once 'security.php';
include_once 'display.php';

global $PRODUCT;
print "<html>";
print "<head><title>$PRODUCT - ";
print pacsone_gettext("Automatic Routing Table");
print "</title></head>";
print "<body>";
require_once 'header.php';
$dbcon = new MyConnection();
$username = $dbcon->username;
$view = $dbcon->hasaccess("viewprivate", $username);
$modify = $dbcon->hasaccess("modifydata", $username);
if (!$view && !$modify) {
    print "<h3><font color=red>";
    print pacsone_gettext("You do not have the required privilege to view the requested information.");
    print "</font></h3>";
    exit();
}
// Dicom routes
$result = $dbcon->query("SELECT * FROM autoroute");
$num_rows = $result->rowCount();
if ($num_rows > 1)
    $preface = sprintf(pacsone_gettext("There are %d Dicom Routing Entries defined:"), $num_rows);
else
    $preface = sprintf(pacsone_gettext("There is %d Dicom Routing Entry defined:"), $num_rows);
displayRouteEntry($result, $preface);
// MPPS routes
$result = $dbcon->query("SELECT * FROM mppsroute");
$num_rows = $result->rowCount();
if ($num_rows > 1)
    $preface = sprintf(pacsone_gettext("There are %d Modality Performed Procedure Step (MPPS) Routing Entries defined:"), $num_rows);
else
    $preface = sprintf(pacsone_gettext("There is %d Modality Performed Procedure Step (MPPS) Routing Entry defined:"), $num_rows);
displayRouteEntry($result, $preface, true);
// Hl-7 Message routes
if (isHL7OptionInstalled()) {
    $result = $dbcon->query("SELECT * FROM hl7route");
    $num_rows = $result->rowCount();
    if ($num_rows > 1)
        $preface = sprintf(pacsone_gettext("There are %d HL7 Message Routing Entries defined:"), $num_rows);
    else
        $preface = sprintf(pacsone_gettext("There is %d HL7 Message Routing Entry defined:"), $num_rows);
    displayHL7Route($result, $preface);
}
require_once 'footer.php';
print "</table>";
print "</body>";
print "</html>";
?>
