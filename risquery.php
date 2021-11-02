<?php
//
// risquery.php
//
// Module for serving queries from RIS
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
function isIpAddress($ip_addr)
{
    $num="(\\d|[1-9]\\d|1\\d\\d|2[0-4]\\d|25[0-5])";
    return preg_match("/^$num\\.$num\\.$num\\.$num$/", $ip_addr);
}    

include_once 'database.php';

$url = parse_url($_SERVER['HTTP_REFERER']);
$refer = $url['host'];

$hostname = "www.mriconsultants.com";
$ipaddr = "207.38.23.8";


// verify hostname or IP address

// if (!isIpAddress($refer) && strcasecmp($hostname, $refer))
//    die('<h2><font color=red>Unauthorized Hostname!</font></h2>');

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
if (!isset($_REQUEST['query']))
    die('<h2><font color=red>A Valid Query Must Be Entered!</font></h2>');
if (!isset($_REQUEST['database']))
    die('<h2><font color=red>A Valid Database Must Be Entered!</font></h2>');
// user authentication
$database = $_REQUEST['database'];
$username = $_REQUEST['username'];
$password = $_REQUEST['password'];
$dbcon = new MyDatabase("localhost", $database, $username, $password);
global $STUDY_SEARCH_COLUMNS;
$key = urldecode($_REQUEST['key']);
if (!in_array(strtolower($key), $STUDY_SEARCH_COLUMNS))
    die('<h2><font color=red>Invalid Study Search Key!</font></h2>');
$value = urldecode($_REQUEST['value']);
$query = "select uuid from study where $key LIKE ?";
$bindList = array($value . "%");
$result = $dbcon->preparedStmt($query, $bindList);
print $result->rowCount()? "Y" : "N";

?>
