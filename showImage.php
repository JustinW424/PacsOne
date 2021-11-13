<?php
//
// showImage.php
//
// Module for displaying full-size converted images
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
// Change Log
// 2005-09-23 J Crick   modified 2 lines and added 4 to enable on the fly size changes
//                      when viewing a jpeg - see inline comments
session_start();
set_time_limit(0);

function getPreviousTaggedImage(&$dbcon, &$seriesUid, $instance)
{
    $uid = "";
    // first query within this series
    $query = "select uuid from image where seriesuid='$seriesUid' and tagged=1 and instance < $instance";
    $result = $dbcon->query($query);
    if ($result && $result->rowCount()) {
        return $result->fetchColumn();
    }
    // query series with lower series number
    $result = $dbcon->query("select studyuid,seriesnumber from series where uuid='$seriesUid'");
    if ($result && $result->rowCount()) {
        $row = $result->fetch(PDO::FETCH_NUM);
        $studyUid = $row[0];
        $seriesNum = $row[1];
        $query = "select uuid from series where studyuid='$studyUid' and seriesnumber < $seriesNum";
        $seriesResult = $dbcon->query($query);
        while ($otherUid = $seriesResult->fetchColumn()) {
            $result = $dbcon->query("select uuid from image where tagged=1 and seriesuid='$otherUid'");
            if ($result && $result->rowCount()) {
                $uid = $result->fetchColumn();
                break;
            }
        }
    }
    return $uid;
}

function getNextTaggedImage(&$dbcon, &$seriesUid, $instance)
{
    $uid = "";
    // first query within this series
    $query = "select uuid from image where seriesuid='$seriesUid' and tagged=1 and instance > $instance";
    $result = $dbcon->query($query);
    if ($result && $result->rowCount()) {
        return $result->fetchColumn();
    }
    // query series with higher series number
    $result = $dbcon->query("select studyuid,seriesnumber from series where uuid='$seriesUid'");
    if ($result && $result->rowCount()) {
        $row = $result->fetch(PDO::FETCH_NUM);
        $studyUid = $row[0];
        $seriesNum = $row[1];
        $query = "select uuid from series where studyuid='$studyUid' and seriesnumber > $seriesNum";
        $seriesResult = $dbcon->query($query);
        while ($otherUid = $seriesResult->fetchColumn()) {
            $result = $dbcon->query("select uuid from image where tagged=1 and seriesuid='$otherUid'");
            if ($result && $result->rowCount()) {
                $uid = $result->fetchColumn();
                break;
            }
        }
    }
    return $uid;
}

require_once 'locale.php';
include_once 'security.php';
include_once 'sharedData.php';
include_once 'display.php';

global $PRODUCT;
print "<html>";
print "<head><title>$PRODUCT - ";
print pacsone_gettext("Image Display");
print "</title></head>";
print "<body>";
require_once 'header.php';

$tagged = 0;
if (isset($_REQUEST['tagged']))
    $tagged = $_REQUEST['tagged'];
$id = $_REQUEST['id'];
$id = urlClean($id, 64);
if (!isUidValid($id)) {
    $error = pacsone_gettext("Invalid SOP Instance UID");
    print "<h2><font color=red>$error</font></h2>";
    exit();
}
$dbcon = new MyConnection();
$username = $dbcon->username;
$query = "SELECT path,seriesuid,instance FROM image where uuid=?";
$bindList = array($id);
$result = $dbcon->preparedStmt($query, $bindList);
$row = $result->fetch(PDO::FETCH_NUM);
$path = $row[0];
$seriesuid = $row[1];
$instance = $row[2];
if ($tagged) {
    // query the previous and next tagged image number
    $uid = getPreviousTaggedImage($dbcon, $seriesuid, $instance);
    if (strlen($uid))
        $previous = $uid;
    $uid = getNextTaggedImage($dbcon, $seriesuid, $instance);
    if (strlen($uid))
        $next = $uid;
} else {
    $images = array();
    $count = 0;
    $match = 0;
    // query the previous and next instance number
    $query = "SELECT uuid,instance FROM image where seriesuid='$seriesuid' ORDER BY instance ASC";
    $result = $dbcon->query($query);
    $rows = $result->rowCount();
    while ($row = $result->fetch(PDO::FETCH_NUM)) {
        $images[] = $row[0];
        if ($instance == $row[1])
            $match = $count;
        $count++;
    }
    if ($count > 1) {
        if ($match == 0) {
            $next = $images[$match+1];
        } else if ($match == $count - 1) {
            $previous = $images[$match-1];
        } else {
            $previous = $images[$match-1];
            $next = $images[$match+1];
        }
    }
}
// query Series Number, Study ID and Patient Name
$result = $dbcon->query("SELECT seriesnumber FROM series WHERE uuid='$seriesuid'");
$seriesNum = $result->fetchColumn();
$query = "SELECT studyuid FROM series WHERE uuid='$seriesuid'";
$result = $dbcon->query($query);
$studyuid = $result->fetchColumn();
$result = $dbcon->query("SELECT id,patientid FROM study WHERE uuid='$studyuid'");
$row = $result->fetch(PDO::FETCH_NUM);
$studyId = strlen($row[0])? $row[0] : $studyuid;
$patientId = $row[1];
$patientName = $dbcon->getPatientName($patientId);
if (!$tagged) {
    // query the previous and next Series number
    $series = array();
    $match = 0;
    $count = 0;
    $query = "SELECT uuid,seriesnumber FROM series where studyuid='$studyuid' and modality!='SR' ORDER BY seriesnumber ASC";
    $result = $dbcon->query($query);
    $rows = $result->rowCount();
    while ($row = $result->fetch(PDO::FETCH_NUM)) {
        $series[] = $row[0];
        if ($seriesNum == $row[1])
            $match = $count;
        $count++;
    }
    if ($count > 1) {
        if ($match == 0) {
            $nextSerie = $series[$match+1];
        } else if ($match == $count - 1) {
            $previousSerie = $series[$match-1];
        } else {
            $previousSerie = $series[$match-1];
            $nextSerie = $series[$match+1];
        }
    }
}    
// save information for display in image processing window
$_SESSION['patientId'] = $patientId;
$_SESSION['patientName'] = $patientName;
$_SESSION['studyUid'] = $studyuid;
$_SESSION['studyId'] = $studyId;
$_SESSION['seriesUid'] = $seriesuid;
$_SESSION['seriesNum'] = $seriesNum;
$_SESSION['instance'] = $instance;
// get the images directory if configured
$imagedir = "";
$result = $dbcon->query("select imagedir from config");
if ($result && $result->rowCount()) {
    $dir = $result->fetchColumn();
    if (strlen($dir) && file_exists($dir))
        $imagedir = $dir;
}
$file = strlen($imagedir)? $imagedir : getcwd();
$file = strtr($file, "\\", "/");
// append '/' at the end if not so already
if (strcmp(substr($file, strlen($file)-1, 1), "/"))
	$file .= "/";
$file .= "images/";
// create the 'images' sub-directory if it doesn't exist
if (!is_dir($file))
	mkdir($file);
$nocache = $file;
$jpg = $file . "$id.jpg";
$gif = $file . "$id.gif";
if (file_exists($jpg)) {
    $file = $jpg;
    $nocache .= "temp." . $id . ".jpg";
} else if (file_exists($gif)) {
    $file = $gif;
    $nocache .= "temp." . $id . ".gif";
} else {
    // convert the image
    $handle = imagick_readimage($path);
    if (imagick_iserror($handle)) {
        $reason      = imagick_failedreason($handle);
        $description = imagick_faileddescription($handle);
        print pacsone_gettext("imagick_readimage() failed!");
        print "<BR>\n";
        printf(pacsone_gettext("Reason: %s<BR>\nDescription: %s<BR>\n"), $reason, $description);
        exit();
    }
    // convert to GIF format for multi-frame images
    if (imagick_getlistsize($handle) > 1)
    {
        $file .= $id . ".gif";
        $nocache .= "temp." . $id . ".gif";
    }
    else
    {
        $file .= $id . ".jpg";
        $nocache .= "temp." . $id . ".jpg";
    }
    if (!imagick_writeimage($handle, $file))
    {
        $reason      = imagick_failedreason($handle) ;
        $description = imagick_faileddescription( $handle ) ;
        print pacsone_gettext("failed to convert image to JPG");
        print "<BR>\n";
        printf(pacsone_gettext("Reason: %s<BR>\nDescription: %s<BR>\n"), $reason, $description);
	    exit();
    }
    imagick_destroyhandle($handle);
}
$jpg = imagick_readimage($file);
if (imagick_iserror($jpg))
{
    $reason      = imagick_failedreason($jpg) ;
    $description = imagick_faileddescription($jpg) ;
    print pacsone_gettext("failed to read converted JPG file");
    print "<BR>\n";
    printf(pacsone_gettext("Reason: %s<BR>\nDescription: %s<BR>\n"), $reason, $description);
	exit();
}
$width = imagick_getwidth($jpg);
$height = imagick_getheight($jpg);
// another copy for online image processing
if (!file_exists($nocache))
    copy($file, $nocache);
imagick_destroyhandle($jpg);
// display the converted image
global $BGCOLOR;
// display Image Notes
print "<table width=100% border=0 cellspacing=5 cellpadding=0>\n";
print "<tr valign=top>";
print "<td class=notes>";
displayImageNotes($id);
print "</td>";
print "<td width=1 bgcolor=$BGCOLOR><img src=blank.gif width=1 height=1></td>";
print "<td>";
print "<p>";
global $CUSTOMIZE_PATIENT;
printf(pacsone_gettext("%s: <a href='study.php?patientId=%s'>%s</a> Study: <a href='series.php?patientId=%s&studyId=%s'>%s</a> Series Number: <a href='image.php?patientId=%s&studyId=%s&seriesId=%s'>%d</a> Instance Number: %d"), $CUSTOMIZE_PATIENT, urlencode($patientId), $patientName, urlencode($patientId), $studyuid, urlencode($studyId), urlencode($patientId), $studyuid, $seriesuid, $seriesNum, $instance);
print "<p>\n";
// different size options
$sizes = array(
    25      => pacsone_gettext("75% Smaller"),
    50      => pacsone_gettext("50% Smaller"),
    75      => pacsone_gettext("25% Smaller"),
    100     => pacsone_gettext("Original Size"),
    125     => pacsone_gettext("25% Larger"),
    150     => pacsone_gettext("50% Larger"),
    175     => pacsone_gettext("75% Larger"),
);
foreach ($sizes as $key => $value) {
    $x = $width * $key / 100;
    $y = $height * $key / 100;
    print "<SPAN onMouseOver=document.jpegimage.height=$y,document.jpegimage.width=$x><U>$value </U>&nbsp;</SPAN>\n";
}    
print "<p>";
print "<table width=100% border=0 cellspacing=0 cellpadding=0>\n";
print "<tr><td>\n";
print "<table width=100% border=0 cellspacing=0 cellpadding=2>\n";
print "<tr>";
print "<td align='left' valign='bottom'>";
if (isset($previous)) {
    if ($tagged) {
        print "<a href='showImage.php?id=$previous&tagged=1'>";
        print pacsone_gettext("Previous Tagged Image") . "</a> &nbsp;";
    } else {
        print "<a href='showImage.php?id=$previous'>";
        print pacsone_gettext("Previous") . "</a> &nbsp;";
    }
}
if (isset($next)) {
    if ($tagged) {
        print "<a href='showImage.php?id=$next&tagged=1'>";
        print pacsone_gettext("Next Tagged Image") . "</a>";
    } else {
        print "<a href='showImage.php?id=$next'>";
        print pacsone_gettext("Next") . "</a>";
    }
}
print "</td>";
// display links to next/previous Series
print "<td align='right' valign='bottom'>";
if (!$tagged) {
    if (isset($previousSerie)) {
        $firstUid = $dbcon->getFirstImage($previousSerie);
        if ($firstUid) {
            print "<a href='showImage.php?id=$firstUid'>";
            print pacsone_gettext("Previous Series") . "</a> &nbsp;";
        }
    }
    if (isset($nextSerie)) {
        $firstUid = $dbcon->getFirstImage($nextSerie);
        if ($firstUid) {
            print "<a href='showImage.php?id=$firstUid'>";
            print pacsone_gettext("Next Series") . "</a>";
        }
    }
}
print "</td>";
print "</tr></table>\n";
print "</td></tr>\n";

$basename = basename($file);
print "<tr><td>\n";
// 2005-09-23 next statement added a height and name parameter
$imgsrc = strlen($imagedir)? ("tempimage.php?path=" . urlencode($file) . "&purge=0") : "images/$basename";
print "<P><IMG SRC='$imgsrc' BORDER='0' ALIGN='middle' ALT='$id' NAME='jpegimage'><P>\n";
print "</td></tr>\n";

print "<tr><td>\n";
print "<table width=100% border=0 cellspacing=0 cellpadding=2>\n";
print "<tr>";
print "<td align='left' valign='bottom'>";
if (isset($previous)) {
    if ($tagged) {
        print "<a href='showImage.php?id=$previous&tagged=1'>";
        print pacsone_gettext("Previous Tagged Image") . "</a> &nbsp;";
    } else {
        print "<a href='showImage.php?id=$previous'>";
        print pacsone_gettext("Previous");
        print "</a> &nbsp;";
    }
}
if (isset($next)) {
    if ($tagged) {
        print "<a href='showImage.php?id=$next&tagged=1'>";
        print pacsone_gettext("Next Tagged Image") . "</a>";
    } else {
        print "<a href='showImage.php?id=$next'>";
        print pacsone_gettext("Next") . "</a>";
    }
}
print "</td>";
// display links to next/previous Series
print "<td align='right' valign='bottom'>";
if (!$tagged) {
    if (isset($previousSerie)) {
        $firstUid = $dbcon->getFirstImage($previousSerie);
        if ($firstUid) {
            print "<a href='showImage.php?id=$firstUid'>";
            print pacsone_gettext("Previous Series") . "</a> &nbsp;";
        }
    }
    if (isset($nextSerie)) {
        $firstUid = $dbcon->getFirstImage($nextSerie);
        if ($firstUid) {
            print "<a href='showImage.php?id=$firstUid'>";
            print pacsone_gettext("Next Series") . "</a>";
        }
    }
}
print "</td>";
print "</tr></table>\n";
print "</td></tr>\n";

print "</table>\n";
print "<form method=POST action='manipulate.php'>\n";
$nocache = urlencode($nocache);
print "<input type=hidden value=$nocache name='path'>\n";
print "<input class='btn btn-primary' type=submit value='";
print pacsone_gettext("Online Image Processing");
print "' title='";
print pacsone_gettext("Online Image Processing");
print "'>\n";
print "</form>\n";
// email converted JPG/GIF image to user's email address
$result = $dbcon->query("select * from smtp");
if ($result && $result->rowCount() && ($email = $dbcon->getEmailAddress($username))) {
    print "<form method='POST' action='emailjpg.php'>\n";
    print "<input type=hidden name='cc' value='$email'>";
    print "<input type=hidden name='imagefile' value='$file'>";
    print "<input type=hidden name='instance' value='$instance'>";
    print "<input type=hidden name='seriesnum' value='$seriesNum'>";
    print "<input type=hidden name='studyuid' value='$studyuid'>";
    print "<input type=hidden name='studyid' value='$studyId'>";
    print "<input type=hidden name='patientid' value='$patientId'>";
    print "<input type=hidden name='patientname' value='$patientName'>";
    print "<input class='btn btn-primary' type=submit value='";
    printf(pacsone_gettext("Email %s Image"), strtoupper(substr($file, -3)));
    print "'>&nbsp;";
    print pacsone_gettext("To:");
    print "<input type='text' name='recipients' size=64 maxlength=255>";
    print "&nbsp;" . pacsone_gettext("(Separate multiple email addresses with coma ',')");
    print "</form>\n";
}
print "</td></tr>";
print "</table>";
require_once 'footer.php';
print "</body>";
print "</html>";

?>
