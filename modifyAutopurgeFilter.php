<?php
//
// modifyAutopurgeFilter.php
//
// Module for modifying automatic purging by data element filters
//
// CopyRight (c) 2004-2020 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once 'database.php';
include_once 'sharedData.php';
include_once 'utils.php';

// main
global $PRODUCT;
$dbcon = new MyConnection();
if (isset($_POST['action']))
   	$action = $_POST['actionvalue'];
if (isset($_POST['entry']))
   	$entry = $_POST['entry'];
$result = NULL;
if (isset($_GET['key'])) {
    modifyEntryForm(urldecode($_GET['key']));
}
else {
    if (isset($action) && strcasecmp($action, "Delete") == 0) {
	    $result = deleteEntries($entry);
    }
    else if (isset($action) && strcasecmp($action, "Add") == 0) {
	    if (isset($_POST['tag'])) {
	        $result = addEntry();
        }
        else {
            addEntryForm();
        }
    }
    else if (isset($action) && strcasecmp($action, "Modify") == 0) {
	    $result = modifyEntry();
    }
}
if (isset($result)) {       // display results
    if (empty($result)) {   // success
        $title = pacsone_gettext("Automatic Purge Storage Directories");
        header('Location: tools.php?page=' . urlencode($title));
    }
    else {                  // error
        print "<html>\n";
        print "<head><title>$PRODUCT - ";
        print pacsone_gettext("Automatic Purging Filter Error");
        print "</title></head>\n";
        print "<body>\n";
        require_once 'header.php';
        print $result;
		require_once 'footer.php';
        print "</body>\n";
        print "</html>\n";
    }
}

function deleteEntries($entry)
{
    global $dbcon;
	$ok = array();
	$errors = array();
	foreach ($entry as $value) {
        $tokens = explode("|", urldecode($value));
        $dir = $tokens[0];
        $tag = $tokens[1];
        $pattern = $tokens[2];
		$query = "delete from autopurge where directory=? and tag=? and pattern=?";
        $bindList = array($dir, $tag, $pattern);
		if (!$dbcon->preparedStmt($query, $bindList)) {
			$errors[$value] = sprintf(pacsone_gettext("Database Error: %s"), $dbcon->getError());
		}
		else
			$ok[] = $value;
	}
	$result = "";
	if (!empty($errors)) {
        if (count($errors) == 1)
		    $result = pacsone_gettext("Error deleting the following Automatic Purging Filter:");
        else
		    $result = pacsone_gettext("Error deleting the following Automatic Purging Filters:");
        $result .= "<P>\n";
		foreach ($errors as $key => $value) {
			$result .= "<h3><font color=red><u>$key</u> - $value</font></h3><P>\n";
		}
	}
    return $result;
}

function addEntry()
{
    global $dbcon;
    $result = "";
    $dir = $_POST['directory'];
    $aging = $_POST['aging'];
	$tokens = array();
	preg_match("/^(.*)\((.*)\)$/", $_POST['tag'], $tokens);
	$tag = hexdec($tokens[2]);
    $query = "insert into autopurge (directory,tag,pattern,aging,description,schedule,enable,delpatient) values(";
    $query .= "?,?,?,?,";
    $bindList = array($dir, $tag, $_POST['pattern'], $aging);
	if (isset($_POST['description'])) {
		$query .= "?,";
        $bindList[] = $_POST['description'];
    }
	else
		$query .= "NULL,";
	$schedule = hour2schedule($_POST['hour'], $_POST['ampm']);
	$query .= "?,1,?)";
    array_push($bindList, $schedule, $_POST['delpatient']);
	// execute SQL query
	if (!$dbcon->preparedStmt($query, $bindList)) {
		$result .= "<h3><font color=red>";
        $result .= sprintf(pacsone_gettext("Error adding Automatic Purging Filter for (0x%08x) - %s: "), $tag, $pattern);
        $result .= "SQL Query = [$query]<br>";
		$result .= $dbcon->getError();
        $result .= "</font></h3><p>\n";
	}
    return $result;
}

function modifyEntry()
{
    global $dbcon;
    $key = $_POST['key'];
    $tokens = explode("|", $key);
    $dir = $tokens[0];
	$tag = $tokens[1];
    $pattern = $tokens[2];
    $result = "";
	$query = "update autopurge set pattern=?,";
	$schedule = hour2schedule($_POST['hour'], $_POST['ampm']);
    $query .= "schedule=?,aging=?,";
    $bindList = array($_POST['pattern'], $schedule, $_POST['aging']);
	if (isset($_POST['description'])) {
	    $query .= "description=?,";
        $bindList[] = $_POST['description'];
    }
    else
        $query .= "description=NULL,";
    $query .= "delpatient=?,";
    $bindList[] = $_POST['delpatient'];
    // replace the last ',' with ';'
    $npos = strrpos($query, ",");
    if ($npos != false)
        $query = substr($query, 0, $npos);
    $query .= " where directory=? and tag=? and pattern=?";
    array_push($bindList, $dir, $tag, $pattern);
	// execute SQL query
	if (!$dbcon->preparedStmt($query, $bindList)) {
		$result .= "<h3><font color=red>";
        $result .= sprintf(pacsone_gettext("Error updating Automatic Purging Filter for (0x%08x) - %s: "), $tag, $pattern);
        $result .= $dbcon->getError();
        $result .= "</font></h3><p>\n";
	}
    return $result;
}

function addEntryForm()
{
    include_once 'checkUncheck.js';
    global $PRODUCT;
    global $AUTOPURGE_FILTER_TBL;
    // display Add New Application Entity form
    print "<html>\n";
    print "<head><title>$PRODUCT - ";
    print pacsone_gettext("Add Automatic Purging By Data Element Filter");
    print "</title></head>\n";
    print "<body>\n";
    require_once 'header.php';
    print "<form method='POST' action='modifyAutopurgeFilter.php'>\n";
    print "<input type='hidden' name='actionvalue'>\n";
    print "<table>\n";
    print "<input type='hidden' name='directory' value='_all'>\n";
    print "<tr><td>";
    print pacsone_gettext("Data Element Tag:") . "</td>\n";
    print "<td><select name='tag'>";
    foreach ($AUTOPURGE_FILTER_TBL as $tag => $desc) {
        printf("<option>%s (0x%08x)</option>", $desc, $tag);
    }
    print "</select></td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Filter Pattern:") . "</td>\n";
    print "<td><input type='text' size=32 maxlength=32 name='pattern'></td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Description:") . "</td>\n";
    print "<td><input type='text' size=64 maxlength=64 name='description'></td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("24-Hour Schedule:") . "</td>\n";
    print "<td><select name='hour'>\n";
    for ($i = 1; $i <= 12; $i++)
    	print "<option>$i</option>";
    print "</select> <input type='radio' name='ampm' value=0 checked>";
    print pacsone_gettext("A.M. ");
    print "<input type='radio' name='ampm' value=1>";
    print pacsone_gettext("P.M.");
    print "</td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Aging Period:") . "</td>\n";
    print "<td>";
    printf(pacsone_gettext("Delete matching images after %s days"),
        "<input type='text' size=8 maxlength=8 name='aging' value=100>");
    print "</td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Delete patient record after all studies of the patient have been purged:") . "</td>\n";
    print "<td><input type='radio' name='delpatient' value=1>";
    print pacsone_gettext("Yes");
    print "&nbsp;<input type='radio' name='delpatient' value=0 checked>";
    print pacsone_gettext("No");
    print "</td></tr>\n";
    print "</table>\n";
    print "<p><input class='btn btn-primary' type='submit' name='action' value='";
    print pacsone_gettext("Add");
    print "' onclick='switchText(this.form,\"actionvalue\",\"Add\")'></form>\n";
	require_once 'footer.php';
    print "</body>\n";
    print "</html>\n";
}

function modifyEntryForm($entry)
{
    include_once 'checkUncheck.js';
    global $PRODUCT;
    global $dbcon;
    global $AUTOPURGE_FILTER_TBL;
    // display Modify Automatic Purging by Data Element Filter form
    print "<html>\n";
    print "<head><title>$PRODUCT - ";
    pacsone_gettext("Modify Automatic Purging By Data Element Coercion Filter: %s");
    print "</title></head>\n";
    print "<body>\n";
    require_once 'header.php';
    $tokens = explode("|", $entry);
    $dir = $tokens[0];
    $tag = $tokens[1];
    $pattern = $tokens[2];
    $query = "select * from autopurge where directory=? and tag=? and pattern=?";
    $bindList = array($dir, $tag, $pattern);
    $result = $dbcon->preparedStmt($query, $bindList);
    if ($result->rowCount() == 1) {
        $row = $result->fetch(PDO::FETCH_ASSOC);
        print "<form method='POST' action='modifyAutopurgeFilter.php'>\n";
        print "<input type='hidden' name='actionvalue'>\n";
        print "<input type='hidden' name='key' value='$entry'>\n";
        print "<table>\n";
        print "<input type='hidden' name='directory' value='_all'>\n";
        print "<tr><td>";
        print pacsone_gettext("Data Element Tag:") . "</td>\n";
        $data = $row['tag'];
        $desc = $AUTOPURGE_FILTER_TBL[$data];
        $value = sprintf("%s (0x%08x)", $desc, $data);
    	print "<td><input type='text' name='tag' value='$value' readonly>";
        print "</td></tr>\n";
        print "</tr>\n";
        print "<tr><td>";
        print pacsone_gettext("Filter Pattern:") . "</td>\n";
        $data = $row['pattern'];
        $value = "<td><input type='text' size=32 maxlength=32 name='pattern'";
        if (isset($data))
            $value .= "value='$data'";
        $value .= "></td>\n";
        print $value;
        print "</tr>\n";
        print "<tr><td>";
        print pacsone_gettext("Filter Pattern Description:") . "</td>\n";
        $data = $row['description'];
        $value = "<td><input type='text' size=64 maxlength=64 name='description'";
        if (isset($data))
            $value .= "value='$data'";
        $value .= "></td>\n";
        print $value;
        print "</tr>\n";
        $schedule = $row['schedule'];
        $hour = $schedule % 12;
        if ($hour == 0)
            $hour = 12;
        $ampm = ($schedule >= 12)? 1 : 0;
        $checkedAM = ($ampm)? "" : "checked";
        $checkedPM = ($ampm)? "checked" : "";
        print "<tr><td>";
        print pacsone_gettext("24-Hour Schedule:") . "</td>\n";
        print "<td><select name='hour'>\n";
        print "<option selected>$hour</option>";
        for ($i = 1; $i <= 12; $i++)
            if ($i != $hour)
    	        print "<option>$i</option>";
        print "</select> <input type='radio' name='ampm' value=0 $checkedAM>";
        print pacsone_gettext("A.M. ");
        print "<input type='radio' name='ampm' value=1 $checkedPM>";
        print pacsone_gettext("P.M.");
        print "</td></tr>\n";
        print "<tr><td>";
        print pacsone_gettext("Aging Period:") . "</td>\n";
        print "<td>";
        $aging = $row['aging'];
        printf(pacsone_gettext("Delete matching images after <input type='text' size=8 maxlength=8 name='aging' value=%d>days"), $aging);
        print "</td></tr>\n";
        $delpatient = $row['delpatient'];
        $yes = $delpatient? "checked" : "";
        $no = $delpatient? "" : "checked";
        print "<tr><td>";
        print pacsone_gettext("Delete patient record after all studies of the patient have been purged:") . "</td>\n";
        print "<td><input type='radio' name='delpatient' value=1 $yes>";
        print pacsone_gettext("Yes");
        print "&nbsp;<input type='radio' name='delpatient' value=0 $no>";
        print pacsone_gettext("No");
        print "</td></tr>\n";
        print "</table>\n";
        print "<p><input class='btn btn-primary' type='submit' name='action' value='";
        print pacsone_gettext("Modify");
        print "' onclick='switchText(this.form,\"actionvalue\",\"Modify\")'>\n";
        print "</form>\n";
    } else {
        print "<h3><font color=red>";
        printf(pacsone_gettext("<u>Automatic Purging Filter for: (0x%08x) - %s not found in database!"), $tag, $pattern);
        print "</font></h3>\n";
    }
	require_once 'footer.php';
    print "</body>\n";
    print "</html>\n";
}

?>
