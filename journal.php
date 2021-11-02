<?php
//
// journal.php
//
// Module for display system journal logs
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once "locale.php";
include_once 'database.php';
include_once 'security.php';
require_once 'utils.php';
include_once "tabbedpage.php";

class JournalPage extends TabbedPage {
    var $dbcon;
    var $query;
    var $sort;
    var $offset;
    var $all;

    function __construct(&$dbcon, $query, $sort, $offset, $all) {
        $this->dbcon = $dbcon;
        $this->query = $query;
        $this->sort = $sort;
        $this->offset = $offset;
        $this->all = $all;
    }
    function __destruct() { }
    function showHtml() {
        $result = $this->dbcon->query($this->query);
        if (!$result)
            die("Failed to run query: " . $this->query . ", error = " . $this->dbcon->getError());
        $rows = array();
        while ($row = $result->fetch(PDO::FETCH_ASSOC))
            $rows[] = $row;
        $toggle = 0;
        $sort = $this->sort;
        if (!strcasecmp($sort, $_SESSION['lastSort']) && isset($_REQUEST['toggle'])
            && ($toggle != $_REQUEST['toggle'])) {
            $toggle = 1 - $toggle;
        }
        my_usort($rows, $sort, $toggle);
        $_SESSION['lastSort'] = $sort;
        $_SESSION['sortToggle'] = $toggle;
        $num_rows = sizeof($rows);
        $preface = sprintf(pacsone_gettext("There are %d events logged."), $num_rows);
        $url = urlReplace($this->url, "toggle", $toggle);
        displayJournal($rows, $preface, $url, $this->offset, $this->all);
    }
}

class JournalToday extends JournalPage {
    function __construct(&$dbcon, $query, $sort, $offset, $all) {
        // call base class constructor
        parent::__construct($dbcon, $query, $sort, $offset, $all);
        $this->title = pacsone_gettext("Today's Activities");
        $this->url = "journal.php?type=0&all=" . $this->all . "&sort=" . urlencode($this->sort);
    }
    function __destruct() { }
}

class JournalYesterday extends JournalPage {
    function __construct(&$dbcon, $query, $sort, $offset, $all) {
        // call base class constructor
        parent::__construct($dbcon, $query, $sort, $offset, $all);
        $this->title = pacsone_gettext("Yesterday's Activities");
        $this->url = "journal.php?type=1&all=" . $this->all . "&sort=" . urlencode($this->sort);
    }
    function __destruct() { }
}

class JournalThisWeek extends JournalPage {
    function __construct(&$dbcon, $query, $sort, $offset, $all) {
        // call base class constructor
        parent::__construct($dbcon, $query, $sort, $offset, $all);
        $this->title = pacsone_gettext("This Week's Activities");
        $this->url = "journal.php?type=2&all=" . $this->all . "&sort=" . urlencode($this->sort);
    }
    function __destruct() { }
}

class JournalThisMonth extends JournalPage {
    function __construct(&$dbcon, $query, $sort, $offset, $all) {
        // call base class constructor
        parent::__construct($dbcon, $query, $sort, $offset, $all);
        $this->title = pacsone_gettext("This Month's Activities");
        $this->url = "journal.php?type=3&all=" . $this->all . "&sort=" . urlencode($this->sort);
    }
    function __destruct() { }
}

class JournalLastMonth extends JournalPage {
    function __construct(&$dbcon, $query, $sort, $offset, $all) {
        // call base class constructor
        parent::__construct($dbcon, $query, $sort, $offset, $all);
        $this->title = pacsone_gettext("Last Month's Activities");
        $this->url = "journal.php?type=4&all=" . $this->all . "&sort=" . urlencode($this->sort);
    }
    function __destruct() { }
}

class JournalAll extends JournalPage {
    function __construct(&$dbcon, $query, $sort, $offset, $all) {
        // call base class constructor
        parent::__construct($dbcon, $query, $sort, $offset, $all);
        $this->title = pacsone_gettext("All Activities");
        $this->url = "journal.php?type=5&all=" . $this->all . "&sort=" . urlencode($this->sort);
    }
    function __destruct() { }
}

$dbcon = new MyConnection();
$username = $dbcon->username;
$sort = "cmp_when";
if (isset($_REQUEST['sort']))
    $sort = $_REQUEST['sort'];
$type = 0;
if (isset($_REQUEST['type']))
    $type = $_REQUEST['type'];
// main
require_once 'header.php';
require_once 'display.php';
global $PRODUCT;
global $ONE_DAY;
print "<html>";
print "<head><title>$PRODUCT - " . pacsone_gettext("System Journal Logs") . "</title></head>";
print "<body>";

if (!$dbcon->hasaccess("admin", $username)) {
    print "<h2><font color=red>";
    print pacsone_gettext("You must have the Admin privilege in order to access this page");
    print "</font></h2>";
    exit();
}
$oracle = "YYYYMMDD";
if ($type == 0) {           // events from today
    $from = date("Ymd");
    $query = "SELECT * FROM journal where timestamp > $from ORDER BY timestamp ASC";
    if ($dbcon->useOracle)
        $query = "SELECT * FROM journal where timestamp > TO_DATE('$from','$oracle') ORDER BY timestamp ASC";
} else if ($type == 1) {    // events from yesterday
    $from = date("Ymd", time() - $ONE_DAY);
    $to = date("Ymd");
    $query = "SELECT * FROM journal where timestamp >= $from and timestamp < $to ORDER BY timestamp ASC";
    if ($dbcon->useOracle)
        $query = "SELECT * FROM journal where timestamp >= TO_DATE('$from','$oracle') and timestamp < TO_DATE('$to','$oracle') ORDER BY timestamp ASC";
} else if ($type == 2) {    // events from this week
    $current = getdate();
    $from = date("Ymd", $current[0] - $current['wday'] * $ONE_DAY);
    $query = "SELECT * FROM journal where timestamp > $from ORDER BY timestamp ASC";
    if ($dbcon->useOracle)
        $query = "SELECT * FROM journal where timestamp > TO_DATE('$from','$oracle') ORDER BY timestamp ASC";
} else if ($type == 3) {    // events from this month
    $current = getdate();
    $from = date("Ymd", $current[0] - ($current['mday'] - 1) * $ONE_DAY);
    $query = "SELECT * FROM journal where timestamp > $from ORDER BY timestamp ASC";
    if ($dbcon->useOracle)
        $query = "SELECT * FROM journal where timestamp > TO_DATE('$from','$oracle') ORDER BY timestamp ASC";
} else if ($type == 4) {    // events from last month
    $current = getdate();
    $to = date("Ymd", $current[0] - ($current['mday'] - 1) * $ONE_DAY);
    $current = getdate(strtotime($to) - $ONE_DAY);
    $from = date("Ymd", $current[0] - ($current['mday'] - 1) * $ONE_DAY);
    $query = "SELECT * FROM journal where timestamp >= $from and timestamp < $to ORDER BY timestamp ASC";
    if ($dbcon->useOracle)
        $query = "SELECT * FROM journal where timestamp >= TO_DATE('$from','$oracle') and timestamp < TO_DATE('$to','$oracle') ORDER BY timestamp ASC";
} else if ($type == 5) {    // all events
    $query = "SELECT * FROM journal ORDER BY timestamp ASC";
}
$offset = 0;
if (isset($_REQUEST['offset']))
	$offset = $_REQUEST['offset'];
$all = 0;
if (isset($_REQUEST['all']))
	$all = $_REQUEST['all'];
$pages = array(
    (new JournalToday($dbcon, $query, $sort, $offset, $all)),
    (new JournalYesterday($dbcon, $query, $sort, $offset, $all)),
    (new JournalThisWeek($dbcon, $query, $sort, $offset, $all)),
    (new JournalThisMonth($dbcon, $query, $sort, $offset, $all)),
    (new JournalLastMonth($dbcon, $query, $sort, $offset, $all)),
    (new JournalAll($dbcon, $query, $sort, $offset, $all)),
);
$current = $pages[$type]->title;
$tabs = new tabs($pages, $current);
$tabs->showHtml();

require_once 'footer.php';
print "</body>";
print "</html>";

?>
