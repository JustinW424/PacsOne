<?php
//
// emailTrialExpiration.php
//
// Module for sending email about trial license expiration
//
// CopyRight (c) 2007-2020 RainbowFish Software
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
if (isset($argv) && count($argv)) {
    require_once "utils.php";
    $database = $argv[1];
    $username = $argv[2];
    $password = $argv[3];
    $aetitle = $argv[4];
    $days = $argv[5];
    $hostname = getDatabaseHost($aetitle);
    $dbcon = new MyDatabase($hostname, $database, $username, $password, $aetitle);
} else {
    $dbcon = new MyConnection();
    $username = $dbcon->username;
    $days = $_GET['days'];
}
$result = $dbcon->query("SELECT * from smtp");
if (!$result || ($result->rowCount() == 0) ||
    !($row = $result->fetch(PDO::FETCH_ASSOC)))
    die ("<h3><font color=red>" . pacsone_gettext("No SMTP Server is configured.") . "</font></h3>");
$result = $dbcon->query("SELECT adminemail from config");
if (!$result || ($result->rowCount() == 0) ||
    !($config = $result->fetch(PDO::FETCH_NUM)))
    die ("<h3><font color=red>" . pacsone_gettext("Administrator's Email Address is configured.") . "</font></h3>");

$to = $config[0];
$smtp = new smtpPhpMailer($row);
$subject = sprintf(pacsone_gettext("%s - Trial License Will Expire In %d Days"),
    $PRODUCT, $days);
$smtp->Subject = $subject;
// add plain text message
$message = "\n" . pacsone_gettext("Attention:") . "\n";
$message .= "\n" . sprintf(pacsone_gettext("Your current %s trial license will expire in %d days."), $PRODUCT, $days);
$message .= "\n";
$message .= "\n" . sprintf(pacsone_gettext("Please contact mailto:licensing.info@pacsone.net and purchase a full %s license to avoid service interruptions."), $PRODUCT);
$smtp->AltBody = $message;
// add HTML message
$message = "<html><head><title>$subject</title></head>";
$message .= "<body><p>";
$message = "<b>" . pacsone_gettext("Attention:") . "</b>";
$message .= "<p>" . sprintf(pacsone_gettext("Your current %s trial license will expire in %d days."), $PRODUCT, $days);
$message .= "<br>" . sprintf(pacsone_gettext("Please contact <u>mailto:licensing.info@pacsone.net</u> and purchase a full %s license to avoid service interruptions."), $PRODUCT);
$message .= "</body></html>";
$smtp->Body = $message;
if (!isset($argv))
    require_once "header.php";
// split multiple email addresses
$tos = preg_split("/[\s,;]+/", $to);
foreach ($tos as $entry)
    $smtp->addAddress($entry);
if($smtp->send())
    printf(pacsone_gettext("<p>$PRODUCT - Trial license expiration email has been sent to: <u>%s</u>.<br>"), $to);
else {
    printf(pacsone_gettext("Cound not send the message to %s.<br>"), $to);
    printf(pacsone_gettext("Error: %s<br>"), $smtp->ErrorInfo);
}
if (!isset($argv))
    require_once "footer.php";

?>
