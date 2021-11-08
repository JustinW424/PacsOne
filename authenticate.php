<?php
//
// authenticate.php
//
// Module for authenticating username/password of a PHP session
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

include_once 'database.php';
include_once 'locale.php';
require_once "ldap.php";

function checkDatabaseExtension(&$err)
{
    $loaded = false;
    global $ORACLE_CONFIG_FILE;
    $dir = dirname($_SERVER['SCRIPT_FILENAME']);
    $file = substr($dir, 0, strlen($dir) - 3) . $ORACLE_CONFIG_FILE;
    if (file_exists($file)) {
        if (extension_loaded("pdo_oci"))
            $loaded = true;
        else
            $err = pacsone_gettext("'pdo_oci' PHP extension is required but has not been loaded");
    } else {
        if (extension_loaded("pdo_mysql"))
            $loaded = true;
        else
            $err = pacsone_gettext("'pdo_mysql' PHP extension is required but has not been loaded");
    }
    return $loaded;
}

function getFailedLoginDir()
{
    $dir = dirname($_SERVER['SCRIPT_FILENAME']);
    $dir = substr($dir, 0, strlen($dir) - 3) . "FailedLogin/";
    if (!file_exists($dir))
        mkdir($dir);
    return $dir;
}

function getLoginAttempts($username, &$timestamp)
{
    $attempts = 0;
    $file = getFailedLoginDir() . $username;
    if (file_exists($file)) {
        $timestamp = filemtime($file);
        $handle = fopen($file, "r");
        if ($handle) {
            $attempts = (int)fgets($handle);
            fclose($handle);
        }
    }
    return $attempts;
}

// main
global $MAX_LOGIN_ATTEMPTS;
global $LOCKOUT_HOURS;

// connect to database
$action = $_POST['loginvalue'];
$database = isset($_POST["formDatabase"])? urldecode($_POST["formDatabase"]) : "";
$hostname = "localhost";
$aetitle = $_POST['aetitle'];
$ini = parseIniByAeTitle($aetitle);
if (isset($ini['Schema']))
    $_SESSION['Schema'] = $ini['Schema'];
if (isset($ini['DatabaseHost']))
    $hostname = $ini['DatabaseHost'];

echo("authenticate.php-74-".$action);

if (strcasecmp($action, "signup") == 0) {
    $url = "userSignup.php?hostname=" . urlencode($hostname) . "&database=" . urlencode($database);
    header("Location: $url");
    exit();
}
$user = urldecode($_POST["formUsername"]);
if (strstr($user, "..") || strstr($user, "/")) {
    print "<p><h3><font color=red>";
    printf(pacsone_getetxt("Invalid Username: <u>%s</u>"), $user);
    print "</font></h3>";
    exit();
}
// check if user has reached the maximum allowed login attempts
$timestamp = null;
$attempts = getLoginAttempts($user, $timestamp);
$timestamp = time() - $timestamp;
// if (($attempts >= $MAX_LOGIN_ATTEMPTS) && ($timestamp < $LOCKOUT_HOURS * 3600)) {
//     print "<p><h3><font color=red>";
//     printf(pacsone_gettext("You have been locked out because you have reached the maximum allowed %d login attempts."), $MAX_LOGIN_ATTEMPTS);
//     print "<p>";
//     printf(pacsone_gettext("Please reconnect in %d hours again."), $LOCKOUT_HOURS);
//     print "</font></h3>";
//     exit();
// }
$password = urldecode($_POST["formPassword"]);


echo("authenticate.php-100-".$user."-".$password);

$antispaminput = urldecode($_POST["formAntiSpam"]);
$dbcon = new MyDatabase($hostname, $database, $user, $password, $aetitle);
$err = "";
$loggedIn = $dbcon->connection;
// check if use LDAP for authentication
if (!$dbcon->isAdministrator($user) && isset($ini['LdapHost'])) {
    $ldapDn = "cn=" . $user;
    $ldapDn .= "," . $ini['DnSuffix'];
    $ldap = new ldapAPI($ini['LdapHost'], $ini['LdapPort'], $ldapDn, $password);
    $loggedIn = $ldap->isConnected();
    if (!$loggedIn)
        $err = $ldap->getLastError();
    else {
        unset($dbcon);
        $password = strtolower($aetitle . "-" . $ini['UTC']);
        $dbcon = new MyDatabase($hostname, $database, $user, $password, $aetitle);
    }
}

ob_start();

if ( isset($antispaminput) AND
     ($antispaminput == $_SESSION['antispamcode']) AND $loggedIn ) 
{
    $allow = true;
    if (!$dbcon->isAdministrator($user)) {
	    // save full name of user
	    $query = "SELECT firstname,lastname FROM privilege WHERE username=?";
        $bindList = array($user);
        $result = $dbcon->preparedStmt($query, $bindList);
        if ($result && $result->rowCount()) {
	        $row = $result->fetch(PDO::FETCH_NUM);
        } else {
            $allow = false;
        }
    }

    echo("authenticate.php-134-".$allow);

    if ($allow) {
        // log activity to system journal
        $value = get_client_ip();
        $dbcon->logJournal($user, "Login", $value, session_id());
        $_SESSION['clientip'] = $value;
        $_SESSION['aetitle'] = $aetitle;
        $_SESSION['authenticatedHost'] = $hostname;
        $_SESSION['authenticatedDatabase'] = $database;
        require_once 'authenticatedSession.php';
        $authenticated = new EncryptedSession($user, $password);
        // save encrypted username/password
        $_SESSION['authenticatedUser'] = $authenticated->getUsername();
        $_SESSION['authenticatedPassword'] = $authenticated->getPassword();
	    if (isset($row) && isset($row[0])) {
		    $fullname = strcmp($row[0], "_GROUP")? $row[0] : "";
		    if (isset($row[1]))
			    $fullname .= " " . $row[1];
		    $_SESSION['fullname'] = $fullname;
	    }
        // reset failed login attempt counter
        $file = getFailedLoginDir() . $user;
        if (file_exists($file))
            unlink($file);
    } else {
        // do not allow login using reserved username
        $message = sprintf(pacsone_gettext("Username: '%s' is reserved"), $user);
        $message .= pacsone_gettext("\nPlease login using a different username/password.\n");
    }
} else if (!checkDatabaseExtension($err)) {
    $message = $err;
} else {
    // authentication failed
    $message = sprintf(pacsone_gettext("Could not connect to database '%s'@'%s' as User: '%s'"), $database, $hostname, $user);
    $message .= pacsone_gettext("\nPlease check your username and password.\n");
    if (strlen($err))
        $message .= $err . "\n";
    if ($LOCKOUT_HOURS) {
        $message .= sprintf(pacsone_gettext("If you failed to login after the maximum allowed %d attempts, this username will be locked out for %d hours before it can be used to login again"), $MAX_LOGIN_ATTEMPTS, $LOCKOUT_HOURS);
        // record the failed login attempt
        $attempts++;
        $file = getFailedLoginDir() . $user;
        if ($handle = fopen($file, "w")) {
            fwrite($handle, $attempts);
            fclose($handle);
        }
    }
}
// back to login page
$url = "Location: login.php";
if (isset($message)) {
    $message = urlencode($message);
    $url .= "?message=$message";
}
header($url);
ob_end_flush();
?>
