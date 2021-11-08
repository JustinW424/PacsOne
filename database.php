<?php
//
// database.php
//
// Module for managing database tables
//
// CopyRight (c) 2003-2021 RainbowFish Software
//

// user accessible tables
$PACSONE_TABLES = array(
	"applentity","autoroute","coercion","dbjob","image","patient","procedurecode","protocolcode",
	"referencedpatient","referencedpps","referencedstudy","referencedvisit",
	"requestedprocedure","scheduledps","series","study","worklist","export","conceptname",
    "autopurge","postroute","config","annotation","cronjob","studynotes","imagenotes",
    "attachment","download","commitment","exportedstudy","autoscan","importscan", "monitor",
    "aefilter","matchworklist","performedprotocode","discontreasoncode","performedps",
    "performedprocedurecode","autoconvert","anonymity","studyview","commitmentreq",
    "commitsopref","commitmentqueue","aeassigneduser","otherpatientids","patientspeciescode",
    "patientbreedcode","breedregistration","patientview","performedseries","xscriptemplate",
    "xscriptbookmark","worklistfromhl7","studyfilter","aegroup","mppsroute","studydosereport",
    "imagedose","irradiationevent","ianqueue","iansubscr",
    "hl7application","hl7message","hl7job","hl7route","hl7patientid","hl7patientname",
    "hl7mothersmaidenname","hl7patientalias","hl7race","hl7patientaddress","hl7homephone",
    "hl7businessphone","hl7mothersid","hl7ethnicgroup","hl7citizenship","hl7attendingdoc",
    "hl7referringdoc","hl7consultingdoc","hl7admittingdoc","hl7ambulatorystatus","hl7financialclass",
    "hl7enteredby","hl7verifiedby","hl7orderingprovider","hl7actionby","hl7callbackphone","hl7entererloc",
    "hl7collectorid","hl7resultcopiesto","hl7quantitytiming","hl7reasonforstudy",
    "hl7assistantresultinterpreter","hl7technician","hl7transcriptionist","hl7transportlogistics",
    "hl7collectorscomment","hl7plannedtransportcomment","hl7orderingfacilityname",
    "hl7orderingfacilityaddr","hl7orderingfacilityphone","hl7orderingprovideraddr","hl7universalserviceid",
    "hl7assignedpatientloc","hl7priorpatientloc","hl7temporaryloc","hl7pendingloc","hl7priortemporaryloc",
    "hl7abnormalflag","hl7natureofabnormaltest","hl7observationmethod",
    "hl7responsibleobserver","hl7allergyreaction","hl7operatorid","hl7priorpatientname",
    "hl7segpid","hl7segpv1","hl7segorc","hl7segobr", "hl7segobx","hl7segal1","hl7segzds",
    "hl7segevn","hl7segmrg","hl7procedurecode","hl7procedurecodemodifier","hl7altpid",
);

// user accessible sequences
$PACSONE_SEQS = array(
    "dbjob_seq","export_seq","studynotes_seq","imagenotes_seq","attachment_seq","commitment_seq",
    "importscan_seq",
);

require_once "classroot.php";
include_once "constants.php";
include_once "utils.php";

class PdoAPI extends PacsOneRoot {
    var $pdo;
    var $version = "";
    var $lastError = "";
    var $affectedRows = 0;
    function __construct($dsn, $username, $password)
    {
        try {
            $this->pdo = new PDO($dsn, $username, $password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
        } catch (PDOException $e) {
            $this->pdo = false;
            $this->lastError = $e->getMessage();
        }
    }
    function __destruct() {}
    function exec($q) {
        $this->affectedRows = $this->pdo->exec($q);
        return $this->affectedRows;
    }
    function query($q) {
        return $this->pdo->query($q);
    }
    function preparedStmt($q, $bindList) {
        try {
            $stmt = $this->pdo->prepare($q);
            if ($stmt)
                $stmt->execute($bindList);
        } catch (PDOException $e) { 
            $this->lastError = $e->getMessage(); 
            $stmt = false;
        }
        return $stmt;
    }
    function preparedStmtWithType($q, $bindList, $typeList) {
        if (count($bindList) != count($typeList)) {
            $this->lastError = pacsone_gettext("Prepared statement bind parameters mismatch");
            return false;
        }
        try {
            $stmt = $this->pdo->prepare($q);
            if ($stmt) {
                for ($i = 0; $i < count($bindList); $i++)
                    $stmt->bindValue($i+1, $bindList[$i], $typeList[$i]);
                $stmt->execute($bindList);
            }
        } catch (PDOException $e) { 
            $this->lastError = $e->getMessage(); 
            $stmt = false;
        }
        return $stmt;
    }
    function insert_id($table) {
        return $this->pdo->lastInsertId();
    }
    function getError() {
        if (!$this->pdo || strlen($this->lastError))
            return $this->lastError;
        $info = $this->pdo->errorInfo();
        return sprintf("Error code: %d %s", $info[1], $info[2]);
    }
}

class MysqlAPI extends PdoAPI {
    function __construct($host, $db, $user, $passwd, $port, $charset)
    {
        $dsn = "mysql:host=$host;dbname=$db";
        if ($port)
            $dsn .= ";port=$port";
        if (strlen($charset))
            $dsn .= ";charset=$charset";
        parent::__construct($dsn, $user, $passwd);
        if ($this->pdo)
            $this->version = $this->pdo->query('select version()')->fetchColumn();
    }
    function __destruct() {}
    function isAdministrator($username) {
        return (strcasecmp($username, "root") == 0);
    }
    function getAdminUsername() {
        return "root";
    }
    function selectDb($db) {
        $this->pdo->exec("use $db");
    }
    function escapeQuote(&$str)
    {
        return addslashes($str);
    }
    function valueToDate($value) {
        return strlen($value)? "'$value'" : "";
    }
}

class OracleAPI extends PdoAPI {
    var $charsetTbl = array(    // common to Oracle character set names
        "utf8"      => "AL32UTF8",
    );
    function __construct($host, $schema, $db, $user, $passwd, $charset = "")
    {
        $dsn = "oci:dbname=$db";
        if (strlen($charset) && isset($this->charsetTbl[$charset])) {
            $charset = $this->charsetTbl[$charset];
            $dsn .= ";charset=$charset";
        }
        parent::__construct($dsn, $user, $passwd);
        if ($this->pdo) {
            if (strlen($schema)) {
                $queries = array(
                    "alter session set current_schema=$schema",
                    // setup case-insensitive query
                    "alter session set NLS_COMP=LINGUISTIC",
                    "alter session set NLS_SORT=BINARY_CI",
                    "alter session set NLS_DATE_FORMAT='DD-MON-YYYY HH24:MI:SS'",
                    //"ALTER SYSTEM SET open_cursors=1000 SCOPE=BOTH",
                );
                foreach ($queries as $q)
                    $this->pdo->exec($q);
                if (!session_id())
                    session_start();
                $_SESSION["_isOracle"] = 1;
            }
            $this->version = $this->pdo->query('select version from PRODUCT_COMPONENT_VERSION')->fetchColumn();
        }
    }
    function __destruct() {}
    function isAdministrator($username) {
        return (strcasecmp($username, "SYSTEM") == 0);
    }
    function getAdminUsername() {
        return "SYSTEM";
    }
    function selectDb($db) {
        die ("selectDb() API not supported for Oracle database!");
    }
    function escapeQuote(&$str)
    {
        return str_replace("'", "''", $str);
    }
    function valueToDate($value) {
        if (strlen($value))
            return strpos($value, "-")? "TO_DATE('$value','YYYY-MM-DD')" : "TO_DATE('$value','YYYYMMDD')";
        return "";
    }
}

class MyDatabase extends PacsOneRoot {
    var $connection = false;
    var $useMysql = true;
    var $useOracle = false;
    var $dbapi;
    var $hostname;
    var $version = "";

    function __construct($host, $db, $user, $passwd, $aetitle = "", $charset = "")
    {
        $this->hostname = $host;
        global $ORACLE_CONFIG_FILE;
        $previous = error_reporting(E_ERROR);
        $dir = dirname($_SERVER['SCRIPT_FILENAME']);
        $dir = substr($dir, 0, strlen($dir) - 3);
        if (file_exists($dir . $ORACLE_CONFIG_FILE)) {
            // use Oracle database api
            $this->useMysql = false;
            $this->useOracle = true;
            $schema = "";
            if (!session_id())
                session_start();
            if (isset($_SESSION['Schema'])) {
                $schema = $_SESSION['Schema'];
            } else if (strlen($aetitle)) {
                $ini = $dir . $aetitle . ".ini";
                $parsed = parseIniFile($ini);
                if (count($parsed) && isset($parsed['Schema'])) {
                    $schema = $parsed['Schema'];
                }
            }
            $host = strcasecmp($host, "localhost")? $host : "";
            $this->dbapi = new OracleAPI($host, $schema, $db, $user, $passwd, $charset);
        } else {
            // use MySQL database api
            $port = 0;
            if (strlen($aetitle)) {
                $ini = $dir . $aetitle . ".ini";
                $parsed = parseIniFile($ini);
                if (isset($parsed['MysqlPort']))
                    $port = $parsed['MysqlPort'];
            }
            $this->dbapi = new MysqlAPI($host, $db, $user, $passwd, $port, $charset);
        }
        $this->connection = $this->dbapi->pdo;
        $this->version = $this->dbapi->version;
        error_reporting($previous);
    }
    function __destruct() { }
    // check if the specified username is the System Administrator
    function isAdministrator($username) {
        return $this->dbapi->isAdministrator($username);
    }
    // get the username for the System Administrator
    function getAdminUsername() {
        return $this->dbapi->getAdminUsername();
    }
    // query function
    function query($q) {
        $previous = error_reporting(E_ERROR);
        $ret = $this->dbapi->query($q);
        error_reporting($previous);
        return $ret;
    }
    // prepared statment functions
    function preparedStmt($q, &$bindList) {
        $previous = error_reporting(E_ERROR);
        $ret = $this->dbapi->preparedStmt($q, $bindList);
        error_reporting($previous);
        return $ret;
    }
    function preparedStmtWithType($q, &$bindList, &$typeList) {
        $previous = error_reporting(E_ERROR);
        $ret = $this->dbapi->preparedStmtWithType($q, $bindList, $typeList);
        error_reporting($previous);
        return $ret;
    }
    // select database
    function selectDb($db) {
        $this->dbapi->selectDb($db);
    }
    // get error string
    function getError()
    {
        return $this->dbapi->getError();
    }
    // encapsulation api functions
    function affected_rows()
    {
        return $this->dbapi->affectedRows;
    }
    function insert_id($table)
    {
        return $this->dbapi->insert_id($table);
    }
    function escapeQuote($str)
    {
        return strlen($str)? $this->dbapi->escapeQuote($str) : $str;
    }
    function valueToDate($value)
    {
        return $this->dbapi->valueToDate($value);
    }
    // this function tests if current user has specified access in the privilege table
    function hasaccess($access, $username)
    {
	    // super-user has all privileges
	    if ($this->isAdministrator($username))
		    return 1;
	    // check the user privilege table for all other users
        $query = "select $access from privilege where username=?";
        $bindList = array($username);

        echo("query=".$query." "."bindlist=".$bindList);

        $result = $this->preparedStmt($query, $bindList);
	    if (!$result)
            return 0;
        return $result->fetchColumn();
    }

    function getSeriesNumber($uid)
    {
        $query = "SELECT seriesnumber FROM series WHERE uuid=?";
        $bindList = array($uid);
        $result = $this->preparedStmt($query, $bindList);
	    if ($result)
	        return $result->fetchColumn();
        return $uid;
    }

    function getStudyId($uid)
    {
	    $query = "SELECT id FROM study WHERE uuid=?";
        $bindList = array($uid);
        $result = $this->preparedStmt($query, $bindList);
	    return $result? $result->fetchColumn() : $uid;
    }

    function getAccessionNumber($uid)
    {
	    $query = "SELECT accessionnum FROM study WHERE uuid=?";
        $bindList = array($uid);
        $result = $this->preparedStmt($query, $bindList);
	    return $result? $result->fetchColumn() : "";
    }

    function getDob($pid)
    {
        $query = "select birthdate from patient where origid=?";
        $bindList = array($pid);
        $result = $this->preparedStmt($query, $bindList);
        return $result? $result->fetchColumn() : "";
    }

    function getPatientName($uid)
    {
        if (!strlen($uid))
            return $uid;
        $dir = dirname($_SERVER['SCRIPT_FILENAME']);
        $dir = substr($dir, 0, strlen($dir) - 3);
        $ucNames = file_exists($dir . "do.not.convert.dicom.names")? false : true;
        $value = $uid;
        $query = "SELECT firstname,middlename,lastname,ideographic,phonetic,charset FROM patient WHERE origid=?";
        $bindList = array($uid);
        $result = $this->preparedStmt($query, $bindList);
	    if ($result && ($row = $result->fetch(PDO::FETCH_NUM))) {
            $firstName = $ucNames? ucfirst(strtolower($row[0])) : $row[0];
            $middleName = $ucNames? ucfirst(strtolower($row[1])) : $row[1];
            $lastName = $ucNames? ucfirst(strtolower($row[2])) : $row[2];
            $value = "";
            $charset = (isset($row[5]) && strlen($row[5]))? $row[5] : "";
            if (isset($row[0]) && strlen($row[0]))
                $value .= strlen($charset)? $row[0] : $firstName;
            if (isset($row[1]) && strlen($row[1]))
                $value .= " " . (strlen($charset)? $row[1] : $middleName);
            if (isset($row[2]) && strlen($row[2]))
                $value .= " " . (strlen($charset)? $row[2] : $lastName);
            if (!strlen($value) || !strcmp($value, " ") || !strcmp($value, "  "))
                $value = pacsone_gettext("(Blank)");
            // check if need to convert from specific charset to configured
            $value = $this->convertCharset($value, $charset);
            // check for ideographic or phonetic names
            $esc = $this->getCharsetEscape();
            if (isset($row[3]) && strlen($row[3])) {
                $escaped = str_replace($esc, "", $row[3]);
                $value .= "=" . $escaped;
            }
            if (isset($row[4]) && strlen($row[4])) {
                $escaped = str_replace($esc, "", $row[4]);
                $value .= "=" . $escaped;
            }
        }
	    return $value;
    }

    function getDicomPatientName($uid)
    {
        if (!strlen($uid))
            return $uid;
        $value = "";
        $query = "SELECT lastname,firstname,middlename,prefix,suffix,charset FROM patient WHERE origid=?";
        $bindList = array($uid);
        $result = $this->preparedStmt($query, $bindList);
	    if ($result && ($row = $result->fetch(PDO::FETCH_NUM))) {
            $charset = (isset($row[5]) && strlen($row[5]))? $row[5] : "";
            if (isset($row[0]) && strlen($row[0]))
                $value .= strlen($charset)? $row[0] : ucfirst(strtolower($row[0]));
            if (isset($row[1]) && strlen($row[1]))
                $value .= "^" . (strlen($charset)? $row[1] : ucfirst(strtolower($row[1])));
            if (isset($row[2]) && strlen($row[2]))
                $value .= "^" . (strlen($charset)? $row[2] : ucfirst(strtolower($row[2])));
            if (isset($row[3]) && strlen($row[3]))
                $value .= "^" . (strlen($charset)? $row[3] : ucfirst(strtolower($row[3])));
            if (isset($row[4]) && strlen($row[4]))
                $value .= "^" . (strlen($charset)? $row[4] : ucfirst(strtolower($row[4])));
            // check if need to convert from specific charset to configured
            if (strlen($value) && strlen($charset))
                $value = $this->convertCharset($value, $charset);
        }
        return $value;
    }

    function getPatientIdByStudyUid($uid)
    {
	    $query = "SELECT patientid FROM study WHERE uuid=?";
        $bindList = array($uid);
        $result = $this->preparedStmt($query, $bindList);
	    return $result? $result->fetchColumn() : false;
    }

    function getPatientNameByStudyUid($uid)
    {
	    $query = "SELECT patientid FROM study WHERE uuid=?";
        $bindList = array($uid);
        $result = $this->preparedStmt($query, $bindList);
        if ($result)
            return $this->getPatientName($result->fetchColumn());
	    return false;
    }

    function getPatientNameBySeriesUid($uid)
    {
	    $query = "SELECT studyuid FROM series WHERE uuid=?";
        $bindList = array($uid);
        $result = $this->preparedStmt($query, $bindList);
        if ($result)
            return $this->getPatientNameByStudyUid($result->fetchColumn());
	    return false;
    }

    function checkNamesMatch($dicomName, $first, $last)
    {
        if (!strlen($first) && !strlen($last))
            return false;
        $tokens = explode("^", $dicomName);
        // convert to upper case
        for ($i = 0; $i < count($tokens); $i++) {
            // normalize space, coma, etc contained within the token parts
            $value = "";
            $parts = preg_split("/[\s,]+/", $tokens[$i]);
            foreach ($parts as $part) {
                if (strlen($value))
                    $value .= " ";
                $value .= $part;
            }
            $tokens[$i] = strtoupper($value);
        }
        $match = in_array(strtoupper($first), $tokens);
        if ($match)
            $match = in_array(strtoupper($last), $tokens);
        if (!$match) {
            // allow the following exceptions
            $exceptions = array(
                (strtoupper($first) . " " . strtoupper($last)),
                (strtoupper($last) . " " . strtoupper($first)),
                (strtoupper($first) . "," . strtoupper($last)),
                (strtoupper($last) . "," . strtoupper($first)),
            );
            foreach ($exceptions as $ex) {
                if (in_array($ex, $tokens)) {
                    $match = true;
                    break;
                }
            }
        }
        return $match;
    }

    function checkNamesMatchGroup($groupName, $referring, $reading, $requesting)
    {
        $match = false;
        if (!strlen($referring) && !strlen($reading) && !strlen($requesting))
            return false;
        $query = "select firstname,lastname from privilege inner join groupmember on privilege.username=groupmember.username where groupmember.groupname=?";
        $bindList = array($groupName);
        $result = $this->preparedStmt($query, $bindList);
        if (!$result || ($result->rowCount() == 0))
            return false;
        while ($userRow = $result->fetch(PDO::FETCH_NUM)) {
            $first = $userRow[0];
            $last = $userRow[1];
            foreach (array($referring, $reading, $requesting) as $doc) {
                $match = $this->checkNamesMatch($doc, $first, $last);
                if ($match)
                    return $match;
            }
        }
        return $match;
    }

    function checkNamesMatchSelf($pid, $first, $last)
    {
        $match = false;
        if (!strlen($first) && !strlen($last))
            return false;
        $query = "select firstname,lastname from patient where origid=?";
        $bindList = array($pid);
        $result = $this->preparedStmt($query, $bindList);
        if ($result && ($drow = $result->fetch(PDO::FETCH_NUM))) {
            $dfirst = $drow[0];
            $dlast = $drow[1];
            if (!strcasecmp($dfirst, $first) && !strcasecmp($dlast, $last))
                $match = true;
        }
        return $match;
    }

    function sourceaeAssignedToUser($aet, $username)
    {
        $ret = false;
        $query = "select * from aeassigneduser where UPPER(aetitle)=UPPER(?) and UPPER(username)=UPPER(?)";
        $bindList = array($aet, $username);
        $result = $this->preparedStmt($query, $bindList);
        if (!$result) {
            print $this->getError();
            return false;
        }
        if ($result && ($result->rowCount() == 1))
            $ret = true;
        // check if the study was uploaded by this user
        if (!$ret && !strcasecmp($aet, "Upload-$username"))
            $ret = true;
        return $ret;
    }

    function checkUserFilter($username, $sourceae, $referdoc, $readingdoc, $patientId)
    {
        $match = false;
        $query = "select * from userfilter where username=?";
        $bindList = array($username);
        $result = $this->preparedStmt($query, $bindList);
        if ($result && $result->rowCount()) {
            $current = array(
                "sourceae"              => strtolower($sourceae),
                "referringphysician"    => strtolower($referdoc),
                "readingphysician"      => strtolower($readingdoc),
                "institution"           => "",
            );
            $filters = array();
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $attr = $row["attr"];
                $value = $row["value"];
                $filters[$attr][] = strtolower($value);
            }
            // check if Institution Name filter is defined and if so run a sub-query
            if (isset($filters["institution"])) {
                $query = "select institution from patient where origid=?";
                $bindList = array($patientId);
                $result = $this->preparedStmt($query, $bindList);
                if ($result && $result->rowCount())
                    $current["institution"] = strtolower($result->fetchColumn());
            }
            $count = 0;
            foreach ($filters as $attr => $entry) {
                if (in_array($current[$attr], $entry))
                    $count++;
            }
            $match = ($count == count($filters));
        }
        return $match;
    }

    function getAccessiblePatients($username)
    {
    $rows = array();
    $query = "select firstname,lastname,substring from privilege where username=?";
    $bindList = array($username);
    $result = $this->preparedStmt($query, $bindList);
    if (!$result || $result->rowCount() == 0)
        return $rows;
    // query the study table for matching referring or reading physician name
    $row = $result->fetch(PDO::FETCH_NUM);
    $firstName = $row[0];
    $lastName = $row[1];
    $substring = $row[2];
    $groups = array();
    $idlist = array();
    // check if this is a group username
    if (strcmp($firstName, "_GROUP")) {
        // check if this user belongs to any group
        $query = "select groupname from groupmember where username=?";
        $bindList = array($username);
        $result = $this->preparedStmt($query, $bindList);
        while ($result && ($group = $result->fetchColumn())) {
            $query = "select lastname,matchgroup,substring from privilege where username=?";
            $bindList = array($group);
            $priv = $this->preparedStmt($query, $bindList);
            if ($priv && ($row = $priv->fetch(PDO::FETCH_NUM))) {
                $groupName = $row[0];
                $groupMatch = $row[1];
                $substring = $row[2];
                $groups[] = array($group, $groupName, $groupMatch, $substring);
            }
        }
    }
    $result = $this->query("select patientid,referringphysician,readingphysician,requestingphysician,sourceae from study");
    while ($row = $result->fetch(PDO::FETCH_NUM)) {
        $match = false;
        $id = $row[0];
        // check if Patient Name matches with this user
        if ($this->checkNamesMatchSelf($id, $firstName, $lastName)) {
            if (!array_key_exists($id, $idlist))
                $idlist[ $id ] = $row;
            continue;
        }
        $referring = $row[1];
        $reading = $row[2];
        $requesting = $row[3];
        $sourceae = $row[4];
        if (strcmp($firstName, "_GROUP")) { // non-group user
            foreach (array($referring, $reading, $requesting) as $doc) {
                if (isset($doc) && strlen($doc)) {
                    if ($this->checkNamesMatch($doc, $firstName, $lastName)) {
                        $match = true;
                        break;
                    }
                }
            }
            // check group match if username does not match
            if (!$match && count($groups)) {
                foreach ($groups as $entry) {
                    $group = $entry[0];
                    $groupMatch = $entry[2];
                    if ($groupMatch)
                        $match = $this->checkNamesMatchGroup($group, $referring, $reading, $requesting);
                    if ($match)
                        break;
                    if ($this->sourceaeAssignedToUser($sourceae, $group)) {
                        $match = true;
                        break;
                    }
                    if ($this->checkUserFilter($group, $sourceae, $referring, $reading, $id)) {
                        $match = true;
                        break;
                    }
                }
            }
        }
        // check if this study was received from a source ae assigned to this user
        if (!$match && $this->sourceaeAssignedToUser($sourceae, $username))
            $match = true;
        // check if this study matches with any defined user access filters
        if (!$match && $this->checkUserFilter($username, $sourceae, $referring, $reading, $id))
            $match = true;
        if ($match && !array_key_exists($id, $idlist)) {
            $idlist[ $id ] = $row;
        }
    }
    foreach ($idlist as $id => $row) {
        $query = "select * from patient where private=1 and origid=?";
        $bindList = array($id);
        $result = $this->preparedStmt($query, $bindList);
        if ($result && $result->rowCount())
            $rows[$id] = $result->fetch(PDO::FETCH_ASSOC);
    }
    if (strcmp($firstName, "_GROUP") == 0)
        $groups[] = array($username, $lastName, false, $substring);
    if (count($groups)) {
        foreach ($groups as $entry) {
            $groupName = $entry[1];
            $substring = $entry[3];
            // add any patient accessible by this group
            $query = "select * from patient where institution";
            $query .= $substring? " LIKE '%$groupName%'" : "='$groupName'";
            $result = $this->query($query);
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $id = $row['origid'];
                if (!array_key_exists($id, $rows))
                    $rows[$id] = $row;
            }
        }
    }
    return $rows;
    }

    function getAccessibleStudies($origid, $username)
    {
    $rows = array();
    $query = "select firstname,lastname,substring from privilege where username=?";
    $bindList = array($username);
    $result = $this->preparedStmt($query, $bindList);
    if (!$result || $result->rowCount() == 0)
        return $rows;
    // query the study table for matching referring or reading physician name
    $row = $result->fetch(PDO::FETCH_NUM);
    $firstName = $row[0];
    $lastName = $row[1];
    $substring = $row[2];
    $groups = array();
    $patientMatch = $this->checkNamesMatchSelf($origid, $firstName, $lastName);
    if (!$patientMatch) {
        // check if this is a group username
        if (strcmp($firstName, "_GROUP")) {
            // check if this user belongs to any group
            $query = "select groupname from groupmember where username=?";
            $bindList = array($username);
            $result = $this->preparedStmt($query, $bindList);
            while ($result && ($group = $result->fetchColumn())) {
                $query = "select lastname,matchgroup,substring from privilege where username=?";
                $bindList = array($group);
                $priv = $this->preparedStmt($query, $bindList);
                if ($priv && ($row = $priv->fetch(PDO::FETCH_NUM))) {
                    $groupName = $row[0];
                    $groupMatch = $row[1];
                    $substring = $row[2];
                    $groups[] = array($group, $groupName, $groupMatch, $substring);
                }
            }
        } else {
            $groups[] = array($username, $lastName, false, $substring);
        }
        if (count($groups)) {
            foreach ($groups as $entry) {
                $groupName = $entry[1];
                $substring = $entry[3];
                $query = "select * from patient where origid=? and institution";
                $query .= $substring? " LIKE ?" : "=?";
                $bindList = array($origid, ($substring? "%$groupName%" : $groupName));
                $result = $this->preparedStmt($query, $bindList);
                if ($result->rowCount()) {
                    $patientMatch = true;
                    break;
                }
            }
        }
    }
    $query = "select * from study where private=1 and patientid=?";
    $bindList = array($origid);
    $result = $this->preparedStmt($query, $bindList);
    while ($result && $row = $result->fetch(PDO::FETCH_ASSOC)) {
        if ($patientMatch) {
            $match = true;
        } else {
            $referring = $row['referringphysician'];
            $reading = $row['readingphysician'];
            $requesting = $row['requestingphysician'];
            $sourceae = $row['sourceae'];
            $match = false;
            foreach (array($referring, $reading, $requesting) as $doc) {
                if (isset($doc) && strlen($doc)) {
                    if ($this->checkNamesMatch($doc, $firstName, $lastName)) {
                        $match = true;
                        break;
                    }
                }
            }
            // check group match if username does not match
            if (!$match && count($groups)) {
                foreach ($groups as $entry) {
                    $group = $entry[0];
                    $groupMatch = $entry[2];
                    if ($groupMatch)
                        $match = $this->checkNamesMatchGroup($group, $referring, $reading, $requesting);
                    if ($match)
                        break;
                    if ($this->sourceaeAssignedToUser($sourceae, $group)) {
                        $match = true;
                        break;
                    }
                    if ($this->checkUserFilter($group, $sourceae, $referring, $reading, $origid)) {
                        $match = true;
                        break;
                    }
                }
            }
            // check if this study matches with any defined user access filters
            if (!$match && $this->checkUserFilter($username, $sourceae, $referring, $reading, $origid))
                $match = true;
        }
        if ($match) {
            $rows[] = $row;
        } else {
            // check if this study was received from a source ae assigned to this user
            if ($this->sourceaeAssignedToUser($sourceae, $username))
                $rows[] = $row;
        }
    }
    return $rows;
    }

    function getNumAccessibleStudies($origid, $username)
    {
    $count = 0;
    $viewAccess = $this->hasaccess("viewprivate", $username);
    $bindList = array($origid);
    if ($viewAccess) {
        $query = "SELECT COUNT(*) FROM study where patientid=?";
        $result = $this->preparedStmt($query, $bindList);
        if ($result)
            $count += $result->fetchColumn();
    } else {
        // all public studies
        $query = "SELECT COUNT(*) FROM study where private=0 and patientid=?";
        $result = $this->preparedStmt($query, $bindList);
        if ($result)
            $count += $result->fetchColumn();
        // plus any private studies with matching referring or reading physician name
        $studies = $this->getAccessibleStudies($origid, $username);
        $count += count($studies);
    }
    return $count;
    }

    function getTodayStudies($username)
    {
    $rows = array();
    $query = "select firstname,lastname,substring from privilege where username=?";
    $bindList = array($username);
    $result = $this->preparedStmt($query, $bindList);
    if (!$result || $result->rowCount() == 0)
        return $rows;
    // query the study table for matching referring or reading physician name
    $row = $result->fetch(PDO::FETCH_NUM);
    $firstName = $row[0];
    $lastName = $row[1];
    $substring = $row[2];
    $groups = array();
    if (strcmp($firstName, "_GROUP")) {
        // check if this user belongs to any group
        $query = "select groupname from groupmember where username=?";
        $bindList = array($username);
        $result = $this->preparedStmt($query, $bindList);
        while ($result && $group = $result->fetchColumn()) {
            $query = "select lastname,matchgroup,substring from privilege where username=?";
            $bindList = array($group);
            $priv = $this->preparedStmt($query, $bindList);
            if ($priv && ($row = $priv->fetch(PDO::FETCH_NUM))) {
                $groupName = $row[0];
                $groupMatch = $row[1];
                $substring = $row[2];
                $groups[] = array($group, $groupName, $groupMatch, $substring);
            }
        }
    } else {
        $groups[] = array($username, $lastName, false, $substring);
    }
    $query = "select * from study LEFT JOIN patient ON study.patientid = patient.origid where study.private=1 and ";
    $query .= $this->useOracle?  "TRUNC(received)=TRUNC(SYSDATE)" : "DATE(received)=CURDATE()";
    $result = $this->query($query);
    while ($result && $row = $result->fetch(PDO::FETCH_ASSOC)) {
        $patientId = $row['patientid'];
        $match = $this->checkNamesMatchSelf($patientId, $firstName, $lastName);
        if ($match) {
            $rows[] = $row;
            continue;
        }
        $sourceae = $row['sourceae'];
        $referring = $row['referringphysician'];
        $reading = $row['readingphysician'];
        $requesting = $row['requestingphysician'];
        // check if this is a group username
        if (strcmp($firstName, "_GROUP")) {
            foreach (array($referring, $reading, $requesting) as $doc) {
                if (isset($doc) && strlen($doc)) {
                    if ($this->checkNamesMatch($doc, $firstName, $lastName)) {
                        $match = true;
                        break;
                    }
                }
            }
            // check group match if username does not match
            if (!$match && count($groups)) {
                foreach ($groups as $entry) {
                    $group = $entry[0];
                    $groupMatch = $entry[2];
                    if ($groupMatch)
                        $match = $this->checkNamesMatchGroup($group, $referring, $reading, $requesting);
                    if ($match)
                        break;
                    if ($this->checkUserFilter($group, $sourceae, $referring, $reading, $patientId)) {
                        $match = true;
                        break;
                    }
                }
            }
        }
        // check if this study matches with any defined user access filters
        if (!$match && $this->checkUserFilter($username, $sourceae, $referring, $reading, $patientId))
            $match = true;
        // plus any studies can be accessed by the group (s) this user belongs to
        if (!$match && count($groups)) {
            foreach ($groups as $entry) {
                $groupName = $entry[1];
                $substring = $entry[3];
                $query = "select * from patient where origid=? and institution";
                $query .= $substring? " LIKE ?" : "=?";
                $bindList = array($patientId, ($substring? "%$groupName%" : $groupName));
                $patient = $this->preparedStmt($query, $bindList);
                if ($patient && $patient->rowCount()) {
                    $match = true;
                    break;
                }
                if ($this->sourceaeAssignedToUser($sourceae, $groupName)) {
                    $match = true;
                    break;
                }
            }
        }
        if ($match) {
            $rows[] = $row;
        } else {
            // check if this study was received from a source ae assigned to this user
            if ($this->sourceaeAssignedToUser($sourceae, $username))
                $rows[] = $row;
        }
    }
    return $rows;
    }

    function accessPatient($patientId, $username)
    {
    $query = "select firstname,lastname,substring from privilege where username=?";
    $bindList = array($username);
    $result = $this->preparedStmt($query, $bindList);
    if (!$result || $result->rowCount() == 0)
        return false;
    $row = $result->fetch(PDO::FETCH_NUM);
    $firstName = $row[0];
    $lastName = $row[1];
    $substring = $row[2];
    if ($this->checkNamesMatchSelf($uid, $firstName, $lastName))
        return true;
    $groups = array();
    // check if this is a group username
    if (strcmp($firstName, "_GROUP")) {
        // check if this user belongs to any group
        $query = "select groupname from groupmember where username=?";
        $bindList = array($username);
        $result = $this->preparedStmt($query, $bindList);
        while ($result && ($group = $result->fetchColumn())) {
            $query = "select lastname,matchgroup,substring from privilege where username=?";
            $bindList = array($group);
            $priv = $this->preparedStmt($query, $bindList);
            if ($priv && ($row = $priv->fetch(PDO::FETCH_NUM))) {
                $groupName = $row[0];
                $groupMatch = $row[1];
                $substring = $row[2];
                $groups[] = array($group, $groupName, $groupMatch, $substring);
            }
        }
    } else {
        $groups[] = array($username, $lastName, false, $substring);
    }
    if (count($groups)) {
        foreach ($groups as $entry) {
            $groupName = $entry[1];
            $substring = $entry[3];
            $query = "select * from patient where origid=? and institution";
            $query .= $substring? " LIKE ?" : "=?";
            $bindList = array($patientId, ($substring? "%$groupName%" : $groupName));
            $result = $this->preparedStmt($query, $bindList);
            if ($result && $result->rowCount())
                return true;
        }
    }
    $match = false;
	$query = "SELECT * FROM study where patientid=?";
    $bindList = array($patientId);
    $result = $this->preparedStmt($query, $bindList);
	while (!$match && $result && ($row = $result->fetch(PDO::FETCH_ASSOC))) {
	    $private = $row['private'];
        if (!$private)
            return true;
        // check if matches with referring or reading physician name
        $referring = $row['referringphysician'];
        $reading = $row['readingphysician'];
        $requesting = $row['requestingphysician'];
	    $sourceae = $row['sourceae'];
        foreach (array($referring, $reading, $requesting) as $doc) {
            if (isset($doc) && strlen($doc)) {
                if ($this->checkNamesMatch($doc, $firstName, $lastName)) {
                    $match = true;
                    break;
                }
            }
        }
        // check group match if username does not match
        if (!$match && count($groups)) {
            foreach ($groups as $entry) {
                $group = $entry[0];
                $groupMatch = $entry[2];
                if ($groupMatch)
                    $match = $this->checkNamesMatchGroup($group, $referring, $reading, $requesting);
                if ($match)
                    break;
                if ($this->sourceaeAssignedToUser($sourceae, $group)) {
                    $match = true;
                    break;
                }
                if ($this->checkUserFilter($group, $sourceae, $referring, $reading, $uid)) {
                    $match = true;
                    break;
                }
            }
        }
        // check if this study was received from a source ae assigned to this user
        if ($this->sourceaeAssignedToUser($sourceae, $username)) {
            $match = true;
            break;
        }
        // check if this study matches with any defined user access filters
        if (!$match && $this->checkUserFilter($username, $sourceae, $referring, $reading, $uid))
            $match = true;
    }
    return $match;
    }

    function accessStudy($uid, $username)
    {
    if ($this->hasaccess("viewprivate", $username))
        return true;
	$query = "SELECT * FROM study where uuid=?";
    $bindList = array($uid);
    $result = $this->preparedStmt($query, $bindList);
	$row = $result->fetch(PDO::FETCH_ASSOC);
	$private = $row['private'];
    if (!$private)
        return true;
    // check if matches with referring or reading physician name
    $referring = $row['referringphysician'];
    $reading = $row['readingphysician'];
    $requesting = $row['requestingphysician'];
    $origid = $row["patientid"];
    $sourceae = $row["sourceae"];
    $query = "select firstname,lastname,substring from privilege where username=?";
    $bindList = array($username);
    $result = $this->preparedStmt($query, $bindList);
    if (!$result || $result->rowCount() == 0)
        return false;
    $row = $result->fetch(PDO::FETCH_NUM);
    $firstName = $row[0];
    $lastName = $row[1];
    $substring = $row[2];
    if ($this->checkNamesMatchSelf($origid, $firstName, $lastName))
        return true;
    $match = false;
    // check if this is a group username
    if (strcmp($firstName, "_GROUP")) {
        $groups = array();
        // check if this user belongs to any group
        $query = "select groupname from groupmember where username=?";
        $bindList = array($username);
        $result = $this->preparedStmt($query, $bindList);
        while ($result && ($group = $result->fetchColumn())) {
            $query = "select lastname,matchgroup,substring from privilege where username=?";
            $bindList = array($group);
            $priv = $this->preparedStmt($query, $bindList);
            if ($priv && ($row = $priv->fetch(PDO::FETCH_NUM))) {
                $groupName = $row[0];
                $groupMatch = $row[1];
                $substring = $row[2];
                $groups[] = array($group, $groupName, $groupMatch, $substring);
            }
        }
        foreach (array($referring, $reading, $requesting) as $doc) {
            if (isset($doc) && strlen($doc)) {
                if ($this->checkNamesMatch($doc, $firstName, $lastName)) {
                    $match = true;
                    break;
                }
            }
        }
        // check group match if username does not match
        if (!$match && count($groups)) {
            foreach ($groups as $entry) {
                $group = $entry[0];
                $groupMatch = $entry[2];
                if ($groupMatch)
                    $match = $this->checkNamesMatchGroup($group, $referring, $reading, $requesting);
                if ($match)
                    break;
                if ($this->sourceaeAssignedToUser($sourceae, $group)) {
                    $match = true;
                    break;
                }
                if ($this->checkUserFilter($group, $sourceae, $referring, $reading, $origid)) {
                    $match = true;
                    break;
                }
            }
        }
    } else {
        $groups[] = array($username, $lastName, false, $substring);
    }
    if (!$match && count($groups)) {
        // check if any group this user belongs to matches with the Institution Name of this patient
        foreach ($groups as $entry) {
            $groupName = $entry[1];
            $substring = $entry[3];
            $query = "select origid from patient where origid=? and institution";
            $query .= $substring? " LIKE ?" : "=?";
            $bindList = array($origid, ($substring? "%$groupName%" : $groupName));
            $result = $this->preparedStmt($query, $bindList);
            if ($result && $result->rowCount()) {
                $match = true;
                break;
            }
        }
    }
    // check if this study matches with any defined user access filters
    if (!$match && $this->checkUserFilter($username, $sourceae, $referring, $reading, $origid))
        $match = true;
    // check if this study was received from a source ae assigned to this user
    if (!$match && $this->sourceaeAssignedToUser($sourceae, $username))
        $match = true;
    return $match;
    }

    function getStudyModalities($uid)
    {
        $modalities = "";
        $query = "select modality from series where studyuid=?";
        $bindList = array($uid);
        $result = $this->preparedStmt($query, $bindList);
        while ($result && ($mod = $result->fetchColumn())) {
            if (strlen($mod) && !stristr($modalities, $mod)) {
                if (strlen($modalities))
                    $modalities .= " ";
                $modalities .= $mod;
            }
        }
        return $modalities;
    }

    function entryExists($table, $key, $uid)
    {
        $query = "select * from $table where $key=?";
        $bindList = array($uid);
        $result = $this->preparedStmt($query, $bindList);
        return ($result && $result->rowCount() > 0);
    }

    function findRefImage(&$uid)
    {
	    $url = "";
	    $query = "select seriesuid,modality from image inner join series on series.uuid=image.seriesuid where image.uuid=?";
        $bindList = array($uid);
        $result = $this->preparedStmt($query, $bindList);
	    if ($result && $result->rowCOunt()) {
            $row = $result->fetch(PDO::FETCH_NUM);
            $modality = $row[1];
            $page = (strlen($modality) && (!strcasecmp($modality, "SR") || !strcasecmp($modality, "KO")))? "showReport.php" : "showImage.php";
		    $url = "<a href=$page?id=$uid>$uid</a>";
	    }
	    return $url;
    }

    function getExportStudySize(&$studies)
    {
    $total = 0;
    foreach ($studies as $uid) {
        $query = "SELECT uuid FROM series WHERE studyuid=?";
        $bindList = array($uid);
        $result = $this->preparedStmt($query, $bindList);
        while ($result && $seriesUid = $result->fetchColumn()) {
            $instances = $this->query("SELECT path FROM image WHERE seriesuid='$seriesUid'");
            while ($instances && $path = $instances->fetchColumn())
                $total += filesize($path);
        }
    }
    return $total;
    }

    function getMyAeTitle()
    {
        $result = $this->query("SELECT aetitle FROM config");
        return $result->fetchColumn();
    }

    function getExportSize(&$level, &$uids)
    {
    if (strcasecmp($level, "Image") == 0)
        $total = $this->getExportImageSize($uids);
    else if (strcasecmp($level, "Series") == 0)
        $total = $this->getExportSerieSize($uids);
    else if (strcasecmp($level, "Study") == 0)
        $total = $this->getExportStudySize($uids);
    else if (strcasecmp($level, "Patient") == 0)
        $total = $this->getExportPatientSize($uids);
    else
        die ("<font color=red>Invalid Export Level: $level</font>");
    return $total;
    }

    function getExportImageSize(&$uids)
    {
    $total = 0;
    foreach ($uids as $uid) {
        $query = "SELECT path FROM image WHERE uuid=?";
        $bindList = array($uid);
        $result = $this->preparedStmt($query, $bindList);
        if ($result && $path = $result->fetchColumn())
            $total += filesize($path);
    }
    return $total;
    }

    function getExportSerieSize(&$uids)
    {
    $total = 0;
    foreach ($uids as $uid) {
        $query = "SELECT path FROM image WHERE seriesuid=?";
        $bindList = array($uid);
        $result = $this->preparedStmt($query, $bindList);
        while ($result && $path = $result->fetchColumn()) {
            $total += filesize($path);
        }
    }
    return $total;
    }

    function getExportPatientSize(&$uids)
    {
    $total = 0;
    foreach ($uids as $uid) {
        $query = "SELECT uuid FROM study WHERE patientid=?";
        $bindList = array($uid);
        $studies = $this->preparedStmt($query, $bindList);
        while ($studies && $studyUid = $studies->fetchColumn()) {
            $query = "SELECT uuid FROM series WHERE studyuid=?";
            $bindList = array($studyUid);
            $result = $this->preparedStmt($query, $bindList);
            while ($result && $seriesUid = $result->fetchColumn()) {
                $query = "SELECT path FROM image WHERE seriesuid=?";
                $bindList = array($seriesUid);
                $instances = $this->preparedStmt($query, $bindList);
                while ($instances && $path = $instances->fetchColumn())
                    $total += filesize($path);
            }
        }
    }
    return $total;
    }

    function getStudySizeCount(&$uid, &$size, &$count) {
        $query = "SELECT uuid FROM series WHERE studyuid=?";
        $bindList = array($uid);
        $result = $this->preparedStmt($query, $bindList);
        $count = 0;
        $size = 0;
        while ($result && $seriesUid = $result->fetchColumn()) {
            $query = "SELECT path FROM image WHERE seriesuid=?";
            $bindList = array($seriesUid);
            $instances = $this->preparedStmt($query, $bindList);
            while ($instances && $path = $instances->fetchColumn()) {
                $count++;
                $size += filesize($path);
            }
        }
    }

    function displayFileSize($total) {
        $KB = 1024;
        $MB = 1024 * $KB;
        $GB = 1024 * $MB;
        if ($total > $GB)
            $size = number_format($total / $GB, 2, '.', '') . " GB";
        else if ($total > $MB)
            $size = number_format($total / $MB, 2, '.', '') . " MB";
        else
            $size = number_format($total / $KB, 2, '.', '') . " KB";
        return $size;
    }

    function logJournal($username, $action, $type, $uid, $db = "")
    {
        global $CUSTOMIZE_PATIENT_ID;
        global $CUSTOMIZE_PATIENT_NAME;
        $details = "";
        $table = strlen($db)? "$db.journal" : "journal";
        $query = "INSERT INTO $table ";
        $fields = "timestamp,username,did,what,uuid";
        $values = $this->useOracle? "SYSDATE" : "NOW()";
        $values .= ",?,?,?";
        $bindList = array($username, $action, $type);
        if (strcasecmp($type, "Route")) {
            $values .= ",?";
            $bindList[] = $uid;
        } else
            $values .= ",'N/A'";
        // add details
        if (strcasecmp($type, "Patient") == 0) {
            if (strcasecmp($action, "Modify"))
                $details .= $CUSTOMIZE_PATIENT_NAME . ": " . $this->getPatientName($uid) . "<br>"; 
            else {
                $details .= $CUSTOMIZE_PATIENT_ID . ": " . $uid;
            }
        } else if (strcasecmp($type, "Study") == 0) {
            if (strcasecmp($action, "MatchOrm")) {
                $details .= $CUSTOMIZE_PATIENT_NAME . ": " . $this->getPatientNameByStudyUid($uid) . "<br>";
                $subq = "SELECT id,description FROM study WHERE uuid=?";
                $subList = array($uid);
                $result = $this->preparedStmt($subq, $subList);
                if ($result && ($row = $result->fetch(PDO::FETCH_NUM))) {
                    if (strlen($row[0]))
                        $details .= pacsone_gettext("Study ID: ") . $row[0] . "<br>";
                    if (strlen($row[1]))
                        $details .= pacsone_gettext("Study Description: ") . $row[1] . "<br>";
                }
            } else {
                $subq = "select details from dbjob where type='MatchORM' and uuid=?";
                $subList = array($uid);
                $result = $this->preparedStmt($subq, $subList);
                if ($result && ($row = $result->fetchColumn())) {
                    if (strlen($row))
                        $details .= pacsone_gettext("Details: ") . $row . "<br>";
                }
            }
        } else if (strcasecmp($type, "Series") == 0) {
            $details .= $CUSTOMIZE_PATIENT_NAME . ": " . $this->getPatientNameBySeriesUid($uid) . "<br>"; 
            $subq = "SELECT seriesnumber,description FROM series WHERE uuid=?";
            $subList = array($uid);
            $result = $this->preparedStmt($subq, $subList);
            if ($result && ($row = $result->fetch(PDO::FETCH_NUM))) {
                if (strlen($row[0]))
                    $details .= pacsone_gettext("Series Number: ") . $row[0] . "<br>";
                if (strlen($row[1]))
                    $details .= pacsone_gettext("Series Description: ") . $row[1] . "<br>";
            }
        } else if (strcasecmp($type, "Image") == 0) {
            $subq = "SELECT seriesuid,instance,instancedate FROM image WHERE uuid=?";
            $subList = array($uid);
            $result = $this->preparedStmt($subq, $subList);
            if ($result && ($row = $result->fetch(PDO::FETCH_NUM))) {
                $details .= $CUSTOMIZE_PATIENT_NAME . ": " . $this->getPatientNameBySeriesUid($row[0]) . "<br>"; 
                if (strlen($row[1]))
                    $details .= pacsone_gettext("Instance Number: ") . $row[1] . "<br>";
                if (strlen($row[2]))
                    $details .= pacsone_gettext("Instance Date: ") . $row[2] . "<br>";
            }
        } else if (strcasecmp($type, "AeTitle") == 0) {
            $subq = "SELECT description,hostname,ipaddr,port FROM applentity WHERE title=?";
            $subList = array($uid);
            $result = $this->preparedStmt($subq, $subList);
            if ($result && ($row = $result->fetch(PDO::FETCH_NUM))) {
                if (strlen($row[0]))
                    $details .= pacsone_gettext("Description: ") . $row[0] . "<br>";
                if (strlen($row[1]))
                    $details .= pacsone_gettext("Hostname: ") . $row[1] . "<br>";
                if (strlen($row[2]))
                    $details .= pacsone_gettext("IP Address: ") . $row[2] . "<br>";
                if (strlen($row[3]))
                    $details .= pacsone_gettext("TCP Port: ") . $row[3] . "<br>";
            }
        } else if (strcasecmp($type, "HL7App") == 0) {
            $subq = "SELECT description,hostname,ipaddr,port FROM hl7application WHERE name=?";
            $subList = array($uid);
            $result = $this->preparedStmt($subq, $subList);
            if ($result && ($row = $result->fetch(PDO::FETCH_NUM))) {
                if (strlen($row[0]))
                    $details .= pacsone_gettext("Description: ") . $row[0] . "<br>";
                if (strlen($row[1]))
                    $details .= pacsone_gettext("Hostname: ") . $row[1] . "<br>";
                if (strlen($row[2]))
                    $details .= pacsone_gettext("IP Address: ") . $row[2] . "<br>";
                if (strlen($row[3]))
                    $details .= pacsone_gettext("TCP Port: ") . $row[3] . "<br>";
            }
        } else if (strcasecmp($type, "Route") == 0) {
            $aes = explode("||", $uid);
            $source = $aes[0];
            $keytag = $aes[1];
            $group = $keytag >> 16;
            $element = $keytag & 0xFFFF;
            $dest = $aes[2];
            $details .= pacsone_gettext("Source AE: ") . $source . "<br>";
            $details .= pacsone_gettext("Key Tag: ") . sprintf("(%04x,%04x)", $group, $element) . "<br>";
            $details .= pacsone_gettext("Destination AE: ") . $dest . "<br>";
        } else if (strcasecmp($type, "HL7Route") == 0) {
            $source = $uid["source"];
            $keyname = $uid["keyname"];
            $pattern = $uid["pattern"];
            $dest = $uid["destination"];
            $details .= pacsone_gettext("Source Application: " ). $source . "<br>";
            if (strlen($keyname))
                $details .= pacsone_gettext("Key: ") . $keyname . "<br>";
            if (strlen($pattern))
                $details .= pacsone_gettext("Pattern: ") . $pattern . "<br>";
            $details .= pacsone_gettext("Destination: ") . $dest . "<br>";
        } else if (strcasecmp($type, "User") == 0) {
            $details .= pacsone_gettext("Username: ") . "$uid<br>"; 
            $subq = "SELECT firstname,middlename,lastname FROM privilege WHERE username=?";
            $subList = array($uid);
            $result = $this->preparedStmt($subq, $subList);
            $details .= pacsone_gettext("Full Name: "); 
            if ($result && ($row = $result->fetch(PDO::FETCH_NUM))) {
                if (strlen($row[0]))
                    $details .= " " . $row[0];
                if (strlen($row[1]))
                    $details .= " " . $row[1];
                if (strlen($row[2]))
                    $details .= " " . $row[2];
            }
            $details .= "<br>";
        } else if (strcasecmp($type, "Worklist") == 0) {
            $subq = "SELECT id,patientname,patientid FROM worklist WHERE studyuid=?";
            $subList = array($uid);
            $result = $this->preparedStmt($subq, $subList);
            if ($result && ($row = $result->fetch(PDO::FETCH_NUM))) {
                if (strlen($row[0]))
                    $details .= pacsone_gettext("Worklist ID: ") . $row[0] . "<br>";
                if (strlen($row[1]))
                    $details .= $CUSTOMIZE_PATIENT_NAME . ": " . $row[1] . "<br>";
                if (strlen($row[2]))
                    $details .= $CUSTOMIZE_PATIENT_ID . ": " . $row[2] . "<br>";
            }
        } else if (!strcasecmp($type, "StudyNotes") ||
                   !strcasecmp($type, "ImageNotes")) {
            if (strcasecmp($action, "Delete")) {
                $subq = "SELECT headline FROM $type WHERE id=?";
                $subList = array($uid);
                $result = $this->preparedStmt($subq, $subList);
                if ($result && ($row = $result->fetchColumn()))
                    $details .= pacsone_gettext("Subject: ") . $row . "<br>";
            }
        }
        // add Export destination to details
        if (strcasecmp($action, "Export") == 0) {
            $subq = "SELECT jobid from export WHERE class=? and uuid=?";
            $subList = array($type, $uid);
            $result = $this->preparedStmt($subq, $subList);
            if ($result && ($row = $result->fetch(PDO::FETCH_NUM))) {
                $subq = "SELECT details FROM dbjob WHERE id=?";
                $subList = array($row[0]);
                $result = $this->preparedStmt($subq, $subList);
                if ($result && $row = $result->fetchColumn()) {
                    $details .= pacsone_gettext("Destination Path: ") . $row . "<br>";
                }
            }
        }
        // add Forward/Print destination to details
        else if (!strcasecmp($action, "Forward") || !strcasecmp($action, "Print")) {
            $subq = "SELECT aetitle from dbjob WHERE type=? and class=? and uuid=?";
            $subList = array($action, $type, $uid);
            $result = $this->preparedStmt($subq, $subList);
            if ($result && $row = $result->fetchColumn()) {
                $details .= pacsone_gettext("Destination AE: ") . $row . "<br>";
            }
        }
        // add Import destination to details
        else if (!strcasecmp($action, "Import")) {
            if (strlen($type) > 1) {
                $details .= pacsone_gettext("From: ") . substr($type, 1) . "<br>";
                $details .= pacsone_gettext("To Folder: ") . "$uid<br>";
            } else {
                $details .= pacsone_gettext("From: ") . "$uid<br>";
            }
        }
        if (strlen($details)) {
            $fields .= ",details";
            $values .= ",?";
            $bindList[] = $details;
        }
        $query .= "($fields) VALUES($values)";
        return $this->preparedStmt($query, $bindList);
    }

    function getEmailAddress($username, $aetitle = "")
    {
        if ($this->isAdministrator($username) ||
            $this->isReservedUser($username, $aetitle)) {
            $result = $this->query("select adminemail from config");
        } else {
            $query = "select email from privilege where username=?";
            $bindList = array($username);
            $result = $this->preparedStmt($query, $bindList);
        }
        if ($result && $row = $result->fetchColumn())
            return $row;
        else
            return false;
    }

    function getFirstImage(&$seriesUid)
    {
        $query = "select uuid from image where seriesuid=? order by instance asc";
        $bindList = array($seriesUid);
        $result = $this->preparedStmt($query, $bindList);
        if ($result && $row = $result->fetchColumn())
            return $row;
        else
            return false;
    }

    function checkIsMyNote($table, &$username, $id, $author)
    {
        $ret = 0;
        $query = "select count(id) from $table where id=? and username=?";
        $bindList = array($id, $username);
        $result = $this->preparedStmt($query, $bindList);
        if ($result && ($row = $result->fetch(PDO::FETCH_NUM)))
            $ret = $row[0];
        if (!$ret) {
            // check if the author is sharing notes with others
            $query = "select sharenotes from privilege where username=?";
            $bindList = array($author);
            $result = $this->preparedStmt($query, $bindList);
            if ($result && ($row = $result->fetch(PDO::FETCH_NUM)))
                $ret = $row[0];
        }
        return $ret;
    }

    function checkAnyMyNote($table, &$username, $uid)
    {
        $query = "select count(id) from $table where uuid=? and username=?";
        $bindList = array($uid, $username);
        $result = $this->preparedStmt($query, $bindList);
        if ($result && ($row = $result->fetch(PDO::FETCH_NUM)))
            return $row[0];
        else
            return 0;
    }

    function getStudyInstanceCount(&$uid)
    {
        $count = 0;
        $query = "select uuid from series where studyuid=?";
        $bindList = array($uid);
        $result = $this->preparedStmt($query, $bindList);
        while ($result && $seriesUid = $result->fetchColumn()) {
            $query = "select count(*) from image where seriesuid=?";
            $bindList = array($seriesUid);
            $imageRes = $this->preparedStmt($query, $bindList);
            $count += $imageRes->fetchColumn();
        }
        return $count;
    }

    function getStudySeriesCount(&$uid)
    {
        $count = 0;
        $query = "select count(*) from series where studyuid=?";
        $bindList = array($uid);
        $result = $this->preparedStmt($query, $bindList);
        if ($result && ($row = $result->fetch(PDO::FETCH_NUM)))
            $count = (int)$row[0];
        return $count;
    }

    function isEuropeanDateFormat()
    {
        $euro = 0;
        $result = $this->query("select dateformat from config where dateformat is not NULL");
        if ($result && $result->rowCount())
            $euro = 1;
        return $euro;
    }

    function formatDate(&$value)
    {
        if (!strlen($value))
            return $value;
        $func = "DATE_FORMAT";
        if ($this->useOracle)
            $func = "TO_CHAR";
        $query = "select $func(?, dateformat) from config where dateformat is not NULL";
        $bindList = array($value);
        $result = $this->preparedStmt($query, $bindList);
        if ($result && ($date = $result->fetch(PDO::FETCH_NUM))) {
            $value = $date[0];
        }
        return $value;
    }
    function formatDateTime(&$value)
    {
        if (!strlen($value))
            return $value;
        $func = "DATE_FORMAT";
        if ($this->useOracle)
            $func = "TO_CHAR";
        $query = "select $func(?, datetimeformat) from config where datetimeformat is not NULL";
        $bindList = array($value);
        $result = $this->preparedStmt($query, $bindList);
        if ($result && ($date = $result->fetch(PDO::FETCH_NUM))) {
            $value = $date[0];
        }
        return $value;
    }
    function hasSRseries($uid)
    {
        $ret = false;
        $query = "select modality from series where modality='SR' and studyuid=?";
        $bindList = array($uid);
        $result = $this->preparedStmt($query, $bindList);
        if ($result && $result->rowCount())
            $ret = true;
        return $ret;
    }
    function findExistingUsers() {
        $users = array();
        $this->selectDb("mysql");
        $result = $this->query("SELECT User FROM user");
        while ($result && $row = $result->fetch(PDO::FETCH_NUM)) {
            if ($row[0] && strcasecmp($row[0], "root"))
                $users[] = $row[0];
        }
        return $users;
    }
    function getStudyViewColumns($username, $showPatientInfo) {
        global $STUDY_VIEW_COLUMNS_TBL;
        $columns = array();
        $enabled = $STUDY_VIEW_COLUMNS_TBL;
        global $PATIENT_INFO_STUDY_VIEW_TBL;
        // check if any Storage Commitment Report SCP is enabled
        $result = $this->query("select * from applentity where commitscp=1 and reqcommitment=1 order by title asc");
        $commitReport = ($result && $result->rowCount())? true : false;
        $query = "select * from studyview where username=?";
        $bindList = array($username);
        $result = $this->preparedStmt($query, $bindList);
        while ($result && ($row = $result->fetch(PDO::FETCH_ASSOC))) {
            $coln = strtolower($row['columnname']);
            $yesno = $row['enabled'];
            if (!strcasecmp($coln, "commitreport"))
                $yesno = $commitReport? $yesno : 0;
            $enabled[$coln][1] = $yesno;
        }
        foreach ($enabled as $coln => $value) {
            if (in_array($coln, $PATIENT_INFO_STUDY_VIEW_TBL) && !$showPatientInfo)
                continue;
            $descr = $value[0];
            if ($value[1])
                $columns[$descr] = $coln;
        }
        return $columns;
    }
    function isVeterinary() {
        $ret = false;
        $result = $this->query("select veterinary from config");
        if ($result && ($vet = $result->fetch(PDO::FETCH_NUM)))
            $ret = $vet[0];
        return $ret;
    }
    function isVeterinaryColumn($column) {
        global $PATIENT_VIEW_COLUMNS_TBL;
        global $PATIENT_VIEW_COLUMNS_TBL_VET;
        $key = strtolower($column);
        return (array_key_exists($key, $PATIENT_VIEW_COLUMNS_TBL_VET) && !array_key_exists($key, $PATIENT_VIEW_COLUMNS_TBL));
    }
    function getPatientViewColumns($username) {
        global $PATIENT_VIEW_COLUMNS_TBL;
        global $PATIENT_VIEW_COLUMNS_TBL_VET;
        $columns = array();
        $enabled = ($this->isVeterinary())? $PATIENT_VIEW_COLUMNS_TBL_VET : $PATIENT_VIEW_COLUMNS_TBL;
        $query = "select * from patientview where username=?";
        $bindList = array($username);
        $result = $this->preparedStmt($query, $bindList);
        while ($result && ($row = $result->fetch(PDO::FETCH_ASSOC))) {
            $coln = strtolower($row['columnname']);
            if (!isset($enabled[$coln]))
                continue;
            $yesno = $row['enabled'];
            $enabled[$coln][1] = $yesno;
        }
        foreach ($enabled as $coln => $value) {
            $descr = $value[0];
            if ($value[1])
                $columns[$descr] = $coln;
        }
        return $columns;
    }
    function getObservationReports($accessNum) {
        $uid = "";
        $query = "select distinct controlid from hl7message inner join hl7segobr on hl7message.controlid=hl7segobr.uuid where UPPER(placerfield1)=UPPER(?) and UPPER(hl7message.type) like 'ORU%'";
        $bindList = array($accessNum);
        $result = $this->preparedStmt($query, $bindList);
        if ($result && ($row = $result->fetch(PDO::FETCH_NUM)))
            $uid = $row[0];
        else {
            // check the Placer Order Number/OBR-2 as well
            $query = "select distinct controlid from hl7message inner join hl7segobr on hl7message.controlid=hl7segobr.uuid where UPPER(placerordernum)=UPPER(?) and UPPER(hl7message.type) like 'ORU%'";
            $result = $this->preparedStmt($query, $bindList);
            if ($result && ($row = $result->fetch(PDO::FETCH_NUM)))
                $uid = $row[0];
        }
        return $uid;
    }
    function getAllUsers(&$databases) {
        $users = array();
        foreach ($databases as $db) {
            $result = $this->query("select username from $db.privilege");
            while ($result && ($row = $result->fetch(PDO::FETCH_NUM))) {
                $user = strtolower($row[0]);
                if (!in_array($user, $users))
                    $users[] = $user;
            }
        }
        return $users;
    }
    function checkIfUserExists($user) {
        $ret = false;
        $query = "select user from mysql.user where user=?";
        if ($this->useOracle)
            $query = "select username from dba_users where username=?";
        $bindList = array($user);
        $result = $this->preparedStmt($query, $bindList);
        if ($result && $result->rowCount()) {
            $ret = true;
        }
        return $ret;
    }
    function getSharedInstances($user) {
        require_once "utils.php";
        $instances = getServerInstances();
        $count = 0;
        foreach ($instances as $instance => $db) {
            $query = "select * from $db.privilege where username=?";
            $bindList = array($user);
            $result = $this->preparedStmt($query, $bindList);
            if ($result && $result->rowCount()) {
                $count++;
            }
        }
        return $count;
    }
    function getStorageCommitStatus($uid) {
        $ret = false;
        $allSeriesCommit = true;
        $query = "select uuid from series where studyuid=?";
        $bindList = array($uid);
        $result = $this->preparedStmt($query, $bindList);
        while ($result && $seriesUid = $result->fetchColumn()) {
            // check if any images of this series has storage commitment report
            $query = "select distinct * from image inner join commitsopref on image.uuid=commitsopref.sopinstance where seriesuid=?";
            $bindList = array($seriesUid);
            $commit = $this->preparedStmt($query, $bindList);
            if ($commit && $commit->rowCount() == 0) {
                // no report for this series
                $allSeriesCommit = false;
                continue;
            }
            // find out how many images of this series are pending storage commitment report
            $query = "select * from image inner join commitsopref on image.uuid=commitsopref.sopinstance where seriesuid=? and commitsopref.status is null";
            $bindList = array($seriesUid);
            $commit = $this->preparedStmt($query, $bindList);
            if ($commit && $commit->rowCount()) {
                $ret = array("icon" => "commitReq.png", "descr" => pacsone_gettext("Commitment Report Requested"));
                break;
            }
            // verify if all instances of this series have been committed
            $query = "select uuid from image where seriesuid=?";
            $bindList = array($seriesUid);
            $image = $this->preparedStmt($query, $bindList);
            while ($image && $instanceUid = $image->fetchColumn()) {
                $query = "select * from commitsopref where sopinstance=? and status=0";
                $bindList = array($instanceUid);
                $commit = $this->preparedStmt($query, $bindList);
                if (!$commit || $commit->rowCount() == 0) {
                    // partially committed
                    $ret = array("icon" => "partialCommit.png", "descr" => pacsone_gettext("Partially Committed"));
                    break;
                }
            }
        }
        if (!$ret && $allSeriesCommit) {
            $ret = array("icon" => "fullyCommit.png", "descr" => pacsone_gettext("Fully Committed"));
        }
        return $ret;
    }
    function getBrowserCharset() {
        $charset = "";
        $result = $this->query("select charset from config where charset is not NULL");
        if ($result && ($row = $result->fetch(PDO::FETCH_NUM))) {
            $charset = $row[0];
            global $DICOM_CHARSET_TBL;
            if (isset($DICOM_CHARSET_TBL[$charset])) {
                $value = $DICOM_CHARSET_TBL[$charset][1][0];
                $charset = $value;
            }
        }
        return $charset;
    }
    function getCharsetEscape() {
        $esc = "";
        $result = $this->query("select charset from config where charset is not NULL");
        if ($result && ($row = $result->fetch(PDO::FETCH_NUM))) {
            $charset = $row[0];
            global $DICOM_CHARSET_TBL;
            if (isset($DICOM_CHARSET_TBL[$charset])) {
                $esc = $DICOM_CHARSET_TBL[$charset][1][1];
            }
        }
        return $esc;
    }
    function convertCharset($value, $current) {
        $converted = $value;
        global $DICOM_CHARSET_TBL;
        if (strlen($current) && isset($DICOM_CHARSET_TBL[strtoupper($current)])) {
            $from = $DICOM_CHARSET_TBL[$current][1][0];
            $charset = $this->getBrowserCharset();
            if (strlen($charset) && strcasecmp($charset, $from))
                $converted = iconv($from, "$charset//TRANSLIT//IGNORE", $value);
        }
        return $converted;
    }
    function getHostname() {
        return strcasecmp($this->hostname, "localhost")? "%" : "localhost";
    }
    function getAutoConvertJPG() {
        $autoconvert = false;
        $result = $this->query("select autoconvert from config");
        if ($result && ($row = $result->fetch(PDO::FETCH_NUM))) {
            $autoconvert = $row[0];
        }
        return $autoconvert;
    }
    function getStudyUidBySeriesUid($uid)
    {
        $studyUid = "";
	    $query = "SELECT studyuid FROM series WHERE uuid=?";
        $bindList = array($uid);
        $result = $this->preparedStmt($query, $bindList);
	    if ($result && ($row = $result->fetch(PDO::FETCH_NUM))) {
            $studyUid = $row[0];
        }
	    return $studyUid;
    }
    function findRefSeries(&$uid)
    {
	    $url = "";
	    $query = "select uuid,modality from series where uuid=?";
        $bindList = array($uid);
        $result = $this->preparedStmt($query, $bindList);
	    if ($result && $result->rowCount()) {
            $row = $result->fetch(PDO::FETCH_NUM);
            $modality = $row[1];
            $page = (strlen($modality) && (!strcasecmp($modality, "SR") || !strcasecmp($modality, "KO")))? "sreport.php" : "image.php";
		    $url = "<a href=$page?seriesId=$uid>$uid</a>";
	    }
	    return $url;
    }
    function findRefStudy(&$uid)
    {
	    $url = "";
	    $query = "select uuid from study where uuid=?";
        $bindList = array($uid);
        $result = $this->preparedStmt($query, $bindList);
	    if ($result && $result->rowCount())
		    $url = "<a href=series.php?studyId=$uid>$uid</a>";
	    return $url;
    }
    function getExternalAccessUrl() {
        $url = "";
        $result = $this->query("select externalAccessUrl from config");
        if ($result && ($row = $result->fetch(PDO::FETCH_NUM))) {
            $url = $row[0];
            // append trailing '/' if necessary
            if (strlen($url) && strcmp(substr($url, strlen($url)-1, 1), "/"))
                $url .= "/";
        }
        return $url;
    }
    function getAutoRefresh($username) {
        global $AUTO_REFRESH_DEFAULT;
        $ret = $AUTO_REFRESH_DEFAULT;
        $query = "select refreshperiod from privilege where username=?";
        $bindList = array($username);
        $result = $this->preparedStmt($query, $bindList);
        if ($result && ($row = $result->fetch(PDO::FETCH_NUM)))
            $ret = $row[0];
        return $ret;
    }
    function isAeGroup($aet) {
        $ret = false;
        $query = "select title from applentity where title=? and aegroup!=0";
        $bindList = array($aet);
        $result = $this->preparedStmt($query, $bindList);
        if ($result && $result->rowCount())
            $ret = true;
        return $ret;
    }
    function getWADOUrl($uuid, $seriesUid) {
        $url = "wadouri:" . parseUrlSelfPrefix();
        $url .= "/wado.php?requestType=WADO&contentType=";
        $url .= urlencode("application/dicom");
        $url .= "&objectUID=" . urlencode($uuid);
        $url .= "&seriesUID=" . urlencode($seriesUid);
        $sid = session_id();
        if (strlen($sid))
            $url .= "&sessionid=" . urlencode($sid);
        return $url;
    }
    function getWadoRsStudyUrl($uuid) {
        $url = parseDicomWebPrefix();
        $url .= "/wadors.php/studies/" . urlencode($uuid);
        $sid = session_id();
        if (strlen($sid))
            $url .= "?sessionid=" . urlencode($sid);
        return $url;
    }
    function getWadoRsSeriesUrl($studyUid, $uuid) {
        $url = parseDicomWebPrefix();
        $url .= "/wadors.php/studies/" . urlencode($studyUid);
        $url .= "/series/" . urlencode($uuid);
        $sid = session_id();
        if (strlen($sid))
            $url .= "?sessionid=" . urlencode($sid);
        return $url;
    }
    function getWadoRsInstanceUrl($studyUid, $seriesUid, $uuid) {
        $url = parseDicomWebPrefix();
        $url .= "/wadors.php/studies/" . urlencode($studyUid);
        $url .= "/series/" . urlencode($seriesUid);
        $url .= "/instances/" . urlencode($uuid);
        $sid = session_id();
        if (strlen($sid))
            $url .= "?sessionid=" . urlencode($sid);
        return $url;
    }
    function getStowRsStoreDir() {
        $dir = dirname($_SERVER['SCRIPT_FILENAME']);
        $dir = substr($dir, 0, strlen($dir) - 3);
        $result = $this->query("select archivedir from config");
        if ($result && ($row = $result->fetch(PDO::FETCH_NUM)))
            $dir = $row[0];
        // append '/' at the end if not so already
        if (strcmp(substr($dir, strlen($dir)-1, 1), "/"))
            $dir .= "/";
        $dir .= "stowrs/";
        if (!file_exists($dir))
            mkdir($dir);
        return $dir;
    }
    function isReservedUser($username, $aetitle) {
        $ini = parseIniByAeTitle($aetitle);
        $reserved = isset($ini['Username'])? $ini['Username'] : "";
        return strcasecmp($username, $reserved);
    }
}

class MyConnection extends MyDatabase {
    var $database;
    var $username;
    var $password;

    function __construct() {
        if (!session_id())
            session_start();
        $hostname = isset($_SESSION['authenticatedHost'])? $_SESSION['authenticatedHost'] : "localhost";
        $this->database = isset($_SESSION['authenticatedDatabase'])? $_SESSION['authenticatedDatabase'] : "dicom";

        //echo("database.php-1958-".$this->database);

        require_once 'authenticatedSession.php';
        $authenticated = new DecryptedSession();
        $this->username = $authenticated->getUsername();
        $this->password = $authenticated->getPassword();
        // call base class constructor
        parent::__construct($hostname, $this->database, $this->username, $this->password, $_SESSION['aetitle']);
    }
    function __destruct() {
        // call base class destructor
        parent::__destruct();
    }
    function getPageSize() {
        global $DEFAULT_PAGE_SIZE;
        $value = $DEFAULT_PAGE_SIZE;
        $query = "select pagesize from privilege where username=?";
        $bindList = array($this->username);
        $result = $this->preparedStmt($query, $bindList);
        if ($result && ($row = $result->fetch(PDO::FETCH_NUM))) {
            $value = $row[0];
        }
        return $value;
    }
    function showStudyNotesIcon(&$username) {
        $value = 1;
        $query = "select studynoteicon from privilege where username=?";
        $bindList = array($username);
        $result = $this->preparedStmt($query, $bindList);
        if ($result && ($row = $result->fetch(PDO::FETCH_NUM)))
            $value = $row[0];
        return $value;
    }
    function getStudyFilters(&$username) {
        $data = false;
        $query = "select * from studyfilter where username=?";
        $bindList = array($username);
        $result = $this->preparedStmt($query, $bindList);
        if ($result && ($row = $result->fetch(PDO::FETCH_ASSOC)))
            $data = $row;
        return $data;
    }
    function filterStudies(&$username) {
        // build the filter strings for the WHERE clause in the SQL query
        $sql = "";
        $query = "select * from studyfilter where username=?";
        $bindList = array($username);
        $result = $this->preparedStmt($query, $bindList);
        if ($result && ($row = $result->fetch(PDO::FETCH_ASSOC))) {
            global $STUDY_FILTER_STATUS_READ;
            global $STUDY_FILTER_STUDYDATE_MASK;
            global $STUDY_FILTER_STUDYDATE_MASK_BITS;
            global $STUDY_FILTER_STUDYDATE_LAST_N_DAYS;
            global $STUDY_FILTER_STUDYDATE_FROM_TO;
            global $STUDY_FILTER_BY_REFERRING_DOC;
            global $STUDY_FILTER_BY_READING_DOC;
            global $STUDY_FILTER_BY_DATE_RECEIVED;
            // filter by study status
            $status = $row['status'];
            if ($status)
                $sql .= "reviewed " . (($status == $STUDY_FILTER_STATUS_READ)? "IS NOT" : "IS") . " NULL";
            // filter by study date
            $studyDate = $row['studydate'] & $STUDY_FILTER_STUDYDATE_MASK;
            $period = 0;
            if ($studyDate == $STUDY_FILTER_STUDYDATE_FROM_TO) {
                $dateFrom = isset($row['datefrom'])? $row['datefrom'] : "";
                $dateTo = isset($row['dateto'])? $row['dateto'] : "";
                if (strlen($dateFrom)) {
                    if (strlen($sql))
                        $sql .= " AND ";
                    $sql .= "studydate >= " . ($this->useOracle? "TO_DATE('$dateFrom','YYYY-MM-DD')" : "'$dateFrom'");
                }
                if (strlen($dateTo)) {
                    if (strlen($sql))
                        $sql .= " AND ";
                    $sql .= "studydate <= " . ($this->useOracle? "TO_DATE('$dateTo','YYYY-MM-DD')" : "'$dateTo'");
                }
            } else if ($studyDate == $STUDY_FILTER_STUDYDATE_LAST_N_DAYS) {
                $period = ($row['studydate'] >> $STUDY_FILTER_STUDYDATE_MASK_BITS);
                if (strlen($sql))
                    $sql .= " AND ";
                if ($this->useOracle)
                    $sql .= sprintf("(TRUNC(SYSDATE) - TRUNC(studydate)) <= %d", $period - 1);
                else
                    $sql .= sprintf("(TO_DAYS(NOW()) - TO_DAYS(studydate)) <= %d", $period - 1);
            } else if ($studyDate) {    // studies from today, yesterday, or the day before yesterday
                if (strlen($sql))
                    $sql .= " AND ";
                if ($this->useOracle)
                    $sql .= sprintf("(TRUNC(SYSDATE) - TRUNC(studydate)) = %d", $studyDate - 1);
                else
                    $sql .= sprintf("(TO_DAYS(NOW()) - TO_DAYS(studydate)) = %d", $studyDate - 1);
            }
            // filter by referring or reading physician, or date when study was received
            $filterBy = $row['filterby'];
            if ($filterBy & $STUDY_FILTER_BY_REFERRING_DOC) {
                $referdoc = $row['referdoc'];
                if (strlen($referdoc)) {
                    if (strlen($sql))
                        $sql .= " AND ";
                    $sql .= "referringphysician" . wildcardReplace($referdoc);
                }
            }
            if ($filterBy & $STUDY_FILTER_BY_READING_DOC) {
                $readdoc = $row['readdoc'];
                if (strlen($readdoc)) {
                    if (strlen($sql))
                        $sql .= " AND ";
                    $sql .= "readingphysician" . wildcardReplace($readdoc);
                }
            }
            if ($filterBy & $STUDY_FILTER_BY_DATE_RECEIVED) {
                $receivedFrom = isset($row['receivedfrom'])? $row['receivedfrom'] : "";
                if (strlen($receivedFrom)) {
                    if (strlen($sql))
                        $sql .= " AND ";
                    $sql .= "received >= " . ($this->useOracle? "TO_DATE('$receivedFrom','YYYY-MM-DD')" : "'$receivedFrom'");
                }
                $receivedTo = isset($row['receivedto'])? $row['receivedto'] : "";
                if (strlen($receivedTo)) {
                    if (strlen($sql))
                        $sql .= " AND ";
                    $sql .= "received <= " . ($this->useOracle? "TO_DATE('$receivedTo','YYYY-MM-DD')" : "'$receivedTo'");
                }
            }
        }
        return $sql;
    }
    function isUserFilterEnabled() {
        $enabled = false;
        $result = $this->query("select userfilter from config");
        if ($result && ($row = $result->fetch(PDO::FETCH_NUM)))
            $enabled = $row[0];
        return $enabled;
    }
    function downloadFilenamePrefix(&$option, &$entry) {
        $prefix = "";
        $pids = array();
        $studies = array();
        $series = array();
        if (strcasecmp($option, "Image") == 0) {
            foreach ($entry as $uid) {
                $query = "select seriesuid from image where uuid=?";
                $bindList = array($uid);
                $result = $this->preparedStmt($query, $bindList);
                if ($result && ($row = $result->fetch(PDO::FETCH_NUM))) {
                    if (!in_array($row[0], $series))
                        $series[] = $row[0];
                }
            }
        }
        if (strcasecmp($option, "Series") == 0) {
            $series = array_merge($series, $entry);
        }
        foreach ($series as $uid) {
            $query = "select studyuid from series where uuid=?";
            $bindList = array($uid);
            $result = $this->preparedStmt($query, $bindList);
            if ($result && ($row = $result->fetch(PDO::FETCH_NUM))) {
                if (!in_array($row[0], $studies))
                    $studies[] = $row[0];
            }
        }
        if (strcasecmp($option, "Study") == 0) {
            $studies = array_merge($studies, $entry);
        }
        foreach ($studies as $uid) {
            $query = "select patientid from study where uuid=?";
            $bindList = array($uid);
            $result = $this->preparedStmt($query, $bindList);
            if ($result && ($row = $result->fetch(PDO::FETCH_NUM))) {
                if (!in_array($row[0], $studies))
                    $pids[] = $row[0];
            }
        }
        if (strcasecmp($option, "Patient") == 0) {
            $pids = array_merge($pids, $entry);
        }
        if (count($pids) == 1) {
            // all selected items belong to the same patient
            $prefix = preg_replace('/,\s+/', '', $this->getPatientName($pids[0]));
            $prefix .= "-";
        }
        return $prefix;
    }
}

?>
