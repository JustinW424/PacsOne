<?php
//
// remote.php
//
// Main menu for remote application entity operations: C-ECHO, C-FIND and C-MOVE
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once 'security.php';
include_once 'sharedData.php';
require_once 'header.php';

global $PRODUCT;
print "<html>";
print "<head><title>$PRODUCT - ";
print pacsone_gettext("Remote Application Entities");
print "</title></head>";
print "<body>";
$dbcon = new MyConnection();
// main
require_once 'display.php';
// display list of configured remote SCPs
$result = $dbcon->query("SELECT * FROM applentity WHERE port IS NOT NULL order by title asc");
$count = $result->rowCount();
if ($count > 1)
    $preface = sprintf(pacsone_gettext("There are %d remote SCP application entities defined."), $count);
else
    $preface = sprintf(pacsone_gettext("There is %d remote SCP application entity defined."), $count);
displayApplEntity($result, $preface);

require_once 'footer.php';
print "</body>";
print "</html>";
?>
