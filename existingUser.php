<?php
//
// existingUser.php
//
// Page for upgrading existing users to the new privilege scheme
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once "locale.php";
include_once 'security.php';
include_once 'display.php';

// main
global $PRODUCT;
print "<html>";
print "<head><title>$PRODUCT - " . pacsone_gettext("Upgrade Existing Users") . "</title></head>";
print "<body>";
require_once 'header.php';

$external = 1;
if (isset($_GET['external']))
    $external = $_GET['external'];
$dbcon = new MyConnection();
$database = $dbcon->database;
$username = $dbcon->username;
if (!$dbcon->isAdministrator($username))
	die ("<font color=red>You must login as <b>Administrator</b> to manage user accounts</font>");
// find existing users
$users = $dbcon->findExistingUsers();
// check if the users are upgradable
$eligible = array();
$dbcon->selectDb($database);
foreach ($users as $user) {
    $bindList = array($user);
	$result = $dbcon->preparedStmt("SELECT username from privilege WHERE username=?", $bindList);
	if ($external && !($row = $result->fetch(PDO::FETCH_NUM)))
		$eligible[] = $user;
	else if (!$external && ($row = $result->fetch(PDO::FETCH_NUM)))
		$eligible[] = $user;
}
$count = count($eligible);
// display existing usernames in database
$plural = ($count > 1)? "users" : "user";
displayExistingUsers($eligible, "There are $count existing $plural that can be upgraded:", $external);

require_once 'footer.php';
print "</body>";
print "</html>";

?>
