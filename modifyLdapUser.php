<?php
//
// modifyLdapUser.php
//
// Module for querying remote LDAP Server and synchronize with PRIVILEGE table
//
// CopyRight (c) 2017-2020 RainbowFish Software
//
session_start();
ob_start();

require_once 'locale.php';
require_once 'ldap.php';
include_once 'database.php';
include_once 'sharedUserAdmin.php';

// main
global $PRODUCT;
$dbcon = new MyConnection();
$database = $dbcon->database;
$username = $dbcon->username;
if (!$dbcon->isAdministrator($username)) {
	print "<font color=red>";
    print pacsone_gettext("You must login as <b>Administrator</b> to manage user accounts");
    print "</font>";
    exit();
}
$result = NULL;
$aetitle = $_SESSION['aetitle'];
$updateIni = false;
$ini = parseIniByAeTitle($aetitle);
if (strcasecmp($ini['LdapCn'], $_POST['ldapCn'])) {
    $ini['LdapCn'] = $_POST['ldapCn'];
    $updateIni = true;
}
$bindDn = "cn=" . $_POST['ldapCn'];
if (strlen($_POST['dnSuffix'])) {
    $bindDn .= "," . $_POST['dnSuffix'];
    if (strcasecmp($ini['DnSuffix'], $_POST['dnSuffix'])) {
        $ini['DnSuffix'] = $_POST['dnSuffix'];
        $updateIni = true;
    }
}
if (strcasecmp($ini['UserKey'], $_POST['userKey'])) {
    $ini['UserKey'] = $_POST['userKey'];
    $updateIni = true;
}
if (strcasecmp($ini['UserFilter'], $_POST['userFilter'])) {
    $ini['UserFilter'] = $_POST['userFilter'];
    $updateIni = true;
}
if (strcasecmp($ini['GroupKey'], $_POST['groupKey'])) {
    $ini['GroupKey'] = $_POST['groupKey'];
    $updateIni = true;
}
if (strcasecmp($ini['GroupFilter'], $_POST['groupFilter'])) {
    $ini['GroupFilter'] = $_POST['groupFilter'];
    $updateIni = true;
}
if ($updateIni)
    writeIniByAeTitle($aetitle, $ini);
$ldap = new ldapAPI($ini['LdapHost'], $ini['LdapPort'], $bindDn, $_POST['ldapPassword']);
$status = "";
if ($ldap->isConnected()) {
    // existing users and groups
    $currentUsers = array();
    $currentGroups = array();
    $users = $dbcon->query("select username from privilege where firstname!='_GROUP'");
    while ($row = $users->fetch(PDO::FETCH_NUM))
        $currentUsers[ $row[0] ] = false;
    $groups = $dbcon->query("select username from privilege where firstname='_GROUP' and usergroup is NULL");
    while ($row = $groups->fetch(PDO::FETCH_NUM))
        $currentGroups[ $row[0] ] = false;
    // query and sync all users
    global $LDAP_ATTR_CN;
    global $LDAP_ATTR_SN;
    global $LDAP_ATTR_EMAIL;
    global $LDAP_ATTR_UNIQUE_MEMBER;
    global $LDAP_ATTR_DESCRIPTION;
    $attrs = array($LDAP_ATTR_CN, $LDAP_ATTR_SN, $LDAP_ATTR_EMAIL);
    $users = $ldap->search($ini['UserKey'], $ini['UserFilter'], $attrs);
    if ($users)
        $status = syncUsers($aetitle, $ini['UTC'], $ldap->getEntries($users), $currentUsers, $result);
    if (empty($result)) {
        // query and sync all groups
        $attrs = array($LDAP_ATTR_CN, $LDAP_ATTR_UNIQUE_MEMBER, $LDAP_ATTR_DESCRIPTION);
        $groups = $ldap->search($ini['GroupKey'], $ini['GroupFilter'], $attrs);
        if ($groups) {
            $status .= "<br>";
            $status .= syncGroups($aetitle, $ini['UTC'], $ldap->getEntries($groups), $currentGroups, $result);
        }
    }
} else {
    $result = sprintf(pacsone_gettext("Failed to connect to %s as [%s], error = %s"),
        $ini['LdapHost'], $bindDn, $ldap->getLastError());
}
if (empty($result)) {   // success
    ob_end_clean();
    $url = 'Location: ldapUser.php';
    if (strlen($status))
        $url .= "?status=" . urlencode($status);
    header($url);
}
else {                  // error
    ob_end_flush();
    print "<html>\n";
    print "<head><title>$PRODUCT - ";
    print pacsone_gettext("LDAP Error");
    print "</title></head>\n";
    print "<body>\n";
    require_once 'header.php';
    print $result;
    require_once 'footer.php';
    print "</body>\n";
    print "</html>\n";
}

function syncUsers($aetitle, $utc, &$entries, &$currentUsers, &$result)
{
    global $dbcon;
    global $DEFAULT_PRIVILEGE_TBL;
    global $LDAP_ATTR_CN;
    global $LDAP_ATTR_SN;
    global $LDAP_ATTR_EMAIL;
    $ldapUsers = array();
    $synced = 0;
    $added = 0;
    $removed = 0;
    $total = $entries['count'];
    // list of user accounts returned by LDAP server
    for ($i = 0; $i < $total; $i++) {
        $found = false;
        $ldapUser = $entries[$i][$LDAP_ATTR_CN][0];
        $last = $entries[$i][$LDAP_ATTR_SN][0];
        $first = getFirstFromFullLastNames($ldapUser, $last);
        $email = isset($entries[$i][$LDAP_ATTR_EMAIL])? $entries[$i][$LDAP_ATTR_EMAIL][0] : "";
        $ldapUsers[] = strtolower($ldapUser);
        $current = "";
        foreach ($currentUsers as $current => $checked) {
            if (!strcasecmp($current, $ldapUser)) {
                $found = true;
                $currentUsers[$current] = true;
                break;
            }
        }
        if ($found) {
            $query = "update privilege set lastname=?,firstname=?,email=? where username=?";
            $bindList = array($last, $first, $email, $current);
            if ($dbcon->preparedStmt($query, $bindList))
                $synced++;
        } else {
            // add the LDAP user if not already on the current user list
            if (!createDbLogin($ldapUser, strtolower($aetitle . "-" . $utc), $error)) {
                $result = sprintf(pacsone_gettext("Error creating database login for LDAP user [%s]: %s"),
                    $ldapUser, $error);
                return $result;
            }
            if (!addPrivilege($dbcon->database, $ldapUser, $error, 0)) {
                $result = sprintf(pacsone_gettext("Error setting up privileges for LDAP user [%s]: %s"),
                    $ldapUser, $error);
                removeDbLogin($ldapUser, $error);
                return $result;
            }
            $columns = array("username", "firstname", "lastname");
            $bindList = array($ldapUser, $first, $last);
            if (strlen($email)) {
                $columns[] = "email";
                $bindList[] = $email;
            }
            foreach ($DEFAULT_PRIVILEGE_TBL as $key => $enabled) {
                $columns[] = $key;
                $bindList[] = $enabled? 1 : 0;
            }
            $query = "insert into privilege (";
            for ($j = 0; $j < count($columns); $j++) {
                if ($j)
                    $query .= ",";
                $query .= $columns[$j];
            }
            $query .= ") values(";
            for ($j = 0; $j < count($bindList); $j++) {
                if ($j)
                    $query .= ",";
                $query .= "?";
            }
            $query .= ");";
            if (!$dbcon->preparedStmt($query, $bindList)) {
                $result = sprintf(pacsone_gettext("Error running SQL query [$query]: %s"),
                    $query, $dbcon->getError());
                removePrivilege($dbcon->database, $ldapUser, $error);
                removeDbLogin($ldapUser, $error);
                return $result;
            }
            $added++;
        }
    }
    // remove any current user not on the LDAP user list
    foreach ($currentUsers as $current => $checked) {
        $error = "";
        if ($checked || array_key_exists(strtolower($current), $ldapUsers))
            continue;
        if (!deleteUser($dbcon->database, $current, $error)) {
            $result = sprintf(pacsone_gettext("Error deleting current user [%s]: %s"),
                $current, $error);
            return $result;
        }
        $removed++;
    }
    return sprintf(pacsone_gettext("%d LDAP users added, %d existing users updated and %d existing users removed."),
        $added, $synced, $removed);
}

function syncGroups($aetitle, $utc, &$entries, &$currentGroups, &$result)
{
    global $dbcon;
    global $DEFAULT_PRIVILEGE_TBL;
    global $LDAP_ATTR_CN;
    global $LDAP_ATTR_UNIQUE_MEMBER;
    global $LDAP_ATTR_DESCRIPTION;
    $ldapGroups = array();
    $added = 0;
    $synced = 0;
    $removed = 0;
    // query all current users
    $currentUsers = array();
    $users = $dbcon->query("select username from privilege where firstname!='_GROUP'");
    while ($row = $users->fetch(PDO::FETCH_NUM))
        $currentUsers[ strtolower($row[0]) ] = false;
    $total = $entries['count'];
    // list of group accounts returned by LDAP server
    for ($i = 0; $i < $total; $i++) {
        $found = false;
        $ldapGroup = $entries[$i][$LDAP_ATTR_CN][0];
        $description = $entries[$i][$LDAP_ATTR_DESCRIPTION][0];
        $members = array();
        $list = $entries[$i][$LDAP_ATTR_UNIQUE_MEMBER];
        for ($m = 0; $m < $list['count']; $m++)
            $members[] = parseLdapComponent($list[$m], $LDAP_ATTR_CN);
        if (count($members))
            $ldapGroups[ strtolower($ldapGroup) ] = $members;
        $current = "";
        foreach ($currentGroups as $current => $checked) {
            if (!strcasecmp($current, $ldapGroup)) {
                $found = true;
                $currentGroups[$current] = true;
                break;
            }
        }
        if ($found) {
            $query = "update privilege set lastname=? where username=?";
            $bindList = array($description, $current);
            if ($dbcon->preparedStmt($query, $bindList))
                $synced++;
        } else {
            // add the LDAP group if not already on the current group list
            if (!createDbLogin($ldapGroup, strtolower($aetitle . "-" . $utc), $error)) {
                $result = sprintf(pacsone_gettext("Error creating database login for LDAP group [%s]: %s"),
                    $ldapGroup, $error);
                return $result;
            }
            if (!addPrivilege($dbcon->database, $ldapGroup, $error, 0)) {
                $result = sprintf(pacsone_gettext("Error setting up privileges for LDAP group [%s]: %s"),
                    $ldapGroup, $error);
                removeDbLogin($ldapGroup, $error);
                return $result;
            }
            $columns = array("username", "firstname", "lastname");
            $bindList = array($ldapGroup, '_GROUP', $description);
            foreach ($DEFAULT_PRIVILEGE_TBL as $key => $enabled) {
                $columns[] = $key;
                $bindList[] = $enabled? 1 : 0;
            }
            $query = "insert into privilege (";
            for ($j = 0; $j < count($columns); $j++) {
                if ($j)
                    $query .= ",";
                $query .= $columns[$j];
            }
            $query .= ") values(";
            for ($j = 0; $j < count($bindList); $j++) {
                if ($j)
                    $query .= ",";
                $query .= "?";
            }
            $query .= ");";
            if (!$dbcon->preparedStmt($query, $bindList)) {
                $result = sprintf(pacsone_gettext("Error running SQL query [$query]: %s"),
                    $query, $dbcon->getError());
                removePrivilege($dbcon->database, $ldapGroup, $error);
                removeDbLogin($ldapGroup, $error);
                return $result;
            }
            // setup group members
            foreach ($members as $member) {
                if (array_key_exists(strtolower($member), $currentUsers)) {
                    $bindList = array($member, $ldapGroup);
                    $dbcon->preparedStmt("insert into groupmember (username,groupname) values(?,?)", $bindList);
                }
            }
            $added++;
        }
    }
    // remove any current group not on the LDAP group list
    foreach ($currentGroups as $current => $checked) {
        $error = "";
        if ($checked || array_key_exists(strtolower($current), $ldapGroups))
            continue;
        if (!deleteUser($dbcon->database, $current, $error)) {
            $result = sprintf(pacsone_gettext("Error deleting current group [%s]: %s"),
                $current, $error);
            return $result;
        }
        $bindList = array($current);
        if (!$dbcon->preparedStmt("delete from groupmember where groupname=?", $bindList)) {
            $result = sprintf(pacsone_gettext("Error deleting group members for group [%s]: %s"),
                $current, $dbcon->getError());
            return $result;
        }
        $removed++;
    }
    return sprintf(pacsone_gettext("%d LDAP groups added, %d existing groups updated and %d existing groups removed."),
        $added, $synced, $removed);
}

?>
