<?php
//
// systemService.php
//
// Module for system services
//
// CopyRight (c) 2013-2020 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once 'database.php';
include_once 'sharedData.php';

// main
global $PRODUCT;
$dbcon = new MyConnection();
if (isset($_POST['action']))
   	$action = $_POST['actionvalue'];
$result = NULL;
if (isset($action) && strcasecmp($action, "Restart") == 0) {
    $result = restartService();
}
if (isset($result)) {       // display results
    if (empty($result)) {   // success
        // check latest logs for verification
        $title = pacsone_gettext("Today's Log");
        $url = "tools.php?&page=" . urlencode($title) . "#end";
        header('Location: tools.php?page=' . $url);
        exit();
    }
    else {                  // error
        print "<html>\n";
        print "<head><title>$PRODUCT - ";
        print pacsone_gettext("Restart Service Error");
        print "</title></head>\n";
        print "<body>\n";
        require_once 'header.php';
        print $result;
		require_once 'footer.php';
        print "</body>\n";
        print "</html>\n";
    }
}

function restartService()
{
	$result = "";
    global $dbcon;
	$query = "insert into dbjob (username,aetitle,type,class,uuid,submittime,status) values(";
    $query .= "?,'_','restart','System','";
    $bindList = array($dbcon->getAdminUsername());
    $query .= getDateTimeStamp();
    $query .= "',";
    $query .= $dbcon->useOracle? "SYSDATE" : "NOW()";
    $query .= ",'submitted')";
	if (!$dbcon->preparedStmt($query, $bindList)) {
		$error = sprintf(pacsone_gettext("Database Error: %s"), $dbcon->getError());
		$result = pacsone_gettext("Failed to restart service: ");
		$result .= $error;
	}
    return $result;
}

?>
