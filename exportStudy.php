<?php
//
// exportStudy.php
//
// Module for exporting studies stored in local database
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once "locale.php";
include_once 'security.php';
include_once 'display.php';

global $PRODUCT;
print "<html>";
print "<head><title>$PRODUCT - " . pacsone_gettext("Exporting Studies") . "</title></head>";
print "<body>";
require_once 'header.php';
$dbcon = new MyConnection();

if (isset($_POST["media"])) {
    $media = $_POST["media"];
    $label = $_POST["label"];
	if (!strlen($label)) {
		print "<p><font color=red>";
        print pacsone_gettext("Error: A volume label of upto 16 characters need to be specified for exporting to external media.");
        print "</font></p>";
		print "<p><a href='tools.php?page=" . urlencode(pacsone_gettext("Export")) . "'>" . pacsone_gettext("Back") . "</a></p>";
		exit();
	}
    // check if this label has already been used by a prior export
    $query = "select * from exportedstudy where label=? order by exported desc";
    $bindList = array($label);
    $result = $dbcon->preparedStmt($query, $bindList);
    if ($result && $result->rowCount()) {
        $row = $result->fetch(PDO::FETCH_ASSOC);
        $user = $row["username"];
        $when = $row["exported"];
        $patient = $row["patient"];
        $studyId = $row["id"];
        $studyDate = $row["studydate"];
        print "<p><font color=red>";
        printf(pacsone_gettext("Error: The media label of <u>%s</u> has been used by User: <b>%s</b> on %s for exporting Study \"%s\" of Patient: <b>$patient</b>.<br>"), $label, $user, $when, $studyId);
        print "<br>" . pacsone_gettext("Please select a different media label and try again.") . "</font></p>";
        print "<p><a href='tools.php?page=" . urlencode(pacsone_gettext("Export")) . "'>" . pacsone_gettext("Back") . "</a></p>";
        exit();
    }
    $directory = $_POST["exportdir"];
    if (!strlen($directory) || !file_exists($directory)) {
        print "<br><font color=red>";
        printf(pacsone_gettext("Invalid export directory: <b>%s</b>."), $directory);
        print "</font>";
        exit();
    }
    $_SESSION['ExportDirectory'] = $directory;
    $_SESSION['ExportMedia'] = $media;
    $_SESSION['ExportMediaLabel'] = $label;
} else {
    $directory = $_SESSION['ExportDirectory'];
    $label = $_SESSION['ExportMediaLabel'];
}
if (isset($_POST['option'])) {
    $selected = $_POST['entry'];
} else if (isset($_SESSION['ExportStudies'])) {
    $selected = $_SESSION['ExportStudies'];
} else {
    $selected = array();
    $_SESSION['ExportStudies'] = $selected;
}
// replace Windows-style slashes with Unix-syle
$directory = str_replace("\\", "/", $directory);
// whether to zip exported content into a zip file
$zip = 0;
if (isset($_REQUEST['zip']))
    $zip = $_REQUEST['zip'];
// whether to include external viewer program with export
$viewer = 0;
if (isset($_REQUEST['viewer'])) {
    $viewer = $_REQUEST['viewer'];
    if (isset($_POST['viewerdir'])) {
        $viewerdir = $_POST['viewerdir'];
        // replace Windows-style slashes with Unix-syle
        $viewerdir = str_replace("\\", "/", $viewerdir);
        if (!file_exists($viewerdir)) {
	        print "<p><font color=red>";
            printf(pacsone_gettext("Error: Invalid viewer directory: <b>[%s]</b>"), $viewerdir);
            print "</font>";
	        exit();
        }
        $bindList = array($viewerdir);
        if ($dbcon->isAdministrator($dbcon->username))
            $query = "update config set viewerdir=?";
        else {
            $query = "update privilege set viewerdir=? where username=?";
            $bindList[] = $dbcon->username;
        }
        $dbcon->preparedStmt($query, $bindList);
    }
}
// whether to purge raw images of study after export
$purge = 0;
if (isset($_REQUEST['purge']))
    $purge = $_REQUEST['purge'];
if (isset($_POST['option'])) {
    $level = $_POST['option'];
    $total = $dbcon->getExportSize($level, $selected);
    $mbytes = $total / 1024.0 / 1024.0;
    print "<form method='POST' action='export.php'>\n";
    print "<input type='hidden' name='actionvalue'>\n";
    print "<input type=hidden name='option' value='$level'>";
	foreach ($selected as $uid) {
        print "<input type=hidden name='entry[]' value='$uid'>\n";
	}
	print "<table>\n";
	print "<tr><td>";
    printf(pacsone_gettext("Export the following %s:"), $level);
    print "</td></tr>\n";
	print "<tr><td><br></td></tr>\n";
    print "<tr><td>";
    displayExportItem($level, $selected);
    print "</tr></td>";
    print "<tr><td><br></td></tr>\n";
    print "<tr><td>";
    printf(pacsone_gettext("Total of %f MBytes to local directory: \"<b>%s</b>\" with media label \"<b><u>%s</u></b>\"<br>"), number_format($mbytes, 2, '.', ''), $directory, $label);
    print "</table>\n";
    print "<p><table width=20% border=0 cellpadding=5>\n";
    print "<tr>\n";
    print "<td><input class='btn btn-primary' type=submit value='";
    print pacsone_gettext("Export");
    print "' name='action' title='";
    printf(pacsone_gettext("Export %s"), $level);
    print "' onclick='switchText(this.form,\"actionvalue\",\"Export\")'></td>\n";
    print "<input type=hidden value=$zip name='zip'></input>\n";
    print "<input type=hidden value=$viewer name='viewer'></input>\n";
    print "<input type=hidden value=$purge name='purge'></input>\n";
    print "</tr>\n";
    print "</table>\n";
    print "</form>\n";
} else {
    $sort = "cmp_studyid";
    if (isset($_REQUEST['sort']))
        $sort = $_REQUEST['sort'];
    $url = "exportStudy.php?sort=" . urlencode($sort);
    $offset = 0;
    if (isset($_REQUEST['offset']))
        $offset = $_REQUEST['offset'];
    $all = 0;
    if (isset($_REQUEST['all']))
        $all = $_REQUEST['all'];
    $total = $dbcon->getExportStudySize($selected);
    $mbytes = $total / 1024.0 / 1024.0;
    $preface = sprintf(pacsone_gettext("There are %d studies (%d MBytes) selected for exporting to local directory: \"<b>%s</b>\" with media label \"<b><u>%s</u></b>\"<br>"), count($selected), number_format($mbytes, 2, '.', ''), $directory, $label);
    displayStudiesForExport($selected, $preface, $url, $offset, $all, $zip, $viewer, $purge);
}
// check disk free space for the specified directory
$freespace = disk_free_space($directory);
if ($freespace < $total) {
	print "<p><font color=red>";
    printf(pacsone_gettext("Warning: The specified directory: <b>[%s]</b> has less than %d MBytes disk free space left"), $directory, number_format($mbytes, 2, '.', ''));
    print "</font>";
	exit();
}

require_once 'footer.php';
print "</table>";
print "</body>";
print "</html>";

?>
