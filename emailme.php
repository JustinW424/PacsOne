<?php
//
// emailme.php
//
// Module for emailing online statistics report to user's email address
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once "locale.php";
include_once 'database.php';
include_once 'sharedData.php';
require_once "smtpPhpMailer.php";

// main
global $PRODUCT;
$dbcon = new MyConnection();
$result = $dbcon->query("SELECT * from smtp");
if (!$result || ($result->rowCount() == 0) ||
    !($row = $result->fetch(PDO::FETCH_ASSOC)))
    die ("<h3><font color=red>" . pacsone_gettext("No SMTP Server is configured.") . "</font></h3>");
if (!isset($_POST['to']) || !isset($_POST['studies']))
    die ("<h3><font color=red>" . pacsone_gettext("No <u>TO:</u> is configured or no studies to report.") . "</font></h3>");

$to = $_POST['to'];
$studies = $_POST['studies'];
$preface = "";
if (isset($_POST['preface']))
    $preface = $_POST['preface'];
$smtp = new smtpPhpMailer($row);
print "<p>";
$smtp->Subject = sprintf(pacsone_gettext("Statistics Report from %s"), $PRODUCT);
require_once "statistics.php";
// add plain text message
$stmp->AltBody = emailReport(0, $preface, $dbcon, $studies);
// add HTML message
$smtp->Body = emailReport(1, $preface, $dbcon, $studies);
// split multiple email addresses
$tos = preg_split("/[\s,;]+/", $to);
foreach ($tos as $entry)
    $smtp->addAddress($entry);
require_once "header.php";
if($smtp->send())
    printf(pacsone_gettext("A report email has been sent to: <u>%s</u>.<br>"), $to);    
else {
    printf(pacsone_gettext("Cound not send the message to %s.<br>"), $to);
    printf(pacsone_gettext("Error: %s<br>"), $smtp->ErrorInfo);
}
require_once "footer.php";
?>
