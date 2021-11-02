<?php
//
// originalImage.php
//
// Module for display the original un-processed image
//
// CopyRight (c) 2003-2006 Xiaohui Li
//
session_start();

#if (!extension_loaded('php_imagick'))
#    dl ('php_imagick.' . PHP_SHLIB_SUFFIX);

$path = urldecode($_POST['path']);
// update the image path of the image being processed
$_SESSION['imagePath'] = $path;
// copy the original image into the non-cached image being processed
$pos = strrpos($path, "/");
$dir = substr($path, 0, $pos+1);
$file = substr($path, $pos+6);
copy($dir . $file, $path);

header('Cache-Control: no-cache');
header('Pragma: no-cache');
header('Location: manipulate.php');

?>
