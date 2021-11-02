<?php
//
// notifynewstudy.php
//
// Module for sending email notifications for newly received study
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
if (!isset($argv))
    session_start();

require "locale.php";

function notifyNewStudy($html, &$subject, &$dbcon, $uid)
{
    global $BGCOLOR;
    global $CUSTOMIZE_PATIENT_ID;
    global $CUSTOMIZE_PATIENT_NAME;
    $columns = array(
        $CUSTOMIZE_PATIENT_ID                   => "patientid",
        $CUSTOMIZE_PATIENT_NAME                 => "",
        pacsone_gettext("Study ID")             => "id",
        pacsone_gettext("Accession Number")     => "accessionnum",
        pacsone_gettext("Study Description")    => "description",
    );
    $NL = ($html)? "<br>" : "\n";
    if ($html) {
        $msg = "<html>";
        $msg .= "<head><title>$subject</title></head>";
        $msg .= "<body>";
        $msg .= "<p>$subject<p>";
    } else
        $msg = "$subject" . $NL;
    $msg .= $NL . sprintf(pacsone_gettext("Study %s Has Arrived"), $uid) . $NL;
    $bindList = array($uid);
    $result = $dbcon->preparedStmt("select * from study where uuid=?", $bindList);
    if ($html) {
        $msg .= "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"5\">";
        $msg .= "<tr bgcolor=$BGCOLOR>";
        foreach ($columns as $key => $column) {
            $header = ucfirst($key);
            $msg .= "<td><b>$header</b></td>";
        }
        $msg .= "</tr>";
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            print "<tr>\n";
            foreach ($columns as $key => $column) {
                if (!strlen($column))
                    $value = $dbcon->getPatientNameByStudyUid($uid);
                else {
                    $value = isset($row[$column])? $row[$column] : pacsone_gettext("N/A");
                }
                if (!strlen($value))
                    $value = pacsone_gettext("N/A");
    	        $msg .= "<td>$value</td>";
            }
            $msg .= "</tr>";
        }
        $msg .= "</table>";
        $msg .= "</body></html>";
    } else {
        $msg .= "--------------------------------------------------------------------------------\n";
        foreach ($columns as $key => $column) {
            $header = ucfirst($key);
            $msg .= "|$header";
        }
        $msg .= $NL;
        $msg .= "--------------------------------------------------------------------------------\n";
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            foreach ($columns as $key => $column) {
                if (!strlen($column))
                    $value = $dbcon->getPatientNameByStudyUid($uid);
                else {
                    $value = isset($row[$column])? $row[$column] : pacsone_gettext("N/A");
                }
                if (!strlen($value))
                    $value = pacsone_gettext("N/A");
                $msg .= "|$value";
            }
            $msg .= "\n";
        }
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
    $user = $argv[5];
    $studyUid = $argv[6];
    $hostname = getDatabaseHost($aetitle);
    $dbcon = new MyDatabase($hostname, $database, $username, $password, $aetitle);
} else {
    $dbcon = new MyConnection();
    $user = $_GET["user"];
    $studyUid = $_GET["uid"];
}
$result = $dbcon->query("SELECT * from smtp");
// only support one SMTP server for now
if (!$result || ($result->rowCount() == 0) ||
    !($row = $result->fetch(PDO::FETCH_ASSOC)))
    die ("<h3><font color=red>No SMTP Server is configured.</font></h3>");
$to = "";
// get the email address of this user
$bindList = array($user);
$result = $dbcon->preparedStmt("select email from privilege where username=?", $bindList);
if ($result && ($result->rowCount() == 1) &&
    ($email = $result->fetchColumn()) &&
    strlen($email)) {
    $to = $email;
}
if (strlen($to) == 0)
    die ("<h3><font color=red>Email address for user: <u>$user</u> is not found.</font></h3>");

$smtp = new smtpPhpMailer($row);
// send new study notification
$subject = sprintf(pacsone_gettext("%s - Study %s Has Arrived"),
    $PRODUCT, $studyUid);
$smtp->Subject = $subject;
// add plain text message
$smtp->AltBody = notifyNewStudy(0, $subject, $dbcon, $studyUid);
// add HTML message
$smtp->Body = notifyNewStudy(1, $subject, $dbcon, $studyUid);
// split multiple email addresses
$tos = preg_split("/[\s,;]+/", $to);
foreach ($tos as $entry)
    $smtp->addAddress($entry);
if ($smtp->send())
    echo "Message sent to $to.\n";
else
    echo "Cound not send the message to $to.\nError: " . $smtp->ErrorInfo . "\n"
?>
