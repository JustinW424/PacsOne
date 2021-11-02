<?php
//
// sharedUserAdmin.php
//
// Common functions for User Administration
//
// CopyRight (c) 2009-2020 RainbowFish Software
//

require_once 'locale.php';
include_once 'database.php';
include_once 'sharedData.php';

$FIELD_TBL = array(
    "firstname"         => "'",
    "lastname"          => "'",
    "middlename"        => "'",
    "email"             => "'",
    "viewprivate"       => "",
    "modifydata"        => "",
    "forward"           => "",
    "query"             => "",
    "move"              => "",
    "download"          => "",
    "print"             => "",
    "export"            => "",
    "import"            => "",
    "upload"            => "",
    "monitor"           => "",
    "mark"              => "",
    "notifynewstudy"    => "",
    "matchgroup"        => "",
    "substring"         => "",
    "admin"             => "",
    "changestore"       => "",
    "qidors"            => "",
    "wadors"            => "",
    "stowrs"            => "",
);

function removePrivilege($db, $user, &$error)
{
    global $PACSONE_TABLES;
	global $PACSONE_SEQS;
    global $dbcon;
    $hostname = $dbcon->getHostname();
    $status = true;
    // remove column privilege
    $what = $dbcon->useOracle? "privilege" : "$db.privilege";
    $queries_mysql = array(
        array("revoke update (firstname,lastname,middlename,email,importdir,importdrive,importdest,exportdir,expire,sharenotes,pagesize,viewerdir,studynoteicon,refreshperiod) on $what from ?@?", array($user, $hostname)),
    );
    $queries_oracle = array(
        array("revoke update on privilege from ?", array($user)),
    );
    $queries = $dbcon->useOracle? $queries_oracle : $queries_mysql;
    foreach ($queries as $entry) {
        if (!$dbcon->preparedStmt($entry[0], $entry[1])) {
            $error = $dbcon->getError();
            $status = false;
        }
    }
    $extras = array("privilege", "journal", "smtp", "groupmember", "userfilter");
    foreach (array_merge($PACSONE_TABLES, $extras) as $table) {
        $what = $dbcon->useOracle? "$table" : "$db.$table";
        $query = "revoke all privileges on $what from ?@?";
        $bindList = array($user, $hostname);
        if ($dbcon->useOracle) {
            $query = "revoke all on $table from ?";
            $bindList = array($user);
        }
        if (!$dbcon->preparedStmt($query, $bindList)) {
            /*
            $error .= $dbcon->getError();
            $status = false;
             */
        }
    }
    if ($dbcon->useOracle) {
        foreach ($PACSONE_SEQS as $seq) {
            $bindList = array($user);
            $dbcon->preparedStmt("revoke select on $seq from ?", $bindList);
        }
    }
    if ($dbcon->useMysql)
        $dbcon->query("flush privileges");
    return $status;
}

function createDbLogin($user, $passwd, &$error)
{
    global $dbcon;
    $hostname = $dbcon->getHostname();
	// create username
    $queries_mysql = array(
	    array("create user ?@? identified by ?", array($user,$hostname,$passwd)),
	    array("grant select (host,user) on mysql.user to ?@?", array($user,$hostname)),
	    array("grant reload on *.* to ?@?", array($user,$hostname)),
    );
    // MySQL USER table in version 5.7.6 or higher does not have the 'password' column any more
    if ($dbcon->useMysql && (versionCompare($dbcon->version, 5, 7, 6) < 0))
	    $queries_mysql[] = array("grant update (password) on mysql.user to ?@?", array($user,$hostname));
    $queries_oracle = array(
        array("create user ? identified by ?", array($user,$passwd)),
        array("grant create session to ?", array($user)),
    );
    $queries = $dbcon->useOracle? $queries_oracle : $queries_mysql;
    foreach ($queries as $entry) {
	    if (!$dbcon->preparedStmt($entry[0], $entry[1])) {
		    $error = $dbcon->getError();
		    return false;
        }
	}
    if ($dbcon->useMysql)
	    $dbcon->query("flush privileges");
	return true;
}

function addPrivilege($db, $user, &$error, $sysadmin)
{
	global $PACSONE_TABLES;
	global $PACSONE_SEQS;
    global $dbcon;
    $hostname = $dbcon->getHostname();
	// add column privilege
    $queries_mysql = array(
        array("grant update (firstname,lastname,middlename,email,importdir,importdrive,importdest,exportdir,expire,sharenotes,pagesize,viewerdir,studynoteicon,refreshperiod) on $db.privilege to ?@?", array($user,$hostname)),
	    array("grant select on $db.privilege to ?@?", array($user,$hostname)),
	    array("grant select on $db.smtp to ?@?", array($user,$hostname)),
	    array("grant insert on $db.journal to ?@?", array($user,$hostname)),
	    array("grant select on $db.groupmember to ?@?", array($user,$hostname)),
	    array("grant select on $db.userfilter to ?@?", array($user,$hostname)),
    );
    $queries_oracle = array(
        array("grant update (firstname,lastname,middlename,email,importdir,importdrive,importdest,exportdir,expire,sharenotes,pagesize,viewerdir,studynoteicon,refreshperiod) on privilege to ?", array($user)),
	    array("grant select on privilege to ?", array($user)),
	    array("grant select on smtp to ?", array($user)),
	    array("grant insert on journal to ?", array($user)),
	    array("grant select on groupmember to ?", array($user)),
	    array("grant select on userfilter to ?", array($user)),
    );
    $queries = $dbcon->useOracle? $queries_oracle : $queries_mysql;
    foreach ($queries as $entry) {
	    if (!$dbcon->preparedStmt($entry[0], $entry[1])) {
		    $error = $dbcon->getError();
		    return false;
        }
	}
	foreach ($PACSONE_TABLES as $table) {
        $what = $dbcon->useOracle? "$table" : "$db.$table";
		$query = "grant all privileges on $what to ?@?";
        $bindList = array($user, $hostname);
        if ($dbcon->useOracle) {
		    $query = "grant all on $table to ?";
            $bindList = array($user);
        }
		if (!$dbcon->preparedStmt($query, $bindList)) {
			$error = $dbcon->getError();
			return false;
		}
	}
    // system administration privilege
    if ($sysadmin) {
        if (!addSysAdminPrivilege($db, $user, $error))
            return false;
    }
    if ($dbcon->useOracle) {
        foreach ($PACSONE_SEQS as $seq) {
            $bindList = array($user);
            $dbcon->preparedStmt("grant select on $seq to ?", $bindList);
        }
    }
    if ($dbcon->useMysql)
	    $dbcon->query("flush privileges");
	return true;
}

function removeDbLogin($user, &$error)
{
    global $dbcon;
    $hostname = $dbcon->getHostname();
    if ($dbcon->useOracle) {
        $bindList = array($user);
	    if (!$dbcon->preparedStmt("drop user ? cascade", $bindList)) {
		    $error = $dbcon->getError();
		    return false;
	    }
    } else if ($dbcon->useMysql) {
	    // delete user from USER table
        $queries = array(
            array("revoke select (host,user) on mysql.user from ?@?", array($user,$hostname)),
            array("revoke reload on *.* from ?@?", array($user,$hostname)),
            array("delete from mysql.user where User=?", array($user)),
        );
        // MySQL USER table in version 5.7.6 or higher does not have the 'password' column any more
        if (versionCompare($dbcon->version, 5, 7, 6) < 0)
            array_unshift($queries, array("revoke update (password) on mysql.user from ?@?", array($user,$hostname)));
	    foreach ($queries as $entry) {
	        if (!$dbcon->preparedStmt($entry[0], $entry[1])) {
		        $error = sprintf(pacsone_gettext("Cannot delete %s from MySQL USER table: %s"), $user, $dbcon->getError());
		        return false;
	        }
        }
	    // delete user from TABLES_PRIV table
	    $query = "delete from mysql.tables_priv where User=?";
        $bindList = array($user);
	    if (!$dbcon->preparedStmt($query, $bindList)) {
		    $error = sprintf(pacsone_gettext("Cannot delete %s from MySQL TABLES_PRIV table: %s"), $user, $dbcon->getError());
		    return false;
	    }
        $dbcon->query("flush privileges");
    }
    return true;
}

function deleteUser($db, $user, &$error)
{
    global $dbcon;
    // remove entry from privilege table
    $what = $dbcon->useOracle? "privilege" : "$db.privilege";
    $query = "delete from $what where username=?";
    $bindList = array($user);
    if (!$dbcon->preparedStmt($query, $bindList)) {
        $error = sprintf(pacsone_gettext("Failed to delete User: <u>%s</u> from database: <u>%s</u>"), $user, $db);
        $error .= "<p>" . $dbcon->getError();
		return false;
    }
	// revoke all privileges for this user
	if (!removePrivilege($db, $user, $error)) {
		$error = sprintf(pacsone_gettext("Failed to revoke User: '%s' privileges: "), $user) . $error;
		return false;
	}
	// delete user from derived tables
    $what = $dbcon->useOracle? "groupmember" : "$db.groupmember";
	$query = "delete from $what where username=? or groupname=?";
    $bindList = array($user, $user);
	if (!$dbcon->preparedStmt($query, $bindList)) {
	    $error = sprintf(pacsone_gettext("Cannot delete %s from table groupmember: %s"), $user, $dbcon->getError());
	    return false;
	}
    $derived = array("studyview", "aeassigneduser", "userfilter");
    foreach ($derived as $table) {
        $what = $dbcon->useOracle? $table : "$db.$table";
	    $query = "delete from $what where username=?";
        $bindList = array($user);
	    if (!$dbcon->preparedStmt($query, $bindList)) {
	        $error = sprintf(pacsone_gettext("Cannot delete %s from table %s: %s"), $user, $table, $dbcon->getError());
	        return false;
	    }
    }
	// delete database login if username has been removed from all server instances
	if (($dbcon->getSharedInstances($user) == 0) && !removeDbLogin($user, $error)) {
		$error = sprintf(pacsone_gettext("Failed to delete Database Username: '%s': "), $user) . $error;
		return false;
	}
	return true;
}

function addSysAdminPrivilege($db, $user, &$error)
{
    global $dbcon;
    $hostname = $dbcon->getHostname();
    $queries_mysql = array(
	    array("grant select on $db.journal to ?@?", array($user,$hostname)),
	    array("grant update on $db.config to ?@?", array($user,$hostname)),
	    array("grant insert,update,delete on $db.smtp to ?@?", array($user,$hostname)),
	    array("grant insert,update,delete on $db.userfilter to ?@?", array($user,$hostname)),
	    array("grant insert,update,delete on $db.aeassigneduser to ?@?", array($user,$hostname)),
    );
    $queries_oracle = array(
	    array("grant select on journal to ?", array($user)),
	    array("grant update on config to ?", array($user)),
	    array("grant insert,update,delete on smtp to ?", array($user)),
	    array("grant insert,update,delete on userfilter to ?", array($user)),
	    array("grant insert,update,delete on aeassigneduser to ?", array($user)),
    );
    $queries = $dbcon->useOracle? $queries_oracle : $queries_mysql;
    foreach ($queries as $entry) {
	    if (!$dbcon->preparedStmt($entry[0], $entry[1])) {
		    $error .= $dbcon->getError();
        }
	}
    if ($dbcon->useMysql)
        $dbcon->query("flush privileges");
    return true;
}

function removeSysAdminPrivilege($db, $user, &$error)
{
    global $dbcon;
    $hostname = $dbcon->getHostname();
    $queries_mysql = array(
	    array("revoke select on $db.journal from ?@?", array($user,$hostname)),
	    array("revoke update on $db.config from ?@?", array($user,$hostname)),
	    array("revoke insert,update,delete on $db.smtp from ?@?", array($user,$hostname)),
	    array("revoke insert,update,delete on $db.userfilter from ?@?", array($user,$hostname)),
	    array("revoke insert,update,delete on $db.aeassigneduser from ?@?", array($user,$hostname)),
    );
    $queries_oracle = array(
	    array("revoke select on journal from ?", array($user)),
	    array("revoke update on config from ?", array($user)),
	    array("revoke insert,update,delete on smtp from ?", array($user)),
	    array("revoke insert,update,delete on userfilter from ?", array($user)),
	    array("revoke insert,update,delete on aeassigneduser from ?", array($user)),
    );
    $queries = $dbcon->useOracle? $queries_oracle : $queries_mysql;
    foreach ($queries as $entry) {
	    if (!$dbcon->preparedStmt($entry[0], $entry[1])) {
		    $error .= $dbcon->getError();
        }
	}
    if ($dbcon->useMysql)
        $dbcon->query("flush privileges");
    return true;
}

?>
