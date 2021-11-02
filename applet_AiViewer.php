<?php
//
// applet.php
//
// Module for displaying images through a Java applet viewer
//
// CopyRight (c) 2004-2015 RainbowFish Software
//
require_once "utils.php";

function appletExists()
{
	$dir = dirname($_SERVER['SCRIPT_FILENAME']);
	$dir .= "/dicomviewer/Dicom.dic";
	return file_exists($dir);
}

//
// uids - array of SOP instance UIDs to display
//
function appletViewer(&$uids, &$studies)
{
	print "<html>\n";
	print "<body leftmargin=\"0\" topmargin=\"0\" bgcolor=\"#cccccc\">\n";
	require_once 'header.php';
	require_once 'footer.php';
    $url = parseUrlSelfPrefix();
	print "<APPLET ARCHIVE=\"dicomviewer/applet.jar\" CODEBASE=\".\" CODE=\"dicomviewer.Viewer.class\" width=100% height=100% align=middle NAME=\"Viewer.java\">\n";
	print "<PARAM NAME=\"dicURL\" VALUE=\"$url/dicomviewer/Dicom.dic\">\n";
	$count = 0;
	foreach ($uids as $uid) {
		printf ("<PARAM NAME=imgURL%d VALUE=\"$url/viewer.php?uid=%s\">\n", $count, $uid);
		$count++;
	}
    print "<PARAM NAME=NUM VALUE=$count>\n";
	print "</APPLET>\n";
	print "</body>\n";
	print "</html>\n";
}

?>
