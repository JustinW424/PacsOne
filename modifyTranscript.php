<?php
//
// modifyTranscript.php
//
// Module for modifying entries in the Transcription Template Table
//
// CopyRight (c) 2011-2020 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once 'database.php';
include_once 'sharedData.php';
include_once 'upload.php';

// main
global $PRODUCT;
$dbcon = new MyConnection();
$username = $dbcon->username;
$result = $dbcon->query("select maxupload from config");
$max = $result->fetchColumn();
$maxupload = $max * 1024 * 1024;
$mbytes = $maxupload / 1024 / 1024;
if (isset($_POST['name']))
	$name = $_POST['name'];
if (isset($_POST['action']))
   	$action = $_POST['actionvalue'];
if (isset($_POST['entry']))
   	$entry = $_POST['entry'];
$result = NULL;
if (isset($_GET['name'])) {
    modifyEntryForm(urldecode($_GET['name']), $mbytes);
} else {
    if (isset($action) && strcasecmp($action, "Delete") == 0) {
	    $result = deleteEntries($entry);
    } else if (isset($action) && strcasecmp($action, "Add") == 0) {
	    if (isset($name)) {
	        $result = addEntry($name);
        } else {
            addEntryForm($username, $mbytes);
        }
    }
    else if (isset($action) && strcasecmp($action, "Modify") == 0) {
	    $result = modifyEntry($name);
    } else if (isset($action) && strcasecmp($action, "Attach") == 0) {
        $attached = $_FILES['attachfile'];
        $origname = $attached['name'];
        $error = $attached['error'];
        if ($error) {
            print "<h2><font color=red>";
            printf(pacsone_gettext("Error uploading file <b>%s</b>: %s"), $origname, getUploadError($error));
            print "</font></h2>";
            exit();
        }
        // security check
        if (!uploadCheck($origname)) {
            print "<h2><font color=red>";
            printf(pacsone_gettext("Access denied uploading file: <b><u>%s</b></u>"), $origname);
            print "</font></h2>";
            exit();
        }
        $destdir = dirname($_SERVER['SCRIPT_FILENAME']);
        // change to Unix-style path
        $destdir = str_replace("\\", "/", $destdir);
        // append '/' at the end if not so already
        if (strcmp(substr($destdir, strlen($destdir)-1, 1), "/"))
            $destdir .= "/";
        $destdir .= "transcript/";
        if (!file_exists($destdir))
            mkdir($destdir);
        $destfile = $destdir . $attached['name'];
        if (file_exists($destfile)) {
            print "<h2><font color=red>";
            printf(pacsone_gettext("Template document: %s already exists"), $destfile);
            print "<br>";
            print_r($_FILES);
            print "</font></h2>";
            exit();
        }
        if (!move_uploaded_file($attached['tmp_name'], $destfile)) {
            print "<h2><font color=red>";
            printf(pacsone_gettext("Upload file: %s failed"), $destfile);
            print "<br>";
            print_r($_FILES);
            print "</font></h2>";
            exit();
        }
        $attached['destfile'] = $destfile;
        // save the uploaded file
        if (isset($_SESSION['attachments']))
            $attachments = $_SESSION['attachments'];
        else
            $attachments = array();
        $found = 0;
        foreach ($attachments as $exist) {
            if (!strcasecmp($exist['name'], $attached['name']) &&
                !strcasecmp($exist['type'], $attached['type'])) {
                $found = 1;
                break;
            }
        }
        if (!$found)
            $attachments[] = $attached;
        $_SESSION['attachments'] = $attachments;
        // go back to either Add or Modify page
        if (isset($_POST['modify']))
            modifyEntryForm($name, $mbytes);
        else
            addEntryForm($username, $mbytes);
    }
    else if (isset($action) && strcasecmp($action, "Unattach") == 0) {
        // remove uploaded file
        if (isset($_SESSION['attachments']) && isset($_POST['unattach'])) {
            $unattach = $_POST['unattach'];
            $attachments = $_SESSION['attachments'];
            $newList = array();
            foreach ($attachments as $att) {
                if (in_array($att['name'], $unattach))
                    unlink($att['destfile']);
                else
                    $newList[] = $att;
            }
            // save modified upload images
            $_SESSION['attachments'] = $newList;
        }
        // go back to either Add or Modify page
        if (isset($_POST['modify']))
            modifyEntryForm($name, $mbytes);
        else
            addEntryForm($username, $mbytes);
    }
}
if (isset($result)) {       // display results
    if (empty($result)) {   // success
        header('Location: tools.php?page=' . urlencode(pacsone_gettext("Transcription Templates")));
    }
    else {                  // error
        print "<html>\n";
        print "<head><title>$PRODUCT - ";
        print pacsone_gettext("Transcription Template Table Error");
        print "</title></head>\n";
        print "<body>\n";
        require_once 'header.php';
        print $result;
		require_once 'footer.php';
        print "</body>\n";
        print "</html>\n";
    }
    if (isset($_SESSION['attachments']))
        unset($_SESSION['attachments']);
}

function deleteEntries($entry)
{
    global $dbcon;
	$ok = array();
	$errors = array();
	foreach ($entry as $value) {
        // delete the template Word document
        $bindList = array($value);
        $result = $dbcon->preparedStmt("select path from xscriptemplate where name=?", $bindList);
        if ($result && ($file = $result->fetchColumn())) {
            if (file_exists($file))
                unlink($file);
        }
        // delete from database tables
        $queries = array(
            "delete from xscriptemplate where name=?",
            "delete from xscriptbookmark where template=?",
            "update applentity set xscript=NULL where xscript=?",
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
		    $result = pacsone_gettext("Error deleting the following Transcription Template:");
        else
		    $result = pacsone_gettext("Error deleting the following Transcription Templates:");
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
    $result = "";
    // insert into XSCRIPTEMPLATE table
    if (isset($_SESSION['attachments'])) {
        $destfile = $_SESSION['attachments'][0]['destfile'];
        if (!file_exists($destfile)) {
		    $result .= "<h3><font color=red>";
            $result .= sprintf(pacsone_gettext("Template document <u>%s</u> does not exist!"), $destfile);
            $result .= "</font></h3><p>\n";
            return $result;
        }
        $query = "insert into xscriptemplate (name,path) values(?, ?)";
        $bindList = array($name, $destfile);
	    // execute SQL query
	    if (!$dbcon->preparedStmt($query, $bindList)) {
		    $result .= "<h3><font color=red>";
            $result .= sprintf(pacsone_gettext("Error adding Transcription Template <u>%s</u>: "), $name);
            $result .= "SQL Query = [$query]<br>";
		    $result .= $dbcon->getError();
            $result .= "</font></h3><p>\n";
            return $result;
	    }
    }
    // insert into XSCRIPTBOOKMARK table
    global $XSCRIPT_BOOKMARK_FIELD_TBL;
    foreach ($XSCRIPT_BOOKMARK_FIELD_TBL as $id => $entry) {
        $descr = $entry[0];
        $key = "fieldid-$id";
        if (!isset($_POST[$key]))
            continue;
        $bookmark = $_POST[$key];
        if (!strlen($bookmark))
            continue;
        $columns = array();
        $bindList = array();
        $columns[] = "template";
        $values = "?";
        $bindList[] = $name;
	    $query = "insert into xscriptbookmark (";
        $columns[] = "id";
        $values .= ",?";
        $bindList[] = $id;
	    $columns[] = "bookmark";
        $values .= ",?";
		$bindList[] = $bookmark;
        $descr = isset($_POST["description-$id"])? $_POST["description-$id"] : "";
        if (strlen($descr)) {
	        $columns[] = "description";
            $values .= ",?";
            $bindList[] = $descr;
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
            $result .= sprintf(pacsone_gettext("Error adding Transcription Template <u>%s</u>: "), $name);
            $result .= "SQL Query = [$query]<br>";
		    $result .= $dbcon->getError();
            $result .= "</font></h3><p>\n";
	    }
    }
    return $result;
}

function modifyEntry($name)
{
    global $dbcon;
    $result = "";
    // modify XSCRIPTEMPLATE table
    if (isset($_SESSION['attachments'])) {
        $destfile = $_SESSION['attachments'][0]['destfile'];
        if (!file_exists($destfile)) {
		    $result .= "<h3><font color=red>";
            $result .= sprintf(pacsone_gettext("Template document <u>%s</u> does not exist!"), $destfile);
            $result .= "</font></h3><p>\n";
            return $result;
        }
        $query = "update xscriptemplate set path=? where name=?";
        $bindList = array($destfile, $name);
	    // execute SQL query
	    if (!$dbcon->preparedStmt($query, $bindList)) {
		    $result .= "<h3><font color=red>";
            $result .= sprintf(pacsone_gettext("Error modifying Transcription Template <u>%s</u>: "), $name);
            $result .= "SQL Query = [$query]<br>";
		    $result .= $dbcon->getError();
            $result .= "</font></h3><p>\n";
            return $result;
	    }
        // remove the old template document
        $file = $_POST['currentpath'];
        if (strcasecmp($file, $destfile) && file_exists($file))
            unlink($file);
    }
    // modify XSCRIPTBOOKMARK table
    global $XSCRIPT_BOOKMARK_FIELD_TBL;
    foreach ($XSCRIPT_BOOKMARK_FIELD_TBL as $id => $entry) {
        $descr = $entry[0];
        $key = "fieldid-$id";
        if (!isset($_POST[$key]))
            continue;
        $bookmark = $_POST[$key];
        if (!strlen($bookmark))
            continue;
        $bindList = array();
	    $query = "update xscriptbookmark set bookmark=?";
		$bindList[] = $bookmark;
        $descr = isset($_POST["description-$id"])? $_POST["description-$id"] : "";
        if (strlen($descr)) {
	        $query .= ",description=?";
            $bindList[] = $descr;
        }
        $query .= " where template=? and id=?";
        array_push($bindList, $name, $id);
	    // execute SQL query
	    if (!$dbcon->preparedStmt($query, $bindList)) {
		    $result .= "<h3><font color=red>";
            $result .= sprintf(pacsone_gettext("Error modifying Transcription Template <u>%s</u>: "), $name);
            $result .= "SQL Query = [$query]<br>";
		    $result .= $dbcon->getError();
            $result .= "</font></h3><p>\n";
	    }
    }
    return $result;
}

function addEntryForm(&$username, $mbytes)
{
    include_once 'checkUncheck.js';
    global $PRODUCT;
    global $XSCRIPT_BOOKMARK_FIELD_TBL;
    global $dbcon;
    $disabled = $dbcon->hasaccess("upload", $username)? "" : "disabled";
    if (!strlen($disabled) && isset($_SESSION['attachments'])) {
        // allow only 1 attachment for the template document
        if (count($_SESSION['attachments']))
            $disabled = "disabled";
    }
    // display Add New Transcription Template form
    print "<html>\n";
    print "<head><title>$PRODUCT - ";
    print pacsone_gettext("Add Transcription Template");
    print "</title></head>\n";
    print "<body>\n";
    require_once 'header.php';
    print "<form method='POST' action='modifyTranscript.php' enctype='multipart/form-data'>\n";
    print "<input type='hidden' name='actionvalue'>\n";
    print "<table width=100% border=1 cellpadding=3 cellspacing=0>\n";
    print "<tr><td>";
    print pacsone_gettext("Enter Transcription Template Name:") . "</td>\n";
    $value = isset($_POST['name'])? $_POST['name'] : "";
    print "<td><input type='text' size=32 maxlength=64 name='name' value='$value'></td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Upload Transcription Template Word Document: ");
    print pacsone_gettext("(This Word template will contain the following bookmarks)");
    print "</td>";
    print "<td><input type=file name='attachfile' size=64 maxlength=255 $disabled>&nbsp;\n";
    printf(pacsone_gettext("(max %d Mbytes)"), $mbytes) . "\n";
    print "<p><input type=submit name='action' value='";
    print pacsone_gettext("Attach");
    print "' onclick='switchText(this.form,\"actionvalue\",\"Attach\")' $disabled><br>";
    // display any uploaded document here
    if (isset($_SESSION['attachments'])) {
        $attachments = $_SESSION['attachments'];
        if (count($attachments)) {
            print "<br>";
            foreach ($attachments as $att) {
                $value = $att['name'];
                print "<input type=checkbox name='unattach[]' value='$value'>";
                print "<img src='attachment.gif' border=0>";
                print "<b>" . $att['name'] . "</b> (" . $att['size'] . " bytes)<br>\n";
            }
            print "<br><input type=submit name='action' value='";
            print pacsone_gettext("Unattach");
            print "' onclick='switchText(this.form,\"actionvalue\",\"Unattach\")'>\n";
        }
    }
    print "</td></tr>\n";
    foreach ($XSCRIPT_BOOKMARK_FIELD_TBL as $id => $entry) {
        $descr = $entry[0];
        $default = $entry[1];
        print "<tr><td>";
        printf(pacsone_gettext("Enter Bookmark Name for: <b>%s</b>"), $descr);
        print "</td><td>";
        print pacsone_gettext("Bookmark: ");
        print "<input type='text' size=32 maxlength=64 name='fieldid-$id' value=\"$default\">\n";
        print "<p>" . pacsone_gettext("Description: ") . "&nbsp;\n";
        print "<input type='text' size=64 maxlength=64 name='description-$id'>";
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

function modifyEntryForm($name, $mbytes)
{
    include_once 'checkUncheck.js';
    global $PRODUCT;
    global $XSCRIPT_BOOKMARK_FIELD_TBL;
    global $dbcon;
    // display Add New Transcription Template form
    print "<html>\n";
    print "<head><title>$PRODUCT - ";
    print pacsone_gettext("Modify Transcription Template");
    print "</title></head>\n";
    print "<body>\n";
    require_once 'header.php';
    print "<form method='POST' action='modifyTranscript.php' enctype='multipart/form-data'>\n";
    print "<input type='hidden' name='actionvalue'>\n";
    print "<input type='hidden' name='modify' value=1>\n";
    print "<table width=100% border=1 cellpadding=3 cellspacing=0>\n";
    print "<tr><td>";
    print pacsone_gettext("Transcription Template Name:") . "</td>\n";
    print "<td><input type='text' size=32 maxlength=64 name='name' value='$name' readonly></td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Upload Transcription Template Word Document: ");
    print pacsone_gettext("(This Word template will contain the following bookmarks)");
    print "</td>";
    $disabled = "";
    if (isset($_SESSION['attachments'])) {
        // allow only 1 attachment for the template document
        if (count($_SESSION['attachments']))
            $disabled = "disabled";
    }
    $result = $dbcon->query("select path from xscriptemplate");
    if (!$result || $result->rowCount() == 0) {
        die(pacsone_gettext("Error: Cannot find transcription document template: ") . $name);
    }
    $path = $result->fetchColumn();
    print "<td>";
    print pacsone_gettext("Current template document: ");
    print "<input type='text' size=128 maxlength=255 name='currentpath' value='$path' readonly>";
    print "<p><input type=file name='attachfile' size=64 maxlength=255 $disabled>&nbsp;\n";
    printf(pacsone_gettext("(max %d Mbytes)"), $mbytes) . "\n";
    print "<p><input type=submit name='action' value='";
    print pacsone_gettext("Attach");
    print "' onclick='switchText(this.form,\"actionvalue\",\"Attach\")' $disabled><br>";
    // display any uploaded document here
    if (isset($_SESSION['attachments'])) {
        $attachments = $_SESSION['attachments'];
        if (count($attachments)) {
            print "<br>";
            foreach ($attachments as $att) {
                $value = $att['name'];
                print "<input type=checkbox name='unattach[]' value='$value'>";
                print "<img src='attachment.gif' border=0>";
                print "<b>" . $att['name'] . "</b> (" . $att['size'] . " bytes)<br>\n";
            }
            print "<br><input type=submit name='action' value='";
            print pacsone_gettext("Unattach");
            print "' onclick='switchText(this.form,\"actionvalue\",\"Unattach\")'>\n";
        }
    }
    print "</td></tr>\n";
    foreach ($XSCRIPT_BOOKMARK_FIELD_TBL as $id => $entry) {
        $descr = $entry[0];
        print "<tr><td>";
        printf(pacsone_gettext("Change Bookmark Name for: <b>%s</b>"), $descr);
        print "</td><td>";
        print pacsone_gettext("Bookmark: ");
        $bindList = array($name, $id);
        $result = $dbcon->preparedStmt("select bookmark,description from xscriptbookmark where template=? and id=?", $bindList);
        $row = $result->fetch(PDO::FETCH_NUM);
        $value = isset($row[0])? $row[0] : "";
        print "<input type='text' size=32 maxlength=64 name='fieldid-$id' value=\"$value\">\n";
        print "<p>" . pacsone_gettext("Description: ") . "&nbsp;\n";
        $value = isset($row[1])? $row[1] : "";
        print "<input type='text' size=64 maxlength=64 name='description-$id' value=\"$value\">";
        print "</td></tr>\n";
    }
    print "</table>\n";
    print "<p><input type='submit' name='action' value='";
    print pacsone_gettext("Modify");
    print "' onclick='switchText(this.form,\"actionvalue\",\"Modify\")'></form>\n";
	require_once 'footer.php';
    print "</body>\n";
    print "</html>\n";
}

?>
