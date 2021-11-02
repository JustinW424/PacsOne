<?php
//
// security.php
//
// Module for displaying security messages
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
require_once 'locale.php';

$loginScript = "login.php";

if (!isset($_SESSION['authenticatedUser']))
{
    // IIS does not supply $SERVER['REQUEST_URI'] to PHP while Apache does
    $uri = isset($_SERVER['REQUEST_URI'])? $_SERVER['REQUEST_URI'] : $_SERVER['SCRIPT_NAME'];
    // save the request URL if any
    if (strlen($uri)) {
        if (!session_id())
            session_start();
        $_SESSION['requestUri'] = $uri;
    }
    if (!isset($_SESSION['authenticatedDatabase'])) {
        include_once "sharedData.php";
        global $PRODUCT;
        $message = sprintf(pacsone_gettext("Welcome to %s"), $PRODUCT);
    } else {
	    $message = pacsone_gettext("Not authorized to access this URL: ");
	    $message .= "http://" . $_SERVER['SERVER_NAME'] . $uri;
    }
    $message = urlencode($message);

    // back to login page
    header("Location: " . $loginScript . "?message=$message");
    exit;
}

include_once 'database.php';

// connect to database
$dbcon = new MyConnection();
$user = $dbcon->username;
if (!$dbcon->isAdministrator($user)) {
    // check if user password has expired
    $q = "select expire from privilege where username=? and expire < NOW()";
    if ($dbcon->useOracle)
        $q = "select expire from privilege where username=? and expire < SYSDATE";
    $bindList = array($user);
    $result = $dbcon->preparedStmt($q, $bindList);
    if ($result && $result->rowCount()) {
        header("Location: expired.php");
        exit;
    }
}
$now = time();
if (isset($_SESSION['lastActivity'])) {
    $lastActivity = $_SESSION['lastActivity'];
    $result = $dbcon->query("select autologout from config");
    if ($result) {
        $row = $result->fetch(PDO::FETCH_NUM);
        // get the automatic logout period in seconds
        $autologout = 60 * $row[0];
        if (($now - $lastActivity) > $autologout) {
            $message = sprintf(pacsone_gettext("User [%s] has been logged out automatically due to inactivity."), $user);
            // delete session cookie
            setcookie("sessionCookie", "", time() - 3600);
            unset($_SESSION['authenticatedUser']);
            // back to login page
            header("Location: login.php?message=" . urlencode($message));
            session_destroy();
            exit();
        }
    }
}
$_SESSION['lastActivity'] = $now;

?>
