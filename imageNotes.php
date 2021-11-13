<?php
//
// imageNotes.php
//
// Module for maintaining the Image Notes Table
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once "locale.php";
include_once 'database.php';
include_once 'upload.php';
include_once 'utils.php';

if (isset($_REQUEST['uid']))
    $uid = $_REQUEST['uid'];
else
    die("<h2><font color=red>" . pacsone_gettext("Unknown SOP Instance UID") . "</font></h2>");
if (!isUidValid($uid)) {
    print "<p><font color=red>";
    printf(pacsone_gettext("Error: Invalid SOP Instance UID: <b>[%s]</b>"), $uid);
    print "</font>";
    exit();
}
// main
global $PRODUCT;
$dbcon = new MyConnection();
$username = $dbcon->username;
$query = "select studyuid from series,image where series.uuid = image.seriesuid and image.uuid=?";
$bindList = array($uid);
$result = $dbcon->preparedStmt($query, $bindList);
if ($result && ($studyUid = $result->fetchColumn())) {
    $result = $dbcon->query("select patientid from study where uuid='$studyUid'");
    if ($result && ($row = $result->fetch(PDO::FETCH_NUM))) {
        $patientId = $row[0];
    }
}
if (!isset($patientId)) {
    global $CUSTOMIZE_PATIENT_ID;
    die("<h2><font color=red>" . sprintf(pacsone_gettext("Unknown %s"), $CUSTOMIZE_PATIENT_ID) . "</font></h2>");
}
if (isset($_POST['action']))
   	$action = $_POST['imagenoteaction'];
else if (isset($_GET['view']) && ($_GET['view'] == 1))
    $action = "View";
else if (isset($_GET['modify']) && ($_GET['modify'] == 1))
    $action = "Modify";
$result = NULL;
if (strcasecmp($action, "View") == 0) {
    viewNotesForm($username, $patientId, $studyUid, $uid);
} else if (isset($action) && strcasecmp($action, "Delete") == 0) {
    $result = deleteEntries($username);
} else if (isset($action) && strcasecmp($action, "Unattach") == 0) {
    // remove attached file
    if (isset($_SESSION['attachments']) && isset($_POST['unattach'])) {
        $unattach = $_POST['unattach'];
        $attachments = $_SESSION['attachments'];
        $newList = array();
        foreach ($attachments as $att) {
            if (in_array($att['name'], $unattach)) {
                if (file_exists($att['destfile']))
                    unlink($att['destfile']);
            } else
                $newList[] = $att;
        }
        // save modified attachments
        $_SESSION['attachments'] = $newList;
    }
    // go back to either Add or Modify page
    if (isset($_POST['modify']))
        modifyEntryForm($username, $patientId, $studyUid, $uid);
    else
        addEntryForm($username, $patientId, $studyUid, $uid);
} else if (isset($action) && strcasecmp($action, "Attach") == 0) {
    $attached = $_FILES['attachfile'];
    $origname = $attached['name'];
    $error = $attached['error'];
    if ($error) {
        print "<font color=red>";
        printf(pacsone_gettext("Error uploading file <b>%s</b>: %s"), $origname, getUploadError($error));
        print "</font>";
        exit();
    }
    // security check
    if (!uploadCheck($origname)) {
        print "<font color=red>";
        printf(pacsone_gettext("Access denied uploading file: <b><u>%s</b>"), $origname);
        print "</u></font>";
        exit();
    }
    // get upload directory
    $destdir = "";
    $uploaddir = 1;
    $config = $dbcon->query("select uploaddir,attachment from config");
    if ($config && ($row = $config->fetch(PDO::FETCH_NUM))) {
        $destdir = $row[0];
        $attachment = $row[1];
    }
    if (strcasecmp($attachment, "table") == 0) {
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
    $destfile = "$destdir$prefix-" . $attached['name'];
    if (!move_uploaded_file($attached['tmp_name'], $destfile)) {
        print "<font color=red>Upload file: $destfile failed<br>";
        print_r($_FILES);
        print "</font>";
        exit;
    }
    $attached['destfile'] = $destfile;
    // save the uploaded file
    if (isset($_SESSION['attachments']))
        $attachments = $_SESSION['attachments'];
    else
        $attachments = array();
    $found = 0;
    foreach ($attachments as $exist) {
        if (!strcasecmp($exist['name'], $attached['name']) &&
            !strcasecmp($exist['type'], $attached['type'])) {
            $found = 1;
            break;
        }
    }
    if (!$found)
        $attachments[] = $attached;
    $_SESSION['attachments'] = $attachments;
    // go back to either Add or Modify page
    if (isset($_POST['modify']))
        modifyEntryForm($username, $patientId, $studyUid, $uid);
    else
        addEntryForm($username, $patientId, $studyUid, $uid);
} else if (isset($action) && strcasecmp($action, "Add") == 0) {
    if (isset($_POST['headline']))
        $result = addEntry($username);
    else
        addEntryForm($username, $patientId, $studyUid, $uid);
}
else if (isset($action) && strcasecmp($action, "Modify") == 0) {
    if (isset($_POST['id']))
        $result = modifyEntry($username, $_POST['id']);
    else
        modifyEntryForm($username, $patientId, $studyUid, $uid);
}
else if (isset($action) && strcasecmp($action, "DeleteAttachment") == 0) {
    $id = $_POST['id'];
    $entry = $_POST['seq'];
    foreach ($entry as $seq) {
        $query = "select path from attachment where uuid=? and id=? and seq=?";
        $bindList = array($uid, $id, $seq);
        $att = $dbcon->preparedStmt($query, $bindList);
        if ($att && ($attfile = $att->fetchColumn())) {
            // remove the uploaded file
            if (file_exists($attfile))
                unlink($attfile);
        }
        // delete from attachment table
        $dbcon->preparedStmt("delete from attachment where uuid=? and id=? and seq=?", $bindList);
    }
    modifyEntryForm($username, $patientId, $studyUid, $uid);
}
else if (isset($action) && (!strcasecmp($action, "Download") || !strcasecmp($action, "Email"))) {
    if (!isset($_POST['entry']))
        $result = pacsone_gettext("No Image Note is selected.");
    else {
        $atts = array();
        $html = buildHtml($patientId, $studyUid, $atts);
        if (strcasecmp($action, "Download")) {
            // check email address
            $to = isset($_POST['emailaddr'])? $_POST['emailaddr'] : "";
            if (strlen($to) == 0) {
                $result = "<h3><font color=red>";
                $result .= pacsone_gettext("You must enter a valid email address for sending the image notes information to.");
                $result .= "</font></h3>";
            } else {
                require_once "emailHtml.php";
                $subject = sprintf(pacsone_gettext("%s - Image Note Information"), $PRODUCT);
                // check if need to attach the converted JPG/GIF image
                $result = emailHtml($to, $subject, $html, $atts);
                if (!strlen($result)) {
                    require_once "header.php";
                    print "<p>";
                    printf(pacsone_gettext("Image Notes sent successfully to <u>%s</u>."), $to);
                    require_once "footer.php";
                }
            }
        } else {
            $result = downloadEntries($html);
        }
    }
}
if (isset($result)) {       // back to the Image Thumbnail page
    if (isset($_SESSION['attachments']))
        unset($_SESSION['attachments']);
    if (empty($result)) {   // success
        $url = "showImage.php";
        $url .= "?id=" . urlencode($uid);
        header("Location: $url");
        exit();
    }
    else {                  // error
        print "<html>\n";
        print "<head><title>$PRODUCT - " . pacsone_gettext("Image Notes Error") . "</title></head>\n";
        print "<body>\n";
        require_once 'header.php';
        print "<h3><font color=red>";
        print $result;
        print "</font></h3>";
        require_once 'footer.php';
        print "</body>\n";
        print "</html>\n";
    }
}

function buildHtml($patientId, $studyUid, &$atts)
{
    if (!isset($_POST['entry']))
        return pacsone_gettext("No Image Note is selected.");
    global $dbcon;
    global $PRODUCT;
    global $BGCOLOR;
    $entry = $_POST['entry'];
    $htm = "<html><head><title>\n";
    $htm .= sprintf(pacsone_gettext("%s - Image Notes"), $PRODUCT);
    $htm .= "</title></head>\n";
    $htm .= "<body>\n";
    $htm .= "<table width='100%' border=0 cellpadding=2 cellspacing=0>\n";
    $uids = array();
    foreach ($entry as $value) {
        $query = "select * from imagenotes where id=?";
        $bindList = array($value);
        $studyNotes = $dbcon->preparedStmt($query, $bindList);
        while ($studyNotes && ($note = $studyNotes->fetch(PDO::FETCH_ASSOC))) {
            $user = $note['username'];
            $when = $note['created'];
            $headline = $note['headline'];
            $details = $note['notes'];
            $uids[] = $note['uuid'];
            // convert line breaks into HTML
            $details = str_replace("\r\n", "<br>", $details);
            $details = str_replace("\n", "<br>", $details);
            $details = str_replace("\r", "<br>", $details);
            $htm .= "<tr><table width='100%' border=1 cellpadding=2 cellspacing=0>\n";
            $htm .= "<tr bgcolor=$BGCOLOR><td colspan=2>";
            $htm .= sprintf(pacsone_gettext("<u>%s</u> by User: <b>%s</b> on %s"), $headline, $user, $when);
            // embed the small logo
            $url = parseUrlSelfPrefix() . "/smallLogo.jpg";
            $htm .= "<embed src=\"$url\" align=\"right\" alt=\"small logo\">";
            $htm .= "</td></tr>";
            $query = "select * from patient inner join study on patient.origid=study.patientid where study.uuid=?";
            $bindList = array($studyUid);
            $study = $dbcon->preparedStmt($query, $bindList);
            if ($study && ($studyRow = $study->fetch(PDO::FETCH_ASSOC))) {
                // patient information
                global $CUSTOMIZE_PATIENT_ID;
                $htm .= "<tr><td>$CUSTOMIZE_PATIENT_ID:";
                $htm .= "</td><td>$patientId</td></tr>";
                global $CUSTOMIZE_PATIENT_NAME;
                $htm .= "<tr><td>$CUSTOMIZE_PATIENT_NAME:";
                $htm .= "</td><td>" . $dbcon->getPatientName($patientId) . "</td></tr>";
                global $CUSTOMIZE_PATIENT_DOB;
                $htm .= "<tr><td>$CUSTOMIZE_PATIENT_DOB:";
                $value = (isset($studyRow['birthdate']) && strlen($studyRow['birthdate']))? $studyRow['birthdate'] : pacsone_gettext("N/A");
                $htm .= "</td><td>$value</td></tr>";
                $htm .= "<tr><td>";
                $htm .= pacsone_gettext("Institution Name:");
                $value = (isset($studyRow['institution']) && strlen($studyRow['institution']))? $studyRow['institution'] : pacsone_gettext("N/A");
                $htm .= "</td><td>$value</td></tr>";
                // study information
                $value = (isset($studyRow['referringphysician']) && strlen($studyRow['referringphysician']))? $studyRow['referringphysician'] : pacsone_gettext("N/A");
                global $CUSTOMIZE_REFERRING_DOC;
                $htm .= "<tr><td>$CUSTOMIZE_REFERRING_DOC:";
                $htm .= "</td><td>$value</td></tr>";
                $value = (isset($studyRow['id']) && strlen($studyRow['id']))? $studyRow['id'] : pacsone_gettext("N/A");
                $htm .= "<tr><td>";
                $htm .= pacsone_gettext("Study ID:");
                $htm .= "</td><td>$value</td></tr>";
                $value = (isset($studyRow['accessionnum']) && strlen($studyRow['accessionnum']))? $studyRow['accessionnum'] : pacsone_gettext("N/A");
                $htm .= "<tr><td>";
                $htm .= pacsone_gettext("Accession Number:");
                $htm .= "</td><td>$value</td></tr>";
                $value = (isset($studyRow['studydate']) && strlen($studyRow['studydate']))? $studyRow['studydate'] : pacsone_gettext("N/A");
                $htm .= "<tr><td>";
                $htm .= pacsone_gettext("Study Date:");
                $htm .= "</td><td>$value</td></tr>";
                $value = (isset($studyRow['description']) && strlen($studyRow['description']))? $studyRow['description'] : pacsone_gettext("N/A");
                $htm .= "<tr><td>";
                $htm .= pacsone_gettext("Study Description:");
                $htm .= "</td><td>$value</td></tr>";
            }
            // detailed notes
            $htm .= "<tr><td>";
            $htm .= pacsone_gettext("Details:");
            $htm .= "</td><td>$details</td></tr>";
            // end of one note
            $htm .= "</table></tr>\n";
        }
    }
    $htm .= "</table>\n";
    $htm .= "</body>\n";
    $htm .= "</html>\n";
    // check if need to attach converted JPG/GIF image
    if (isset($_POST['imagefile']) && file_exists($_POST['imagefile'])) {
        $attach = array();
        $imagefile = $_POST['imagefile'];
        $attach['file'] = $imagefile;
        $attach['basename'] = basename($imagefile);
        $attach['encoding'] = 'base64';
        $attach['contenttype'] = strcasecmp(substr($imagefile, -3), "jpg")? "image/gif" : "image/jpeg";
        $attach['displacement'] = 'attachment';
        $atts[] = $attach;
    }
    // check if need to attach user-uploaded documents
    if (count($uids)) {
        $query = "select * from attachment where uuid in (";
        foreach ($uids as $uid)
            $query .= "'$uid',";
        $query = substr($query, 0, -1) . ")";
        $result = $dbcon->query($query);
        while ($result && ($row = $result->fetch(PDO::FETCH_ASSOC))) {
            $attach = array();
            $path = $row['path'];
            if (file_exists($path)) {
                $attach['file'] = $path;
                $attach['remove'] = false;
            } else {  // attachment is stored as BLOB instead of regular file
                $ext = pathinfo($path, PATHINFO_EXTENSION);
                $tempname = tempnam(getenv("TEMP"), "PacsOne");
                unlink($tempname);
                $tempname = $tempname . ".$ext";
                $fp = fopen($tempname, "wb");
                if ($fp)  {
                    fwrite($fp, $row['data']);
                    fclose($fp);
                }
                $attach['file'] = $tempname;
                // set flag to remove when done
                $attach['remove'] = true;
            }
            $attach['basename'] = basename($path);
            $attach['encoding'] = 'base64';
            $attach['contenttype'] = $row['mimetype'];
            $attach['displacement'] = 'attachment';
            $atts[] = $attach;
        }
    }
    return $htm;
}

function downloadEntries(&$html)
{
    if (!isset($_POST['entry']))
        return pacsone_gettext("No item is selected for download");
    global $PRODUCT;
    global $BGCOLOR;
    error_reporting(E_ERROR);
    ob_start();
    $ok = array();
    $result = "";
    $entry = $_POST['entry'];
    $tempname = tempnam(getenv("TEMP"), "PacsOne");
    unlink($tempname);
    $tempname = $tempname . ".htm";
    $fp = fopen($tempname, "wb");
    if ($fp)  {
        fwrite($fp, $html);
        fclose($fp);
        while (@ob_end_clean());
        header("Cache-Control: cache, must-revalidate");   
        header("Pragma: public");
        header("Content-type: text/html;");
        // MSIE handling of Content-Disposition
        if (strstr($_SERVER["HTTP_USER_AGENT"], "MSIE")) {
            $filename = "ImageNotes.htm";
            $disposition = "Content-disposition: file; filename=\"$filename\"";
        } else {
            $filename = "ImageNotes.html";
            $disposition = "Content-disposition: attachment; filename=\"$filename\"";
        }
        header($disposition);
        header("Content-length: " . filesize($tempname));
        $fp = fopen($tempname, "rb");
        fpassthru($fp);
        fclose($fp);
        unlink($tempname);
    } else {
        $result .= "<h3><font color=red>";
        $result .= sprintf(pacsone_gettext("Failed to open temporary file: [%s] when downloading image notes"), $tempname);
        $result .= "</font></h3><P>\n";
    }
    // return status
    return $result;
}

function deleteEntries($username)
{
    if (!isset($_POST['entry']))
        return pacsone_gettext("No item is selected for deletion");
    global $dbcon;
    $ok = array();
    $errors = array();
    $entry = $_POST['entry'];
    foreach ($entry as $value) {
        $query = "delete from imagenotes where id=?";
        $bindList = array($value);
        if (!$dbcon->preparedStmt($query, $bindList)) {
            $errors[$value] = "Database Error: " . $dbcon->getError();
            continue;
        }
        // delete attachment if any
        $result = $dbcon->preparedStmt("select path from attachment where id=?", $bindList);
        if ($result && $result->rowCount()) {
            while ($path = $result->fetchColumn()) {
                if (file_exists($path))
                    unlink($path);
            }
        }
        $dbcon->preparedStmt("delete from attachment where id=?", $bindList);
        $ok[] = $value;
        // log activity to system journal
        $dbcon->logJournal($username, "Delete", "ImageNotes", $value);
    }
    $result = "";
    if (!empty($errors)) {
        if (count($errors) == 1)
            $result = pacsone_gettext("Error deleting the following Image Note");
        else
            $result = pacsone_gettext("Error deleting the following Image Notes");
        $result .= ":<P>\n";
        foreach ($errors as $key => $value) {
            $result .= "<h3><font color=red><u>$key</u> - $value</font></h3><P>\n";
        }
    }
    return $result;
}

function addEntry(&$username)
{
    global $dbcon;
    $result = "";
    $bindList = array();
    $query = "insert into imagenotes (username,created,uuid,headline,notes) ";
    $query .= "values(?,";
    $bindList[] = $username;
    $query .= $dbcon->useOracle? "SYSDATE,?," : "NOW(),?,";
    $bindList[] = $_POST['uid'];
    $query .= "?,";
    $bindList[] = $_POST['headline'];
    $query .= "?)";
    $bindList[] = $_POST['notes'];
    if (!$dbcon->preparedStmt($query, $bindList)) {
        $result = "Database Error: " . $dbcon->getError();
    } else {
        $id = $dbcon->insert_id("imagenotes");
        // add attachment if any
        if (isset($_SESSION['attachments']))
            $result = uploadAttachments($username, $id);
        // log activity to system journal
        $dbcon->logJournal($username, "Add", "ImageNotes", $id);
    }
    return $result;
}

function modifyEntry(&$username, &$id)
{
    global $dbcon;
    $result = "";
    $query = "update imagenotes set headline=?,notes=? where id=?";
    $bindList = array($_POST['headline'], $_POST['notes'], $id);
    // execute SQL query
    if (!$dbcon->preparedStmt($query, $bindList)) {
        $result .= "<h3><font color=red>";
        $result .= sprintf(pacsone_gettext("Error updating Image Note <u>%s</u>: "), $id);
        $result .= $dbcon->getError();
        $result .= "</font></h3><p>\n";
    } else {
        // add attachment if any
        if (isset($_SESSION['attachments']))
            $result = uploadAttachments($username, $id);
        // log activity to system journal
        $dbcon->logJournal($username, "Modify", "ImageNotes", $id);
    }
    return $result;
}

function addEntryForm(&$username, &$patientId, &$studyUid, &$uid)
{
    include_once 'checkInput.js';
    include_once 'checkUncheck.js';
    global $PRODUCT;
    global $BGCOLOR;
    global $dbcon;
    global $MIME_TBL;
    $result = $dbcon->query("select maxupload from config");
    $maxupload = $result->fetchColumn() * 1024 * 1024;
    $disabled = $dbcon->hasaccess("upload", $username)? "" : "disabled";
    // display Add Image Notes form
    print "<html>\n";
    print "<head><title>$PRODUCT - " . pacsone_gettext("Add Image Note") . "</title></head>\n";
    print "<body>\n";
    require_once 'header.php';
    print "<table width=100% border=0 cellspacing=5 cellpadding=0>\n";
    print "<tr valign=top>";
    print "<td class=notes>";
    displayImageInfo($patientId, $studyUid, $uid);
    print "</td>";
    print "<td width=1 bgcolor=$BGCOLOR><img src=blank.gif width=1 height=1></td>";
    print "<td>";
    // Add Image Note form
    print "<table border=0 cellpadding=2 cellspacing=5>\n";
    print "<form onSubmit='return checkHeadline(this.headline);' method='POST' action='imageNotes.php' enctype='multipart/form-data'>\n";
    print "<input type='hidden' name='imagenoteaction'>\n";
    print "<input type=hidden name='MAX_FILE_SIZE' value=$maxupload>\n";
    print "<input type=hidden name='uid' value='$uid'>\n";
    print "<input type=hidden name='class' value='image'>\n";
    print "<tr><td>" . pacsone_gettext("Enter Subject Headline (up to 64 characters):") . "</td>\n";
    $value = isset($_POST['headline'])? $_POST['headline'] : "";
    print "<td><input type='text' size=64 maxlength=64 name='headline' value='$value'></td></tr>\n";
    print "<tr><td>" . pacsone_gettext("Enter Detailed Notes About This Image:") . "</td>\n";
    $value = isset($_POST['notes'])? $_POST['notes'] : "";
    print "<td><textarea rows=8 cols=64 name='notes' wrap=virtual>$value</textarea></td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Attachment:");
    print "</td>\n";
    print "<td><input type=file name='attachfile' size=64 $disabled><br>\n";
    print pacsone_gettext("(The following file types can be attached)");
    print "<br><b>";
    foreach ($MIME_TBL as $ext => $mimetype) {
        print " .$ext";
    }
    print "</b><p>";
    $mbytes = $maxupload / 1024 / 1024;
    print "<input class='btn btn-primary' type=submit name='action' value='";
    print pacsone_gettext("Attach");
    print "' onclick='switchText(this.form,\"imagenoteaction\",\"Attach\")' $disabled> ";
    printf(pacsone_gettext("(max %d Mb)"), $mbytes);
    print "<br>\n";
    // display any attchments here
    if (isset($_SESSION['attachments'])) {
        $attachments = $_SESSION['attachments'];
        if (count($attachments)) {
            $pdf = false;
            print "<br>";
            foreach ($attachments as $att) {
                $value = $att['name'];
                if (strcasecmp(substr($value, -4), ".pdf") == 0)
                    $pdf = true;
                print "<input type=checkbox name='unattach[]' value='$value'>";
                print "<img src='attachment.gif' border=0>";
                print "<b>" . $att['name'] . "</b> (" . $att['size'] . " bytes)<br>\n";
            }
            print "<br><input class='btn btn-primary' type=submit name='action' value='";
            print pacsone_gettext("Unattach");
            print "' onclick='switchText(this.form,\"imagenoteaction\",\"Unattach\")'>\n";
            if ($pdf) {
                print "<p><input type=checkbox name='pdf2dcm'>";
                print pacsone_gettext("Convert PDF to Dicom Encapsulated Document object");
            }
        }
    }
    print "</td></tr>\n";
    print "<tr><td colspan=2>&nbsp;</td></tr>\n";
    print "<tr><td><input type='submit' name='action' value='";
    print pacsone_gettext("Add");
    print "' onclick='switchText(this.form,\"imagenoteaction\",\"Add\")'></td></tr>\n";
    print "</form></table>\n";
    // attach full-size image below
    print "<p>";
    showFullSizeImage($uid);
    print "</td></tr>";
    print "</table>";
    require_once 'footer.php';
    print "</body>\n";
    print "</html>\n";
}

function modifyEntryForm(&$username, &$patientId, &$studyUid, &$uid)
{
    include_once 'checkInput.js';
    include_once 'checkUncheck.js';
    global $PRODUCT;
    global $BGCOLOR;
    global $dbcon;
    global $MIME_TBL;
    $result = $dbcon->query("select maxupload from config");
    $maxupload = $$result->fetchColumn() * 1024 * 1024;
    $disabled = $dbcon->hasaccess("upload", $username)? "" : "disabled";
    // display Modify Image Notes form
    print "<html>\n";
    print "<head><title>$PRODUCT - " . pacsone_gettext("Modify Image Note") . "</title></head>\n";
    print "<body>\n";
    require_once 'header.php';
    print "<table width=100% border=0 cellspacing=5 cellpadding=0>\n";
    print "<tr valign=top>";
    print "<td class=notes>";
    displayImageInfo($patientId, $studyUid, $uid);
    print "</td>";
    print "<td width=1 bgcolor=$BGCOLOR><img src=blank.gif width=1 height=1></td>";
    print "<td>";
    $headline = "";
    $notes = "";
    $noteid = $_REQUEST['id'];
    if (!is_numeric($noteid)) {
	    print "<p><font color=red>";
        printf(pacsone_gettext("Error: Invalid Note ID: <b>[%s]</b>"), $noteid);
        print "</font>";
	    exit();
    }
    $query = "select headline,notes from imagenotes where id=?";
    $bindList = array($noteid);
    $result = $dbcon->preparedStmt($query, $bindList);
    if ($result && ($row = $result->fetch(PDO::FETCH_NUM))) {
        $headline = $row[0];
        $notes = $row[1];
    }
    // Modify Image Note form
    print "<table border=0 cellpadding=2 cellspacing=5>\n";
    print "<form onSubmit='return checkHeadline(this.headline);' method='POST' action='imageNotes.php' enctype='multipart/form-data'>\n";
    print "<input type='hidden' name='imagenoteaction'>\n";
    print "<input type=hidden name='MAX_FILE_SIZE' value=$maxupload>\n";
    print "<input type=hidden name='uid' value='$uid'>\n";
    print "<input type=hidden name='id' value='$noteid'>\n";
    print "<input type=hidden name='modify' value=1>\n";
    print "<tr><td>" . pacsone_gettext("Enter Subject Headline (up to 64 characters):") . "</td>\n";
    print "<td><input type='text' size=64 maxlength=64 name='headline' value='$headline'></td></tr>\n";
    print "<tr><td>" . pacsone_gettext("Enter Detailed Notes About This Image:") . "</td>\n";
    print "<td><textarea rows=8 cols=64 name='notes' wrap=virtual>$notes</textarea></td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Attachment:");
    print "</td>\n";
    print "<td>";
    // list existing attachments
    $query = "select * from attachment where uuid=? and id=?";
    $bindList = array($uid, $noteid);
    $result = $dbcon->preparedStmt($query, $bindList);
    if ($result && $result->rowCount()) {
        print "<br>";
        $count = 0;
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $seq = $row['seq'];
            $name = basename($row['path']);
            $size = file_exists($row['path'])? filesize($row['path']) : $row['totalsize'];
            print "<input type='checkbox' name='seq[]' value=$seq></input>";
            print "<img src='attachment.gif' border=0>";
            print "<b>$name</b> ($size bytes)\n";
            print "<br>";
            $count++;
        }
        if ($count) {
            print "<input type='submit' name='action' value='";
            print pacsone_gettext("Delete");
            print "' onclick='switchText(this.form,\"imagenoteaction\",\"DeleteAttachment\")'></input>";
            print "<p>";
        }
    }
    // add any new attachments
    print "<br><input type=file name='attachfile' size=64 $disabled><br>\n";
    print pacsone_gettext("(The following file types can be attached)");
    print "<br><b>";
    foreach ($MIME_TBL as $ext => $mimetype) {
        print " .$ext";
    }
    print "</b><p>";
    $mbytes = $maxupload / 1024 / 1024;
    print "<input class='btn btn-primary' type=submit name='action' value='";
    print pacsone_gettext("Attach");
    print "' onclick='switchText(this.form,\"imagenoteaction\",\"Attach\")' $disabled> ";
    printf(pacsone_gettext("(max %d Mb)"), $mbytes);
    print "<br>\n";
    $attachments = array();
    if (isset($_SESSION['attachments'])) {
        $attachments = $_SESSION['attachments'];
    }
    if (count($attachments)) {
        print "<br>";
        foreach ($attachments as $att) {
            $value = $att['name'];
            print "<input type=checkbox name='unattach[]' value='$value'>";
            print "<img src='attachment.gif' border=0>";
            print "<b>" . $att['name'] . "</b> (" . $att['size'] . " bytes)<br>\n";
        }
        print "<br><input class='btn btn-primary' type=submit name='action' value='";
        print pacsone_gettext("Unattach");
        print "' onclick='switchText(this.form,\"imagenoteaction\",\"Unattach\")'>\n";
    }
    print "</td></tr>\n";
    print "<tr><td colspan=2>&nbsp;</td></tr>\n";
    print "<tr><td><input type='submit' name='action' value='";
    print pacsone_gettext("Modify");
    print "' onclick='switchText(this.form,\"imagenoteaction\",\"Modify\")'></td></tr>\n";
    print "</form></table>\n";
    // attach full-size image below
    print "<p>";
    showFullSizeImage($uid);
    print "</td></tr>";
    print "</table>";
    require_once 'footer.php';
    print "</body>\n";
    print "</html>\n";
}

function displayImageInfo(&$patientId, &$studyUid, &$uid)
{
    global $dbcon;
    global $CUSTOMIZE_PATIENT_ID;
    global $CUSTOMIZE_PATIENT_NAME;
    global $CUSTOMIZE_REFERRING_DOC;
    global $CUSTOMIZE_READING_DOC;
    // check whether to bypass Series level
    $skipSeries = 0;
    $result = $dbcon->query("select skipseries from config");
    if ($result && ($row = $result->fetch(PDO::FETCH_NUM)))
        $skipSeries = $row[0];
    $url = "study.php?patientId=" . urlencode($patientId);
    print "$CUSTOMIZE_PATIENT_ID:" . " <a href='$url'>$patientId</a><br>";
    print "$CUSTOMIZE_PATIENT_NAME:" . " <a href='$url'>" .  $dbcon->getPatientName($patientId) . "</a><br>";
    // query patient information
    $query = "select * from study where uuid=?";
    $bindList = array($studyUid);
    $result = $dbcon->preparedStmt($query, $bindList);
    if ($result && ($row = $result->fetch(PDO::FETCH_ASSOC))) {
        $skip = ($skipSeries && $dbcon->hasSRseries($studyUid))? false : $skipSeries;
        $url = $skip? "image.php" : "series.php";
        $url .= "?patientId=" . urlencode($patientId);
        $url .= "&studyId=" . urlencode($studyUid);
        print pacsone_gettext("Study ID:") . " <a href='$url'>" . $row['id'] . "</a><br>";
        print pacsone_gettext("Study Date:") . " <b>" . $dbcon->formatDate($row['studydate']) . "</b><br>";
        print pacsone_gettext("Accession Number:") . " <b>" . $row['accessionnum'] . "</b><br>";
        if (isset($row['referringphysician']) && strlen($row['referringphysician']))
            print "$CUSTOMIZE_REFERRING_DOC:" . " <b>" . str_replace("^", " ", $row['referringphysician']) . "</b><br>";
        if (isset($row['description']) && strlen($row['description']))
            print pacsone_gettext("Study Description:") . " <b>" . $row['description'] . "</b><br>";
        if (isset($row['readingphysician']) && strlen($row['readingphysician']))
            print "$CUSTOMIZE_READING_DOC:" . " <b>" . str_replace("^", " ", $row['readingphysician']) . "</b><br>";
        if (isset($row['admittingdiagnoses']) && strlen($row['admittingdiagnoses']))
            print pacsone_gettext("Admitting Diagnoses:") . " <b>" . str_replace("^", " ", $row['admittingdiagnoses']) . "</b><br>";
        if (isset($row['interpretationauthor']) && strlen($row['interpretationauthor']))
            print pacsone_gettext("Interpretation Author:") . " <b>" . str_replace("^", " ", $row['interpretationauthor']) . "</b><br>";
    }
    // query series information
    $query = "select * from series INNER JOIN image ON series.uuid = image.seriesuid WHERE image.uuid=?";
    $bindList = array($uid);
    $result = $dbcon->preparedStmt($query, $bindList);
    if ($result && ($row = $result->fetch(PDO::FETCH_ASSOC))) {
        $url = "image.php?patientId=" . urlencode($patientId);
        $url .= "&studyId=" . urlencode($studyUid);
        $url .= "&seriesId=" . urlencode($row['seriesuid']);
        print "<p>" . pacsone_gettext("Series Number:") . " <a href='$url'>" . $row['seriesnumber'] . "</a><br>";
        print pacsone_gettext("Series Date:") . " <b>" . $dbcon->formatDate($row['seriesdate']) . "</b><br>";
        print pacsone_gettext("Modality:") . " <b>" . $row['modality'] . "</b><br>";
        if (isset($row['bodypart']) && strlen($row['bodypart']))
            print pacsone_gettext("Body Part:") . " <b>" . $row['bodypart'] . "</b><br>";
        if (isset($row['description']) && strlen($row['description']))
            print pacsone_gettext("Series Description:") . " <b>" . $row['description'] . "</b><br>";
        if (isset($row['instances']) && strlen($row['instances']))
            print pacsone_gettext("Number of Instances:") . " <b>" . $row['instances'] . "</b><br>";
    }
}

function viewNotesForm(&$username, &$patientId, &$studyUid, &$uid)
{
    include_once "checkUncheck.js";
    require_once "display.php";
    global $dbcon;
    global $PRODUCT;
    global $BGCOLOR;
    $modify = $dbcon->hasaccess("modifydata", $username);
    $download = $dbcon->hasaccess("download", $username);
    print "<html>\n";
    print "<head><title>$PRODUCT - " . pacsone_gettext("Image Notes") . "</title></head>\n";
    print "<body>\n";
    require_once 'header.php';
    print "<table width=100% border=0 cellspacing=5 cellpadding=0>\n";
    print "<tr valign=top>";
    print "<td class=notes>";
    displayImageInfo($patientId, $studyUid, $uid);
    print "</td>";
    print "<td width=1 bgcolor=$BGCOLOR><img src=blank.gif width=1 height=1></td>";
    print "<td>";
    print "<form method='POST' action='imageNotes.php'>\n";
    print "<input type='hidden' name='imagenoteaction'>\n";
    print "<input type=hidden name='uid' value='$uid'>\n";
    $count = 0;
    $query = "select * from imagenotes where uuid=? order by created asc";
    $bindList = array($uid);
    $result = $dbcon->preparedStmt($query, $bindList);
    if ($result) {
        $rows = array();
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = $row;
        }
        $count = displayNotes("imagenotes", $rows, $username, "imageNotes.php", 1, 0);
    }
    print "<p><table width=60% border=0 cellspacing=0 cellpadding=5>\n";
    print "<tr>\n";
    if ($count) {
        $check = pacsone_gettext("Check All");
        $uncheck = pacsone_gettext("Uncheck All");
        print "<td><input type=button value='$check' name='checkUncheck' onClick='checkAll(this.form,\"entry\", \"$check\", \"$uncheck\")'>&nbsp;\n";
    }
    print "<input class='btn btn-primary' type=submit value='";
    print pacsone_gettext("Add");
    print "' name='action' title='";
    print pacsone_gettext("Add New Image Note");
    print "' onclick='switchText(this.form,\"imagenoteaction\",\"Add\")'>&nbsp;\n";
    if ($count && $modify) {
        print "<input class='btn btn-primary' type=submit value='";
        print pacsone_gettext("Delete");
        print "' name='action' title='";
        print pacsone_gettext("Delete Selected Image Notes");
        print "' onclick='switchText(this.form,\"imagenoteaction\",\"Delete\");return confirm(\"";
        print pacsone_gettext("Are you sure?");
        print "\");'>&nbsp;\n";
    }
    if ($count && $download) {
        print "<input class='btn btn-primary' type=submit value='";
        print pacsone_gettext("Download");
        print "' name='action' title='";
        print pacsone_gettext("Download Selected Image Notes");
        print "' onclick='switchText(this.form,\"imagenoteaction\",\"Download\");'>";
        print "&nbsp;\n";
    }
    print "</tr>\n";
    // attach full-size image below
    print "<tr><td>";
    $imagefile = showFullSizeImage($uid);
    print "</td></tr>";
    if ($count) {
        $result = $dbcon->query("select * from smtp");
        if ($result && $result->rowCount()) {
            print "<input type=hidden name='imagefile' value='$imagefile'>";
            print "<tr><td>";
            print "<input class='btn btn-primary' type=submit value='";
            print pacsone_gettext("Email Image Notes To");
            print "' name='action' title='";
            print pacsone_gettext("Email Selected Image Notes To Specified Email Address (es)");
            print "' onclick='switchText(this.form,\"imagenoteaction\",\"Email\");'>";
            print "&nbsp;<input type='text' name='emailaddr' size=64 maxlength=256>";
            print "</td></tr>";
        }
    }
    print "</table>\n";
    print "</table>";
    print "</form>";
    require_once 'footer.php';
    print "</body>\n";
    print "</html>\n";
}

function showFullSizeImage(&$id)
{
    global $dbcon;
    $query = "SELECT path FROM image where uuid=?";
    $bindList = array($id);
    $result = $dbcon->preparedStmt($query, $bindList);
    $path = $result->fetchColumn();
    // convert the image
    $handle = imagick_readimage($path);
    if (imagick_iserror($handle)) {
        $reason      = imagick_failedreason($handle);
        $description = imagick_faileddescription($handle);
        print pacsone_gettext("imagick_readimage() failed!");
        printf(pacsone_gettext("<BR>\nReason: %s<BR>\nDescription: %s<BR>\n"), $reason, $description);
        exit();
    }
    $imagedir = "images";
    $file = strtr(getcwd(), "\\", "/");
    // append '/' at the end if not so already
    if (strcmp(substr($file, strlen($file)-1, 1), "/"))
	    $file .= "/";
    $file .= "$imagedir/";
    if (file_exists($file . $id . ".gif"))
    {
        $file .= $id . ".gif";
    }
    else
    {
        $file .= $id . ".jpg";
    }
    // display the converted image
    print "<table width=100% border=0 cellspacing=0 cellpadding=0>\n";
    $base = basename($file);
    print "<tr><td>\n";
    print "<P><IMG SRC='$imagedir/$base' BORDER='0' ALIGN='middle' ALT='$id'><P>\n";
    print "</td></tr>\n";
    print "</table>\n";
    return $file;
}

?>
