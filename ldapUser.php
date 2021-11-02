<?php
//
// ldapUser.php
//
// User Administration page for remote LDAP users
//
// CopyRight (c) 2017-2020 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once 'security.php';
include_once 'display.php';

// main
global $PRODUCT;
global $LDAP_QUERY_USER_FILTER;
global $LDAP_QUERY_GROUP_FILTER;
print "<html>";
print "<head><title>$PRODUCT - ";
print pacsone_gettext("LDAP User Administration");
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
$aetitle = $_SESSION['aetitle'];
$ini = parseIniByAeTitle($aetitle);
$ldapHost = $ini['LdapHost'];
$ldapPort = $ini['LdapPort'];
$ldapCn = isset($ini['LdapCn'])? $ini['LdapCn'] : "";
$dnSuffix = isset($ini['DnSuffix'])? $ini['DnSuffix'] : "";
$userKey = isset($ini['UserKey'])? $ini['UserKey'] : "";
$userFilter = isset($ini['UserFilter'])? $ini['UserFilter'] : $LDAP_QUERY_USER_FILTER;
$groupKey = isset($ini['GroupKey'])? $ini['GroupKey'] : "";
$groupFilter = isset($ini['GroupFilter'])? $ini['GroupFilter'] : $LDAP_QUERY_GROUP_FILTER;
if (isset($_GET['status'])) {
    print "<p><b><u>";
    print urldecode($_GET['status']);
    print "</u></b><p>";
}
print "<form method='POST' action='modifyLdapUser.php'>\n";
print "<p><table width=100% border=0 cellpadding=0 cellspacing=5 border=0>\n";
print "<tr><td>\n";
print pacsone_gettext("LDAP Server Hostname:") . "</td>";
print "<td><input type='text' size=32 maxlength=64 name='ldapHost' value='$ldapHost' readonly>";
print "</td></tr>\n";
print "<tr><td>\n";
print pacsone_gettext("LDAP Server Port:") . "</td>";
print "<td><input type='text' size=8 maxlength=8 name='ldapHost' value='$ldapPort' readonly>";
print "</td></tr>\n";
print "<tr><td>\n";
print pacsone_gettext("LDAP Account Login Distinguished Name (DN):") . "</td>";
print "<td>CN=<input type='text' size=16 maxlength=32 name='ldapCn' value='$ldapCn'>";
print "&nbsp;,&nbsp;<input type='text' size=32 maxlength=64 name='dnSuffix' value='$dnSuffix'>";
print pacsone_gettext("Other components such as \"O=Organization,C=USA\", etc");
print "</td></tr>\n";
print "<tr><td>\n";
print pacsone_gettext("LDAP Account Password:") . "</td>";
print "<td><input type='password' size=32 maxlength=64 name='ldapPassword' value=''>";
print "</td></tr>\n";
print "<tr><td>\n";
print pacsone_gettext("User Account Querying Key:") . "</td>";
print "<td><input type='text' size=32 maxlength=64 name='userKey' value='$userKey'>";
print "</td></tr>\n";
print "<tr><td>\n";
print pacsone_gettext("User Account Filters:") . "</td>";
print "<td><input type='text' size=32 maxlength=64 name='userFilter' value='$userFilter'>";
print "</td></tr>\n";
print "<tr><td>\n";
print pacsone_gettext("Group Account Querying Key:") . "</td>";
print "<td><input type='text' size=32 maxlength=64 name='groupKey' value='$groupKey'>";
print "</td></tr>\n";
print "<tr><td>\n";
print pacsone_gettext("Group Account Filters:") . "</td>";
print "<td><input type='text' size=32 maxlength=64 name='groupFilter' value='$groupFilter'>";
print "</td></tr>\n";
// end of table
print "</table>\n";
print "<table width=20% border=0 cellpadding=5>\n";
print "<tr><td><input type='submit' value='";
print pacsone_gettext("Synchronize");
print "' title='";
print pacsone_gettext("Query And Synchronize All LDAP Users");
print "'</input>";
print "</td></tr></table>";
print "</form>\n";
// check if the internal username/password has been created for local database
$result = $dbcon->query("SELECT * FROM privilege WHERE firstname!='_GROUP'");
$num_rows = $result->rowCount();
// display total number of patient records in database
if ($num_rows > 1)
    $preface = sprintf(pacsone_gettext("There are %d LDAP user accounts in PACS database."), $num_rows);
else
    $preface = sprintf(pacsone_gettext("There is %d LDAP user account in PACS database."), $num_rows);
displayUsers($result, $preface, 1);
print "<p>";
// display user groups
$result = $dbcon->query("SELECT * FROM privilege WHERE firstname='_GROUP' and usergroup is NULL");
$num_rows = $result->rowCount();
if ($num_rows > 1)
    $preface = sprintf(pacsone_gettext("There are %d LDAP user groups in PACS database."), $num_rows);
else
    $preface = sprintf(pacsone_gettext("There is %d LDAP user group in PACS database."), $num_rows);
displayGroups($result, $preface, 1);

require_once 'footer.php';
print "</body>";
print "</html>";

?>
