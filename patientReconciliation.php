<?php
//
// patientReconciliation.php
//
// Module for modifying entries in the Patient Reconciliation Table
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
if (isset($_POST['action']))
   	$action = $_POST['actionvalue'];
if (isset($_POST['entry']))
   	$entry = $_POST['entry'];
$result = NULL;
if (isset($action) && strcasecmp($action, "Delete") == 0) {
    $result = deleteEntries($entry);
}
if (isset($result)) {       // display results
    if (empty($result)) {   // success
        header('Location: tools.php?page=' . urlencode(pacsone_gettext("Patient Reconciliation")));
        exit();
    }
    else {                  // error
        print "<html>\n";
        print "<head><title>$PRODUCT - ";
        print pacsone_gettext("Patient Reconciliation Table Error");
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
	foreach ($entry as $uid) {
		$query = "delete from matchworklist where studyuid=?";
        $bindList = array($uid);
		if (!$dbcon->preparedStmt($query, $bindList)) {
			$errors[$uid] = sprintf(pacsone_gettext("Database Error: %s"), $dbcon->getError());
		}
		else
			$ok[] = $uid;
	}
	$result = "";
	if (!empty($errors)) {
        if (count($errors) == 1)
		    $result = pacsone_gettext("Error deleting the following Patient Reconciliation event:");
        else
		    $result = pacsone_gettext("Error deleting the following Patient Reconciliation events:");
        $result .= "<P>\n";
		foreach ($errors as $key => $value) {
			$result .= "<h3><font color=red><u>$key</u> - $value</font></h3><P>\n";
		}
	}
    return $result;
}

?>
