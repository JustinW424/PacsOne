<?php
//
// modifyUser.php
//
// Module for modifying entries in the user privilege Table
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();
ob_start();

require_once 'locale.php';
include_once 'database.php';
include_once 'sharedData.php';
include_once 'checkUncheck.js';
include_once 'sharedUserAdmin.php';

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
if (isset($_POST['user']))
	$user = trim($_POST['user']);
if (isset($_POST['action']))
   	$action = $_POST['actionvalue'];
else if (isset($_REQUEST['actionvalue']))
   	$action = $_REQUEST['actionvalue'];
if (isset($_POST['entry']))
   	$entry = $_POST['entry'];
$group = 0;
if (isset($_REQUEST['group']))
   	$group = $_REQUEST['group'];
$result = NULL;
if (isset($_GET['user'])) {
    modifyEntryForm($_GET['user'], $group);
}
else {
    if (isset($action) && stristr($action, "Delete")) {
        $group = strcasecmp($action, "Delete")? 1 : 0;
	    $result = deleteEntries($username, $database, $entry, $group);
    }
    else if (isset($action) && stristr($action, "Add")) {
	    if (isset($user)) {
			$passwd = urldecode($_POST['password']);
            if (!validatePassword($passwd)) {
                global $PASSWD_SPECIAL_CHARS;
                $err = sprintf(pacsone_gettext("Error: valid passwords must be at least 8 characters, must contain at least 1 number, 1 capital letter, and 1 special character from %s"), $PASSWD_SPECIAL_CHARS);
                $url = "modifyUser.php?actionvalue=" . ($group? urlencode("Add User Group") : "Add");
                alertBox($err, $url);
                exit();
            }
	        $result = addEntry($username, $database, $user, $passwd, $group);
        }
        else {
            $group = strcasecmp($action, "Add")? 1 : 0;
            addEntryForm($group);
        }
    }
    else if (isset($action) && strcasecmp($action, "Modify") == 0) {
	    $result = modifyEntry($username, $group);
    }
}
if (isset($result)) {       // display results
    if (empty($result)) {   // success
        ob_end_clean();
        $url = (isset($_POST['ldap']) && $_POST['ldap'])? "ldapUser.php" : "user.php";
        header("Location: $url");
    }
    else {                  // error
        ob_end_flush();
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

function deleteEntries($username, $db, $entry, $group)
{
    global $dbcon;
	$ok = array();
	$errors = array();
    $what = $group? "Group" : "User";
	foreach ($entry as $value) {
		$error = "";
		// delete entry from User table
		if (!deleteUser($db, $value, $error))
            $errors[$value] = sprintf(pacsone_gettext("Failed to delete %s %s from User table: "),
                                      $what,
                                      $value) . $error;
        // update GROUPMEMBER table
        if ($group) {
            $bindList = array($value);
            if (!$dbcon->preparedStmt("delete from groupmember where groupname=?", $bindList))
                $errors[$value] = sprintf(pacsone_gettext("Failed to delete Group %s: %s"),
                                      $value,
                                      $dbcon->getError());
        }
		// update result for this entry
		if (!isset($errors[$value])) {
			$ok[] = $value;
            // log activity to system journal
            $dbcon->logJournal($username, "Delete", $what, $value);
        }
	}
	$result = "";
	if (!empty($errors)) {
        if (count($errors) > 1)
            $what = $group? "Groups" : "Users";
		$result = sprintf(pacsone_gettext("Warning: Error deleting the following %s:"), $what);
        $result .= "<P>\n";
		foreach ($errors as $key => $value) {
			$result .= "<h3><font color=red><u>$key</u> - $value</font></h3><P>\n";
		}
	}
    return $result;
}

function addEntry($username, $db, $user, $passwd, $group)
{
    require_once 'utils.php';
    global $dbcon;
    global $FIELD_TBL;
    // make sure the username does no already exist
    if ($dbcon->checkIfUserExists($user)) {
        print "<script language=\"JavaScript\">\n";
        print "<!--\n";
        print "alert(\"";
        printf(pacsone_gettext("Username: '%s' already exist!"), $user);
        print "\");";
        print "//-->\n";
        $url = "user.php";
        if (count(getServerInstances()) > 1)
            $url = "multiInstanceUser.php?configtype=1&shareduser=" . urlencode($user);
        print "window.location = \"$url\";";
        print "</script>\n";
        exit();
    }
    $what = $group? "Group" : "User";
    $result = $dbcon->query("select passwordexpire from config");
    $expire = $result->fetchColumn();
    $result = "";
    $query = "insert into privilege (username";
    $fields = "";
    $values = "?";
    $bindList = array($user);
    foreach ($FIELD_TBL as $field => $quote) {
        if (isset($_POST[$field])) {
            $fields .= ",$field";
            if (strlen($quote)) {
                if (strlen($_POST[$field])) {
                    $values .= ",?";
                    $bindList[] = $_POST[$field];
                } else if (strcasecmp($field, "Lastname") == 0) {
		            $result .= "<h3><font color=red>";
                    $what = $group? pacsone_gettext("Group Description") : pacsone_gettext("Lastname");
                    $result .= sprintf(pacsone_gettext("<u>%s</u> is a required field that must be filled in."), $what);
                    $result .= "</font></h3><p>\n";
		            return $result;
                } else {
                    $values .= ",NULL";
                }
            } else {
                $values .= ",?";
                $bindList[] = $_POST[$field];
            }
        }
    }
    if (!$group) {
        // add default password expiration date
        $fields .= ",expire";
        if ($dbcon->useOracle)
            $values .= ",SYSDATE+$expire";
        else
            $values .= ",DATE_ADD(NOW(), INTERVAL $expire DAY)";
        // modify user group
        if (isset($_POST['usergroup'])) {
            $entry = $_POST['usergroup'];
            foreach ($entry as $g) {
                $subList = array($user, $g);
                if (!$dbcon->preparedStmt("insert into groupmember (username,groupname) values(?,?)", $subList)) {
		            $result .= "<h3><font color=red>";
                    $result .= sprintf(pacsone_gettext("Error configuring Group membership: <u>%s</u> for User: <u>%s</u>: "), $g, $user);
                    $result .= $dbcon->getError();
                    $result .= "</font></h3><p>\n";
		            return $result;
                }
            }
        }
    }
    $query .= "$fields) values($values)";
	// execute SQL query
	if (!$dbcon->preparedStmt($query, $bindList)) {
		$result .= "<h3><font color=red>";
        $result .= sprintf(pacsone_gettext("Error adding %s <u>%s</u>: "), $what, $user);
        $result .= $dbcon->getError();
        $result .= "</font></h3><p>\n";
		return $result;
	}
    // log activity to system journal
    $dbcon->logJournal($username, "Add", $what, $user);
	// create username
    $bindList = array($user);
	$error = "";
	if (!createDbLogin($user, $passwd, $error)) {
        $dbcon->preparedStmt("delete from privilege where username=?", $bindList);
		$result .= "<h3><font color=red>";
        $result .= sprintf(pacsone_gettext("Error creating Database Username for %s <u>%s</u>: "),
                           $what,
                           $user);
        $result .= $error;
        $result .= "</font></h3><p>\n";
		return $result;
	}
	// add user privileges
	$error = "";
    $sysadmin = isset($_POST['admin'])? $_POST['admin'] : 0;
	if (!addPrivilege($db, $user, $error, $sysadmin)) {
        $dbcon->preparedStmt("delete from privilege where username=?", $bindList);
		$result .= "<h3><font color=red>";
        $result .= sprintf(pacsone_gettext("Error setting up privileges for %s <u>%s</u>: "),
                           $what,
                           $user);
        $result .= $error;
        $result .= "</font></h3><p>\n";
		return $result;
	}
    // user access filters
    global $USER_FILTER_TBL;
    foreach ($USER_FILTER_TBL as $filter) {
        if (isset($_POST[$filter])) {
            foreach ($_POST[$filter] as $entry) {
                $subList = array($user, $filter, $entry);
                $dbcon->preparedStmt("insert into userfilter (username,attr,value) values(?,?,?)", $subList);
            }
        }
    }
    return $result;
}

function modifyEntry($username, $group)
{
    global $dbcon;
    global $FIELD_TBL;
    $what = $group? "Group" : "User";
    $result = $dbcon->query("select passwordexpire from config");
    $expire = $result->fetchColumn();
    $result = "";
    $user = trim($_POST['user']);
    $query = "update privilege set ";
    $bindList = array();
    foreach ($FIELD_TBL as $field => $quote) {
        if (isset($_POST[$field])) {
            $query .= "$field=?,";
            $bindList[] = $_POST[$field];
        }
    }
    if (isset($_POST['password']) && strlen($_POST['password'])) {
        // modify password expiration date
        if ($dbcon->useOracle)
            $query .= "expire=SYSDATE+$expire,";
        else
            $query .= "expire=DATE_ADD(NOW(), INTERVAL $expire DAY),";
    }
    if (!$group) {
        // delete any existing group membership
        $subList = array($user);
        if (!$dbcon->preparedStmt("delete from groupmember where username=?", $subList)) {
            print "<h3><font color=red>";
            printf(pacsone_gettext("Failed to remove existing group membership for User: <u>%s</u>"), $user);
            print "</font></h3>";
            exit();
        }
        if (isset($_POST['usergroup'])) {
            // modify user group membership
            $entry = $_POST['usergroup'];
            foreach ($entry as $g) {
                $subList = array($user, $g);
                if (!$dbcon->preparedStmt("insert into groupmember (username,groupname) values(?,?)", $subList)) {
		            print "<h3><font color=red>";
                    printf(pacsone_gettext("Error configuring Group membership: <u>%s</u> for User: <u>%s</u>: "), $g, $user);
                    print $dbcon->getError();
                    print "</font></h3><p>\n";
                    exit();
                }
            }
        }
    }
    // get rid of ',' at the end of query
    $query = substr($query, 0, strlen($query) - 1);
    $query .= " where username=?";
    $bindList[] = $user;
    // execute SQL query
    if (!$dbcon->preparedStmt($query, $bindList)) {
        $result .= "<h3><font color=red>";
        $result .= sprintf(pacsone_gettext("Error updating %s Account <u>%s</u>: "), $what, $user);
        $result .= $dbcon->getError();
        $result .= "</font></h3><p>\n";
    } else {
        // log activity to system journal
        $dbcon->logJournal($username, "Modify", $what, $user);
    }
    // change current password
    if (isset($_POST['password']) && strlen($_POST['password'])) {
        $password = $_POST['password'];
        if (!validatePassword($password)) {
            global $PASSWD_SPECIAL_CHARS;
            $err = sprintf(pacsone_gettext("Error: valid passwords must be at least 8 characters, must contain at least 1 number, 1 capital letter, and 1 special character from %s"), $PASSWD_SPECIAL_CHARS);
            alertBox($err, "modifyUser.php?username=$user");
            exit();
        }
        $bindList = array();
        if ($dbcon->useMysql) {
            if (versionCompare($dbcon->version, 5, 7, 6) < 0) {
                $query = "update mysql.user set password=PASSWORD(?) where mysql.user=?";
                $bindList = array($password, $user);
            } else {
                $hostname = $dbcon->getHostname();
                $query = "alter user ?@? identified by ?";
                $bindList = array($user, $hostname, $password);
            }
        } else if ($dbcon->useOracle) {
            $query = "alter user ? identified by ?";
            $bindList = array($user, $password);
        }
        if (!$dbcon->preparedStmt($query, $bindList)) {
            $result .= "<h3><font color=red>";
            $result .= sprintf(pacsone_gettext("Error Changing %s Password for <u>%s</u>: "), $what, $user);
            $result .= $dbcon->getError();
            $result .= "</font></h3><p>\n";
        }
        if ($dbcon->useMysql)
            $dbcon->query("flush privileges");
    }
    // system administration privilege
    if (isset($_POST["admin"])) {
        $sysadmin = $_POST["admin"];
        $error = "";
        if ($sysadmin)
            $ret = addSysAdminPrivilege($dbcon->database, $user, $error);
        else
            $ret = removeSysAdminPrivilege($dbcon->database, $user, $error);
        if (!$ret) {
            $result .= "<h3><font color=red>";
            $result .= sprintf(pacsone_gettext("Error updating System Administration privilege for User: <u>%s</u>: %s"), $user, $error);
            $result .= "</font></h3><p>\n";
        }
    }
    // user access filters
    global $USER_FILTER_TBL;
    $bindList = array($user);
    $dbcon->preparedStmt("delete from userfilter where username=?", $bindList);
    foreach ($USER_FILTER_TBL as $filter) {
        if (isset($_POST[$filter])) {
            foreach ($_POST[$filter] as $entry) {
                $bindList = array($user, $filter, $entry);
                $dbcon->preparedStmt("insert into userfilter (username,attr,value) values(?,?,?)", $bindList);
            }
        }
    }
    return $result;
}

function addEntryForm($group)
{
    global $PRODUCT;
    $what = $group? "User Group" : "User";
    // display Add New Application Entity form
    print "<html>\n";
    print "<head><title>$PRODUCT - ";
    printf(pacsone_gettext("Add New %s"), $what);
    print "</title></head>\n";
    print "<body>\n";
    require_once 'header.php';
    print "<p>";
    printf(pacsone_gettext("Add the following new %s:"), $what);
    print "<p>";
    print "<form method='POST' action='modifyUser.php'>\n";
    print "<input type='hidden' name='actionvalue'>\n";
    print "<input type='hidden' name='group' value=$group>\n";
    print "<table width=100% border=1 cellspacing=0 cellpadding=5>\n";
    print "<tr><td>";
    $what = $group? "Group Username" : "Username";
    printf(pacsone_gettext("Enter %s:"), $what);
    print "</td>\n";
    print "<td>";
    print pacsone_gettext("This is the username to login to the database");
    print "</td>\n";
    print "<td><input type='text' size=16 maxlength=16 name='user'></td></tr>\n";
    print "<tr><td>" . pacsone_gettext("Enter Password:") . "</td>\n";
    print "<td>" . pacsone_gettext("Password for the database username above") . "</td>\n";
    print "<td><input type='password' size=20 maxlength=20 name='password'></td></tr>\n";
    if ($group) {
        print "<input type='hidden' name='firstname' value='_GROUP'>\n";
        print "<tr><td>";
        print pacsone_gettext("Enter Group Description:");
        print "</td>\n";
        print "<td>";
        print pacsone_gettext("Description of the user group (upto 64 characters)");
        print "</td>\n";
        print "<td><input type='text' size=64 maxlength=64 name='lastname'></td></tr>\n";
    } else {
        print "<tr><td>" . pacsone_gettext("Enter User's First Name:") . "</td>\n";
        print "<td>" . pacsone_gettext("Firstname of the user (upto 20 characters)") . "</td>\n";
        print "<td><input type='text' size=20 maxlength=20 name='firstname'></td></tr>\n";
        print "<tr><td>" . pacsone_gettext("Enter User's Last Name:") . "</td>\n";
        print "<td>" . pacsone_gettext("Lastname of the user (upto 20 characters)") . "</td>\n";
        print "<td><input type='text' size=20 maxlength=20 name='lastname'></td></tr>\n";
        print "<tr><td>" . pacsone_gettext("Enter User's Middle Name:") . "</td>\n";
        print "<td>" . pacsone_gettext("Middlename of the user (upto 20 characters)") . "</td>\n";
        print "<td><input type='text' size=20 maxlength=20 name='middlename'></td></tr>\n";
    }
    $what = $group? "Group" : "User's";
    print "<tr><td>";
    printf(pacsone_gettext("Enter %s Email Address:"), $what);
    print "</td>\n";
    print "<td>";
    $what = $group? "Group" : "User";
    printf(pacsone_gettext("Email address of the %s (upto 64 characters)"), $what);
    print "</td>\n";
    print "<td><input type='text' size=64 maxlength=64 name='email'></td></tr>\n";
    // whether or not to send email notification when new study arrives with this user being the Referring Physician
    if (!$group) {
        print "<tr><td>";
        print pacsone_gettext("Email Notification:");
        print "</td><td>";
        print pacsone_gettext("Send Email Notification When New Study with This User Being the Referring Physician Has Arrived");
        print "</td><td>";
        print "<input type=radio name='notifynewstudy' value=0 checked>" . pacsone_gettext("No"); 
        print "<br><input type=radio name='notifynewstudy' value=1>" . pacsone_gettext("Yes"); 
        print "</td></tr>";
        // User Group
        $result = $dbcon->query("select username,lastname from privilege where firstname='_GROUP' and usergroup is NULL");
        $groups = array();
        while ($g = $result->fetch(PDO::FETCH_NUM))
            $groups[] = $g;
        if (count($groups)) {
            print "<tr><td>";
            print pacsone_gettext("Enter User Group:");
            print "</td>\n";
            print "<td>";
            print pacsone_gettext("Select the user group (s) this user belongs to");
            print "</td>\n";
            print "<td>";
            foreach ($groups as $g) {
                $value = $g[0];
                $descr = $g[1];
                print "<input type='checkbox' name='usergroup[]' value='$value'>&nbsp;$value ($descr)<br>\n";
            }
            print "</td></tr>\n";
        }
    }
    // privileges
    global $DEFAULT_PRIVILEGE_TBL;
    print "<tr><td>" . pacsone_gettext("Select <b>View</b> privilege:") . "</td>\n";
    print "<td>";
    printf(pacsone_gettext("Privilege to view private patients/studies stored in %s database</td>\n"), $PRODUCT);
    $checked = $DEFAULT_PRIVILEGE_TBL['viewprivate']? "checked" : "";
    print "<td><input type=radio name='viewprivate' value=1 $checked>";
    print pacsone_gettext("Enable") . "<br>\n";
    $checked = $DEFAULT_PRIVILEGE_TBL['viewprivate']? "" : "checked";
    print "<input type=radio name='viewprivate' value=0 $checked>";
    print pacsone_gettext("Disable") . "\n";
    print "</td></tr>\n";
    print "<tr><td>" . pacsone_gettext("Select <b>Modify</b> privilege:") . "</td>\n";
    print "<td>";
    printf(pacsone_gettext("Privilege to modify %s database tables"), $PRODUCT);
    print "</td>\n";
    $checked = $DEFAULT_PRIVILEGE_TBL['modifydata']? "checked" : "";
    print "<td><input type=radio name='modifydata' value=1 $checked>";
    print pacsone_gettext("Enable") . "<br>\n";
    $checked = $DEFAULT_PRIVILEGE_TBL['modifydata']? "" : "checked";
    print "<input type=radio name='modifydata' value=0 $checked>";
    print pacsone_gettext("Disable") . "\n";
    print "</td></tr>\n";
    print "<tr><td>" . pacsone_gettext("Select <b>Forward</b> privilege:") . "</td>\n";
    print "<td>";
    printf(pacsone_gettext("Privilege to forward images stored in %s database to remote AEs</td>\n"), $PRODUCT);
    $checked = $DEFAULT_PRIVILEGE_TBL['forward']? "checked" : "";
    print "<td><input type=radio name='forward' value=1 $checked>";
    print pacsone_gettext("Enable") . "<br>\n";
    $checked = $DEFAULT_PRIVILEGE_TBL['forward']? "" : "checked";
    print "<input type=radio name='forward' value=0 $checked>";
    print pacsone_gettext("Disable") . "\n";
    print "</td></tr>\n";
    print "<tr><td>" . pacsone_gettext("Select <b>Query</b> privilege:") . "</td>\n";
    print "<td>";
    print pacsone_gettext("Privilege to query remote Query/Retrieve SCP applications");
    print "</td>\n";
    $checked = $DEFAULT_PRIVILEGE_TBL['query']? "checked" : "";
    print "<td><input type=radio name='query' value=1 $checked>";
    print pacsone_gettext("Enable") . "<br>\n";
    $checked = $DEFAULT_PRIVILEGE_TBL['query']? "" : "checked";
    print "<input type=radio name='query' value=0 $checked>";
    print pacsone_gettext("Disable") . "\n";
    print "</td></tr>\n";
    print "<tr><td>" . pacsone_gettext("Select <b>Move</b> privilege:") . "</td>\n";
    print "<td>";
    print pacsone_gettext("Privilege to move images stored in remote Query/Retrieve SCP applications") . "</td>\n";
    $checked = $DEFAULT_PRIVILEGE_TBL['move']? "checked" : "";
    print "<td><input type=radio name='move' value=1 $checked>";
    print pacsone_gettext("Enable") . "<br>\n";
    $checked = $DEFAULT_PRIVILEGE_TBL['move']? "" : "checked";
    print "<input type=radio name='move' value=0 $checked>";
    print pacsone_gettext("Disable") . "\n";
    print "</td></tr>\n";
    print "<tr><td>" . pacsone_gettext("Select <b>Download</b> privilege:") . "</td>\n";
    print "<td>";
    print pacsone_gettext("Privilege to download images from web browsers like Microsoft Internet Explorer") . "</td>\n";
    $checked = $DEFAULT_PRIVILEGE_TBL['download']? "checked" : "";
    print "<td><input type=radio name='download' value=1 $checked>";
    print pacsone_gettext("Enable") . "<br>\n";
    $checked = $DEFAULT_PRIVILEGE_TBL['download']? "" : "checked";
    print "<input type=radio name='download' value=0 $checked>";
    print pacsone_gettext("Disable") . "\n";
    print "</td></tr>\n";
    print "<tr><td>" . pacsone_gettext("Select <b>Print</b> privilege:") . "</td>\n";
    print "<td>";
    print pacsone_gettext("Privilege to print images from web browsers like Microsoft Internet Explorer") . "</td>\n";
    $checked = $DEFAULT_PRIVILEGE_TBL['print']? "checked" : "";
    print "<td><input type=radio name='print' value=1 $checked>";
    print pacsone_gettext("Enable") . "<br>\n";
    $checked = $DEFAULT_PRIVILEGE_TBL['print']? "" : "checked";
    print "<input type=radio name='print' value=0 $checked>";
    print pacsone_gettext("Disable") . "\n";
    print "</td></tr>\n";
    print "<tr><td>" . pacsone_gettext("Select <b>Export</b> privilege:") . "</td>\n";
    print "<td>";
    print pacsone_gettext("Privilege to Export studies for Dicom-compatible media interchange") . "</td>\n";
    $checked = $DEFAULT_PRIVILEGE_TBL['export']? "checked" : "";
    print "<td><input type=radio name='export' value=1 $checked>";
    print pacsone_gettext("Enable") . "<br>\n";
    $checked = $DEFAULT_PRIVILEGE_TBL['export']? "" : "checked";
    print "<input type=radio name='export' value=0 $checked>";
    print pacsone_gettext("Disable") . "\n";
    print "</td></tr>\n";
    print "<tr><td>" . pacsone_gettext("Select <b>Import</b> privilege:") . "</td>\n";
    print "<td>";
    print pacsone_gettext("Privilege to Import images from Dicom-formatted media or directory") . "</td>\n";
    $checked = $DEFAULT_PRIVILEGE_TBL['import']? "checked" : "";
    print "<td><input type=radio name='import' value=1 $checked>";
    print pacsone_gettext("Enable") . "<br>\n";
    $checked = $DEFAULT_PRIVILEGE_TBL['import']? "" : "checked";
    print "<input type=radio name='import' value=0 $checked>";
    print pacsone_gettext("Disable") . "\n";
    print "</td></tr>\n";
    print "<tr><td>" . pacsone_gettext("Select <b>Upload</b> privilege:") . "</td>\n";
    print "<td>";
    print pacsone_gettext("Privilege to Upload files such as notes, Word/Pdf documents, audio/video clips, etc.") . "</td>\n";
    $checked = $DEFAULT_PRIVILEGE_TBL['upload']? "checked" : "";
    print "<td><input type=radio name='upload' value=1 $checked>";
    print pacsone_gettext("Enable") . "<br>\n";
    $checked = $DEFAULT_PRIVILEGE_TBL['upload']? "" : "checked";
    print "<input type=radio name='upload' value=0 $checked>";
    print pacsone_gettext("Disable") . "\n";
    print "</td></tr>\n";
    print "<tr><td>" . pacsone_gettext("Select <b>Monitor</b> privilege:") . "</td>\n";
    print "<td>";
    print pacsone_gettext("Privilege to access system monitoring activities such as system logs, live monitors, etc.") . "</td>\n";
    $checked = $DEFAULT_PRIVILEGE_TBL['monitor']? "checked" : "";
    print "<td><input type=radio name='monitor' value=1 $checked>";
    print pacsone_gettext("Enable") . "<br>\n";
    $checked = $DEFAULT_PRIVILEGE_TBL['monitor']? "" : "checked";
    print "<input type=radio name='monitor' value=0 $checked>";
    print pacsone_gettext("Disable") . "\n";
    print "</td></tr>\n";
    print "<tr><td>" . pacsone_gettext("Select <b>Mark Study</b> privilege:") . "</td>\n";
    print "<td>";
    print pacsone_gettext("Privilege to mark a study as Read or Un-read") . "</td>\n";
    $checked = $DEFAULT_PRIVILEGE_TBL['mark']? "checked" : "";
    print "<td><input type=radio name='mark' value=1 $checked>";
    print pacsone_gettext("Enable") . "<br>\n";
    $checked = $DEFAULT_PRIVILEGE_TBL['mark']? "" : "checked";
    print "<input type=radio name='mark' value=0 $checked>";
    print pacsone_gettext("Disable") . "\n";
    print "</td></tr>\n";
    print "<tr><td>" . pacsone_gettext("Select <b>Change Storage Location</b> privilege:") . "</td>\n";
    print "<td>";
    print pacsone_gettext("Privilege to change storage location of received Dicom studies") . "</td>\n";
    $checked = $DEFAULT_PRIVILEGE_TBL['changestore']? "checked" : "";
    print "<td><input type=radio name='changestore' value=1 $checked>";
    print pacsone_gettext("Enable") . "<br>\n";
    $checked = $DEFAULT_PRIVILEGE_TBL['changestore']? "" : "checked";
    print "<input type=radio name='changestore' value=0 $checked>";
    print pacsone_gettext("Disable") . "\n";
    print "</td></tr>\n";
    print "<tr><td>" . pacsone_gettext("Select <b>System Administration</b> privilege:") . "</td>\n";
    print "<td>";
    print pacsone_gettext("Privilege to access system-level configurations such as Email, Job Status, System Journal, etc.") . "</td>\n";
    $checked = $DEFAULT_PRIVILEGE_TBL['admin']? "checked" : "";
    print "<td><input type=radio name='admin' value=1 $checked>";
    print pacsone_gettext("Enable") . "<br>\n";
    $checked = $DEFAULT_PRIVILEGE_TBL['admin']? "" : "checked";
    print "<input type=radio name='admin' value=0 $checked>";
    print pacsone_gettext("Disable") . "\n";
    print "</td></tr>\n";
    // DicomWeb QIDO-RS access
    print "<tr><td>" . pacsone_gettext("Select <b>DicomWeb QIDO-RS</b> privilege:") . "</td>\n";
    print "<td>";
    print pacsone_gettext("Privilege to run DicomWeb QIDO-RS Query from a remote client") . "</td>\n";
    $checked = $DEFAULT_PRIVILEGE_TBL['qidors']? "checked" : "";
    print "<td><input type=radio name='qidors' value=1 $checked>";
    print pacsone_gettext("Enable") . "<br>\n";
    $checked = $DEFAULT_PRIVILEGE_TBL['qidors']? "" : "checked";
    print "<input type=radio name='qidors' value=0 $checked>";
    print pacsone_gettext("Disable") . "\n";
    print "</td></tr>\n";
    // DicomWeb WADO-RS access
    print "<tr><td>" . pacsone_gettext("Select <b>DicomWeb WADO-RS</b> privilege:") . "</td>\n";
    print "<td>";
    print pacsone_gettext("Privilege to run DicomWeb WADO-RS Query/Retrieve from a remote client") . "</td>\n";
    $checked = $DEFAULT_PRIVILEGE_TBL['wadors']? "checked" : "";
    print "<td><input type=radio name='wadors' value=1 $checked>";
    print pacsone_gettext("Enable") . "<br>\n";
    $checked = $DEFAULT_PRIVILEGE_TBL['wadors']? "" : "checked";
    print "<input type=radio name='wadors' value=0 $checked>";
    print pacsone_gettext("Disable") . "\n";
    print "</td></tr>\n";
    // DicomWeb STOW-RS access
    print "<tr><td>" . pacsone_gettext("Select <b>DicomWeb STOW-RS</b> privilege:") . "</td>\n";
    print "<td>";
    print pacsone_gettext("Privilege to run DicomWeb STOW-RS Store operation from a remote client") . "</td>\n";
    $checked = $DEFAULT_PRIVILEGE_TBL['stowrs']? "checked" : "";
    print "<td><input type=radio name='stowrs' value=1 $checked>";
    print pacsone_gettext("Enable") . "<br>\n";
    $checked = $DEFAULT_PRIVILEGE_TBL['stowrs']? "" : "checked";
    print "<input type=radio name='stowrs' value=0 $checked>";
    print pacsone_gettext("Disable") . "\n";
    print "</td></tr>\n";
    if ($group) {
        print "<tr><td>" . pacsone_gettext("Select <b>Group Share</b>:") . "</td>\n";
        print "<td>";
        print pacsone_gettext("If this privilege is enabled, the <b>View</b> access of any user of this group is shared by all other users of the same group. For example, if a user of this group can access a private study and this privilege is enabled, then all other users of this group can access the same private study.") . "</td>\n";
        print "<td><input type=radio name='matchgroup' value=1>";
        print pacsone_gettext("Enable") . "<br>\n";
        print "<input type=radio name='matchgroup' value=0 checked>";
        print pacsone_gettext("Disable") . "\n";
        print "</td></tr>\n";
        // Sub-string Group Matching
        print "<tr><td>" . pacsone_gettext("Select <b>Sub-string Group Matching</b>:") . "</td>\n";
        print "<td>";
        printf(pacsone_gettext("If this option is enabled, then instead of using Exact-matching method when checking if this group has access to a private patient, %s will check whether the Group Description of this group is a sub-string of the Institution Name of that patient."), $PRODUCT) . "</td>\n";
        print "<td><input type=radio name='substring' value=1>";
        print pacsone_gettext("Enable") . "<br>\n";
        print "<input type=radio name='substring' value=0 checked>";
        print pacsone_gettext("Disable") . "\n";
        print "</td></tr>\n";
    }
    // user access filters
    if ($dbcon->isUserFilterEnabled()) {
        print "<tr><td>" . pacsone_gettext("Select <b>User Access Filters</b>:") . "</td>\n";
        print "<td>";
        print pacsone_gettext("Grant access to this user for the specific patient or study with the matching Dicom data element filter values. If multiple filters are defined, then the logical <b>AND (&&)</b> operator will be applied for all defined filters");
        print "</td><td><ul>";
        // Source AE filter
        $aefilter = array();
        $result = $dbcon->query("select title from applentity order by title asc");
        while ($result && ($row = $result->fetch(PDO::FETCH_NUM)))
            $aefilter[] = $row[0];
        if (count($aefilter) > 1) {
            print "<li>";
            print "<b><u>" . pacsone_gettext("Source AE Title:") . "</u></b><br>";
            foreach ($aefilter as $entry)
                print "<input type='checkbox' name='sourceae[]' value='$entry'>$entry" . "&nbsp";
            print "</li>";
        }
        // Referring Physician's Name filter
        $referfilter = array();
        $result = $dbcon->query("select distinct referringphysician from study where referringphysician is not NULL order by referringphysician asc");
        while ($result && ($row = $result->fetch(PDO::FETCH_NUM)))
            $referfilter[] = $row[0];
        if (count($referfilter) > 1) {
            print "<li>";
            print "<b><u>" . pacsone_gettext("Referring Physician's Name:") . "</u></b><br>";
            foreach ($referfilter as $entry)
                print "<input type='checkbox' name='referringphysician[]' value='$entry'>$entry" . "&nbsp";
            print "</li>";
        }
        // Reading Physician's Name filter
        $readfilter = array();
        $result = $dbcon->query("select distinct readingphysician from study where readingphysician is not NULL order by readingphysician asc");
        while ($result && ($row = $result->fetch(PDO::FETCH_NUM)))
            $readfilter[] = $row[0];
        if (count($readfilter) > 1) {
            print "<li>";
            print "<b><u>" . pacsone_gettext("Reading Physician's Name:") . "</u></b><br>";
            foreach ($readfilter as $entry)
                print "<input type='checkbox' name='readingphysician[]' value='$entry'>$entry" . "&nbsp";
            print "</li>";
        }
        // Institution Name filter
        $instfilter = array();
        $result = $dbcon->query("select distinct institution from patient where institution is not NULL order by institution asc");
        while ($result && ($row = $result->fetch(PDO::FETCH_NUM)))
            $instfilter[] = $row[0];
        if (count($instfilter) > 1) {
            print "<li>";
            print "<b><u>" . pacsone_gettext("Institution Name:") . "</u></b><br>";
            foreach ($instfilter as $entry)
                print "<input type='checkbox' name='institution[]' value='$entry'>$entry" . "&nbsp";
            print "</li>";
        }
        print "</ul></td></tr>\n";
    }
    print "</table>\n";
    print "<p><input type='submit' name='action' value='";
    print pacsone_gettext("Add");
    print "' onclick='switchText(this.form,\"actionvalue\",\"Add\")'></form>\n";
    require_once 'footer.php';
    print "</body>\n";
    print "</html>\n";
}

function modifyEntryForm($user, $group)
{
    global $PRODUCT;
    global $dbcon;
    // display Modify User Account form
    print "<html>\n";
    print "<head><title>$PRODUCT - ";
    $what = $group? "Group" : "User";
    printf(pacsone_gettext("Modify %s Profile %s"), $what, $user);
    print "</title></head>\n";
    print "<body>\n";
    require_once 'header.php';
    print "<p>";
    printf(pacsone_gettext("Modify %s Profile for: <b><u>%s</u></b>"), $what, $user);
    print "<p>";
    $bindList = array($user);
    $query = "select * from privilege where username=?";
    $result = $dbcon->preparedStmt($query, $bindList);
    $ldap = isset($_REQUEST['ldap'])? $_REQUEST['ldap'] : 0;
    if ($result->rowCount() == 1) {
        $row = $result->fetch(PDO::FETCH_ASSOC);
        print "<form method='POST' action='modifyUser.php'>\n";
        print "<input type='hidden' name='actionvalue'>\n";
		print "<input type=hidden name='user' value='$user'>\n";
        print "<input type=hidden name='group' value=$group>\n";
        print "<input type=hidden name='ldap' value=$ldap>\n";
        print "<table width=100% border=1 cellspacing=0 cellpadding=5>\n";
        if (!$ldap) {
		    // password
    	    print "<tr><td>";
            $what = $group? "Group" : "User's";
            printf(pacsone_gettext("Change %s Password:"), $what);
            print "</td>\n";
		    print "<td>";
            $what = $group? "Group" : "User";
            global $PASSWD_SPECIAL_CHARS;
            printf(pacsone_gettext("New password for current %s (must be at least 8 characters and include 1 number, 1 capital letter and 1 special character from \"%s\")"), $what, $PASSWD_SPECIAL_CHARS);
            print "</td>\n";
    	    $value = "<td><input type='password' size=20 maxlength=20 name='password'";
		    $value .= "></td></tr>\n";
		    print $value;
        }
        $readOnly = $ldap? " readonly" : "";
        $disabled = $ldap? "disabled" : "";
        if ($group) {
		    // group name
    	    print "<tr><td>" . pacsone_gettext("Enter Group Description:") . "</td>\n";
		    print "<td>";
            print pacsone_gettext("Description of the user group (upto 64 characters)");
            print "</td>\n";
    	    $value = "<td><input type='text' size=64 maxlength=64 name='lastname'";
		    if (isset($row['lastname']))
			    $value .= "value='" . $row['lastname'] . "'";
		    $value .= "$readOnly></td></tr>\n";
		    print $value;
        } else {
		    // firstname
    	    print "<tr><td>" . pacsone_gettext("Enter User's First Name:") . "</td>\n";
		    print "<td>";
            print pacsone_gettext("Firstname of the user (upto 20 characters)") . "</td>\n";
    	    $value = "<td><input type='text' size=20 maxlength=20 name='firstname'";
		    if (isset($row['firstname']))
			    $value .= " value='" . $row['firstname'] . "'";
		    $value .= "$readOnly></td></tr>\n";
		    print $value;
		    // lastname
    	    print "<tr><td>" . pacsone_gettext("Enter User's Last Name:") . "</td>\n";
		    print "<td>";
            print pacsone_gettext("Lastname of the user (upto 20 characters)") . "</td>\n";
    	    $value = "<td><input type='text' size=20 maxlength=20 name='lastname'";
		    if (isset($row['lastname']))
			    $value .= "value='" . $row['lastname'] . "'";
		    $value .= "$readOnly></td></tr>\n";
		    print $value;
		    // middlename
    	    print "<tr><td>" . pacsone_gettext("Enter User's Middle Name:") . "</td>\n";
		    print "<td>";
            print pacsone_gettext("Middlename of the user (upto 20 characters)") . "</td>\n";
    	    $value = "<td><input type='text' size=20 maxlength=20 name='middlename'";
		    if (isset($row['middlename']))
			    $value .= " value='" . $row['middlename'] . "'";
		    $value .= "$readOnly></td></tr>\n";
		    print $value;
        }
		// email address
        $what = $group? "Group" : "User's";
        print "<tr><td>";
        printf(pacsone_gettext("Enter %s Email Address:"), $what);
        print "</td>\n";
		print "<td>";
        $what = $group? "Group" : "User";
        printf(pacsone_gettext("Email address of the %s (upto 64 characters)"), $what);
        print "</td>\n";
    	$value = "<td><input type='text' size=64 maxlength=64 name='email'";
		if (isset($row['email']))
			$value .= " value='" . $row['email'] . "'";
        $readOnly = ($ldap && !$group)? " readonly" : "";
		$value .= "$readOnly></td></tr>\n";
		print $value;
        // whether or not to send email notification when new study arrives with this user being the Referring Physician
        if (!$group) {
            $value = $row['notifynewstudy'];
            print "<tr><td>";
            print pacsone_gettext("Email Notification:");
            print "</td><td>";
            print pacsone_gettext("Send Email Notification When New Study with This User Being the Referring Physician Has Arrived");
            print "</td><td>";
            $checked = $value? "" : "checked";
            print "<input type=radio name='notifynewstudy' value=0 $checked>" . pacsone_gettext("No"); 
            $checked = $value? "checked" : "";
            print "<br><input type=radio name='notifynewstudy' value=1 $checked>" . pacsone_gettext("Yes"); 
            print "</td></tr>";
            // User Group
            $result = $dbcon->query("select username,lastname from privilege where firstname='_GROUP' and usergroup is NULL");
            $groups = array();
            while ($g = $result->fetch(PDO::FETCH_NUM))
                $groups[] = $g;
            $result = $dbcon->preparedStmt("select groupname from groupmember where username=?", $bindList);
            $membership = array();
            while ($m = $result->fetchColumn())
                $membership[] = $m;
            print "<tr><td>";
            print pacsone_gettext("Enter User Group:");
            print "</td>\n";
		    print "<td>";
            print pacsone_gettext("Select the user group (s) this user belongs to");
            print "</td>\n";
            print "<td>";
            if (count($groups)) {
                foreach ($groups as $g) {
                    $value = $g[0];
                    $descr = $g[1];
                    $checked = in_array($value, $membership)? "checked" : "";
                    print "<input type='checkbox' name='usergroup[]' value='$value' $checked $disabled>&nbsp;$value ($descr)<br>\n";
                }
            } else {
                print pacsone_gettext("No User Group is defined");
            }
		    print "</td></tr>\n";
        }
		// modify privileges
		print "<tr><td>" . pacsone_gettext("Select <b>View</b> privilege:") . "</td>\n";
		print "<td>";
        printf(pacsone_gettext("Privilege to view private patients/studies stored in %s database"), $PRODUCT) . "</td>\n";
		$enable = isset($row['viewprivate'])? $row['viewprivate'] : 0;
		$checked = $enable? "checked" : "";
		print "<td><input type=radio name='viewprivate' value=1 $checked>";
        print pacsone_gettext("Enable") . "<br>\n";
		$checked = $enable? "" : "checked";
		print "<input type=radio name='viewprivate' value=0 $checked>";
        print pacsone_gettext("Disable") . "\n";
		print "</td></tr>\n";
		print "<tr><td>" . pacsone_gettext("Select <b>Modify</b> privilege:") . "</td>\n";
		print "<td>";
        printf(pacsone_gettext("Privilege to modify %s database tables"), $PRODUCT);
        print "</td>\n";
		$enable = isset($row['modifydata'])? $row['modifydata'] : 0;
		$checked = $enable? "checked" : "";
		print "<td><input type=radio name='modifydata' value=1 $checked>";
        print pacsone_gettext("Enable") . "<br>\n";
		$checked = $enable? "" : "checked";
		print "<input type=radio name='modifydata' value=0 $checked>";
        print pacsone_gettext("Disable") . "\n";
		print "</td></tr>\n";
		print "<tr><td>" . pacsone_gettext("Select <b>Forward</b> privilege:") . "</td>\n";
		print "<td>";
        printf(pacsone_gettext("Privilege to forward images stored in %s database to remote AEs"), $PRODUCT);
        print "</td>\n";
		$enable = isset($row['forward'])? $row['forward'] : 0;
		$checked = $enable? "checked" : "";
		print "<td><input type=radio name='forward' value=1 $checked>";
        print pacsone_gettext("Enable") . "<br>\n";
		$checked = $enable? "" : "checked";
		print "<input type=radio name='forward' value=0 $checked>";
        print pacsone_gettext("Disable") . "\n";
		print "</td></tr>\n";
		print "<tr><td>" . pacsone_gettext("Select <b>Query</b> privilege:") . "</td>\n";
		print "<td>";
        print pacsone_gettext("Privilege to query remote Query/Retrieve SCP applications");
        print "</td>\n";
		$enable = isset($row['query'])? $row['query'] : 0;
		$checked = $enable? "checked" : "";
		print "<td><input type=radio name='query' value=1 $checked>";
        print pacsone_gettext("Enable") . "<br>\n";
		$checked = $enable? "" : "checked";
		print "<input type=radio name='query' value=0 $checked>";
        print pacsone_gettext("Disable") . "\n";
		print "</td></tr>\n";
		print "<tr><td>" . pacsone_gettext("Select <b>Move</b> privilege:") . "</td>\n";
		print "<td>";
        print pacsone_gettext("Privilege to move images stored in remote Query/Retrieve SCP applications");
        print "</td>\n";
		$enable = isset($row['move'])? $row['move'] : 0;
		$checked = $enable? "checked" : "";
		print "<td><input type=radio name='move' value=1 $checked>";
        print pacsone_gettext("Enable") . "<br>\n";
		$checked = $enable? "" : "checked";
		print "<input type=radio name='move' value=0 $checked>";
        print pacsone_gettext("Disable") . "\n";
		print "</td></tr>\n";
		print "<tr><td>" . pacsone_gettext("Select <b>Download</b> privilege:") . "</td>\n";
		print "<td>";
        print pacsone_gettext("Privilege to download images from web browsers like Microsoft Internet Explorer");
        print "</td>\n";
		$enable = isset($row['download'])? $row['download'] : 0;
		$checked = $enable? "checked" : "";
		print "<td><input type=radio name='download' value=1 $checked>";
        print pacsone_gettext("Enable") . "<br>\n";
		$checked = $enable? "" : "checked";
		print "<input type=radio name='download' value=0 $checked>";
        print pacsone_gettext("Disable") . "\n";
		print "</td></tr>\n";
		print "<tr><td>" . pacsone_gettext("Select <b>Print</b> privilege:") . "</td>\n";
		print "<td>";
        print pacsone_gettext("Privilege to print images from web browsers like Microsoft Internet Explorer") . "</td>\n";
		$enable = isset($row['print'])? $row['print'] : 0;
		$checked = $enable? "checked" : "";
		print "<td><input type=radio name='print' value=1 $checked>";
        print pacsone_gettext("Enable") . "<br>\n";
		$checked = $enable? "" : "checked";
		print "<input type=radio name='print' value=0 $checked>";
        print pacsone_gettext("Disable") . "\n";
		print "</td></tr>\n";
		print "<tr><td>" . pacsone_gettext("Select <b>Export</b> privilege:") . "</td>\n";
		print "<td>";
        print pacsone_gettext("Privilege to Export studies for Dicom-compatible media interchage") . "</td>\n";
		$enable = isset($row['export'])? $row['export'] : 0;
		$checked = $enable? "checked" : "";
		print "<td><input type=radio name='export' value=1 $checked>";
        print pacsone_gettext("Enable") . "<br>\n";
		$checked = $enable? "" : "checked";
		print "<input type=radio name='export' value=0 $checked>";
        print pacsone_gettext("Disable") . "\n";
		print "</td></tr>\n";
		print "<tr><td>" . pacsone_gettext("Select <b>Import</b> privilege:") . "</td>\n";
		print "<td>";
        print pacsone_gettext("Privilege to Import images from Dicom-formatted media or directory") . "</td>\n";
		$enable = isset($row['import'])? $row['import'] : 0;
		$checked = $enable? "checked" : "";
		print "<td><input type=radio name='import' value=1 $checked>";
        print pacsone_gettext("Enable") . "<br>\n";
		$checked = $enable? "" : "checked";
		print "<input type=radio name='import' value=0 $checked>";
        print pacsone_gettext("Disable") . "\n";
		print "</td></tr>\n";
		print "<tr><td>" . pacsone_gettext("Select <b>Upload</b> privilege:") . "</td>\n";
		print "<td>";
        print pacsone_gettext("Privilege to Upload files such as notes, Word/Pdf documents, audio/video clips, etc.") . "</td>\n";
		$enable = isset($row['upload'])? $row['upload'] : 0;
		$checked = $enable? "checked" : "";
		print "<td><input type=radio name='upload' value=1 $checked>";
        print pacsone_gettext("Enable") . "<br>\n";
		$checked = $enable? "" : "checked";
		print "<input type=radio name='upload' value=0 $checked>";
        print pacsone_gettext("Disable") . "\n";
		print "</td></tr>\n";
		print "<tr><td>" . pacsone_gettext("Select <b>Monitor</b> privilege:") . "</td>\n";
		print "<td>";
        print pacsone_gettext("Privilege to access system monitoring activities such as system logs, live monitors, etc.") . "</td>\n";
		$enable = isset($row['monitor'])? $row['monitor'] : 0;
		$checked = $enable? "checked" : "";
		print "<td><input type=radio name='monitor' value=1 $checked>";
        print pacsone_gettext("Enable") . "<br>\n";
		$checked = $enable? "" : "checked";
		print "<input type=radio name='monitor' value=0 $checked>";
        print pacsone_gettext("Disable") . "\n";
		print "</td></tr>\n";
		print "<tr><td>" . pacsone_gettext("Select <b>Mark Study</b> privilege:") . "</td>\n";
		print "<td>";
        print pacsone_gettext("Privilege to mark a study as Read or Un-read") . "</td>\n";
		$enable = isset($row['mark'])? $row['mark'] : 0;
		$checked = $enable? "checked" : "";
		print "<td><input type=radio name='mark' value=1 $checked>";
        print pacsone_gettext("Enable") . "<br>\n";
		$checked = $enable? "" : "checked";
		print "<input type=radio name='mark' value=0 $checked>";
        print pacsone_gettext("Disable") . "\n";
		print "</td></tr>\n";
		print "<tr><td>" . pacsone_gettext("Select <b>Change Storage Location</b> privilege:") . "</td>\n";
		print "<td>";
        print pacsone_gettext("Privilege to change storage location of received Dicom studies") . "</td>\n";
		$enable = isset($row['changestore'])? $row['changestore'] : 0;
		$checked = $enable? "checked" : "";
		print "<td><input type=radio name='changestore' value=1 $checked>";
        print pacsone_gettext("Enable") . "<br>\n";
		$checked = $enable? "" : "checked";
		print "<input type=radio name='changestore' value=0 $checked>";
        print pacsone_gettext("Disable") . "\n";
		print "</td></tr>\n";
		print "<tr><td>" . pacsone_gettext("Select <b>System Administration</b> privilege:") . "</td>\n";
        print "<td>";
        print pacsone_gettext("Privilege to access system-level configurations such as Email, Job Status, System Journal, etc.") . "</td>\n";
		$enable = isset($row['admin'])? $row['admin'] : 0;
		$checked = $enable? "checked" : "";
        print "<td><input type=radio name='admin' value=1 $checked>";
        print pacsone_gettext("Enable") . "<br>\n";
		$checked = $enable? "" : "checked";
        print "<input type=radio name='admin' value=0 $checked>";
        print pacsone_gettext("Disable") . "\n";
        print "</td></tr>\n";
        // DicomWeb QIDO-RS access
        print "<tr><td>" . pacsone_gettext("Select <b>DicomWeb QIDO-RS</b> privilege:") . "</td>\n";
        print "<td>";
        print pacsone_gettext("Privilege to run DicomWeb QIDO-RS Query from a remote client") . "</td>\n";
		$enable = isset($row['qidors'])? $row['qidors'] : 0;
		$checked = $enable? "checked" : "";
        print "<td><input type=radio name='qidors' value=1 $checked>";
        print pacsone_gettext("Enable") . "<br>\n";
		$checked = $enable? "" : "checked";
        print "<input type=radio name='qidors' value=0 $checked>";
        print pacsone_gettext("Disable") . "\n";
        print "</td></tr>\n";
        // DicomWeb WADO-RS access
        print "<tr><td>" . pacsone_gettext("Select <b>DicomWeb WADO-RS</b> privilege:") . "</td>\n";
        print "<td>";
        print pacsone_gettext("Privilege to run DicomWeb WADO-RS Query/Retrieve from a remote client") . "</td>\n";
		$enable = isset($row['wadors'])? $row['wadors'] : 0;
		$checked = $enable? "checked" : "";
        print "<td><input type=radio name='wadors' value=1 $checked>";
        print pacsone_gettext("Enable") . "<br>\n";
		$checked = $enable? "" : "checked";
        print "<input type=radio name='wadors' value=0 $checked>";
        print pacsone_gettext("Disable") . "\n";
        print "</td></tr>\n";
        // DicomWeb STOW-RS access
        print "<tr><td>" . pacsone_gettext("Select <b>DicomWeb STOW-RS</b> privilege:") . "</td>\n";
        print "<td>";
        print pacsone_gettext("Privilege to run DicomWeb STOW-RS Store operation from a remote client") . "</td>\n";
		$enable = isset($row['stowrs'])? $row['stowrs'] : 0;
		$checked = $enable? "checked" : "";
        print "<td><input type=radio name='stowrs' value=1 $checked>";
        print pacsone_gettext("Enable") . "<br>\n";
		$checked = $enable? "" : "checked";
        print "<input type=radio name='stowrs' value=0 $checked>";
        print pacsone_gettext("Disable") . "\n";
        print "</td></tr>\n";
        if ($group) {
		    print "<tr><td>" . pacsone_gettext("Select <b>Group Share</b>:") . "</td>\n";
		    print "<td>";
            print pacsone_gettext("If this privilege is enabled, the <b>View</b> access of any user of this group is shared by all other users of the same group. For example, if a user of this group can access a private study and this privilege is enabled, then all other users of this group can access the same private study.") . "</td>\n";
		    $enable = isset($row['matchgroup'])? $row['matchgroup'] : 0;
		    $checked = $enable? "checked" : "";
		    print "<td><input type=radio name='matchgroup' value=1 $checked>";
            print pacsone_gettext("Enable") . "<br>\n";
		    $checked = $enable? "" : "checked";
		    print "<input type=radio name='matchgroup' value=0 $checked>";
            print pacsone_gettext("Disable") . "\n";
		    print "</td></tr>\n";
            // Sub-string Group Matching
            print "<tr><td>" . pacsone_gettext("Select <b>Sub-string Group Matching</b>:") . "</td>\n";
            print "<td>";
            printf(pacsone_gettext("If this option is enabled, then instead of using Exact-matching method when checking if this group has access to a private patient, %s will check whether the Group Description of this group is a sub-string of the Institution Name of that patient."), $PRODUCT) . "</td>\n";
		    $enable = isset($row['substring'])? $row['substring'] : 0;
		    $checked = $enable? "checked" : "";
            print "<td><input type=radio name='substring' value=1 $checked>";
            print pacsone_gettext("Enable") . "<br>\n";
		    $checked = $enable? "" : "checked";
            print "<input type=radio name='substring' value=0 $checked>";
            print pacsone_gettext("Disable") . "\n";
            print "</td></tr>\n";
        }
        // user access filters
        if ($dbcon->isUserFilterEnabled()) {
            global $USER_FILTER_TBL;
            $current = array();
            $result = $dbcon->preparedStmt("select * from userfilter where username=?", $bindList);
            while ($result && ($row = $result->fetch(PDO::FETCH_ASSOC))) {
                $attr = $row["attr"];
                $value = $row["value"];
                if (in_array($attr, $USER_FILTER_TBL))
                    $current[$attr][] = strtolower($value);
            }
            print "<tr><td>" . pacsone_gettext("Select <b>User Access Filters</b>:") . "</td>\n";
            print "<td>";
            print pacsone_gettext("Grant access to this user for the specific patient or study with the matching Dicom data element filter values. If multiple filters are defined, then the logical <b>AND (&&)</b> operator will be applied for all defined filters");
            print "</td><td><ul>";
            // Source AE filter
            print "<li>";
            $aefilter = array();
            $result = $dbcon->query("select title from applentity order by title asc");
            while ($result && ($row = $result->fetch(PDO::FETCH_NUM)))
                $aefilter[] = $row[0];
            print "<b><u>" . pacsone_gettext("Source AE Title:") . "</u></b><br>";
            foreach ($aefilter as $entry) {
                $checked = (isset($current["sourceae"]) && in_array(strtolower($entry), $current["sourceae"]))? "checked" : "";
                print "<input type='checkbox' name='sourceae[]' value='$entry' $checked>$entry" . "&nbsp;";
            }
            print "</li>";
            // Referring Physician's Name filter
            print "<li>";
            $referfilter = array();
            $result = $dbcon->query("select distinct referringphysician from study where referringphysician is not NULL order by referringphysician asc");
            while ($result && ($row = $result->fetch(PDO::FETCH_NUM)))
                $referfilter[] = $row[0];
            print "<b><u>" . pacsone_gettext("Referring Physician's Name:") . "</u></b><br>";
            foreach ($referfilter as $entry) {
                $checked = (isset($current["referringphysician"]) && in_array(strtolower($entry), $current["referringphysician"]))? "checked" : "";
                print "<input type='checkbox' name='referringphysician[]' value='$entry' $checked>$entry" . "&nbsp;";
            }
            print "</li>";
            // Reading Physician's Name filter
            print "<li>";
            $readfilter = array();
            $result = $dbcon->query("select distinct readingphysician from study where readingphysician is not NULL order by readingphysician asc");
            while ($result && ($row = $result->fetch(PDO::FETCH_NUM)))
                $readfilter[] = $row[0];
            print "<b><u>" . pacsone_gettext("Reading Physician's Name:") . "</u></b><br>";
            foreach ($readfilter as $entry) {
                $checked = (isset($current["readingphysician"]) && in_array(strtolower($entry), $current["readingphysician"]))? "checked" : "";
                print "<input type='checkbox' name='readingphysician[]' value='$entry' $checked>$entry" . "&nbsp;";
            }
            print "</li>";
            // Institution Name filter
            print "<li>";
            $instfilter = array();
            $result = $dbcon->query("select distinct institution from patient where institution is not NULL order by institution asc");
            while ($result && ($row = $result->fetch(PDO::FETCH_NUM)))
                $instfilter[] = $row[0];
            print "<b><u>" . pacsone_gettext("Institution Name:") . "</u></b><br>";
            foreach ($instfilter as $entry) {
                $checked = (isset($current["institution"]) && in_array(strtolower($entry), $current["institution"]))? "checked" : "";
                print "<input type='checkbox' name='institution[]' value='$entry' $checked>$entry" . "&nbsp;";
            }
            print "</li>";
            print "</ul></td></tr>\n";
        }
        print "</table>\n";
        print "<p><input type='submit' name='action' value='";
        print pacsone_gettext("Modify");
        print "' onclick='switchText(this.form,\"actionvalue\",\"Modify\")'>\n";
        print "</form>\n";
    }
    else {
        print "<h3><font color=red>";
        $what = $group? "Group" : "User";
        printf(pacsone_gettext("%s <u>%s</u> not found in database"), $what, $user);
        print "</font></h3>\n";
    }
    require_once 'footer.php';
    print "</body>\n";
    print "</html>\n";
}

?>
