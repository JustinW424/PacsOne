<?php
//
// logout.php
//
// Module for logging out user from an active PHP session
//
// CopyRight (c) 2003-2017 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once 'database.php';

$dbcon = new MyConnection();
$user = $dbcon->username;
// log activity to system journal
$dbcon->logJournal($user, "Logout", $_SESSION['clientip'], session_id());
$message = sprintf(pacsone_gettext("User [%s] has logged out successfully."), $user);
setcookie("sessionCookie", "", time() - 3600);
unset($_SESSION['authenticatedUser']);
// back to login page
header("Location: login.php?message=" . urlencode($message));
session_destroy();
?>
