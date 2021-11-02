<?php
//
// emailfailedjob.php
//
// Module for sending email notifications for failed jobs
//
// CopyRight (c) 2003-2021 RainbowFish Software
//
if (!isset($argv))
    session_start();

function emailJob($html, &$subject, &$dbcon, $jobid)
{
    global $BGCOLOR;
    $NL = ($html)? "<br>" : "\n";
    if ($html) {
        $msg = "<html>";
        $msg .= "<head><title>$subject</title></head>";
        $msg .= "<body>";
        $msg .= "<p>$subject<p>";
    } else
        $msg = "$subject" . $NL;
    $msg .= $NL . "Job ID $jobid Has Failed" . $NL;
    $bindList = array($jobid);
    $result = $dbcon->preparedStmt("select * from dbjob where id=?", $bindList);
    $row = $result->fetch(PDO::FETCH_ASSOC);
    $columns = array_keys($row);
    if ($html) {
        $msg .= "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"5\">";
        $msg .= "<tr bgcolor=$BGCOLOR>";
        foreach ($columns as $column) {
            $header = ucfirst($column);
            $msg .= "<td><b>$header</b></td>";
        }
        $msg .= "</tr>";
        print "<tr>\n";
        foreach ($row as $key => $value) {
            if (!isset($value) || !strlen($value)) {
                $value = "N/A";
            }
    	    $msg .= "<td>$value</td>";
        }
        $msg .= "</tr>";
        $msg .= "</table>";
        $msg .= "</body></html>";
    } else {
        $msg .= "--------------------------------------------------------------------------------\n";
        foreach ($columns as $column) {
            $header = ucfirst($column);
            $msg .= "|$header";
        }
        $msg .= $NL;
        $msg .= "--------------------------------------------------------------------------------\n";
        foreach ($row as $key => $value) {
            if (!isset($value) || !strlen($value)) {
                $value = "N/A";
            }
            $msg .= "|$value";
        }
        $msg .= "\n";
        $msg .= "--------------------------------------------------------------------------------\n";
    }
    return $msg;
}

include_once 'database.php';
include_once 'sharedData.php';
require_once "deencrypt.php" ;
require_once 'utils.php';
require_once "smtpPhpMailer.php";

// main
global $PRODUCT;
if (isset($argv) && count($argv)) {
    $database = $argv[1];
    $username = $argv[2];
    $password = $argv[3];
    $aetitle = $argv[4];
    $jobid = $argv[5];
    $hostname = getDatabaseHost($aetitle);
    $dbcon = new MyDatabase($hostname, $database, $username, $password, $aetitle);
} else {
    $dbcon = new MyConnection();
    $aetitle = $_SESSION['aetitle'];
    $jobid = $_GET['jobid'];
}
$result = $dbcon->query("SELECT * from smtp");
// only support one SMTP server for now
if (!$result || ($result->rowCount() == 0) ||
    !($row = $result->fetch(PDO::FETCH_ASSOC)))
    die ("<h3><font color=red>No SMTP Server is configured.</font></h3>");
$bindList = array($jobid);
$result = $dbcon->preparedStmt("SELECT username from dbjob where id=?", $bindList);
if (!$result || ($result->rowCount() == 0) ||
    !($dbjob = $result->fetch(PDO::FETCH_NUM)))
    die ("<h3><font color=red>Job ID <u>$jobid</u> is not found.</font></h3>");

$user = $dbjob[0];
// get the email address of this user
$to = $dbcon->getEmailAddress($user, $aetitle);
if (strlen($to) == 0)
    die ("<h3><font color=red>Email address for user: <u>$user</u> is not found.</font></h3>");

$smtp = new smtpPhpMailer($row);
// send job failure notification
$subject = sprintf(pacsone_gettext("%s - Job ID %d Failed"),
    $PRODUCT, $jobid);
$smtp->Subject = $subject;
// add plain text message
$message = emailJob(0, $subject, $dbcon, $jobid);
$smtp->AltBody = $message;
// add HTML message
$message = emailJob(1, $subject, $dbcon, $jobid);
$smtp->Body = $message;
// split multiple email addresses
$tos = preg_split("/[\s,;]+/", $to);
foreach ($tos as $entry)
    $smtp->addAddress($entry);
if($smtp->send())
    echo "Message sent to $to.\n";
else
    echo "Cound not send the message to $to.\nError: " . $smtp->ErrorInfo . "\n"
?>
