<?php
//
// modifyHL7App.php
//
// Module for modifying entries in the HL-7 Application Table
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once 'database.php';
include_once 'sharedData.php';

// main
global $PRODUCT;
$dbcon = new MyConnection();
$username = $dbcon->username;
if (isset($_POST['name']))
	$name = $_POST['name'];
if (isset($_POST['action']))
   	$action = $_POST['actionvalue'];
if (isset($_POST['entry']))
   	$entry = $_POST['entry'];
$result = NULL;
if (isset($_GET['name'])) {
    modifyEntryForm($_GET['name']);
}
else {
    if (isset($action) && strcasecmp($action, "Delete") == 0) {
	    $result = deleteEntries($username, $entry);
    }
    else if (isset($action) && strcasecmp($action, "Add") == 0) {
	    if (isset($name)) {
	        $result = addEntry($username, $name);
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
        header('Location: hl7app.php');
    }
    else {                  // error
        print "<html>\n";
        print "<head><title>$PRODUCT - ";
        print pacsone_gettext("HL7 Application Error");
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
	foreach ($entry as $value) {
		$query = "delete from hl7application where name=?";
        $bindList = array($value);
		if (!$dbcon->preparedStmt($query, $bindList)) {
			$errors[$value] = sprintf(pacsone_gettext("Database Error: %s"), $dbcon->getError());
            continue;
		}
		$ok[] = $value;
    // log activity to system journal
    $dbcon->logJournal($username, "Delete", "HL7App", $value);
	}
	$result = "";
	if (!empty($errors)) {
        if (count($errors) == 1)
		    $result = "Error deleting the following HL7 Application:";
        else
		    $result = "Error deleting the following HL7 Applications:";
        $result .= "<P>\n";
		foreach ($errors as $key => $value) {
			$result .= "<h3><font color=red><u>$key</u> - $value</font></h3><P>\n";
		}
	}
    return $result;
}

function addEntry(&$username, &$name)
{
    global $dbcon;
    $result = "";
    $columns = array();
    $bindList = array();
    $query = "insert into hl7application (";
    $columns[] = "name";
    $values = "?";
    $bindList[] = $name;
	$columns[] = "facility";
	if (isset($_POST['facility']) && strlen($_POST['facility'])) {
        $values .= ",?";
		$bindList[] = $_POST['facility'];
	} else
		$values .= ",NULL";
	$columns[] = "description";
	if (isset($_POST['description']) && strlen($_POST['description'])) {
        $values .= ",?";
		$bindList[] = $_POST['description'];
	} else
		$values .= ",NULL";
	$columns[] = "hostname";
	if (isset($_POST['hostname']) && strlen($_POST['hostname'])) {
        $values .= ",?";
		$bindList[] = $_POST['hostname'];
    } else
		$values .= ",NULL";
	$columns[] = "ipaddr";
	if (isset($_POST['ipaddr']) && strlen($_POST['ipaddr'])) {
        $values .= ",?";
		$bindList[] = $_POST['ipaddr'];
    } else
		$values .= ",NULL";
	$columns[] = "port";
	if (isset($_POST['port']) && strlen($_POST['port'])) {
        $values .= ",?";
		$bindList[] = $_POST['port'];
    } else
		$values .= ",NULL";
	if (isset($_POST['maxsessions'])) {
        $columns[] = "maxsessions";
        $values .= ",?";
        $bindList[] = $_POST['maxsessions'];
	}
    $columns[] = "orureport";
    $values .= ",?";
    $bindList[] = $_POST['orureport'];
    for ($i = 0; $i < count($columns); $i++) {
        if ($i)
            $query .= ",";
        $query .= $columns[$i];
    }
    $query .= ") values($values)";
    // execute SQL query
    if (!$dbcon->preparedStmt($query, $bindList)) {
        $result .= "<h3><font color=red>";
        $result .= sprintf(pacsone_gettext("Error adding HL7 Application <u>%s</u>: "), $name);
        $result .= $dbcon->getError();
        $result .= "</font></h3><p>\n";
    } else {
        // log activity to system journal
        $dbcon->logJournal($username, "Add", "HL7App", $name);
    }
    return $result;
}

function modifyEntry(&$username)
{
    global $dbcon;
    $result = "";
    $bindList = array();
	$name = $_POST['name'];
	$query = "update hl7application set ";
	$fac = $_POST['facility'];
	if ($fac) {
		$query .= "facility=?,";
        $bindList[] = $fac;
    }
	$description = $_POST['description'];
	if ($description) {
		$query .= "description=?,";
        $bindList[] = $description;
    }
	$hostname = $_POST['hostname'];
	if ($hostname) {
		$query .= "hostname=?,";
        $bindList[] = $hostname;
    }
	$ipaddr = $_POST['ipaddr'];
	if ($ipaddr) {
		$query .= "ipaddr=?,";
        $bindList[] = $ipaddr;
    } else
        $query .= "ipaddr=NULL,";
	$port = $_POST['port'];
	if ($port) {
		$query .= "port=?,";
        $bindList[] = $port;
    } else
        $query .= "port=NULL,";
	if (isset($_POST['maxsessions'])) {
		$query .= "maxsessions=?,";
        $bindList[] = $_POST['maxsessions'];
	}
    $query .= "orureport=?";
    $bindList[] = $_POST['orureport'];
    $query .= " where name=?";
    $bindList[] = $name;
    // execute SQL query
    if (!$dbcon->preparedStmt($query, $bindList)) {
        $result .= "<h3><font color=red>";
        $result .= sprintf(pacsone_gettext("Error updating HL7 Application <u>%s</u>: "), $name);
        $result .= $dbcon->getError();
        $result .= "</font></h3><p>\n";
    } else {
        // log activity to system journal
        $dbcon->logJournal($username, "Modify", "HL7App", $name);
    }
    return $result;
}

function addEntryForm()
{
    include_once 'checkUncheck.js';
    global $PRODUCT;
    // display Add New HL-7 Application form
    print "<html>\n";
    print "<head><title>$PRODUCT - ";
    print pacsone_gettext("Add HL7 Application");
    print "</title></head>\n";
    print "<body>\n";
    require_once 'header.php';
    print "<form method='POST' action='modifyHL7App.php'>\n";
    print "<input type='hidden' name='actionvalue'>\n";
    print "<p><table border=1 cellpadding=2 cellspacing=0>\n";
    print "<tr><td>";
    print pacsone_gettext("Enter Application Name:") . "</td>\n";
    print "<td><input type='text' size=32 maxlength=32 name='name'></td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Enter Facility:") . "</td>\n";
    print "<td><input type='text' size=64 maxlength=64 name='facility'></td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Enter Description of HL7 Application:") . "</td>\n";
    print "<td><input type='text' size=64 maxlength=64 name='description'></td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Enter Hostname:") . "</td>\n";
    print "<td><input type='text' size=20 maxlength=20 name='hostname'></td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Enter IP Address:") . "</td>\n";
    print "<td><input type='text' size=20 maxlength=20 name='ipaddr'></td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Enter Port Number:") . "</td>\n";
    print "<td><input type='text' size=10 maxlength=10 name='port'></td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Enter Maximum Number of Simultaneous Connections:") . "</td>\n";
    print "<td><input type='text' size=10 maxlength=10 name='maxsessions' value=10></td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Enable ORU Report Notification Message for Newly Received Dicom Studies:");
    print "</td><td>";
    print "<input type='radio' name='orureport' value=1>" . pacsone_gettext("Yes") . "<br>";
    print "<input type='radio' name='orureport' value=0 checked>" . pacsone_gettext("No");
    print "</td></tr>";
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
    // display Modify HL-7 Application form
    print "<html>\n";
    print "<head><title>$PRODUCT - ";
    print pacsone_gettext("Modify HL7 Application");
    print "</title></head>\n";
    print "<body>\n";
    require_once 'header.php';
    $query = "select * from hl7application where name=?";
    $bindList = array($name);
    $result = $dbcon->preparedStmt($query, $bindList);
    if ($result && $result->rowCount() == 1) {
        $row = $result->fetch(PDO::FETCH_ASSOC);
        print "<form method='POST' action='modifyHL7App.php'>\n";
        print "<input type='hidden' name='actionvalue'>\n";
        print "<p><table border=1 cellpadding=2 cellspacing=0>\n";
        print "<tr><td>";
        print pacsone_gettext("Application Name:") . "</td>\n";
        $data = $row['name'];
        $value = "<td><input type='text' size=32 maxlength=32 name='name' ";
        if (isset($data))
            $value .= "value='$data' ";
        $value .= "readonly></td>\n";
        print $value;
        print "</tr>\n";
        print "<tr><td>" . pacsone_gettext("Facility:") . "</td>\n";
        $data = $row['facility'];
        $value = "<td><input type='text' size=64 maxlength=64 name='facility'";
        if (isset($data))
            $value .= "value='$data'";
        $value .= "></td>\n";
        print $value;
        print "</tr>\n";
        print "<tr><td>" . pacsone_gettext("Description:") . "</td>\n";
        $data = $row['description'];
        $value = "<td><input type='text' size=64 maxlength=64 name='description'";
        if (isset($data))
            $value .= "value='$data'";
        $value .= "></td>\n";
        print $value;
        print "</tr>\n";
        print "<tr><td>" . pacsone_gettext("Hostname:") . "</td>\n";
        $data = $row['hostname'];
        $value = "<td><input type='text' size=20 maxlength=20 name='hostname'";
        if (isset($data))
            $value .= "value='$data'";
        $value .= "></td>\n";
        print $value;
        print "</tr>\n";
        print "<tr><td>" . pacsone_gettext("IP Address:") . "</td>\n";
        $data = $row['ipaddr'];
        $value = "<td><input type='text' size=20 maxlength=20 name='ipaddr'";
        if (isset($data))
            $value .= "value='$data'";
        $value .= "></td>\n";
        print $value;
        print "</tr>\n";
        print "<tr><td>" . pacsone_gettext("Port Number:") . "</td>\n";
        $data = $row['port'];
        $value = "<td><input type='text' size=10 maxlength=10 name='port'";
        if (isset($data))
            $value .= "value='$data'";
        $value .= "></td>\n";
        print $value;
        print "</tr>\n";
        print "<tr><td>";
        print pacsone_gettext("Maximum Number of Simultaneous Connections:") . "</td>\n";
        $data = $row['maxsessions'];
        $value = "<td><input type='text' size=10 maxlength=10 name='maxsessions'";
        if (isset($data))
            $value .= "value='$data'";
        $value .= "></td>\n";
        print $value;
        print "</tr>\n";
        print "<tr><td>";
        print pacsone_gettext("Enable ORU Report Message for Newly Received Dicom Studies:") . "</td>\n";
        $data = $row['orureport'];
        $checked = $data? "checked" : "";
        print "<td><input type='radio' name='orureport' value=1 $checked>" . pacsone_gettext("Yes") . "<br>";
        $checked = $data? "" : "checked";
        print "<input type='radio' name='orureport' value=0 $checked>" . pacsone_gettext("No");
        print "</td></tr>\n";
        print "</table>\n";
        print "<p><input type='submit' name='action' value='";
        print pacsone_gettext("Modify");
        print "' onclick='switchText(this.form,\"actionvalue\",\"Modify\")'>\n";
        print "</form>\n";
    }
    else {
        print "<h3><font color=red>";
        printf(pacsone_gettext("<u>%s</u> not found in database"), $name);
        print "</font></h3>\n";
    }
	require_once 'footer.php';
    print "</body>\n";
    print "</html>\n";
}

?>
