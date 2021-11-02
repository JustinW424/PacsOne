<?php
//
// header.php
//
// Common HTML header file
//
// CopyRight (c) 2003-2017 RainbowFish Software
//
if (!session_id())
    session_start();

require_once "locale.php";
include_once 'database.php';
include_once 'sharedData.php';

global $BGCOLOR;
$dbcon = new MyConnection();
$database = $dbcon->database;
$username = $dbcon->username;
print "<LINK rel=\"stylesheet\" type=\"text/css\" href=\"template_style.css\">";
print "<table width=\"100%\" border=\"0\" cellspacing=\"1\" cellpadding=\"0\">";
print "<tr><td><table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"5\">";
print "<tr style=\"background-color: $BGCOLOR;color: white; font-weight: bold\">";
print "<td align=\"left\" valign=\"middle\">";

$view = $dbcon->hasaccess("viewprivate", $username);
$modify = $dbcon->hasaccess("modifydata", $username);
$query = $dbcon->hasaccess("query", $username);
if (isset($_SESSION['fullname']))
	$fullname = $_SESSION['fullname'];
if (isset($_SESSION['authenticatedUser'])) {
    $url = "logout.php";
    $value = sprintf("%s %s @ %s",
        pacsone_gettext("Logout"),
        isset($fullname)? $fullname : $username,
        $database);
} else {
    $url = "login.php";
    $value = pacsone_gettext("Login");
}
print "<a href='$url'>$value</a><br></td>";
print "<td align='right' valign='middle'>";
if ($dbcon->isAdministrator($username)) {
    $ini = parseIniByAeTitle($_SESSION['aetitle']);
    if (isset($ini['LdapHost']))
	    print "<a href='ldapUser.php'>" . pacsone_gettext("LDAP User Administration") . "</a> | ";
    else
	    print "<a href='user.php'>" . pacsone_gettext("User Administration") . "</a> | ";
}
if ($dbcon->hasaccess("admin", $username)) {
	print "<a href='config.php'>" . pacsone_gettext("Configuration") . "</a> | ";
	print "<a href='email.php'>" . pacsone_gettext("Email") . "</a> | ";
	print "<a href='journal.php'>" . pacsone_gettext("Journal") . "</a> | ";
}
$menu = array();
$menu[pacsone_gettext("Home")] = "home.php";
$menu[pacsone_gettext("Unread Studies")] = "unread.php";
$menu[pacsone_gettext("Browse")] = "browse.php";
$menu[pacsone_gettext("Search")] = "search.php";
if ($view || $modify || $query) {
    $menu[pacsone_gettext("Dicom AE")] = "applentity.php";
    // check if the HL-7 listener option is installed
    if (isHL7OptionInstalled())
        $menu[pacsone_gettext("HL7 Application")] = "hl7app.php";
    $menu[pacsone_gettext("Auto Route")] = "autoroute.php";
}
$menu[pacsone_gettext("Job Status")] = "status.php";
if ($view || $query) {
    $menu[pacsone_gettext("Modality Worklist")] = "worklist.php";
}
$menu[pacsone_gettext("Tools")] = "tools.php#end";
$encoded = urlencode($username);
$menu[pacsone_gettext("Profile")] = "profile.php?user=$encoded";
$menu[pacsone_gettext("Help")] = "manual.pdf";
print "<b>";
$total = count($menu);
$index = 1;
foreach ($menu as $key => $url) {
    print "<a href='$url'>$key</a>";
    if ($index++ < $total)
        print "&nbsp;|&nbsp;";
}
print "</b>";
print "</td>";
// check if small logo exists
$dir = dirname($_SERVER["SCRIPT_FILENAME"]);
if (file_exists("$dir/smallLogo.jpg")) {
    print "<td align='right' valign='bottom'><img src=\"smallLogo.jpg\"></td>";
}
print "</tr></table>";
print "</td></tr>";
print "<tr><td>";
?>
