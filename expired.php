<?php
//
// expired.php
//
// Module for processing expired user password
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once "locale.php";
include_once 'database.php';
include_once 'sharedData.php';

// connect to database
$dbcon = new MyConnection();
$username = $dbcon->username;

global $PASSWD_SPECIAL_CHARS;
if (!isset($_POST['modified']))
{
    global $PRODUCT;
    print "<html>\n";
    print "<head><title>$PRODUCT - " . pacsone_gettext("User Password Expired") . "</title></head>\n";
    print "<body>\n";
    require_once 'header.php';
    include_once 'checkInput.js';

    print "<h3><font color=red>" . pacsone_gettext("Your password has expired!") . "</font></h3>\n";
    print "<p><b>" . pacsone_gettext("Please reset a new password below:") . "</b>\n";
    print "<form method='POST' action='expired.php' onSubmit='return checkPassword(this);'>\n";
    print "<input type='hidden' name='modified' value=1>\n";
    print "<table width='30%' border='0' cellspacing='0' cellpadding='1'>\n";
    print "<tr><td>" . pacsone_gettext("Enter your existing password:") . "</td>";
    print "<td><input type='password' name='oldPassword' size=16 maxlength=64></td></tr>\n";
    print "<tr><td>";
    printf(pacsone_gettext("Enter your new password: (must be at least 8 characters and include 1 number, 1 capital letter and 1 special character from \"%s\")"), $PASSWD_SPECIAL_CHARS);
    print "</td>";
    print "<td><input type='password' name='newPassword' size=16 maxlength=64></td></tr>\n";
    print "<tr><td>" . pacsone_gettext("Re-enter the new password:") . "</td>";
    print "<td><input type='password' name='newPassword2' size=16 maxlength=64></td></tr>\n";
    print "<tr><td colspan='2'>&nbsp;</td></tr>\n";
    print "<tr><td><input type='submit' value='";
    print pacsone_gettext("Change Password");
    print "'></td></tr>";
    print "</form></table>\n";
    require_once 'footer.php';
    print "</body>\n";
    print "</html>\n";
}
else
{
    // get the password expiration period
    $result = $dbcon->query("select passwordexpire from config");
    $expire = $result->fetchColumn();
    $oldPassword = $_POST['oldPassword'];
    $newPassword = $_POST['newPassword'];
    if (!validatePassword($newPassword)) {
        $error = sprintf(pacsone_gettext("A valid new password must be at least 8 characters, must contain at least 1 number, 1 capital letter, and 1 special character from %s"), $PASSWD_SPECIAL_CHARS);
        alertBox($error, "expired.php");
        exit();
    }
    if ($dbcon->useOracle) {
        $q = "alter user ? identified by ?";
        $bindList = array($username, $newPassword);
    } else {
        if (versionCompare($dbcon->version, 5, 7, 6) < 0) {
            $q = "set password = password(?)";
            $bindList = array($newPassword);
        } else {
            $hostname = $dbcon->getHostname();
            $q = "alter user ?@? identified by ?";
            $bindList = array($username, $hostname, $newPassword);
        }
    }
    if (!$dbcon->preparedStmt($q, $bindList)) {
        $error = sprintf(pacsone_gettext("Unable to change user password for [<b>%s</b>]: %s"), $username, $dbcon->getError());
        die("<h3><font color=red>$error</font></h3>");
    }
    // setup the next password expiration
    $bindList = array($username);
    $q = "update privilege set expire=DATE_ADD(NOW(), INTERVAL $expire DAY) where username=?";
    if ($dbcon->useOracle)
        $q = "update privilege set expire=(SYSDATE+$expire) where username=?";
    $dbcon->preparedStmt($q, $bindList);
    // update session variable
    require_once 'authenticatedSession.php';
    $authenticated = new EncryptedSession($username, $newPassword);
    $_SESSION['authenticatedPassword'] = $authenticated->getPassword();
    // back to the Login page
    header("Location: login.php");
}

?>
