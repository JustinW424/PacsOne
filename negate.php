<?php
//
// negate.php
//
// Module for negating images
//
// CopyRight (c) 2003-2006 Xiaohui Li
//
session_start();

require_once 'locale.php';
#if (!extension_loaded('php_imagick'))
#    dl ('php_imagick.' . PHP_SHLIB_SUFFIX);

$path = urldecode($_POST['path']);
// update the image path of the image being processed
$_SESSION['imagePath'] = $path;
$negate = $_POST['negate'];
if (!isset($negate))
    $negate = 1;
// read the image
$handle = imagick_readimage($path);
if (imagick_iserror($handle))
{
    $reason = imagick_failedreason($handle);
    $description = imagick_faileddescription($handle);
    print pacsone_gettext("handle failed!");
    print "<BR>\n";
    printf(pacsone_gettext("Reason: %s<BR>\nDescription: %s"), $reason, $description);
    print "<BR>\n";
    exit();
}
imagick_negate($handle, $negate);
imagick_writeimage($handle, $path);

header('Cache-Control: no-cache');
header('Pragma: no-cache');
header('Location: manipulate.php');

?>
