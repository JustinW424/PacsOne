<?php
//
// worklist.php
//
// Module for handling items in the local worklist table
//
// CopyRight (c) 2004-2020 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once "security.php";
include_once "dicom.php";
include_once "display.php";
include_once "tabbedpage.php";

// worklist tabbed pages
class WorklistPage extends TabbedPage {
    var $dbcon;
    var $query;
    var $offset;
    var $all;

    function __construct(&$dbcon, $query, $offset, $all) {
        $this->dbcon = $dbcon;
        $this->query = $query;
        $this->offset = $offset;
        $this->all = $all;
    }
    function __destruct() { }
    function showHtml() {
        $result = $this->dbcon->query($this->query);
        $rows = array();
        while ($result && ($row = $result->fetch(PDO::FETCH_BOTH)))
            $rows[] = $row;
        $count = $result->rowCount();
        if ($count > 1)
            $preface = sprintf(pacsone_gettext("There are %d worklist items found in the database."), $count);
        else
            $preface = sprintf(pacsone_gettext("There is %d worklist item found in the database."), $count);
        displayWorklist($preface, $rows, $this->url, $this->offset, $this->all);
    }
}

class WorklistTodayPage extends WorklistPage {
    function __construct(&$dbcon, $query, $offset, $all) {
        // call base class constructor
        parent::__construct($dbcon, $query, $offset, $all);
        $this->title = pacsone_gettext("Today's Worklist");
        $this->url = "worklist.php?type=0";
    }
    function __destruct() { }
}

class WorklistYesterdayPage extends WorklistPage {
    function __construct(&$dbcon, $query, $offset, $all) {
        // call base class constructor
        parent::__construct($dbcon, $query, $offset, $all);
        $this->title = pacsone_gettext("Yesterday's Worklist");
        $this->url = "worklist.php?type=1";
    }
    function __destruct() { }
}

class WorklistThisWeekPage extends WorklistPage {
    function __construct($dbcon, $query, $offset, $all) {
        // call base class constructor
        parent::__construct($dbcon, $query, $offset, $all);
        $this->title = pacsone_gettext("This Week's Worklist");
        $this->url = "worklist.php?type=2";
    }
    function __destruct() { }
}

class WorklistThisMonthPage extends WorklistPage {
    function __construct($dbcon, $query, $offset, $all) {
        // call base class constructor
        parent::__construct($dbcon, $query, $offset, $all);
        $this->title = pacsone_gettext("This Month's Worklist");
        $this->url = "worklist.php?type=3";
    }
    function __destruct() { }
}

class WorklistLastMonthPage extends WorklistPage {
    function __construct($dbcon, $query, $offset, $all) {
        // call base class constructor
        parent::__construct($dbcon, $query, $offset, $all);
        $this->title = pacsone_gettext("Last Month's Worklist");
        $this->url = "worklist.php?type=4";
    }
    function __destruct() { }
}

class WorklistAllPage extends WorklistPage {
    function __construct($dbcon, $query, $offset, $all) {
        // call base class constructor
        parent::__construct($dbcon, $query, $offset, $all);
        $this->title = pacsone_gettext("All Worklist");
        $this->url = "worklist.php?type=5";
    }
    function __destruct() { }
}

class WorklistEnterNewPage extends WorklistPage {
    function __construct($dbcon, $query, $offset, $all) {
        // call base class constructor
        parent::__construct($dbcon, $query, $offset, $all);
        $this->title = pacsone_gettext("Enter New Worklist");
        $this->url = "enterWorklist.php";
    }
    function __destruct() { }
}

// worklist item tabbed pages
class WorklistItemPage extends TabbedPage {
    var $row;
    var $columns;
    var $table;
    function __construct(&$row) {
        $this->row = $row;
    }
    function __destruct() { }
    function showHtml() {
        displayWorklistItem($this, $this->row);
    }
}

class ScheduledProcedureStep extends WorklistItemPage {
    function __construct(&$row) {
        // call base class constructor
        parent::__construct($row);
        $this->title = pacsone_gettext("Scheduled Procedure Step");
        $uid = $row["studyuid"];
        $this->url = "worklist.php?uid=$uid&page=0";
        $this->columns = array (
            pacsone_gettext("Station AE Title")              => "aetitle",
            pacsone_gettext("Station Name")                  => "station",
            pacsone_gettext("Modality")                      => "modality",
            pacsone_gettext("Procedure Step Location")       => "location",
            pacsone_gettext("Procedure Step Start Date")     => "startdate",
            pacsone_gettext("Procedure Step Start Time")     => "starttime",
            pacsone_gettext("Procedure Description")         => "description",
            pacsone_gettext("Performing Physician's Name")   => "performingphysician",
            pacsone_gettext("Procedure Step ID")             => "id",
            pacsone_gettext("Procedure Step Status")         => "status",
            pacsone_gettext("Pre-Medication")                => "premedication",
            pacsone_gettext("Requested Contrast Agent")      => "contrastagent",
        );
        $this->table = "scheduledps";
    }
    function __destruct() { }
}

class ScheduledProtocolCodeSeq extends WorklistItemPage {
    function __construct(&$row) {
        // call base class constructor
        parent::__construct($row);
        $this->title = pacsone_gettext("Scheduled Protocol Code Sequence");
        $uid = $row["studyuid"];
        $this->url = "worklist.php?uid=$uid&page=1";
        $this->columns = array (
            pacsone_gettext("Code Vale")                     => "value",
            pacsone_gettext("Code Scheme Designator")        => "schemedesignator",
            pacsone_gettext("Code Scheme Version")           => "schemeversion",
            pacsone_gettext("Code Meaning")                  => "meaning",
        );
        $this->table = "protocolcode";
    }
    function __destruct() { }
}

class RequestedProcedure extends WorklistItemPage {
    function __construct(&$row) {
        // call base class constructor
        parent::__construct($row);
        $this->title = pacsone_gettext("Requested Procedure");
        $uid = $row["studyuid"];
        $this->url = "worklist.php?uid=$uid&page=2";
        $this->columns = array (
            pacsone_gettext("Procedure ID")                  => "id",
            pacsone_gettext("Procedure Description")         => "description",
            pacsone_gettext("Priority")                      => "priority",
        );
        $this->table = "requestedprocedure";
    }
    function __destruct() { }
}

class RequestedProcedureCodeSeq extends WorklistItemPage {
    function __construct(&$row) {
        // call base class constructor
        parent::__construct($row);
        $this->title = pacsone_gettext("Requested Procedure Code Sequence");
        $uid = $row["studyuid"];
        $this->url = "worklist.php?uid=$uid&page=3";
        $this->columns = array (
            pacsone_gettext("Code Value")                    => "value",
            pacsone_gettext("Code Scheme Designator")        => "schemedesignator",
            pacsone_gettext("Code Scheme Version")           => "schemeversion",
            pacsone_gettext("Code Meaning")                  => "meaning",
        );
        $this->table = "procedurecode";
    }
    function __destruct() { }
}

class ReferencedStudySeq extends WorklistItemPage {
    function __construct(&$row) {
        // call base class constructor
        parent::__construct($row);
        $this->title = pacsone_gettext("Referenced Study Sequence");
        $uid = $row["studyuid"];
        $this->url = "worklist.php?uid=$uid&page=4";
        $this->columns = array (
            pacsone_gettext("SOP Class UID")                 => "classuid",
            pacsone_gettext("SOP Instance UID")              => "instanceuid",
        );
        $this->table = "referencedstudy";
    }
    function __destruct() { }
}

class ReferencedPatientSeq extends WorklistItemPage {
    function __construct(&$row) {
        // call base class constructor
        parent::__construct($row);
        $this->title = pacsone_gettext("Referenced Patient Sequence");
        $uid = $row["studyuid"];
        $this->url = "worklist.php?uid=$uid&page=5";
        $this->columns = array (
            pacsone_gettext("SOP Class UID")                 => "classuid",
            pacsone_gettext("SOP Instance UID")              => "instanceuid",
        );
        $this->table = "referencedpatient";
    }
    function __destruct() { }
}

global $PRODUCT;
print "<html>";
print "<head><title>$PRODUCT - ";
print pacsone_gettext("Modality Worklist");
print "</title></head>";
print "<body>";
require_once "header.php";

if (isset($_REQUEST['uid']))
    $uid = urldecode($_REQUEST['uid']);
if (isset($_REQUEST['sort']))
    $sort = urldecode($_REQUEST['sort']);
$type = 0;  // default to Today's Worklist
if (isset($_REQUEST['type']))
	$type = $_REQUEST['type'];
$offset = 0;
if (isset($_REQUEST['offset']))
	$offset = $_REQUEST['offset'];
$all = 0;
if (isset($_REQUEST['all']))
	$all = $_REQUEST['all'];

$dbcon = new MyConnection();
$username = $dbcon->username;
$query = $dbcon->hasaccess("query", $username);
if (!$query) {
    print "<h3><font color=red>";
    print pacsone_gettext("You do not have the required privilege to view the requested information.");
    print "</font></h3>";
    exit();
}
if (!isset($uid)) {
    global $ONE_DAY;
    $query = "SELECT * FROM worklist LEFT JOIN scheduledps ON worklist.studyuid = scheduledps.studyuid LEFT JOIN procedurecode ON scheduledps.studyuid = procedurecode.studyuid";
    if ($type == 0) {           // worklist from today
        $from = date("Ymd");
        if ($dbcon->useOracle)
            $from = "TO_DATE('$from','YYYYMMDD')";
        $query .= " WHERE scheduledps.startdate >= $from";
    } else if ($type == 1) {    // worklist from yesterday
        $from = date("Ymd", time() - $ONE_DAY);
        $to = date("Ymd");
        if ($dbcon->useOracle) {
            $from = "TO_DATE('$from','YYYYMMDD')";
            $to = "TO_DATE('$to','YYYYMMDD')";
        }
        $query .= " WHERE scheduledps.startdate >= $from and scheduledps.startdate < $to";
    } else if ($type == 2) {    // worklist from this week
        $current = getdate();
        $from = date("Ymd", $current[0] - $current['wday'] * $ONE_DAY);
        if ($dbcon->useOracle)
            $from = "TO_DATE('$from','YYYYMMDD')";
        $query .= " WHERE scheduledps.startdate >= $from";
    } else if ($type == 3) {    // worklist from this month
        $current = getdate();
        $from = date("Ymd", $current[0] - ($current['mday'] - 1) * $ONE_DAY);
        if ($dbcon->useOracle)
            $from = "TO_DATE('$from','YYYYMMDD')";
        $query .= " WHERE scheduledps.startdate >= $from";
    } else if ($type == 4) {    // worklist from last month
        $current = getdate();
        $to = date("Ymd", $current[0] - ($current['mday'] - 1) * $ONE_DAY);
        $current = getdate(strtotime($to) - $ONE_DAY);
        $from = date("Ymd", $current[0] - ($current['mday'] - 1) * $ONE_DAY);
        if ($dbcon->useOracle) {
            $from = "TO_DATE('$from','YYYYMMDD')";
            $to = "TO_DATE('$to','YYYYMMDD')";
        }
        $query .= " WHERE scheduledps.startdate >= $from and scheduledps.startdate < $to";
    } else if ($type == 5)  {   // all worklist
    }
    if (isset($sort) && strlen($sort)) {
        global $WORKLIST_SORT_COLUMNS;
        if (!in_array(strtolower($sort), $WORKLIST_SORT_COLUMNS)) {
            print "<h3><font color=red>";
            printf(pacsone_gettext("Invalid Modality Worklist sort column: %s"), $sort);
            print "</font></h3>";
            exit();
        }
        $query .= " order by $sort,worklist.status desc";
        $url .= "&sort=" . urlencode($sort);
    } else
        $query .= " order by worklist.status desc";
    $pages = array(
        (new WorklistTodayPage($dbcon, $query, $offset, $all)),
        (new WorklistYesterdayPage($dbcon, $query, $offset, $all)),
        (new WorklistThisWeekPage($dbcon, $query, $offset, $all)),
        (new WorklistThisMonthPage($dbcon, $query, $offset, $all)),
        (new WorklistLastMonthPage($dbcon, $query, $offset, $all)),
        (new WorklistAllPage($dbcon, $query, $offset, $all)),
        (new WorklistEnterNewPage($dbcon, $query, $offset, $all)),
    );
    $current = $pages[$type]->title;
    $tabs = new tabs($pages, $current);
    $tabs->showHtml();
} else {
    if (!isUidValid($uid)) {
        $error = pacsone_gettext("Invalid Study Instance UID");
        print "<h2><font color=red>$error</font></h2>";
        exit();
    }
    $page = 0;
    if (isset($_REQUEST['page']))
        $page = $_REQUEST['page'];
    $query = "SELECT * FROM worklist WHERE studyuid=?";
    $bindList = array($uid);
    $result = $dbcon->preparedStmt($query, $bindList);
    $row = $result->fetch(PDO::FETCH_ASSOC);
    $pages = array(
        (new ScheduledProcedureStep($row)),
        (new ScheduledProtocolCodeSeq($row)),
        (new RequestedProcedure($row)),
        (new RequestedProcedureCodeSeq($row)),
        (new ReferencedStudySeq($row)),
        (new ReferencedPatientSeq($row)),
    );
    $current = $pages[$page]->title;
    $tabs = new tabs($pages, $current);
    $tabs->showHtml();
}

require_once 'footer.php';
print "</body>";
print "</html>";

?>
