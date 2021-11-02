<?php 
//
// downloadZip.php
//
// Module for streaming compressed zip file for client download
//
// CopyRight (c) 2013-2017 RainbowFish Software
//
if (!session_id())
    session_start();

$seq = $_GET['seq'];
if (!isset($_SESSION["downloadFilename-$seq"]) || !isset($_SESSION["downloadPath-$seq"]))
    die("Download sequence [$seq] not found!");
$filename = $_SESSION["downloadFilename-$seq"];
$path = $_SESSION["downloadPath-$seq"];
unset($_SESSION["downloadFilename-$seq"]);
unset($_SESSION["downloadPath-$seq"]);
if (!file_exists($path))
    die("Requested file [$path] does not exist!");
// MSIE handling of Content-Disposition
if (strstr($_SERVER["HTTP_USER_AGENT"], "MSIE")) {
    $contentType = "application/force-download";
    $filename = str_replace(".", "_", $filename);
    $filename .= ".zip";
    $disposition = "Content-disposition: file; filename=\"$filename\"";
} else {
    $contentType = "application/x-zip";
    $filename .= ".zip";
    $disposition = "Content-disposition: attachment; filename=\"$filename\"";
}
// the next three lines force an immediate download of the zip file: 
header("Cache-Control: cache, must-revalidate");   
header("Pragma: public");
header("Content-type: $contentType");    
header($disposition);    
header("Content-length: " . filesize($path));    
/*
 * Must use temporary file here instead
 * 
 * echo $data;
 */
$fp = fopen($path, "rb");
fpassthru($fp);
fclose($fp);
unlink($path);
exit();

?>
