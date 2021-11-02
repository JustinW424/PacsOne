<?php
//
// weeklyreport.php
//
// Module for generating weekly email reports for studies received
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
if (!isset($argv))
    session_start();

require_once 'locale.php';
include_once 'database.php';
include_once 'sharedData.php';
require_once "deencrypt.php" ;
require_once "smtpPhpMailer.php";

// main
global $PRODUCT;
global $ONE_DAY;
if (isset($argv) && count($argv)) {
    require_once "utils.php";
    $database = $argv[1];
    $username = $argv[2];
    $password = $argv[3];
    $aetitle = $argv[4];
    $hostname = getDatabaseHost($aetitle);
    $dbcon = new MyDatabase($hostname, $database, $username, $password, $aetitle);
} else {
    $dbcon = new MyConnection();
}
$result = $dbcon->query("SELECT * from smtp");
// only support one SMTP server for now
if (!$result || ($result->rowCount() == 0) ||
    !($row = $result->fetch(PDO::FETCH_ASSOC))) {
    print "<h3><font color=red>";
    print pacsone_gettext("No SMTP Server is configured.");
    print "</font></h3>";
    exit();
}
$result = $dbcon->query("SELECT adminemail from config");
if (!$result || ($result->rowCount() == 0) ||
    !($config = $result->fetch(PDO::FETCH_NUM))) {
    print "<h3><font color=red>";
    print pacsone_gettext("No <u>TO:</u> is configured.");
    print "</font></h3>";
    exit();
}

$to = $config[0];    
$smtp = new smtpPhpMailer($row);
$since = getdate(time() - 7 * $ONE_DAY);
$fromdate = date("Y-m-d", $since[0] - $since['wday'] * $ONE_DAY);
$todate = date("Y-m-d", strtotime($fromdate) + 7 * $ONE_DAY);
$subject = sprintf(pacsone_gettext("%s Weekly Report - Studies Received From %s To %s"), $PRODUCT, $fromdate, $todate);
$smtp->Subject = $subject;
// generate statistics report
$images = 0;
$totalSize = 0;
require_once "statistics.php";
$rows = generateStats($dbcon, 4, $image, $totalSize, $fromdate, $todate, "");
// add plain text message
$smtp->AltBody = emailStats(0, $subject, $dbcon, $rows, $image, $totalSize);
// add HTML message
$smtp->Body = emailStats(1, $subject, $dbcon, $rows, $image, $totalSize);
// split multiple email addresses
$tos = preg_split("/[\s,;]+/", $to);
foreach ($tos as $entry)
    $smtp->addAddress($entry);
if($smtp->send())
    printf(pacsone_gettext("Message sent to %s.\n"), $to);
else
    printf(pacsone_gettext("Cound not send the message to %s.\nError: %s\n"), $to, $smtp->ErrorInfo);

?>
