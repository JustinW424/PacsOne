<?php
//
// importWorklist.php
//
// Module for importing uploaded Modality Worklist text files
//
// CopyRight (c) 2007-2021 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once 'database.php';
include_once 'upload.php';

function WorklistFromFile(&$dbcon, &$file, &$error)
{
    global $CUSTOMIZE_PATIENT_ID;
    global $CUSTOMIZE_PATIENT_NAME;
    global $CUSTOMIZE_PATIENT_SEX;
    global $CUSTOMIZE_PATIENT_AGE;
    global $CUSTOMIZE_PATIENT_SIZE;
    global $CUSTOMIZE_PATIENT_WEIGHT;
    global $CUSTOMIZE_PATIENT_DOB;
    global $CUSTOMIZE_REFERRING_DOC;
    global $CUSTOMIZE_REQUESTING_DOC;
    global $CUSTOMIZE_PERFORMING_DOC;
    $count = 0;
    if (version_compare(PHP_VERSION,"5.3.0","<"))
    	$tokens = @parse_ini_file($file, true);
    else
    	$tokens = @parse_ini_file($file, true, INI_SCANNER_RAW);
    if (!$tokens)
        die("Failed to parse $file");
    if (!$tokens || !count($tokens))
        return 0;
    $eurodate = $dbcon->isEuropeanDateFormat();
    foreach ($tokens as $key => $worklist)
    {
        $worklist = array_change_key_case($worklist, CASE_UPPER);
        // check this is a request to cancel a scheduled worklist
        $cancel = "CANCEL";
        if (array_key_exists($cancel, $worklist)) {
            $entry = array();
            require_once "utils.php";
            $entry[] = $worklist[$cancel];
            deleteWorklists($entry);
            return 0;
        }
        // check if all required keys are present
        $required = array(
            $CUSTOMIZE_PATIENT_NAME,
            $CUSTOMIZE_PATIENT_ID,
            "Accession Number",
            "Scheduled AE Station",
            "Scheduled Start Date",
            "Scheduled Start Time",
            "Requested Procedure ID",
        );
        foreach ($required as $key) {
            $key = strtoupper($key);
            if (!array_key_exists($key, $worklist) || !strlen($worklist[$key])) {
                if (strcasecmp($key, "Accession Number")) {
                    $error = sprintf(pacsone_gettext("Required data field <b>%s</b> missing"), $key);
                    return 0;
                } else {
                    // fill in Accession Number if not present
                    $accession = $dbcon->isEuropeanDateFormat()? date("dmYHi") : date("YmdHi");
                    $accession = sprintf("%s%04.4d", $accession, $count+1);
                    $worklist[$key] = $accession;
                }
            }
        }
        $worklistTbl = array(
            "patientname"           => $CUSTOMIZE_PATIENT_NAME,
            "patientid"             => $CUSTOMIZE_PATIENT_ID,
            "birthdate"             => $CUSTOMIZE_PATIENT_DOB,
            "sex"                   => $CUSTOMIZE_PATIENT_SEX,
            "accessionnum"          => "Accession Number",
            "referringphysician"    => $CUSTOMIZE_REFERRING_DOC,
            "requestingphysician"   => $CUSTOMIZE_REQUESTING_DOC,
            "pregnancystat"         => "Pregnancy Status",
            "lastmenstrual"         => "Last Menstrual Date",
            "admittingdiagnoses"    => "Admitting Diagnoses Description",
            "patienthistory"        => "Additional Patient History",
            "institution"           => "Institution Name",
            "specharset"            => "Specific Character Set",
            "age"                   => $CUSTOMIZE_PATIENT_AGE,
            "size"                  => $CUSTOMIZE_PATIENT_SIZE,
            "weight"                => $CUSTOMIZE_PATIENT_WEIGHT,
            // veterinary specific infomration
            "respperson"            => "Responsible Person",
            "resppersonrole"        => "Responsible Person Role",
            "speciesdescr"          => "Species Description",
            "breeddescr"            => "Breed Description",
        );
        $reqprocTbl = array(
            "id"                    => "Requested Procedure ID",
            "description"           => "Requested Procedure Description",
            "priority"              => "Requested Procedure Priority",
        );
        $schedpsTbl = array(
            "aetitle"               => "Scheduled AE Station",
            "modality"              => "Modality",
            "startdate"             => "Scheduled Start Date",
            "starttime"             => "Scheduled Start Time",
            "enddate"               => "Scheduled End Date",
            "endtime"               => "Scheduled End Time",
            "performingphysician"   => $CUSTOMIZE_PERFORMING_DOC,
            "id"                    => "Scheduled Procedure ID",
            "description"           => "Scheduled Procedure Description",
            "location"              => "Scheduled Procedure Location",
            "premedication"         => "Scheduled Procedure Pre-Medication",
            "contrastagent"         => "Scheduled Procedure Contrast Agent",
        );
        $procodeTbl = array(
            "value"                 => "Procedure Code Value",
            "meaning"               => "Procedure Code Meaning",
            "schemedesignator"      => "Procedure Code Scheme",
            "schemeversion"         => "Procedure Code Scheme Version",
        );
        $protocodeTbl = array(
            "value"                 => "Protocol Code Value",
            "meaning"               => "Protocol Code Meaning",
            "schemedesignator"      => "Protocol Code Scheme",
            "schemeversion"         => "Protocol Code Scheme Version",
        );
        $allTables = array(
            "requestedprocedure"    => $reqprocTbl,
            "scheduledps"           => $schedpsTbl,
            "procedurecode"         => $procodeTbl,
            "protocolcode"          => $protocodeTbl,
            "worklist"              => $worklistTbl,
        );
        if (array_key_exists(strtoupper("Study Instance UID"), $worklist)) {
            $uid = $worklist[strtoupper("Study Instance UID")];
        } else {
            $uid = MakeStudyUid();
        }
        // check if this is an update or new record
        $update = 0;
        $query = "select * from worklist where studyuid=?";
        $bindList = array($uid);
        $result = $dbcon->preparedStmt($query, $bindList);
        if ($result && $result->rowCount())
            $update = 1;
        foreach ($allTables as $name => $table) {
            $empty = true;
            $columns = array();
            $values = array();
            $bindList = array();
            foreach ($table as $coln => $key) {
                if (isset($worklist[ strtoupper($key) ])) {
                    $value = $worklist[ strtoupper($key) ];
                    if (strlen($value)) {
                        // valid user input
                        if (strstr($value, "<") || strstr($value, "</")) {
                            $error = pacsone_gettext("Invalid character in input data");
                            return 0;
                        }
                        $columns[] = $coln;
                        $token = "?";
                        if (!strcasecmp($coln, "startdate") || !strcasecmp($coln, "enddate") ||
                            !strcasecmp($coln, "birthdate")) {
                            if ($eurodate)
                                $value = reverseDate(str_replace(".", "-",$value));
                            if ($dbcon->useOracle)
                                $token = sprintf("TO_DATE(?,'%s')", strstr($value, "-")? "YYYY-MM-DD" : "YYYYMMDD");
                        }
                        $values[] = $token;
                        $bindList[] = $value;
                        $empty = false;
                    }
                }
            }
            if ($empty)
                continue;
            if ($update) {
                $query = "update $name set ";
                for ($i = 0; $i < count($columns); $i++) {
                    if ($i)
                        $query .= ",";
                    $query .= sprintf("%s=%s", $columns[$i], $values[$i]);
                }
                $query .= " where studyuid=?";
                $bindList[] = $uid;
            } else {
                $columns[] = "studyuid";
                $values[] = "?";
                $bindList[] = $uid;
                $query = "insert into $name (";
                if (strcasecmp($name, "worklist") == 0) {
                    $columns[] = "received";
                    $values[] = $dbcon->useOracle? "SYSDATE" : "NOW()";
                }
                for ($i = 0; $i < count($columns); $i++) {
                    if ($i)
                        $query .= ",";
                    $query .= $columns[$i];
                }
                $query .= ") values(";
                for ($i = 0; $i < count($values); $i++) {
                    if ($i)
                        $query .= ",";
                    $query .= $values[$i];
                }
                $query .= ")";
            }
            if ($dbcon->preparedStmt($query, $bindList)) {
                if (strcasecmp($name, "worklist") == 0)
                    $count++;
            } else {
                $error .= pacsone_gettext("Error running query: ");
                $error .= $query;
                $error .= "<p>" . $dbcon->getError();
                break;
            }
        }
    }
    return $count;
}

// main
global $PRODUCT;
if (isset($argv)) {
    require_once "utils.php";
    $database = $argv[1];
    $username = $argv[2];
    $password = $argv[3];
    $aetitle = $argv[4];
    $scandir = $argv[5];
    $hostname = getDatabaseHost($aetitle);
    $dbcon = new MyDatabase($hostname, $database, $username, $password, $aetitle);
} else {
    $dbcon = new MyConnection();
    $username = $dbcon->username;
}
if (isset($_POST['action']))
   	$action = $_POST['actionvalue'];
$result = "";
if (isset($action) && strcasecmp($action, "Import Worklist") == 0) {
    $uploaded = $_FILES['uploadfile'];
    $origname = $uploaded['name'];
    $error = $uploaded['error'];
    if ($error) {
        print "<h2><font color=red>";
        printf(pacsone_gettext("Error uploading file <b>%s</b>: %s"), $origname, getUploadError($error));
        print "</font></h2>";
        exit();
    }
    $uploaded['destfile'] = $uploaded['tmp_name'];
    $worklistfiles = array();
    $worklistfiles[] = $uploaded;
    $imported = 0;
    foreach ($worklistfiles as $att) {
        $destfile = $att['destfile'];
        $size = filesize($destfile);
        if (!file_exists($destfile) || !$size) {
            $result = sprintf(pacsone_gettext("Invalid Worklist Text File: %s, size = %d bytes"), $destfile, $size);
            break;
        } else {
            $records = WorklistFromFile($dbcon, $destfile, $result);
            if ($records)
                $imported += $records;
            // log activity to system journal
            $dbcon->logJournal($username, "Import", "_Worklist", $destfile);
            // remove uploaded worklist text file
            unlink($destfile);
        }
    }
} else if (isset($scandir)) {
    // change to Unix-style path
    $scandir = str_replace("\\", "/", $scandir);
    // append '/' at the end if not so already
    if (strcmp(substr($scandir, strlen($scandir)-1, 1), "/"))
        $scandir .= "/";
    // scan folder for worklist files
    if ($dirh = opendir($scandir)) {
        $files = 0;
        $imported = 0;
        while (false != ($file = readdir($dirh))) {
            $file = $scandir . $file;
            if (strcasecmp(filetype($file), "dir")) {
                printf(pacsone_gettext("Scanning worklist records from file: %s on %s"), $file, date("r"));
                $files++;
                $records = WorklistFromFile($dbcon, $file, $result);
                if ($records) {
                    $imported += $records;
                    // log activity to system journal
                    $dbcon->logJournal($username, "Import", "_Worklist", $file);
                    // remove scanned worklist text file
                    if (!unlink($file)) {
                        print "<h2><font color=red>";
                        printf(pacsone_gettext("Failed to remove worklist file: %s after import"), $file);
                        print "</font></h2>";
                    }
                }
                printf(pacsone_gettext("%d records imported from file: %s on %s"), $records, $file, date("r"));
            }
        }
        closedir($dirh);
        $result = sprintf(pacsone_gettext("%d worklist records imported from %d files"), $imported, $files);
    } else {
        $result = sprintf(pacsone_gettext("Failed to access directory: %s"), $scandir);
    }
    print $result;
    exit();
}
print "<html>\n";
print "<head><title>$PRODUCT - ";
print pacsone_gettext("Import Modality Worklist");
print "</title></head>\n";
print "<body>\n";
require_once 'header.php';

if (empty($result) && isset($imported)) {   // success
    print "<p>";
    printf(pacsone_gettext("<a href='worklist.php'>%d worklist records</a> imported."), $imported);
    print "<p>";
    $url = "tools.php?page=" . urlencode(pacsone_gettext("Import Worklist"));
    print "<a href='$url'>Back</a><br>";
}
else {                  // error
    print "<h3><font color=red>";
    print $result;
    print "</font></h3>";
    if (isset($_SESSION['worklistfiles'])) {
        $worklistfiles = $_SESSION['worklistfiles'];
        foreach ($worklistfiles as $att) {
            if (file_exists($att['destfile']))
                unlink($att['destfile']);
        }
    }
}
if (isset($_SESSION['worklistfiles']))
    unset($_SESSION['worklistfiles']);

require_once 'footer.php';
print "</body>\n";
print "</html>\n";

?>
