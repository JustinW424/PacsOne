<?php
//
// dicomweb.php
//
// Implementation for DicomWeb related classes
//
// CopyRight (c) 2017-2021 RainbowFish Software
//
require_once 'classroot.php';
require_once 'constants.php';
include_once "dicom.php";

// QIDO-RS Search Keys
//
// format: key => database table column
//
$QIDO_PATIENT_KEYS = array(
    "PATIENTNAME"                           => "",
    "00100010"                              => "",
    "PATIENTID"                             => "origid",
    "00100020"                              => "origid",
);
$QIDO_STUDY_KEYS = array(
    "STUDYDATE"                             => "studydate",
    "00080020"                              => "studydate",
    "STUDYTIME"                             => "studytime",
    "00080030"                              => "studytime",
    "ACCESSIONNUMBER"                       => "accessionnum",
    "00080050"                              => "accessionnum",
    "MODALITIESINSTUDY"                     => "modalities",
    "00080061"                              => "modalities",
    "REFERRINGPHYSICIANNAME"                => "referringphysicia",
    "00080090"                              => "referringphysicia",
    "STUDYINSTANCEUID"                      => "uuid",
    "0020000D"                              => "uuid",
    "STUDYID"                               => "id",
    "00200010"                              => "id",
    "STUDYDESCRIPTION"                      => "description",
    "00081030"                              => "description",
);
$QIDO_SERIES_KEYS = array(
    "MODALITY"                              => "modality",
    "00080060"                              => "modality",
    "SERIESINSTANCEUID"                     => "uuid",
    "0020000E"                              => "uuid",
    "SERIESNUMBER"                          => "seriesnumber",
    "00200011"                              => "seriesnumber",
    "00080021"                              => "seriesdate",
    "00080031"                              => "seriestime",
    /*
    "performedprocedurestepstartdate"       => 0x00400244,
    "performedprocedurestepstarttime"       => 0x00400245,
     */
);
$QIDO_INSTANCE_KEYS = array(
    "SOPCLASSUID"                           => "sopclass",
    "00080016"                              => "sopclass",
    "SOPINSTANCEUID"                        => "uuid",
    "00080018"                              => "uuid",
    "INSTANCENUMBER"                        => "instance",
    "00200013"                              => "instance",
);
// supported QIDO-RS Include Fields
//
// format:
//
// key => array(vr, column, keyword)
//
// key: Dicom attribute
// vr: Dicom value representation (VR)
// column: corresponding database table column to fetch the value
// keyword: attribute name
//
$QIDO_STUDY_FIELDS = array(
    "00080020"                              => array("DA", "studydate", "Study Date"),
    "00080030"                              => array("TM", "studytime", "Study Time"),
    "00080050"                              => array("SH", "accessionnum", "Accession Number"),
    "00080090"                              => array("PN", "referringphysician", "Referring Physician's Name"),
    "00100010"                              => array("PN", "", "Patient Name"),
    "00100020"                              => array("LO", "patientid", "Patient ID"),
    "00100030"                              => array("DA", "birthdate", "Patient's Birth Date"),
    "00100040"                              => array("CS", "sex", "Patient's Sex"),
    "0020000D"                              => array("UI", "uuid", "Study Instance UID"),
    "00200010"                              => array("SH", "id", "Study ID"),
    "00081030"                              => array("LO", "description", "Study Description"),
);
$QIDO_SERIES_FIELDS = array(
    "00080060"                              => array("CS", "modality", "Modality"),
    "0008103E"                              => array("LO", "description", "Series Description"),
    "00080021"                              => array("DA", "seriesdate", "Series Date"),
    "00080031"                              => array("TM", "seriestime", "Series Time"),
    "00180015"                              => array("CS", "bodypart", "Body Part Examined"),
    "0020000E"                              => array("UI", "uuid", "Series Instance UID"),
    "00200011"                              => array("IS", "seriesnumber", "Series Number"),
);
$QIDO_INSTANCE_FIELDS = array(
    "00080016"                              => array("UI", "sopclass", "SOP Class UID"),
    "00080018"                              => array("UI", "uuid", "SOP Insyance UID"),
    "00200013"                              => array("IS", "instance", "Instance Number"),
    "00080012"                              => array("DA", "instancedate", "Instance Creation Date"),
    "00080013"                              => array("TM", "instancetime", "Instance Creation Time"),
    "00280002"                              => array("US", "samplesperpixel", "Samples Per Pixel"),
    "00280004"                              => array("CS", "photometric", "Photometric Interpretation"),
    "00280008"                              => array("IS", "numframes", "Number of Frames"),
    "00280010"                              => array("US", "numrows", "Rows"),
    "00280011"                              => array("US", "numcolumns", "Columns"),
    "00280100"                              => array("US", "bitsallocated", "Bits Allocated"),
    "00280101"                              => array("US", "bitsstored", "Bits Stored"),
    "00280103"                              => array("US", "pixelrepresentation", "Pixel Representation"),
);

class DicomWeb extends PacsOneRoot {
    var $dbcon;
    var $rows;
    function __construct(&$dbcon) {
        $this->dbcon = $dbcon;
        $this->rows = array();
    }
    function __destruct() { }
    function noMatchFound() {
        return (count($this->rows) == 0);
    }
    function toXmlPersonName($alpha, $ideo = "", $phonetic = "") {
        $xml = "<Alphabetic>";
        $tokens = explode("^", $alpha);
        $xml .= "<FamilyName>" . (isset($tokens[0])? $tokens[0] : "") . "</FamilyName>";
        $xml .= "<GivenName>" . (isset($tokens[1])? $tokens[1] : "") . "</GivenName>";
        $xml .= "<MiddleName>" . (isset($tokens[2])? $tokens[2] : "") . "</MiddleName>";
        $xml .= "<NamePrefix>" . (isset($tokens[3])? $tokens[3] : "") . "</NamePrefix>";
        $xml .= "<NameSuffix>" . (isset($tokens[4])? $tokens[4] : "") . "</NameSuffix>";
        $xml .= "</Alphabetic>";
        if (strlen($ideo)) {
            $xml .= "<Ideographic>";
            $tokens = explode("^", $ideo);
            $xml .= "<FamilyName>" . (isset($tokens[0])? $tokens[0] : "") . "</FamilyName>";
            $xml .= "<GivenName>" . (isset($tokens[1])? $tokens[1] : "") . "</GivenName>";
            $xml .= "<MiddleName>" . (isset($tokens[2])? $tokens[2] : "") . "</MiddleName>";
            $xml .= "<NamePrefix>" . (isset($tokens[3])? $tokens[3] : "") . "</NamePrefix>";
            $xml .= "<NameSuffix>" . (isset($tokens[4])? $tokens[4] : "") . "</NameSuffix>";
            $xml .= "</Ideographic>";
        }
        if (strlen($phonetic)) {
            $xml .= "<Phonetic>";
            $tokens = explode("^", $phonetic);
            $xml .= "<FamilyName>" . (isset($tokens[0])? $tokens[0] : "") . "</FamilyName>";
            $xml .= "<GivenName>" . (isset($tokens[1])? $tokens[1] : "") . "</GivenName>";
            $xml .= "<MiddleName>" . (isset($tokens[2])? $tokens[2] : "") . "</MiddleName>";
            $xml .= "<NamePrefix>" . (isset($tokens[3])? $tokens[3] : "") . "</NamePrefix>";
            $xml .= "<NameSuffix>" . (isset($tokens[4])? $tokens[4] : "") . "</NameSuffix>";
            $xml .= "</Phonetic>";
        }
        return $xml;
    }
}

class DicomWebResultAttr extends DicomWeb {
    var $fields;
    function __construct(&$dbcon, &$fields) {
        // call base class constructor
        parent::__construct($dbcon);
        $this->fields = $fields;
    }
    function __destruct() { }
    // convert database values to Dicom VR format
    function toDicomVr(&$value, $vr) {
        switch (strtoupper($vr)) {
        case "DA":
            $ret = str_replace("-", "", $value);
            break;
        case "IS":
        case "US":
            $ret = intval($value);
            break;
        default:
            $ret = $value;
            break;
        }
        return $ret;
    }
    // convert Dicom Date Range Matching values into SQL query
    function dateRangeQuery($column, $value) {
        $query = "";
        if (substr($value, 0, 1) === "-") {
            $to = substr($value, 1);
            $query = sprintf("(%s <= %s)", $column, $to);
        } else if (substr($value, -1) === "-") {
            $from = substr($value, 0, -1);
            $query = sprintf("(%s >= '%s')", $column, $from);
        } else {
            $tokens = explode("-", $value);
            if (count($tokens) >= 2) {
                $from = $tokens[0];
                $to = $tokens[1];
                /*
                if ($this->dbcon->isEuropeanDateFormat()) {
                    $from = reverseDate($from);
                    $to = reverseDate($to);
                }
                 */
                $query = sprintf("(%s >= '%s' AND %s <= %s)", $column, $from, $column, $to);
            }
        }
        return $query;
    }
}

class DicomWebInstanceResult extends DicomWebResultAttr {
    // whether or not to return the old WADO-style URL via (0008,1190) to retrieve this instance
    var $wadoUri = false;
    // required attributes to include when sending match results
    var $reqAttrs = array(
        "00080016",
        "00080018",
        "00200013",
        "00280010",
        "00280011",
        "00280100",
        "00280008",
    );
    function __construct(&$dbcon, &$path, $queries, &$fields) {
        // call base class constructor
        parent::__construct($dbcon, $fields);
        // find matching instances
        global $QIDO_INSTANCE_KEYS;
        if (stripos($path, "wadoUri"))
            $this->wadoUri = true;
        $studyUid = "";
        if (stripos($path, "studies")) {
            $tokens = explode("/", $path);
            for ($i = 0; $i < count($tokens); $i++) {
                if (strcasecmp($tokens[$i], "studies") == 0) {
                    if ($i + 1 < count($tokens))
                        $studyUid = $tokens[$i+1];
                    break;
                }
            }
        }
        $seriesList = array();
        if (stripos($path, "series")) {
            $tokens = explode("/", $path);
            for ($i = 0; $i < count($tokens); $i++) {
                if (strcasecmp($tokens[$i], "series") == 0) {
                    if (isset($tokens[$i+1]))
                        $seriesList[] = $tokens[$i+1];
                    break;
                }
            }
        } else if (stripos($path, "studies")) {
            if (strlen($studyUid)) {
                $query = "select uuid from series where studyuid=?";
                $bindList = array($studyUid);
                $series = $this->dbcon->preparedStmt($query, $bindList);
                while ($series && ($seriesRow = $series->fetch(PDO::FETCH_NUM)))
                    $seriesList[] = $seriesRow[0];
            }
        }
        $filters = "";
        $bindList = array();
        foreach ($queries as $key => $value) {
            if (array_key_exists(strtoupper($key), $QIDO_INSTANCE_KEYS)) {
                if (strlen($filters))
                    $filters .= " AND ";
                $column = $QIDO_INSTANCE_KEYS[strtoupper($key)];
                $filters .= "$column=?";
                $bindList[] = $value;
            }
        }
        foreach ($seriesList as $uid) {
            if (strlen($filters))
                $filters .= " AND ";
            $filters .= "seriesuid=?";
            $bindList[] = $uid;
        }
        $sql = "select * from image";
        if (strlen($filters))
            $sql .= " where $filters";
        if (count($bindList))
            $result = $this->dbcon->preparedStmt($sql, $bindList);
        else
            $result = $this->dbcon->query($sql);
        while ($result && ($row = $result->fetch(PDO::FETCH_ASSOC))) {
            $row["studyuid"] = $studyUid;
            $this->rows[] = $row;
        }
    }
    function __destruct() {
        // call base class destructor
        parent::__destruct();
    }
    function sendJson() {
        global $QIDO_INSTANCE_FIELDS;
        header("Content-Type: application/dicom+json");
        $json = array();
        $keys = array_merge($this->reqAttrs, $this->fields);
        foreach ($this->rows as $instance) {
            $entry = array();
            // add required attributes and include fields
            foreach ($keys as $key) {
                if (isset($QIDO_INSTANCE_FIELDS[$key]) &&
                    !isset($entry[$key])) {
                    $vr = $QIDO_INSTANCE_FIELDS[$key][0];
                    $column = $QIDO_INSTANCE_FIELDS[$key][1];
                    $value = array();
                    $data = isset($instance[$column])? $instance[$column] : "";
                    if (strcasecmp($vr, "PN")) {
                        $value[] = $this->toDicomVr($data, $vr);
                    } else {
                        $pn = array("Alphabetic" => $data);
                        $value[] = $pn;
                    }
                    $entry[$key] = array(
                        "vr"        => $vr,
                        "Value"     => $value,
                    );
                }
            }
            // Instance Availability
            $entry["00080056"] = array(
                "vr"        => "CS",
                "Value"     => array("ONLINE"),
            );
            // Retrieve URL
            $url = $this->wadoUri? $this->dbcon->getWADOUrl($instance["uuid"], $instance["seriesuid"]) : $this->dbcon->getWadoRsInstanceUrl($instance["studyuid"], $instance["seriesuid"], $instance["uuid"]);
            $entry["00081190"] = array(
                "vr"        => "UR",
                "Value"     => array($url),
            );
            // add this entry
            $json[] = $entry;
        }
        $encoded = json_encode($json);
        if ($encoded == false)
            die("DicomWebInstanceResult::json_encode() failed, error = " . json_last_error_msg());
        echo $encoded;
    }
    function sendXml() {
        global $QIDO_INSTANCE_FIELDS;
        $keys = array_merge($this->reqAttrs, $this->fields);
        header("Content-Type: multipart/related; type=\"application/dicom+xml\"");
        foreach ($this->rows as $instance) {
            header("Content-Type: application/dicom+xml");
            echo "<?xml version=\"1.0\" encoding=\"UTF-8\" xml:space=\"preserve\" ?>";
            echo "<NativeDicomModel>";
            // add required attributes and include fields
            foreach ($this->reqAttrs as $key) {
                if (isset($QIDO_INSTANCE_FIELDS[$key]) &&
                    !isset($entry[$key])) {
                    $vr = $QIDO_INSTANCE_FIELDS[$key][0];
                    $column = $QIDO_INSTANCE_FIELDS[$key][1];
                    $keyword = $QIDO_INSTANCE_FIELDS[$key][2];
                    $data = isset($instance[$column])? $instance[$column] : "";
                    $entry = "<DicomAttribute tag=\"$key\" vr=\"$vr\" keyword=\"$keyword\">";
                    if (strcasecmp($vr, "PN"))
                        $entry .= "<Value number=\"1\">" . $this->toDicomVr($data, $vr) . "</Value>";
                    else
                        $entry .= "<PersonName number=\"1\">" . $this->toXmlPersonName($data) . "</PersonName>";
                    $entry .= "</DicomAttribute>";
                    echo $entry;
                }
            }
            // Instance Availability
            $entry = "<DicomAttribute tag=\"00080056\" vr=\"CS\" keyword=\"Instance Availability\">";
            $entry .= "<Value number=\"1\">ONLINE</Value>";
            $entry .= "</DicomAttribute>";
            echo $entry;
            // Retrieve URL
            $url = $this->wadoUri? $this->dbcon->getWADOUrl($instance["uuid"], $instance["seriesuid"]) : $this->dbcon->getWadoRsInstanceUrl($instance["studyuid"], $instance["seriesuid"], $instance["uuid"]);
            $entry = "<DicomAttribute tag=\"00081190\" vr=\"UR\" keyword=\"Retrieve URL\">";
            $entry .= "<Value number=\"1\">" . $url . "</Value>";
            $entry .= "</DicomAttribute>";
            echo $entry;
            echo "</NativeDicomModel>";
        }
    }
}

class DicomWebSeriesResult extends DicomWebResultAttr {
    // whether or not to return the Retrieve URL via (0008,1190) to retrieve this series
    var $retrieveUrl = true;
    // required attributes to include when sending match results
    var $reqAttrs = array(
        "00080060",
        "0008103E",
        "0020000E",
        "00200011",
    );
    function __construct(&$dbcon, &$path, $queries, &$fields) {
        // call base class constructor
        parent::__construct($dbcon, $fields);
        if (stripos($path, "donotretrieve"))
            $retrieveUrl = false;
        // find matching series
        global $QIDO_SERIES_KEYS;
        $studyUid = "";
        if (stripos($path, "studies")) {
            $tokens = explode("/", $path);
            for ($i = 0; $i < count($tokens); $i++) {
                if (strcasecmp($tokens[$i], "studies") == 0) {
                    if ($i + 1 < count($tokens))
                        $studyUid = $tokens[$i+1];
                    break;
                }
            }
        }
        $filters = "";
        $bindList = array();
        foreach ($queries as $key => $value) {
            if (array_key_exists(strtoupper($key), $QIDO_SERIES_KEYS)) {
                if (strlen($filters))
                    $filters .= " AND ";
                $column = $QIDO_SERIES_KEYS[strtoupper($key)];
                $after = "";
                $default = "$column" . preparedStmtWildcard($value, $after);
                $toBind = true;
                if (!strcasecmp($key, "00080021") ||
                    !strcasecmp($key, "SeriesDate")) {
                    // check if date range is specified
                    if (strpos($value, "-")) {
                        $value = trim($value);
                        $default = $this->dateRangeQuery($column, $value);
                        $toBind = false;
                    }
                }
                $filters .= $default;
                if ($toBind)
                    $bindList[] = $after;
            }
        }
        if (strlen($studyUid)) {
            if (strlen($filters))
                $filters .= " AND ";
            $filters .= "studyuid=?";
            $bindList[] = $studyUid;
        }
        $sql = "select * from series";
        if (strlen($filters))
            $sql .= " where $filters";
        if (count($bindList))
            $result = $this->dbcon->preparedStmt($sql, $bindList);
        else
            $result = $this->dbcon->query($sql);
        while ($result && ($row = $result->fetch(PDO::FETCH_ASSOC)))
            $this->rows[] = $row;
    }
    function __destruct() {
        // call base class destructor
        parent::__destruct();
    }
    function sendJson() {
        global $QIDO_SERIES_FIELDS;
        header("Content-Type: application/dicom+json");
        $json = array();
        $keys = array_merge($this->reqAttrs, $this->fields);
        foreach ($this->rows as $series) {
            $entry = array();
            // add required attributes and include fields
            foreach ($keys as $field) {
                if (isset($QIDO_SERIES_FIELDS[$field]) &&
                    !isset($entry[$field])) {
                    $vr = $QIDO_SERIES_FIELDS[$field][0];
                    $column = $QIDO_SERIES_FIELDS[$field][1];
                    $value = array();
                    $data = isset($series[$column])? $series[$column] : "";
                    if (strcasecmp($vr, "PN")) {
                        $value[] = $this->toDicomVr($data, $vr);
                    } else {
                        $pn = array("Alphabetic" => $data);
                        $value[] = $pn;
                    }
                    $entry[$field] = array(
                        "vr"        => $vr,
                        "Value"     => $value,
                    );
                }
            }
            // Number of Series Related Instances
            $uid = $series["uuid"];
            $instances = $this->dbcon->query("select count(*) from image where seriesuid='$uid'");
            if ($instances && ($instRow = $instances->fetch(PDO::FETCH_NUM)))
                $instances = (int)$instRow[0];
            else
                $instances = 0;
            $entry["00201209"] = array(
                "vr"    => "IS",
                "Value"     => array($instances),
            );
            // Retrieve URL
            if ($this->retrieveUrl) {
                $entry["00081190"] = array(
                    "vr"    => "UR",
                    "Value"     => array($this->dbcon->getWadoRsSeriesUrl($series["studyuid"], $series["uuid"])),
                );
            }
            // add this entry
            $json[] = $entry;
        }
        $encoded = json_encode($json);
        if ($encoded == false)
            die("DicomWebSeriesResult::json_encode() failed, error = " . json_last_error_msg());
        echo $encoded;
    }
    function sendXml() {
        global $QIDO_SERIES_FIELDS;
        $keys = array_merge($this->reqAttrs, $this->fields);
        header("Content-Type: multipart/related; type=\"application/dicom+xml\"");
        foreach ($this->rows as $series) {
            header("Content-Type: application/dicom+xml");
            echo "<?xml version=\"1.0\" encoding=\"UTF-8\" xml:space=\"preserve\" ?>";
            echo "<NativeDicomModel>";
            // add required attributes and include fields
            foreach ($keys as $key) {
                if (isset($QIDO_SERIES_FIELDS[$key]) &&
                    !isset($entry[$key])) {
                    $vr = $QIDO_SERIES_FIELDS[$key][0];
                    $column = $QIDO_SERIES_FIELDS[$key][1];
                    $keyword = $QIDO_SERIES_FIELDS[$key][2];
                    $data = isset($series[$column])? $series[$column] : "";
                    $entry = "<DicomAttribute tag=\"$key\" vr=\"$vr\" keyword=\"$keyword\">";
                    if (strcasecmp($vr, "PN")) {
                        $value = $this->toDicomVr($data, $vr);
                        $entry .= "<Value number=\"1\">" . $value . "</Value>";
                    } else {
                        $entry .= "<PersonName number=\"1\">" . $this->toXmlPersonName($data) . "</PersonName>";
                    }
                    $entry .= "</DicomAttribute>";
                    echo $entry;
                }
            }
            // Number of Series Related Instances
            $uid = $series["uuid"];
            $instances = $this->dbcon->query("select count(*) from image where seriesuid='$uid'");
            if ($instances && ($instRow = $instances->fetch(PDO::FETCH_NUM)))
                $instances = (int)$instRow[0];
            else
                $instances = 0;
            $entry = "<DicomAttribute tag=\"00201209\" vr=\"IS\" keyword=\"Number of Series Related Instances\">";
            $entry .= "<Value number=\"1\">$instances</Value>";
            $entry .= "</DicomAttribute>";
            echo $entry;
            // Retrieve URL
            if ($this->retrieveUrl) {
                $entry = "<DicomAttribute tag=\"00081190\" vr=\"UR\" keyword=\"Retrieve URL\">";
                $entry .= "<Value number=\"1\">" . $this->dbcon->getWadoRsSeriesUrl($series["studyuid"], $series["uuid"]) . "</Value>";
                $entry .= "</DicomAttribute>";
                echo $entry;
            }
            echo "</NativeDicomModel>";
        }
    }
}

class DicomWebStudyResult extends DicomWebResultAttr {
    // required attributes to include when sending match results
    var $reqAttrs = array(
        "00080020",
        "00080030",
        "00080050",
        "00080056",
        "00080061",
        "00080090",
        "00100010",
        "00100020",
        "00100030",
        "00100040",
        "00200010",
        "0020000D",
    );
    function __construct(&$dbcon, &$path, $queries, &$fields) {
        // call base class constructor
        parent::__construct($dbcon, $fields);
        // find matching studies
        global $QIDO_PATIENT_KEYS;
        global $QIDO_STUDY_KEYS;
        $sql = "select distinct * from patient inner join study on patient.origid=study.patientid";
        $filters = "";
        $after = "";
        $bindList = array();
        foreach ($queries as $key => $value) {
            $value = urldecode($value);
            // patient level keys
            if (array_key_exists(strtoupper($key), $QIDO_PATIENT_KEYS)) {
                if (strlen($filters))
                    $filters .= " AND ";
                if (!strcasecmp($key, "00100010") ||
                    !strcasecmp($key, "PatientName")) {
                    // special processing for Patient Name search
                    $tokens = explode("^", $value);
                    if (isset($tokens[0])) {
                        $filters .= "lastname" . preparedStmtWildcard($tokens[0], $after);
                        $bindList[] = $after;
                    }
                    if (isset($tokens[1])) {
                        $filters .= " AND firstname" . preparedStmtWildcard($tokens[1], $after);
                        $bindList[] = $after;
                    }
                } else {
                    $column = $QIDO_PATIENT_KEYS[strtoupper($key)];
                    $filters .= "$column" . preparedStmtWildcard($value, $after);
                    $bindList[] = $after;
                }
            }
            // study level keys
            if (array_key_exists(strtoupper($key), $QIDO_STUDY_KEYS)) {
                if (!strcasecmp($key, "00080061") ||
                    !strcasecmp($key, "MODALITIESINSTUDY")) {
                    $sql = "SELECT DISTINCT study.uuid as uuid,study.description as description,study.* FROM study,series,patient";
                    $join = "study.uuid=series.studyuid AND study.patientid=patient.origid AND series.modality" . preparedStmtWildcard($value, $after);
                    if (strlen($filters)) {
                        $filters = $join . " AND " . $filters;
                        array_unshift($bindList, $after);
                    } else {
                        $filters = $join;
                        $bindList[] = $after;
                    }
                } else {
                    if (strlen($filters))
                        $filters .= " AND ";
                    $column = $QIDO_STUDY_KEYS[strtoupper($key)];
                    $default = "$column" . preparedStmtWildcard($value, $after);
                    $toBind = true;
                    if (!strcasecmp($key, "00080020") ||
                        !strcasecmp($key, "StudyDate")) {
                        // check if date range is specified
                        if (strpos($value, "-")) {
                            $value = trim($value);
                            $default = $this->dateRangeQuery($column, $value);
                            $toBind = false;
                        }
                    }
                    $filters .= $default;
                    if ($toBind)
                        $bindList[] = $after;
                }
            }
        }
        if (strlen($filters))
            $sql .= " where $filters";
        if (count($bindList))
            $result = $this->dbcon->preparedStmt($sql, $bindList);
        else
            $result = $this->dbcon->query($sql);
        while ($result && ($row = $result->fetch(PDO::FETCH_ASSOC)))
            $this->rows[] = $row;
    }
    function __destruct() {
        // call base class destructor
        parent::__destruct();
    }
    function sendJson() {
        global $QIDO_STUDY_FIELDS;
        header("Content-Type: application/dicom+json");
        $json = array();
        $keys = array_merge($this->reqAttrs, $this->fields);
        foreach ($this->rows as $study) {
            $entry = array();
            // add required attributes and include fields
            foreach ($keys as $key) {
                if (isset($QIDO_STUDY_FIELDS[$key]) &&
                    !isset($entry[$key])) {
                    $vr = $QIDO_STUDY_FIELDS[$key][0];
                    $column = $QIDO_STUDY_FIELDS[$key][1];
                    $value = array();
                    if (!strcmp($key, "00100010")) {
                        // special processing for Patient Name
                        $alpha = $this->dbcon->getDicomPatientName($study["patientid"]);
                        $ideogr = (isset($study["ideographic"]) && strlen($study["ideographic"]))? $study["ideographic"] : "";
                        $phonetic = (isset($study["phonetic"]) && strlen($study["phonetic"]))? $study["phonetic"] : "";
                        // JSON requires UTF-8 encoding
                        if (function_exists('mb_convert_encoding')) {
                            $alpha = mb_convert_encoding($alpha, 'UTF-8', 'UTF-8');
                            if (strlen($ideogr))
                                $ideogr = mb_convert_encoding($ideogr, 'UTF-8', 'UTF-8');
                            if (strlen($phonetic))
                                $phonetic = mb_convert_encoding($phonetic, 'UTF-8', 'UTF-8');
                        }
                        $pn = array("Alphabetic" => $alpha);
                        $esc = $this->dbcon->getCharsetEscape();
                        if (strlen($ideogr)) {
                            $escaped = str_replace($esc, "", $ideogr);
                            $pn["Ideographic"] = "=" . $escaped;
                        }
                        if (strlen($phonetic)) {
                            $escaped = str_replace($esc, "", $phonetic);
                            $pn["Phonetic"] = "=" . $escaped;
                        }
                        $value[] = $pn;
                    } else {
                        $data = isset($study[$column])? $study[$column] : "";
                        if (strcasecmp($vr, "PN")) {
                            $value[] = $this->toDicomVr($data, $vr);
                        } else {
                            $pn = array("Alphabetic" => $data);
                            $value[] = $pn;
                        }
                    }
                    if (count($value)) {
                        $entry[$key] = array(
                            "vr"        => $vr,
                            "Value"     => $value,
                        );
                    }
                }
            }
            // Instance Availability
            $entry["00080056"] = array(
                "vr"        => "CS",
                "Value"     => array("ONLINE"),
            );
            // Modalities in Study
            $value = str_replace(" ", "\\", $this->dbcon->getStudyModalities($study["uuid"]));
            $entry["00080061"] = array(
                "vr"        => "CS",
                "Value"     => array($value),
            );
            // Retrieve URL
            $entry["00081190"] = array(
                "vr"        => "UR",
                "Value"     => array($this->dbcon->getWadoRsStudyUrl($study["uuid"])),
            );
            // Number of Study Related Series
            $entry["00201206"] = array(
                "vr"        => "IS",
                "Value"     => array($this->dbcon->getStudySeriesCount($study["uuid"])),
            );
            // Number of Study Related Instances
            $entry["00201208"] = array(
                "vr"        => "IS",
                "Value"     => array($this->dbcon->getStudyInstanceCount($study["uuid"])),
            );
            ksort($entry);
            // add this entry
            $json[] = $entry;
        }
        $encoded = json_encode($json);
        if ($encoded == false)
            die("DicomWebStudyResult::json_encode() failed, error = " . json_last_error_msg());
        echo $encoded;
    }
    function sendXml() {
        global $QIDO_STUDY_FIELDS;
        $keys = array_merge($this->reqAttrs, $this->fields);
        header("Content-Type: multipart/related; type=\"application/dicom+xml\"");
        foreach ($this->rows as $study) {
            header("Content-Type: application/dicom+xml");
            echo "<?xml version=\"1.0\" encoding=\"UTF-8\" xml:space=\"preserve\" ?>";
            echo "<NativeDicomModel>";
            // add required attributes and include fields
            foreach ($keys as $key) {
                if (isset($QIDO_STUDY_FIELDS[$key]) &&
                    !isset($entry[$key])) {
                    $vr = $QIDO_STUDY_FIELDS[$key][0];
                    $column = $QIDO_STUDY_FIELDS[$key][1];
                    $keyword = $QIDO_STUDY_FIELDS[$key][2];
                    if (!strcmp($key, "00100010")) {
                        // special processing for Patient Name
                        $pn = $this->dbcon->getDicomPatientName($study["patientid"]);
                        if (isset($study["ideographic"]) && strlen($study["ideographic"])) {
                            $escaped = str_replace($esc, "", $study["ideographic"]);
                            $pn .= "=" . $escaped;
                        }
                        if (isset($study["phonetic"]) && strlen($study["phonetic"])) {
                            $escaped = str_replace($esc, "", $study["phonetic"]);
                            $pn .= "=" . $escaped;
                        }
                        $entry = "<DicomAttribute tag=\"$key\" vr=\"PN\" keyword=\"Patient's Name\">";
                        $entry .= "<PersonName>" . $pn . "</PersonName>";
                        $entry .= "</DicomAttribute>";
                        echo $entry;
                    } else {
                        $data = isset($study[$column])? $study[$column] : "";
                        $entry = "<DicomAttribute tag=\"$key\" vr=\"$vr\" keyword=\"$keyword\">";
                        if (strcasecmp($vr, "PN"))
                            $entry .= "<Value number=\"1\">" . $this->toDicomVr($data, $vr) . "</Value>";
                        else
                            $entry .= "<PersonName number=\"1\">" . $this->toXmlPersonName($data) . "</PersonName>";
                        $entry .= "</DicomAttribute>";
                        echo $entry;
                    }
                }
            }
            // Instance Availability
            $entry = "<DicomAttribute tag=\"00080056\" vr=\"CS\" keyword=\"Instance Availability\">";
            $entry .= "<Value number=\"1\">ONLINE</Value>";
            $entry .= "</DicomAttribute>";
            echo $entry;
            // Modalities in Study
            $entry = "<DicomAttribute tag=\"00080061\" vr=\"CS\" keyword=\"Modalities in Study\">";
            $value = str_replace(" ", "\\", $this->dbcon->getStudyModalities($study["uuid"]));
            $entry .= "<Value number=\"1\">$value</Value>";
            echo $entry;
            // Retrieve URL
            $entry = "<DicomAttribute tag=\"00081190\" vr=\"UR\" keyword=\"Retrieve URL\">";
            $entry .= "<Value number=\"1\">" . $this->dbcon->getWadoRsStudyUrl($study["uuid"]) . "</Value>";
            $entry .= "</DicomAttribute>";
            echo $entry;
            // Number of Study Related Series
            $entry = "<DicomAttribute tag=\"00201206\" vr=\"IS\" keyword=\"Number of Study Related Series\">";
            $entry .= "<Value number=\"1\">" . $this->dbcon->getStudySeriesCount($study["uuid"]) . "</Value>";
            $entry .= "</DicomAttribute>";
            echo $entry;
            // Number of Study Related Instances
            $entry = "<DicomAttribute tag=\"00201208\" vr=\"IS\" keyword=\"Number of Study Related Instances\">";
            $entry .= "<Value number=\"1\">" . $this->dbcon->getStudyInstanceCount($study["uuid"]) . "</Value>";
            $entry .= "</DicomAttribute>";
            echo $entry;
            echo "</NativeDicomModel>";
        }
    }
}

class DicomWebRetrieve extends DicomWeb {
    function __construct(&$dbcon) {
        // call base class constructor
        parent::__construct($dbcon);
    }
    function __destruct() { }
    function retrieve() {
        $eol = "\r\n";
        $boundary = md5(time());
        header("Content-Type: multipart/related; type=\"application/dicom\"; boundary=$boundary");
        echo $eol;
        foreach ($this->rows as $row) {
            $path = $row["path"];
            $xferSyntax = $row["xfersyntax"];
            if (file_exists($path)) {
                echo $eol . '--' . $boundary . $eol;
                echo "Content-Type: application/dicom; transfer-syntax=$xferSyntax";
                echo $eol . $eol;
                $fp = fopen($path, 'rb');
                fpassthru($fp);
                fclose($fp);
            }
        }
        // last part
        echo $eol;
        echo '--' . $boundary . '--' . $eol;
    }
    function sendJson() {
        header("Content-Type: application/dicom+json");
        $json = array();
        foreach ($this->rows as $row) {
            $entry = array();
            $path = $row["path"];
            $image = new RawTags($path);
            foreach ($image->attrs as $key => $attr) {
                if (is_a($attr, 'Sequence')) {
                    $entry[ sprintf("%08X", $key) ] = $attr->toJson();
                } else {
                    $vr = $attr["vr"];
                    $attrName = "Value";
                    $value = dicomValueToJson($vr, $attrName, $attr["value"]);
                    if (count($value)) {
                        $entry[ sprintf("%08X", $key) ] = array(
                            "vr"        => $vr,
                            $attrName   => $value,
                        );
                    }
                }
            }
            // add this entry
            $json[] = $entry;
        }
        $encoded = json_encode($json);
        if ($encoded == false)
            die("DicomWebRetrieve::json_encode() failed, error = " . json_last_error_msg());
        echo $encoded;
    }
    function sendXml() {
        header("Content-Type: application/dicom+xml");
        foreach ($this->rows as $row) {
            echo "<?xml version=\"1.0\" encoding=\"UTF-8\" xml:space=\"preserve\" ?>";
            echo "<NativeDicomModel>";
            $path = $row["path"];
            $image = new RawTags($path);
            foreach ($image->attrs as $key => $attr) {
                if (is_a($attr, 'Sequence')) {
                    echo $attr->toXml();
                } else {
                    $vr = $attr["vr"];
                    echo dicomValueToXml($key, $vr, $attr["value"]);
                }
            }
            echo "</NativeDicomModel>";
        }
    }
    function retrieveBulkData() {
        // figure out the Content Type in response
        $helper = new DicomTransferSyntaxHelper();
        $mediaType = "";
        $xferSyntax = "";
        foreach ($this->rows as $row) {
            $path = $row["path"];
            $xferSyntax = $row["xfersyntax"];
            // sanity checks
            if (!file_exists($path))
                die("DicomWebRetrieve::retrieveBulkData() image file [$path] does not exist!");
            if ($helper->isCompressed($xferSyntax))
                $mediaType = $helper->getMediaType($xferSyntax);
        }
        // send response
        $eol = "\r\n";
        $boundary = md5(time());
        if (strlen($mediaType)) {
            header("Content-Type: multipart/related; type=\"$mediaType\"; boundary=$boundary; transfer-syntax=$xferSyntax");
        } else {
            header("Content-Type: multipart/related; type=\"application/octet-stream\"; boundary=$boundary; transfer-syntax=$xferSyntax");
        }
        echo $eol;
        foreach ($this->rows as $row) {
            $uid = $row["uuid"];
            $path = $row["path"];
            $numFrames = isset($row["numframes"])? $row["numframes"] : 0;
            $xferSyntax = $row["xfersyntax"];
            $rows = $row["numrows"];
            $columns = $row["numcolumns"];
            $bitsAlloc = $row["bitsallocated"];
            $samples = $row["samplesperpixel"];
            $image = new RawTags($path, false);
            $pixelData = $image->getPixelData();
            if ($numFrames) {   // multi-frame
                $numFrags = $pixelData->numberOfFragments();
                if ($numFrames != $numFrags)
                    die(sprintf("DicomWebRetrieve::retrieveBulkData() Number of Fragments %d is different from Number of Frames %d", $numFrags, $numFrames));
                $contentType = "Content-Type: \"$mediaType\"; transfer-syntax=$xferSyntax";
                for ($frame = 1; $frame <= $numFrames; $frame++) {
                    echo $eol . '--' . $boundary . $eol;
                    echo $contentType . $eol;
                    $url = parseDicomWebPrefix();
                    $url .= sprintf("/bulkdata.php/instances/%s/frames/%d", $uid, $frame);
                    echo "Content-Location: $url" . $eol;
                    echo $eol;
                    $data = $pixelData->getFragment($frame)->data;
                    echo $data;
                }
            } else {            // single-frame
                if ($helper->isCompressed($xferSyntax)) {
                    $contentType = "Content-Type: \"$mediaType\"; transfer-syntax=$xferSyntax";
                    $data = $pixelData->getFragment(1)->data;
                } else {
                    $contentType = "Content-Type: \"application/octet-stream\"; transfer-syntax=$xferSyntax";
                    $data = $pixelData->getUncompressedFrameData(1, $rows, $columns, $samples, $bitsAlloc);
                }
                echo $eol . '--' . $boundary . $eol;
                echo $contentType . $eol;
                $url = parseDicomWebPrefix();
                $url .= sprintf("/bulkdata.php/instances/%s", $uid);
                echo "Content-Location: $url" . $eol;
                echo $eol;
                echo $data;
            }
        }
        // last part
        echo $eol;
        echo '--' . $boundary . '--' . $eol;
    }
}

class DicomWebRetrieveInstance extends DicomWebRetrieve {
    function __construct(&$dbcon, &$path, $queries) {
        // call base class constructor
        parent::__construct($dbcon);
        // find this SOP instance
        $uid = "";
        if (stripos($path, "instances")) {
            $tokens = explode("/", $path);
            for ($i = 0; $i < count($tokens); $i++) {
                if (strcasecmp($tokens[$i], "instances") == 0) {
                    if (isset($tokens[$i+1]))
                        $uid = $tokens[$i+1];
                    break;
                }
            }
        }
        if (strlen($uid)) {
            $bindList = array($uid);
            $result = $this->dbcon->preparedStmt("select * from image where uuid=?", $bindList);
            if ($result && ($image = $result->fetch(PDO::FETCH_ASSOC)))
                $this->rows[] = $image;
        }
    }
    function __destruct() { }
}

class DicomWebRetrieveSeries extends DicomWebRetrieve {
    function __construct(&$dbcon, &$path, $queries) {
        // call base class constructor
        parent::__construct($dbcon);
        // find this series
        $uid = "";
        if (stripos($path, "series")) {
            $tokens = explode("/", $path);
            for ($i = 0; $i < count($tokens); $i++) {
                if (strcasecmp($tokens[$i], "series") == 0) {
                    if (isset($tokens[$i+1]))
                        $uid = $tokens[$i+1];
                    break;
                }
            }
        }
        if (strlen($uid)) {
            $bindList = array($uid);
            $result = $this->dbcon->preparedStmt("select * from image where seriesuid=?", $bindList);
            while ($result && ($image = $result->fetch(PDO::FETCH_ASSOC)))
                $this->rows[] = $image;
        }
    }
    function __destruct() { }
}

class DicomWebRetrieveStudy extends DicomWebRetrieve {
    function __construct(&$dbcon, &$path, $queries) {
        // call base class constructor
        parent::__construct($dbcon);
        // find this study
        $uid = "";
        if (stripos($path, "studies")) {
            $tokens = explode("/", $path);
            for ($i = 0; $i < count($tokens); $i++) {
                if (strcasecmp($tokens[$i], "studies") == 0) {
                    if (isset($tokens[$i+1]))
                        $uid = $tokens[$i+1];
                    break;
                }
            }
        }
        if (strlen($uid)) {
            $bindList = array($uid);
            // find all series of this study
            $seriesList = array();
            $result = $this->dbcon->preparedStmt("select uuid from series where studyuid=?", $bindList);
            while ($result && ($series = $result->fetch(PDO::FETCH_NUM)))
                $seriesList[] = $series[0];
            foreach ($seriesList as $uid) {
                $result = $this->dbcon->query("select * from image where seriesuid='$uid'");
                while ($result && ($image = $result->fetch(PDO::FETCH_ASSOC)))
                    $this->rows[] = $image;
            }
        }
    }
    function __destruct() { }
}

class DicomWebRetrieveFrames extends DicomWebRetrieveInstance {
    var $frameList = array();

    function __construct(&$dbcon, &$path, $queries) {
        // call base class constructor
        parent::__construct($dbcon, $path, $queries);
        // check if individual frame list is specified
        $frames = "";
        $tokens = explode("/", $path);
        for ($i = 0; $i < count($tokens); $i++) {
            if (strcasecmp($tokens[$i], "frames") == 0) {
                if (isset($tokens[$i+1]))
                    $frames = $tokens[$i+1];
                break;
            }
        }
        if (strlen($frames)) {
            $tokens = explode(",", $frames);
            foreach ($tokens as $token)
                $this->frameList[] = $token;
        }
    }
    function __destruct() { }
    // overwrite parent methods
    function retrieve() {
        return $this->retrieveBulkData();
    }
    function retrieveBulkData() {
        $row = $this->rows[0];
        $uid = $row["uuid"];
        $path = $row["path"];
        // sanity checks
        if (!file_exists($path))
            die("DicomWebRetrieveFrames: image file [$path] does not exist!");
        $numFrames = isset($row["numframes"])? $row["numframes"] : 0;
        if (!$numFrames)
            die(sprintf("DicomWebRetrieveFrames: Number of Frames is 0/un-defined for image [%s]", $path));
        if (count($this->frameList)) {
            foreach ($this->frameList as $frame)
                if ($frame > $numFrames)
                    die(sprintf("DicomWebRetrieveFrames: Frame: %d is larger than total number of frames %d", $frame, $numFrames));
        } else {    // default to all frames
            for ($i = 0; $i < $numFrames; $i++)
                $this->frameList[] = $i+1;
        }
        $xferSyntax = $row["xfersyntax"];
        $rows = $row["numrows"];
        $columns = $row["numcolumns"];
        $bitsAlloc = $row["bitsallocated"];
        $samples = $row["samplesperpixel"];
        $image = new RawTags($path, false);
        $pixelData = $image->getPixelData();
        // send response
        $eol = "\r\n";
        $boundary = md5(time());
        $helper = new DicomTransferSyntaxHelper();
        if ($helper->isCompressed($this->xferSyntax)) {
            $numFrags = $pixelData->numberOfFragments();
            if ($numFrames != $numFrags)
                die(sprintf("DicomWebRetrieveFrames: Number of Fragments %d is different from Number of Frames %d", $numFrags, $numFrames));
            $mediaType = $helper->getMediaType($xferSyntax);
            header("Content-Type: multipart/related; type=\"$mediaType\"; boundary=$boundary; transfer-syntax=$xferSyntax");
            $contentType = "Content-Type: \"$mediaType\"; transfer-syntax=$xferSyntax";
        } else {
            header("Content-Type: multipart/related; type=\"application/octet-stream\"; boundary=$boundary; transfer-syntax=$xferSyntax");
            $contentType = "Content-Type: \"application/octet-stream\"; transfer-syntax=$xferSyntax";
        }
        echo $eol;
        foreach ($this->frameList as $frame) {
            echo $eol . '--' . $boundary . $eol;
            echo $contentType . $eol;
            $url = parseDicomWebPrefix();
            $url .= sprintf("/bulkdata.php/instances/%s/frames/%d", $uid, $frame);
            echo "Content-Location: $url" . $eol;
            echo $eol;
            if ($helper->isCompressed($this->xferSyntax)) {
                $data = $pixelData->getFragment($frame)->data;
            } else {
                $data = $pixelData->getUncompressedFrameData($frame, $rows, $columns, $samples, $bitsAlloc);
            }
            echo $data;
        }
        // last part
        echo $eol;
        echo '--' . $boundary . '--' . $eol;
    }
}

abstract class StowRsWarningReason {
    const CoercionOfDataElements = 0xB000;
    const ElementsDiscarded = 0xB006;
    const DatasetNotMatchSopClass = 0xB007;
}

abstract class StowRsFailedReason {
    const RefusedOutOfResources = 0xA700;
    const DatasetNotMatchSopClass = 0xA900;
    const CannotUnderstand = 0xC000;
    const TransferSyntaxNotSupported = 0xC122;
    const ProcessingFailure = 0x0110;
    const SopClassNotSupported = 0x0122;
}

class DicomWebStow extends DicomWeb {
    var $studyuid = "";
    var $success = array();
    var $failed = array();
    function __construct(&$dbcon, $path) {
        // call base class constructor
        parent::__construct($dbcon);
        // check if a Study Instance UID is specified
        if (stripos($path, "studies")) {
            $tokens = explode("/", $path);
            for ($i = 0; $i < count($tokens); $i++) {
                if (strcasecmp($tokens[$i], "studies") == 0) {
                    if ($i + 1 < count($tokens))
                        $this->studyuid = $tokens[$i+1];
                    break;
                }
            }
        }
        $dir = $dbcon->getStowRsStoreDir() . strtoupper(date("Y-m-d-D")) . "/";
        if (!file_exists($dir))
            mkdir($dir);
        // parse the multi-part message body
        $raw_data = file_get_contents('php://input');
        // skip beginning newlines
        while (0 == strpos($raw_data, "\r\n"))
            $raw_data = substr($raw_data, 2);
        $boundary = substr($raw_data, 0, strpos($raw_data, "\r\n"));
        // fetch each part
        $parts = array_slice(explode($boundary, $raw_data), 1);
        foreach ($parts as $part) {
            // if this is the last part, break
            if ($part == "--\r\n") break; 
            // separate content from headers
            $part = trim($part, "\r\n");
            list($raw_headers, $body) = explode("\r\n\r\n", $part, 2);
            // parse the headers list
            $raw_headers = explode("\r\n", $raw_headers);
            $headers = array();
            foreach ($raw_headers as $header) {
                list($name, $value) = explode(':', $header);
                $headers[strtolower($name)] = ltrim($value, ' '); 
            }
            // verify the Content-Type header
            if (isset($headers['content-type']) &&
                !strcasecmp($headers['content-type'], "application/dicom")) {
                // save this message body into a temporary file
                $tempname = tempnam($dir, "PacsOne");
                if (file_put_contents($tempname, $body) != false)
                    $this->rows[] = str_replace("\\", "/", $tempname);
            }
        }
    }
    function __destruct() { }
    function checkKeyExists($table, $column, $key) {
        $exist = 0;
        $bindList = array($key);
        $result = $this->dbcon->preparedStmt("select count($column) from $table where $column=?", $bindList);
        if ($result && ($match = $result->fetch(PDO::FETCH_NUM)))
            $exist = $match[0];
        return $exist;
    }
    function insertTable($table, &$columns, $logSql = false, $logfile = "") {
        if (!count($columns))
            return false;
        $sql = "insert into $table (";
        $bindList = array();
        foreach ($columns as $column => $value)
            if (isset($value[1]) && strlen($value[1]))
                $sql .= "$column,";
        // remove the last ','
        $sql = substr($sql, 0, -1);
        $sql .= ") values (";
        foreach ($columns as $column => $value) {
            if (isset($value[1]) && strlen($value[1])) {
                if ($value[0]) {
                    $sql .= "?,";
                    $bindList[] = $value[1];
                } else {
                    $sql .= $value[1] . ",";
                }
            }
        }
        // remove the last ','
        $sql = substr($sql, 0, -1) . ")";
        if ($logSql && strlen($logfile)) {
            $msg = sprintf("Running query: [%s]", $sql);
            file_put_contents($logfile, $msg);
        }
        $result = $this->dbcon->preparedStmt($sql, $bindList);
        if (!$result && strlen($logfile)) {
            $msg = sprintf("\r\nDatabase error = %s", $this->dbcon->getError());
            file_put_contents($logfile, $msg, FILE_APPEND);
        }
        return $result;
    }
    function updateTable($table, $key, &$columns, $logSql = false, $logfile = "") {
        if (!count($columns))
            return false;
        $sql = "update $table set ";
        $bindList = array();
        foreach ($columns as $column => $value) {
            if (strcasecmp($column, $key['column']) && isset($value[1]) && strlen($value[1])) {
                if ($value[0]) {
                    $sql .= "$column=?,";
                    $bindList[] = $value[1];
                } else {
                    $sql .= sprintf("%s=%s,", $column, $value[1]);
                }
            }
        }
        // remove the last ','
        $sql = substr($sql, 0, -1);
        $sql .= " where " . $key['column'] . "=?";
        $bindList[] = $key['value'];
        if ($logSql && strlen($logfile)) {
            $msg = sprintf("Running query: [%s]", $sql);
            file_put_contents($logfile, $msg);
        }
        $result = $this->dbcon->preparedStmt($sql, $bindList);
        if (!$result && strlen($logfile)) {
            $msg = sprintf("\r\nDatabase error = %s", $this->dbcon->getError());
            file_put_contents($logfile, $msg, FILE_APPEND);
        }
        return $result;
    }
    function parsePatientName($value) {
        $pn = array(
            "lastname"      => "",
            "firstname"     => "",
            "middlename"    => "",
            "prefix"        => "",
            "suffix"        => "",
            "ideographic"   => "",
            "phonetic"      => "",
        );
        $tokens = explode("=", $value);
        if (isset($tokens[0]) && strlen($tokens[0])) {
            $comps = explode("^", $tokens[0]);
            $pn["lastname"] = isset($comps[0])? $comps[0] : "";
            $pn["firstname"] = isset($comps[1])? $comps[1] : "";
            $pn["middlename"] = isset($comps[2])? $comps[2] : "";
            $pn["prefix"] = isset($comps[3])? $comps[3] : "";
            $pn["suffix"] = isset($comps[4])? $comps[4] : "";
        } else {
            // mal-formatted name, save value to Last Name
            $pn["lastname"] = $value;
        }
        if (isset($tokens[1]) && strlen($tokens[1]))
            $pn["ideographic"] = $tokens[1];
        if (isset($tokens[2]) && strlen($tokens[2]))
            $pn["phonetic"] = $tokens[2];
        return $pn;
    }
    function parseAgeString($value) {
        $age = 0.0;
        switch (strtoupper(substr($value, -1, 1))) {
        case "Y":
            $age = (int) substr($value, 0, 3);
            break;
        case "M":
            $age = ((int) substr($value, 0, 3)) / 12.0;
            break;
        case "D":
            $age = ((int) substr($value, 0, 3)) / 365.0 ;
            break;
        case "W":
            $age = ((int) substr($value, 0, 3)) / 52.0;
            break;
        default:
            break;
        }
        return $age;
    }
    function patientIdConflict(&$pid, $firstname, $lastname, $middlename) {
        $dup = false;
        $query = "select firstname,lastname,middlename,origid from patient where origid=? or origid like ? order by origid desc";
        $bindList = array($pid, $pid . "[%");
        $result = $this->dbcon->preparedStmt($query, $bindList);
        while ($result && ($patient = $result->fetch(PDO::FETCH_NUM))) {
            $first = isset($patient[0])? $patient[0] : "";
            $last = isset($patient[1])? $patient[1] : "";
            $middle = isset($patient[2])? $patient[2] : "";
            $origid = isset($patient[3])? $patient[3] : "";
            // check if this record is already a duplicate
            if (strlen($origid) && strcasecmp($origid, $pid) &&
                strstr($origid, "[") && strstr($origid, "]")) {
                $match = false;
                if (strlen($first) == strlen($firstname) &&
                    strlen($last) == strlen($lastname))
                {
                    $match = true;
                    if ((strlen($first) && strcasecmp($first, $firstname)) ||
                        (strlen($last) && strcasecmp($last, $lastname)))
                        $match = false;
                }
                if ($match) {
                    // found a matching duplicate
                    $pid = $origid;
                    return false;
                }
            }
            // compare the existing names vs new names
            $current = array();
            if (strlen($first))
                $current[] = trim(str_replace(",", " ", $first));
            if (strlen($middle))
                $current[] = trim(str_replace(",", " ", $middle));
            if (strlen($last))
                $current[] = trim(str_replace(",", " ", $last));
            $newNames = array();
            if (strlen($firstname))
                $newNames[] = trim(str_replace(",", " ", $firstname));
            if (strlen($middlename))
                $newNames[] = trim(str_replace(",", " ", $middlename));
            if (strlen($lastname))
                $newNames[] = trim(str_replace(",", " ", $lastname));
            if (count($current) < count($newNames)) {
                $shortNames =& $current;
                $longNames =& $newNames;
            } else {
                $longNames =& $current;
                $shortNames =& $newNames;
            }
            // check if one set of names is empty/blank
            if (count($shortNames) == 0 && count($longNames)) {
                $dup = true;
                break;
            }
            foreach ($shortNames as $short) {
                $match = false;
                foreach ($longNames as $long) {
                    if (strcasecmp($short, $long) == 0) {
                        $match = true;
                        break;
                    }
                }
                if (!$match) {
                    $dup = true;
                    break;
                }
            }
        }
        return $dup;
    }
    function import($rawTags, $tempfile, $ipaddr) {
        $status = 0;
        // parse into IMAGE table
        $ok = false;
        $uid = $rawTags->getAttr(0x00080018);
        // rename temporary file to the SOP Instance UID
        $dir = dirname($tempfile);
        $newfile = $dir . "/" . $uid;
        if (!rename($tempfile, $newfile)) {
            unlink($tempfile);
            return StowRsFailedReason::RefusedOutOfResources;
        }
        $columns = array(   // array index 0 - if value is place holder for prepared statement
            "path"                  => array(true, $newfile),
            "uuid"                  => array(true, $uid),
            "sopclass"              => array(true, $rawTags->getAttr(0x00080016)),
            "xfersyntax"            => array(true, $rawTags->getAttr(0x00020010)),
            "seriesuid"             => array(true, $rawTags->getAttr(0x0020000e)),
            "instance"              => array(true, $rawTags->getAttr(0x00200013)),
            "instancedate"          => array(false, $this->dbcon->valueToDate($rawTags->getAttr(0x00080012))),
            "instancetime"          => array(true, $rawTags->getAttr(0x00080013)),
            "samplesperpixel"       => array(true, $rawTags->getAttr(0x00280002)),
            "photometric"           => array(true, $rawTags->getAttr(0x00280004)),
            "numframes"             => array(true, $rawTags->getAttr(0x00280008)),
            "numrows"               => array(true, $rawTags->getAttr(0x00280010)),
            "numcolumns"            => array(true, $rawTags->getAttr(0x00280011)),
            "bitsallocated"         => array(true, $rawTags->getAttr(0x00280100)),
            "bitsstored"            => array(true, $rawTags->getAttr(0x00280101)),
            "pixelrepresentation"   => array(true, $rawTags->getAttr(0x00280103)),
        );
        if ($this->checkKeyExists("image", "uuid", $uid)) {
            $key = array("column" => "uuid", "value" => $uid);
            $ok = $this->updateTable("image", $key, $columns);
        } else
            $ok = $this->insertTable("image", $columns);
        if (!$ok) {
            unlink($newfile);
            return StowRsFailedReason::ProcessingFailure;
        }
        // parse into SERIES table
        $ok = false;
        $uid = $rawTags->getAttr(0x0020000e);
        $columns = array(   // array index 0 - if value is place holder for prepared statement
            "uuid"                  => array(true, $uid),
            "studyuid"              => array(true, $rawTags->getAttr(0x0020000d)),
            "modality"              => array(true, $rawTags->getAttr(0x00080060)),
            "seriesnumber"          => array(true, $rawTags->getAttr(0x00200011)),
            "instances"             => array(true, $rawTags->getAttr(0x00201002)),
            "seriesdate"            => array(false, $this->dbcon->valueToDate($rawTags->getAttr(0x00080021))),
            "seriestime"            => array(true, $rawTags->getAttr(0x00080031)),
            "description"           => array(true, $rawTags->getAttr(0x0008103E)),
            "bodypart"              => array(true, $rawTags->getAttr(0x00180015)),
            "institution"           => array(true, $rawTags->getAttr(0x00080080)),
            "protocolname"          => array(true, $rawTags->getAttr(0x00181030)),
            "stationname"           => array(true, $rawTags->getAttr(0x00081010)),
            "department"            => array(true, $rawTags->getAttr(0x00081040)),
            "operatorname"          => array(true, $rawTags->getAttr(0x00081070)),
        );
        if ($this->checkKeyExists("series", "uuid", $uid)) {
            $key = array("column" => "uuid", "value" => $uid);
            $ok = $this->updateTable("series", $key, $columns);
        } else
            $ok = $this->insertTable("series", $columns);
        if (!$ok) {
            unlink($newfile);
            return StowRsFailedReason::ProcessingFailure;
        }
        // parse into STUDY table
        $uid = $rawTags->getAttr(0x0020000d);
        $pid = $rawTags->getAttr(0x00100020);
        $pn = $this->parsePatientName($rawTags->getAttr(0x00100010));
        if ($this->patientIdConflict($pid, $pn["firstname"], $pn["lastname"], $pn["middlename"])) {
            // flag this Patient ID as a duplicate and return Warning status
            $pid .= sprintf("[STOWRS-%s-%s]", $ipaddr, date("YmdHis"));
            $status = StowRsWarningReason::CoercionOfDataElements;
        }
        $ok = false;
        $columns = array(   // array index 0 - if value is place holder for prepared statement
            "uuid"                  => array(true, $uid),
            "patientid"             => array(true, $pid),
            "id"                    => array(true, $rawTags->getAttr(0x00200010)),
            "studydate"             => array(false, $this->dbcon->valueToDate($rawTags->getAttr(0x00080020))),
            "studytime"             => array(true, $rawTags->getAttr(0x00080030)),
            "accessionnum"          => array(true, $rawTags->getAttr(0x00080050)),
            "description"           => array(true, $rawTags->getAttr(0x00081030)),
            "readingphysician"      => array(true, $rawTags->getAttr(0x00081060)),
            "referringphysician"    => array(true, $rawTags->getAttr(0x00080090)),
            "requestingphysician"   => array(true, $rawTags->getAttr(0x00321032)),
            "admittingdiagnoses"    => array(true, $rawTags->getAttr(0x00081080)),
            "interpretationauthor"  => array(true, $rawTags->getAttr(0X4008010C)),
            "sourceae"              => array(true, "STOWRS-$ipaddr"),
            "received"              => array(false, $this->dbcon->useOracle? "SYSDATE" : "NOW()"),
        );
        if ($this->checkKeyExists("study", "uuid", $uid)) {
            $key = array("column" => "uuid", "value" => $uid);
            $ok = $this->updateTable("study", $key, $columns);
        } else
            $ok = $this->insertTable("study", $columns);
        if (!$ok) {
            unlink($newfile);
            return StowRsFailedReason::ProcessingFailure;
        }
        // parse into PATIENT table
        $columns = array(   // array index 0 - if value is place holder for prepared statement
            "origid"                => array(true, $pid),
            "lastname"              => array(true, $pn["lastname"]),
            "firstname"             => array(true, $pn["firstname"]),
            "middlename"            => array(true, $pn["middlename"]),
            "prefix"                => array(true, $pn["prefix"]),
            "suffix"                => array(true, $pn["suffix"]),
            "institution"           => array(true, $rawTags->getAttr(0x00080080)),
            "sex"                   => array(true, $rawTags->getAttr(0x00100040)),
            "birthDate"             => array(false, $this->dbcon->valueToDate($rawTags->getAttr(0x00100030))),
            "birthTime"             => array(true, $rawTags->getAttr(0x00100032)),
            "ethnicGroup"           => array(true, $rawTags->getAttr(0x00102160)),
            "occupation"            => array(true, $rawTags->getAttr(0x00102180)),
            "height"                => array(true, $rawTags->getAttr(0x00101020)),
            "weight"                => array(true, $rawTags->getAttr(0x00101030)),
            "address"               => array(true, $rawTags->getAttr(0x00101040)),
            // veterinary specific information
            "speciesdescr"          => array(true, $rawTags->getAttr(0x00102201)),
            "sexneutered"           => array(true, $rawTags->getAttr(0x00102203)),
            "breeddescr"            => array(true, $rawTags->getAttr(0x00102292)),
            "respperson"            => array(true, $rawTags->getAttr(0x00102297)),
            "resppersonrole"        => array(true, $rawTags->getAttr(0x00102298)),
            "resppersonorg"         => array(true, $rawTags->getAttr(0x00102299)),
        );
        if ($this->checkKeyExists("patient", "origid", $pid)) {
            $key = array("column" => "origid", "value" => $pid);
            $ok = $this->updateTable("patient", $key, $columns);
        } else
            $ok = $this->insertTable("patient", $columns);
        if (!$ok) {
            unlink($newfile);
            return StowRsFailedReason::ProcessingFailure;
        }
        // veterinary specific tables
        $breedReg = $rawTags->getSequence(0x00102294);
        $breedRegCode = $rawTags->getSequence(0x00102296);
        if ($breedReg || $breedRegCode) {
            $columns = array(
                "patientid"         => array(true, $pid),
            );
            if ($breedReg)
                $columns["regnumber"] = array(true, $breedReg->getAttr(0x00102295));
            if ($breedRegCode) {
                $columns["value"] = array(true, $breedRegCode->getAttr(0x00080100));
                $columns["schemedesignator"] = array(true, $breedRegCode->getAttr(0x00080102));
                $columns["schemeversion"] = array(true, $breedRegCode->getAttr(0x00080103));
                $columns["meaning"] = array(true, $breedRegCode->getAttr(0x00080104));
            }
            if ($this->checkKeyExists("breedregistration", "patientid", $pid)) {
                $key = array("column" => "patientid", "value" => $pid);
                $ok = $this->updateTable("breedregistration", $key, $columns);
            } else
                $ok = $this->insertTable("breedregistration", $columns);
            if (!$ok) {
                unlink($newfile);
                return StowRsFailedReason::ProcessingFailure;
            }
        }
        $speciesCode = $rawTags->getSequence(0x00102202);
        if ($speciesCode) {
            $columns = array(
                "patientid"         => array(true, $pid),
                "value"             => array(true, $speciesCode->getAttr(0x00080100)),
                "schemedesignator"  => array(true, $speciesCode->getAttr(0x00080102)),
                "schemeversion"     => array(true, $speciesCode->getAttr(0x00080103)),
                "meaning"           => array(true, $speciesCode->getAttr(0x00080104)),
            );
            if ($this->checkKeyExists("patientspeciescode", "patientid", $pid)) {
                $key = array("column" => "patientid", "value" => $pid);
                $ok = $this->updateTable("patientspeciescode", $key, $columns);
            } else
                $ok = $this->insertTable("patientspeciescode", $columns);
            if (!$ok) {
                unlink($newfile);
                return StowRsFailedReason::ProcessingFailure;
            }
        }
        $breedCode = $rawTags->getSequence(0x00102293);
        if ($breedCode) {
            $columns = array(
                "patientid"         => array(true, $pid),
                "value"             => array(true, $breedCode->getAttr(0x00080100)),
                "schemedesignator"  => array(true, $breedCode->getAttr(0x00080102)),
                "schemeversion"     => array(true, $breedCode->getAttr(0x00080103)),
                "meaning"           => array(true, $breedCode->getAttr(0x00080104)),
            );
            if ($this->checkKeyExists("patientbreedcode", "patientid", $pid)) {
                $key = array("column" => "patientid", "value" => $pid);
                $ok = $this->updateTable("patientbreedcode", $key, $columns);
            } else
                $ok = $this->insertTable("patientbreedcode", $columns);
            if (!$ok) {
                unlink($newfile);
                return StowRsFailedReason::ProcessingFailure;
            }
        }
        return $status;
    }
    function store($json, $username, $ipaddr) {
        foreach ($this->rows as $row) {
            if (file_exists($row)) {
                $image = new RawTags($row);
                // check required data elements
                $sopclass = $image->getAttr(0x00080016);
                $uid = $image->getAttr(0x00080018);
                $studyuid = $image->getAttr(0x0020000d);
                $seriesuid = $image->getAttr(0x0020000e);
                if (!strlen($sopclass) || !strlen($uid) || !strlen($seriesuid) || !strlen($studyuid)) {
                    // reject this instance as not all required data elements are present
                    $this->failed[] = array(
                        "sopclass"      => $sopclass,
                        "uid"           => $uid,
                        "reason"        => StowRsFailedReason::DatasetNotMatchSopClass,
                    );
                    unlink($row);
                    continue;
                }
                if (strlen($this->studyuid) && strcasecmp($studyuid, $this->studyuid)) {
                    // reject this instance as it does not match with the specified study
                    $this->failed[] = array(
                        "sopclass"      => $sopclass,
                        "uid"           => $uid,
                        "reason"        => StowRsFailedReason::CannotUnderstand,
                    );
                    unlink($row);
                    continue;
                }
                // save the Study Instance UID
                if (!strlen($this->studyuid))
                    $this->studyuid = $studyuid;
            }
            $reason = $this->import($image, $row, $ipaddr);
            if ($reason) {
                $this->failed[] = array(
                    "sopclass"      => $sopclass,
                    "uid"           => $uid,
                    "reason"        => $reason,
                );
            } else {
                $this->success[] = array(
                    "sopclass"      => $sopclass,
                    "uid"           => $uid,
                    "retrieveurl"   => $this->dbcon->getWadoRsInstanceUrl($studyuid, $seriesuid, $uid),
                );
            }
        }
        // log activity to system journal
        $what = sprintf(pacsone_gettext("%d instances stored successfully and %d failed"), count($this->success), count($this->failed));
        $this->dbcon->logJournal($username, "STOW-RS", "Store", $what);
        $this->sendResponse($json);
    }
    function sendResponse($json) {
        if (count($this->failed) == 0)
            header('HTTP/1.0 200 Success');
        // at least 1 instance has failed
        else if (count($this->success) == 0)
            header('HTTP/1.0 409 Conflict');
        else
            header('HTTP/1.0 202 Accepted');
        // response message body
        if ($json)
            $this->sendResponseBodyJson();
        else
            $this->sendResponseBodyXml();
    }
    function sendResponseBodyJson() {
        header("Content-Type: application/dicom+json");
        $json = array();
        $entry = array();
        // retrieve URL for study
        if (strlen($this->studyuid)) {
            $value = array($this->dbcon->getWadoRsStudyUrl($this->studyuid));
            $entry["00081190"] = array(
                "vr" =>"UR",
                "Value" => $value,
            );
        }
        // failed SOP instances
        if (count($this->failed)) {
            $entry["00081198"] = array(
                "vr" =>"SQ",
                "Value" => array(),
            );
            foreach ($this->failed as $failed) {
                $item = array();
                $item["00081150"] = array(
                    "vr" => "UI",
                    "Value" => array( $failed["sopclass"] ),
                );
                $item["00081155"] = array(
                    "vr" => "UI",
                    "Value" => array( $failed["uid"] ),
                );
                $item["00081197"] = array(
                    "vr" => "US",
                    "Value" => array( $failed["reason"] ),
                );
                $entry["00081198"]["Value"][] = $item;
            }
        }
        // successful SOP instances
        if (count($this->success)) {
            $entry["00081199"] = array(
                "vr" =>"SQ",
                "Value" => array(),
            );
            foreach ($this->success as $success) {
                $item = array();
                $item["00081150"] = array(
                    "vr" => "UI",
                    "Value" => array( $success["sopclass"] ),
                );
                $item["00081155"] = array(
                    "vr" => "UI",
                    "Value" => array( $success["uid"] ),
                );
                $item["00081190"] = array(
                    "vr" => "UR",
                    "Value" => array( $success["retrieveurl"] ),
                );
                if (isset($success["reason"])) {
                    $item["00081197"] = array(
                        "vr" => "US",
                        "Value" => array( $success["reason"] ),
                    );
                }
                $entry["00081199"]["Value"][] = $item;
            }
        }
        $json[] = $entry;
        $encoded = json_encode($json);
        if ($encoded == false)
            die("DicomWebStow::json_encode() failed, error = " . json_last_error_msg());
        echo $encoded;
    }
    function sendResponseBodyXml() {
        header("Content-Type: application/dicom+xml");
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\" xml:space=\"preserve\" ?>";
        echo "<NativeDicomModel>";
        // retrieve URL for study
        if (strlen($this->studyuid)) {
            echo "<DicomAttribute tag=\"00081190\" vr=\"UR\" keyword=\"RetrieveURL\">";
            echo "<Value number=\"1\">" . $this->dbcon->getWadoRsStudyUrl($this->studyuid) . "</Value>";
            echo "</DicomAttribute>";
        }
        // failed SOP instances
        $index = 1;
        echo "<DicomAttribute tag=\"00081198\" vr=\"SQ\" keyword=\"FailedSOPSequence\">";
        foreach ($this->failed as $instance) {
            echo "<Item number=\"$index\">";
            echo "<DicomAttribute tag=\"00081150\" vr=\"UI\" keyword=\"ReferencedSOPClassUID\">";
            echo "<Value number=\"1\">" . $instance["sopclass"] . "</Value>";
            echo "</DicomAttribute>";
            echo "<DicomAttribute tag=\"00081155\" vr=\"UI\" keyword=\"ReferencedSOPInstanceUID\">";
            echo "<Value number=\"1\">" . $instance["uid"] . "</Value>";
            echo "</DicomAttribute>";
            echo "<DicomAttribute tag=\"00081197\" vr=\"US\" keyword=\"FailureReason\">";
            echo "<Value number=\"1\">" . $instance["reason"] . "</Value>";
            echo "</DicomAttribute>";
            $index++;
            echo "</Item>";
        }
        echo "</DicomAttribute>";
        // successful SOP instances
        $index = 1;
        echo "<DicomAttribute tag=\"00081199\" vr=\"SQ\" keyword=\"ReferencedSOPSequence\">";
        foreach ($this->success as $instance) {
            echo "<Item number=\"$index\">";
            echo "<DicomAttribute tag=\"00081150\" vr=\"UI\" keyword=\"ReferencedSOPClassUID\">";
            echo "<Value number=\"1\">" . $instance["sopclass"] . "</Value>";
            echo "</DicomAttribute>";
            echo "<DicomAttribute tag=\"00081155\" vr=\"UI\" keyword=\"ReferencedSOPInstanceUID\">";
            echo "<Value number=\"1\">" . $instance["uid"] . "</Value>";
            echo "</DicomAttribute>";
            echo "<DicomAttribute tag=\"00081190\" vr=\"UR\" keyword=\"RetrieveURL\">";
            echo "<Value number=\"1\">" . $instance["retrieveurl"] . "</Value>";
            echo "</DicomAttribute>";
            if (isset($instance["reason"])) {
                echo "<DicomAttribute tag=\"00081196\" vr=\"UI\" keyword=\"WarningReason\">";
                echo "<Value number=\"1\">" . $instance["reason"] . "</Value>";
                echo "</DicomAttribute>";
            }
            $index++;
            echo "</Item>";
        }
        echo "</DicomAttribute>";
        echo "</NativeDicomModel>";
    }
}

?>
