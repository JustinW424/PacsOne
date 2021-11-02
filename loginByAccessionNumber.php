<?php
//
// loginByAccessionNumber.php
//
// Module for accessing Dicom study directly via its Accession Number
//
// CopyRight (c) 2011-2020 RainbowFish Software
//
if (!session_id())
    session_start();

require_once 'database.php';
include_once 'sharedData.php';

// user authentication
$hostname = isset($_REQUEST["hostname"])? urldecode($_REQUEST["hostname"]) : "localhost";
$database = $_REQUEST['database'];
$username = $_REQUEST['username'];
$password = $_REQUEST['password'];
$dbcon = new MyDatabase($hostname, $database, $username, $password);
$accession = $_REQUEST['AccessionNumber'];
if (preg_match("/[';\"]/", $accession)) {
    $error = pacsone_gettext("Invalid Accession Number");
    print "<h2><font color=red>$error</font></h2>";
    exit();
}
$error = "";
if ($dbcon->connection) {
    $query = "select uuid,patientid from study where accessionnum=?";
    $bindList = array($accession);
    $result = $dbcon->preparedStmt($query, $bindList);
    if ($result && ($row = $result->fetch(PDO::FETCH_NUM))) {
        $uid = $row[0];
        $patientid = $row[1];
        $viewAccess = $dbcon->hasaccess("viewprivate", $username);
        if ($viewAccess || $dbcon->accessStudy($uid, $username)) {
            $allow = true;
            if (!$dbcon->isAdministrator($username)) {
	            // save full name of user
	            $query = "SELECT firstname,lastname FROM privilege WHERE username=?";
                $bindList = array($username);
                $result = $dbcon->preparedStmt($query, $bindList);
                if ($result && $result->rowCount()) {
	                $row = $result->fetch(PDO::FETCH_NUM);
                } else {
                    $allow = false;
                }
            }
            if ($allow) {
                // save login information
                $_SESSION['authenticatedHost'] = $hostname;
                $_SESSION['authenticatedDatabase'] = $database;
                require_once 'authenticatedSession.php';
                $authenticated = new EncryptedSession($username, $password);
                // save encrypted username/password
                $_SESSION['authenticatedUser'] = $authenticated->getUsername();
                $_SESSION['authenticatedPassword'] = $authenticated->getPassword();
	            if (isset($row) && isset($row[0])) {
		            $fullname = strcmp($row[0], "_GROUP")? $row[0] : "";
		            if (isset($row[1]))
			            $fullname .= " " . $row[1];
		            $_SESSION['fullname'] = $fullname;
	            }
            } else {
                // do not allow login using reserved username
                $error = sprintf(pacsone_gettext("Username: <u>%s</u> is reserved"), $username);
                $error .= pacsone_gettext("<p>Please login using a different username/password.<p>");
            }
            // redirect browser to that study page
            $url = "Location: series.php?patientId=" . urlencode($patientid) . "&studyId=$uid";
            header($url);
            exit();
        } else {
            $error = sprintf(pacsone_gettext("Username: <u>%s</u> does not have sufficient privilege to access this study with Accession Number [%s]"), $username, $accession);
        }
    } else {
        $error = sprintf(pacsone_gettext("Failed to find any study with Accession Number: [%s]"), $accession);
    }
} else {
    $error = sprintf(pacsone_gettext("Failed to login to <u>%s</u> database. Please check your username/password and the Accession Number and make sure they are valid."), $database);
}
if (strlen($error)) {
    global $PRODUCT;
    print "<html>\n";
    print "<head><title>";
    print pacsone_gettext("$PRODUCT - Error");
    print "</title></head>\n";
    print "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=iso-8859-1\"></head>\n";
    print "<body leftmargin=\"0\" topmargin=\"0\" bgcolor=\"#cccccc\">\n";
    print "<h2><font color=red>$error</font></h2>";
    print "</body>\n";
    print "</html>\n";
}
session_destroy();

?>
