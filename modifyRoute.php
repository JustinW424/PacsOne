<?php
//
// modifyRoute.php
//
// Module for managing the Automatic Routing Tables
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();
ob_start();

require_once 'locale.php';
include_once 'database.php';
include_once 'utils.php';
include_once 'sharedData.php';
include_once 'xferSyntax.php';
include_once 'checkUncheck.js';
include_once "logicalExpression.js";

// main
global $PRODUCT;
$dbcon = new MyConnection();
$username = $dbcon->username;
if (isset($_POST['source']))
	$source = $_POST['source'];
if (isset($_POST['action']))
   	$action = $_POST['actionvalue'];
if (isset($_POST['entry']))
   	$entry = $_POST['entry'];
$result = NULL;
$mpps = isset($_REQUEST['mpps'])? $_REQUEST['mpps'] : 0;
if (isset($_GET['source']) && isset($_GET['destination']) &&
    isset($_GET['keytag']) ) {
    $source = $_GET['source'];
    $destination = $_GET['destination'];
    $keytag = $_GET['keytag'];
    $window = $_GET['window'];
    $pattern = isset($_GET['pattern'])? $_GET['pattern'] : "";
    $weekday = $_GET['weekday'];
    if (isset($_GET['enabled'])) {
        $enabled = $_GET['enabled'];
        $table = $mpps? "mppsroute" : "autoroute";
        $query = "UPDATE $table SET enabled=? WHERE source=? AND ";
        $bindList = array($enabled, $source);
        $query .= "destination=? AND ";
        $bindList[] = $destination;
        if (strlen($pattern)) {
            $query .= "pattern=? AND ";
            $bindList[] = $pattern;
        }
        $query .= "keytag=? and schedwindow=? and weekday=?";
        array_push($bindList, $keytag, $window, $weekday);
        if (!$dbcon->preparedStmt($query, $bindList)) {
            print "<h3><font color=red>";
            printf(pacsone_gettext("Error running query: %s"), $query) . "<br>";
            printf(pacsone_gettext("Database Error: %s"), $dbcon->getError());
            print "</font></h3><br>";
            exit();
        }
        $result = "";
    } else {
        modifyEntryForm($source, $destination, $keytag, $window, $pattern, $weekday, $mpps);
    }
} else {
    if (isset($action) && strcasecmp($action, "Delete") == 0) {
	    $result = deleteEntries($username, $entry, $mpps);
    }
    else if (isset($action) && strcasecmp($action, "Enable All") == 0) {
	    $result = toggleEntries($entry, 1, $mpps);
    }
    else if (isset($action) && strcasecmp($action, "Disable All") == 0) {
	    $result = toggleEntries($entry, 0, $mpps);
    }
    else if (isset($action) && strcasecmp($action, "Add") == 0) {
	    if (isset($source)) {
	        $result = addEntry($username, $mpps);
        }
        else {
            addEntryForm($mpps);
        }
    }
    else if (isset($action) && strcasecmp($action, "Modify") == 0) {
	    $result = modifyEntry($username, $mpps);
    }
}
if (isset($result)) {       // display results
    if (empty($result)) {   // success
        ob_end_clean();
        header('Location: autoroute.php');
    }
    else {                  // error
        ob_end_flush();
        print "<html>\n";
        print "<head><title>$PRODUCT - ";
        print pacsone_gettext("Application Entity Error");
        print "</title></head>\n";
        print "<body>\n";
        require_once 'header.php';
        print $result;
		require_once 'footer.php';
        print "</body>\n";
        print "</html>\n";
    }
}

function validateDatePattern($key, &$pattern, &$result)
{
    global $dbcon;
    global $ROUTE_DATE_KEY_TBL;
    // validate dates for date-based key attributes
    if (in_array($key, $ROUTE_DATE_KEY_TBL)) {
        $eurodate = $dbcon->isEuropeanDateFormat();
        $tokens = explode(" ", $pattern);
        $modified = array();
        $bDate = false;
        $bValid = true;
        foreach ($tokens as $token) {
            if (strstr($token, "-")) {
                $bDate = true;
                if (!isDateValid($token, $eurodate)) {
                    $bValid = false;
                    break;
                } else if ($eurodate) {
                    // convert to SQL date
                    $token = reverseDate($token);
                }
            }
            $modified[] = $token;
        }
        if (!$bValid || !$bDate) {
            $result .= "<h3><font color=red>";
            $result .= sprintf(pacsone_gettext("Invalid DATE [%s] for key attribute (0x%08x), please use %s format"),
                $pattern, $key, ($eurodate)? "DD-MM-YYYY" : "YYYY-MM-DD");
            $result .= "</font></h3>";
            return false;
        }
        $newPattern = implode(" ", $modified);
        if (strcasecmp($pattern, $newPattern))
            $pattern = $newPattern;
    }
    return true;
}

function validateLogicalExpression(&$pattern, &$result)
{
    $modified = array();
    $tokens = explode("%", $pattern);
    foreach ($tokens as $token) {
        if (strstr($token, "=")) {
            $exps = explode("=", $token);
            sscanf($exps[0], "%08x", $key);
            if (strstr($exps[1], "-")) {
                $copy = $exps[1];
                if (!validateDatePattern($key, $copy, $result))
                    return false;
                if (strcmp($copy, $exps[1]))
                    $token = sprintf("%08x=%s", $key, $copy);
            }
        }
        $modified[] = $token;
    }
    $pattern = implode("%", $modified);
    return true;
}

function deleteEntries($username, $entry, $mpps)
{
    global $dbcon;
	$ok = array();
	$errors = array();
	foreach ($entry as $value) {
		$aes = explode("||", $value);
		$source = $aes[0];
		$key = $aes[1];
		$dest = $aes[2];
		$window = $aes[3];
        $pattern = isset($aes[4])? $aes[4] : "";
		$schedule = $aes[5];
		$weekday = $aes[6];
        $table = $mpps? "mppsroute" : "autoroute";
		$query = "delete from $table where source=? and keytag=? ";
        $bindList = array($source, $key);
        if (strlen($pattern)) {
            $query .= "and pattern=? ";
            $bindList[] = $pattern;
        }
        $query .= "and destination=? and schedwindow=? and schedule=? and weekday=?";
        array_push($bindList, $dest, $window, $schedule, $weekday);
		if (!$dbcon->preparedStmt($query, $bindList)) {
			$errors[$value] = sprintf(pacsone_gettext("Database Error: %s"), $dbcon->getError());
		}
		else {
			$ok[] = $value;
      // log activity to system journal
      $dbcon->logJournal($username, "Delete", "Route", $value);
    }
	}
	$result = "";
	if (!empty($errors)) {
        if (count($errors) == 1)
		    $result = pacsone_gettext("Error deleting the following Routing Entry:");
        else
		    $result = pacsone_gettext("Error deleting the following Routing Entries:");
        $result .= "<P>\n";
		foreach ($errors as $key => $value) {
			$result .= "<h3><font color=red><u>$key</u> - $value</font></h3><P>\n";
		}
	}
    return $result;
}

function addEntry($username, $mpps)
{
    global $ROUTE_KEY_TBL;
    global $ROUTE_MPPS_TBL;
    global $dbcon;
    global $HOUR_TBL;
	if (!isset($_POST['source']) || !isset($_POST['destination']))
		return "<h3><font color=red>" . pacsone_gettext("Source and Destination AE must be defined.") . "</font></h3>";

    $result = "";
	$routeBy = $_POST['routeby'];
	$source = isset($_POST['source'])? $_POST['source'] : "";
    if (!strlen($source) || !strcmp($source, "*"))
        $source = "_Any";
    $key = 0;
    $destfolder = "";
    if ($_POST['desttype'] == 0) {
	    $tokens = explode(" - ", $_POST['destination']);
        $destination = $tokens[0];
    } else {
        $destination = "_";
        $destfolder = cleanPostPath($_POST['destfolder']);
        if (!file_exists($destfolder)) {
            $error = "<h3><font color=red>";
            $error .= sprintf(pacsone_gettext("Destination folder [%s] does not exist!"), $destfolder);
            $error .= "</font></h3>";
            return $error;
        }
    }
    $columns = array();
    $bindList = array();
    $table = $mpps? "mppsroute" : "autoroute";
    $query = "insert into $table (";
    $columns[] = "source";
    $values = "?";
    $bindList[] = $source;
    $columns[] = "destination";
    $values .= ",?";
    $bindList[] = $destination;
    if ($routeBy) {
        $keyTable = $mpps? $ROUTE_MPPS_TBL : $ROUTE_KEY_TBL;
        $key = ($routeBy == 3)? 0xFFFFFFFF : $keyTable[ cleanPostPath($_POST['keytag'], false) ];
	    $pattern = ($routeBy == 3)? $_POST['logicalExpr'] : $_POST['pattern'];
        if (strlen($pattern) == 0) {
		    $result .= "<h3><font color=red>";
            $result .= pacsone_gettext("Key Matching Pattern Must Be Defined.");
            $result .= "</font></h3>";
            return $result;
        }
        if ($key == 0xFFFFFFFF && !validateLogicalExpression($pattern, $result))
            return $result;
        else if (!validateDatePattern($key, $pattern, $result))
            return $result;
        $columns[] = "keytag";
        $values .= ",?";
        $bindList[] = $key;
        $columns[] = "pattern";
        $values .= ",?";
        $bindList[] = $pattern;
    } else {
        $columns[] = "pattern";
        $values .= ",''";
    }
	if (isset($_POST['autopurge'])) {
		$autopurge = $_POST['autopurge'];
        $columns[] = "autopurge";
        $values .= ",?";
        $bindList[] = $autopurge;
    }
	if (isset($_POST['schedule'])) {
		$schedule = $_POST['schedule'];
		if ($schedule == 1) {	// not immediately
			$schedule = hour2schedule($_POST['hour'], $_POST['ampm']);
		} else if (($schedule == 2) || ($schedule == 3) || ($schedule == 4)) {
            if ($schedule == 2) {
                $schedule = -1;
                $from =  $_REQUEST['from'];
                $to = $_REQUEST['to'];
            } else if ($schedule == 3) {
                $schedule = 0;
                $from =  $_REQUEST['schedulefrom'];
                $to =  $_REQUEST['scheduleto'];
            } else if ($schedule == 4) {
                $schedule = -2;
                $from =  $_REQUEST['delayfrom'];
                $to =  $_REQUEST['delayto'];
            }
            $from = $HOUR_TBL[$from];
            $to = $HOUR_TBL[$to];
            if ($to <= $from) {
                print "<font color=red>";
                print pacsone_gettext("<b>TO</b> hour must be greater than <b>FROM</b> hour</font>");
                exit();
            }
            $window = ($from << 8) | $to;
            $columns[] = "schedwindow";
            $values .= ",?";
            $bindList[] = $window;
        }
        $columns[] = "schedule";
        $values .= ",?";
        $bindList[] = $schedule;
	}
	if (isset($_POST['priority'])) {
        $value = $_POST['priority'];
        $columns[] = "priority";
        $values .= ",?";
        $bindList[] = $value;
    }
	if (isset($_POST['weekday'])) {
        $value = 0;
        foreach ($_POST['weekday'] as $day)
            $value += $day;
        $columns[] = "weekday";
        $values .= ",?";
        $bindList[] = $value;
	}
    if (isset($_POST['retryinterval'])) {
        $value = $_POST['retryinterval'];
        if ($value) {
            $columns[] = "retryinterval";
            $values .= ",?";
            $bindList[] = $value;
        }
    }
    if (isset($_POST['fetchmore'])) {
        $fetchmore = 0 - $_POST['studies'];
        $columns[] = "fetchmore";
        $values .= ",?";
        $bindList[] = $fetchmore;
    }
    if (isset($_POST['delayedstudy'])) {
        $minutes = $_POST['waitmins'];
        $columns[] = "delayedstudy";
        $values .= ",?";
        $bindList[] = $minutes;
    }
    if (isset($_POST['delayedseries'])) {
        $minutes = $_POST['seriesmins'];
        $columns[] = "delayedseries";
        $values .= ",?";
        $bindList[] = $minutes;
    }
    if (isset($_POST['usesendingaet'])) {
        $sendingaet = $_POST['usewhichaet']? "\$SOURCE\$" : $_POST['sendingaet'];
        if (strlen($sendingaet)) {
            $columns[] = "sendingaet";
            $values .= ",?";
            $bindList[] = $sendingaet;
        }
    }
    if (isset($_POST['xfersyntax'])) {
        $tokens = explode(" - ", $_POST['xfersyntax']);
        if (count($tokens) > 1) {
            global $XFER_SYNTAX_TBL;
            $syntax = trim($tokens[1]);
            if (!array_key_exists($syntax, $XFER_SYNTAX_TBL))
                $syntax = "";
            $columns[] = "xfersyntax";
            $values .= ",?";
            $bindList[] = $syntax;
        }
    }
    if (strlen($destfolder)) {
        $columns[] = "destfolder";
        $values .= ",?";
        $bindList[] = $destfolder;
    }
    for ($i = 0; $i < count($columns); $i++) {
        if ($i)
            $query .= ",";
        $query .= $columns[$i];
    }
    $query .= ") values($values)";
    // execute SQL query
    if (!$dbcon->preparedStmt($query, $bindList)) {
        $result .= "<h3><font color=red>";
        $result .= sprintf(pacsone_gettext("Error adding Routing Entity for <u>%s</u>: "), $source);
        $result .= $dbcon->getError();
        $result .= "</font></h3><p>\n";
    } else {
        // log activity to system journal
        $dbcon->logJournal($username, "Add", "Route", "$source||$key||$destination");
    }
    return $result;
}

function modifyEntry($username, $mpps)
{
    global $dbcon;
    global $HOUR_TBL;
	if (!isset($_POST['source']) || !isset($_POST['destination']))
		return "<h3><font color=red>" . pacsone_gettext("Source and Destination AE must be defined.") . "</font></h3>";

    $result = "";
	$source = $_POST['source'];
	$keytag = $_POST['keytag'];
    $destfolder = "";
    $destination = $_POST['destination'];
    if (strcmp($destination, "_")) {
	    $tokens = explode(" - ", $_POST['destination']);
        $destination = $tokens[0];
    } else {
        $destfolder = $_POST['destfolder'];
        if (get_magic_quotes_gpc())
            $destfolder = stripslashes($destfolder);
        // change to Unix-style path
        $destfolder = str_replace("\\", "/", $destfolder);
        if (!file_exists($destfolder)) {
            $error = "<h3><font color=red>";
            $error .= sprintf(pacsone_gettext("Destination folder [%s] does not exist!"), $destfolder);
            $error .= "</font></h3>";
            return $error;
        }
    }
    $oldwindow = $_POST['oldwindow'];
    $oldweekday = $_POST['oldweekday'];
    $table = $mpps? "mppsroute" : "autoroute";
	$query = "update $table set ";
    $bindList = array();
    if ($keytag) {
		$pattern = ($keytag == 0xFFFFFFFF)? $_POST['logicalExpr'] : $_POST['pattern'];
        $oldpattern = $_POST['oldpattern'];
        if (strlen($pattern) == 0) {
		    $result .= "<h3><font color=red>";
            $result .= pacsone_gettext("Key Matching Pattern Must Be Defined.");
            $result .= "</font></h3>";
            return $result;
        }
        if ($keytag == 0xFFFFFFFF && !validateLogicalExpression($pattern, $result))
            return $result;
        else if (!validateDatePattern($keytag, $pattern, $result))
            return $result;
        $query .= "pattern=?,";
        $bindList[] = $pattern;
    }
	if (isset($_POST['autopurge'])) {
        $query .= "autopurge=?,";
        $bindList[] = $_POST['autopurge'];
    }
	if (isset($_POST['schedule'])) {
		$schedule = $_POST['schedule'];
        $window = 0;
		if ($schedule == 1) {
			$schedule = hour2schedule($_POST['hour'], $_POST['ampm']);
		} else if (($schedule == 2) || ($schedule == 3) || ($schedule == 4)) {
            if ($schedule == 2) {
                $schedule = -1;
                $from =  $_REQUEST['from'];
                $to = $_REQUEST['to'];
            } else if ($schedule == 3) {
                $schedule = 0;
                $from =  $_REQUEST['schedulefrom'];
                $to =  $_REQUEST['scheduleto'];
            } else if ($schedule == 4) {
                $schedule = -2;
                $from =  $_REQUEST['delayfrom'];
                $to =  $_REQUEST['delayto'];
            }
            $from = $HOUR_TBL[$from];
            $to = $HOUR_TBL[$to];
            if ($to <= $from) {
                print "<font color=red>";
                print pacsone_gettext("<b>TO</b> hour must be greater than <b>FROM</b> hour</font>");
                exit();
            }
            $window = ($from << 8) | $to;
        }
		$query .= "schedule=?,schedwindow=$window,";
        $bindList[] = $schedule;
	}
    if (isset($_POST['priority'])) {
		$query .= "priority=?,";
        $bindList[] = $_POST['priority'];
    }
    if (isset($_POST['weekday'])) {
        $value = 0;
        foreach ($_POST['weekday'] as $day)
            $value += $day;
		$query .= "weekday=$value,";
    }
    if (isset($_POST['retryinterval'])) {
        $query .= "retryinterval=?,";
        $bindList[] = $_POST['retryinterval'];
    } else {
        $query .= "retryinterval=0,";
    }
    if (!$mpps) {
        if (isset($_POST['fetchmore'])) {
            $fetchmore = 0 - $_POST['studies'];
            $query .= "fetchmore=$fetchmore,";
        } else {
            $query .= "fetchmore=0,";
        }
        if (isset($_POST['delayedstudy'])) {
            $query .= "delayedstudy=?,";
            $bindList[] = $_POST['waitmins'];
        } else {
            $query .= "delayedstudy=0,";
        }
        if (isset($_POST['delayedseries'])) {
            $query .= "delayedseries=?,";
            $bindList[] = $_POST['seriesmins'];
        } else {
            $query .= "delayedseries=0,";
        }
        if (strlen($destfolder) && file_exists($destfolder)) {
            $query .= "destfolder=?,";
            $bindList[] = $destfolder;
        }
        if (isset($_POST['xfersyntax'])) {
            $syntax = "";
            $tokens = explode(" - ", $_POST['xfersyntax']);
            if (count($tokens) > 1) {
                global $XFER_SYNTAX_TBL;
                $syntax = trim($tokens[1]);
                if (!array_key_exists($syntax, $XFER_SYNTAX_TBL))
                    $syntax = "";
            }
            $query .= "xfersyntax=?,";
            $bindList[] = $syntax;
        }
    }
    if (isset($_POST['usesendingaet'])) {
        $sendingaet = $_POST['usewhichaet']? "\$SOURCE\$" : $_POST['sendingaet'];
        if (strlen($sendingaet)) {
            $query .= "sendingaet=?,";
            $bindList[] = $sendingaet;
        }
    } else {
        $query .= "sendingaet=NULL,";
    }
    // get rid of the last ','
    $npos = strrpos($query, ",");
    if ($npos != false)
        $query = substr($query, 0, $npos);
    $query .= " where keytag=? and source=? ";
    array_push($bindList, $keytag, $source);
    if (isset($oldpattern)) {
        $query .= "and pattern=? ";
        $bindList[] = $oldpattern;
    }
    $query .= "and schedwindow=? ";
    $bindList[] = $oldwindow;
    $query .= "and weekday=? ";
    $bindList[] = $oldweekday;
    $query .= "and destination=?";
    $bindList[] = $destination;
    // execute SQL query
    if (!$dbcon->preparedStmt($query, $bindList)) {
        $result .= "<h3><font color=red>";
        $result .= sprintf(pacsone_gettext("Error updating Routing Entity for <u>%s</u>: "), $source);
        $result .= $dbcon->getError();
        $result .= "</font></h3><p>\n";
    } else {
        // log activity to system journal
        $dbcon->logJournal($username, "Modify", "Route", "$source||$keytag||$destination");
    }
    return $result;
}

function logicalExpressionForm($expr)
{
    global $ROUTE_KEY_TBL;
    print "<table cellspacing=10 cellpadding=0>";
    print "<tr><td><input type='button' name='leftBracket' value='";
    print pacsone_gettext("Left Round Bracket (");
    print "' title='";
    print pacsone_gettext("Left Round Bracket (");
    print "' onClick='onLeftBracket(this.form)'></td>";
    print "<td><input type='button' name='rightBracket' value='";
    print pacsone_gettext("Right Round Bracket )");
    print "' title='";
    print pacsone_gettext("Right Round Bracket )");
    print "' onClick='onRightBracket(this.form)'></td>";
    print "<td><input type='button' name='logicalAnd' value='AND (&&)' title='";
    print pacsone_gettext("Logical AND Operator");
    print "' onClick='onAndButton(this.form)'></td>";
    print "<td><input type='button' name='logicalOr' value='OR (||)' title='";
    print pacsone_gettext("Logical OR Operator");
    print "' onClick='onOrButton(this.form)'></td></tr>";
    print "<tr><td><select name='logicalTag'>";
    foreach ($ROUTE_KEY_TBL as $key => $tag) {
        print "<option>$key</option>\n";
    }
    print "</select></td>";
    print "<td><input type='text' name='tokenExpr' size=32 maxlength=255></td>";
    print "<td><input type='button' name='appendButton' value='";
    print pacsone_gettext("Append");
    print "' title='";
    print pacsone_gettext("Append Pattern to Composite Logical Expression");
    print "' onClick='onAppendButton(this.form)'></td>";
    print "<td><input type='button' name='resetButton' value='";
    print pacsone_gettext("Reset");
    print "' title='";
    print pacsone_gettext("Reset Composite Logical Expression");
    print "' onClick='onResetButton(this.form)'></td></tr>";
    print "<tr><td colspan=4>";
    print "<input type='text' name='logicalExpr' value='$expr' size=64 maxlength=255>";
    print "</td></tr>";
    print "</table>";
}

function addEntryForm($mpps)
{
    global $PRODUCT;
    global $ROUTE_KEY_TBL;
    global $ROUTE_MPPS_TBL;
    global $dbcon;
    global $HOUR_TBL;
    global $WEEKDAY_MASK;
    // display Add New Automatic Routing Entity form
    print "<html>\n";
    print "<head><title>$PRODUCT - ";
    print pacsone_gettext("Add Automatic Route");
    print "</title></head>\n";
    print "<body>\n";
    require_once 'header.php';
	print "<P>";
    $what = $mpps? pacsone_gettext("Modality Performed Procedure Step (MPPS) messages") : pacsone_gettext("images");
    printf(pacsone_gettext("Once a routing entry is defined, all %s received from the <b>SOURCE</b> Application Entity (AE) will automatically be forwarded to the <b>DESTINATION</b> Application Entity (AE) with the specified <b>SCHEDULE</b>."), $what);
    print "<P>";
    print "<form method='POST' action='modifyRoute.php'>\n";
    print "<input type='hidden' name='actionvalue'>\n";
    if ($mpps)
        print "<input type='hidden' name='mpps' value=1>\n";
    print "<table cellspacing=10 cellpadding=0>\n";
    print "<tr><td>";
    print pacsone_gettext("Select Routing Criteria:") . "</td>\n";
    print "<td><input type=radio name='routeby' value=0 checked>";
    print pacsone_gettext("By Source Application Entity Title:\n");
    print "<input type='text' size=16 maxlength=16 name='source'>";
    print pacsone_gettext(" (Wild-Card Characters <b>*</b> and <b>?</b> Are Supported)<br>\n");
    print "<input type=radio name='routeby' value=1>";
    print pacsone_gettext("By Key Attribute:\n");
    print "<select name='keytag'>\n";
    $keyTable = $mpps? $ROUTE_MPPS_TBL : $ROUTE_KEY_TBL;
    foreach ($keyTable as $key => $tag) {
        print "<option>$key</option>\n";
    }
    print "</select><br>&nbsp;&nbsp;&nbsp;&nbsp;\n";
    print pacsone_gettext("with Case-Insensitive Matching Pattern:");
    print " <input type='text' size=64 maxlength=255 name='pattern'>\n";
    print pacsone_gettext(" (Wild-Card Characters <b>*</b> and <b>?</b> Are Supported)<br>\n");
    print "<input type=radio name='routeby' value=2>";
    print pacsone_gettext("By Applying the Logical AND Operator (<b>&&</b>) to Both Criteria Above:\n");
    if (!$mpps) {
        // advanced logical expressions
        print "<br><input type=radio name='routeby' value=3>";
        print pacsone_gettext("By Applying the following advanced logical expressions:");
        print "<br>";
        logicalExpressionForm("");
    }
    print "</td></tr>\n";
	// query database for the list of eligible destination AEs
	$result = $dbcon->query("select title,description from applentity where port is not null order by title asc");
    print "<tr><td>" . pacsone_gettext("Enter Destination Application Entity:") . "</td>\n";
    print "<td><input type=radio name='desttype' value=0 checked>";
    print pacsone_gettext("Forward to this destination AE: ");
    print "<select name='destination'>\n";
	while ($row = $result->fetch(PDO::FETCH_NUM)) {
        $value = $row[0];
        if (strlen($row[1]))
            $value .= " - " . $row[1];
		print "<option>$value</option>";
    }
    print "</select><br>";
    if (!$mpps) {
        print "<input type=radio name='desttype' value=1>";
        print pacsone_gettext("Copy received images to this destination foder: ");
        print "<input type='text' name='destfolder' size=64 maxlength=256>\n";
    }
    print "</td></tr>\n";
	print "<tr></tr>\n";
    // hourly schedule
    print "<tr><td>" . pacsone_gettext("Enter Hourly Schedule:") . "</td>\n";
    print "<td><input type='radio' name='schedule' value=-1 checked>";
    print pacsone_gettext("Immediately (As soon as received)") . "<br>\n";
	print "<input type='radio' name='schedule' value=2>";
    print pacsone_gettext("Only when local time is from: \n");
	print "<select name='from'>\n";
	foreach ($HOUR_TBL as $key => $value) {
        if ($value == 24)
            break;
        $option = ($value == 0)? "<option selected>" : "<option>";
		print "$option$key</option>";
    }
	print "</select>\n";
    print pacsone_gettext(" To: ");
	print "<select name='to'>\n";
	foreach ($HOUR_TBL as $key => $value) {
        $option = ($value == 23)? "<option selected>" : "<option>";
		print "$option$key</option>";
    }
	print "</select><br>\n";
	print "<input type='radio' name='schedule' value=1>";
    print pacsone_gettext("Fixed At: \n");
	print "<select name='hour'>\n";
	for ($i = 1; $i <= 12; $i++)
		print "<option>$i</option>";
	print "</select><input type='radio' name='ampm' value=0 checked>" . pacsone_gettext("A.M.");
	print "<input type='radio' name='ampm' value=1>" . pacsone_gettext("P.M.") . "<br>";
    print "<input type='radio' name='schedule' value=3>";
    print pacsone_gettext("Immediately, but only during the following hourly window:");
    print "&nbsp;" . pacsone_gettext("From: \n");
	print "<select name='schedulefrom'>\n";
	foreach ($HOUR_TBL as $key => $value) {
        if ($value == 24)
            break;
        $option = ($value == 0)? "<option selected>" : "<option>";
		print "$option$key</option>";
    }
	print "</select>\n";
    print pacsone_gettext(" To: ");
	print "<select name='scheduleto'>\n";
	foreach ($HOUR_TBL as $key => $value) {
        $option = ($value == 23)? "<option selected>" : "<option>";
		print "$option$key</option>";
    }
	print "</select><br>\n";
    print "<input type='radio' name='schedule' value=4>";
    print pacsone_gettext("Delayed until the following hourly window:");
    print "&nbsp;" . pacsone_gettext("From: \n");
	print "<select name='delayfrom'>\n";
	foreach ($HOUR_TBL as $key => $value) {
        if ($value == 24)
            break;
        $option = ($value == 0)? "<option selected>" : "<option>";
		print "$option$key</option>";
    }
	print "</select>\n";
    print pacsone_gettext(" To: ");
	print "<select name='delayto'>\n";
	foreach ($HOUR_TBL as $key => $value) {
        $option = ($value == 23)? "<option selected>" : "<option>";
		print "$option$key</option>";
    }
	print "</select><br>\n";
	print "</td></tr>\n";
    // priority
    print "<tr><td>" . pacsone_gettext("Enter Priority:") . "</td>\n";
    print "<td><input type='text' name='priority' value=0 size=3 maxlength=3>&nbsp;";
    print pacsone_gettext("Higher priority rules will create higher priority routing jobs which will be processed before those routing jobs with lower priorities");
	print "</td></tr>\n";
    // weekday schedule
    print "<tr><td>" . pacsone_gettext("Enter Weekday Schedule:") . "</td>\n";
    print "<td>";
    foreach ($WEEKDAY_MASK as $bit => $weekday) {
        print "<input type=checkbox name='weekday[]' value=$bit checked>$weekday";
    }
	print "</td></tr>\n";
    // retry interval
    print "<tr><td>";
    print pacsone_gettext("Time Interval for Retrying Failed Routing Jobs:") . "</td>\n";
    print "<td><input type='text' name='retryinterval' size=2 value=0>&nbsp;";
    print pacsone_gettext("hours");
    print "</td></tr>\n";
    if (!$mpps) {
        // purge received images after routing
        print "<tr><td>";
        print pacsone_gettext("Purge Received Images After Routing:") . "</td>\n";
        print "<td><input type='radio' name='autopurge' value=1>";
        print pacsone_gettext("Yes") . "<br>\n";
        print "<input type='radio' name='autopurge' value=0 checked>";
        print pacsone_gettext("No") . "<br>\n";
	    print "</td></tr>\n";
    }
    // more options
    print "<tr><td>" . pacsone_gettext("More Options:") . "</td>\n";
    print "<td>";
    if (!$mpps) {
        print "<input type=checkbox name='fetchmore' value=1> ";
        print pacsone_gettext("Forward Existing <input type=text name='studies' value=1 size=2 maxlength=2> Oldest Studies To Destination AE");
        print "<br><input type=checkbox name='delayedstudy' value=0> ";
        global $DEFAULT_STUDY_WAIT;
        printf(pacsone_gettext("Wait <input type='text' name='waitmins' value=%d size=3 maxlength=4> minutes for all instances of the study to be received, and forward the entire study instead of individual images"), $DEFAULT_STUDY_WAIT);
        print "<br><input type=checkbox name='delayedseries' value=0> ";
        global $DEFAULT_SERIES_WAIT;
        printf(pacsone_gettext("Wait <input type='text' name='seriesmins' value=%d size=3 maxlength=4> minutes for all instances of the series to be received, and forward the entire series instead of individual images"), $DEFAULT_SERIES_WAIT);
    }
    // sending AE Title
    print "<br><input type=checkbox name='usesendingaet'> ";
    printf(pacsone_gettext("Do not use the AE Title assigned to %s"), $PRODUCT);
    print "<br>&nbsp;&nbsp;&nbsp;";
    print "<input type='radio' name='usewhichaet' value=1 checked>";
    print pacsone_gettext("Use the Original or Source AE Title When Sending To Destination AE");
    print "<br>&nbsp;&nbsp;&nbsp;";
    print "<input type='radio' name='usewhichaet' value=0>";
    print pacsone_gettext("Use This AE Title: <input type=text name='sendingaet' size=16 maxlength=16> When Sending To Destination AE");
    print "</td></tr>\n";
    if (!$mpps) {
        // preferred transfer syntax
        print "<tr><td>" . pacsone_gettext("Preferred Dicom Transfer Syntax When Sending To Destination AE:") . "</td>\n";
        global $XFER_SYNTAX_TBL;
        print "<td><select name='xfersyntax'>";
        print "<option selected>" . pacsone_gettext("None") . "</option>";
        foreach ($XFER_SYNTAX_TBL as $key => $syntax)
            print "<option>" . $XFER_SYNTAX_TBL[$key][2] . " - $key</option>";
        print "</select></td>\n";
        print "</td></tr>\n";
    }
    print "</table>\n";
    print "<p><input type='submit' name='action' value='";
    print pacsone_gettext("Add");
    print "' onclick='switchText(this.form,\"actionvalue\",\"Add\")'></form>\n";
	require_once 'footer.php';
    print "</body>\n";
    print "</html>\n";
}

function modifyEntryForm($source, $destination, $keytag, $window, $pattern, $weekday, $mpps)
{
    global $PRODUCT;
    global $dbcon;
    global $HOUR_TBL;
    global $WEEKDAY_MASK;
    // display Modify Automatic Routing Entity form
    print "<html>\n";
    print "<head><title>$PRODUCT - ";
    print pacsone_gettext("Modify Automatic Route");
    print "</title></head>\n";
    print "<body>\n";
    require_once 'header.php';
    $table = $mpps? "mppsroute" : "autoroute";
    $query = "select * from $table where source=? and keytag=? ";
    $bindList = array($source, $keytag);
    if (strlen($pattern)) {
        $query .= "and pattern=? ";
        $bindList[] = $pattern;
    }
    $query .= "and destination=? and schedwindow=? and weekday=?";
    array_push($bindList, $destination, $window, $weekday);
    $result = $dbcon->preparedStmt($query, $bindList);
    if ($result && $result->rowCount() == 1) {
        $row = $result->fetch(PDO::FETCH_ASSOC);
        print "<form method='POST' action='modifyRoute.php'>\n";
        print "<table cellspacing=10 cellpadding=0>\n";
        print "<input type=hidden name='action' value='Modify'>\n";
        print "<input type='hidden' name='actionvalue' value='Modify'>\n";
        if ($mpps)
            print "<input type='hidden' name='mpps' value=1>\n";
        $data = $row['source'];
        if ($keytag == 0) {
            print "<tr><td>";
            print pacsone_gettext("Route By Source Application Entity Title:") . "</td>\n";
            $value = (strlen($data) && ($data[0] == '_'))?
                pacsone_gettext("Any") : $data;
            print "<td><b>$value</b></td>\n";
            $value = "<input type=hidden name='source' ";
            if (isset($data))
                $value .= "value='$data' ";
            $value .= "</input>\n";
            print $value;
            print "<input type=hidden name='keytag' value=$keytag></input>\n";
            print "</tr>\n";
        } else {
            print "<tr><td>";
            print pacsone_gettext("Route By Source Application Entity Title:") . "</td>\n";
            $value = (strlen($data) && ($data[0] == '_'))?
                pacsone_gettext("Any") : $data;
            print "<td><b>$value</b></td>\n";
            print "</td></tr>\n";
            print "<tr><td>";
            $value = $row["pattern"];
            if ($keytag == 0xFFFFFFFF) { // advanced logical expressions
                print pacsone_gettext("Advanced Logical Expressions:");
                print "</td><td>";
                $dvalue = $dbcon->isEuropeanDateFormat()? reverseLogicalExpDate($value) : $value;
                logicalExpressionForm($dvalue);
            } else {
                global $ROUTE_DATE_KEY_TBL;
                global $ROUTE_KEY_TBL;
                global $ROUTE_MPPS_TBL;
                $table = $mpps? $ROUTE_MPPS_TBL : $ROUTE_KEY_TBL;
                $key = array_search($keytag, $table);
                printf(pacsone_gettext("Route By <b>%s</b>:"), $key) . "</td>\n";
                $dvalue = $value;
                if ($dbcon->isEuropeanDateFormat() && in_array($keytag, $ROUTE_DATE_KEY_TBL))
                    $dvalue = reverseEmbedDate($value);
                print "<td>" . pacsone_gettext("Case-Insensitive Matching Pattern: \n");
                print "<input type='text' size=64 maxlength=255 name='pattern' value='$dvalue'>";
            }
            print "<input type=hidden name='source' value='$data'>\n";
            print "<input type=hidden name='keytag' value=$keytag>\n";
            print "<input type=hidden name='oldpattern' value=\"$value\">\n";
            print "</td></tr>\n";
        }
        print "<tr><td>";
        $data = $row['destination'];
        print "<input type=hidden name='destination' value='$data'>\n";
        if (strcmp($data, "_")) {
            print pacsone_gettext("Desination Application Entity Title:") . "</td>\n";
            $result = $dbcon->query("select description from applentity where title='$data'");
            if ($result && ($aerow = $result->fetch(PDO::FETCH_NUM))) {
                if (strlen($aerow[0]))
                    $data .= " - " . $aerow[0];
            }
            print "<td><b>$data</b></td>\n";
        } else {
            print pacsone_gettext("Desination Folder:") . "</td>\n";
            $value = $row['destfolder'];
            print "<td><input type='text' name='destfolder' value='$value' size=64 maxlength=256></td>";
            $data = "";
        }
        print "</tr>\n";
        // hourly schedule
        print "<tr><td>" . pacsone_gettext("Hourly Schedule:") . "</td>\n";
        $data = $row['schedule'];
        print "<input type=hidden name='oldschedule' value=$data>";
        $window = $row['schedwindow'];
        print "<input type=hidden name='oldwindow' value=$window>";
		$immediate = (($data == -1) && !$window)? "checked" : "";
		$hour = (($data >= 0) && !$window)? "checked" : "";
        $immedwindow = (($data == 0) && $window)? "checked" : "";
        $delaywindow = (($data == -2) && $window)? "checked" : "";
        $from = 0;
        $to = 23;
        if ($window) {
            $from = ($window & 0xFF00) >> 8;
            $to = ($window & 0x00FF);
        }
		$window = (($data == -1) && $window)? "checked" : "";
    	print "<td><input type='radio' name='schedule' value=-1 $immediate>";
        print pacsone_gettext("Immediately") . "<br>\n";
	    print "<input type='radio' name='schedule' value=2 $window>";
        print pacsone_gettext("Only when local time is from: \n");
	    print "<select name='from'>\n";
	    foreach ($HOUR_TBL as $key => $value) {
            if ($value == 24)
                break;
            $option = ($value == $from)? "<option selected>" : "<option>";
		    print "$option$key</option>";
        }
	    print "</select>\n";
        print pacsone_gettext(" To: ");
	    print "<select name='to'>\n";
	    foreach ($HOUR_TBL as $key => $value) {
            $option = ($value == $to)? "<option selected>" : "<option>";
		    print "$option$key</option>";
        }
	    print "</select><br>\n";
		print "<input type='radio' name='schedule' value=1 $hour>";
        print pacsone_gettext("Fixed At: \n");
		$hour = $data % 12;
		if ($hour == 0)
			$hour = 12;
		$am = ($data < 12)? "checked" : "";
		$pm = ($data < 12)? "" : "checked";
		print "<select name='hour'>\n";
		for ($i = 1; $i <= 12; $i++) {
			$selected = ($i == $hour)? "selected" : "";
			print "<option $selected>$i</option>";
		}
        print "</select> <input type='radio' name='ampm' value=0 $am>" . pacsone_gettext("A.M.");
		print "<input type='radio' name='ampm' value=1 $pm>" . pacsone_gettext("P.M.");
        print "<br><input type='radio' name='schedule' value=3 $immedwindow>";
        print pacsone_gettext("Immediately, but only during the following hourly window:");
        print "&nbsp;" . pacsone_gettext("From: \n");
	    print "<select name='schedulefrom'>\n";
	    foreach ($HOUR_TBL as $key => $value) {
            if ($value == 24)
                break;
            $option = ($value == $from)? "<option selected>" : "<option>";
		    print "$option$key</option>";
        }
	    print "</select>\n";
        print pacsone_gettext(" To: ");
	    print "<select name='scheduleto'>\n";
	    foreach ($HOUR_TBL as $key => $value) {
            $option = ($value == $to)? "<option selected>" : "<option>";
		    print "$option$key</option>";
        }
	    print "</select><br>\n";
	    print "<input type='radio' name='schedule' value=4 $delaywindow>";
        print pacsone_gettext("Delayed until the following hourly window:");
        print "&nbsp;" . pacsone_gettext("From: \n");
	    print "<select name='delayfrom'>\n";
	    foreach ($HOUR_TBL as $key => $value) {
            if ($value == 24)
                break;
            $option = ($value == $from)? "<option selected>" : "<option>";
		    print "$option$key</option>";
        }
	    print "</select>\n";
        print pacsone_gettext(" To: ");
	    print "<select name='delayto'>\n";
	    foreach ($HOUR_TBL as $key => $value) {
            $option = ($value == $to)? "<option selected>" : "<option>";
		    print "$option$key</option>";
        }
		print "</select><br>\n";
		print "</td></tr>\n";
        print "</tr>\n";
        // priority
        $data = $row['priority'];
        print "<tr><td>" . pacsone_gettext("Priority:") . "</td>\n";
        print "<td><input type='text' name='priority' value=$data size=3 maxlength=3>&nbsp;";
        print pacsone_gettext("Higher priority rules will create higher priority routing jobs which will be processed before those routing jobs with lower priorities");
	    print "</td></tr>\n";
        // weekday schedule
        $data = $row['weekday'];
        print "<input type=hidden name='oldweekday' value=$data>";
        print "<tr><td>" . pacsone_gettext("Weekday Schedule:") . "</td>\n";
        print "<td>";
        foreach ($WEEKDAY_MASK as $bit => $weekday) {
            $checked = ($bit & $data)? "checked" : "";
            print "<input type=checkbox name='weekday[]' value=$bit $checked>$weekday";
        }
	    print "</td></tr>\n";
        // retry interval
        $data = $row['retryinterval'];
        print "<tr><td>";
        print pacsone_gettext("Time Interval for Retrying Failed Routing Jobs:") . "</td>\n";
        print "<td><input type='text' name='retryinterval' size=2 value=$data>&nbsp;";
        print pacsone_gettext("hours");
	    print "</td></tr>\n";
        if (!$mpps) {
            // purge received images after routing
            $data = $row['autopurge'];
            $yes = ($data)? "checked" : "";
            $no = ($data == 0)? "checked" : "";
            print "<tr><td>";
            print pacsone_gettext("Purge Received Images After Routing:") . "</td>\n";
            print "<td><input type='radio' name='autopurge' value=1 $yes>";
            print pacsone_gettext("Yes") . "<br>\n";
            print "<input type='radio' name='autopurge' value=0 $no>";
            print pacsone_gettext("No") . "<br>\n";
	        print "</td></tr>\n";
        }
        // more options
        print "<tr><td>" . pacsone_gettext("More Options:") . "</td>\n";
        print "<td>";
        if (!$mpps) {
            $data = $row['fetchmore'];
            $checked = ($data)? "checked" : "";
            $value = $data? (0 - $data) : 1;
            print "<input type=checkbox name='fetchmore' value=1 $checked> ";
            printf(pacsone_gettext("Forward Existing <input type=text name='studies' value=\"%d\" size=2 maxlength=2> Oldest Studies To Destination AE"), $value);
            $data = $row['delayedstudy'];
            $checked = $data? "checked" : "";
            global $DEFAULT_STUDY_WAIT;
            if (!$data)
                $data = $DEFAULT_STUDY_WAIT;
            print "<br><input type=checkbox name='delayedstudy' value=1 $checked> ";
            printf(pacsone_gettext("Wait <input type=text name='waitmins' value=%.3f size=6 maxlength=6> minutes for all instances of the study to be received, and forward the entire study instead of individual images"), $data);
            $data = $row['delayedseries'];
            $checked = $data? "checked" : "";
            global $DEFAULT_SERIES_WAIT;
            if (!$data)
                $data = $DEFAULT_SERIES_WAIT;
            print "<br><input type=checkbox name='delayedseries' value=1 $checked> ";
            printf(pacsone_gettext("Wait <input type=text name='seriesmins' value=%.3f size=6 maxlength=6> minutes for all instances of the series to be received, and forward the entire series instead of individual images"), $data);
        }
        // sending AE Title
        $data = $row['sendingaet'];
        $checked = strlen($data)? "checked" : "";
        print "<br><input type=checkbox name='usesendingaet' $checked> ";
        printf(pacsone_gettext("Do not use the AE Title assigned to %s"), $PRODUCT);
        print "<br>&nbsp;&nbsp;&nbsp;";
        $checked = strcasecmp($data, "\$SOURCE\$")? "" : "checked";
        print "<input type='radio' name='usewhichaet' value=1 $checked>";
        print pacsone_gettext("Use the Original or Source AE Title When Sending To Destination AE");
        print "<br>&nbsp;&nbsp;&nbsp;";
        $checked = strcasecmp($data, "\$SOURCE\$")? "checked" : "";
        print "<input type='radio' name='usewhichaet' value=0 $checked>";
        print pacsone_gettext("Use This AE Title: <input type=text name='sendingaet' size=16 maxlength=16 value='$data'> When Sending To Destination AE");
        print "</td></tr>\n";
        if (!$mpps) {
            // preferred transfer syntax
            $data = isset($row['xfersyntax'])? $row['xfersyntax'] : "";
            print "<tr><td>" . pacsone_gettext("Preferred Dicom Transfer Syntax When Sending To Destination AE:") . "</td>\n";
            global $XFER_SYNTAX_TBL;
            print "<td><select name='xfersyntax'>";
            $selected = (strlen($data) && array_key_exists($data, $XFER_SYNTAX_TBL))? "" : "selected";
            print "<option $selected>" . pacsone_gettext("None") . "</option>";
            foreach ($XFER_SYNTAX_TBL as $key => $syntax) {
                $selected = strcasecmp($data, $key)? "" : "selected";
                print "<option $selected>" . $XFER_SYNTAX_TBL[$key][2] . " - $key</option>";
            }
            print "</select></td>\n";
            print "</td></tr>\n";
        }
        print "</table>\n";
        print "<p><input type='submit' value='";
        print pacsone_gettext("Modify");
        print "'>\n";
        print "</form>\n";
    }
    else {
        print "<h3><font color=red>";
        printf(pacsone_gettext("<u>%s</u> not found in database"), $source);
        print "</font></h3>\n";
    }
	require_once 'footer.php';
    print "</body>\n";
    print "</html>\n";
}

function toggleEntries($entry, $enabled, $mpps)
{
    global $dbcon;
	$ok = array();
	$errors = array();
	foreach ($entry as $value) {
		$aes = explode("||", $value);
		$source = $aes[0];
		$key = $aes[1];
		$dest = $aes[2];
		$window = $aes[3];
        $pattern = isset($aes[4])? $aes[4] : "";
		$schedule = $aes[5];
		$weekday = $aes[6];
        $table = $mpps? "mppsroute" : "autoroute";
		$query = "update $table set enabled=? where keytag=? and schedule=? and ";
        $bindList = array($enabled, $key, $schedule);
        if (strlen($pattern)) {
            $query .= "pattern=? and ";
            $bindList[] = $pattern;
        }
        $query .= "destination=? and source=? and schedwindow=? and weekday=?";
        array_push($bindList, $dest, $source, $window, $weekday);
		if (!$dbcon->preparedStmt($query, $bindList)) {
			$errors[$value] = sprintf(pacsone_gettext("Database Error: %s"), $dbcon->getError());
		}
		else
			$ok[] = $value;
	}
	$result = "";
	if (!empty($errors)) {
        if (count($errors) == 1) {
            if ($enabled)
		        $result = pacsone_gettext("Error enabling the following Routing Entry:");
            else
		        $result = pacsone_gettext("Error disabling the following Routing Entry:");
        } else {
            if ($enabled)
		        $result = pacsone_gettext("Error enabling the following Routing Entries:");
            else
		        $result = pacsone_gettext("Error disabling the following Routing Entries:");
        }
        $result .= "<P>\n";
		foreach ($errors as $key => $value) {
			$result .= "<h3><font color=red><u>$key</u> - $value</font></h3><P>\n";
		}
	}
    return $result;
}

?>
