<?php
//
// applentity.php
//
// Module for managing the Application Entity table
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
print pacsone_gettext("Application Entity Titles");
print "</title></head>";
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
$result = $dbcon->query("SELECT * FROM applentity order by title asc");
$num_rows = $result->rowCount();
// retrieve current configurations
$config = $dbcon->query("SELECT * FROM config");
$row = $config->fetch(PDO::FETCH_ASSOC);
print "<p>";
printf(pacsone_gettext("Current %s Configurations:"), $PRODUCT);
print "<UL>\n";
print "<LI>" . pacsone_gettext("Application Entity Title: ");
print "<b>" . $row["aetitle"] . "</b></LI>\n";
print "<LI>" . pacsone_gettext("Hostname: ");
print "<b>" . $_SERVER["SERVER_NAME"] . "</b></LI>\n";
print "<LI>" . pacsone_gettext("TCP Port Number: ");
print "<b>" . $row["port"] . "</b></LI>\n";
if ($row["tlsport"]) {
    print "<LI>" . pacsone_gettext("TLS Port Number: ");
    print "<b>" . $row["tlsport"] . "</b></LI>\n";
}
// display maximum number of AEs supported
$dir = dirname($_SERVER['SCRIPT_FILENAME']);
$dir = substr($dir, 0, strlen($dir) - 3);
$aelimit = $dir . "license.aes";
if (file_exists($aelimit)) {
    if ($fp = fopen($aelimit, "r")) {
        $aelimit = 10;
        if (fscanf($fp, "%d", $aelimit) == 1) {
            print "<LI>" . pacsone_gettext("Maximum Number of AE Supported: ");
            $value = ($aelimit == 0xFFFF)? pacsone_gettext("Unlimited") : $aelimit;
            print "<b>$value</b></LI>";
            // update the CONFIG table
            $dbcon->query("update config set maxaes=$aelimit");
        }
    }
}
print "</UL>\n";
$preface = sprintf(pacsone_gettext("There are %d Application Entities defined:"), $num_rows);
displayApplEntity($result, $preface);

require_once 'footer.php';

print "</table>";
print "</body>";
print "</html>";
?>

