<?php
//
// windowLevel.php
//
// Module for adjusting window/level of displayed images
//
// CopyRight (c) 2003-2004 Xiaohui Li
//
session_start();

require_once 'locale.php';

$path = urldecode($_POST['path']);
$window = $_POST['window'];
$level = $_POST['level'];
// update the image path of the image being processed
$_SESSION['imagePath'] = $path;
// validate the parameters
if ($window < 1 || $window > 100)
    $window = 100;
if ($level < 0 || $level > 100)
{
    $level = 50;
}
// save the last Window/Level values into session variables
$_SESSION['window'] = $window;
$_SESSION['level'] = $level;
// translate window/level values into black/white scale control
$black = $level - $window / 2;
if ($black < 0)
    $black = 0;
$white = $level + $window / 2;
if ($white > 100)
    $white = 100;
$spec = $black . "%," . $white . "%,1.0";
// read the image
$handle = imagick_readimage($path);
if (imagick_iserror($handle))
{
    $reason = imagick_failedreason($handle);
    $description = imagick_faileddescription($handle);
    print pacsone_gettext("handle failed!");
    print "<BR>\n";
    printf(pacsone_gettext("Reason: %s<BR>\nDescription: %s<BR>\n"), $reason, $description);
    exit();
}
imagick_level($handle, $spec);
imagick_writeimage($handle, $path);

header('Cache-Control: no-cache');
header('Pragma: no-cache');
header('Location: manipulate.php');

?>
