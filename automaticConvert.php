<?php
//
// automaticConvert.php
//
// Module for automatic conversion of received Dicm images into
// thumbnail/full-size JPG/GIF images
//
// CopyRight (c) 2009-2020 RainbowFish Software
//
if (!isset($argv))
    session_start();

include_once 'database.php';
include_once 'sharedData.php';
if (!isset($argv))
    include_once 'header.php';

function getMemoryLimit()
{
    // default PHP memory limit in bytes
    $ret = 128 * 1024 * 1024;
    $limit = ini_get('memory_limit');
    if (strlen($limit)) {
        $val = (int)trim($limit);
        $last = strtolower($limit[strlen($limit)-1]);
        $valid = true;
        switch($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
                break;
            default:
                $valid = false;
                break;
        }
        if ($valid)
            $ret = $val;
    } 
    return $ret;
}

function convertImage($path, $thumbnail, $fullsize)
{
    $src = imagick_readimage($path);
    $err = "";
    if (!imagick_iserror($src)) {
        // write thumbnail image
        if (imagick_getlistsize($src) > 1) {
            $thumbnail .= ".gif";
            $fullsize .= ".gif";
        } else {
            $thumbnail .= ".jpg";
            $fullsize .= ".jpg";
        }
        if (imagick_writeimage($src, $fullsize)) {
            $handle = imagick_readimage($fullsize);
            imagick_scale($handle, 100, 100, "!");
            if (imagick_writeimage($handle, $thumbnail)) {
                imagick_destroyhandle($handle);
            } else {
                $reason = imagick_failedreason($src);
                $descr = imagick_faileddescription($src);
                $err = "Failed to write thumbnail image. Reason: $reason, Description: $descr";
            }
        } else {
            $reason = imagick_failedreason($src);
            $descr = imagick_faileddescription($src);
            $err = "Failed to write full-size image. Reason: $reason, Description: $descr";
        }
        imagick_destroyhandle($src);
    } else {
        $reason = imagick_failedreason($src);
        $descr = imagick_faileddescription($src);
        $err = "Failed to read $path. Reason: $reason, Description: $descr";
    }
    return $err;
}

// main
global $PRODUCT;
global $AUTOCONVERT_MAX_RETRIES;
if (isset($argv) && count($argv)) {
    require_once "utils.php";
    $database = $argv[1];
    $username = $argv[2];
    $password = $argv[3];
    $aetitle = $argv[4];
    $hostname = getDatabaseHost($aetitle);
    $dbcon = new MyDatabase($hostname, $database, $username, $password, $aetitle);
} else {
    $dbcon = new MyConnection();
}
// destination folders for converted JPG/GIF images
$thumbnaildir = "";
$imagedir = "";
$limit = 10;
$defaultdir = strtr(dirname($_SERVER['SCRIPT_FILENAME']), "\\", "/");
// append '/' at the end if not so already
if (strcmp(substr($defaultdir, strlen($defaultdir)-1, 1), "/"))
    $defaultdir .= "/";
$result = $dbcon->query("select thumbnaildir,imagedir,convertlimit from config");
if ($result && ($row = $result->fetch(PDO::FETCH_NUM))) {
    $thumbnaildir = $row[0];
    $imagedir = $row[1];
    $limit = $row[2];
    if (strlen($thumbnaildir)) {
        // append '/' at the end if not so already
        $thumbnaildir = strtr($thumbnaildir, "\\", "/");
        if (strcmp(substr($thumbnaildir, strlen($thumbnaildir)-1, 1), "/"))
            $thumbnaildir .= "/";
    }
    if (strlen($imagedir)) {
        $imagedir = strtr($imagedir, "\\", "/");
        if (strcmp(substr($imagedir, strlen($imagedir)-1, 1), "/"))
            $imagedir .= "/";
    }
}
if (!strlen($thumbnaildir) || !file_exists($thumbnaildir)) {
    $thumbnaildir = $defaultdir;
}
if (!strlen($imagedir) || !file_exists($imagedir)) {
    $imagedir = $defaultdir;
}
$thumbnaildir .= "thumbnails/";
if (!file_exists($thumbnaildir))
    mkdir($thumbnaildir);
$imagedir .= "images/";
if (!file_exists($imagedir))
    mkdir($imagedir);
$count = 0;
print "<p>";
printf(pacsone_gettext("Started Automatic Conversion on %s"), date("r"));
print "<p>";
$result = $dbcon->query("select * from autoconvert limit $limit");
$total = $result->rowCount();
$images = array();
while ($result && ($row = $result->fetch(PDO::FETCH_ASSOC))) {
    $uid = $row["uuid"];
    $path = $row['path'];
    $retries = $row['retries'];
    $images[$uid] = array('success' =>false, 'retries' => $retries);
    if (!file_exists($path)) {
        print "<br>";
        print "<h2><font color=red>";
        printf(pacsone_gettext("Failed to convert image: <u>%s</u>: File does not exist!"), $path);
        print "</font></h2><br>";
        // no need to retry
        $images[$uid]['retries'] = $AUTOCONVERT_MAX_RETRIES;
        continue;
    }
    $limit = getMemoryLimit();
    $fileSize = filesize($path);
    if ($fileSize > $limit) {
        print "<br>";
        print "<h2><font color=red>";
        printf(pacsone_gettext("Image: <u>%s</u> file size of %d bytes exceeds current PHP memory limit of %d bytes"), $path, $fileSize, $limit);
        print "</font></h2><br>";
        // no need to retry
        $images[$uid]['retries'] = $AUTOCONVERT_MAX_RETRIES;
        continue;
    }
    $thumbnail = $thumbnaildir . $uid;
    $fullsize = $imagedir . $uid;
    $err = convertImage($path, $thumbnail, $fullsize);
    if (strlen($err)) {
        print "<br>";
        print "<h2><font color=red>";
        printf(pacsone_gettext("Failed to convert image: <u>%s</u> error = <u>%s</u>"), $path, $err);
        print "</font></h2><br>";
        continue;
    }
    $images[$uid]['success'] = true;
    $count++;
}
foreach ($images as $uid => $entry) {
    if ($entry['success'] ||    // delete this entry from AUTOCONVERT table
        $entry['retries'] >= $AUTOCONVERT_MAX_RETRIES)
        $dbcon->query("delete from autoconvert where uuid='$uid'");
    else    // retry this entry
    {
        $retries = $entry['retries'] + 1;
        $dbcon->query("update autoconvert set retries=$retries where uuid='$uid'");
    }
}
print "<p>";
printf(pacsone_gettext("Finished Automatic Conversion of %d out of %d Dicom Images on %s"), $count, $total, date("r"));
print "<br>";
if (!isset($argv))
    include_once 'footer.php';

?>
