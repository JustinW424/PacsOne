<?php
//
// edge.php
//
// Module for edge enhancement
//
// CopyRight (c) 2003-2004 Xiaohui Li
//
session_start();

require_once "locale.php";
#if (!extension_loaded('php_imagick'))
#    dl ('php_imagick.' . PHP_SHLIB_SUFFIX);

$path = urldecode($_POST['path']);
// update the image path of the image being processed
$_SESSION['imagePath'] = $path;
$radius = $_POST['radius'];
if (!isset($radius))
    $radius = 0;
// read the image
$handle = imagick_readimage($path);
if (imagick_iserror($handle))
{
    $reason = imagick_failedreason($handle);
    $description = imagick_faileddescription($handle);
    print pacsone_gettext("handle failed!");
    print "<BR>\n";
    printf(pacsone_gettext("Reason: %s<BR>\nDescription: %s"), $reason, $description);
    print "<BR>\n" ;
    exit();
}
imagick_edge($handle, $radius);
imagick_writeimage($handle, $path);

header('Cache-Control: no-cache');
header('Pragma: no-cache');
header('Location: manipulate.php');

?>

