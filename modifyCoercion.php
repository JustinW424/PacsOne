<?php
//
// modifyCoercion.php
//
// Module for modifying entries in the Data Element Coercion Table
//
// CopyRight (c) 2004-2020 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once 'database.php';
include_once 'sharedData.php';

// main
global $PRODUCT;
$dbcon = new MyConnection();
if (isset($_POST['title']))
	$title = $_POST['title'];
if (isset($_POST['action']))
   	$action = $_POST['actionvalue'];
if (isset($_POST['entry']))
   	$entry = $_POST['entry'];
$result = NULL;
if (isset($_GET['title'])) {
   	$seq = $_GET['seq'];
    modifyEntryForm($_GET['title'], $seq);
}
else {
    if (isset($action) && strcasecmp($action, "Delete") == 0) {
	    $result = deleteEntries($entry);
    }
    else if (isset($action) && strcasecmp($action, "Add") == 0) {
	    if (isset($title)) {
	        $result = addEntry($title);
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
        header('Location: tools.php?page=' . urlencode(pacsone_gettext("Data Element Coercion")));
    }
    else {                  // error
        print "<html>\n";
        print "<head><title>$PRODUCT - ";
        print pacsone_gettext("Coercion Table Error");
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
        $tokens = explode(" ", urldecode($value));
        $title = $tokens[0];
        $seq = $tokens[1];
		$query = "delete from coercion where aetitle=? and sequence=?";
        $bindList = array($title, $seq);
		if (!$dbcon->preparedStmt($query, $bindList)) {
			$errors[$title] = sprintf(pacsone_gettext("Database Error: %s"), $dbcon->getError());
		}
		else {
			$ok[] = $title;
            $username = $dbcon->username;
            // log activity to system journal
            $what = "Source AE Title: $title Seq: $seq";
            $dbcon->logJournal($username, "Delete", "Coercion", $what);
        }
	}
	$result = "";
	if (!empty($errors)) {
        if (count($errors) == 1)
		    $result = pacsone_gettext("Error deleting the following Coercion Rule:");
        else
		    $result = pacsone_gettext("Error deleting the following Coercion Rules:");
        $result .= "<P>\n";
		foreach ($errors as $key => $value) {
			$result .= "<h3><font color=red><u>$key</u> - $value</font></h3><P>\n";
		}
	}
    return $result;
}

function addEntry($title)
{
    global $dbcon;
    $result = "";
    $columns = array("aetitle");
    $values = "?";
    $bindList = array($title);
	$query = "insert into coercion (";
	$tokens = array();
	preg_match("/^(.*)\((.*)\)$/", $_POST['tag'], $tokens);
    $columns[] = "tag";
    $values .= ",?";
    $bindList[] = hexdec($tokens[2]);
	$columns[] = "syntax";
	if (isset($_POST['syntax']) && strlen($_POST['syntax'])) {
        $values .= ",?";
		$bindList[] = $_POST['syntax'];
    } else
		$values .= ",NULL";
	$columns[] = "description";
	if (isset($_POST['description'])) {
        $values .= ",?";
		$bindList[] = $_POST['description'];
    }
	else
		$values .= ",NULL";
    if ($_POST['coerceby']) {
        unset($tokens);
	    preg_match("/^(.*)\((.*)\)$/", $_POST['keytag'], $tokens);
        $columns[] = "keytag";
        $values .= ",?";
        $bindList[] = hexdec($tokens[2]);
	    if (isset($_POST['pattern']) && strlen($_POST['pattern'])) {
	        $columns[] = "pattern";
            $values .= ",?";
		    $bindList[] = $_POST['pattern'];
        }
    }
	if (isset($_POST['sequence'])) {
	    $columns[] = "sequence";
        $values .= ",?";
		$bindList[] = $_POST['sequence'];
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
        $result .= sprintf(pacsone_gettext("Error adding Coercion Rule for <u>%s</u>: "), $title);
        $result .= "SQL Query = [$query]<br>";
		$result .= $dbcon->getError();
        $result .= "</font></h3><p>\n";
	} else {
        $username = $dbcon->username;
        // log activity to system journal
        $what = "Source AE Title: $title Tag: " . hexdec($tokens[2]);
        $dbcon->logJournal($username, "Add", "Coercion", $what);
    }
    return $result;
}

function modifyEntry()
{
    global $dbcon;
    $result = "";
    $bindList = array();
	$title = $_POST['title'];
	$query = "update coercion set ";
	$aetitle = $_POST['title'];
	if ($aetitle) {
		$query .= "aetitle=?,";
        $bindList[] = $aetitle;
    }
	$tokens = array();
	preg_match("/^(.*)\((.*)\)$/", $_POST['tag'], $tokens);
	$tag = hexdec($tokens[2]);
	if ($tag) {
		$query .= "tag=?,";
        $bindList[] = $tag;
    } else
        $query .= "tag=NULL,";
	$syntax = $_POST['syntax'];
	if ($syntax) {
	    $query .= "syntax=?,";
        $bindList[] = $syntax;
    }
    else
        $query .= "syntax=NULL,";
	$description = $_POST['description'];
	if ($description) {
	    $query .= "description=?,";
        $bindList[] = $description;
    }
    else
        $query .= "description=NULL,";
    if ($_POST['coerceby']) {
        unset($tokens);
	    preg_match("/^(.*)\((.*)\)$/", $_POST['keytag'], $tokens);
        $value = hexdec($tokens[2]);
    } else
        $value = 0;
    $query .= "keytag=$value,";
    $query .= "pattern=?,";
	$bindList[] = (isset($_POST['pattern']) && strlen($_POST['pattern']))? $_POST['pattern'] : "";
    $sequence = $_POST['sequence'];
    // replace the last ',' with ';'
    $npos = strrpos($query, ",");
    if ($npos != false)
        $query = substr($query, 0, $npos);
    $query .= " where aetitle=? and tag=? and sequence=?";
    array_push($bindList, $title, $tag, $sequence);
	// execute SQL query
	if (!$dbcon->preparedStmt($query, $bindList)) {
		$result .= "<h3><font color=red>";
        $result .= sprintf(pacsone_gettext("Error updating Coercion Rule for <u>%s</u>: "), $title);
        $result .= $dbcon->getError();
        $result .= "</font></h3><p>\n";
    } else {
        $username = $dbcon->username;
        // log activity to system journal
        $what = "Source AE Title: $title Seq: $sequence Tag: $tag";
        $dbcon->logJournal($username, "Modify", "Coercion", $what);
    }
    return $result;
}

function addEntryForm()
{
    include_once 'checkUncheck.js';
    global $PRODUCT;
    global $COERCION_TBL;
    global $ROUTE_KEY_TBL;
    // display Add New Application Entity form
    print "<html>\n";
    print "<head><title>$PRODUCT - ";
    print pacsone_gettext("Add Data Element Coercion Rule");
    print "</title></head>\n";
    print "<body>\n";
    require_once 'header.php';
    print "<form method='POST' action='modifyCoercion.php'>\n";
    print "<input type='hidden' name='actionvalue'>\n";
    print "<table cellpadding=3 cellspacing=6>\n";
    print "<tr><td>";
    print "<input type=radio name='coerceby' value=0 checked>";
    print pacsone_gettext("Coerce By Source Application Entity Title:");
    print "</input></td>\n";
    print "<td><input type='text' size=16 maxlength=16 name='title'></td></tr>\n";
    print "<tr><td>";
    print "<input type=radio name='coerceby' value=1>";
    print pacsone_gettext("Coerce By Key Attribute Value:");
    print "</input></td>\n";
    print "<td><select name='keytag'>\n";
    foreach ($ROUTE_KEY_TBL as $key => $tag) {
        print "<option>$key</option>\n";
    }
    print "</select>&nbsp;&nbsp;&nbsp;\n";
    print pacsone_gettext("with Case-Insensitive Matching Pattern:");
    print " <input type='text' size=64 maxlength=255 name='pattern'><br>\n";
    print pacsone_gettext(" (Wild-Card Characters <b>*</b> and <b>?</b> Are Supported)<br>\n");
    print "</td></tr>";
    print "<tr><td>";
    print pacsone_gettext("Enter Data Element Tag:") . "</td>\n";
    print "<td><select name='tag'>";
    foreach ($COERCION_TBL as $tag => $desc) {
        printf("<option>%s (0x%08x)</option>", $desc, $tag);
    }
    print "</select></td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Enter Coercion Rule Syntax:") . "</td>\n";
    print "<td><input type='text' size=32 maxlength=32 name='syntax'></td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Enter Description:") . "</td>\n";
    print "<td><input type='text' size=64 maxlength=64 name='description'></td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Applying Order When There Are Multiple Rules:") . "</td>\n";
    $seq = 1;
    if (isset($_POST['sequence']))
        $seq = $_POST['sequence'] + 1;
    print "<td><input type='text' size=4 maxlength=4 name='sequence' value=$seq></td></tr>\n";
    print "</table>\n";
    print "<p><input type='submit' name='action' value='";
    print pacsone_gettext("Add");
    print "' onclick='switchText(this.form,\"actionvalue\",\"Add\")'></form>\n";
	require_once 'footer.php';
    print "</body>\n";
    print "</html>\n";
}

function modifyEntryForm($title, $seq)
{
    include_once 'checkUncheck.js';
    global $PRODUCT;
    global $dbcon;
    global $COERCION_TBL;
    global $ROUTE_KEY_TBL;
    // display Modify Data Element Coercion form
    print "<html>\n";
    print "<head><title>$PRODUCT - ";
    printf(pacsone_gettext("Modify Data Element Coercion Rule: %s"), $title);
    print "</title></head>\n";
    print "<body>\n";
    require_once 'header.php';
    $query = "select * from coercion where aetitle=? and sequence=?";
    $bindList = array($title, $seq);
    $result = $dbcon->preparedStmt($query, $bindList);
    if ($result->rowCount() == 1) {
        $row = $result->fetch(PDO::FETCH_ASSOC);
        print "<form method='POST' action='modifyCoercion.php'>\n";
        print "<input type='hidden' name='actionvalue'>\n";
        print "<table cellpadding=3 cellspacing=6>\n";
        print "<tr><td>";
        $keyTag = $row['keytag'];
        $checked = $keyTag? "" : "checked";
        print "<input type=radio name='coerceby' value=0 $checked>";
        print pacsone_gettext("Coerce By Source Application Entity Title:");
        print "</input></td>\n";
        $data = $row['aetitle'];
        print "<td><input type='text' size=16 maxlength=16 name='title' value='$data' readonly>";
        print "</td></tr>";
        print "<tr><td>";
        $checked = $keyTag? "checked" : "";
        print "<input type=radio name='coerceby' value=1 $checked>";
        print pacsone_gettext("Coerce By Key Attribute Value:");
        print "</input></td>\n";
        print "<td><select name='keytag'>\n";
        foreach ($ROUTE_KEY_TBL as $key => $tag) {
            $selected = ($tag == $keyTag)? "selected" : "";
            print "<option $selected>$key</option>\n";
        }
        print "</select>&nbsp;&nbsp;&nbsp;\n";
        print pacsone_gettext("with Case-Insensitive Matching Pattern:");
        $data = $row['pattern'];
        print " <input type='text' size=64 maxlength=255 name='pattern' value='$data'>\n";
        print pacsone_gettext(" (Wild-Card Characters <b>*</b> and <b>?</b> Are Supported)<br>\n");
        print "</td></tr>";
        print "<tr><td>";
        print pacsone_gettext("Data Element Tag:") . "</td>\n";
        $data = $row['tag'];
        $desc = $COERCION_TBL[$data];
        $value = sprintf("%s (0x%08x)", $desc, $data);
    	print "<td><input type='text' name='tag' value='$value' readonly>";
        print "</td></tr>\n";
        print "</tr>\n";
        print "<tr><td>";
        print pacsone_gettext("Coercion Syntax:") . "</td>\n";
        $data = $row['syntax'];
        $value = "<td><input type='text' size=32 maxlength=32 name='syntax'";
        if (isset($data))
            $value .= "value='$data'";
        $value .= "></td>\n";
        print $value;
        print "</tr>\n";
        print "<tr><td>";
        print pacsone_gettext("Coercion Rule Description:") . "</td>\n";
        $data = $row['description'];
        $value = "<td><input type='text' size=64 maxlength=64 name='description'";
        if (isset($data))
            $value .= "value='$data'";
        $value .= "></td>\n";
        print $value;
        print "</tr>\n";
        $data = $row['sequence'];
        $value = "<input type=hidden name='sequence' value='$data'>\n";
        print $value;
        print "</table>\n";
        print "<p><input type='submit' name='action' value='";
        print pacsone_gettext("Modify");
        print "' onclick='switchText(this.form,\"actionvalue\",\"Modify\")'>\n";
        print "</form>\n";
    }
    else {
        print "<h3><font color=red>";
        printf(pacsone_gettext("<u>Coercion rule for: %s</u> not found in database!"), $title);
        print "</font></h3>\n";
    }
	require_once 'footer.php';
    print "</body>\n";
    print "</html>\n";
}

?>
