<?php
//
// studyNotes.php
//
// Module for maintaining the Study Notes Table
//
// CopyRight (c) 2003-2021 RainbowFish Software
//
if (!session_id())
    session_start();

require_once 'locale.php';
include_once 'database.php';
include_once 'upload.php';
include_once 'utils.php';

function transcribe(&$dbcon, &$uid, &$name, &$template)
{
    global $XSCRIPT_BOOKMARK_FIELD_TBL;
    $username = $dbcon->username;
    com_load_typelib('Word.Application');
    $word = new COM("word.application") or die(pacsone_gettext("Unable to instantiate Microsoft Word"));
    $output = "C:/$uid-$username.doc";
    if (!copy($template, $output))
        die(sprintf(pacsone_gettext("Failed to copy file from [%s] to [%s]"), $template, $output));
    $query = "select distinct * from study inner join patient on study.patientid=patient.origid where study.uuid=?";
    $bindList = array($uid);
    $result = $dbcon->preparedStmt($query, $bindList);
    if (!$result || $result->rowCount() == 0)
        die(sprintf(pacsone_gettext("Failed to find Study Instance UID: [%s]"), $uid));
    $studyRow = $result->fetch(PDO::FETCH_ASSOC);
    // use like this. Place your's word file in C drive
    $word->Documents->Open($output);
    // get the defined bookmarks for this template
    $query = "select id,bookmark from xscriptbookmark where template=?";
    $bindList = array($name);
    $result = $dbcon->preparedStmt($query, $bindList);
    while ($result && ($xsrow = $result->fetch(PDO::FETCH_NUM))) {
        $value = "";
        $id = $xsrow[0];
        $bookmark = $xsrow[1];
        $column = $XSCRIPT_BOOKMARK_FIELD_TBL[$id][2];
        if (isset($studyRow[$column]))
            $value = $studyRow[$column];
        else if (strcasecmp($column, "PatientName") == 0) {
            if (isset($studyRow['firstname']) && strlen($studyRow['firstname']))
                $value .= $studyRow['firstname'];
            if (isset($studyRow['middlename']) && strlen($studyRow['middlename']))
                $value .= " " . $studyRow['middlename'];
            if (isset($studyRow['lastname']) && strlen($studyRow['lastname']))
                $value .= " " . $studyRow['lastname'];
            if (!strlen($value) || !strcmp($value, " ") || !strcmp($value, "  "))
                $value = pacsone_gettext("(Blank)");
        }
        if (strlen($value) && $word->ActiveDocument->Bookmarks->Exists($bookmark)) {
            $obj = $word->ActiveDocument->Bookmarks($bookmark);
            $range = $obj->Range;
            $range->Text = $value ;
        }
    }
    $word->Documents[1]->SaveAs($output);
    $word->ActiveDocument->Close(false);
    $word->Quit();
    $word = null;
    return $output;
}


if (isset($_REQUEST['uid']))
    $uid = $_REQUEST['uid'];
else
    die("<h2><font color=red>" . pacsone_gettext("Unknown Study Instance UID") . "</font></h2>");
if (!isUidValid($uid))
    die("<h2><font color=red>" . pacsone_gettext("Invalid Study Instance UID") . "</font></h2>");
// main
global $PRODUCT;
$dbcon = new MyConnection();
$username = $dbcon->username;
$query = "select patientid from study where uuid=?";
$bindList = array($uid);
$result = $dbcon->preparedStmt($query, $bindList);
if ($result && ($row = $result->fetch(PDO::FETCH_NUM))) {
    $patientId = $row[0];
} else {
    global $CUSTOMIZE_PATIENT_ID;
    die("<h2><font color=red>" . sprintf(pacsone_gettext("Unknown %s"), $CUSTOMIZE_PATIENT_ID) . "</font></h2>");
}
if (isset($_REQUEST['action']))
   	$action = $_REQUEST['studynoteaction'];
else if (isset($_GET['view']) && ($_GET['view'] == 1))
    $action = "View";
else if (isset($_GET['modify']) && ($_GET['modify'] == 1))
    $action = "Modify";
$result = NULL;
if (strcasecmp($action, "View") == 0) {
    viewNotesForm($username, $patientId, $uid);
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
        modifyEntryForm($username, $patientId, $uid);
    else
        addEntryForm($username, $patientId, $uid);
} else if (isset($action) && strcasecmp($action, "Attach") == 0) {
    $attached = $_FILES['attachfile'];
    $origname = $attached['name'];
    $error = $attached['error'];
    if ($error) {
        print "<h2><font color=red>";
        printf(pacsone_gettext("Error uploading file <b>%s</b>: %s"), $origname, getUploadError($error));
        print "</font></h2>";
        exit();
    }
    // security check
    if (!uploadCheck($origname)) {
        print "<h2><font color=red>";
        printf(pacsone_gettext("Access denied uploading file: <b><u>%s</b></u>"), $origname);
        print "</font></h2>";
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
        print "<h2><font color=red>";
        printf(pacsone_gettext("Upload file: %s failed"), $destfile);
        print "<br>";
        print_r($_FILES);
        print "</font></h2>";
        exit();
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
        modifyEntryForm($username, $patientId, $uid);
    else
        addEntryForm($username, $patientId, $uid);
} else if (isset($action) && strcasecmp($action, "Add") == 0) {
    if (isset($_POST['headline']))
        $result = addEntry($username);
    else
        addEntryForm($username, $patientId, $uid);
}
else if (isset($action) && strcasecmp($action, "Modify") == 0) {
    if (isset($_POST['id']))
        $result = modifyEntry($username, $_POST['id']);
    else
        modifyEntryForm($username, $patientId, $uid);
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
        $query = "delete from attachment where uuid=? and id=? and seq=?";
        $dbcon->preparedStmt($query, $bindList);
    }
    modifyEntryForm($username, $patientId, $uid);
}
else if (isset($action) && (!strcasecmp($action, "Download") || !strcasecmp($action, "Email"))) {
    if (!isset($_POST['entry']))
        $result = pacsone_gettext("No Study Note is selected.");
    else {
        $atts = array();
        $html = buildHtml($username, $atts);
        if (strcasecmp($action, "Download")) {
            // check email address
            $to = isset($_POST['emailaddr'])? $_POST['emailaddr'] : "";
            if (strlen($to) == 0) {
                $result = "<h3><font color=red>";
                $result .= pacsone_gettext("You must enter a valid email address for sending the study notes information to.");
                $result .= "</font></h3>";
            } else {
                require_once "emailHtml.php";
                $subject = sprintf(pacsone_gettext("%s - Study Note Information"), $PRODUCT);
                $result = emailHtml($to, $subject, $html, $atts);
                if (!strlen($result)) {
                    require_once "header.php";
                    print "<p>";
                    printf(pacsone_gettext("Study Notes sent successfully to <u>%s</u>."), $to);
                    require_once "footer.php";
                }
            }
        } else {
            $result = downloadEntries($username, $html);
        }
    }
} else if (isset($action) && strcasecmp($action, "Xscript") == 0) {
    $template = $_POST['xstemplate'];
    $query = "select path from xscriptemplate where name=?";
    $bindList = array($template);
    $xs = $dbcon->preparedStmt($query, $bindList);
    if ($xs && ($file = $xs->fetchColumn())) {
        // Allow sufficient execution time to the script:
        set_time_limit(0);
        error_reporting(E_ERROR);
        ob_start();
        $output = transcribe($dbcon, $uid, $template, $file);
        while (@ob_end_clean());
        // stream the pre-filled document
        if (strlen($output) && file_exists($output)) {
            $filename = basename($output);
            // MSIE handling of Content-Disposition
            if (strstr($_SERVER["HTTP_USER_AGENT"], "MSIE")) {
                $disposition = "Content-disposition: file; filename=$filename";
            } else {
                $disposition = "Content-disposition: attachment; filename=$filename";
            }
            header("Cache-Control: cache, must-revalidate");   
            header("Pragma: public");
            header("Content-type: application/msword");    
            header($disposition);    
            header("Content-length: " . filesize($output));
            $fp = fopen($output, "rb");
            fpassthru($fp);
            fclose($fp);
            unlink($output);
            exit();
        }
    }
} else if (isset($action) && (!strcasecmp($action, "Approve") || !strcasecmp($action, "Disapprove"))) {
    global $STUDY_NEED_VERIFICATION;
    $value = strcasecmp($action, "Disapprove")? $username : $STUDY_NEED_VERIFICATION;
    $query = "update study set verified=? where uuid=?";
    $bindList = array($value, $uid);
    $result = "";
    if (!$dbcon->preparedStmt($query, $bindList)) {
        $result .= "<h3><font color=red>";
        $result .= sprintf(pacsone_gettext("Error running SQL query <u>%s</u>: "), $query);
        $result .= $dbcon->getError();
        $result .= "</font></h3><p>\n";
    }
}
if (isset($result)) {       // back to the Study page
    if (isset($_SESSION['attachments']))
        unset($_SESSION['attachments']);
    if (empty($result)) {   // success
        $url = "series.php";
        $url .= "?patientId=" . urlencode($patientId);
        $url .= "&studyId=" . urlencode($uid);
        header("Location: $url");
        exit();
    }
    else {                  // error
        print "<html>\n";
        print "<head><title>$PRODUCT - ";
        print pacsone_gettext("Study Notes Error");
        print "</title></head>\n";
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

function buildHtml($username, &$atts)
{
    if (!isset($_POST['entry']))
        return pacsone_gettext("No Study Note is selected.");
    global $dbcon;
    global $PRODUCT;
    global $BGCOLOR;
    $entry = $_POST['entry'];
    $htm = "<html><head><title>\n";
    $htm .= sprintf(pacsone_gettext("%s - Study Notes"), $PRODUCT);
    $htm .= "</title></head>\n";
    $htm .= "<body>\n";
    $htm .= "<table width='100%' border=0 cellpadding=2 cellspacing=0>\n";
    foreach ($entry as $value) {
        $query = "select * from studynotes where id=?";
        $bindList = array($value);
        $studyNotes = $dbcon->preparedStmt($query, $bindList);
        while ($studyNotes && ($note = $studyNotes->fetch(PDO::FETCH_ASSOC))) {
            $uid = $note['uuid'];
            $user = $note['username'];
            $when = $note['created'];
            $headline = $note['headline'];
            $details = $note['notes'];
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
            $study = $dbcon->query("select * from patient inner join study on patient.origid=study.patientid where study.uuid='$uid'");
            if ($study && ($studyRow = $study->fetch(PDO::FETCH_ASSOC))) {
                // patient information
                global $CUSTOMIZE_PATIENT_ID;
                $htm .= "<tr><td>$CUSTOMIZE_PATIENT_ID:";
                $htm .= "</td><td>" . $studyRow['patientid'] . "</td></tr>";
                global $CUSTOMIZE_PATIENT_NAME;
                $htm .= "<tr><td>$CUSTOMIZE_PATIENT_NAME:";
                $htm .= "</td><td>" . $dbcon->getPatientNameByStudyUid($uid) . "</td></tr>";
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
            // attachments
            $query = "select * from attachment where uuid='$uid';";
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
    }
    $htm .= "</table>\n";
    $htm .= "</body>\n";
    $htm .= "</html>\n";
    return $htm;
}

function downloadEntries($username, &$html)
{
    if (!isset($_POST['entry']))
        return pacsone_gettext("No item is selected for download");
    global $dbcon;
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
    if ($fp) {
        fwrite($fp, $html);
        fclose($fp);
        while (@ob_end_clean());
        header("Cache-Control: cache, must-revalidate");   
        header("Pragma: public");
        header("Content-type: text/html;");
        // MSIE handling of Content-Disposition
        if (strstr($_SERVER["HTTP_USER_AGENT"], "MSIE")) {
            $filename = "StudyNotes.htm";
            $disposition = "Content-disposition: file; filename=\"$filename\"";
        } else {
            $filename = "StudyNotes.html";
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
        $result .= sprintf(pacsone_gettext("Failed to open temporary file: [%s] when downloading study notes"), $tempname);
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
        $query = "delete from studynotes where id=?";
        $bindList = array($value);
        if (!$dbcon->preparedStmt($query, $bindList)) {
            $errors[$value] = sprintf(pacsone_gettext("Database Error: %s"), $dbcon->getError());
            continue;
        }
        // delete attachment if any
        $query = "select path from attachment where id=?";
        $result = $dbcon->preparedStmt($query, $bindList);
        if ($result && $result->rowCount()) {
            while ($path = $result->fetchColumn()) {
                if (file_exists($path))
                    unlink($path);
            }
        }
        $query = "delete from attachment where id=?";
        $dbcon->preparedStmt($query, $bindList);
        $ok[] = $value;
        // log activity to system journal
        $dbcon->logJournal($username, "Delete", "StudyNotes", $value);
    }
    $result = "";
    if (!empty($errors)) {
        if (count($errors) == 1)
            $result = pacsone_gettext("Error deleting the following Study Note:");
        else
            $result = pacsone_gettext("Error deleting the following Study Notes:");
        $result .= "<P>\n";
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
    $when = $dbcon->useOracle? "SYSDATE" : "NOW()";
    $query = "insert into studynotes (username,created,uuid,headline,notes) ";
    $query .= "values(?,$when,?,?,?)";
    $bindList = array($username, $_POST['uid'], $_POST['headline'], $_POST['notes']);
    if (!$dbcon->preparedStmt($query, $bindList)) {
        $result = sprintf(pacsone_gettext("Error running query: [%s]<br>"), $query);
        $result .= sprintf(pacsone_gettext("Database Error: %s"), $dbcon->getError());
    } else {
        $id = $dbcon->insert_id("studynotes");
        // add attachment if any
        if (isset($_SESSION['attachments']))
            $result = uploadAttachments($username, $id);
        // log activity to system journal
        $dbcon->logJournal($username, "Add", "StudyNotes", $id);
    }
    return $result;
}

function modifyEntry(&$username, &$id)
{
    global $dbcon;
    $result = "";
    $headline = $_POST['headline'];
    $notes = $_POST['notes'];
    $query = "update studynotes set headline=?,notes=? where id=?";
    $bindList = array($headline, $notes, $id);
    // execute SQL query
    if (!$dbcon->preparedStmt($query, $bindList)) {
        $result .= "<h3><font color=red>";
        $result .= sprintf(pacsone_gettext("Error updating Study Note <u>%d</u>: "), $id);
        $result .= $dbcon->getError();
        $result .= "</font></h3><p>\n";
    } else {
        // add attachment if any
        if (isset($_SESSION['attachments']))
            $result = uploadAttachments($username, $id);
        // log activity to system journal
        $dbcon->logJournal($username, "Modify", "StudyNotes", $id);
    }
    return $result;
}

function addEntryForm(&$username, &$patientId, &$uid)
{
    include_once 'checkInput.js';
    include_once 'checkUncheck.js';
    global $PRODUCT;
    global $BGCOLOR;
    global $MIME_TBL;
    global $dbcon;
    $result = $dbcon->query("select maxupload from config");
    $max = $result->fetchColumn();
    $maxupload = $max * 1024 * 1024;
    $disabled = $dbcon->hasaccess("upload", $username)? "" : "disabled";
    // display Add Study Notes form
    print "<html>\n";
    print "<head><title>$PRODUCT - ";
    print pacsone_gettext("Add Study Note");
    print "</title></head>\n";
    print "<body>\n";
    require_once 'header.php';
    print "<table class=\"table table-bordered\" width=100% border=0 cellspacing=5 cellpadding=0>\n";
    print "<tr valign=top>";
    print "<td width=\"20%\">";
    displayStudyInfo($patientId, $uid);
    print "</td>";
    //print "<td width=1 bgcolor=$BGCOLOR><img src=blank.gif width=1 height=1></td>";
    
    print "<td>";
    // Add Study Note form
    print "<table class=\"table\" border=0 cellpadding=2 cellspacing=5>\n";
    print "<form onSubmit='return checkHeadline(this.headline);' method='POST' action='studyNotes.php' enctype='multipart/form-data'>\n";
    print "<input type='hidden' name='studynoteaction'>\n";
    print "<input type=hidden name='MAX_FILE_SIZE' value=$maxupload>\n";
    print "<input type=hidden name='uid' value='$uid'>\n";
    print "<input type=hidden name='class' value='study'>\n";
    print "<tr><td width=\"20%\">";
    print pacsone_gettext("Enter Subject Headline (up to 64 characters):") . "</td>\n";
    $value = isset($_POST['headline'])? $_POST['headline'] : "";
    print "<td><input type='text' class=\"form-control\" size=64 maxlength=64 name='headline' value='$value'></td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Enter Detailed Notes About This Study:") . "</td>\n";
    $value = isset($_POST['notes'])? $_POST['notes'] : "";
    print "<td><textarea class=\"form-control\" rows=8 cols=64 name='notes' wrap=virtual>$value</textarea></td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Attachment:");
    print "</td>";
    print "<td><input class=\"btn btn-primary\" type=file name='attachfile' size=64 $disabled><br>\n";
    print pacsone_gettext("(The following file types can be attached)");
    print "<br><b>";
    foreach ($MIME_TBL as $ext => $mimetype) {
        print " .$ext";
    }
    print "</b><p>";
    $mbytes = $maxupload / 1024 / 1024;
    print "<input class=\"btn btn-primary\" type=submit name='action' value='";
    print pacsone_gettext("Attach");
    print "' onclick='switchText(this.form,\"studynoteaction\",\"Attach\")' $disabled> ";
    printf(pacsone_gettext("(max %d Mbytes)"), $mbytes) . "<br>\n";
    // display any attachments here
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
            print "<br><input type=submit name='action' value='";
            print pacsone_gettext("Unattach");
            print "' onclick='switchText(this.form,\"studynoteaction\",\"Unattach\")'>\n";
            if ($pdf) {
                print "<p><input type=checkbox name='pdf2dcm'>";
                print pacsone_gettext("Convert PDF to Dicom Encapsulated Document object");
            }
        }
    }
    print "</td></tr>\n";
    // check if any transcrition template defined for this study
    $query = "select xscript from applentity inner join study on applentity.title=study.sourceae where study.uuid=? and xscript is not null";
    $bindList = array($uid);
    $result = $dbcon->preparedStmt($query, $bindList);
    if ($result && $template = $result->fetchColumn()) {
        print "<tr><td>";
        print "<input type='hidden' name='xstemplate' value=\"$template\">";
        print pacsone_gettext("Transcription Template:");
        print "</td>";
        print "<td>" . sprintf(pacsone_gettext("Transcription template: <u>%s</u> is pre-defined for this study"), $template);
        print "&nbsp;<input type=submit name='action' value='";
        print pacsone_gettext("Download") . "' title='";
        print pacsone_gettext("Download Pre-defined Transcription Template");
        print "' onclick='switchText(this.form,\"studynoteaction\",\"Xscript\")'>\n";
        print "</td></tr>\n";
    }
    print "<tr><td colspan=2>&nbsp;</td></tr>\n";
    print "<tr><td><input class=\"btn btn-primary\" type='submit' name='action' value='";
    print pacsone_gettext("Add");
    print "' onclick='switchText(this.form,\"studynoteaction\",\"Add\")'></td></tr>\n";
    print "</form></table>\n";
    print "</td></tr>";
    print "</table>";
    require_once 'footer.php';
    print "</body>\n";
    print "</html>\n";

    /*
    include_once 'checkInput.js';
    include_once 'checkUncheck.js';
    global $PRODUCT;
    global $BGCOLOR;
    global $MIME_TBL;
    global $dbcon;
    $result = $dbcon->query("select maxupload from config");
    $max = $result->fetchColumn();
    $maxupload = $max * 1024 * 1024;
    $disabled = $dbcon->hasaccess("upload", $username)? "" : "disabled";
    // display Add Study Notes form
    print "<html>\n";
    print "<head><title>$PRODUCT - ";
    print pacsone_gettext("Add Study Note");
    print "</title></head>\n";
    print "<body>\n";
    require_once 'header.php';
    print "<table class=\"table\" width=100% border=0 cellspacing=5 cellpadding=0>\n";
    print "<tr valign=top>";
    print "<td class=notes>";
    displayStudyInfo($patientId, $uid);
    print "</td>";
    print "<td width=1 bgcolor=$BGCOLOR><img src=blank.gif width=1 height=1></td>";
    print "<td>";
    // Add Study Note form
    print "<table border=0 cellpadding=2 cellspacing=5>\n";
    print "<form onSubmit='return checkHeadline(this.headline);' method='POST' action='studyNotes.php' enctype='multipart/form-data'>\n";
    print "<input type='hidden' name='studynoteaction'>\n";
    print "<input type=hidden name='MAX_FILE_SIZE' value=$maxupload>\n";
    print "<input type=hidden name='uid' value='$uid'>\n";
    print "<input type=hidden name='class' value='study'>\n";
    print "<tr><td>";
    print pacsone_gettext("Enter Subject Headline (up to 64 characters):") . "</td>\n";
    $value = isset($_POST['headline'])? $_POST['headline'] : "";
    print "<td><input type='text' size=64 maxlength=64 name='headline' value='$value'></td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Enter Detailed Notes About This Study:") . "</td>\n";
    $value = isset($_POST['notes'])? $_POST['notes'] : "";
    print "<td><textarea rows=8 cols=64 name='notes' wrap=virtual>$value</textarea></td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Attachment:");
    print "</td>";
    print "<td><input type=file name='attachfile' size=64 $disabled><br>\n";
    print pacsone_gettext("(The following file types can be attached)");
    print "<br><b>";
    foreach ($MIME_TBL as $ext => $mimetype) {
        print " .$ext";
    }
    print "</b><p>";
    $mbytes = $maxupload / 1024 / 1024;
    print "<input type=submit name='action' value='";
    print pacsone_gettext("Attach");
    print "' onclick='switchText(this.form,\"studynoteaction\",\"Attach\")' $disabled> ";
    printf(pacsone_gettext("(max %d Mbytes)"), $mbytes) . "<br>\n";
    // display any attachments here
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
            print "<br><input type=submit name='action' value='";
            print pacsone_gettext("Unattach");
            print "' onclick='switchText(this.form,\"studynoteaction\",\"Unattach\")'>\n";
            if ($pdf) {
                print "<p><input type=checkbox name='pdf2dcm'>";
                print pacsone_gettext("Convert PDF to Dicom Encapsulated Document object");
            }
        }
    }
    print "</td></tr>\n";
    // check if any transcrition template defined for this study
    $query = "select xscript from applentity inner join study on applentity.title=study.sourceae where study.uuid=? and xscript is not null";
    $bindList = array($uid);
    $result = $dbcon->preparedStmt($query, $bindList);
    if ($result && $template = $result->fetchColumn()) {
        print "<tr><td>";
        print "<input type='hidden' name='xstemplate' value=\"$template\">";
        print pacsone_gettext("Transcription Template:");
        print "</td>";
        print "<td>" . sprintf(pacsone_gettext("Transcription template: <u>%s</u> is pre-defined for this study"), $template);
        print "&nbsp;<input type=submit name='action' value='";
        print pacsone_gettext("Download") . "' title='";
        print pacsone_gettext("Download Pre-defined Transcription Template");
        print "' onclick='switchText(this.form,\"studynoteaction\",\"Xscript\")'>\n";
        print "</td></tr>\n";
    }
    print "<tr><td colspan=2>&nbsp;</td></tr>\n";
    print "<tr><td><input type='submit' name='action' value='";
    print pacsone_gettext("Add");
    print "' onclick='switchText(this.form,\"studynoteaction\",\"Add\")'></td></tr>\n";
    print "</form></table>\n";
    print "</td></tr>";
    print "</table>";
    require_once 'footer.php';
    print "</body>\n";
    print "</html>\n";
    */
}

function modifyEntryForm(&$username, &$patientId, &$uid)
{
    include_once 'checkInput.js';
    include_once 'checkUncheck.js';
    global $PRODUCT;
    global $BGCOLOR;
    global $dbcon;
    global $MIME_TBL;
    $result = $dbcon->query("select maxupload from config");
    $max = $result->fetchColumn();
    $maxupload = $max * 1024 * 1024;
    $disabled = $dbcon->hasaccess("upload", $username)? "" : "disabled";
    // display Modify Study Notes form
    print "<html>\n";
    print "<head><title>$PRODUCT - ";
    print pacsone_gettext("Modify Study Note");
    print "</title></head>\n";
    print "<body>\n";
    require_once 'header.php';
    print "<table class=\"table table-bordered\" width=100% border=0 cellspacing=5 cellpadding=0>\n";
    print "<tr valign=top>";
    print "<td width=\"20%\">";
    displayStudyInfo($patientId, $uid);
    print "</td>";
    //print "<td width=1 bgcolor=$BGCOLOR><img src=blank.gif width=1 height=1></td>";

    print "<td>";
    $headline = "";
    $notes = "";
    $noteid = $_REQUEST['id'];
    if (!is_numeric($noteid)) {
        $error = pacsone_gettext("Invalid Note ID");
        print "<h2><font color=red>$error</font></h2>";
        exit();
    }
    $query = "select headline,notes from studynotes where id=?";
    $bindList = array($noteid);
    $result = $dbcon->preparedStmt($query, $bindList);
    if ($result && ($row = $result->fetch(PDO::FETCH_NUM))) {
        $headline = $row[0];
        $notes = $row[1];
    }
    // Modify Study Note form
    print "<table class=\"table\" border=0 cellpadding=2 cellspacing=5>\n";
    print "<form onSubmit='return checkHeadline(this.headline);' method='POST' action='studyNotes.php' enctype='multipart/form-data'>\n";
    print "<input type='hidden' name='studynoteaction'>\n";
    print "<input type=hidden name='MAX_FILE_SIZE' value=$maxupload>\n";
    print "<input type=hidden name='uid' value='$uid'>\n";
    print "<input type=hidden name='id' value='$noteid'>\n";
    print "<input type=hidden name='modify' value=1>\n";
    print "<tr><td width=\"20%\">";
    print pacsone_gettext("Enter Subject Headline (up to 64 characters):") . "</td>\n";
    print "<td><input type='text' class=\"form-control\" size=64 maxlength=64 name='headline' value='$headline'></td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Enter Detailed Notes About This Study:") . "</td>\n";
    print "<td><textarea class=\"form-control\" rows=8 cols=64 name='notes' wrap=virtual>$notes</textarea></td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Attachment:");
    print "</td>";
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
            print "<b>$name</b> ";
            printf(pacsone_gettext("(%d bytes)"), $size);
            print "<br>";
            $count++;
        }
        if ($count) {
            print "<input class=\"btn btn-primary\" type='submit' name='action' value='";
            print pacsone_gettext("Delete");
            print "' onclick='switchText(this.form,\"studynoteaction\",\"DeleteAttachment\")'></input>";
            print "<p>";
        }
    }
    // add any new attachments
    print "<br><input class=\"btn btn-primary\" type=file name='attachfile' size=64 $disabled><br>\n";
    print pacsone_gettext("(The following file types can be attached)");
    print "<br><b>";
    foreach ($MIME_TBL as $ext => $mimetype) {
        print " .$ext";
    }
    print "</b><p>";
    $mbytes = $maxupload / 1024 / 1024;
    print "<input class=\"btn btn-primary\" type=submit name='action' value='";
    print pacsone_gettext("Attach");
    print "' onclick='switchText(this.form,\"studynoteaction\",\"Attach\")' $disabled> ";
    printf(pacsone_gettext("(max %d Mb)"), $mbytes) . "<br>\n";
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
        print "<br><input type=submit name='action' value='";
        print pacsone_gettext("Unattach");
        print "' onclick='switchText(this.form,\"studynoteaction\",\"Unattach\")'>\n";
    }
    print "</td></tr>\n";
    // check if any transcrition template defined for this study
    $query = "select xscript from applentity inner join study on applentity.title=study.sourceae where study.uuid=? and xscript is not null";
    $bindList = array($uid);
    $result = $dbcon->preparedStmt($query, $bindList);
    if ($result && $template = $result->fetchColumn()) {
        print "<tr><td>";
        print "<input type='hidden' name='xstemplate' value=\"$template\">";
        print pacsone_gettext("Transcription Template:");
        print "</td>";
        print "<td>" . sprintf(pacsone_gettext("Transcription template: <u>%s</u> is pre-defined for this study"), $template);
        print "&nbsp;<input type=submit name='action' value='";
        print pacsone_gettext("Download") . "' title='";
        print pacsone_gettext("Download Pre-defined Transcription Template");
        print "' onclick='switchText(this.form,\"studynoteaction\",\"Xscript\")'>\n";
        print "</td></tr>\n";
    }
    print "<tr><td colspan=2>&nbsp;</td></tr>\n";
    print "<tr><td><input class=\"btn btn-primary\" type='submit' name='action' value='";
    print pacsone_gettext("Modify");
    print "' onclick='switchText(this.form,\"studynoteaction\",\"Modify\")'></td></tr>\n";
    print "</form></table>\n";
    print "</td></tr>";
    print "</table>";
    require_once 'footer.php';
    print "</body>\n";
    print "</html>\n";
    
    /*
    include_once 'checkInput.js';
    include_once 'checkUncheck.js';
    global $PRODUCT;
    global $BGCOLOR;
    global $dbcon;
    global $MIME_TBL;
    $result = $dbcon->query("select maxupload from config");
    $max = $result->fetchColumn();
    $maxupload = $max * 1024 * 1024;
    $disabled = $dbcon->hasaccess("upload", $username)? "" : "disabled";
    // display Modify Study Notes form
    print "<html>\n";
    print "<head><title>$PRODUCT - ";
    print pacsone_gettext("Modify Study Note");
    print "</title></head>\n";
    print "<body>\n";
    require_once 'header.php';
    print "<table class=\"table\" width=100% border=0 cellspacing=5 cellpadding=0>\n";
    print "<tr valign=top>";
    print "<td class=notes>";
    displayStudyInfo($patientId, $uid);
    print "</td>";
    print "<td width=1 bgcolor=$BGCOLOR><img src=blank.gif width=1 height=1></td>";
    print "<td>";
    $headline = "";
    $notes = "";
    $noteid = $_REQUEST['id'];
    if (!is_numeric($noteid)) {
        $error = pacsone_gettext("Invalid Note ID");
        print "<h2><font color=red>$error</font></h2>";
        exit();
    }
    $query = "select headline,notes from studynotes where id=?";
    $bindList = array($noteid);
    $result = $dbcon->preparedStmt($query, $bindList);
    if ($result && ($row = $result->fetch(PDO::FETCH_NUM))) {
        $headline = $row[0];
        $notes = $row[1];
    }
    // Modify Study Note form
    print "<table border=0 cellpadding=2 cellspacing=5>\n";
    print "<form onSubmit='return checkHeadline(this.headline);' method='POST' action='studyNotes.php' enctype='multipart/form-data'>\n";
    print "<input type='hidden' name='studynoteaction'>\n";
    print "<input type=hidden name='MAX_FILE_SIZE' value=$maxupload>\n";
    print "<input type=hidden name='uid' value='$uid'>\n";
    print "<input type=hidden name='id' value='$noteid'>\n";
    print "<input type=hidden name='modify' value=1>\n";
    print "<tr><td>";
    print pacsone_gettext("Enter Subject Headline (up to 64 characters):") . "</td>\n";
    print "<td><input type='text' size=64 maxlength=64 name='headline' value='$headline'></td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Enter Detailed Notes About This Study:") . "</td>\n";
    print "<td><textarea rows=8 cols=64 name='notes' wrap=virtual>$notes</textarea></td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Attachment:");
    print "</td>";
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
            print "<b>$name</b> ";
            printf(pacsone_gettext("(%d bytes)"), $size);
            print "<br>";
            $count++;
        }
        if ($count) {
            print "<input type='submit' name='action' value='";
            print pacsone_gettext("Delete");
            print "' onclick='switchText(this.form,\"studynoteaction\",\"DeleteAttachment\")'></input>";
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
    print "<input type=submit name='action' value='";
    print pacsone_gettext("Attach");
    print "' onclick='switchText(this.form,\"studynoteaction\",\"Attach\")' $disabled> ";
    printf(pacsone_gettext("(max %d Mb)"), $mbytes) . "<br>\n";
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
        print "<br><input type=submit name='action' value='";
        print pacsone_gettext("Unattach");
        print "' onclick='switchText(this.form,\"studynoteaction\",\"Unattach\")'>\n";
    }
    print "</td></tr>\n";
    // check if any transcrition template defined for this study
    $query = "select xscript from applentity inner join study on applentity.title=study.sourceae where study.uuid=? and xscript is not null";
    $bindList = array($uid);
    $result = $dbcon->preparedStmt($query, $bindList);
    if ($result && $template = $result->fetchColumn()) {
        print "<tr><td>";
        print "<input type='hidden' name='xstemplate' value=\"$template\">";
        print pacsone_gettext("Transcription Template:");
        print "</td>";
        print "<td>" . sprintf(pacsone_gettext("Transcription template: <u>%s</u> is pre-defined for this study"), $template);
        print "&nbsp;<input type=submit name='action' value='";
        print pacsone_gettext("Download") . "' title='";
        print pacsone_gettext("Download Pre-defined Transcription Template");
        print "' onclick='switchText(this.form,\"studynoteaction\",\"Xscript\")'>\n";
        print "</td></tr>\n";
    }
    print "<tr><td colspan=2>&nbsp;</td></tr>\n";
    print "<tr><td><input type='submit' name='action' value='";
    print pacsone_gettext("Modify");
    print "' onclick='switchText(this.form,\"studynoteaction\",\"Modify\")'></td></tr>\n";
    print "</form></table>\n";
    print "</td></tr>";
    print "</table>";
    require_once 'footer.php';
    print "</body>\n";
    print "</html>\n";
    */
}

function displayStudyInfo(&$patientId, &$uid)
{
    global $dbcon;
    // check whether to bypass Series level
    $skipSeries = 0;
    $result = $dbcon->query("select skipseries from config");
    if ($result && ($row = $result->fetch(PDO::FETCH_NUM)))
        $skipSeries = $row[0];
    $url = "study.php?patientId=" . urlencode($patientId);
    global $CUSTOMIZE_PATIENT_ID;
    print "$CUSTOMIZE_PATIENT_ID: <a href='$url'>$patientId</a><br>";
    global $CUSTOMIZE_PATIENT_NAME;
    print "$CUSTOMIZE_PATIENT_NAME: <a href='$url'>" .  $dbcon->getPatientName($patientId) . "</a><br>";
    // query patient information
    $query = "select * from study where uuid=?";
    $bindList = array($uid);
    $result = $dbcon->preparedStmt($query, $bindList);
    if ($result && ($row = $result->fetch(PDO::FETCH_ASSOC))) {
        $skip = ($skipSeries && $dbcon->hasSRseries($uid))? false : $skipSeries;
        $url = $skip? "image.php" : "series.php";
        $url .= "?patientId=" . urlencode($patientId);
        $url .= "&studyId=" . urlencode($uid);
        print pacsone_gettext("Study ID:") . " <a href='$url'>" . $row['id'] . "</a><br>";
        print pacsone_gettext("Study Date:") . " <b>" . $dbcon->formatDate($row['studydate']) . "</b><br>";
        print pacsone_gettext("Accession Number:") . " <b>" . $row['accessionnum'] . "</b><br>";
        global $CUSTOMIZE_REFERRING_DOC;
        if (isset($row['referringphysician']) && strlen($row['referringphysician']))
            print "$CUSTOMIZE_REFERRING_DOC: <b>" . str_replace("^", " ", $row['referringphysician']) . "</b><br>";
        if (isset($row['description']) && strlen($row['description']))
            print pacsone_gettext("Study Description:") . " <b>" . $row['description'] . "</b><br>";
        global $CUSTOMIZE_READING_DOC;
        if (isset($row['readingphysician']) && strlen($row['readingphysician']))
            print "$CUSTOMIZE_READING_DOC: <b>" . str_replace("^", " ", $row['readingphysician']) . "</b><br>";
        if (isset($row['admittingdiagnoses']) && strlen($row['admittingdiagnoses']))
            print pacsone_gettext("Admitting Diagnoses:") . " <b>" . str_replace("^", " ", $row['admittingdiagnoses']) . "</b><br>";
        if (isset($row['interpretationauthor']) && strlen($row['interpretationauthor']))
            print pacsone_gettext("Interpretation Author:") . " <b>" . str_replace("^", " ", $row['interpretationauthor']) . "</b><br>";
    }
}

function viewNotesForm(&$username, &$patientId, &$uid)
{
    include_once "checkUncheck.js";
    require_once "display.php";
    global $dbcon;
    global $PRODUCT;
    global $BGCOLOR;
    $modify = $dbcon->hasaccess("modifydata", $username);
    $download = $dbcon->hasaccess("download", $username);
    print "<html>\n";
    print "<head><title>$PRODUCT - ";
    print pacsone_gettext("Study Notes");
    print "</title></head>\n";
    print "<body>\n";
    require_once 'header.php';
    print "<table class=\"table table-bordered\" width=100% border=0 cellspacing=5 cellpadding=0>\n";
    print "<tr valign=top>";
    print "<td width=\"20%\">";
    displayStudyInfo($patientId, $uid);
    print "</td>";

    //print "<td width=1 bgcolor=$BGCOLOR><img src=blank.gif width=1 height=1></td>";
    
    print "<td>";
    print "<form method='POST' action='studyNotes.php'>\n";
    print "<input type='hidden' name='studynoteaction'>\n";
    print "<input type=hidden name='uid' value='$uid'>\n";
    $count = 0;
    $query = "select * from studynotes where uuid=? order by created asc";
    $bindList = array($uid);
    $result = $dbcon->preparedStmt($query, $bindList);

    if ($result) {
        $rows = array();
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = $row;
        }
        $count = displayNotes("studynotes", $rows, $username, "studyNotes.php", 1, 0);
    }

    print "<p><table width=60% border=0 cellspacing=0 cellpadding=5>\n";
    print "<tr>\n";
    if ($count) {
        $check = pacsone_gettext("Check All");
        $uncheck = pacsone_gettext("Uncheck All");
        print "<td><input class=\"btn btn-primary\" type=button value='$check' name='checkUncheck' onClick='checkAll(this.form,\"entry\", \"$check\", \"$uncheck\")'>&nbsp;\n";
    }

    print "<input type=submit class=\"btn btn-primary\" value='";
    print pacsone_gettext("Add");
    print "' name='action' title='";
    print pacsone_gettext("Add New Study Note");
    print "' onclick='switchText(this.form,\"studynoteaction\",\"Add\")'>&nbsp;\n";
    if ($count && $modify) {
        print "<input class=\"btn btn-primary\" type=submit value='";
        print pacsone_gettext("Delete");
        print "' name='action' title='";
        print pacsone_gettext("Delete Selected Study Notes");
        print "' onclick='switchText(this.form,\"studynoteaction\",\"Delete\");return confirm(\"";
        print pacsone_gettext("Are you sure?");
        print "\");'>&nbsp;\n";
        // add buttons to approve or disapprove reports
        print "<input class=\"btn btn-primary\" type=submit value='";
        print pacsone_gettext("Report OK");
        print "' name='action' title='";
        print pacsone_gettext("Report Verified As OK");
        print "' onclick='switchText(this.form,\"studynoteaction\",\"Approve\");'>";
        print "&nbsp;\n";
        print "<input class=\"btn btn-primary\" type=submit value='";
        print pacsone_gettext("Report Needs Attention");
        print "' name='action' title='";
        print pacsone_gettext("Report Needs To Be Verified Again");
        print "' onclick='switchText(this.form,\"studynoteaction\",\"Disapprove\");'>";
        print "&nbsp;\n";
    }
    if ($count && $download) {
        print "<input class=\"btn btn-primary\" type=submit value='";
        print pacsone_gettext("Download");
        print "' name='action' title='";
        print pacsone_gettext("Download Selected Study Notes");
        print "' onclick='switchText(this.form,\"studynoteaction\",\"Download\");'>";
        print "&nbsp;\n";
    }
    print "</tr>\n";
    if ($count) {
        $result = $dbcon->query("select * from smtp");
        if ($result && $result->rowCount()) {
            print "<tr><td>";
            print "<input type=submit value='";
            print pacsone_gettext("Email Study Notes To");
            print "' name='action' title='";
            print pacsone_gettext("Email Selected Study Notes To Specified Email Address (es)");
            print "' onclick='switchText(this.form,\"studynoteaction\",\"Email\");'>";
            print "&nbsp;<input type='text' name='emailaddr' size=64 maxlength=256>";
            print "</td></tr>";
        }
    }
    print "</table>\n";
    print "</form>";
    print "</td></tr>";
    print "</table>";
    require_once 'footer.php';
    print "</body>\n";
    print "</html>\n";

    /*
    include_once "checkUncheck.js";
    require_once "display.php";
    global $dbcon;
    global $PRODUCT;
    global $BGCOLOR;
    $modify = $dbcon->hasaccess("modifydata", $username);
    $download = $dbcon->hasaccess("download", $username);
    print "<html>\n";
    print "<head><title>$PRODUCT - ";
    print pacsone_gettext("Study Notes");
    print "</title></head>\n";
    print "<body>\n";
    require_once 'header.php';
    print "<table class=\"table\" width=100% border=0 cellspacing=5 cellpadding=0>\n";
    print "<tr valign=top>";
    print "<td class=notes>";
    displayStudyInfo($patientId, $uid);
    print "</td>";
    print "<td width=1 bgcolor=$BGCOLOR><img src=blank.gif width=1 height=1></td>";
    print "<td>";
    print "<form method='POST' action='studyNotes.php'>\n";
    print "<input type='hidden' name='studynoteaction'>\n";
    print "<input type=hidden name='uid' value='$uid'>\n";
    $count = 0;
    $query = "select * from studynotes where uuid=? order by created asc";
    $bindList = array($uid);
    $result = $dbcon->preparedStmt($query, $bindList);
    if ($result) {
        $rows = array();
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = $row;
        }
        $count = displayNotes("studynotes", $rows, $username, "studyNotes.php", 1, 0);
    }
    print "<p><table width=60% border=0 cellspacing=0 cellpadding=5>\n";
    print "<tr>\n";
    if ($count) {
        $check = pacsone_gettext("Check All");
        $uncheck = pacsone_gettext("Uncheck All");
        print "<td><input type=button value='$check' name='checkUncheck' onClick='checkAll(this.form,\"entry\", \"$check\", \"$uncheck\")'>&nbsp;\n";
    }
    print "<input type=submit value='";
    print pacsone_gettext("Add");
    print "' name='action' title='";
    print pacsone_gettext("Add New Study Note");
    print "' onclick='switchText(this.form,\"studynoteaction\",\"Add\")'>&nbsp;\n";
    if ($count && $modify) {
        print "<input type=submit value='";
        print pacsone_gettext("Delete");
        print "' name='action' title='";
        print pacsone_gettext("Delete Selected Study Notes");
        print "' onclick='switchText(this.form,\"studynoteaction\",\"Delete\");return confirm(\"";
        print pacsone_gettext("Are you sure?");
        print "\");'>&nbsp;\n";
        // add buttons to approve or disapprove reports
        print "<input type=submit value='";
        print pacsone_gettext("Report OK");
        print "' name='action' title='";
        print pacsone_gettext("Report Verified As OK");
        print "' onclick='switchText(this.form,\"studynoteaction\",\"Approve\");'>";
        print "&nbsp;\n";
        print "<input type=submit value='";
        print pacsone_gettext("Report Needs Attention");
        print "' name='action' title='";
        print pacsone_gettext("Report Needs To Be Verified Again");
        print "' onclick='switchText(this.form,\"studynoteaction\",\"Disapprove\");'>";
        print "&nbsp;\n";
    }
    if ($count && $download) {
        print "<input type=submit value='";
        print pacsone_gettext("Download");
        print "' name='action' title='";
        print pacsone_gettext("Download Selected Study Notes");
        print "' onclick='switchText(this.form,\"studynoteaction\",\"Download\");'>";
        print "&nbsp;\n";
    }
    print "</tr>\n";
    if ($count) {
        $result = $dbcon->query("select * from smtp");
        if ($result && $result->rowCount()) {
            print "<tr><td>";
            print "<input type=submit value='";
            print pacsone_gettext("Email Study Notes To");
            print "' name='action' title='";
            print pacsone_gettext("Email Selected Study Notes To Specified Email Address (es)");
            print "' onclick='switchText(this.form,\"studynoteaction\",\"Email\");'>";
            print "&nbsp;<input type='text' name='emailaddr' size=64 maxlength=256>";
            print "</td></tr>";
        }
    }
    print "</table>\n";
    print "</form>";
    print "</td></tr>";
    print "</table>";
    require_once 'footer.php';
    print "</body>\n";
    print "</html>\n";
    */
}

?>
