<?php
//
// risdirect.php
//
// Module for direct interface from RIS
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
function isIpAddress($ip_addr)
{
    $num="(\\d|[1-9]\\d|1\\d\\d|2[0-4]\\d|25[0-5])";
    return preg_match("/^$num\\.$num\\.$num\\.$num$/", $ip_addr);
}

session_start();

include_once 'database.php';
include_once 'sharedData.php';

$url = isset($_SERVER['HTTP_REFERER'])? parse_url($_SERVER['HTTP_REFERER']) : array();
$refer = isset($url['host'])? $url['host'] : "";

$hostname = "www.mriconsultants.com";
$ipaddr = "207.38.23.8";

// verify hostname or IP address
if ( !isIpAddress($refer) && strcasecmp($hostname, $refer)  )
    die('<h2><font color=red>Unauthorized Hostname!</font></h2>');

if (isIpAddress($refer) && strcasecmp($ipaddr, $refer))
    die('<h2><font color=red>Unauthorized IP Address!</font></h2>');
if (!isset($_REQUEST['key']))
    die('<h2><font color=red>A Study Search Key Must Be Specified!</font></h2>');
if (!isset($_REQUEST['value']))
    die('<h2><font color=red>A Study Search Value Must Be Specified!</font></h2>');
if (!isset($_REQUEST['username']))
    die('<h2><font color=red>A Valid Username Must Be Specified!</font></h2>');
if (!isset($_REQUEST['password']))
    die('<h2><font color=red>A Valid Password Must Be Specified!</font></h2>');
if (!isset($_REQUEST['database']))
    die('<h2><font color=red>A Valid Database Must Be Specified!</font></h2>');
// user authentication
$hostname = isset($_REQUEST['hostname'])? $_REQUEST['hostname'] : "localhost";
$database = $_REQUEST['database'];
$username = $_REQUEST['username'];
$password = $_REQUEST['password'];
$dbcon = new MyDatabase("localhost", $database, $username, $password);
global $STUDY_SEARCH_COLUMNS;
$key = urldecode($_REQUEST['key']);
if (!in_array(strtolower($key), $STUDY_SEARCH_COLUMNS))
    die('<h2><font color=red>Invalid Study Search Key!</font></h2>');
$value = urldecode($_REQUEST['value']);
$query = "select uuid from study where $key LIKE ?;";
$bindList = array($value . "%");
$result = $dbcon->preparedStmt($query, $bindList);
if (!$result)
    die("<h2><font color=red>Error querying $PRODUCT database!</font></h2>");
if (!$result->rowCount())
    die('<h2>No Matching Study Found.</h2>');
$uid = $result->fetchColumn();
// save login information
$_SESSION['authenticatedHost'] = $hostname;
$_SESSION['authenticatedDatabase'] = $database;
require_once 'authenticatedSession.php';
$authenticated = new EncryptedSession($username, $password);
// save encrypted username/password
$_SESSION['authenticatedUser'] = $authenticated->getUsername();
$_SESSION['authenticatedPassword'] = $authenticated->getPassword();

if (isset($_REQUEST['url']) && strlen($_REQUEST['url']))
{
    $url = $_REQUEST['url'];
    header("Location: $url");
}
else
{
    global $PRODUCT;
    print "<html>\n";
    print "<head><title>$PRODUCT - Show Images</title></head>\n";
    print "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=iso-8859-1\"></head>\n";
    print "<body leftmargin=\"0\" topmargin=\"0\" bgcolor=\"#cccccc\">\n";
    require 'header.php';
    include_once 'applet.php';
    $uids = array();
    $resultSeries = $dbcon->query("SELECT uuid FROM series where studyuid='$uid' ORDER BY seriesnumber ASC;");
    while ($seriesUid = $resultSeries->fetchColumn())
    {
        $result = $dbcon->query("SELECT uuid FROM image where seriesuid='$seriesUid' ORDER BY instance ASC;");
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $uids[] = $row[0];
        }
    }
    if (count($uids))
        appletViewer($uids);
    else
        print "<p>No image to display.<br>";
    require 'footer.php';
    print "</body>\n";
    print "</html>\n";
}

?>
