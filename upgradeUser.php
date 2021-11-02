<?php
//
// upgradeUser.php
//
// Module for upgrading existing Database users to use the user privilege Table
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once 'database.php';
include_once 'sharedData.php';
include_once 'sharedUserAdmin.php';

// main
$dbcon = new MyConnection();
$username = $dbcon->username;
if (!$dbcon->isAdministrator($username)) {
	print "<h3><font color=red>";
    print pacsone_gettext("You must login as <b>Administrator</b> to manage user accounts");
    print "</font></h3>";
    exit();
}
$action = $_POST['action'];
$entry = $_POST['entry'];
if (isset($action) && strcasecmp($action, "Upgrade") == 0) {
    $result = upgradeUsers($database, $entry);
} else {
	print "<h3><font color=red>";
    printf(pacsone_gettext("Invalid operation: [%s]"), $action);
    print "</font></h3>";
    exit();
}
if (isset($result)) {       // display results
    if (empty($result)) {   // success
        header('Location: user.php');
    }
    else {                  // error
        print "<html>\n";
        print "<head><title>$PRODUCT - ";
        print pacsone_gettext("User Administration Error");
        print "</title></head>\n";
        print "<body>\n";
        require_once 'header.php';
        print $result;
		require_once 'footer.php';
        print "</body>\n";
        print "</html>\n";
    }
}

function upgradeUsers($db, $entry)
{
    global $PRODUCT;
    global $dbcon;
    $result = "";
    $external = $_POST['external'];
	foreach ($entry as $user) {
        if ($external) {
            $query = "insert into privilege (username,modifydata,forward,query,move,viewprivate,download,export,import) values(";
            $query .= "?,0,0,0,0,0,0,0,0)";
            $bindList = array($user);
		    if (!$dbcon->preparedStmt($query, $bindList)) {
			    $error = sprintf(pacsone_gettext("Warning: Failed to setup privilege for [%s] on [%s]: %s"), $user, $db, $dbcon->getError());
			    $result = "<p><font color=red>$error</font>";
		    }
        }
        $error = "";
		// add new privileges
        if (!addPrivilege($db, $user, $error, 0)) {
            $result .= "<p><font color=red>";
            $result .= sprintf(pacsone_gettext("Failed to add privilege for user: [%s], error = [%s]"). $user, $error);
            $result .= "</font>";
        }
	}
    print "<p>";
    if ($external)
	    printf(pacsone_gettext("All users have been upgraded to have no privileges for %s database. "), $PRODUCT);
    else
	    printf(pacsone_gettext("All users have been upgraded for %s database. "), $PRODUCT);
	print pacsone_gettext("Please <a href='user.php'>Set up</a> the user profile and adjust the privilege individually for each user.");

    if (!$dbcon->useOracle)
	    $dbcon->query("flush privileges");
	return $result;
}

?>
