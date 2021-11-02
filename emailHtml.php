<?php
//
// emailHtml.php
//
// Module for emailing inline HTML document
//
// CopyRight (c) 2008-2021 RainbowFish Software
//
session_start();

function emailHtml(&$to, &$subject, &$html, $atts = array())
{
    error_reporting(E_ERROR);
    ob_start();
    require_once "deencrypt.php" ;
    require_once 'utils.php';
    require_once "smtpPhpMailer.php";
    global $dbcon;
    $result = $dbcon->query("SELECT * from smtp");
    // only support one SMTP server for now
    if (!$result || ($result->rowCount() == 0) ||
        !($row = $result->fetch(PDO::FETCH_ASSOC))) {
            $result = "<h3><font color=red>";
            $result .= pacsone_gettext("No SMTP Server is configured.");
            $result .= "</font></h3>";
            return $result;
    }
    $result = "";
    $smtp = new smtpPhpMailer($row);
    // send study notes information
    $smtp->Subject = $subject;
    // add HTML message
    $smtp->Body = $html;
    // split multiple email addresses
    $tos = preg_split("/[\s,;]+/", $to);
    foreach ($tos as $entry)
        $smtp->addAddress($entry);
    // add attachments if any
    foreach ($atts as $attach)
        $smtp->addAttachment($attach['file'], $attach['basename'], $attach['encoding'], $attach['contenttype'], $attach['displacement']);
    if(!$smtp->send())
        $result = sprintf(pacsone_gettext("Cound not send the message to %s.\nError: %s\n"), $to, $smtp->ErrorInfo);
    while (@ob_end_clean());
    // remove temporary attachment files
    foreach ($atts as $attach)
        if ($attach['remove'] && file_exists($attach['file']))
            unlink($attach['file']);
    // return status
    $dir = dirname($_SERVER['SCRIPT_FILENAME']);
    $dir = str_replace("\\", "/", $dir);
    $file = $dir . "/emailHtml.log";
    if ($fp = fopen($file, "w")) {
        if (strlen($result)) {
            fwrite($fp, $result);
        } else {
            fwrite($fp, "HTML email successfully sent to $to.");
        }
        fclose($fp);
    }
    return $result;
}

?>
