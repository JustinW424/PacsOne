<?php
//
// manipulate.php
//
// Module for manipulating images
//
// CopyRight (c) 2003-2013 RainbowFish Software
//
session_start();

require_once "locale.php";
include_once 'security.php';
include_once 'sharedData.php';

global $PRODUCT;
global $CUSTOMIZE_PATIENT;
print "<html>";
print "<head><title>$PRODUCT - " . pacsone_gettext("Image Processing") . "</title></head>";
print "<body>";

require_once 'header.php';

// display patient information
$patientId = $_SESSION['patientId'];
$patientName = $_SESSION['patientName'];
print "<p>$CUSTOMIZE_PATIENT:<a href='study.php?patientId=$patientId'>$patientName</a> ";
$studyUid = $_SESSION['studyUid'];
$studyId = $_SESSION['studyId'];
print pacsone_gettext("Study:") . " <a href='series.php?patientId=$patientId&studyId=$studyUid'>$studyId</a> ";
$seriesUid = $_SESSION['seriesUid'];
$seriesNum = $_SESSION['seriesNum'];
print pacsone_gettext("Series Number:") . " <a href='image.php?patientId=$patientId&studyId=$studyUid&seriesId=$seriesUid'>$seriesNum</a> ";
$instance = $_SESSION['instance'];
printf(pacsone_gettext("Instance Number: %d<p>\n"), $instance);
// gather image information
if (isset($_POST['path']))
	$path = urldecode($_POST['path']);
else
    $path = $_SESSION['imagePath'];
$handle = imagick_readimage($path);
if (imagick_iserror($handle))
{
    $reason = imagick_failedreason($handle);
    $description = imagick_faileddescription($handle);
    print pacsone_gettext("handle failed!");
    printf(pacsone_gettext("<BR>\nReason: %s<BR>\nDescription: %s<BR>\n"), $reason, $description);
    exit();
}
$width = imagick_getwidth($handle);
$height = imagick_getheight($handle);
imagick_destroyhandle($handle);
// image processing menu
print "<p><table width=100% border=0 cellspacing=1 cellpadding=0>\n";
$file = basename($path);
$path = urlencode($path);
// display the converted image
print "<tr><td>\n";
print "<P><IMG SRC='nocache.php?path=$path' BORDER='0' ALIGN='left' ALT='";
print pacsone_gettext("Processed Image");
print "'>\n";
print "</td>\n";
// display original image as reference
$file = substr($file, 5);
$src = "images/$file";
$dir = dirname($_SERVER["SCRIPT_FILENAME"]);
if (!file_exists("$dir/$file")) {
    $dir = dirname(urldecode($path));
    $dir .= "/$file";
    $src = "tempimage.php?path=" . urlencode($dir);
}
print "<td><IMG SRC='$src' BORDER=0 ALIGN=left ALT='";
print pacsone_gettext("Original Image");
print "'>\n";
print "</td></tr>\n";
print "<tr><td colspan=2></td></tr><p>\n";
// image processing table
print "<tr><td colspan=2><table width=100% border=1 cellspacing=0 cellpadding=0>\n";
// window/level control
print "<tr><form method='POST' action='windowLevel.php'>\n";
print "<input type=hidden name='path' value=$path>\n";
print "<td><b>";
print pacsone_gettext("Window/Level");
print "</b></td>\n";
print "<td>\n";
print pacsone_gettext("Image pixel grayscale values range from 0 (darkest) to 100 (brightest)<br>");
print "Window size: ";  // window control
$value = 100;
if (isset($_SESSION['window']))
	$value = $_SESSION['window'];
print "<input type=text maxlength=3 name='window' value=$value>";
print pacsone_gettext("from 1 to 100") . "<br>\n";
print pacsone_gettext("Level: ");        // level control
$value = 50;
if (isset($_SESSION['level']))
	$value = $_SESSION['level'];
print "<input type=text maxlength=3 name='level' value=$value>";
print pacsone_gettext("from 0 to 100");
print "<br>\n";
print "</td>\n";
print "<td align=center>\n";
print "<input type=submit value='";
print pacsone_gettext("Window/Level");
print "' title='";
print pacsone_gettext("Display Processed Image") . "'>\n";
print "</td>\n";
print "</form></tr>\n";
// gamma control
print "<tr><form method='POST' action='gamma.php'>\n";
print "<input type=hidden name='path' value=$path>\n";
print "<td><b>" . pacsone_gettext("Gamma Correction") . "</b></td>\n";
print "<td>" . pacsone_gettext("Gamma correction value: ");
print "<input type=text maxlength=4 name='gamma' value='1.5'> ";
print pacsone_gettext("typical range from 0.8 to 2.3");
print "</input><br>\n";
print "</td>\n";
print "<td align=center>\n";
print "<input type=submit value='Gamma' title='";
print pacsone_gettext("Gamma Correction") . "'>\n";
print "</input></td>\n";
print "</form></tr>\n";
// image contrast
print "<tr><form method='POST' action='contrast.php'>\n";
print "<input type=hidden name='path' value=$path>\n";
print "<td><b>" . pacsone_gettext("Contrast") . "</b></td>\n";
print "<td>\n";
print "<input type=radio name='contrast' value='1' checked>";
print pacsone_gettext("Increase");
print "</input><br>\n";
print "<input type=radio name='contrast' value='0'>";
print pacsone_gettext("Decrease") . "</input>\n";
print "</td>\n";
print "<td align=center>\n";
print "<input type=submit value='";
print pacsone_gettext("Contrast");
print "' title='";
print pacsone_gettext("Image Contrast") . "'>\n";
print "</input></td>\n";
print "</form></tr>\n";
// edge enhancement
print "<tr><form method='POST' action='edge.php'>\n";
print "<input type=hidden name='path' value=$path>\n";
print "<td><b>" . pacsone_gettext("Edge Enhancement") . "</b></td>\n";
print "<td>" . pacsone_gettext("Select radius from 1 to 10 pixels: ");
print "<input type=text maxlength=2 name='radius' value='2'></input>";
print "</td>\n";
print "<td align=center>\n";
print "<input type=submit value='";
print pacsone_gettext("Enhance");
print "' title='";
print pacsone_gettext("Edge Enhancement") . "'>\n";
print "</td>\n";
print "</form></tr>\n";
// resize image
print "<tr><form method='POST' action='resize.php'>\n";
print "<input type=hidden name='path' value=$path>\n";
print "<td><b>" . pacsone_gettext("Resize Image") . "</b></td>\n";
print "<td>\n";
print "<input type=radio name='zoom' value='0' checked>\n";
print pacsone_gettext("Width:") . " <input type=text maxlength=3 name='width' value='$width'></input>\n";
print pacsone_gettext("Height:") . " <input type=text maxlength=3 name='height' value='$height'></input><br>\n";
print "</input>\n";
print "<input type=radio name='zoom' value='2'>";
print pacsone_gettext("2x Zoom") . "</input>\n";
print "</td>\n";
print "<td align=center>\n";
print "<input type=submit value='";
print pacsone_gettext("Resize");
print "' title='";
print pacsone_gettext("Resize Image") . "'>\n";
print "</td>\n";
print "</form></tr>\n";
// image enhancement
print "<tr><form method='POST' action='enhance.php'>\n";
print "<input type=hidden name='path' value=$path>\n";
print "<td><b>" . pacsone_gettext("Enhance Image") . "</b></td>\n";
print "<td>";
print pacsone_gettext("Apply digital filtering to improve the quality of a noisy image");
print "</td>\n";
print "<td align=center>\n";
print "<input type=submit value='";
print pacsone_gettext("Image Enhancement");
print "' title='";
print pacsone_gettext("Enhance Image") . "'>\n";
print "</input></td>\n";
print "</form></tr>\n";
// histogram equalization
print "<tr><form method='POST' action='equalize.php'>\n";
print "<input type=hidden name='path' value=$path>\n";
print "<td><b>" . pacsone_gettext("Equalize Image") . "</b></td>\n";
print "<td>" . pacsone_gettext("Apply histogram equalization to the image") . "</td>\n";
print "<td align=center>\n";
print "<input type=submit value='";
print pacsone_gettext("Equalize");
print "' title='";
print pacsone_gettext("Equalize Image") . "'>\n";
print "</input></td>\n";
print "</form></tr>\n";
// negate image
print "<tr><form method='POST' action='negate.php'>\n";
print "<input type=hidden name='path' value=$path>\n";
print "<td><b>" . pacsone_gettext("Invert Image") . "</b></td>\n";
print "<td>\n";
print "<input type=radio name='negate' value='1' checked>";
print pacsone_gettext("Invert only grayscale values within the image");
print "</input><br>\n";
print "<input type=radio name='negate' value='0'>";
print pacsone_gettext("Invert all grayscale values") . "</input>\n";
print "</td>\n";
print "<td align=center>\n";
print "<input type=submit value='";
print pacsone_gettext("Invert");
print "' title='";
print pacsone_gettext("Invert Image") . "'>\n";
print "</input></td>\n";
print "</form></tr>\n";
// normalize image
print "<tr><form method='POST' action='normalize.php'>\n";
print "<input type=hidden name='path' value=$path>\n";
print "<td><b>" . pacsone_gettext("Normalize Image") . "</b></td>\n";
print "<td>";
print pacsone_gettext("Enhance contrast of images by normalizing pixel grayscale values");
print "</td>\n";
print "<td align=center>\n";
print "<input type=submit value='";
print pacsone_gettext("Normalize");
print "' title='";
print pacsone_gettext("Normalize Image") . "'>\n";
print "</input></td>\n";
print "</form></tr>\n";
// image rotation
print "<tr><form method='POST' action='rotate.php'>\n";
print "<input type=hidden name='path' value=$path>\n";
print "<td><b>" . pacsone_gettext("Rotate Image") . "</b></td>\n";
print "<td>\n";
print pacsone_gettext("Rotate ");
print "<input type=text maxlength=3 name='degree' value='90'> ";
print pacsone_gettext("degrees") . "</input><br>\n";
print "<input type=radio name='direction' value='1' checked>";
print pacsone_gettext("Clockwise") . "<br>\n";
print "<input type=radio name='direction' value='-1'>";
print pacsone_gettext("Counter-clockwise") . "<br>\n";
print "</td>\n";
print "<td align=center>\n";
print "<input type=submit value='";
print pacsone_gettext("Rotate");
print "' title='";
print pacsone_gettext("Rotate Image") . "'>\n";
print "</input></td>\n";
print "</form></tr>\n";
// go back to the original image
print "<tr><form method='POST' action='originalImage.php'>\n";
print "<input type=hidden name='path' value=$path>\n";
print "<td><b>" . pacsone_gettext("Original Image") . "</b></td>\n";
print "<td>" . pacsone_gettext("Display the stored original image") . "</td>\n";
print "<td align=center>\n";
print "<input type=submit value='";
print pacsone_gettext("Original Image");
print "' title='";
print pacsone_gettext("Go Back To Original Image") . "'>\n";
print "</input></td>\n";
print "</form></tr>\n";
// end of image processing table
print "</table></td></tr>\n";
print "</table>\n";

require_once 'footer.php';
print "</body>";
print "</html>";

?>
