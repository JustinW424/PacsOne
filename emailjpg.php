<?php
//
// emailjpg.php
//
// Module for emailing converted JPG/GIF image to user's email address
//
// CopyRight (c) 2004-2020 RainbowFish Software
//
session_start();

require_once "locale.php";
include_once 'database.php';
include_once 'sharedData.php';
require_once "deencrypt.php" ;
require_once "smtpPhpMailer.php";

// main
global $PRODUCT;
global $CUSTOMIZE_PATIENT_ID;
global $CUSTOMIZE_PATIENT_NAME;
$dbcon = new MyConnection();
$result = $dbcon->query("SELECT * from smtp");
if (!$result || ($result->rowCount() == 0) ||
    !($row = $result->fetch(PDO::FETCH_ASSOC)))
    die ("<h3><font color=red>" . pacsone_gettext("No SMTP Server is configured.") . "</font></h3>");
if (!isset($_POST['recipients']) || !isset($_POST['cc']))
    die ("<h3><font color=red>" . pacsone_gettext("No <u>TO:</u> email address is configured") . "</font></h3>");

$to = $_POST['recipients'];
$cc = $_POST['cc'];
if (strlen($to) == 0)
    $to = $cc;
$imagefile = $_POST['imagefile'];
$default = pacsone_gettext("N/A");
$patientid = strlen($_POST["patientid"])? $_POST["patientid"] : $default;
$patientname = strlen($_POST["patientname"])? $_POST["patientname"] : $default;
$studyid = strlen($_POST["studyid"])? $_POST["studyid"] : $default;
$seriesnum = isset($_POST["seriesnum"])? $_POST["seriesnum"] : $default;
$instance = isset($_POST["instance"])? $_POST["instance"] : $default;

$smtp = new smtpPhpMailer($row);
$subject = sprintf(pacsone_gettext("Converted %s Image from %s"),
    strtoupper(substr($imagefile, -3)), $PRODUCT);
$smtp->Subject = $subject;
// add plain text message
/*
$message = "\n$subject\n";
$message .= "\n" . $CUSTOMIZE_PATIENT_ID . ":\t" . "$patientid";
$message .= "\n" . $CUSTOMIZE_PATIENT_NAME . ":\t" . "$patientname";
$message .= "\n" . pacsone_gettext("Study ID:") . "\t" . "$studyid";
$message .= "\n" . pacsone_gettext("Series Number:") . "\t" . "$seriesnum";
$message .= "\n" . pacsone_gettext("Instance Number:") . "\t" . "$instance";
$message .= "\n";
$smtp->AltBody = $message;
 */
// add HTML message
$message = "<html><head><title>$subject</title></head>";
$message .= "<body><p>$subject<p>";
$message .= "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">";
$message .= "<tr><td><b>" . $CUSTOMIZE_PATIENT_ID . ":</b></td>";
$message .= "<td>$patientid</td></tr>";
$message .= "<tr><td><b>" . $CUSTOMIZE_PATIENT_NAME . ":</b></td>";
$message .= "<td>$patientname</td></tr>";
$message .= "<tr><td><b>" . pacsone_gettext("Study ID:") . "</b></td>";
$message .= "<td>$studyid</td></tr>";
$message .= "<tr><td><b>" . pacsone_gettext("Series Number:") . "</b></td>";
$message .= "<td>$seriesnum</td></tr>";
$message .= "<tr><td><b>" . pacsone_gettext("Instance Number:") . "</b></td>";
$message .= "<td>$instance</td></tr>";
$message .= "</table></body></html>";
$smtp->Body = $message;
// add JPG/GIF attachment
$contentType = strcasecmp(substr($imagefile, -3), "jpg")? "image/gif" : "image/jpeg";
$encoding = 'base64';
$disp = 'attachment';
$smtp->addAttachment($imagefile, basename($imagefile), $encoding, $contentType, $disp);
// split multiple email addresses
$tos = preg_split("/[\s,;]+/", $to);
foreach ($tos as $entry)
    $smtp->addAddress($entry);
$tos = preg_split("/[\s,;]+/", $cc);
foreach ($tos as $entry)
    $smtp->addCc($entry);
require_once "header.php";
if($smtp->send())
    printf(pacsone_gettext("<p>Converted %s image has been sent to: <u>%s</u>.<br>"),
        strtoupper(substr($imagefile, -3)), $to);
else {
    printf(pacsone_gettext("Cound not send the message to %s.<br>"), $to);
    printf(pacsone_gettext("Error: %s<br>"), $smtp->ErrorInfo);
}
require_once "footer.php";
?>
