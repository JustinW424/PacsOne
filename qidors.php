<?php
//
// qidors.php
//
// Implementation for Query based on ID for Dicom Objects by RESTful services (QIDO-RS)
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
global $QIDORS_BYPASS_FILE;
$bypass = $dir . $QIDORS_BYPASS_FILE;
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
        header('WWW-Authenticate: Basic realm="PacsOne Server QIDO-RS"');
        header('HTTP/1.0 401 Unauthorized');
        print "<h2><font color=red>";
        print pacsone_gettext("A valid username/password must be specified in order to access this page");
        print "</font></h2>";
        exit;
    }
} else {
    // if QIDO-RS authentication is bypassed, then a fixed username/password must be supplied here
    $username = "qido";
    $password = "qido";
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
$dbcon = new MyDatabase($hostname, $database, $username, $password, $aetitle, "utf8");
if (!$dbcon->connection) {
    header('HTTP/1.0 401 Unauthorized');
    print "<h2><font color=red>";
    printf(pacsone_gettext("Failed to Connect to Database [%s] as Username: [%s]"), $database, $username);
    print "</font></h2>";
    exit;
}
// check if username has QIDO-RS access enabled
if (!$dbcon->hasaccess("qidors", $username)) {
    header('HTTP/1.0 403 Forbidden');
    print "<h2><font color=red>";
    printf(pacsone_gettext("DicomWeb QIDO-RS Access Denied for Username: [%s]"), $username);
    print "</font></h2>";
    exit;
}
$path = isset($_SERVER['PATH_INFO'])? $_SERVER['PATH_INFO'] : "";
$query = array();
parse_str($_SERVER['QUERY_STRING'], $query);
$json = true;
$accept = $_SERVER['HTTP_ACCEPT'];
if (!stripos($accept, "dicom+json") && stripos($accept, "dicom+xml"))
    $json = false;  // return seach results in XML instead
$queries = array();
$fields = array();
foreach ($query as $key => $value) {
    if (strcasecmp($key, "includefield"))
        $queries[strtoupper($key)] = $value;
    else {
        if (strpos($value, ",") == false)
            $fields[] = strtoupper($value);
        else {
            $tokens = explode(",", $value);
            foreach ($tokens as $token)
                $fields[] = strtoupper($token);
        }
    }
}
$matches = null;
if (stripos($path, "instances"))
    $matches = new DicomWebInstanceResult($dbcon, $path, $queries, $fields);
else if (stripos($path, "series"))
    $matches = new DicomWebSeriesResult($dbcon, $path, $queries, $fields);
else if (stripos($path, "studies"))
    $matches = new DicomWebStudyResult($dbcon, $path, $queries, $fields);
else {
    header('HTTP/1.0 400 Bad Request');
    print "<h2><font color=red>";
    printf(pacsone_gettext("Invalid Query Parameters: [%s]"), $_SERVER['REQUEST_URI']);
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
$what = $path . "?" . $_SERVER['QUERY_STRING'];
$dbcon->logJournal($username, "QIDO-RS", "Query", $what);
if ($json)
    $matches->sendJson();
else
    $matches->sendXml();

?>
