<?php
//
// multiInstanceUser.php
//
// User Administration page for multiple server instances of PacsOne Server
//
// CopyRight (c) 2009-2020 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once 'security.php';
include_once 'database.php';
include_once 'utils.php';
include_once 'sharedUserAdmin.php';
include_once 'checkUncheck.js';

//
// privilege table:
//
// table column name => array(description, default value)
//
$PRIVILEGES = array(
    "viewprivate"   => array(pacsone_gettext("View Private Data"), 0),
    "modifydata"    => array(pacsone_gettext("Modify"), 0),
    "forward"       => array(pacsone_gettext("Forward"), 1),
    "query"         => array(pacsone_gettext("Query Remote AE"), 1),
    "move"          => array(pacsone_gettext("Move"), 1),
    "download"      => array(pacsone_gettext("Download"), 1),
    "print"         => array(pacsone_gettext("Print"), 1),
    "export"        => array(pacsone_gettext("Export"), 1),
    "import"        => array(pacsone_gettext("Import"), 1),
    "upload"        => array(pacsone_gettext("Upload"), 0),
    "monitor"       => array(pacsone_gettext("Monitor"), 0),
    "mark"          => array(pacsone_gettext("Mark Study"), 0),
    "admin"         => array(pacsone_gettext("System Administration"), 0),
);

function addSharedUser(&$dbcon, $instance, $db, $user, &$error)
{
    global $PRIVILEGES;
    global $FIELD_TBL;
    $result = $dbcon->query("select passwordexpire from $db.config");
    $expire = $result->fetchColumn();
    $query = "insert into $db.privilege (username";
    $fields = "";
    $values = "?";
    $bindList = array($user);
    foreach ($FIELD_TBL as $field => $quote) {
        if (array_key_exists($field, $PRIVILEGES)) {
            $fields .= ",$field";
            $values .= isset($_POST["$field-$instance"])? ",1" : ",0";
        } else if (isset($_POST[$field])) {
            $fields .= ",$field";
            $values .= ",?";
            $bindList[] = strlen($_POST[$field])? $_POST[$field] : null;
        }
    }
    // add default password expiration date
    $fields .= ",expire";
    if ($dbcon->useOracle)
        $values .= ",SYSDATE+$expire";
    else
        $values .= ",DATE_ADD(NOW(), INTERVAL $expire DAY)";
    $query .= "$fields) values($values)";
    // execute SQL query
    if (!$dbcon->preparedStmt($query, $bindList)) {
        $error = "Error running query: [$query], " . $dbcon->getError();
        return false;
    }
    // add user privileges
    $sysadmin = isset($_POST["admin-$instance"])? $_POST["admin-$instance"] : 0;
    if (!addPrivilege($db, $user, $error, $sysadmin)) {
        $error = sprintf(pacsone_gettext("Error setting up privileges for User <u>%s</u>: %s"), $user, $error);
        return false;
    }
    return true;
}

// main
global $PRODUCT;
print "<html>";
print "<head><title>$PRODUCT - ";
print pacsone_gettext("User Administration for Multiple Server Instances");
print "</title></head>";
print "<body>";
require_once 'header.php';

$dbcon = new MyConnection();
$username = $dbcon->username;
if (!$dbcon->isAdministrator($username)) {
	print "<h3><font color=red>";
    print pacsone_gettext("You must login as <b>Administrator</b> to manage user accounts");
    print "</font></h3>";
    exit();
}
$type = $_REQUEST['configtype'];
$instances = getServerInstances();
$addUser = false;
global $BGCOLOR;
if ($type == 0) {
    // configure all users for one server instance
    $instance = $_POST['instance'];
    $db = $instances[strtolower($instance)];
    if (isset($_POST['actionvalue'])) {
        $action = $_POST['actionvalue'];
        if (!strcasecmp($action, "Modify")) {
            $users = $_POST['users'];
            $error = false;
            foreach ($users as $user) {
                $query = "update $db.privilege set ";
                $data = "";
                foreach ($PRIVILEGES as $field => $value) {
                    if (strlen($data))
                        $data .= ",";
                    $value = (isset($_POST["$field-$user"]))? 1 : 0;
                    $data .= "$field=$value";
                }
                if (strlen($data)) {
                    $query .= $data . " where username=?";
                    $bindList = array($user);
                    if (!$dbcon->preparedStmt($query, $bindList)) {
                        $error = true;
                        print "<h3><font color=red>";
                        printf(pacsone_gettext("Failed to update privilege for username: <u>%s</u>"), $user);
                        print "<br>" . $dbcon->getError();
                        print "</font></h3>";
                    }
                }
            }
            if (!$error) {
                print "<script language=\"JavaScript\">\n";
                print "<!--\n";
                print "alert(\"";
                print pacsone_gettext("User Privileges Updated Successfully.");
                print "\");";
                print "//-->\n";
                print "</script>\n";
            }
        } else if (!strcasecmp($action, "Delete")) {
            $users = $_POST['entry'];
            foreach ($users as $user) {
                $error = "";
                // delete entry from User table
                if (!deleteUser($db, $user, $error))
                    $error = sprintf(pacsone_gettext("Failed to delete username %s from User table: "),
                                      $user) . $error;
                if (strlen($error)) {
                    print "<h3><font color=red>";
                    print "<p>$error";
                    print "</font></h3>";
                }
            }
        } else if (!strcasecmp($action, "Add")) {
            $addUser = true;
        }
    }
    if (!$addUser) {
        print "<p>";
        printf(pacsone_gettext("User privilege configurations for Server Instance:<u>%s</u>"), $instance);
        print "<p><table class='table table-hover table-bordered table-striped' width=100% border=0 cellpadding=3 cellspacing=1>";
        print "<form method='POST' action='multiInstanceUser.php'>";
        print "<input type='hidden' name='actionvalue'>";
        print "<tr class='tableHeadForBGUp'>";
        print "<td rowspan=2></td>";
        print "<td rowspan=2>";
        print pacsone_gettext("Username");
        $colspan = count($PRIVILEGES);
        print "</td><td colspan=$colspan align=center>";
        print pacsone_gettext("Privilege Settings");
        print "</td></tr>";
        print "<tr class='listhead' bgcolor=$BGCOLOR>";
        foreach ($PRIVILEGES as $field => $value) {
            $desc = $value[0];
            print "<td>$desc</td>";
        }
        print "</tr>";
        $result = $dbcon->query("select * from $db.privilege");
        $count = 0;
        while ($result && ($row = $result->fetch(PDO::FETCH_ASSOC))) {
            $bgclass = ($count++ & 0x1)? "oddrows" : "evenrows";
            print "<tr class='$bgclass'>";
            $username = $row["username"];
            print "<td align=center width='1%'>";
            print "<input type='checkbox' name='entry[]' value='$username'></td>";
            print "<input type='hidden' name='users[]' value='$username'>";
            print "<td>$username</td>";
            foreach ($PRIVILEGES as $field => $value) {
                $checked = $row[$field]? "checked" : "";
                print "<td><input type='checkbox' name='$field-$username' $checked></td>";
            }
            print "</tr>";
        }
        print "</table>";
        print "<p><table width=20% border=0 cellpadding=5>\n";
        print "<tr>";
	    if ($count) {
            $check = pacsone_gettext("Check All");
            $uncheck = pacsone_gettext("Uncheck All");
    	    print "<td><input type=button value='$check' onClick='this.value=checkAll(this.form,\"entry\", \"$check\", \"$uncheck\")'></td>\n";
    	    print "<td><input class='btn btn-primary' type=submit value='";
            print pacsone_gettext("Delete");
            print "' name='action' title='";
            print pacsone_gettext("Delete Selected Users");
            print "' onclick='switchText(this.form,\"actionvalue\",\"Delete\");return confirm(\"";
            print pacsone_gettext("Are you sure?");
            print "\");'></td>\n";
	    }
        print "<td><input type='submit' value='";
        print pacsone_gettext("Modify");
        print "' name='action' title='";
        print pacsone_gettext("Modify User Privilege Settings");
        print "' onclick='switchText(this.form,\"actionvalue\",\"Modify\")'></td>\n";
        print "<td><input class='btn btn-primary' type=submit value='";
        print pacsone_gettext("Add");
        print "' name='action' title='";
        print pacsone_gettext("Add New User");
        print "' onclick='switchText(this.form,\"actionvalue\",\"Add\")'></td>\n";
        print "</tr>\n";
        print "<input type='hidden' name='instance' value='$instance'>";
        print "<input type='hidden' name='configtype' value=0>";
        print "</table>";
        print "</form>";
    }
} else if (isset($_REQUEST['shareduser']) && ($type == 1)) {
    // configure multiple server instances for one shared user
    $user = $_REQUEST['shareduser'];
    $bindList = array($user);
    if (strstr($user, pacsone_gettext("Add New Shared User"))) {
        $addUser = true;
    } else if (isset($_POST['actionvalue'])) {
        $entries = $_POST['instances'];
        $error = "";
        $action = $_POST['actionvalue'];
        if (strcasecmp($action, "Modify") == 0) {
            $toadd = $_POST['toadd'];
            $allOk = true;
            foreach ($entries as $entry) {
                $db = $instances[strtolower($entry)];
                if (in_array($entry, $toadd)) {
                    // add this user to this instance
                    if (!addSharedUser($dbcon, $entry, $db, $user, $error)) {
                        $allOk = false;
                        foreach ($toadd as $entry) {
                            $db = $instances[strtolower($entry)];
                            $dbcon->preparedStmt("delete from $db.privilege where username=?", $bindList);
                        }
                        print "<h3><font color=red>";
                        printf(pacsone_gettext("Failed to add username: <u>%s</u>: %s"), $user, $error);
                        print "</font></h3>";
                    }
                } else {
                    // update privilege settings for this instance
                    $query = "update $db.privilege set ";
                    $data = "";
                    foreach ($PRIVILEGES as $field => $value) {
                        if (strlen($data))
                            $data .= ",";
                        $value = (isset($_POST["$field-$entry"]))? 1 : 0;
                        $data .= "$field=$value";
                    }
                    $query .= $data . " where username=?";
                    if (!$dbcon->preparedStmt($query, $bindList)) {
                        $allOk = false;
                        print "<h3><font color=red>";
                        printf(pacsone_gettext("Failed to update privilege for username: <u>%s</u>"), $user);
                        print "<br>" . $dbcon->getError();
                        print "</font></h3>";
                    }
                }
                if (!$allOk)
                    break;
            }
            if ($allOk) {
                print "<script language=\"JavaScript\">\n";
                print "<!--\n";
                print "alert(\"";
                print pacsone_gettext("User Privileges Updated Successfully.");
                print "\");";
                print "//-->\n";
                print "</script>\n";
            }
        } else if (!strcasecmp($action, "Delete")) {
            $allOk = true;
            foreach ($entries as $entry) {
                $db = $instances[strtolower($entry)];
                if (!deleteUser($db, $user, $error)) {
                    print "<h3><font color=red><p>";
                    printf(pacsone_gettext("Failed to delete username: <u>%s</u> from database: <u>%s</u>: %s"), $user, $db, $error);
                    print "</font></h3>";
                    $allOk = false;
                }
            }
            if ($allOk && ($dbcon->getSharedInstances($user) == 0)) {
                // user has been removed from all server instances
                header("Location: user.php?type=1");
                exit();
            }
        }
    }
    if (!$addUser) {
        print "<p>";
        printf(pacsone_gettext("Configure Multiple Server Instances for Shared User <u>%s</u>:"), $user);
        print "<p>";
        print "<form method='POST' action='multiInstanceUser.php'>\n";
        print "<input type='hidden' name='configtype' value=1>\n";
        print "<input type='hidden' name='actionvalue'>\n";
        print "<input type='hidden' name='shareduser' value='$user'>\n";
        print "<table width=100% border=1 cellspacing=0 cellpadding=5>\n";
        print "<tr><td colspan=3>";
        print "<p><table class='table table-hover table-bordered table-striped' width=100% border=0 cellpadding=3 cellspacing=1>";
        print "<tr class='tableHeadForBGUp'>";
        print "<td rowspan=2></td>";
        print "<td rowspan=2>";
        print pacsone_gettext("Server Instances");
        $colspan = count($PRIVILEGES);
        print "</td><td colspan=$colspan align=center>";
        print pacsone_gettext("Privilege Settings");
        print "</td></tr>";
        print "<tr class='listhead' bgcolor=$BGCOLOR>";
        foreach ($PRIVILEGES as $field => $value) {
            $desc = $value[0];
            print "<td>$desc</td>";
        }
        print "</tr>";
        $count = 0;
        $addInstance = false;
        $firstname = "";
        $lastname = "";
        $email = "";
        foreach ($instances as $instance => $db) {
            $bgclass = ($count++ & 0x1)? "oddrows" : "evenrows";
            $result = $dbcon->preparedStmt("select * from $db.privilege where username=?", $bindList);
            $checked = "checked";
            if (!$result || $result->rowCount() == 0) {
                print "<input type='hidden' name='toadd[]' value='$instance'>";
                $addInstance = true;
                $checked = "";
            }
            print "<tr class='$bgclass'>\n";
            print "<td align=center width='1%'>";
            print "<input type='checkbox' name='instances[]' value='$instance' $checked></td>\n";
            print "<td>$instance</td>\n";
            $row = $result->fetch(PDO::FETCH_ASSOC);
            if (!strlen($firstname))
                $firstname = $row["firstname"];
            if (!strlen($lastname))
                $lastname = $row["lastname"];
            if (!strlen($email))
                $email = $row["email"];
            foreach ($PRIVILEGES as $field => $value) {
                $checked = $row[$field]? "checked" : "";
                print "<td><input type='checkbox' name='$field-$instance' $checked></td>\n";
            }
            print "</tr>\n";
        }
        if ($addInstance) {
            $fields = array(
                "firstname"     => $firstname,
                "lastname"      => $lastname,
                "email"         => $email,
            );
            foreach ($fields as $field => $value) {
                $value = addslashes($value);
                print "<input type='hidden' name='$field' value='$value'>";
            }
        }
        print "</table>";
        print "</td></tr>";
        print "</table>\n";
        print "<p><table width=20% border=0 cellpadding=5>\n";
        print "<tr>";
        $check = pacsone_gettext("Check All");
        $uncheck = pacsone_gettext("Uncheck All");
        print "<td><input type=button value='$check' onClick='this.value=checkAll(this.form,\"instances\", \"$check\", \"$uncheck\")'></td>\n";
	    print "<td><input class='btn btn-primary' type=submit value='";
        print pacsone_gettext("Delete");
        print "' name='action' title='";
        print pacsone_gettext("Delete User From Selected Server Instances");
        print "' onclick='switchText(this.form,\"actionvalue\",\"Delete\");return confirm(\"";
        print pacsone_gettext("Are you sure?");
        print "\");'></td>\n";
        print "<td><input type='submit' name='action' value='";
        print pacsone_gettext("Modify");
        print "' title='";
        print pacsone_gettext("Modify User Privileges For Selected Server Instances");
        print "' onclick='switchText(this.form,\"actionvalue\",\"Modify\")'></td>\n";
        print "</tr></table></form>\n";
    }
} else {
    $addUser = true;
}
if ($addUser) {
    // add shared user for multiple server instances
    if (($type == 2) && isset($_POST['actionvalue'])) {
        $user = $_POST['user'];
        $passwd = urldecode($_POST['password']);
        if (strlen($passwd) == 0) {
            print "<h3><font color=red>";
            print pacsone_gettext("A valid password must be specified!");
            print "</font></h3>";
            exit();
        }
        if (!isset($_POST["lastname"]) || !strlen($_POST["lastname"])) {
            print "<h3><font color=red>";
            print pacsone_gettext("<u>Lastname</u> is a required field that must be filled in.");
            print "</font></h3><p>\n";
            exit();
        }
        // make sure the username does no already exist
        if ($dbcon->checkIfUserExists($user)) {
            print "<script language=\"JavaScript\">\n";
            print "<!--\n";
            print "alert(\"";
            printf(pacsone_gettext("Username: [%s] already exist!"), $user);
            print "\");";
            print "//-->\n";
            print "</script>\n";
            exit();
        }
        // create username
        $error = "";
        if (!createDbLogin($user, $passwd, $error)) {
            print "<h3><font color=red>";
            printf(pacsone_gettext("Error creating Database Username for User <u>%s</u>: "), $user);
            print $error;
            print "</font></h3><p>\n";
            exit();
        }
        global $FIELD_TBL;
        $entries = $_POST['instances'];
        $count = 0;
        $allOk = true;
        foreach ($entries as $entry) {
            $db = $instances[strtolower($entry)];
            if (!addSharedUser($dbcon, $entry, $db, $user, $error)) {
                $allOk = false;
                foreach ($entries as $entry) {
                    $db = $instances[strtolower($entry)];
                    $bindList = array($user);
                    $dbcon->preparedStmt("delete from $db.privilege where username=?", $bindList);
                }
    		    print "<h3><font color=red>";
                printf(pacsone_gettext("Failed to add username: <u>%s</u>: %s"), $user, $error);
                print "</font></h3><p>\n";
		        break;
            }
            // log activity to system journal
            $dbcon->logJournal($dbcon->getAdminUsername(), "Add", "User", $user, $db);
            $count++;
        }
        if (!$count) {
            removeDbLogin($user, $error);
        }
        if ($allOk)
            header("Location: user.php?type=1");
        exit();
    } else {
        print "<p>";
        print pacsone_gettext("Add the following new user:");
        print "<p>";
        print "<form method='POST' action='multiInstanceUser.php'>\n";
        print "<input type='hidden' name='configtype' value=2>\n";
        print "<input type='hidden' name='actionvalue'>\n";
        print "<table width=100% border=1 cellspacing=0 cellpadding=5>\n";
        print "<tr><td>";
        print pacsone_gettext("Enter Username:");
        print "</td>\n";
        print "<td>";
        print pacsone_gettext("This is the username to login to the database");
        print "</td>\n";
        print "<td><input type='text' size=16 maxlength=16 name='user'></td></tr>\n";
        print "<tr><td>" . pacsone_gettext("Enter Password:") . "</td>\n";
        print "<td>" . pacsone_gettext("Password for the database username above") . "</td>\n";
        print "<td><input type='password' size=16 maxlength=16 name='password'></td></tr>\n";
        print "<tr><td>" . pacsone_gettext("Enter User's First Name:") . "</td>\n";
        print "<td>" . pacsone_gettext("Firstname of the user (upto 20 characters)") . "</td>\n";
        print "<td><input type='text' size=20 maxlength=20 name='firstname'></td></tr>\n";
        print "<tr><td>" . pacsone_gettext("Enter User's Last Name:") . "</td>\n";
        print "<td>" . pacsone_gettext("Lastname of the user (upto 20 characters)") . "</td>\n";
        print "<td><input type='text' size=20 maxlength=20 name='lastname'></td></tr>\n";
        print "<tr><td>" . pacsone_gettext("Enter User's Middle Name:") . "</td>\n";
        print "<td>" . pacsone_gettext("Middlename of the user (upto 20 characters)") . "</td>\n";
        print "<td><input type='text' size=20 maxlength=20 name='middlename'></td></tr>\n";
        print "<tr><td>";
        print pacsone_gettext("Enter User's Email Address:");
        print "</td>\n";
        print "<td>";
        print pacsone_gettext("Email address of the User (upto 64 characters)");
        print "</td>\n";
        print "<td><input type='text' size=64 maxlength=64 name='email'></td></tr>\n";
        // whether or not to send email notification when new study arrives with this user being the Referring Physician
        print "<tr><td>";
        print pacsone_gettext("Email Notification:");
        print "</td><td>";
        print pacsone_gettext("Send Email Notification When New Study with This User Being the Referring Physician Has Arrived");
        print "</td><td>";
        print "<input type=radio name='notifynewstudy' value=0 checked>" . pacsone_gettext("No"); 
        print "<br><input type=radio name='notifynewstudy' value=1>" . pacsone_gettext("Yes"); 
        print "</td></tr>";
        // multi-instance privileges
        print "<tr><td colspan=3>";
        print "<p><table class='table table-hover table-bordered table-striped' width=100% border=0 cellpadding=3 cellspacing=1>";
        print "<tr class='tableHeadForBGUp' >";
        print "<td rowspan=2></td>";
        print "<td rowspan=2>";
        print pacsone_gettext("Server Instances");
        $colspan = count($PRIVILEGES);
        print "</td><td colspan=$colspan align=center>";
        print pacsone_gettext("Privilege Settings");
        print "</td></tr>";
        print "<tr class='listhead' bgcolor=$BGCOLOR>";
        foreach ($PRIVILEGES as $field => $value) {
            $desc = $value[0];
            print "<td>$desc</td>";
        }
        print "</tr>";
        $count = 0;
        foreach ($instances as $instance => $db) {
            $bgclass = ($count++ & 0x1)? "oddrows" : "evenrows";
            print "<tr class='$bgclass'>";
            print "<td align=center width='1%'>";
            print "<input type='checkbox' name='instances[]' value='$instance'></td>";
            print "<td>$instance</td>";
            foreach ($PRIVILEGES as $field => $value) {
                $checked = $value[1]? "checked" : "";
                print "<td><input type='checkbox' name='$field-$instance' $checked></td>";
            }
            print "</tr>";
        }
        print "</table>";
        print "</td></tr>";
        print "</table>\n";
        print "<p><table width=20% border=0 cellpadding=5>\n";
        print "<tr>";
	    if (count($instances)) {
            $check = pacsone_gettext("Check All");
            $uncheck = pacsone_gettext("Uncheck All");
    	    print "<td><input type=button value='$check' onClick='this.value=checkAll(this.form,\"instances\", \"$check\", \"$uncheck\")'></td>\n";
	    }
        print "<td><input type='submit' name='action' value='";
        print pacsone_gettext("Add");
        print "' onclick='switchText(this.form,\"actionvalue\",\"Add\")'></td>";
        print "</tr></table></form>\n";
    }
}

require_once 'footer.php';
print "</body>";
print "</html>";

?>
