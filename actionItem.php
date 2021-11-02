<?php
//
// actionItem.php
//
// Module for processing entries from database tables
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();
ob_start();
// disable PHP timeout
set_time_limit(0);

require_once 'locale.php';
include_once 'database.php';
include_once 'sharedData.php';

global $PRODUCT;
global $STUDY_STATUS_READ;
global $STUDY_STATUS_DEFAULT;
global $CUSTOMIZE_PATIENT;
$dbcon = new MyConnection();
$username = $dbcon->username;
$action = $_POST['action'];
if (isset($_POST['actionvalue']))
    $action = $_POST['actionvalue'];
$option = $_POST['option'];
if (stristr($action, "Clear Filters") || stristr($action, "Apply Filters")) {
    // clear all study filters defined by this user
    $query = "delete from studyfilter where username=?";
    $bindList = array($username);
    $dbcon->preparedStmt($query, $bindList);
    if (stristr($action, "Apply")) {
        global $STUDY_FILTER_STUDYDATE_LAST_N_DAYS;
        global $STUDY_FILTER_STUDYDATE_MASK_BITS;
        global $STUDY_FILTER_BY_REFERRING_DOC;
        global $STUDY_FILTER_BY_READING_DOC;
        global $STUDY_FILTER_BY_DATE_RECEIVED;
        global $STUDY_FILTER_STUDYDATE_FROM_TO;
        $eurodate = $dbcon->isEuropeanDateFormat();
        // apply all study filters defined by this user
        $values = array();
        $values['username'] = $username;
        $values['status'] = $_POST['studyStatus'];
        $value = $_POST['studyDate'];
        if ($value == $STUDY_FILTER_STUDYDATE_LAST_N_DAYS)
            $value = ($_POST['filterNdays'] << $STUDY_FILTER_STUDYDATE_MASK_BITS) | $value;
        $values['studydate'] = $value;
        if ($value == $STUDY_FILTER_STUDYDATE_FROM_TO) {
            if (strlen($_POST['studyDateFrom'])) {
                $value = $eurodate? reverseDate($_POST['studyDateFrom']) : $_POST['studyDateFrom'];
                $values['datefrom'] = $value;
            }
            if (strlen($_POST['studyDateTo'])) {
                $value = $eurodate? reverseDate($_POST['studyDateTo']) : $_POST['studyDateTo'];
                $values['dateto'] = $value;
            }
        }
        $entries = isset($_POST['filterBy'])? $_POST['filterBy'] : array();
        $filterby = 0;
        foreach ($entries as $entry) {
            switch ($entry) {
            case $STUDY_FILTER_BY_REFERRING_DOC:
                if (strlen($_POST['referdoc']))
                    $values['referdoc'] = $_POST['referdoc'];
                $filterby |= $entry;
                break;
            case $STUDY_FILTER_BY_READING_DOC:
                if (strlen($_POST['readdoc']))
                    $values['readdoc'] = $_POST['readdoc'];
                $filterby |= $entry;
                break;
            case $STUDY_FILTER_BY_DATE_RECEIVED:
                if (strlen($_POST['receivedfrom'])) {
                    $value = $eurodate? reverseDate($_POST['receivedfrom']) : $_POST['receivedfrom'];
                    $values['receivedfrom'] = $value;
                }
                if (strlen($_POST['receivedto'])) {
                    $value = $eurodate? reverseDate($_POST['receivedto']) : $_POST['receivedto'];
                    $values['receivedto'] = $value;
                }
                $filterby |= $entry;
                break;
            default:
                break;
            }
        }
        if ($filterby)
            $values['filterby'] = $filterby;
        $values['showsettings'] = $_POST['showsettings'];
        // build the SQL query
        $columns = "";
        $data = "";
        $bindList = array();
        foreach ($values as $column => $value) {
            if (strlen($columns))
                $columns .= ",";
            $columns .= $column;
            if (strlen($data))
                $data .= ",";
            $data .= "?";
            $bindList[] = $value;
        }
        $query = "insert into studyfilter ($columns) values($data)";
        if (!$dbcon->preparedStmt($query, $bindList)) {
            print "<h2><font color=red>";
            die("Failed to run query: [$query], error = " . $dbcon->getError());
        }
    }
    // back to the original page
    $url = $_SERVER["HTTP_REFERER"];
    ob_end_clean();
    header("Location: $url");
    exit;
} else if (stristr($action, "Apply Duplicate Patient ID Filter")) {
    global $CUSTOMIZE_PATIENT_ID;
    global $DUPLICATE_FILTER_NONE;
    global $DUPLICATE_FILTER_THIS_WEEK;
    global $DUPLICATE_FILTER_THIS_MONTH;
    global $DUPLICATE_FILTER_THIS_YEAR;
    global $DUPLICATE_FILTER_DATE_RANGE;
    // back to the Tools->Check Duplicate Patient ID page
    $title = sprintf(pacsone_gettext("Check Duplicate %s"), $CUSTOMIZE_PATIENT_ID);
    $url = "tools.php?page=" . urlencode($title);
    if (isset($_POST['dupfilter'])) {
        $url .= "&dupfilter=" . $_POST['dupfilter'];
        if ($_POST['dupfilter'] == $DUPLICATE_FILTER_DATE_RANGE) {
            if (isset($_POST['dupfrom']))
                $url .= "&dupfrom=" . urlencode($_POST['dupfrom']);
            if (isset($_POST['dupto']))
                $url .= "&dupto=" . urlencode($_POST['dupto']);
        }
    }
    ob_end_clean();
    header("Location: $url");
    exit;
}
$entry = $_POST['entry'];
if (!isset($entry) || count($entry) == 0) {
    ob_end_flush();
    print "<h3><font color=red>" . pacsone_gettext("No item has been selected.") . "</font></h3>\n";
    exit();
}
$url = "home.php";
if (!strcasecmp($action, "Tag") || !strcasecmp($action, "Un-Tag")) {
    if (isset($option) && isset($entry) && !strcasecmp($option, "Image")) {
        $value = strcasecmp($action, "Tag")? 0 : 1;
        foreach ($entry as $uid) {
            $query = "update image set tagged=? where uuid=?";
            $bindList = array($value, $uid);
            $dbcon->preparedStmt($query, $bindList);
        }
        // back to the Image Thumbnail page
        $query = "select seriesuid from image where uuid=?";
        $bindList = array($entry[0]);
        $result = $dbcon->preparedStmt($query, $bindList);
        $seriesUid = $result->fetchColumn();
        $result = $dbcon->query("select studyuid from series where uuid='$seriesUid'");
        $studyUid = $result->fetchColumn();
        $patientId = $dbcon->getPatientIdByStudyUid($studyUid);
        $url = "image.php?patientId=" . urlencode($patientId) . "&studyId=$studyUid&seriesId=$seriesUid";
        ob_end_clean();
        header("Location: $url");
        exit;
    }
}
if (strcasecmp($action, "Delete") == 0 && isset($option)) {
    // log activity to system journal
    foreach ($entry as $uid) {
        $uid = urldecode($uid);
        $what = strcasecmp($option, "Patient")? pacsone_gettext($option) : $CUSTOMIZE_PATIENT;
        $dbcon->logJournal($username, $action, $what, $uid);
    }
    if (strcasecmp($option, "Image") == 0) {
	    $url = deleteImages($entry);
    }
    else if (strcasecmp($option, "Series") == 0) {
	    $url = deleteSeries($entry);
    }
    else if (strcasecmp($option, "Study") == 0) {
	    $url = deleteStudies($entry);
    }
    else if (strcasecmp($option, "Patient") == 0) {
	    $url = deletePatients($entry);
    }
    else if (strcasecmp($option, "Worklist") == 0) {
	    $url = deleteWorklists($entry);
        ob_end_clean();
        header("Location: " . $url);
        exit();
    }
    // go back to the page
    $json = array(
        "url"   => $url,
    );
    header('Content-Type: application/json');
    echo json_encode($json);
    exit();
}
if (strcasecmp($action, "Forward") == 0 && isset($option)) {
    ob_end_flush();
	// display destination AE form
	print "<html>\n";
	print "<head><title>$PRODUCT - ";
    printf(pacsone_gettext("Forwarding %s"), strcasecmp($option, "Patient")? pacsone_gettext($option) : $CUSTOMIZE_PATIENT);
    print "</title></head>\n";
	print "<body>\n";
	require_once 'header.php';
	$result = $dbcon->query("select title,ipaddr,description from applentity where port is not NULL order by title asc");
	if ($result->rowCount() == 0) {
		print "<h3><font color=red>";
        print pacsone_gettext("There is no valid AE to forward to.");
        print "</font></h3>";
	}
	else {
        global $HOUR_TBL;
        $remote = $_SERVER["REMOTE_ADDR"];
        print "<form method='POST' action='forward.php'>\n";
		print "<table>\n";
		print "<tr><td>";
        printf(pacsone_gettext("Forward the following %s"), strcasecmp($option, "Patient")? pacsone_gettext($option) : $CUSTOMIZE_PATIENT);
        print ":</td></tr>\n";
		print "<tr><td><br></td></tr>\n";
		foreach ($entry as $uid) {
            if (strcasecmp($option, "Patient") == 0)
                $uid = urldecode($uid);
			print "<tr><td>$uid</td></tr>\n";
		}
		print "<tr><td><br></td></tr>\n";
		print "<tr><td>";
        print pacsone_gettext("Please select which Application Entity to forward to:");
        print "</td>";
		print "<td><select name='aetitle'>";
		while ($row = $result->fetch(PDO::FETCH_NUM)) {
            if (strcmp($row[1], $remote) == 0)
			    $value = "<option selected>";
            else
			    $value = "<option>";
            $value .= $row[0];
            if (strlen($row[2]))
                $value .= " - " . $row[2];
            print $value;
		}
		print "</select></td></tr>\n";
        print "<tr><td>";
        print pacsone_gettext("Please select forwarding schedule:");
        print "</td>";
        print "<td><input type='radio' name='schedule' value=-1 checked>";
        print pacsone_gettext("Immediately") . "<br>\n";
	    print "<input type='radio' name='schedule' value=0>";
        print pacsone_gettext("At this hour: \n");
	    print "<select name='hour'>\n";
	    foreach ($HOUR_TBL as $key => $value) {
            if ($value == 24)
                break;
            $item = ($value == 0)? "<option selected>" : "<option>";
		    print "$item$key</option>";
        }
	    print "</select>\n";
        print "</td></tr>";
        print "<tr><td>";
        print pacsone_gettext("Select Source AE Title:");
        print "</td>";
        print "<td><input type='radio' name='useraet' value=0 checked>";
        printf(pacsone_gettext("Use Current AE Title of %s"), $PRODUCT);
	    print "<br><input type='radio' name='useraet' value=1>";
        print pacsone_gettext("Use This AE Title:");
        print "&nbsp;<input type='text' name='sourceaet' size=32 maxlength=64>";
        print "<br><input type='radio' name='useraet' value=2>";
        printf(pacsone_gettext("Use the Source AE Title when %s was received originally"), $option);
        print "</td></tr>";
        print "</table>\n";
		print "<input type=hidden name='option' value=$option>\n";
		foreach ($entry as $uid) {
			print "<input type=hidden name='entry[]' value='$uid'>\n";
		}
        print "<p><input type='submit' value='" . pacsone_gettext("Forward") . "'>\n";
        print "</form>\n";
	}
	require_once 'footer.php';
	print "</body>\n";
	print "</html>\n";
}
if ((!strcasecmp($action, "Show") || stristr($action, "Download")) && isset($option)) {
    if (strcasecmp($action, "Show") == 0) {
	    include_once 'applet.php';
        $charset = $dbcon->getBrowserCharset();
    } else if (stristr($action, "Download")) {
	    include_once 'download.php';
        $filename = $dbcon->downloadFilenamePrefix($option, $entry);
        $filename .= count($entry) . "-" . $option;
    }
	$uids = array();
    $studies = array();
    if (strcasecmp($option, "Image") == 0) {
	    foreach ($entry as $uid) {
            // log activity to system journal
            $dbcon->logJournal($username, $action, $option, $uid);
		    $uids[] = $uid;
            if (!strcasecmp($action, "Show")) {
                unset($study);
                unset($series);
                $study = array(
                    "patientId"         => "",
                    "patientName"       => "",
                    "studyId"           => "",
                    "studyDate"         => "",
                    "studyDescription"  => "",
                    "seriesList"        => array(),
                    "modality"          => "",
                    "numImages"         => 0,
                );
                $query = "select seriesuid from image where uuid=?";
                $bindList = array($uid);
                $result = $dbcon->preparedStmt($query, $bindList);
                $seriesUid = $result->fetchColumn();
                $series = array( "instanceList" => array() );
                $studyUid = "";
                $seriesRes = $dbcon->query("select * from series where uuid='$seriesUid'");
                $found = false;
                if ($seriesRes && $seriesRow = $seriesRes->fetch(PDO::FETCH_ASSOC)) {
                    $studyUid = $seriesRow["studyuid"];
                    if (array_key_exists($studyUid, $studies))
                        $study = &$studies[$studyUid];
                    if (array_key_exists($seriesUid, $study["seriesList"])) {
                        $series = &$study["seriesList"][$seriesUid];
                        $found = true;
                    }
                    $modality = $seriesRow["modality"];
                    // use the Modality information from the 1st series of this study
                    if (!strlen($study["modality"]) && strlen($modality))
                        $study["modality"] = $modality;
                    $series["seriesNumber"] = $seriesRow["seriesnumber"];
                    $series["seriesDescription"] = pacsone_gettext("Series ") . $series["seriesNumber"];
                    if (strlen($seriesRow["description"]))
                       $series["seriesDescription"] .= " - " . $seriesRow["description"];
                }
                // get the WADO URL for this instance
                $instance = array(
                    "imageId" => $dbcon->getWADOUrl($uid, $seriesUid),
                );
                // add this instance to this series
                $series["instanceList"][] = $instance;
                $study["numImages"]++;
                // add this series to this study
                if (!$found)
                    $study["seriesList"][$seriesUid] = $series;
                // find the list of studies which contain these selected images
                if (strlen($studyUid)) {
                    // only run query below for 1st instance of the study
                    if (!array_key_exists($studyUid, $studies)) {
                        $query = "select * from study where uuid=?";
                        $bindList = array($studyUid);
                        $result = $dbcon->preparedStmt($query, $bindList);
                        if ($result && $studyRow = $result->fetch(PDO::FETCH_ASSOC)) {
                            $study["patientId"] = $studyRow["patientid"];
                            $name = $dbcon->getPatientName($studyRow["patientid"]);
                            if (strlen($charset))
                                $name = mb_convert_encoding($name, 'UTF-8', $charset);
                            $study["patientName"] = $name;
                            $study["studyId"] = $studyRow["id"];
                            $study["studyDate"] = $studyRow["studydate"];
                            $study["studyDescription"] = $studyRow["description"];
                        }
                    }
                    $studies[$studyUid] = $study;
                }
                print "<p>";
            }
	    }
        if (!strcasecmp($action, "Show")) {
            // remove the Series UID key from the series list of each study since
            // the CornerStone viewer expects a simple array with no key
            foreach ($studies as &$study) {
                $seriesList = array();
                foreach ($study["seriesList"] as $uuid => $series)
                    $seriesList[] = $series;
                unset($study["seriesList"]);
                $study["seriesList"] = $seriesList;
            }
        }
    } else if (strcasecmp($option, "Series") == 0) {
	    foreach ($entry as $seriesUid) {
            unset($study);
            unset($series);
            // log activity to system journal
            $dbcon->logJournal($username, $action, $option, $seriesUid);
            $study = array(
                "patientId"         => "",
                "patientName"       => "",
                "studyId"           => "",
                "studyDate"         => "",
                "studyDescription"  => "",
                "seriesList"        => array(),
                "modality"          => "",
                "numImages"         => 0,
            );
            $series = array( "instanceList" => array() );
            $studyUid = "";
            $query = "select * from series where uuid=?";
            $bindList = array($seriesUid);
            $seriesRes = $dbcon->preparedStmt($query, $bindList);
            if ($seriesRes && $seriesRow = $seriesRes->fetch(PDO::FETCH_ASSOC)) {
                $studyUid = $seriesRow["studyuid"];
                if (array_key_exists($studyUid, $studies))
                    $study = &$studies[$studyUid];
                $seriesUid = $seriesRow["uuid"];
                $modality = $seriesRow["modality"];
                // use the Modality information from the 1st series of this study
                if (!strlen($study["modality"]) && strlen($modality))
                    $study["modality"] = $modality;
                $series["seriesNumber"] = $seriesRow["seriesnumber"];
                $series["seriesDescription"] = pacsone_gettext("Series ") . $series["seriesNumber"];
                if (strlen($seriesRow["description"]))
                   $series["seriesDescription"] .= " - " . $seriesRow["description"];
            }
            $query = "select uuid from image where seriesuid=? order by instance ASC";
            $bindList = array($seriesUid);
            $result = $dbcon->preparedStmt($query, $bindList);
            while ($row = $result->fetch(PDO::FETCH_NUM)) {
                $uids[] = $row[0];
                // get the WADO URL for this instance
                $instance = array(
                    "imageId" => $dbcon->getWADOUrl($row[0], $seriesUid),
                );
                // add this instance to this series
                $series["instanceList"][] = $instance;
                $study["numImages"]++;
            }
            if (!strcasecmp($action, "Show")) {
                // add this series to this study
                $study["seriesList"][] = $series;
                // find the list of studies which contain these selected series
                if (strlen($studyUid)) {
                    // only run query below for 1st series of the study
                    if (!array_key_exists($studyUid, $studies)) {
                        $query = "select * from study where uuid=?";
                        $bindList = array($studyUid);
                        $result = $dbcon->preparedStmt($query, $bindList);
                        if ($result && $studyRow = $result->fetch(PDO::FETCH_ASSOC)) {
                            $study["patientId"] = $studyRow["patientid"];
                            $name = $dbcon->getPatientName($studyRow["patientid"]);
                            if (strlen($charset))
                                $name = mb_convert_encoding($name, 'UTF-8', $charset);
                            $study["patientName"] = $name;
                            $study["studyId"] = $studyRow["id"];
                            $study["studyDate"] = $studyRow["studydate"];
                            $study["studyDescription"] = $studyRow["description"];
                        }
                    }
                    $studies[$studyUid] = $study;
                }
            }
	    }
    } else if (strcasecmp($option, "Study") == 0) {
		foreach ($entry as $uid)
		{
            $numImages = 0;
            // log activity to system journal
            $dbcon->logJournal($username, $action, $option, $uid);
            if (strcasecmp($action, "Show") == 0) {
                // mark study as 'reviewed'
                $query = "UPDATE study SET reviewed=? WHERE uuid=?";
                $bindList = array($username, $uid);
                $dbcon->preparedStmt($query, $bindList);
                $query = "UPDATE worklist SET status=$STUDY_STATUS_READ WHERE studyuid=?";
                $bindList = array($uid);
                $dbcon->preparedStmt($query, $bindList);
            }
            $study = array();
            $query = "select * from study where uuid=?";
            $bindList = array($uid);
            $result = $dbcon->preparedStmt($query, $bindList);
            if ($result && $studyRow = $result->fetch(PDO::FETCH_ASSOC)) {
                $study["patientId"] = $studyRow["patientid"];
                $name = $dbcon->getPatientName($studyRow["patientid"]);
                if (strlen($charset))
                    $name = mb_convert_encoding($name, 'UTF-8', $charset);
                $study["patientName"] = $name;
                $study["studyId"] = $studyRow["id"];
                $study["studyDate"] = $studyRow["studydate"];
                $study["studyDescription"] = $studyRow["description"];
                $study["seriesList"] = array();
                $study["modality"] = "";
                $query = "select * from series where studyuid=?";
                $bindList = array($uid);
                $seriesRes = $dbcon->preparedStmt($query, $bindList);
                // find all series of this study
                while ($seriesRow = $seriesRes->fetch(PDO::FETCH_ASSOC)) {
                    $seriesUid = $seriesRow["uuid"];
                    $modality = $seriesRow["modality"];
                    // use the Modality information from the 1st series of this study
                    if (!strlen($study["modality"]) && strlen($modality))
                        $study["modality"] = $modality;
                    $series = array(
                        "seriesNumber"      => $seriesRow["seriesnumber"],
                        "instanceList"      => array(),
                    );
                    $series["seriesDescription"] = pacsone_gettext("Series ") . $series["seriesNumber"];
                    if (strlen($seriesRow["description"]))
                       $series["seriesDescription"] .= " - " . $seriesRow["description"];
                    // find all instances of this series
                    $imageRes = $dbcon->query("select uuid from image where seriesuid='$seriesUid'");
                    while ($imageRow = $imageRes->fetch(PDO::FETCH_NUM)) {
                        $instanceUid = $imageRow[0];
                        // add this instance to the global instance list
                        $uids[] = $instanceUid;
                        // get the WADO URL for this instance
                        $instance = array(
                            "imageId" => $dbcon->getWADOUrl($instanceUid, $seriesUid),
                        );
                        // add this instance to this series
                        $series["instanceList"][] = $instance;
                        $numImages++;
                    }
                    // add this series to this study
                    $study["seriesList"][] = $series;
                }
                $study["numImages"] = $numImages;
                $studies[$uid] = $study;
            }
        }
    } else if (strcasecmp($option, "Patient") == 0) {
		foreach ($entry as $pid) {
            $pid = urldecode($pid);
            // log activity to system journal
            $dbcon->logJournal($username, $action, $CUSTOMIZE_PATIENT, $pid);
            // find the list of studies which contain these selected patients
            $query = "select * from study where patientid=?";
            $bindList = array($pid);
            $result = $dbcon->preparedStmt($query, $bindList);
            while ($studyRow = $result->fetch(PDO::FETCH_ASSOC)) {
                $uid = $studyRow["uuid"];
                if (!strcasecmp($action, "Show")) {
                    // mark study as 'reviewed'
                    $query = "UPDATE study SET reviewed=? WHERE uuid=?";
                    $bindList = array($username, $uid);
                    $dbcon->preparedStmt($query, $bindList);
                    $query = "UPDATE worklist SET status=$STUDY_STATUS_READ WHERE studyuid=?";
                    $bindList = array($uid);
                    $dbcon->preparedStmt($query, $bindList);
                }
                $numImages = 0;
                $study = array(
                    "patientId"         => $pid,
                    "patientName"       => $dbcon->getPatientName($pid),
                    "studyId"           => $studyRow["id"],
                    "studyDate"         => $studyRow["studydate"],
                    "studyDescription"  => $studyRow["description"],
                    "seriesList"        => array(),
                    "modality"          => "",
                );
                if (strlen($charset)) {
                    $name = mb_convert_encoding($study["patientName"], 'UTF-8', $charset);
                    $study["patientName"] = $name;
                }
                $seriesRes = $dbcon->query("select * from series where studyuid='$uid'");
                // find all series of this study
                while ($seriesRow = $seriesRes->fetch(PDO::FETCH_ASSOC)) {
                    $seriesUid = $seriesRow["uuid"];
                    $modality = $seriesRow["modality"];
                    // use the Modality information from the 1st series of this study
                    if (!strlen($study["modality"]) && strlen($modality))
                        $study["modality"] = $modality;
                    $series = array(
                        "seriesNumber"      => $seriesRow["seriesnumber"],
                        "instanceList"      => array(),
                    );
                    $series["seriesDescription"] = pacsone_gettext("Series ") . $series["seriesNumber"];
                    if (strlen($seriesRow["description"]))
                       $series["seriesDescription"] .= " - " . $seriesRow["description"];
                    // find all instances of this series
                    $imageRes = $dbcon->query("select uuid from image where seriesuid='$seriesUid'");
                    while ($imageRow = $imageRes->fetch(PDO::FETCH_NUM)) {
                        $instanceUid = $imageRow[0];
                        // add this instance to the global instance list
                        $uids[] = $instanceUid;
                        // get the WADO URL for this instance
                        $instance = array(
                            "imageId" => $dbcon->getWADOUrl($instanceUid, $seriesUid),
                        );
                        // add this instance to this series
                        $series["instanceList"][] = $instance;
                        $numImages++;
                    }
                    // add this series to this study
                    $study["seriesList"][] = $series;
                }
                $study["numImages"] = $numImages;
                $studies[$uid] = $study;
            }
        }
    }
    if (strcasecmp($action, "Show") == 0) {
        if (count($uids))
	        appletViewer($uids, $studies);
        else {
            ob_end_flush();
	        print "<html>\n";
	        print "<head><title>$PRODUCT - ";
            print pacsone_gettext("Show Images");
            print "</title></head>\n";
	        print "<body leftmargin=\"0\" topmargin=\"0\" bgcolor=\"#cccccc\">\n";
	        require_once 'header.php';
            print "<p>" . pacsone_gettext("No image to display.") . "<br>";
	        require_once 'footer.php';
	        print "</body>\n";
	        print "</html>\n";
        }
    } else if (stristr($action, "Download")) {
        $files = array();
        if (strcasecmp($action, "Download") == 0) {
            foreach ($uids as $uid) {
                $query = "select path from image where uuid=?";
                $bindList = array($uid);
                $result = $dbcon->preparedStmt($query, $bindList);
                if ($result) {
                    $path = $result->fetchColumn();
                    if (file_exists($path)) {
                        $files[$uid] = $path;
                    }
                }
            }
            zipFiles($files, $filename, true);
        } else {
            // download converted JPG/GIF images
            $filename .= "-converted";
            $thumbnaildir = $imagedir = $flashdir = "";
            getThumbnailImageFlashDirs($dbcon, $thumbnaildir, $imagedir, $flashdir);
            $imagedir .= "images/";
            foreach ($uids as $uid) {
                $path = $imagedir . $uid . ".jpg";
                if (file_exists($path))
                    $files[$uid] = $path;
                else {
                    $path = $imagedir . $uid . ".gif";
                    if (file_exists($path))
                        $files[$uid] = $path;
                }
            }
            zipFiles($files, $filename, false);
        }
    }
}
if (strcasecmp($action, "Print") == 0 && isset($option)) {
    ob_end_flush();
	// display destination AE form
	print "<html>\n";
	print "<head><title>$PRODUCT - ";
    printf(pacsone_gettext("Printing %s"), strcasecmp($option, "Patient")? pacsone_gettext($option) : $CUSTOMIZE_PATIENT);
    print "</title></head>\n";
	print "<body>\n";
	require_once 'header.php';
	$result = $dbcon->query("select title from applentity where port is not NULL and printscp=1 order by title asc");
	if ($result->rowCount() == 0) {
		print "<h3><font color=red>";
        print pacsone_gettext("There is no Dicom printer configured.");
        print "</font></h3>";
	}
	else {
        print "<form method='POST' action='printer.php'>\n";
        print "<table width=100% cellpadding=0 cellspacing=0 border=0>\n";
        print "<tr><td>";
        printf(pacsone_gettext("Print the following %s"), strcasecmp($option, "Patient")? pacsone_gettext($option) : $CUSTOMIZE_PATIENT);
        print ":</td></tr>\n";
        print "<tr><td><br></td></tr>\n";
        foreach ($entry as $uid) {
            if (strcasecmp($option, "Patient") == 0)
                $uid = urldecode($uid);
            print "<tr><td>$uid</td></tr>\n";
        }
        print "<tr><td><br></td></tr>\n";
        print "<tr><td>";
        print pacsone_gettext("Please select which Dicom Printer to print to: ");
        print "<select name='printer'>";
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            print "<option>$row[0]";
        }
        print "</select></td>\n";
        print "</table>\n";
        print "<input type=hidden name='option' value=$option>\n";
        foreach ($entry as $uid) {
            print "<input type=hidden name='entry[]' value='$uid'>\n";
        }
        print "<p><input type='submit' value='" . pacsone_gettext("Print") . "'>\n";
        print "</form>\n";
	}
	require_once 'footer.php';
	print "</body>\n";
	print "</html>\n";
}
if (strcasecmp($action, "Export") == 0 && isset($option)) {
    ob_end_flush();
	// display destination AE form
	print "<html>\n";
	print "<head><title>$PRODUCT - ";
    printf(pacsone_gettext("Exporting %s"), strcasecmp($option, "Patient")? pacsone_gettext($option) : $CUSTOMIZE_PATIENT);
    print "</title></head>\n";
	print "<body>\n";
	require_once 'header.php';
	include_once 'display.php';

	print "<table>\n";
	print "<tr><td>";
    printf(pacsone_gettext("Export the following %s"), strcasecmp($option, "Patient")? pacsone_gettext($option) : $CUSTOMIZE_PATIENT);
    print ":</td></tr>\n";
	print "<tr><td><br></td></tr>\n";
	print "<tr><td>";
    displayExportItem($option, $entry);
	print "</tr></td>";
	print "<tr><td><br></td></tr>\n";
	print "<tr><td>";
    $directory = dirname($_SERVER['SCRIPT_FILENAME']);
    $directory = dirname($directory);
    // check if user has defined a preferred Export dir
    $preferred = "";
    $query = "select exportdir from privilege where username=?";
    $bindList = array($username);
    $result = $dbcon->preparedStmt($query, $bindList);
    if ($result && $result->rowCount()) {
        $dir = $result->fetchColumn();
        if (strlen($dir) && file_exists($dir))
            $preferred = $dir;
    }
    $exportdir = strlen($preferred)? $preferred : ($directory . "/export/");
    print "<form method=POST action='exportStudy.php'>\n";
    displayExportForm($option, $exportdir);
    $title = pacsone_gettext("Export ") . ucfirst($option);
    print "<br><input type=submit value='$title' title='$title'><br>";
    print "<input type=hidden name='option' value=$option>\n";
    foreach ($entry as $uid) {
        print "<input type=hidden name='entry[]' value='$uid'>\n";
    }
    print "</form>\n";
    print "</td></tr>\n";
    print "</table>\n";
	require_once 'footer.php';
	print "</body>\n";
	print "</html>\n";
}
if (stristr($action, "Mark Study") && strcasecmp($option, "Study") == 0) {
    if (stristr($action, "Un-Read")) {
        $reviewed = "reviewed=NULL";
        $status = "status=$STUDY_STATUS_DEFAULT";
    } else {
        $reviewed = "reviewed='$username'";
        $status = "status=$STUDY_STATUS_READ";
    }
    foreach ($entry as $uid) {
        $query = "UPDATE study SET $reviewed WHERE uuid=?";
        $bindList = array($uid);
        $dbcon->preparedStmt($query, $bindList);
        $query = "UPDATE worklist SET $status WHERE studyuid=?";
        $dbcon->preparedStmt($query, $bindList);
        // log activity to system journal
        $dbcon->logJournal($username, $action, $option, $uid);
    }
    // back to the original page
    $url = $_SERVER["HTTP_REFERER"];
    if (stristr($url, "study.php")) {
        $studyUid = $entry[0];
        $patientId = $dbcon->getPatientIdByStudyUid($studyUid);
        $url = "study.php?patientId=" . urlencode($patientId);
    }
    ob_end_clean();
    header("Location: $url");
    exit;
}
if (strcasecmp($action, "Change Storage") == 0 && isset($option)) {
    ob_end_flush();
    // display destination storage location form
    print "<html>\n";
    print "<head><title>$PRODUCT - ";
    print pacsone_gettext("Change Storage Location of selected studies");
    print "</title></head>\n";
    print "<body>\n";
    require_once 'header.php';
    $dirs = array();
    $result = $dbcon->query("select archivedir,longtermdir from applentity");
    if ($result && $result->rowCount()) {
        $row = $result->fetch(PDO::FETCH_NUM);
        if (strlen($row[0]))
            $dirs[] = $row[0];
        if (strlen($row[1]))
            $dirs[] = $row[1];
    }
    $result = $dbcon->query("select archivedir,longtermdir from config");
    if ($result && $result->rowCount()) {
        $row = $result->fetch(PDO::FETCH_NUM);
        if (strlen($row[0]))
            $dirs[] = $row[0];
        if (strlen($row[1]))
            $dirs[] = $row[1];
    }
    print "<form method='POST' action='changeStore.php'>\n";
    print "<table cellpadding=5>\n";
    print "<tr><td>";
    printf(pacsone_gettext("Move the following %s:"), $option);
    print "</td><td>\n";
    foreach ($entry as $uid) {
        print "&nbsp;$uid<br>\n";
    }
    print "</td></tr>";
    print "<tr><td>";
    print pacsone_gettext("Please select new storage location:");
    print "</td><td>";
    print "<input type='radio' name='userdir' value=0 checked>";
    print pacsone_gettext("Select from the list of currently defined Archive Directories:");
    print "&nbsp;<select name='selectdir'>";
    foreach ($dirs as $value) {
        print "<option>$value</option>";
    }
    print "</select>\n";
    print "<br><input type='radio' name='userdir' value=1>";
    print pacsone_gettext("Move to this specific directory:");
    print "&nbsp;<input type='text' name='newdir' size=64 maxlength=255>";
    print "</td></tr>";
    print "<tr><td>";
    print pacsone_gettext("Please select move schedule:");
    print "</td>";
    print "<td><input type='radio' name='schedule' value=-1 checked>";
    print pacsone_gettext("Immediately") . "<br>\n";
    print "<input type='radio' name='schedule' value=0>";
    print pacsone_gettext("At this hour: \n");
    print "<select name='hour'>\n";
    foreach ($HOUR_TBL as $key => $value) {
        if ($value == 24)
            break;
        $item = ($value == 0)? "<option selected>" : "<option>";
        print "$item$key</option>";
    }
    print "</select>\n";
    print "</td></tr>";
    print "</table>\n";
    print "<input type=hidden name='option' value=$option>\n";
    foreach ($entry as $uid) {
        print "<input type=hidden name='entry[]' value='$uid'>\n";
    }
    print "<p><input type='submit' value='" . pacsone_gettext("Move") . "'>\n";
    print "</form>\n";
    require_once 'footer.php';
    print "</body>\n";
    print "</html>\n";
}

?>
