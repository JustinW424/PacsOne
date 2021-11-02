<?php
//
// hl7app.php
//
// Module for managing the HL-7 Application table
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once "locale.php";
include_once 'security.php';
include_once 'display.php';

global $PRODUCT;
print "<html>";
print "<head><title>$PRODUCT - " . pacsone_gettext("HL7 Applications") . "</title></head>";
print "<body>";
require_once 'header.php';

$dbcon = new MyConnection();
$username = $dbcon->username;
$view = $dbcon->hasaccess("viewprivate", $username);
$modify = $dbcon->hasaccess("modifydata", $username);
$query = $dbcon->hasaccess("query", $username);
if (!$view && !$modify && !$query) {
    print "<h3><font color=red>";
    print pacsone_gettext("You do not have the required privilege to view the requested information.");
    print "</font></h3>";
    exit();
}
$result = $dbcon->query("SELECT * FROM hl7application");
$num_rows = $result->rowCount();
$preface = sprintf(pacsone_gettext("There are %d HL7 Applications defined:"), $num_rows);
displayHL7App($result, $preface);

require_once 'footer.php';

print "</table>";
print "</body>";
print "</html>";
?>
