<?php
//
// remoteWorklist.php
//
// Module for querying remote modality worklist SCP
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once "dicom.php";
require_once "header.php";

$aetitle = $_REQUEST['aetitle'];
if (preg_match("/[';\"]/", $aetitle)) {
    $error = pacsone_gettext("Invalid AE Title");
    print "<h2><font color=red>$error</font></h2>";
    exit();
}
$patientid = urldecode($_REQUEST['patientid']);
$lastname = urldecode($_REQUEST['lastname']);
$firstname = urldecode($_REQUEST['firstname']);
$patientname = "";
if (strlen($lastname) || strlen($firstname))
	$patientname = $lastname . "^" . $firstname;
$station = urldecode($_REQUEST['station']);
$startdate = "";
if (isset($_REQUEST['datetype']) || isset($_REQUEST['date'])) {
	$type = $_REQUEST['datetype'];
	if ($type == 0) {           // anydate
	} else if ($type == 1) {
		$startdate = date("Ymd");
	} else if ($type == 2) {
		$yesterday = time() - 24*60*60;
		$startdate = date("Ymd", $yesterday);
	} else if ($type == 3) {
		$startdate = urldecode($_REQUEST['date']);
		$startdate = date("Ymd", strtotime($startdate));
	} else if ($type == 4) {
		// convert to "YYYYMMDD-YYYYMMDD" format
		$from = "";
		if (strlen($_REQUEST['from'])) {
			$from = urldecode($_REQUEST['from']);
			$time = strtotime($from);
			$from = date("Ymd", $time);
		}
		$to = "";
		if (strlen($_REQUEST['to'])) {
			$to = urldecode($_REQUEST['to']);
			$time = strtotime($to);
			$to = date("Ymd", $time);
		}
		$startdate = $from . "-" . $to;
	} else {
		die (sprintf(pacsone_gettext("Invalid date type: %s."), $type));
	}
}
$starttime = "";
if (isset($_REQUEST['timetype']) || isset($_REQUEST['time'])) {
	$type = $_REQUEST['timetype'];
	if ($type == 1) {			// anytime
		//$starttime = "000000-235959";
	} else if ($type == 2) {	// at this exact time
		// convert from HH:MM to HHMMSS.frac format
		$time = urldecode($_REQUEST['time']);
		$starttime = implode("", explode(":", $time)) . "00";
	} else if ($type == 3) {	// time range
		// convert from HH:MM to HHMMSS.frac format
		$from = urldecode($_REQUEST['fromtime']);
		$from = implode("", explode(":", $from)) . "00";
		$to = urldecode($_REQUEST['totime']);
		$to = implode("", explode(":", $to)) . "00";
		$starttime = $from . "-" . $to;
	} else {
		die (sprintf(pacsone_gettext("Invalid time type: %s."), $type));
	}
}
$modality = urldecode($_REQUEST['modality']);
$referdoc = "";
if (isset($_REQUEST['doclast']) && strlen($_REQUEST['doclast']))
	$referdoc .= urldecode($_REQUEST['doclast']);
if (isset($_REQUEST['docfirst']) && strlen($_REQUEST['docfirst']))
	$referdoc .= "^" . urldecode($_REQUEST['docfirst']);

$dbcon = new MyConnection();
$query = "SELECT * FROM applentity WHERE title=?";
$bindList = array($aetitle);
$result = $dbcon->preparedStmt($query, $bindList);
$row = $result->fetch(PDO::FETCH_ASSOC);
$ipaddr = $row['ipaddr'];
$hostname = $row['hostname'];
$port = $row['port'];
$tls = $row['tlsoption'];
$mytitle = $dbcon->getMyAeTitle();

$error = '';
$assoc = new Association($ipaddr, $hostname, $port, $aetitle, $mytitle, $tls);
$identifier = new WorklistFindIdentifier($patientid,
					                     $patientname,
										 $station,
										 $startdate,
										 $starttime,
										 $modality,
										 $referdoc);
$matches = $assoc->findWorklist($identifier, $error);
if (strlen($error)) {
    print '<br><font color=red>';
    print 'find() failed: error = ' . $error;
    print '</font><br>';
}
else {
	require_once "display.php";
	if (count($matches)) {
        $imported = importWorklist($matches);
		displayRemoteWorklist($aetitle, $identifier, $matches, $imported);
	} else {
		print "<br>";
        printf(pacsone_gettext("No match found by remote AE <b>%s</b>."), $aetitle);
        print "<br>";
    }
	require_once 'footer.php';
}

function insertUpdateTable(&$dbcon, &$studyuid, &$table, &$values, &$sqlfuncs, $update)
{
    $columns = array();
    $params = array();
    $bindList = array();
    if ($update) {
        $query = "update $table set ";
        foreach ($values as $column => $value) {
            $columns[] = $column;
            $params[] = "?";
            $bindList[] = $value;
        }
        foreach ($sqlfuncs as $column => $value) {
            $columns[] = $column;
            $params[] = $value;
        }
        for ($i = 0; $i < count($columns); $i++) {
            if ($i)
                $query .= ",";
            $query .= sprintf("%s=%s", $columns[$i], $params[$i]);
        }
        $query .= " where studyuid=?";
        $bindList[] = $studyuid;
    } else {
        $columns[] = "studyuid";
        $params[] = "?";
        $bindList[] = $studyuid;
        $query = "insert into $table (";
        foreach ($values as $column => $value) {
            $columns[] = $column;
            $params[] = "?";
            $bindList[] = $value;
        }
        foreach ($sqlfuncs as $column => $value) {
            $columns[] = $column;
            $params[] = $value;
        }
        for ($i = 0; $i < count($columns); $i++) {
            if ($i)
                $query .= ",";
            $query .= $columns[$i];
        }
        $query .= ") values(";
        for ($i = 0; $i < count($params); $i++) {
            if ($i)
                $query .= ",";
            $query .= $params[$i];
        }
        $query .= ")";
    }
	// execute SQL query
    if (!$dbcon->preparedStmt($query, $bindList)) {
        print "<h3><font color=red>";
        printf(pacsone_gettext("Error executing SQL query: <u>%s</u>: "), $query);
        print $dbcon->getError();
        print "</font></h3><p>\n";
    }
}

function importWorklist(&$matches)
{
    global $dbcon;
	$count = 0;
    foreach ($matches as $match) {
        // find the study instance UID key
        if (!$match->hasKey(0x0020000d)) {
            print '<br><font color=red>';
            print pacsone_gettext('Study Instance UID not found, skipping returned match...');
            print '</font><br>';
            continue;
        }
        $studyuid = trim($match->attrs[0x0020000d]['value']);
        // insert or update worklist table
        $table = "worklist";
        $exists = $dbcon->entryExists($table, "studyuid", $studyuid);
        $attrs = array (
            // tag => table column name
            0x00100010  => "patientname",
            0x00100020  => "patientid",
            0x00100030  => "birthdate",
            0x00100040  => "sex",
            0x00101010  => "age",
            0x00101020  => "size",
            0x00101030  => "weight",
            0x00080050  => "accessionnum",
            0x00321032  => "requestingphysician",
            0x00080090  => "referringphysician",
            0x00081080  => "admittingdiagnoses",
            0x001021b0  => "patienthistory",
            0x00080080  => "institution",
        );
        $values = array();
        $sqlfuncs = array();
        // fill in 'received' column for WORKLIST table
        $sqlfuncs["received"] = $dbcon->useOracle? "SYSDATE" : "NOW()";
        foreach ($attrs as $key => $name) {
            if ($match->hasKey($key)) {
                $value = trim($match->attrs[$key]['value']);
                if (strlen($value)) {
                    if ($dbcon->useOracle && !strcasecmp($name, "birthdate"))
                        $sqlfuncs[$name] = "TO_DATE('$value','YYYYMMDD')";
                    else
                        $values[$name] = $value;
                }
            }
        }
        if (count($values)) {
            insertUpdateTable($dbcon, $studyuid, $table, $values, $sqlfuncs, $exists);
        }
        // insert or update scheduled procedure step table
        $table = "scheduledps";
        $exists = $dbcon->entryExists($table, "studyuid", $studyuid);
        $attrs = array (
            0x00321070 => "contrastagent",
            0x00400001 => "aetitle",
            0x00400002 => "startdate",
            0x00400003 => "starttime",
            0x00080060 => "modality",
            0x00400006 => "performingphysician",
            0x00400007 => "description",
            0x00400009 => "id",
            0x00400010 => "station",
            0x00400011 => "location",
            0x00400012 => "premedication",
            0x00400020 => "status",
        );
        $values = array();
        $sqlfuncs = array();
        $sequence = $match->attrs[0x00400100];
        $changes = "";
        foreach ($attrs as $key => $name) {
            if ($sequence->hasKey($key)) {
                $value = $sequence->getAttr($key);
                if ($dbcon->useOracle && !strcasecmp($name, "startdate"))
                    $sqlfuncs[$name] = "TO_DATE('$value','YYYYMMDD')";
                else
                    $values[$name] = $value;
            }
        }
        if (count($values)) {
            insertUpdateTable($dbcon, $studyuid, $table, $values, $sqlfuncs, $exists);
        }
        // insert or update scheduled protocol code table
        if ($match->hasKey(0x00400008)) {
            $sequence = $match->getItem(0x00400008);
            $table = "protocolcode";
            $exists = $dbcon->entryExists($table, "studyuid", $studyuid);
            $attrs = array (
                0x00080100 => "value",
                0x00080102 => "schemedesignator",
                0x00080103 => "schemeversion",
                0x00080104 => "meaning",
            );
            $values = array();
            $sqlfuncs = array();
            foreach ($attrs as $key => $name) {
                if ($sequence->hasKey($key)) {
                    $value = $sequence->getAttr($key);
                    $values[$name] = $value;
                }
            }
            if (count($values)) {
                insertUpdateTable($dbcon, $studyuid, $table, $values, $sqlfuncs, $exists);
            }
        }
        // insert or update requested procedure table
        $table = "requestedprocedure";
        $exists = $dbcon->entryExists($table, "studyuid", $studyuid);
        $attrs = array (
            0x00321060 => "description",
            0x00401001 => "id",
            0x00401003 => "priority",
        );
        $values = array();
        $sqlfuncs = array();
        foreach ($attrs as $key => $name) {
            if ($match->hasKey($key)) {
                $value = $match->attrs[$key]['value'];
                $values[$name] = $value;
            }
        }
        if (count($values)) {
            insertUpdateTable($dbcon, $studyuid, $table, $values, $sqlfuncs, $exists);
        }
        // insert or update requested procedure code table
        if ($match->hasKey(0x00321064)) {
            $sequence = $match->attrs[0x00321064];
            $table = "procedurecode";
            $exists = $dbcon->entryExists($table, "studyuid", $studyuid);
            $attrs = array (
                0x00080100 => "value",
                0x00080102 => "schemedesignator",
                0x00080103 => "schemeversion",
                0x00080104 => "meaning",
            );
            $values = array();
            $sqlfuncs = array();
            foreach ($attrs as $key => $name) {
                if ($sequence->hasKey($key)) {
                    $value = $sequence->getAttr($key);
                    $values[$name] = $value;
                }
            }
            if (count($values)) {
                insertUpdateTable($dbcon, $studyuid, $table, $values, $sqlfuncs, $exists);
            }
        }
        // insert or update referenced study table
        if ($match->hasKey(0x00081110)) {
            $sequence = $match->attrs[0x00081110];
            $table = "referencedstudy";
            $exists = $dbcon->entryExists($table, "studyuid", $studyuid);
            $attrs = array (
                0x00081150 => "classuid",
                0x00081155 => "instanceuid",
            );
            $values = array();
            $sqlfuncs = array();
            foreach ($attrs as $key => $name) {
                if ($sequence->hasKey($key)) {
                    $value = $sequence->getAttr($key);
                    $values[$name] = $value;
                }
            }
            if (count($values)) {
                insertUpdateTable($dbcon, $studyuid, $table, $values, $sqlfuncs, $exists);
            }
        }
        // insert or update referenced patient table
        if ($match->hasKey(0x00081120)) {
            $sequence = $match->attrs[0x00081120];
            $table = "referencedpatient";
            $exists = $dbcon->entryExists($table, "studyuid", $studyuid);
            $attrs = array (
                0x00081150 => "classuid",
                0x00081155 => "instanceuid",
            );
            $values = array();
            $sqlfuncs = array();
            foreach ($attrs as $key => $name) {
                if ($sequence->hasKey($key)) {
                    $value = $sequence->getAttr($key);
                    $values[$name] = $value;
                }
            }
            if (count($values)) {
                insertUpdateTable($dbcon, $studyuid, $table, $values, $sqlfuncs, $exists);
            }
        }
        $count++;
    }
    return $count;
}

?>
