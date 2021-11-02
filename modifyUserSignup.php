<?php
ob_start();
//
// modifyUserSignup.php
//
// Module for approving/rejecting user signup requests
//
// CopyRight (c) 2014-2020 RainbowFish Software
//
if (!session_id())
    session_start();

require_once 'locale.php';
include_once 'database.php';
include_once 'sharedData.php';
include_once 'sharedUserAdmin.php';
include_once 'emailHtml.php';

// main
global $PRODUCT;
$dbcon = new MyConnection();
$database = $dbcon->database;
$username = $dbcon->username;
if (!$dbcon->isAdministrator($username)) {
	print "<font color=red>";
    print pacsone_gettext("You must login as <b>Administrator</b> to manage user accounts");
    print "</font>";
    exit();
}
if (isset($_POST['action']))
   	$action = $_POST['actionvalue'];
else if (isset($_REQUEST['actionvalue']))
   	$action = $_REQUEST['actionvalue'];
$entry = $_POST['entry'];
$result = "";
if (count($entry)) {
    if (isset($action) && !strcasecmp($action, "Approve")) {
        $result = approveRequests($entry);
    } else if (isset($action) && strcasecmp($action, "Reject") == 0) {
        $result = rejectRequests($entry);
    } else if (isset($action) && strcasecmp($action, "Delete") == 0) {
        $result = deleteRequests($entry);
    } else {
        $result = "<h2><font color=red>";
        $result .= pacsone_gettext("User sign-up request must be either approved or rejected!");
        $result .= "</font></h2>";
    }
} else {
    $result = "<h2><font color=red>";
    $result .= pacsone_gettext("No user sign-up request is selected!");
    $result .= "</font></h2>";
}
while (ob_end_clean());

if (!strlen($result)) {
    header('Location: user.php');
    exit();
} else {
    print "<html>\n";
    print "<head><title>$PRODUCT - ";
    print pacsone_gettext("User Sign-Up Error");
    print "</title></head>\n";
    print "<body>\n";
    require_once 'header.php';
    print "<p><p>";
    print "<p>$result";
    print "<p><p>";
    require_once 'footer.php';
    print "</body>\n";
    print "</html>\n";
}

function deleteRequests(&$entry)
{
	$result = "";
    global $dbcon;
	foreach ($entry as $username) {
        $bindList = array($username);
        if (!$dbcon->preparedStmt("delete from usersignup where username=?", $bindList)) {
            $result = sprintf(pacsone_gettext("Failed to delete user sign-up request for %s, error = %s"),
                $username, $dbcon->getError());
            break;
        } else {
            // log activity to system journal
            $dbcon->logJournal($dbcon->getAdminUsername(), "Delete", "User Signup", $username);
        }
	}
    return $result;
}

function approveRequests(&$entry)
{
	$result = "";
    global $dbcon;
    global $PRODUCT;
    // default password expiration date
    $config = $dbcon->query("select passwordexpire from config");
    $expire = $config->fetchColumn();
	foreach ($entry as $username) {
        // make sure the username does no already exist
        if ($dbcon->checkIfUserExists($username)) {
            $result = "<h2><font color=red>";
            $result .= sprintf(pacsone_gettext("Username: <u>%s</u> already exist!"), $username);
            $result .= "</font></h2>";
            return $result;
        }
        $bindList = array($username);
        $signup = $dbcon->preparedStmt("select * from usersignup where username=?", $bindList);
        if ($signup && ($row = $signup->fetch(PDO::FETCH_ASSOC))) {
            $passwd = base64_decode($row["password"]);
            $first = $row["firstname"];
            $last = $row["lastname"];
            $email = $row["email"];
        } else {
            $result = "<h2><font color=red>";
            $result .= sprintf(pacsone_gettext("Invalid User Sign-Up Request for: <u>%s</u>!"), $username);
            $result .= "</font></h2>";
            return $result;
        }
        $error = "";
	    // create username
	    if (!createDbLogin($username, $passwd, $error)) {
            $dbcon->preparedStmt("delete from privilege where username=?", $bindList);
		    $result .= "<h3><font color=red>";
            $result .= sprintf(pacsone_gettext("Error creating Database Username for <u>%s</u>: "),
                           $username);
            $result .= $error;
            $result .= "</font></h3><p>\n";
		    return $result;
	    }
	    // add user privileges
	    if (!addPrivilege($dbcon->database, $username, $error, 0)) {
            $dbcon->preparedStmt("delete from privilege where username=?", $bindList);
		    $result .= "<h3><font color=red>";
            $result .= sprintf(pacsone_gettext("Error setting up privileges for <u>%s</u>: "),
                           $username);
            $result .= $error;
            $result .= "</font></h3><p>\n";
		    return $result;
	    }
        $query = "insert into privilege (username,firstname,lastname,email,expire) values(";
        $query .= "?,?,?,?,";
        $bindList = array($username, $first, $last, $email);
        $query .= $dbcon->useOracle? "SYSDATE+$expire" : "DATE_ADD(NOW(), INTERVAL $expire DAY)";
        $query .= ")";
	    // execute SQL query
	    if (!$dbcon->preparedStmt($query, $bindList)) {
		    $result = "<h3><font color=red>";
            $result .= sprintf(pacsone_gettext("Error adding signed-up user <u>%s</u>: "), $username);
            $result .= "<p>";
            $result .= "[$query], error = ";
            $result .= $dbcon->getError();
            $result .= "</font></h3><p>\n";
		    return $result;
	    }
        // delete sign-up request
        $bindList = array($username);
        $dbcon->preparedStmt("delete from usersignup where username=?", $bindList);
        // log activity to system journal
        $dbcon->logJournal($dbcon->getAdminUsername(), "Approve", "User Signup", $username);
        // send approval email
        $subject = pacsone_gettext("Your Sign-Up Request Has Been Approved");
        $fullname = ucfirst($first) . " " . ucfirst($last);
        $html = sprintf(pacsone_gettext("Dear %s:"), $fullname);
        $html .= "<p>";
        $html .= pacsone_gettext("Congratulations! Your sign-up request has been approved by the Administrator.");
        $html .= "<p><p>";
        $url = $dbcon->getExternalAccessUrl();
        if (strlen($url)) {
            $url .= "login.php";
            $html .= sprintf(pacsone_gettext("You can now <a href=\"%s\">Login</a> to the %s database via any web browser."), $url, $PRODUCT);
        } else {
            $html .= sprintf(pacsone_gettext("You can now Login to the %s database via any web browser."), $PRODUCT);
        }
        emailHtml($email, $subject, $html);
    }
    return $result;
}

function rejectRequests(&$entry)
{
	$result = "";
    global $dbcon;
	foreach ($entry as $username) {
        $bindList = array($username);
        $signup = $dbcon->preparedStmt("select * from usersignup where username=?", $bindList);
        if ($signup && ($row = $signup->fetch(PDO::FETCH_ASSOC))) {
            $first = $row["firstname"];
            $last = $row["lastname"];
            $email = $row["email"];
        } else {
            continue;
        }
        if (!$dbcon->preparedStmt("delete from usersignup where username=?", $bindList)) {
            $result = sprintf(pacsone_gettext("Failed to delete user sign-up request for %s, error = %s"),
                $username, $dbcon->getError());
            return $result;
        }
        // log activity to system journal
        $dbcon->logJournal($dbcon->getAdminUsername(), "Reject", "User Signup", $username);
        // send rejection email
        $subject = pacsone_gettext("Your Sign-Up Request Has Been Rejected");
        $fullname = ucfirst($first) . " " . ucfirst($last);
        $html = sprintf(pacsone_gettext("Dear %s:"), $fullname);
        $html .= "<p>";
        $html .= pacsone_gettext("We regret to inform you that your sign-up request has been rejected by the Administrator.");
        $html .= "<p><p>";
        $html .= pacsone_gettext("Please contact the Administrator if you have any further questions.");
        emailHtml($email, $subject, $html);
	}
    return $result;
}

?>
