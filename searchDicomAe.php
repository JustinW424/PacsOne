<?php
//
// searchDicomAe.php
//
// Module for searching defined Dicom AEs
//
// CopyRight (c) 2015-2020 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once 'security.php';
include_once 'display.php';

global $PRODUCT;
print "<html>";
print "<head>";
print "<title>$PRODUCT - ";
print pacsone_gettext("Dicom AE Search Results:");
print "</title></head>";
print "<body>";

require_once 'header.php';
include_once 'utils.php';

$aetitle = isset($_REQUEST['aetitle'])? urldecode($_REQUEST['aetitle']) : "";
$description = isset($_REQUEST['description'])?urldecode($_REQUEST['description']) : "";
$hostname = isset($_REQUEST['hostname'])? urldecode($_REQUEST['hostname']) : "";
$ipaddr = isset($_REQUEST['ipaddr'])? urldecode($_REQUEST['ipaddr']) : "";
$port = isset($_REQUEST['port'])? urldecode($_REQUEST['port']) : "";

$dbcon = new MyConnection();
$error = searchDicomAe($aetitle, $description, $hostname, $ipaddr, $port);
if ($error) {
    print "<font color=red>$error</font>";
}
require_once 'footer.php';
print "</body>";
print "</html>";

function searchDicomAe($aetitle, $description, $hostname, $ipaddr, $port)
{
    global $dbcon;
    // automatically append wild-card character
    if (strlen($aetitle) && isset($_REQUEST['wildtitle']))
        $aetitle .= "*";
    if (strlen($description) && isset($_REQUEST['wilddesc']))
        $description .= "*";
    if (strlen($hostname) && isset($_REQUEST['wildhost']))
        $hostname .= "*";
    if (strlen($ipaddr) && isset($_REQUEST['wildip']))
        $ipaddr .= "*";
    if (strlen($port) && isset($_REQUEST['wildport']))
        $port .= "*";
	$query = "SELECT * from applentity where ";
    $key = "";
    $value = "";
    $bindList = array();
    // build query string based on form input
    if (strlen($aetitle)) {
        if (strlen($key))
            $key .= " AND ";
        $key .= "title" . preparedStmtWildcard($aetitle, $value);
        $bindList[] = $value;
    }
    if (strlen($description)) {
        if (strlen($key))
            $key .= " AND ";
        $key .= "description" . preparedStmtWildcard($description, $value);
        $bindList[] = $value;
    }
    if (strlen($hostname)) {
        if (strlen($key))
            $key .= " AND ";
        $key .= "hostname" . preparedStmtWildcard($hostname, $value);
        $bindList[] = $value;
    }
    if (strlen($ipaddr)) {
        if (strlen($key))
            $key .= " AND ";
        $key .= "ipaddr" . preparedStmtWildcard($ipaddr, $value);
        $bindList[] = $value;
    }
    if (strlen($port)) {
        if (strlen($key))
            $key .= " AND ";
        $key .= ($dbcon->useOracle? "TO_CHAR(port)" : "CONCAT(port)") . preparedStmtWildcard($port, $value);
        $bindList[] = $value;
    }
    if (strlen($key) == 0) {
        $error = "<br>";
        $title = pacsone_gettext("Search Dicom AE");
        $url = urlencode($title);
        $error .= sprintf(pacsone_gettext("You must enter a criteria for <a href='search.php?page=$url'>%s</a>"), $title);
        return $error;
    }
    $query .= $key;
    if (count($bindList))
        $result = $dbcon->preparedStmt($query, $bindList);
    else
        $result = $dbcon->query($query);
    if ($result) {
        $num_rows = $result->rowCount();
        $preface = sprintf(pacsone_gettext("%d matching %s found."),
            $num_rows,
            ($num_rows > 1)? pacsone_gettext("Dicom AEs") : pacsone_gettext("Dicom AE"));
        displayApplEntity($result, $preface);
    } else {
        $error = "<br>";
        $error .= sprintf(pacsone_gettext("Error running SQL query: [%s]"), $query);
        $error .= " - " . $dbcon->getError();
    }
    return $error;
}

?>
