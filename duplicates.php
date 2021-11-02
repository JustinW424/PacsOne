<?php
//
// duplicates.php
//
// Tool page for check for duplicate Patient IDs and remove them after the 
// duplicate IDs have been resolved from the source
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
require_once "tabbedpage.php";

class DuplicatePage extends TabbedPage {
	var $title;
	var $url;
	var $dbcon;

    function __construct(&$dbcon) {
        global $CUSTOMIZE_PATIENT_ID;
        $this->title = sprintf(pacsone_gettext("Check Duplicate %s"), $CUSTOMIZE_PATIENT_ID);
        $this->url = "tools.php?page=" . urlencode($this->title);
        $this->dbcon = $dbcon;
    }
    function __destruct() { }
    function showHtml() {
        global $DUPLICATE_FILTER_NONE;
        global $DUPLICATE_FILTER_THIS_WEEK;
        global $DUPLICATE_FILTER_THIS_MONTH;
        global $DUPLICATE_FILTER_THIS_YEAR;
        global $DUPLICATE_FILTER_DATE_RANGE;
        global $ONE_DAY;
        $toggle = 0;
        $sort = "cmp_received";
        if (isset($_REQUEST['sort']) && isset($_REQUEST['toggle'])) {
            $sort = $_REQUEST['sort'];
            if (isset($_SESSION['lastSort']) && !strcasecmp($sort, $_SESSION['lastSort'])
                && ($toggle != $_REQUEST['toggle'])) {
                $toggle = 1 - $toggle;
            }
        }
        $rows = array();
        $query = "SELECT * FROM patient WHERE origid LIKE '%[%]%'";
        $result = $this->dbcon->query($query);
        $filter = isset($_REQUEST['dupfilter'])? $_REQUEST['dupfilter'] : $DUPLICATE_FILTER_NONE;
        $since = getdate();
        $from = "";
        $to = "";
        switch ($filter) {
        case $DUPLICATE_FILTER_THIS_WEEK:
            $since = date("Ymd", $since[0] - $since['wday'] * $ONE_DAY);
            break;
        case $DUPLICATE_FILTER_THIS_MONTH:
            $since = date("Ymd", $since[0] - ($since['mday'] - 1) * $ONE_DAY);
            break;
        case $DUPLICATE_FILTER_THIS_YEAR:
            $since = date("Ymd", $since[0] - $since['yday'] * $ONE_DAY);
            break;
        case $DUPLICATE_FILTER_DATE_RANGE:
            $from = isset($_REQUEST['dupfrom'])? $_REQUEST['dupfrom'] : "";
            $to = isset($_REQUEST['dupto'])? $_REQUEST['dupto'] : "";
            if ($this->dbcon->isEuropeanDateFormat()) {
                $from = reverseDate($from);
                $to = reverseDate($to);
            }
            $from = strtotime($from);
            $to = strtotime($to);
            if ($to < $from)
                die("<h3><font color=red><b>TO</b> date must be equal or newer than <b>FROM</b> date!</font></h3>");
            $from = date("Ymd", $from);
            $to = date("Ymd", $to);
            break;
        case $DUPLICATE_FILTER_NONE:
        default:
            $since = "";
            break;
        }
        if ($this->dbcon->useOracle) {
            $since = "TO_DATE('$since','YYYYMMDD')";
            if (strlen($from))
                $from = "TO_DATE('$from','YYYYMMDD')";
            if (strlen($to))
                $to = "TO_DATE('$to','YYYYMMDD')";
        }
        $filterKeys = array(
            $DUPLICATE_FILTER_NONE          => $this->dbcon->useOracle? "TRUNC(received) < TRUNC(SYSDATE)" : "DATE(received) < CURDATE()",
            $DUPLICATE_FILTER_THIS_WEEK     => "received >= $since",
            $DUPLICATE_FILTER_THIS_MONTH    => "received >= $since",
            $DUPLICATE_FILTER_THIS_YEAR     => "received >= $since",
            $DUPLICATE_FILTER_DATE_RANGE    => "received >= $from AND received <= $to",
        ); 
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            // exclude those with studies received today
            $query = "SELECT * FROM study WHERE patientid='";
            $query .= $this->dbcon->escapeQuote($row["origid"]);
            $query .= "' AND ";
            $query .= $filterKeys[$filter];
            $studies = $this->dbcon->query($query);
            if ($studies && $studies->rowCount())
                $rows[] = $row;
        }
        $num_rows = sizeof($rows);
        // sort the rows
        my_usort($rows, $sort, $toggle);
        $_SESSION['lastSort'] = $sort;
        $_SESSION['sortToggle'] = $toggle;
        $url = $this->url . "&sort=$sort" . "&toggle=$toggle";
        $offset = 0;
        if (isset($_REQUEST['offset']))
        	$offset = $_REQUEST['offset'];
        $all = 0;
        if (isset($_REQUEST['all']))
        	$all = $_REQUEST['all'];
        // display total number of patient records in database
        global $CUSTOMIZE_PATIENT_ID;
        $preface = sprintf(pacsone_gettext("There are %d records with duplicate %s of an existing record."), $num_rows, $CUSTOMIZE_PATIENT_ID);
        displayPatients($rows, $preface, $url, $offset, $all, 1);
    }
}

?>
