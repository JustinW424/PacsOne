<?php
//
// checkTables.php
//
// Diagnostic module for checking database tables
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once 'security.php';
include_once 'display.php';

global $PRODUCT;
global $PACSONE_TABLES;
print "<html>";
print "<head><title>$PRODUCT - ";
print pacsone_gettext("Check Database Tables");
print "</title></head>";
print "<body>";
require_once 'header.php';

$dbcon = new MyConnection();
printf(pacsone_gettext("<p>Total of <b>%d</b> tables configured.<p>"), count($PACSONE_TABLES));
$result = $dbcon->query("show tables");
while ($row = $result->fetch(PDO::FETCH_NUM)) {
    $table = $row[0];
    if (!in_array($table, $PACSONE_TABLES)) {
        printf(pacsone_gettext("Table <b>%s</b> exists but not configured!<br>"), $table);
    }
}

require_once 'footer.php';
print "</table>";
print "</body>";
print "</html>";
?>

