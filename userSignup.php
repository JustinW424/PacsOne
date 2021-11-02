<?php
//
// userSIgnup.php
//
// Module for new web user signup (needs Administrator's approval)
//
// CopyRight (c) 2014-2020 RainbowFish Software
//
require_once 'locale.php';
include_once 'database.php';
include_once 'sharedData.php';
error_reporting(E_ERROR);

// main
global $PRODUCT;
global $SIGNUP_USERNAME;
global $SIGNUP_PASSWORD;
$hostname = urldecode($_REQUEST['hostname']);
$database = urldecode($_REQUEST['database']);
$dbcon = new MyDatabase($hostname, $database, $SIGNUP_USERNAME, $SIGNUP_PASSWORD);
if (!$dbcon->connection) {
    $err = pacsone_gettext("Failed to connect to database: [$database] for user signup");
    die("<font color=red>$err</font>");
}
$result = NULL;
if (isset($_REQUEST['username']) && strlen($_REQUEST['username'])) {
    if (preg_match("/[\W+$]/", $_REQUEST['username']))
        die("<font color=red>" . pacsone_gettext("Invalid character in specified username") . "</font>");
    $result = signupUser($dbcon, urldecode($_REQUEST['username']));
    print "<html>\n";
    print "<head><title>$PRODUCT - ";
    print empty($result)? pacsone_gettext("User Sign-Up Submitted") : pacsone_gettext("User Sign-Up Error");
    print "<body>";
    print "</title></head>\n";
    require_once 'header.php';
    if (empty($result)) {   // success
        print "<p><p>";
        print pacsone_gettext("User Sign-Up Request submitted successfully. Please wait for a confirmation email from the Administrator to verify the new user account.");
        print "<p><p>";
    }
    else {                  // error
        print "<font color=red>\n";
        print $result;
        print "</font>";
    }
    require_once 'footer.php';
    print "</body>\n";
    print "</html>\n";
} else {
    addEntryForm($hostname, $database);
}

function addEntryForm(&$hostname, &$db)
{
    require_once 'header.php';
    global $PRODUCT;
    print "<html>\n";
    print "<head><title>$PRODUCT - ";
    print pacsone_gettext("User Sign Up");
    print "</title></head>\n";
    print "<body>\n";
    print "<p>";
    print "<form method='POST' action='userSignup.php'>\n";
    print "<input type=hidden name='hostname' value='$hostname'>";
    print "<input type=hidden name='database' value='$db'>";
    print "<table width=60% border=0 cellspacing=0 cellpadding=5>\n";
    print "<tr><td>";
    print pacsone_gettext("Enter Username (up to 16 characters):");
    print "</td>\n";
    print "<td><input type='text' size=16 maxlength=16 name='username'></td></tr>\n";
    print "<tr><td>" . pacsone_gettext("Enter Password (up to 16 characters):") . "</td>\n";
    print "<td><input type='password' size=16 maxlength=16 name='password'></td></tr>\n";
    print "<tr><td>" . pacsone_gettext("Enter User's First Name (up to 20 characters):") . "</td>\n";
    print "<td><input type='text' size=20 maxlength=20 name='firstname'></td></tr>\n";
    print "<tr><td>" . pacsone_gettext("Enter User's Last Name (up to 20 characters):") . "</td>\n";
    print "<td><input type='text' size=20 maxlength=20 name='lastname'></td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Enter User's Email Address (up to 64 characters):");
    print "</td>\n";
    print "<td><input type='text' size=64 maxlength=64 name='email'></td></tr>\n";
    print "</table>\n";
    print "<p><input type='submit' name='action' value='";
    print pacsone_gettext("Sign Up");
    print "' onclick='switchText(this.form,\"actionvalue\",\"Add\")'></form>\n";
    require_once 'footer.php';
    print "</body>\n";
    print "</html>\n";
}

function signupUser(&$dbcon, &$username)
{
    $result = NULL;
    $password = $_REQUEST['password'];
    $first = $_REQUEST['firstname'];
    $last = $_REQUEST['lastname'];
    $email = $_REQUEST['email'];
    $sql = "insert into usersignup (username,password,firstname,lastname,email,submitted) ";
    $encoded = base64_encode($password);
    $sql .= "values(?,?,?,?,?,";
    $bindList = array($username, $encoded, $first, $last, $email);
    $sql .= $dbcon->useOracle? "SYSDATE" : "NOW()";
    $sql .= ")";
    if (!$dbcon->preparedStmt($sql, $bindList)) {
        $result = "Fail to run query [$sql], error = " . $dbcon->getError();
    }
    return $result;
}

?>
