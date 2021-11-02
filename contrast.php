<?php
//
// contrast.php
//
// Module for increase or decrease image contrast
//
// CopyRight (c) 2003-2010 RainbowFish Software
//
session_start();

require_once 'locale.php';
#if (!extension_loaded('php_imagick'))
#    dl ('php_imagick.' . PHP_SHLIB_SUFFIX);

$path = urldecode($_POST['path']);
// update the image path of the image being processed
$_SESSION['imagePath'] = $path;
$contrast = $_POST['contrast'];
if (!isset($contrast))
    $contrast = 0;
// read the image
$handle = imagick_readimage($path);
if (imagick_iserror($handle))
{
    $reason = imagick_failedreason($handle);
    $description = imagick_faileddescription($handle);
    print "<h3><font color=red>";
    printf(pacsone_gettext("imagick_readimage() failed!<BR>\nReason: %s<BR>\nDescription: %s<BR>\n"), $reason, $description);
    print "</font></h3>";
    exit();
}
imagick_contrast($handle, $contrast);
imagick_writeimage($handle, $path);

header('Cache-Control: no-cache');
header('Pragma: no-cache');
header('Location: manipulate.php');

?>

