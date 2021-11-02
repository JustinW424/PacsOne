<?php
//
// wadors.php
//
// Implementation for Web Access for Dicom Objects by RESTful services (WADO-RS)
//
// CopyRight (c) 2017-2018 RainbowFish Software
//
require_once 'dicomweb.php';

$username = "";
$password = "";
$aetitle = "";
// file to bypass authentication if present
$dir = dirname($_SERVER['SCRIPT_FILENAME']);
$dir = substr($dir, 0, strlen($dir) - 3);
global $WADORS_BYPASS_FILE;
$bypass = $dir . $WADORS_BYPASS_FILE;
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
        header('WWW-Authenticate: Basic realm="PacsOne Server WADO-RS"');
        header('HTTP/1.0 401 Unauthorized');
        print "<h2><font color=red>";
        print pacsone_gettext("A valid username/password must be specified in order to access this page");
        print "</font></h2>";
        exit;
    }
} else {
    // if WADO-RS authentication is bypassed, then a fixed username/password must be supplied here
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
    header('HTTP/1.0 400 Bad Request');
    print "<h2><font color=red>";
    print pacsone_gettext("Failed to Resolve Database Name");
    print "<br>";
    print_r($_REQUEST);
    print "</font></h2>";
    exit;
}
$dbcon = new MyDatabase($hostname, $database, $username, $password, $aetitle, "utf8");
if (!$dbcon->connection) {
    header('HTTP/1.0 401 Unauthorized');
    print "<h2><font color=red>";
    printf(pacsone_gettext("Failed to Connect to Database [%s] as Username: [%s]"), $database, $username);
    print "</font></h2>";
    exit;
}
// check if username has WADO-RS access enabled
if (!$dbcon->hasaccess("wadors", $username)) {
    header('HTTP/1.0 403 Forbidden');
    print "<h2><font color=red>";
    printf(pacsone_gettext("DicomWeb WADO-RS Access Denied for Username: [%s]"), $username);
    print "</font></h2>";
    exit;
}
$path = isset($_SERVER['PATH_INFO'])? $_SERVER['PATH_INFO'] : "";
$query = array();
parse_str($_SERVER['QUERY_STRING'], $query);
$json = true;
$accept = isset($_SERVER['HTTP_ACCEPT'])? $_SERVER['HTTP_ACCEPT'] : "";
if (stripos($accept, "dicom+json") == false && stripos($accept, "dicom+xml"))
    $json = false;
/*
if (stripos($accept, "application/dicom") == false &&
    stripos($accept, "application/dicom+json") == false &&
    stripos($accept, "application/json") == false &&
    stripos($accept, "application/xml") == false &&
    stripos($accept, "application/dicom+xml") == false) {
    header('HTTP/1.0 406 Not Acceptable');
    print "<h2><font color=red>";
    printf(pacsone_gettext("Accept Type: [%s] not supported"), $accept);
    print "</font></h2>";
    exit;
}
 */
$matches = null;
if (stripos($path, "/frames/"))         // retrieve frames
    $matches = new DicomWebRetrieveFrames($dbcon, $path, $query);
else if (stripos($path, "instances"))   // retrieve instances
    $matches = new DicomWebRetrieveInstance($dbcon, $path, $query);
else if (stripos($path, "series"))      // retrieve series
    $matches = new DicomWebRetrieveSeries($dbcon, $path, $query);
else if (stripos($path, "studies"))     // retrieve studies
    $matches = new DicomWebRetrieveStudy($dbcon, $path, $query);

if (!isset($matches)) {
    header('HTTP/1.0 400 Bad Request');
    print "<h2><font color=red>";
    printf(pacsone_gettext("Invalid Retrieve Parameters: [%s]"), $_SERVER['REQUEST_URI']);
    print "</font></h2>";
    exit;
}
if ($matches->noMatchFound()) {
    header('HTTP/1.0 204 No Content');
    if ($json) {
        header("Content-Type: application/dicom+json");
        echo json_encode(array());
    } else {
        header("Content-Type: application/dicom+xml");
        echo "";
    }
    exit;
}
// log activity to system journal
$dbcon->logJournal($username, "WADO-RS", "Retrieve", $path);
if (stripos($path, "metadata")) {
    // retrieve metadata
    if (!$json)
        $matches->sendXml();        // return metadata in XML instead
    else
        $matches->sendJson();       // return metadata in JSON
} else if (!stripos($accept, "application/dicom") && stripos($accept, "multipart/related")) {
    $matches->retrieveBulkData();   // retrieve bulk data
} else {
    $matches->retrieve();           // retrieve Dicom instances
}

?>
