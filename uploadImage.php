<?php
//
// uploadImage.php
//
// Module for uploading Dicom images
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once 'database.php';
include_once 'upload.php';

// main
global $PRODUCT;
$dbcon = new MyConnection();
$username = $dbcon->username;
if (isset($_POST['action']))
    $action = $_POST['action'];
else if (isset($_POST['actionvalue']))
   	$action = $_POST['actionvalue'];
$result = "";
if (isset($action) && strcasecmp($action, "Unattach") == 0) {
    // remove uploaded file
    if (isset($_SESSION['uploadimages']) && isset($_POST['unattach'])) {
        $unattach = $_POST['unattach'];
        $uploadimages = $_SESSION['uploadimages'];
        $newList = array();
        foreach ($uploadimages as $att) {
            if (in_array($att['name'], $unattach))
                unlink($att['destfile']);
            else
                $newList[] = $att;
        }
        // save modified upload images
        $_SESSION['uploadimages'] = $newList;
    }
    // go back to Upload Image page
    $url = "tools.php?page=" . urlencode( pacsone_gettext("Upload Dicom Image") );
    header("Location: $url");
    exit();
} else if (isset($action) && strcasecmp($action, "Attach") == 0) {
    $uploaded = $_FILES['uploadfile'];
    $origname = $uploaded['name'];
    $error = $uploaded['error'];
    if ($error) {
        print "<h2><font color=red>";
        printf(pacsone_gettext("Error uploading file <b>%s</b>: %s"), $origname, getUploadError($error));
        print "</font></h2>";
        exit();
    }
    // only allow .dcm filename extension
    $parts = pathinfo($uploaded['name']);
    if (!isset($parts['extension']) || strcasecmp($parts['extension'], "DCM")) {
        print "<h2><font color=red>";
        print pacsone_gettext("Only files with Dicom filename extension .DCM can be uploaded!");
        print "</font></h2>";
        exit();
    }
    // get upload directory
    $destdir = "";
    $uploaddir = 1;
    $config = $dbcon->query("select uploaddir from config");
    if ($config && ($row = $config->fetch(PDO::FETCH_NUM)))
        $destdir = $row[0];
    if (strlen($destdir) == 0) {
        $uploaddir = 0;
        $destdir = dirname($_SERVER['SCRIPT_FILENAME']);
    }
    $prefix = date("Ymd-His") . "-$username";
    // change to Unix-style path
    $destdir = str_replace("\\", "/", $destdir);
    // append '/' at the end if not so already
    if (strcmp(substr($destdir, strlen($destdir)-1, 1), "/"))
        $destdir .= "/";
    if ($uploaddir == 0)
        $destdir .= "upload/";
    $destfile = "$destdir$prefix-" . $uploaded['name'];
    if (!move_uploaded_file($uploaded['tmp_name'], $destfile)) {
        print "<h2><font color=red>";
        printf(pacsone_gettext("Upload file: %s failed"), $destfile);
        print "<br>";
        print_r($_FILES);
        print "</font></h2>";
        exit();
    }
    $uploaded['destfile'] = $destfile;
    // save the uploaded file
    if (isset($_SESSION['uploadimages']))
        $uploadimages = $_SESSION['uploadimages'];
    else
        $uploadimages = array();
    $found = 0;
    foreach ($uploadimages as $exist) {
        if (!strcasecmp($exist['name'], $uploaded['name']) &&
            !strcasecmp($exist['type'], $uploaded['type'])) {
            $found = 1;
            break;
        }
    }
    if (!$found)
        $uploadimages[] = $uploaded;
    $_SESSION['uploadimages'] = $uploadimages;
    // go back to Upload Image page
    $url = "tools.php?page=" . urlencode( pacsone_gettext("Upload Dicom Image") );
    header("Location: $url");
    exit();
} else if (isset($action) && strcasecmp($action, "Upload") == 0) {
    // schedule database jobs to Import the uploaded Dicom images
    if (isset($_SESSION['uploadimages'])) {
        $attachments = $_SESSION['uploadimages'];
        $query = "insert into dbjob (username,aetitle,type,uuid,class,submittime,status,details) ";
        $query .= "values(?,'_','Upload',";
        $bindList = array($username);
        $query .= $dbcon->useOracle? "TO_CHAR(SYSDATE,'YYYY-MM-DD HH24:MI:SS')," : "NOW(),";
        $query .= "'Image',";
        $query .= $dbcon->useOracle? "SYSDATE," : "NOW(),";
        $query .= "'submitted',";
        $details = "";
        foreach ($attachments as $att) {
            $destfile = $att['destfile'];
            $size = filesize($destfile);
            if (!file_exists($destfile) || !$size) {
                $result = sprintf(pacsone_gettext("Invalid uploaded Dicom image: %s, size = %d bytes"), $destfile, $size);
                break;
            } else {
                if (strlen($details))
                    $details .= "|";
                $details .= $destfile;
                // log activity to system journal
                $dbcon->logJournal($username, "Upload", "Image", $destfile);
            }
        }
        if (strlen($details)) {
            $query .= "?)";
            $bindList[] = $details;
        } else
            $dbcon = null;
        if (!$dbcon || !$dbcon->preparedStmt($query, $bindList)) {
            $result = sprintf(pacsone_gettext("Failed to schedule database job to Import uploaded image: \"%s\".<p>Database error: %s"), $destfile, $dbcon->getError());
        } else {
            $jobid = $dbcon->insert_id("dbjob");
        }
    } else {
        print "<h2><font color=red>";
        print pacsone_gettext("No upload Dicom image is defined.");
        print "</font></h2>";
        exit();
    }
} else if (!count($_POST) && !count($_FILES)) {
    print "<h2><font color=red>";
    print pacsone_gettext("Check the <b>post_max_size</b> and <b>upload_max_size</b> settings in your PHP.INI configuration file, as you may need to increase those limits to accommodate the large file sizes being uploaded.");
    print "</font></h2>";
    exit();
}
print "<html>\n";
print "<head><title>$PRODUCT - ";
print pacsone_gettext("Upload Dicom Image");
print "</title></head>\n";
print "<body>\n";
require_once 'header.php';
include_once 'checkUncheck.js';

if (empty($result) && isset($jobid)) {   // success
    print "<p>";
    printf(pacsone_gettext("<a href=\"%s\">Database Job %d</a> has been scheduled to Import uploaded images."), "status.php", $jobid);
    print "<p>";
}
else {                  // error
    print "<h3><font color=red>";
    print $result;
    print "</font></h3>";
    if (isset($_SESSION['uploadimages'])) {
        $uploadimages = $_SESSION['uploadimages'];
        foreach ($uploadimages as $att) {
            if (file_exists($att['destfile']))
                unlink($att['destfile']);
        }
    }
}
if (isset($_SESSION['uploadimages']))
    unset($_SESSION['uploadimages']);

require_once 'footer.php';
print "</body>\n";
print "</html>\n";

?>
