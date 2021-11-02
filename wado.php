<?php
//
// wado.php
//
// Web Access to DICOM Persistent Objects (WADO)
//
// CopyRight (c) 2012-2020 RainbowFish Software
//
require_once 'constants.php';

// validate request parameters
if ((!isset($_REQUEST['requestType']) || strcasecmp($_REQUEST['requestType'], "WADO")) ||
    (!isset($_REQUEST['objectUID']) || !strlen($_REQUEST['objectUID'])) ||
    (!isset($_REQUEST['seriesUID']) || !strlen($_REQUEST['seriesUID'])))
{
    print "<h2><font color=red>";
    print pacsone_gettext("Invalid WADO Request Parameters");
    print "<br>";
    print_r($_REQUEST);
    print "</font></h2>";
    exit;
}
$username = "";
$password = "";
$aetitle = "";
// file to bypass authentication if present
$dir = dirname($_SERVER['SCRIPT_FILENAME']);
$imagedir = $dir . "/images";
$dir = substr($dir, 0, strlen($dir) - 3);
global $WADO_BYPASS_FILE;
$bypass = $dir . $WADO_BYPASS_FILE;
if (!file_exists($bypass)) {
    $ok = true;
    // username/password supplied from URL take precedence over HTTP authentication
    if (isset($_REQUEST['username']) && strlen($_REQUEST['username']))
        $username = urldecode($_REQUEST['username']);
    if (isset($_REQUEST['password']) && strlen($_REQUEST['password']))
        $password = urldecode($_REQUEST['password']);
    if (!strlen($username) && !strlen($password)) {
        if (isset($_SERVER['PHP_AUTH_USER'])) {
            $username = $_SERVER['PHP_AUTH_USER'];
            $password = $_SERVER['PHP_AUTH_PW'];
        } else if (isset($_REQUEST['sessionid'])) {
            $sid = urldecode($_REQUEST['sessionid']);
            session_id($sid);
            session_start();
            require_once 'authenticatedSession.php';
            $authenticated = new DecryptedSession();
            $username = $authenticated->getUsername();
            $password = $authenticated->getPassword();
            if (isset($_SESSION['aetitle']))
                $aetitle = $_SESSION['aetitle'];
        } else
            $ok = false;
    }
    if (!$ok) {
        header('WWW-Authenticate: Basic realm="PacsOne Server WADO"');
        header('HTTP/1.0 401 Unauthorized');
        print "<h2><font color=red>";
        print pacsone_gettext("A valid username/password must be specified in order to access this page");
        print "</font></h2>";
        exit;
    }
} else {
    // if WADO authentication is bypassed, then a fixed username/password must be supplied here
    $username = "wado";
    $password = "wado";
    $entries = parse_ini_file($bypass);
    if (function_exists("array_change_key_case"))
        $entries = array_change_key_case($entries);
    if (count($entries) && isset($entries["username"]) && isset($entries["password"])) {
        $username = base64_decode($entries["username"]);
        $password = base64_decode($entries["password"]);
    }
}
require_once "database.php";
$hostname = "localhost";
$database = "";
if (isset($_REQUEST['aetitle']) && strlen($_REQUEST['aetitle']))
    $aetitle = urldecode($_REQUEST['aetitle']);
if (strlen($aetitle)) {
    $hostname = getDatabaseHost($aetitle);
    $database = getDatabaseName($aetitle);
} else {
    // just use the first database from the parsed INI configuration files
    $oracle = false;
    $databases = getDatabaseNames($oracle);
    if (count($databases)) {
        $first = reset($databases);
        $database = $first['Database'];
        if (isset($first['DatabaseHost']))
            $hostname = $first['DatabaseHost'];
    }
}
if (!strlen($database)) {
    print "<h2><font color=red>";
    print pacsone_gettext("Failed to Resolve Database Name");
    print "<br>";
    print_r($_REQUEST);
    print "</font></h2>";
    exit;
}
$dbcon = new MyDatabase($hostname, $database, $username, $password);
if (!$dbcon->connection) {
    header('WWW-Authenticate: Basic realm="PacsOne Server WADO"');
    header('HTTP/1.0 401 Unauthorized');
    print "<h2><font color=red>";
    printf(pacsone_gettext("Failed to Connect to Database [%s] as Username: [%s]"), $database, $username);
    print "</font></h2>";
    exit;
}
$uid = $_REQUEST['objectUID'];
$contentType = "image/jpeg";
if (isset($_REQUEST['contentType']) && strlen($_REQUEST['contentType'])) {
    if (!strcasecmp($_REQUEST['contentType'], "application/dicom"))
        $contentType = "application/dicom";
}
$file = "";
if (!strcasecmp($contentType, "application/dicom")) {
    if (!isUidValid($uid)) {
        $error = pacsone_gettext("Invalid WADO Object UID");
        print "<h2><font color=red>$error</font></h2>";
        exit();
    }
    // retrieve Dicom image
    $query = "select path from image where uuid=?";
    $bindList = array($uid);
    $result = $dbcon->preparedStmt($query, $bindList);
    if ($result && ($row = $result->fetch(PDO::FETCH_NUM)))
        $file = $row[0];
} else {
    // retrieve converted JPEG/GIF image
    $result = $dbcon->query("select imagedir from config");
    if ($result && $result->rowCount()) {
        $dir = $result->fetchColumn();
        if (strlen($dir) && file_exists($dir))
            $imagedir = $dir;
    }
    // change to Unix-style path
    $imagedir = str_replace("\\", "/", $imagedir);
    // append '/' at the end if not so already
    if (strcmp(substr($imagedir, strlen($imagedir)-1, 1), "/"))
        $imagedir .= "/";
    $file = $imagedir . $uid . ".jpg";
    if (!file_exists($file)) {
        $file = $imagedir . $uid . ".gif";
        if (file_exists($file))
            $contentType = "image/gif";
    }
}
if (file_exists($file)) {
    $size = filesize($file);
    if ($size > 128) {
        header("Content-Type: $contentType");
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header("Content-Length: $size");
        $fp = fopen($file, 'rb');
        fpassthru($fp);
        fclose($fp);
    }
} else {
    header('HTTP/1.0 500 Internal Server Error');
    print "<h2><font color=red>";
    printf(pacsone_gettext("File: [%s] Not Found"), $file);
    print "</font></h2>";
    exit;
}

?>
