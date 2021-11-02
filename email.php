<?php
//
// email.php
//
// Module for managing SMTP server configurations
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once "locale.php";
include_once 'security.php';
include_once 'display.php';

global $PRODUCT;
print "<html>";
print "<head><title>$PRODUCT - " . pacsone_gettext("Email Server Configurations") . "</title></head>";
print "<body>";
require_once 'header.php';

$dbcon = new MyConnection();
$username = $dbcon->username;
if (!$dbcon->hasaccess("admin", $username))
    die ("<h3><font color=red>" . pacsone_gettext("You must have the Admin privilege in order to access this page.") . "</font></h3>");

$result = $dbcon->query("SELECT * FROM smtp");
$num_rows = $result->rowCount();
$preface = sprintf(pacsone_gettext("There are %d SMTP Servers defined:"), $num_rows);
displaySmtpServer($result, $preface);

require_once 'footer.php';

print "</table>";
print "</body>";
print "</html>";
?>

