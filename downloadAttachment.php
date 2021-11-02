<?php 
//
// downloadAttachment.php
//
// Module for downloading attachments
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();
error_reporting(E_ERROR);

require_once "locale.php";
include_once 'database.php';
include_once 'security.php';
include_once 'utils.php';

$seq = urldecode($_REQUEST['seq']);
$id = urldecode($_REQUEST['id']);
$uid = urldecode($_REQUEST['uid']);
if (!isUidValid($uid) || !is_numeric($seq) || !is_numeric($id)) {
    print "<p><font color=red>";
    printf(pacsone_gettext("Invalid URL Input: Seq = %d, Id = %d, Uid = %s"), $seq, $id, $uid);
    print "</font>";
    exit();
}
$dbcon = new MyConnection();
$query = "select * from attachment where seq=? and id=? and uuid=?";
$bindList = array($seq, $id, $uid);
$attach = $dbcon->preparedStmt($query, $bindList);
if ($attach && $attach->rowCount()) {
    $row = $attach->fetch(PDO::FETCH_ASSOC);
    $path = $row['path'];
    $mimetype = $row['mimetype'];
    $file = basename($path);
    // rebuild the upload file from blob
    $data = $row['data'];
    // otherwise read from files uploaded from previous versions
    if (!strlen($data) && file_exists($path)) {
        $fp = fopen($path, "rb");
        $data = fread($fp, filesize($path));
        fclose($fp);
    }
    session_write_close();
    ob_end_clean();
    set_time_limit(0);

    //filenames in IE containing dots will screw up the
    //filename unless we add this

    if (strstr($_SERVER['HTTP_USER_AGENT'], "MSIE"))
        $file = preg_replace('/\./', '%2e', $file, substr_count($file, '.') - 1);

    header("Cache-Control: ");
    header("Pragma: ");
    //header("Content-Type: application/octet-stream");
    header('Content-Disposition: attachment; filename="'.$file.'"');
    header("Content-Type: $mimetype");
    header("Content-Length: " .(string)(strlen($data)) );
    header("Content-Transfer-Encoding: binary\n");

    $buffer = 1024 * 8;
    while( (strlen($data)) && (connection_status()==0) ){
        $packet = substr($data, 0, $buffer);
        echo $packet;
        $data = substr($data, strlen($packet));
        flush();
    }
} else {
    print "<p><font color=red>" . pacsone_gettext("Attachment does not exist!") . "</font>";
}
exit();
?> 
