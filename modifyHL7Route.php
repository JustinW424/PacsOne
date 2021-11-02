<?php
//
// modifyHL7Route.php
//
// Module for managing the HL-7 Message Routing Table
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();
ob_start();

require_once 'locale.php';
include_once 'database.php';
include_once 'utils.php';
include_once 'sharedData.php';
include_once 'checkUncheck.js';

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
if (isset($_GET['source']) && isset($_GET['destination']) &&
    isset($_GET['keyname']) ) {
    $source = $_GET['source'];
    $destination = $_GET['destination'];
    $keyname = $_GET['keyname'];
    $window = $_GET['window'];
    if (isset($_GET['enabled'])) {
        $enabled = $_GET['enabled'];
        $query = "UPDATE hl7route SET enabled=? WHERE source=? AND ";
        $query .= "destination=? AND keyname=? and schedwindow=?";
        $bindList = array($enabled, $source, $destination, $keyname, $window);
        if (!$dbcon->preparedStmt($query, $bindList)) {
            print "<h3><font color=red>";
            printf(pacsone_gettext("Error running query: %s"), $query) . "<br>";
            printf(pacsone_gettext("Database Error: %s"), $dbcon->getError());
            print "</font></h3><br>";
            exit();
        }
        $result = "";
    } else {
        modifyEntryForm($source, $destination, $keyname, $window);
    }
} else {
    if (isset($action) && strcasecmp($action, "Delete") == 0) {
	    $result = deleteEntries($username, $entry);
    }
    else if (isset($action) && strcasecmp($action, "Enable All") == 0) {
	    $result = toggleEntries($entry, 1);
    }
    else if (isset($action) && strcasecmp($action, "Disable All") == 0) {
	    $result = toggleEntries($entry, 0);
    }
    else if (isset($action) && strcasecmp($action, "Add") == 0) {
	    if (isset($source)) {
	        $result = addEntry($username);
        }
        else {
            addEntryForm();
        }
    }
    else if (isset($action) && strcasecmp($action, "Modify") == 0) {
	    $result = modifyEntry($username);
    }
}
if (isset($result)) {       // display results
    if (empty($result)) {   // success
        header('Location: autoroute.php');
        ob_end_clean();
        exit();
    }
    else {                  // error
        ob_end_flush();
        print "<html>\n";
        print "<head><title>$PRODUCT - ";
        print pacsone_gettext("HL7 Message Route Error");
        print "</title></head>\n";
        print "<body>\n";
        require_once 'header.php';
        print $result;
		require_once 'footer.php';
        print "</body>\n";
        print "</html>\n";
    }
}

function deleteEntries($username, $entry)
{
    global $dbcon;
	$ok = array();
	$errors = array();
	$bindList = array();
	foreach ($entry as $value) {
		$aes = explode(";", $value);
		$source = $aes[0];
		$key = $aes[1];
		$dest = $aes[2];
		$window = $aes[3];
        if (strlen($key) == 0) {
		    $query = "delete from hl7route where source=? ";
            $bindList[] = $source;
        } else {
		    $query = "delete from hl7route where keyname=? ";
            $bindList[] = $key;
        }
        $query .= "and destination=? and schedwindow=?";
        array_push($bindList, $dest, $window);
		if (!$dbcon->preparedStmt($query, $bindList)) {
			$errors[$value] = sprintf(pacsone_gettext("Database Error: %s"), $dbcon->getError());
		}
		else {
			$ok[] = $value;
            $log = array(
                "source"        => $source,
                "keyname"       => $key,
                "pattern"       => "",
                "destination"   => $dest);
            // log activity to system journal
            $dbcon->logJournal($username, "Delete", "HL7Route", $log);
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

function addEntry($username)
{
    if (!isset($_POST['destination']))
		return ("<h3><font color=red>" . pacsone_gettext("Destination HL7 Application must be defined.") . "</font></h3>");

    global $dbcon;
    global $HOUR_TBL;
    global $HL7ROUTE_KEY_TBL;
    $result = "";
	$routeBy = $_POST['routeby'];
	$source = ($routeBy == 0)? $_POST['source'] : "_Any";
    if (strcmp($source, "*") == 0) {
        $source = "_Any";
    }
	$pattern = $_POST['pattern'];
	$destination = $_POST['destination'];
    $columns = array();
    $bindList = array();
    $query = "insert into hl7route (";
    $columns[] = "source";
    $values = "?";
    $bindList[] = $source;
    $columns[] = "destination";
    $values .= ",?";
    $bindList[] = $destination;
    if ($routeBy) {
        $keyname = $_POST['keyname'];
        $key = $HL7ROUTE_KEY_TBL[$keyname];
        if (strlen($pattern) == 0) {
		    $result .= "<h3><font color=red>";
            $result .= pacsone_gettext("Key Matching Pattern Must Be Defined.");
            $result .= "</font></h3>";
            return $result;
        }
        $columns[] = "keyname";
        $values .= ",?";
        $bindList[] = $key;
        $columns[] = "pattern";
        $values .= ",?";
        $bindList[] = $pattern;
    } else if (strlen($source) == 0) {
		$result .= "<h3><font color=red>";
        $result .= pacsone_gettext("A Source HL7 Application Must Be Defined.");
        $result .= "</font></h3>";
        return $result;
    }
	if (isset($_POST['schedule'])) {
		$schedule = $_POST['schedule'];
		if ($schedule == 1) {	// not immediately
			$schedule = hour2schedule($_POST['hour'], $_POST['ampm']);
		} else if ($schedule == 2) {
            $schedule = -1;
            $from = $HOUR_TBL[ $_REQUEST['from'] ];
            $to = $HOUR_TBL[ $_REQUEST['to'] ];
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
    for ($i = 0; $i < count($columns); $i++) {
        if ($i)
            $query .= ",";
        $query .= $columns[$i];
    }
    $query .= ") values($values)";
    // execute SQL query
    if (!$dbcon->preparedStmt($query, $bindList)) {
        $result .= "<h3><font color=red>";
        $result .= sprintf(pacsone_gettext("Error adding HL7 Message Routing Entity for <u>%s</u>: "), $source);
        $result .= $dbcon->getError();
        $result .= "</font></h3><p>\n";
    } else {
        $log = array(
            "source"        => $source,
            "keyname"       => $routeBy? $key : "",
            "pattern"       => $pattern,
            "destination"   => $destination);
        // log activity to system journal
        $dbcon->logJournal($username, "Add", "HL7Route", $log);
    }
    return $result;
}

function modifyEntry($username)
{
    global $dbcon;
    global $HOUR_TBL;
	if (!isset($_POST['source']) || !isset($_POST['destination']))
		return ("<h3><font color=red>" . pacsone_gettext("Source and Destination HL7 Application must be defined.") . "</font></h3>");

    $result = "";
	$source = $_POST['source'];
	$keyname = $_POST['keyname'];
	$pattern = $_POST['pattern'];
	$destination = $_POST['destination'];
    $bindList = array();
	$query = "update hl7route set ";
    if (strlen($keyname)) {
        if (strlen($pattern) == 0) {
		    $result .= "<h3><font color=red>";
            $result .= pacsone_gettext("Key Matching Pattern Must Be Defined.");
            print "</font></h3>";
            return $result;
        }
        $query .= "pattern=?,";
        $bindList[] = $pattern;
    }
	if (isset($_POST['schedule'])) {
		$schedule = $_POST['schedule'];
        $window = 0;
		if ($schedule == 1) {
			$schedule = hour2schedule($_POST['hour'], $_POST['ampm']);
		} else if ($schedule == 2) {
            $schedule = -1;
            $from = $HOUR_TBL[ $_POST['from'] ];
            $to = $HOUR_TBL[ $_POST['to'] ];
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
    // get rid of the last ','
    $npos = strrpos($query, ",");
    if ($npos != false)
        $query = substr($query, 0, $npos);
    if ($keyname) {
        $query .= " where keyname=? ";
        $bindList[] = $keyname;
    } else {
        $query .= " where source=? ";
        $bindList[] = $source;
    }
    $query .= "and destination=?";
    $bindList[] = $destination;
    // execute SQL query
    if (!$dbcon->preparedStmt($query, $bindList)) {
        $result .= "<h3><font color=red>";
        $result .= sprintf(pacsone_gettext("Error updating HL7 Message Routing Entity for <u>%s</u>: "), $source);
        $result .= $dbcon->getError();
        $result .= "</font></h3><p>\n";
    } else {
        $log = array(
            "source"        => $source,
            "keyname"       => $keyname,
            "pattern"       => $pattern,
            "destination"   => $destination);
        // log activity to system journal
        $dbcon->logJournal($username, "Modify", "HL7Route", $log);
    }
    return $result;
}

function addEntryForm()
{
    global $PRODUCT;
    global $dbcon;
    global $HOUR_TBL;
    global $HL7ROUTE_KEY_TBL;
    // display Add New HL-7 Message Routing Entity form
    print "<html>\n";
    print "<head><title>$PRODUCT - ";
    print pacsone_gettext("Add HL7 Message Route");
    print "</title></head>\n";
    print "<body>\n";
    require_once 'header.php';
	print pacsone_gettext("<P>Once a routing entry is defined, all HL7 Messages received from the <b>SOURCE</b> Application will automatically be forwarded to the <b>DESTINATION</b> Application with the specified <b>SCHEDULE</b>.<P>");
    print "<form method='POST' action='modifyHL7Route.php'>\n";
    print "<input type='hidden' name='actionvalue'>\n";
    print "<table cellspacing=10 cellpadding=0>\n";
    print "<tr><td>" . pacsone_gettext("Select Routing Criteria:") . "</td>\n";
    print "<td><input type=radio name='routeby' value=0 checked>";
    print pacsone_gettext("By Source Application Name:\n");
    print "<input type='text' size=32 maxlength=32 name='source'>";
    print pacsone_gettext(" (Wild-Card Characters <b>*</b> and <b>?</b> Are Supported)");
    print "<br>\n";
    print "<input type=radio name='routeby' value=1>";
    print pacsone_gettext("By This Key in Message:\n");
    print "<select name='keyname'>\n";
    foreach ($HL7ROUTE_KEY_TBL as $descp => $key) {
        print "<option>$descp</option>\n";
    }
    print "</select><br>\n";
    print pacsone_gettext("with Case-Insensitive Matching Pattern:");
    print " <input type='text' size=16 maxlength=64 name='pattern'>\n";
    print pacsone_gettext(" (Wild-Card Characters <b>*</b> and <b>?</b> Are Supported)");
    print "<br>\n";
    print "</td></tr>\n";
	// query database for the list of eligible destinations
	$result = $dbcon->query("select name from hl7application where port is not null");
    print "<tr><td>";
    print pacsone_gettext("Enter Destination HL7 Application:") . "</td>\n";
    print "<td><select name='destination'>\n";
	while ($name = $result->fetchColumn())
		print "<option>$name</option>";
	print "</select></td></tr>\n";
	print "<tr></tr>\n";
    print "<tr><td>" . pacsone_gettext("Enter Routing Schedule:") . "</td>\n";
    print "<td><input type='radio' name='schedule' value=-1 checked>";
    print pacsone_gettext("Immediately (As soon as received)") . "<br>\n";
	print "<input type='radio' name='schedule' value=2>";
    print pacsone_gettext("From: \n");
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
	print "</select> <input type='radio' name='ampm' value=0 checked>A.M. \n";
	print "<input type='radio' name='ampm' value=1>P.M.<br>\n";
	print "</td></tr>\n";
    print "</table>\n";
    print "<p><input type='submit' name='action' value='";
    print pacsone_gettext("Add");
    print "' onclick='switchText(this.form,\"actionvalue\",\"Add\")'></form>\n";
	require_once 'footer.php';
    print "</body>\n";
    print "</html>\n";
}

function modifyEntryForm($source, $destination, $keyname, $window)
{
    global $PRODUCT;
    global $HL7ROUTE_KEY_TBL;
    global $dbcon;
    global $HOUR_TBL;
    // display Modify Automatic Routing Entity form
    print "<html>\n";
    print "<head><title>$PRODUCT - ";
    print pacsone_gettext("Modify HL7 Message Route");
    print "</title></head>\n";
    print "<body>\n";
    require_once 'header.php';
    $query = "select * from hl7route where ";
    $bindList = array();
    if (strlen($keyname) == 0) {
        $query .= "source=? ";
        $bindList[] = $source;
    } else {
        $query .= "keyname=? ";
        $bindList[] = $keyname;
    }
    $query .= "and destination=? and schedwindow=?";
    $array_push($bindList, $destination, $window);
    $result = $dbcon->preparedStmt($query, $bindList);
    if ($result->rowCount() == 1) {
        $row = $result->fetch(PDO::FETCH_ASSOC);
        print "<form method='POST' action='modifyHL7Route.php'>\n";
        print "<table cellspacing=10 cellpadding=0>\n";
        print "<input type=hidden name='action' value='Modify'>\n";
        print "<input type='hidden' name='actionvalue' value='Modify'>\n";
        $data = $row['source'];
        if (strlen($keyname) == 0) {
            print "<tr><td>";
            print pacsone_gettext("Route By Source HL-7 Application Name:") . "</td>\n";
            $value = (strlen($data) && ($data[0] == '_'))?
                pacsone_gettext("Any") : $data;
            print "<td><b>$value</b></td>\n";
            $value = "<input type=hidden name='source' ";
            if (isset($data))
                $value .= "value='$data' ";
            $value .= "\n";
            print $value;
            print "<input type=hidden name='keyname' value=''>\n";
            print "</tr>\n";
        } else {
            $key = array_search($keyname, $HL7ROUTE_KEY_TBL);
            print "<tr><td>";
            printf(pacsone_gettext("Route By <b>%s</b>:"), $key) . "</td>\n";
            print "<input type=hidden name='source' value='$data'>\n";
            print "<input type=hidden name='keyname' value=$keyname>\n";
            $value = $row["pattern"];
            print "<td>" . pacsone_gettext("Case-Insensitive Matching Pattern: \n");
            print "<input type='text' size=16 maxlength=64 name='pattern' value='$value'";
            print "</td></tr>\n";
        }
        print "<tr><td>" . pacsone_gettext("Desination HL7 Application:") . "</td>\n";
        $data = $row['destination'];
        print "<td><b>$data</b></td>\n";
        $value = "<input type=hidden name='destination' ";
        if (isset($data))
            $value .= "value='$data' ";
        $value .= "\n";
        print $value;
        print "</tr>\n";
        print "<tr><td>" . pacsone_gettext("Schedule:") . "</td>\n";
        $data = $row['schedule'];
		$immediate = ($data == -1)? "checked" : "";
        $window = $row['schedwindow'];
		$hour = (($data >= 0) && !$window)? "checked" : "";
        $from = 0;
        $to = 23;
        if ($window) {
            $from = ($window & 0xFF00) >> 8;
            $to = ($window & 0x00FF);
        }
		$window = ($window)? "checked" : "";
    	print "<td><input type='radio' name='schedule' value=-1 $immediate>";
        print pacsone_gettext("Immediately") . "<br>\n";
	    print "<input type='radio' name='schedule' value=2 $window>";
        print pacsone_gettext("From: \n");
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
		print "</select> <input type='radio' name='ampm' value=0 $am>A.M. \n";
		print "<input type='radio' name='ampm' value=1 $pm>P.M.\n";
		print "</td></tr>\n";
        print "</tr>\n";
        print "</table>\n";
        print "<p><input type='submit' value='";
        print pacsone_gettext("Modify");
        print "'></form>\n";
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

function toggleEntries($entry, $enabled)
{
    global $dbcon;
	$ok = array();
	$errors = array();
	foreach ($entry as $value) {
		$aes = explode(";", $value);
		$source = $aes[0];
		$key = $aes[1];
		$dest = $aes[2];
		$window = $aes[3];
		$query = "update hl7route set enabled=? where keyname=? and ";
        $bindList = array($enabled, $key);
        $query .= "destination=? and source=? and schedwindow=?";
        array_push($bindList, $dest, $source, $window);
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
