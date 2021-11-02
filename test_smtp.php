<?php
//
// test_smtp.php
//
// Script for email testing with a SMTP server
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once 'database.php';
include_once 'sharedData.php';
require_once "smtp.php" ;
require_once "sasl.php" ;
require_once "deencrypt.php" ;

if (!isset($_REQUEST["to"])) {
    print "<h3><font color=red>";
    print pacsone_gettext("A <b>TO:</b> email address must be specified!");
    print "</font></h3>";
    exit();
}
$to = $_REQUEST["to"];    

// main
global $PRODUCT;
$dbcon = new MyConnection();
$username = $dbcon->username;
if (!$dbcon->hasaccess("admin", $username)) {
    print "<h3><font color=red>";
    print pacsone_gettext("You must have the Admin privilege in order to access this page.");
    prnt "</font></h3>";
    exit();
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
$smtp = new smtp_class;
$decrypt = new DeEncrypt();

$smtp->host_name=$row["hostname"];
$smtp->localhost=isset($_SERVER["SERVER_NAME"])? $_SERVER["SERVER_NAME"] : "localhost";
$smtp->direct_delivery=0;
$smtp->timeout=10;
$smtp->data_timeout=0;
$smtp->debug=1;
$smtp->html_debug=1;
$smtp->pop3_auth_host=isset($row["pop3host"])? $row["pop3host"] : "";
$data = "";
if (isset($row["username"]))
    $data = $decrypt->decrypt($row["username"]);
$smtp->user=$data;
$smtp->realm="";
$data = "";
if (isset($row["password"]))
    $data = $decrypt->decrypt($row["password"]);
$smtp->password=$data;
$smtp->workstation=isset($row["ntlmhost"])? $row["ntlmhost"] : "";
$smtp->authentication_mechanism=isset($row["mechanism"])? $row["mechanism"] : "";

/*
 * If you need to use the direct delivery mode and this is running under
 * Windows or any other platform that does not have enabled the MX
 * resolution function GetMXRR() , you need to include code that emulates
 * that function so the class knows which SMTP server it should connect
 * to deliver the message directly to the recipient SMTP server.
 */
if($smtp->direct_delivery)
{
    if(!function_exists("GetMXRR"))
    {
        /*
        * If possible specify in this array the address of at least on local
        * DNS that may be queried from your network.
        */
        $_NAMESERVERS=array();
        include("getmxrr.php");
    }
    /*
    * If GetMXRR function is available but it is not functional, to use
    * the direct delivery mode, you may use a replacement function.
    */
    /*
    else
    {
        $_NAMESERVERS=array();
        if(count($_NAMESERVERS)==0)
            Unset($_NAMESERVERS);
        include("rrcompat.php");
        $smtp->getmxrr="_getmxrr";
    }
    */
}
$from = $row["myemail"];
if($smtp->SendMessage(
    $from,
    array($to),
    array(
        "From: $from",
        "To: $to",
        "Subject: Test Email From $PRODUCT",
        "Date: ".date(DATE_RFC822))
    ),
    "Hello from $PRODUCT.\n"))
    printf(pacsone_gettext("Message sent to %s.\n"), $to);
else
    printf(pacsone_gettext("Cound not send the message to %s.\nError: %s\n"), $to, $smtp->error);
?>
