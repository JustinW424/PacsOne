<?php
//
// sendOruReport.php
//
// Module for sending ORU report to remote HL7 applications for newly received study
//
// CopyRight (c) 2013-2020 RainbowFish Software
//
if (!isset($argv))
    session_start();

require "locale.php";
include_once 'database.php';
include_once 'sharedData.php';
require_once 'utils.php';

function buildOruReport(&$dbcon, &$hl7app, &$studyUid, &$controlId)
{
    $msg = "";
    $query = "select * from patient inner join study on patient.origid=study.patientid where study.uuid=?";
    $bindList = array($studyUid);
    $patient = $dbcon->preparedStmt($query, $bindList);
    if ($patient && ($row = $patient->fetch(PDO::FETCH_ASSOC))) {
        global $PRODUCT;
        global $HL7VERSION;
        // MSH segment
        $aetitle = "";
        $urlPrefix = "";
        $config = $dbcon->query("select * from config");
        if ($config && ($cfgrow = $config->fetch(PDO::FETCH_ASSOC))) {
            $aetitle = $cfgrow["aetitle"];
            $urlPrefix = $cfgrow["externalaccessurl"];
            // append trailing '/' if necessary
            if (strlen($urlPrefix) && strcmp(substr($urlPrefix, strlen($urlPrefix)-1, 1), "/"))
                $urlPrefix .= "/";
        }
        $datetime = getDateTimeStamp();
        $msg .= "MSH|^~\\&|$PRODUCT|$aetitle|$hl7app||$datetime||ORU^R01|$controlId|P|$HL7VERSION\r\n";
        // PID segment
        $pid = $row["origid"];
        $pname = $row["lastname"];
        $first = $row["firstname"];
        if (strlen($first)) {
            $pname .= strchr($first, '^')? "" : "^";
            $pname .= $first;
        }
        if (isset($row["middlename"])) {
            $middle = $row["middlename"];
            if (strlen($middle)) {
                if (!strlen($first))
                    $pname .= "^";
                $pname .= strchr($middle, '^')? "" : "^";
                $pname .= $middle;
            }
        }
        $dob = $row["birthdate"];
        $sex = $row["sex"];
        $sex = strlen($sex)? $sex[0] : "";
        $admissionId = $row["admissionid"];
        $alt = "";
        // check if there's an Alternative Patient ID
        $subList = array($pid);
        $result = $dbcon->preparedStmt("select uuid from hl7patientid where id=?", $subList);
        if ($result && $result->rowCount()) {
            $ctlId = $result->fetchColumn();
            $subList = array($ctlId);
            $result = $dbcon->preparedStmt("select id from hl7altpid where uuid=?", $subList);
            if ($result && $result->rowCount())
                $alt = $result->fetchColumn();
        }
        $msg .= "PID||$alt|$pid||$pname||$dob|$sex||||||||||$admissionId|\r\n";
        // PV1 segment
        $referdoc = $row["referringphysician"];
        $msg .= "PV1||I|$aetitle|||||$referdoc\r\n";
        // ORC segment
        $accession = $row["accessionnum"];
        $msg .= "ORC|CN||$accession\r\n";
        // OBR segment
        $obsDateTime = $obsEndDateTime = $resultInterp = $schedDateTime = "";
        if (isset($row['studydate']) && strlen($row['studydate'])) {
            $schedDateTime = $row['studydate'];
            $query = "select * from scheduledps where studyuid=?";
            $sps = $dbcon->preparedStmt($query, $bindList);
            if ($sps && ($spsRow = $sps->fetch(PDO::FETCH_ASSOC))) {
                $date = str_replace("-", "", $row['studydate']);
                $obsDateTime = $date . str_replace(":", "", $spsRow['starttime']);
                $obsEndDateTime = $date . str_replace(":", "", $spsRow['endtime']);
                if (isset($spsRow['performingphysician']) && strlen($spsRow['performingphysician']))
                    $resultInterp = $spsRow['performingphysician'];
                // remove the SCHEDULEDPS record if orphaned
                $query = "select studyuid from worklist where studyuid=?";
                $worklist = $dbcon->preparedStmt($query, $bindList);
                if ($worklist && $worklist->rowCount() == 0)
                    $dbcon->preparedStmt("delete from scheduledps where studyuid=?", $bindList);
            }
        }
        $desc = $row["description"];
        $msg .= "OBR|||$accession|$desc|||$obsDateTime|$obsEndDateTime||||||||||||||||||||||||$resultInterp||||$schedDateTime|R\r\n";
        // OBX segment
        $url = "";
        $dateTime = "";
        if (strlen($urlPrefix)) {
            $url = $urlPrefix . "series.php?patientId=" . urlencode($pid) . "&studyId=$studyUid";
            // add optional authentication parameters
            $parsed = array();
            $dir = dirname($_SERVER['SCRIPT_FILENAME']);
            $dir = substr($dir, 0, strlen($dir) - 3);
            $file = file($dir . $aetitle . ".ini");
            foreach ($file as $line) {
                $tokens = preg_split("/[\s=]+/", $line);
                if (count($tokens) > 1)
                    $parsed[$tokens[0]] = $tokens[1];
            }
            if (count($parsed) && isset($parsed['OruReportSkipAuthentication']) &&
                !strcasecmp($parsed['OruReportSkipAuthentication'], "Yes")) {
                $embed = $url;
                $url = $urlPrefix . "risdirect.php?key=uuid&value=" . urlencode($studyUid);
                if (count($parsed) && isset($parsed['OruReportDatabase'])) {
                    $database = $parsed['OruReportDatabase'];
                    $url .= "&database=" . urlencode($database);
                }
                if (count($parsed) && isset($parsed['OruReportUsername'])) {
                    $username = $parsed['OruReportUsername'];
                    $url .= "&username=" . urlencode($username);
                }
                if (count($parsed) && isset($parsed['OruReportPassword'])) {
                    $password = $parsed['OruReportPassword'];
                    $url .= "&password=" . urlencode($password);
                }
                $url .= "&url=" . urlencode($embed);
            }
        }
        if (isset($row['studydate']) && isset($row['studytime']) &&
            strlen($row['studydate']) && strlen($row['studytime'])) {
            $delimiters = array("-", ":");
            $dateTime = str_replace($delimiters, "", $row['studydate']);
            $dateTime .= str_replace($delimiters, "", $row['studytime']);
        }
        if (strlen($url) || strlen($dateTime))
            $msg .= "OBX||RP|$studyUid^$desc||$url||||||R|||$dateTime\r\n";
    }
    return $msg;
}

// main
global $PRODUCT;
global $HL7VERSION;
if (isset($argv) && count($argv)) {
    $database = $argv[1];
    $username = $argv[2];
    $password = $argv[3];
    $aetitle = $argv[4];
    $hl7app = $argv[5];
    $studyUid = $argv[6];
    $hostname = getDatabaseHost($aetitle);
    $dbcon = new MyDatabase($hostname, $database, $username, $password, $aetitle);
} else {
    $dbcon = new MyConnection();
    $username = $dbcon->username;
    $hl7app = $_GET["hl7app"];
    $studyUid = $_GET["uid"];
}
$controlId = MakeHL7MsgControlId();
$msg = buildOruReport($dbcon, $hl7app, $studyUid, $controlId);
if (strlen($msg)) {
    // insert the ORU message
    $query = "INSERT INTO hl7message (type,controlid,processingid,versionid,received,data,status) values('ORU^R01',?,'P','$HL7VERSION',";
    $query .= $dbcon->useOracle? "SYSDATE" : "NOW()";
    $query .= ",?,'F')";
    $bindList = array($controlId, $msg);
    if (!$dbcon->preparedStmt($query, $bindList)) {
        die("Error running SQL query: [$query]");
    }
    // schedule a HL7 for sending the ORU report
    $query = "INSERT INTO hl7job (username,appname,type,uuid,submittime,status) values(";
    $query .= "?,?,'Forward',?,";
    $query .= $dbcon->useOracle? "SYSDATE" : "NOW()";
    $query .= ",'submitted')";
    $bindList = array($username, $hl7app, $controlId);
    if ($dbcon->preparedStmt($query, $bindList)) {
        print "Submitted HL7 for sending ORU report to <u>$hl7app</u> for Dicom study: <u>$studyUid</u><br>";
    } else {
        die("Error running SQL query: [$query], error = " . $dbcon->getError());
    }
} else {
    print "<h2><font color=red>";
    print "Failed to build HL7 ORU Report message for Dicom study: <u>$studyUid</u>";
    print "</font></h2>";
}

?>
