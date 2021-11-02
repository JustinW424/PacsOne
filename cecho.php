<?php
//
// cecho.php
//
// Module for verifying DICOM associations as a SCU
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
require_once 'locale.php';
include_once "dicom.php";

$ipaddr = $_REQUEST['ipaddr'];
$hostname = $_REQUEST['hostname'];
$port = $_REQUEST['port'];
$aetitle = $_REQUEST['aetitle'];
$mytitle = $_REQUEST['mytitle'];
$tls = $_REQUEST['tls'];
$error = '';
$assoc = new Association($ipaddr, $hostname, $port, $aetitle, $mytitle, $tls);
if (!$assoc->verify($error)) {
    print '<br><font color=red>';
    printf(pacsone_gettext('Verify() failed: error = %s'), $error);
    print '</font><br>';
    exit();
}
else {
    $message = sprintf(pacsone_gettext("C-ECHO command verified successfully from AE [%s]"), $aetitle);
    print "<script language=\"JavaScript\">\n";
    print "<!--\n";
    print "alert(\"$message\");";
    print "history.go(-1);\n";
    print "//-->\n";
    print "</script>\n";
}

?>
