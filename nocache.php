<?php
//
// nocache.php
//
// Module for avoid browser displaying cached images
//
// CopyRight (c) 2003-2018 RainbowFish Software
//
if (!session_id())
    die("Unauthorized session!");

$path = urldecode($_GET['path']);
$type = "Content-type: image/";
if (stristr($path, ".jpg")) {
    $type .= "jpg";
} else {
    $type .= "gif";
}
header($type);
$fp = fopen($path, "rb");
fpassthru($fp);
?>

