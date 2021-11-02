<?php
//
// journalreport.php
//
// Module for generating monthly email reports for system journal
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
if (!isset($argv))
    session_start();

require_once "locale.php";
include_once 'database.php';
include_once 'sharedData.php';
require_once "deencrypt.php" ;
require_once "smtpPhpMailer.php";

// main
global $PRODUCT;
if (isset($argv) && count($argv)) {
    $hostname = getDatabaseHost($aetitle);
    $database = $argv[1];
    $username = $argv[2];
    $password = $argv[3];
    $aetitle = $argv[4];
    require_once "utils.php";
    $dbcon = new MyDatabase($hostname, $database, $username, $password, $aetitle);
} else {
    $dbcon = new MyConnection();
}
$result = $dbcon->query("SELECT * from smtp");
// only support one SMTP server for now
if (!$result || ($result->rowCount() == 0) ||
    !($row = $result->fetch(PDO::FETCH_ASSOC)))
    die ("<h3><font color=red>" . pacsone_gettext("No SMTP Server is configured.") . "</font></h3>");
$result = $dbcon->query("SELECT adminemail from config");
if (!$result || ($result->rowCount() == 0) ||
    !($config = $result->fetch(PDO::FETCH_NUM)))
    die ("<h3><font color=red>" . pacsone_gettext("No <u>TO:</u> is configured.") . "</font></h3>");

$to = $config[0];    
$smtp = new smtpPhpMailer($row);
// days in month table
$since = getdate();
$month = $since['mon'];
if ($month == 1) {
    $fromdate = ($since['year'] - 1) . "1201";
    $todate = $since['year'] . "0101";
} else {
    $fromdate = sprintf("%4d%02d01", $since['year'], $month-1);
    $todate = sprintf("%04d%02d01", $since['year'], $month);
}
$subject = sprintf(pacsone_gettext("%s Monthly System Journal - From %s To %s"),
    $PRODUCT, $fromdate, $todate);
$smtp->Subject = $subject;
// generate journal report
require_once "statistics.php";
$rows = array();
$result = $dbcon->query("select * from journal where timestamp >= $fromdate and timestamp < $todate order by timestamp asc");
if ($result) {
    while ($row = $result->fetch(PDO::FETCH_ASSOC))
        $rows[] = $row;
}
// add plain text message
$message = emailJournal(0, $subject, $dbcon, $rows);
$smtp->AltBody = $message;
// add HTML message
$message = emailJournal(1, $subject, $dbcon, $rows);
$smtp->Body = $message;
// split multiple email addresses
$tos = preg_split("/[\s,;]+/", $to);
foreach ($tos as $entry)
    $smtp->addAddress($entry);
if($smtp->send())
    printf(pacsone_gettext("Message sent to %s.\n"), $to);
else
    printf(pacsone_gettext("Cound not send the message to %s.\nError: %s\n"), $to, $smtp->ErrorInfo);
?>
