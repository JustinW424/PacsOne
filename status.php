<?php
//
// status.php
//
// Module for retrieving database job status
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once 'security.php';
include_once 'database.php';
include_once "tabbedpage.php";

$dbcon = new MyConnection();
$username = $dbcon->username;

// job queries and job counts
$JOBQUERY_TBL = array(
    0   => array("SELECT COUNT(*) FROM dbjob where status='success' AND username!='$username' AND username NOT LIKE '\_%'", 0),
    1   => array("SELECT COUNT(*) FROM dbjob where status='processing' AND username!='$username' AND username NOT LIKE '\_%'", 0),
    2   => array("SELECT COUNT(*) FROM dbjob where status='failed' AND username!='$username' AND username NOT LIKE '\_%'", 0),
    3   => array("SELECT COUNT(*) FROM dbjob where status='submitted' AND username!='$username' AND username NOT LIKE '\_%' AND schedule=-1", 0),
    4   => array("SELECT COUNT(*) FROM dbjob where status='submitted' AND username!='$username' AND username NOT LIKE '\_%' AND schedule!=-1", 0),
    5   => array("SELECT COUNT(*) FROM dbjob where status='warning' AND username!='$username' AND username NOT LIKE '\_%'", 0),
);

function GetJobCounters(&$dbcon)
{
    global $JOBQUERY_TBL;
    // run job queries for each category
    foreach ($JOBQUERY_TBL as $key => $tab) {
        $query = $tab[0];
        $job = $dbcon->query($query);
        if ($job && ($count = $job->fetch(PDO::FETCH_NUM))) {
            $JOBQUERY_TBL[$key][1] = $count[0];
        }
    }
}

class JobStatusPage extends TabbedPage {
    var $result;
    var $preface;
    var $offset;
    var $all;
    var $status;
    var $type;
    var $order;

    function __construct(&$result, $preface, $offset, $all, $order) {
        global $JOBQUERY_TBL;
        $this->result = $result;
        $this->preface = $preface;
        $this->offset = $offset;
        $this->all = $all;
        $this->order = $order;
    }
    function __destruct() { }
    function showHtml() {
        print "<br><table width=100% border=0 cellpadding=5 cellspacing=0>\n";
        // table headers
        print "<tr><td>\n";
        displayJobStatus($this->result, $this->preface, $this->status, $this->type, $this->url, $this->offset, $this->all);
        print "</td></tr></table>";
    }
}

class CompletedJob extends JobStatusPage {
    function __construct(&$result, $preface, $offset, $all, $order) {
        global $JOBQUERY_TBL;
        // call base class constructor
        parent::__construct($result, $preface, $offset, $all, $order);
        $this->status = "success";
        $this->type = 0;
        $this->title = $JOBQUERY_TBL[$this->type][1] . " " . pacsone_gettext("Completed");
        $this->url = "status.php?type=" . $this->type . "&order=" . $this->order;
    }
    function __destruct() { }
}

class ProcessingJob extends JobStatusPage {
    function __construct(&$result, $preface, $offset, $all, $order) {
        global $JOBQUERY_TBL;
        // call base class constructor
        parent::__construct($result, $preface, $offset, $all, $order);
        $this->status = "processing";
        $this->type = 1;
        $this->title = $JOBQUERY_TBL[$this->type][1] . " " . pacsone_gettext("Pending");
        $this->url = "status.php?type=" . $this->type . "&order=" . $this->order;
    }
    function __destruct() { }
}

class FailedJob extends JobStatusPage {
    function __construct(&$result, $preface, $offset, $all, $order) {
        global $JOBQUERY_TBL;
        // call base class constructor
        parent::__construct($result, $preface, $offset, $all, $order);
        $this->status = "failed";
        $this->type = 2;
        $this->title = $JOBQUERY_TBL[$this->type][1] . " " . pacsone_gettext("Failed");
        $this->url = "status.php?type=" . $this->type . "&order=" . $this->order;
    }
    function __destruct() { }
}

class ScheduledNowJob extends JobStatusPage {
    function __construct(&$result, $preface, $offset, $all, $order) {
        global $JOBQUERY_TBL;
        // call base class constructor
        parent::__construct($result, $preface, $offset, $all, $order);
        $this->status = "submitted";
        $this->type = 3;
        $this->title = $JOBQUERY_TBL[$this->type][1] . " " . pacsone_gettext("Scheduled Immediately");
        $this->url = "status.php?type=" . $this->type . "&order=" . $this->order;
    }
    function __destruct() { }
}

class ScheduledLaterJob extends JobStatusPage {
    function __construct(&$result, $preface, $offset, $all, $order) {
        global $JOBQUERY_TBL;
        // call base class constructor
        parent::__construct($result, $preface, $offset, $all, $order);
        $this->status = "submitted";
        $this->type = 4;
        $this->title = $JOBQUERY_TBL[$this->type][1] . " " . pacsone_gettext("Scheduled Later");
        $this->url = "status.php?type=" . $this->type . "&order=" . $this->order;
    }
    function __destruct() { }
}

class WarningJob extends JobStatusPage {
    function __construct(&$result, $preface, $offset, $all, $order) {
        global $JOBQUERY_TBL;
        // call base class constructor
        parent::__construct($result, $preface, $offset, $all, $order);
        $this->status = "warning";
        $this->type = 5;
        $this->title = $JOBQUERY_TBL[$this->type][1] . " " . pacsone_gettext("Warning");
        $this->url = "status.php?type=" . $this->type . "&order=" . $this->order;
    }
    function __destruct() { }
}

if (isset($_POST['action']))
	$action = $_POST['action'];
if (isset($_POST['actionvalue']))
	$action = $_POST['actionvalue'];
$type = 0;  // default to Completed Jobs view

//print "status.php action ". $action. "\n";

if (isset($_REQUEST['type']))
	$type = $_REQUEST['type'];
if (isset($action) && strcasecmp($action, "Retry") == 0) {
	$entry = $_POST['entry'];
    $now = $dbcon->useOracle? "SYSDATE" : "NOW()";
	foreach ($entry as $jobid) {
        $query = "UPDATE dbjob SET submittime=$now,starttime=NULL,finishtime=NULL,";
        // retry only forwarding jobs
		$query .= "retries=retries+1,status='submitted' WHERE id=? and type='forward'";
        $bindList = array($jobid);
		$dbcon->preparedStmt($query, $bindList);
	}
	header("Location: status.php?type=3");
}
else if (isset($action) && strcasecmp($action, "Delete") == 0) {
	$entry = $_POST['entry'];
	foreach ($entry as $jobid) {
        // if this is an export job, also delete corresponding rows in the Export table
        $query = "SELECT type FROM dbjob WHERE id=?";
        $bindList = array($jobid);
        $result = $dbcon->preparedStmt($query, $bindList);
        $row = $result->fetch(PDO::FETCH_NUM);
        if ((strcasecmp($row[0], "Export") == 0) ||
            (strcasecmp($row[0], "ExportZ") == 0)) {
		    $query = "DELETE FROM export WHERE jobid=?";
		    $dbcon->preparedStmt($query, $bindList);
        }
        if (stristr($row[0], "ExportZ")) {
            // purge the download zip file created for exported content
		    $query = "SELECT path FROM download WHERE id=?";
            $result = $dbcon->preparedStmt($query, $bindList);
		    if ($result) {
                $path = $result->fetchColumn();
                if (file_exists($path))
                    unlink($path);
                // delete the entry itself
                $dbcon->preparedStmt("DELETE FROM download WHERE id=?", $bindList);
            }
        }
        // delete the job itself
		$query = "DELETE FROM dbjob WHERE id=?";
		$dbcon->preparedStmt($query, $bindList);
	}
	header("Location: status.php?type=$type");
}
else if (isset($action) && strcasecmp($action, "Run Immediately") == 0) {
	$entry = $_POST['entry'];
	foreach ($entry as $jobid) {
        // change the job to run immediately
        $query = "update dbjob set schedule=-1 WHERE id=?";
        $bindList = array($jobid);
		$dbcon->preparedStmt($query, $bindList);
	}
	header("Location: status.php?type=3");
}
else {
// main
require_once 'header.php';
require_once 'display.php';
global $PRODUCT;
print "<html>";
print "<head><title>$PRODUCT - ";
print pacsone_gettext("Database Job Status");
print "</title></head>";
print "<body>";

$offset = 0;
if (isset($_REQUEST['offset']))
	$offset = $_REQUEST['offset'];
$all = 0;
if (isset($_REQUEST['all']))
	$all = $_REQUEST['all'];
print "<p><b><u>". pacsone_gettext("My Submitted Jobs:") . "</u></b><br>\n";
// display completed jobs
$bindList = array($username);
$result = $dbcon->preparedStmt("SELECT * FROM dbjob where username=? and status='success' order by id asc", $bindList);
$count = $result->rowCount();
$preface = sprintf(pacsone_gettext("%d completed jobs. Completed jobs will be deleted after a period of 24 hours"), $count);
$url = "status.php?type=0";
displayJobStatus($result, $preface, "success", -1, $url, $offset, $all);
// display pending, failed or submitted jobs
$result = $dbcon->preparedStmt("SELECT * FROM dbjob where username=? and status!='success' ORDER BY id asc", $bindList);
$count = $result->rowCount();
$preface = sprintf(pacsone_gettext("%d pending or failed jobs. Failed jobs will be deleted after a period of 24 hours"), $count);
$url = "status.php?type=123";
displayJobStatus($result, $preface, "failed", -1, $url, $offset, $all);
if ($dbcon->hasaccess("admin", $username)) {

    //print "admin access\n";

    $order = "username";
    if (isset($_REQUEST["order"]) && strlen($_REQUEST["order"]))
        $order = $_REQUEST["order"];
    print "<p><b><u>" . pacsone_gettext("Other User's Jobs:") . "</u></b><br>\n";
    $url = "status.php?type=$type";
	if ($type == 3) {           // display immediately scheduled jobs from other users
	    $result = $dbcon->preparedStmt("SELECT * FROM dbjob where status='submitted' AND username!=? AND username NOT LIKE '\_%' AND schedule=-1 ORDER BY $order", $bindList);
	    $count = $result->rowCount();
	    $preface = sprintf(pacsone_gettext("%d submitted jobs to run immediately."), $count);
    } else if ($type == 1) {    // display all pending jobs from other users
	    $result = $dbcon->preparedStmt("SELECT * FROM dbjob where status='processing' AND username!=? AND username NOT LIKE '\_%' ORDER BY $order", $bindList);
	    $count = $result->rowCount();
	    $preface = sprintf(pacsone_gettext("%d pending jobs."), $count);
    } else if ($type == 2) {    // display all failed jobs from other users
	    $result = $dbcon->preparedStmt("SELECT * FROM dbjob where status='failed' AND username!=? AND username NOT LIKE '\_%' ORDER BY $order", $bindList);
	    $count = $result->rowCount();
	    $preface = sprintf(pacsone_gettext("%d failed jobs. Failed jobs will be deleted after a period of 24 hours"), $count);
    } else if ($type == 0) {    // display all completed jobs from other users
	    $result = $dbcon->preparedStmt("SELECT * FROM dbjob where status='success' AND username!=? AND username NOT LIKE '\_%' ORDER BY $order", $bindList);
	    $count = $result->rowCount();
	    $preface = sprintf(pacsone_gettext("%d completed jobs. Completed jobs will be deleted after a period of 24 hours"), $count);
    } else if ($type == 4) {    // display later scheduled jobs from other users
	    $result = $dbcon->preparedStmt("SELECT * FROM dbjob where status='submitted' AND username!=? AND username NOT LIKE '\_%' AND schedule!=-1 ORDER BY $order", $bindList);
	    $count = $result->rowCount();
	    $preface = sprintf(pacsone_gettext("%d submitted jobs to run at a later schedule."), $count);
    } else if ($type == 5) {    // display all warning jobs from other users
	    $result = $dbcon->preparedStmt("SELECT * FROM dbjob where status='warning' AND username!=? AND username NOT LIKE '\_%' ORDER BY $order", $bindList);
	    $count = $result->rowCount();
	    $preface = sprintf(pacsone_gettext("%d warning jobs. Completed jobs will be deleted after a period of 24 hours"), $count);
    } else {
        $type = 0;
    }
    GetJobCounters($dbcon);
    $pages = array(
        (new CompletedJob($result, $preface, $offset, $all, $order)),
        (new ProcessingJob($result, $preface, $offset, $all, $order)),
        (new FailedJob($result, $preface, $offset, $all, $order)),
        (new ScheduledNowJob($result, $preface, $offset, $all, $order)),
        (new ScheduledLaterJob($result, $preface, $offset, $all, $order)),
        (new WarningJob($result, $preface, $offset, $all, $order)),
    );
    $current = $pages[$type]->title;
    $tabs = new tabs($pages, $current);
    $tabs->showHtml();
}

require_once 'footer.php';
print "</body>";
print "</html>";
}
?>
