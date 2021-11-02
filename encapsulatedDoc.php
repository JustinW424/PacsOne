<?php
//
// encapsulatedDoc.php
//
// Module for sending encapsulated documents (e.g., PDF) to the browser
//
// CopyRight (c) 2008 RainbowFish Software
//
session_start();
include_once 'security.php';
require_once 'constants.php';

$path = urldecode($_GET['path']);
$mimetype = urldecode($_GET['mimetype']);
global $ENCAPSULATED_DOC_ICON_TBL;
if (!isset($ENCAPSULATED_DOC_ICON_TBL[ strtoupper($mimetype) ])) {
    print "<h2><font color=red>";
    printf(pacsone_gettext("Unknown MIMETYPE: %s"), $mimetype);
    print "</font></h2>";
    exit();
}
$type = "Content-type: $mimetype";
header($type);
$fp = fopen($path, "rb");
fpassthru($fp);
fclose($fp);

?>
