<?php
//
// searchUser.php
//
// Module for searching registered users
//
// CopyRight (c) 2014-2020 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once 'security.php';
include_once 'display.php';

global $PRODUCT;
print "<html>";
print "<head>";
print "<title>$PRODUCT - ";
print pacsone_gettext("Registered User Search Results:");
print "</title></head>";
print "<body>";

require_once 'header.php';
include_once 'utils.php';

$user = isset($_REQUEST['username'])? urldecode($_REQUEST['username']) : "";
$last = isset($_REQUEST['lastname'])?urldecode($_REQUEST['lastname']) : "";
$first = isset($_REQUEST['firstname'])? urldecode($_REQUEST['firstname']) : "";
$email = isset($_REQUEST['email'])? urldecode($_REQUEST['email']) : "";

$dbcon = new MyConnection();
$error = searchUser($user, $last, $first, $email);
if ($error) {
    print "<font color=red>$error</font>";
}
require_once 'footer.php';
print "</body>";
print "</html>";

function searchUser($username, $last, $first, $email)
{
    global $dbcon;
	$error = "";
    // make sure first char of any search pattern is not a wild-card char
    if (isWildcardFirst($username) || isWildcardFirst($last) ||
        isWildcardFirst($first) || isWildcardFirst($email)) {
        return pacsone_gettext("First character of registered user search pattern cannot be wild-card chars like '*' or '?'");
    }
    // automatically append wild-card character
    if (strlen($username) && isset($_REQUEST['wilduser']))
        $username .= "*";
    if (strlen($last) && isset($_REQUEST['wildlast']))
        $last .= "*";
    if (strlen($first) && isset($_REQUEST['wildfirst']))
        $first .= "*";
    if (strlen($email) && isset($_REQUEST['wildemail']))
        $email .= "*";
	$query = "SELECT * from privilege where ";
    $key = "";
    $value = "";
    $bindList = array();
    // build query string based on form input
    if (strlen($username)) {
        if (strlen($key))
            $key .= " AND ";
        $key .= "username" . preparedStmtWildcard($username, $value);
        $bindList[] = $value;
    }
    if (strlen($last)) {
        if (strlen($key))
            $key .= " AND ";
        $key .= "lastname" . preparedStmtWildcard($last, $value);
        $bindList[] = $value;
    }
    if (strlen($first)) {
        if (strlen($key))
            $key .= " AND ";
        $key .= "firstname" . preparedStmtWildcard($first, $value);
        $bindList[] = $value;
    }
    if (strlen($email)) {
        if (strlen($key))
            $key .= " AND ";
        $key .= "email" . preparedStmtWildcard($email, $value);
        $bindList[] = $value;
    }
    if (strlen($key) == 0) {
        $error = "<br>";
        $title = pacsone_gettext("Search Registered User");
        $url = urlencode($title);
        $error .= sprintf(pacsone_gettext("You must enter a criteria for <a href='search.php?page=$url'>%s</a>"), $title);
        return $error;
    }
    $query .= $key;
    if (count($bindList))
        $result = $dbcon->preparedStmt($query, $bindList);
    else
        $result = $dbcon->query($query);
    if ($result) {
        $num_rows = $result->rowCount();
        $preface = sprintf(pacsone_gettext("%d matching %s found."),
            $num_rows,
            ($num_rows > 1)? pacsone_gettext("users") : pacsone_gettext("user"));
        displayUsers($result, $preface);
    }
    return $error;
}

?>
