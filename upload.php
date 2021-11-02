<?php
//
// upload.php
//
// Module for uploading files
//
// CopyRight (c) 2005-2020 RainbowFish Software
//
require_once 'locale.php';
include_once "sharedData.php";

function getMimeType(&$file)
{
    global $MIME_TBL;
    $type = "application/octet-stream";  // default MIME type
    $parts = pathinfo($file);
    if (isset($parts['extension'])) {
        $ext = strtoupper($parts['extension']);
        if (isset($MIME_TBL[$ext]))
            $type = $MIME_TBL[$ext];
    }
    return $type;
}

function uploadCheck(&$file)
{
    global $MIME_TBL;
    $parts = pathinfo($file);
    if (isset($parts['extension'])) {
        $ext = strtoupper($parts['extension']);
        return (isset($MIME_TBL[$ext]));
    }
    return false;
}

function uploadAttachments($username, $id)
{
    $ret = "";
    global $dbcon;
    // check whether to store attachments as blob in table or regular files
    $uploaddir = false;
    $result = $dbcon->query("select attachment from config");
    if ($result && ($row = $result->fetch(PDO::FETCH_NUM))) {
        if (strcasecmp($row[0], "directory") == 0)
            $uploaddir = true;
    }
    $attachments = $_SESSION['attachments'];
    foreach ($attachments as $att) {
        $destfile = $att['destfile'];
        $size = filesize($destfile);
        $fp = fopen($destfile, "rb");
        $data = fread($fp, $size);
        fclose($fp);
        // use the browser supplied mime type if available
        if (isset($att['type']) && strlen($att['type']))
            $mimetype = $att['type'];
        else
            $mimetype = getMimeType($att['name']);
        $blobname = "";
        $query = "insert into attachment (id,path,totalsize,mimetype,data,uuid) values(?,?,?,?,?,?)";
        $bindList = array($id, $destfile, $size, $mimetype, (!$uploaddir? $data : ''), $_POST['uid']);
        $typeList = array(PDO::PARAM_INT, PDO::PARAM_STR, PDO::PARAM_INT, PDO::PARAM_STR, PDO::PARAM_LOB, PDO::PARAM_STR);
        $ok = $dbcon->preparedStmtWithType($query, $bindList, $typeList);
        if (!$ok)
            $ret = sprintf(pacsone_gettext("Failed to add attachment: %s, Database error: %s"), $destfile, $dbcon->getError());
        else {
            $seq = $dbcon->insert_id("attachment");
            $removeFile = false;
            if (!$uploaddir)
                $removeFile = true;
            // check if this is a PDF document to be converted to Dicom
            // Encapsulated Document object
            global $MIME_TBL;
            if (!strcasecmp($mimetype, $MIME_TBL["PDF"]) &&
                 isset($_POST['pdf2dcm']) && $_POST['pdf2dcm']) {
                $class = $_POST['class'];
                $query = "insert into dbjob (username,aetitle,type,class,uuid,priority,submittime,status) ";
                $priority = $removeFile? 1 : 0;
                $query .= "values(?,'_','pdf2dcm',?,'$seq',$priority,";
                $bindList = array($username, $class);
                $query .= $dbcon->useOracle? "SYSDATE," : "NOW(),";
                $query .= "'submitted')";
                if (!$dbcon->preparedStmt($query, $bindList)) {
                    print "<h3><font color=red>";
                    print "Failed to run query: [$query], error = " . $dbcon->getError();
                    print "</font></h3>";
                    exit();
                }
                // uploaded file will be removed after the conversion
                $removeFile = false;
            }
            if ($removeFile)
                unlink($destfile);
            // log activity to system journal
            $dbcon->logJournal($username, "Upload", "Attachment", $destfile);
        }
    }
    return $ret;
}

function getUploadError($errno)
{
    $errTbl = array(
        1 => pacsone_gettext("The uploaded file exceeds the <b>upload_max_filesize</b> directive in php.ini"),
        2 => pacsone_gettext("The uploaded file exceeds the <b>MAX_FILE_SIZE</b> directive that was specified in the HTML form"),
        3 => pacsone_gettext("The uploaded file was only partially uploaded"),
        4 => pacsone_gettext("No file was uploaded"),
        6 => pacsone_gettext("Missing a temporary folder"),
        7 => pacsone_gettext("Failed to write file to disk"),
    );
    return isset($errTbl[$errno])? $errTbl[$errno] : pacsone_gettext("N/A");
}

?>
