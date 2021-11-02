<?php
//
// modifyAnpnymize.php
//
// Module for modifying entries in the Anonymization Template Table
//
// CopyRight (c) 2009-2020 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once 'database.php';
include_once 'sharedData.php';

// main
global $PRODUCT;
$dbcon = new MyConnection();
if (isset($_POST['name']))
	$name = $_POST['name'];
if (isset($_POST['action']))
   	$action = $_POST['actionvalue'];
if (isset($_POST['entry']))
   	$entry = $_POST['entry'];
$result = NULL;
if (isset($_GET['name'])) {
    modifyEntryForm(urldecode($_GET['name']));
}
else {
    if (isset($action) && strcasecmp($action, "Delete") == 0) {
	    $result = deleteEntries($entry);
    }
    else if (isset($action) && strcasecmp($action, "Add") == 0) {
	    if (isset($name)) {
	        $result = addEntry($name);
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
        header('Location: tools.php?page=' . urlencode(pacsone_gettext("Anonymization Templates")));
    }
    else {                  // error
        print "<html>\n";
        print "<head><title>$PRODUCT - ";
        print pacsone_gettext("Anonymization Template Table Error");
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
        $bindList = array($value);
        $queries = array(
            "delete from anonymity where templname=?",
            "update applentity set anonymize=NULL where anonymize=?",
            "update applentity set anonymizetx=NULL where anonymizetx=?",
        );
        foreach ($queries as $query) {
		    if (!$dbcon->preparedStmt($query, $bindList)) {
			    $errors[$value] = sprintf(pacsone_gettext("Database Error: %s"), $dbcon->getError());
		    }
		    else
			    $ok[] = $value;
        }
	}
	$result = "";
	if (!empty($errors)) {
        if (count($errors) == 1)
		    $result = pacsone_gettext("Error deleting the following Anonymization Template:");
        else
		    $result = pacsone_gettext("Error deleting the following Anonymization Templates:");
        $result .= "<P>\n";
		foreach ($errors as $key => $value) {
			$result .= "<h3><font color=red><u>$key</u> - $value</font></h3><P>\n";
		}
	}
    return $result;
}

function addEntry($name)
{
    global $dbcon;
    global $ANONYMIZE_TEMPLATE_TBL;
    $result = "";
    foreach ($ANONYMIZE_TEMPLATE_TBL as $tag => $value) {
        $syntax = "$tag-syntax";
        if (!isset($_POST[$syntax])) {
            continue;
        }
        $value = $_POST[$syntax];
        if (!strlen($value))
            continue;
        $columns = array();
        $values = "";
        $bindList = array();
	    $query = "insert into anonymity (";
        $columns[] = "templname";
        $values = "?";
        $bindList[] = $name;
        $columns[] = "tag";
        $values .= ",?";
        $bindList[] = $tag;
	    $columns[] = "syntax";
        $values .= ",?";
		$bindList[] = $value;
	    $columns[] = "description";
        $desc = "$tag-description";
	    if (isset($_POST[$desc])) {
            $values .= ",?";
		    $bindList[] = $_POST[$desc];
        }
	    else
		    $values .= ",NULL";
        for ($i = 0; $i < count($columns); $i++) {
            if ($i)
                $query .= ",";
            $query .= $columns[$i];
        }
        $query .= ") values($values)";
	    // execute SQL query
	    if (!$dbcon->preparedStmt($query, $bindList)) {
		    $result .= "<h3><font color=red>";
            $result .= sprintf(pacsone_gettext("Error adding Anonymization Template <u>%s</u>: "), $name);
            $result .= "SQL Query = [$query]<br>";
		    $result .= $dbcon->getError();
            $result .= "</font></h3><p>\n";
	    }
    }
    return $result;
}

function modifyEntry()
{
    global $dbcon;
    global $ANONYMIZE_TEMPLATE_TBL;
    $result = "";
	$name = $_POST['name'];
    foreach ($ANONYMIZE_TEMPLATE_TBL as $tag => $value) {
        $bindList = array();
        $syntax = "$tag-syntax";
        if (!isset($_POST[$syntax]))
            continue;
        $syntax = $_POST[$syntax];
        if (strlen($syntax)) {
	        $query = "update anonymity set ";
	        $query .= "syntax=?,";
            $bindList[] = $syntax;
            $desc = "$tag-description";
            $description = isset($_POST[$desc])? $_POST[$desc] : "";
	        if (strlen($description)) {
	            $query .= "description=?,";
                $bindList[] = $description;
            }
            else
                $query .= "description=NULL,";
            // get rid of the last ','
            $npos = strrpos($query, ",");
            if ($npos != false)
                $query = substr($query, 0, $npos);
        } else {
            // remove this tag from anonymization template
            $query = "delete from anonymity";
        }
        $query .= " where templname=? and tag=?";
        array_push($bindList, $name, $tag);
	    // execute SQL query
	    if (!$dbcon->preparedStmt($query, $bindList)) {
		    $result .= "<h3><font color=red>";
            $result .= sprintf(pacsone_gettext("Error updating Anonymization Template <u>%s</u>: "), $name);
            $result .= $dbcon->getError();
            $result .= "</font></h3><p>\n";
        }
	}
    return $result;
}

function addEntryForm()
{
    include_once 'checkUncheck.js';
    global $PRODUCT;
    global $ANONYMIZE_TEMPLATE_TBL;
    // display Add New Anonymization Template form
    print "<html>\n";
    print "<head><title>$PRODUCT - ";
    print pacsone_gettext("Add Anonymization Template");
    print "</title></head>\n";
    print "<body>\n";
    require_once 'header.php';
    print "<form method='POST' action='modifyAnonymize.php'>\n";
    print "<input type='hidden' name='actionvalue'>\n";
    print "<table width=100% border=1 cellpadding=3 cellspacing=0>\n";
    print "<tr><td>";
    print pacsone_gettext("Enter Anonymization Template Name:") . "</td>\n";
    print "<td><input type='text' size=32 maxlength=64 name='name'></td></tr>\n";
    foreach ($ANONYMIZE_TEMPLATE_TBL as $tag => $value) {
        $desc = $value[0];
        $default = $value[1];
        print "<tr><td>";
        printf(pacsone_gettext("Data Element: <b>%s</b> (0x%08X)"), $desc, $tag);
        print "</td><td>";
        print pacsone_gettext("Anonymization Syntax: ") . "&nbsp;\n";
        print "<input type='text' size=32 maxlength=64 name='$tag-syntax' value=\"$default\"><br>\n";
        print pacsone_gettext("Syntax Description: ") . "&nbsp;\n";
        print "<input type='text' size=64 maxlength=64 name='$tag-description'>";
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

function modifyEntryForm($name)
{
    include_once 'checkUncheck.js';
    global $PRODUCT;
    global $dbcon;
    global $ANONYMIZE_TEMPLATE_TBL;
    // display Modify Anonymization Template form
    print "<html>\n";
    print "<head><title>$PRODUCT - ";
    printf(pacsone_gettext("Modify Anonymization Template: %s"), $name);
    print "</title></head>\n";
    print "<body>\n";
    require_once 'header.php';
    print "<p>";
    printf(pacsone_gettext("Modify Anonymization Template <u>%s</u>:"), $name);
    print "<p>";
    $query = "select * from anonymity where templname=? order by tag";
    $bindList = array($name);
    $result = $dbcon->preparedStmt($query, $bindList);
    if ($result && $result->rowCount()) {
        print "<form method='POST' action='modifyAnonymize.php'>\n";
        print "<input type='hidden' name='actionvalue'>\n";
        print "<input type='hidden' name='name' value='$name'>\n";
        print "<table width=100% border=1 cellpadding=3 cellspacing=0>\n";
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            print "<tr><td>";
            print pacsone_gettext("Data Element Tag: ");
            $tag = $row['tag'];
            if (!isset($ANONYMIZE_TEMPLATE_TBL[$tag]))
                continue;
            $desc = $ANONYMIZE_TEMPLATE_TBL[$tag][0];
    	    print "&nbsp;" . sprintf("<b>%s</b> (0x%08x)", $desc, $tag);
            print "<td>" . pacsone_gettext("Anonymization Syntax: ");
            $data = $row['syntax'];
            $value = "&nbsp;<input type='text' size=32 maxlength=64 name='$tag-syntax'";
            if (isset($data))
                $value .= "value='$data'";
            $value .= "><br>\n";
            print $value;
            print pacsone_gettext("Syntax Description: ");
            $data = $row['description'];
            $value = "&nbsp;<input type='text' size=64 maxlength=64 name='$tag-description'";
            if (isset($data) && strlen($data))
                $value .= "value='$data'";
            $value .= "></td>\n";
            print $value;
            print "</tr>\n";
        }
        print "</table>\n";
        print "<p><input type='submit' name='action' value='";
        print pacsone_gettext("Modify");
        print "' onclick='switchText(this.form,\"actionvalue\",\"Modify\")'>\n";
        print "</form>\n";
    }
    else {
        print "<h3><font color=red>";
        printf(pacsone_gettext("<u>Anonymization template: %s</u> not found in database!"), $name);
        print "</font></h3>\n";
    }
	require_once 'footer.php';
    print "</body>\n";
    print "</html>\n";
}

?>
