<?php
//
// dailyreport.php
//
// Module for generating daily email reports for studies received
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
if (!isset($argv))
    session_start();

include_once 'database.php';
include_once 'sharedData.php';
require_once "deencrypt.php" ;
require_once "smtpPhpMailer.php";

// main
global $PRODUCT;
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
    !($row = $result->fetch(PDO::FETCH_ASSOC)))
    die ("<h3><font color=red>No SMTP Server is configured.</font></h3>");
$result = $dbcon->query("SELECT adminemail from config");
if (!$result || ($result->rowCount() == 0) ||
    !($config = $result->fetch(PDO::FETCH_NUM)))
    die ("<h3><font color=red>No <u>TO:</u> is configured.</font></h3>");

$to = $config[0];    
$smtp = new smtpPhpMailer($row);
$subject = sprintf(pacsone_gettext("%s Daily Report - Studies Received %s"), $PRODUCT, date("l F jS, Y", time() - 24 * 60 * 60));
$smtp->Subject = $subject;
// generate statistics report
$images = 0;
$totalSize = 0;
require_once "statistics.php";
$rows = generateStats($dbcon, 0, $image, $totalSize, "", "", "");
// add plain text message
$smtp->AltBody = emailStats(0, $subject, $dbcon, $rows, $image, $totalSize);
// add HTML message
$smtp->Body = emailStats(1, $subject, $dbcon, $rows, $image, $totalSize);
// split multiple email addresses
$tos = preg_split("/[\s,;]+/", $to);
foreach ($tos as $entry)
    $smtp->addAddress($entry);
if ($smtp->send())
    echo "Message sent to $to.\n";
else
    echo "Cound not send the message to $to.\nError: " . $smtp->ErrorInfo . "\n"
?>
