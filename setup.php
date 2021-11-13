<?php
//
// setup.php
//
// Setup page for local database
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once 'sharedData.php';

function login($errorMessage)
{
    global $PRODUCT;
    print "<html>\n";
    print "<head><title>$PRODUCT - ";
    print pacsone_gettext("Database Setup");
    print "</title></head>\n";
    print "<body>\n";
    print "<form method='POST' action='setup.php'>\n";
    print "</body>\n";
    print "</html>\n";
    // include the formatted error message
    if (isset($errorMessage))
        echo "<h3><font color=red>$errorMessage</font></h3>";
    print "<table border=0 cellspacing=5 cellpadding=0>\n";
    print "<tr><td>";
    print pacsone_gettext("Database Host:") . "</td>\n";
    print "<td><input type=text size=16 maxlength=64 name='formHost' value='localhost'></td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Database:") . "</td>\n";
    print "<td><input type=text size=16 name='formDatabase' value='mysql' readonly></td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Database Administrator Username:") . "</td>\n";
    print "<td><input type=text size=16 name='formUsername' value='root' readonly></td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Enter Password for Database Administrator <b>root</b>:</td>") . "\n";
    print "<td><input type=password size=16 maxlength=16 name='formPassword'></td></tr>\n";
	print "<tr><td></td></tr>\n";
	print "<tr><td></td></tr>\n";
	print "<tr><td>";
    printf(pacsone_gettext("Create Database for %s:"), $PRODUCT) . "</td>\n";
	print "<td><input type=text size=16 maxlength=16 name='dbname'></td></tr>\n";
	print "<tr><td>";
    printf(pacsone_gettext("Create Database Username for %s:"), $PRODUCT) . "</td>\n";
	print "<td><input type=text size=16 maxlength=16 name='dbuser'></td></tr>\n";
	print "<tr><td>";
    printf(pacsone_gettext("Create Database Password for %s:"), $PRODUCT) . "</td>\n";
	print "<td><input type=password size=16 maxlength=16 name='dbpasswd'></td></tr>\n";
    print "</table>\n";
    print "<p><input class='btn btn-primary' type=submit value='";
    print pacsone_gettext("Setup");
    print "'></form>\n";
	print "<p><b>";
    printf(pacsone_gettext("Please use the above Database, Username and Password information when installing %s."), $PRODUCT) . "</b>\n";
    print "</body></html>\n";
}

// main
global $PRODUCT;
if (!isset($_REQUEST['dbname']))
{
	if (isset($_REQUEST['message']))
		login($_REQUEST['message']);
	else
    	login(sprintf(pacsone_gettext("Please login as 'root' to setup database for %s"), $PRODUCT));
}
else
{
	include_once "database.php";
	// connect to database
	$hostname = urldecode($_POST["formHost"]);
	$database = urldecode($_POST["formDatabase"]);
	$user = urldecode($_POST["formUsername"]);
	$password = urldecode($_POST["formPassword"]);
	if (($dbcon = new MyDatabase($hostname, $database, $user, $password)))
	{
		$dbname = urldecode($_POST["dbname"]);
		$dbuser = urldecode($_POST["dbuser"]);
		$dbpasswd = urldecode($_POST["dbpasswd"]);
		// create database
		$query = "create database $dbname";
		if (!$dbcon->query($query))
			$error = "<p>Failed to create database $dbname:" . $dbcon->getError();
        else {
		    // create database user account for local or remote database
            $hostname = strcasecmp($hostname, "localhost")? "%" : "localhost";
		    $query = "create user ?@? identified by ?";
            $bindList = array($dbuser, $hostname, $dbpasswd);
		    if (!$dbcon->preparedStmt($query, $bindList))
			    $error = "<p>" . sprintf(pacsone_gettext("Failed to create Database User %s: "), $dbuser) . $dbcon->getError();
            else {
		        $query = "grant all privileges on $dbname.* to ?@?";
                $bindList = array($dbuser, $hostname);
		        if (!$dbcon->preparedStmt($query, $bindList))
			        $error = "<p>" . sprintf(pacsone_gettext("Failed to setup privileges for Database User %s: "), $dbuser) . $dbcon->getError();
                else {
		            $dbcon->query("flush privileges");
		            $message = "<p>" . pacsone_gettext("Success!");
		            $message .= "<p>";
                    $message .= sprintf(pacsone_gettext("Database: <b>%s</b> and User: <b>%s</b> have been created successfully for %s. "), $dbname, $dbuser, $PRODUCT);
		            $message .= "<p>";
                    $message .= sprintf(pacsone_gettext("Please run <b>Setup.exe</b> of the %s installation pacakge to complete the installation of %s. "), $PRODUCT, $PRODUCT);
		            $message .= sprintf(pacsone_gettext("After %s has been installed successfully, press <a href='home.php'>Here</a> to login. "), $PRODUCT);
                    $message .= pacsone_gettext("To create more users or set up user priviledges, please <a href='home.php'>Login</a> as Administrator <b>root</b> and select the <font color=blue>User Administration</font> menu.");
		            print $message;
                }
            }
        }
	}
	else
	{
    	// authentication failed
    	$error = "<p>";
        $error .= pacsone_gettext("Login failed. Please check your username and password.");
	}
	if (isset($error)) {
		// back to login page
		header("Location: setup.php?message=$error");
	}
}

?>
