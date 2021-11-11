<?php
//
// user.php
//
// User Administration page for local database
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once 'security.php';
include_once 'display.php';
include_once "tabbedpage.php";

// single-instance User Administration page
class SingleInstancePage extends TabbedPage {
    var $dbcon;

    function __construct(&$dbcon) {
        $this->dbcon = $dbcon;
        // get the AE Title configured for this instance
        $result = $this->dbcon->query("SELECT aetitle FROM config");
        $this->title = pacsone_gettext("Single-Instance User Administration");
        if ($result && ($row = $result->fetch(PDO::FETCH_NUM))) {
            $this->title = sprintf(pacsone_gettext("Server Instance <u>%s</u>"), $row[0]);
        }
        $this->url = "user.php?type=0";
    }
    function __destruct() { }
    function showHtml() {
        // check if the internal username/password has been created for local database

        $result = $this->dbcon->query("SELECT * FROM privilege WHERE firstname!='_GROUP'");
        $num_rows = $result->rowCount();
        // display total number of patient records in database
        if ($num_rows > 1)
            $preface = sprintf(pacsone_gettext("There are %d user accounts in PACS database."), $num_rows);
        else
            $preface = sprintf(pacsone_gettext("There is %d user account in PACS database."), $num_rows);
        displayUsers($result, $preface);
        print "<p>";
        // display user groups
        $result = $this->dbcon->query("SELECT * FROM privilege WHERE firstname='_GROUP' and usergroup is NULL");
        $num_rows = $result->rowCount();
        if ($num_rows > 1)
            $preface = sprintf(pacsone_gettext("There are %d user groups in PACS database."), $num_rows);
        else
            $preface = sprintf(pacsone_gettext("There is %d user group in PACS database."), $num_rows);
        displayGroups($result, $preface);
        // display user signup requests
        $result = $this->dbcon->query("SELECT * FROM usersignup");
        $num_rows = $result->rowCount();
        if ($num_rows > 1)
            $preface = sprintf(pacsone_gettext("There are %d user sign-up requests."), $num_rows);
        else
            $preface = sprintf(pacsone_gettext("There is %d user sign-up request."), $num_rows);
        displayUserSignups($result, $preface);
    }
}

// multi-instance User Administration page
class MultiInstancePage extends TabbedPage {
    var $dbcon;
    var $instances;

    function __construct(&$dbcon, &$instances) {
        $this->dbcon = $dbcon;
        $this->instances = $instances;
        $this->title = pacsone_gettext("Multi-Instance User Administration");
        $this->url = "user.php?type=1";
    }
    function __destruct() { }
    function showHtml() {

        print "<form method='POST' action='multiInstanceUser.php'>\n";
        print "<p><table border=0 cellpadding=2 cellspacing=0>\n";
        print "<tr><td>";
        print "<input type='radio' name='configtype' value=0 checked>&nbsp;";
        print pacsone_gettext("Configure All Users for Server Instance: ");
        $titles = array_keys($this->instances);
        print "<select name='instance'>";
        foreach ($titles as $aetitle)
            print "<option>$aetitle</option>";
        print "</select></td></tr>";
        print "<tr><td>";
        print "<input type='radio' name='configtype' value=1>&nbsp;";
        $users = $this->dbcon->getAllUsers(array_values($this->instances));
        print pacsone_gettext("Configure Multiple Server Instances for User: ");
        print "<select name='shareduser'>";
        foreach ($users as $user)
            print "<option>$user</option>";
        print "<option>";
        print pacsone_gettext("Add New Shared User for Multiple Server Instances");
        print "</option>";
        print "</select></td></tr>";
        print "</table>";
        print "<p><input type='submit' value='" . pacsone_gettext("Submit") . "'>";
        print "</form>";
    }
}

// main
global $PRODUCT;
print "<html>";
print "<head><title>$PRODUCT - ";
print pacsone_gettext("User Administration");
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
$type = 0;  // default to Single-Instance User Administration page
if (isset($_REQUEST['type']))
	$type = $_REQUEST['type'];
$pages = array(
    (new SingleInstancePage($dbcon)),
);
$instances = getServerInstances();

if (count($instances) > 1)
    $pages[] = new MultiInstancePage($dbcon, $instances);
$current = $pages[$type]->title;

$tabs = new tabs($pages, $current);
$tabs->showHtml();

require_once 'footer.php';
print "</body>";
print "</html>";

?>
