<?php
//
// profile.php
//
// Module for modifying user profile information
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once "security.php";
include_once 'database.php';
include_once 'sharedData.php';

// main
global $PRODUCT;
$dbcon = new MyConnection();
$username = $dbcon->username;
if (isset($_POST['user']))
	$user = $_POST['user'];
else if (isset($_GET['user']))
	$user = $_GET['user'];
$result = "";
print "<html>\n";
print "<head><title>$PRODUCT - ";
print pacsone_gettext("Modify User Profile");
print "</title></head>\n";
print "<body>\n";
require_once 'header.php';

if (strcmp($username, $user)) {
    print "<p><font color=red>Permission Denied!</font>";
} else if (isset($_GET['user'])) {
    modifyEntryForm($_GET['user']);
}
else {
    // update user profile
    $change = "";
    $bindList = array();
    $columns = array(
        "importdir"         => true,
        "importdrive"       => true,
        "importdest"        => true,
        "exportdir"         => true,
        "sharenotes"        => false,
        "pagesize"          => false,
        "studynoteicon"     => false,
        "refreshperiod"     => false,
    );
    if (!$dbcon->isAdministrator($user)) {
        $columns["firstname"] = true;
        $columns["lastname"] = true;
        $columns["middlename"] = true;
        $columns["email"] = true;
    }
    foreach ($columns as $field => $quotes) {
        $value = "";
        if (!strcasecmp($field, "importdir") ||
            !strcasecmp($field, "exportdir") ||
            !strcasecmp($field, "importdrive") ||
            !strcasecmp($field, "importdest")) {
            // change to Unix-style path
            $value = cleanPostPath($_POST[$field]);
            if (strlen($value) && !file_exists($value)) {
                print "<h3><font color=red>";
                printf(pacsone_gettext("Directory: %s does not exist!"), $value);
                print "</font></h3><p>";
                print "<a href='profile.php?user=$username'>Back</a>";
                exit();
            }
        } elseif (strlen($_POST[$field])) {
            $value = $_POST[$field];
        }
        if (strlen($change))
            $change .= ",";
        $change .= "$field=?";
        $bindList[] = $value;
    }
    // update Session Variable
    if (strlen($_POST['firstname']) || strlen($_POST['lastname'])) {
		$fullname = $_POST['firstname'] . " " . $_POST['lastname'];
		$_SESSION['fullname'] = $fullname;
    }
    if (strlen($_POST['password'])) {
        $password = $_POST['password'];
        if (!validatePassword($password)) {
            global $PASSWD_SPECIAL_CHARS;
            $err = sprintf(pacsone_gettext("Error: valid passwords must be at least 8 characters, must contain at least 1 number, 1 capital letter, and 1 special character from %s"), $PASSWD_SPECIAL_CHARS);
            alertBox($err, "profile.php?user=$user");
            exit();
        }
        if ($dbcon->useMysql) {
            if (versionCompare($dbcon->version, 5, 7, 6) < 0)
                $query = "set password=PASSWORD(?)";
            else
                $query = "alter user user() identified by ?";
            $subList = array($password);
        } else if ($dbcon->useOracle) {
            $query = "alter user ? identified by ?";
            $subList = array($user, $password);
        }
        if (!$dbcon->preparedStmt($query, $subList)) {
            $result .= "<h3><font color=red>";
            $result .= sprintf(pacsone_gettext("Error Changing User Password for <u>%s</u>: "), $user);
            $result .= $dbcon->getError();
            $result .= "</font></h3><p>\n";
        } else {
            if ($dbcon->useMysql)
                $dbcon->query("flush privileges");
            // update Session Variable
            require_once 'authenticatedSession.php';
            $authenticated = new EncryptedSession($user, $password);
            $_SESSION['authenticatedPassword'] = $authenticated->getPassword();
            // reset database connection
            unset($dbcon);
            $dbcon = new MyConnection();
            if ($dbcon->connection) {
                // modify password expiration date
                $expire = $dbcon->query("select passwordexpire from config")->fetchColumn();
                if (strlen($change))
                    $change .= ",";
                if ($dbcon->useOracle)
                    $change .= "expire=SYSDATE+$expire";
                else
                    $change .= "expire=DATE_ADD(NOW(), INTERVAL $expire DAY)";
            } else {
                $result .= sprintf(pacsone_gettext("Error logging into database: <u>%s</u>"), $dbcon->getError());
            }
        }
    }
    if (!strlen($result) && strlen($change)) {
        $query = "update privilege set " . $change;
        $query .= " where username=?";
        $bindList[] = $user;
        if (!$dbcon->preparedStmt($query, $bindList)) {
            $result .= "<h3><font color=red>";
            $result .= sprintf(pacsone_gettext("Error running query: [%s]"), $query);
            $result .= $dbcon->getError();
            $result .= "</font></h3><p>\n";
        }
    }
    if (!strlen($result) && isset($_POST['columns'])) {
        // customize columns displayed in study list pages
        global $STUDY_VIEW_COLUMNS_TBL;
        $enabled = $_POST['columns'];
        $current = array();
        $bindList = array($user);
        $check = $dbcon->preparedStmt("select columnname from studyview where username=?", $bindList);
        if ($check && $check->rowCount()) {
            while ($colname = $check->fetchColumn()) {
                $current[] = $colname;
            }
            // update existing records
            foreach ($STUDY_VIEW_COLUMNS_TBL as $coln => $value) {
                if (!in_array($coln, $current)) {
                    $descr = $value[0];
                    $yesno = in_array($coln, $enabled)? 1 : 0;
                    $query = "insert into studyview (username,columnname,description,enabled)";
                    $query .= " values(?,?,?, $yesno)";
                    $bindList = array($user, $coln, $descr);
                } else {
                    $query = "update studyview set ";
                    $query .= sprintf("enabled=%d", in_array($coln, $enabled)? 1 : 0);
                    $query .= " where username=? and columnname=?";
                    $bindList = array($user, $coln);
                }
                if (!$dbcon->preparedStmt($query, $bindList)) {
                    $result .= "<h3><font color=red>";
                    $result .= sprintf(pacsone_gettext("Error running query: [%s]"), $query);
                    $result .= $dbcon->getError();
                    $result .= "</font></h3><p>\n";
                    break;
                }
            }
        } else {
            // insert new records
            foreach ($STUDY_VIEW_COLUMNS_TBL as $coln => $value) {
                $descr = $value[0];
                $yesno = in_array($coln, $enabled)? 1 : 0;
                $query = "insert into studyview (username,columnname,description,enabled)";
                $query .= " values(?,?,?, $yesno)";
                $bindList = array($user, $coln, $descr);
                if (!$dbcon->preparedStmt($query, $bindList)) {
                    $result .= "<h3><font color=red>";
                    $result .= sprintf(pacsone_gettext("Error running query: [%s]"), $query);
                    $result .= $dbcon->getError();
                    $result .= "</font></h3><p>\n";
                    break;
                }
            }
        }
    }
    if (!strlen($result) && isset($_POST['patientcolumns'])) {
        // customize columns displayed in patient list pages
        global $PATIENT_VIEW_COLUMNS_TBL;
        global $PATIENT_VIEW_COLUMNS_TBL_VET;
        $whichTbl = $dbcon->isVeterinary()? $PATIENT_VIEW_COLUMNS_TBL_VET : $PATIENT_VIEW_COLUMNS_TBL;
        $enabled = $_POST['patientcolumns'];
        $current = array();
        $bindList = array($user);
        $check = $dbcon->preparedStmt("select columnname from patientview where username=?", $bindList);
        if ($check && $check->rowCount()) {
            while ($colname = $check->fetchColumn()) {
                $current[] = $colname;
            }
            // update existing records
            foreach ($whichTbl as $coln => $value) {
                if (!in_array($coln, $current)) {
                    $descr = $value[0];
                    $yesno = in_array($coln, $enabled)? 1 : 0;
                    $query = "insert into patientview (username,columnname,description,enabled)";
                    $query .= " values(?,?,?, $yesno)";
                    $bindList = array($user, $coln, $descr);
                } else {
                    $query = "update patientview set ";
                    $query .= sprintf("enabled=%d", in_array($coln, $enabled)? 1 : 0);
                    $query .= " where username=? and columnname=?";
                    $bindList = array($user, $coln);
                }
                if (!$dbcon->preparedStmt($query, $bindList)) {
                    $result .= "<h3><font color=red>";
                    $result .= sprintf(pacsone_gettext("Error running query: [%s]"), $query);
                    $result .= $dbcon->getError();
                    $result .= "</font></h3><p>\n";
                    break;
                }
            }
        } else {
            // insert new records
            foreach ($whichTbl as $coln => $value) {
                $descr = $value[0];
                $yesno = in_array($coln, $enabled)? 1 : 0;
                $query = "insert into patientview (username,columnname,description,enabled)";
                $query .= " values(?,?,?, $yesno)";
                $bindList = array($user, $coln, $descr);
                if (!$dbcon->preparedStmt($query, $bindList)) {
                    $result .= "<h3><font color=red>";
                    $result .= sprintf(pacsone_gettext("Error running query: [%s]"), $query);
                    $result .= $dbcon->getError();
                    $result .= "</font></h3><p>\n";
                    break;
                }
            }
        }
    }
    if (!strlen($result)) {   // success
        $message = sprintf(pacsone_gettext("User Profile Updated Successfully for User: %s"), $username);
        print "<script language=\"JavaScript\">\n";
        print "<!--\n";
        print "alert(\"$message\");";
        print "history.go(-1);\n";
        print "//-->\n";
        print "</script>\n";
        modifyEntryForm($user);
    }
    else {                  // error
        print "<p><font color=red>$result</font>";
    }
}
require_once 'footer.php';
print "</body>\n";
print "</html>\n";

function modifyEntryForm($user)
{
    global $PRODUCT;
    global $dbcon;
    // display Modify User Account form
    print "<p>";
    printf(pacsone_gettext("Modify user profile for: <b><u>%s</u></b>"), $user) . "<p>";
    $query = "select * from privilege where username=?";
    $bindList = array($user);
    $result = $dbcon->preparedStmt($query, $bindList);
    if ($result->rowCount() == 1) {
        $row = $result->fetch(PDO::FETCH_ASSOC);
        print "<form method='POST' action='profile.php'>\n";
		print "<input type=hidden name='user' value='$user'>\n";
        print "<table width=100% border=1 cellspacing=0 cellpadding=5>\n";
		// password
    	print "<tr><td>";
        print pacsone_gettext("Change User's Password:") . "</td>\n";
		print "<td>";
        global $PASSWD_SPECIAL_CHARS;
        printf(pacsone_gettext("New password for current user (must be at least 8 characters and include 1 number, 1 capital letter and 1 special character from \"%s\")"), $PASSWD_SPECIAL_CHARS);
        print "</td>\n";
    	$value = "<td><input type='password' size=20 maxlength=20 name='password'";
		$value .= "></td></tr>\n";
		print $value;
        if (!$dbcon->isAdministrator($user)) {
		    // firstname
    	    print "<tr><td>";
            print pacsone_gettext("Enter User's First Name:") . "</td>\n";
		    print "<td>";
            print pacsone_gettext("First name of the user (upto 20 characters)") . "</td>\n";
    	    $value = "<td><input type='text' size=20 maxlength=20 name='firstname'";
		    if (isset($row['firstname']))
			    $value .= " value='" . $row['firstname'] . "'";
		    $value .= " readonly></td></tr>\n";
		    print $value;
		    // lastname
    	    print "<tr><td>";
            print pacsone_gettext("Enter User's Last Name:") . "</td>\n";
		    print "<td>";
            print pacsone_gettext("Last name of the user (upto 20 characters)") . "</td>\n";
    	    $value = "<td><input type='text' size=20 maxlength=20 name='lastname'";
		    if (isset($row['lastname']))
			    $value .= "value='" . $row['lastname'] . "'";
		    $value .= " readonly></td></tr>\n";
		    print $value;
		    // middlename
    	    print "<tr><td>";
            print pacsone_gettext("Enter User's Middle Name:") . "</td>\n";
		    print "<td>";
            print pacsone_gettext("Middle name of the user (upto 20 characters)") . "</td>\n";
    	    $value = "<td><input type='text' size=20 maxlength=20 name='middlename'";
		    if (isset($row['middlename']))
			    $value .= " value='" . $row['middlename'] . "'";
		    $value .= " readonly></td></tr>\n";
		    print $value;
		    // email address
    	    print "<tr><td>";
            print pacsone_gettext("Enter User's Email Address:") . "</td>\n";
		    print "<td>";
            print pacsone_gettext("Email address of the user (upto 64 characters)") . "</td>\n";
    	    $value = "<td><input type='text' size=64 maxlength=64 name='email'";
		    if (isset($row['email']))
			    $value .= " value='" . $row['email'] . "'";
		    $value .= "></td></tr>\n";
		    print $value;
        }
		// preferred Import folder
    	print "<tr><td>";
        print pacsone_gettext("Enter Preferred Import Directory:") . "</td>\n";
		print "<td>";
        printf(pacsone_gettext("If defined, the Preferred Import Directory will be displayed instead of the system default import directory when Importing files to %s"), $PRODUCT) . "</td>\n";
    	$value = "<td><input type='text' size=64 maxlength=255 name='importdir'";
		if (isset($row['importdir']))
			$value .= " value='" . $row['importdir'] . "'";
		$value .= "></td></tr>\n";
		print $value;
		// preferred removable drive for Import
    	print "<tr><td>";
        print pacsone_gettext("Enter Preferred Import Removable Media Drive:") . "</td>\n";
		print "<td>";
        printf(pacsone_gettext("If defined, the Preferred Import Removable Media Drive will be displayed instead of the system default when Importing from removable media to %s"), $PRODUCT) . "</td>\n";
    	$value = "<td><input type='text' size=64 maxlength=255 name='importdrive'";
		if (isset($row['importdrive']))
			$value .= " value='" . $row['importdrive'] . "'";
		$value .= "></td></tr>\n";
		print $value;
		// preferred Import destination folder
    	print "<tr><td>";
        print pacsone_gettext("Enter Preferred Import Destination Folder:") . "</td>\n";
		print "<td>";
        printf(pacsone_gettext("If defined, the Preferred Import Destination Folder will be displayed instead of the system default when Importing from removable media to %s"), $PRODUCT) . "</td>\n";
    	$value = "<td><input type='text' size=64 maxlength=255 name='importdest'";
		if (isset($row['importdest']))
			$value .= " value='" . $row['importdest'] . "'";
		$value .= "></td></tr>\n";
		print $value;
		// preferred Export folder
    	print "<tr><td>";
        print pacsone_gettext("Enter Preferred Export Directory:") . "</td>\n";
		print "<td>";
        printf(pacsone_gettext("If defined, the Preferred Export Directory will be displayed instead of the system default export directory when Exporting files from %s"), $PRODUCT) . "</td>\n";
    	$value = "<td><input type='text' size=64 maxlength=255 name='exportdir'";
		if (isset($row['exportdir']))
			$value .= " value='" . $row['exportdir'] . "'";
		$value .= "></td></tr>\n";
		print $value;
		// share study or image notes, i.e., allow other users to edit the notes entered by this user
    	print "<tr><td>";
        print pacsone_gettext("Share Study/Image Notes:") . "</td>\n";
		print "<td>";
        print pacsone_gettext("If enabled, other users will be able to modify the notes entered by this user about a subject study or image");
        print "</td><td>";
        $value = $row['sharenotes'];
        $checkedYes = $value? "checked" : "";
        $checkedNo = $value? "" : "checked";
        print "<input type='radio' name='sharenotes' value=1 $checkedYes>" . pacsone_gettext("Yes");
        print "&nbsp;";
        print "<input type='radio' name='sharenotes' value=0 $checkedNo>" . pacsone_gettext("No");
    	print "</td></tr>";
        // customize columns displayed in study list pages
        print "<tr><td>";
        print pacsone_gettext("Customize Study Views");
        print "</td><td>";
        print pacsone_gettext("Select the study information to be displayed for the Study List pages");
        print "</td><td>";
        global $STUDY_VIEW_COLUMNS_TBL;
        $columns = $STUDY_VIEW_COLUMNS_TBL;
        $result = $dbcon->preparedStmt("select * from studyview where username=?", $bindList);
        while ($result && ($studyrow = $result->fetch(PDO::FETCH_ASSOC))) {
            $coln = strtolower($studyrow['columnname']);
            $enabled = $studyrow['enabled'];
            if (array_key_exists($coln, $columns))
                $columns[$coln][1] = $enabled;
        }
        foreach ($columns as $coln => $value) {
            $descr = $value[0];
            $checked = $value[1]? "checked" : "";
            print "<input type='checkbox' name='columns[]' value='$coln' $checked>$descr</input>";
            print "<br>";
        }
        print "</td></tr>";
        // customize columns displayed in patient list pages
        print "<tr><td>";
        global $CUSTOMIZE_PATIENT;
        printf(pacsone_gettext("Customize %s Views"), $CUSTOMIZE_PATIENT);
        print "</td><td>";
        printf(pacsone_gettext("Select the information to be displayed for the %s List pages"), $CUSTOMIZE_PATIENT);
        print "</td><td>";
        global $PATIENT_VIEW_COLUMNS_TBL;
        global $PATIENT_VIEW_COLUMNS_TBL_VET;
        $columns = $dbcon->isVeterinary()? $PATIENT_VIEW_COLUMNS_TBL_VET : $PATIENT_VIEW_COLUMNS_TBL;
        $result = $dbcon->preparedStmt("select * from patientview where username=?", $bindList);
        while ($result && ($patientrow = $result->fetch(PDO::FETCH_ASSOC))) {
            $coln = strtolower($patientrow['columnname']);
            $enabled = $patientrow['enabled'];
            if (array_key_exists($coln, $columns))
                $columns[$coln][1] = $enabled;
        }
        foreach ($columns as $coln => $value) {
            $descr = $value[0];
            $checked = $value[1]? "checked" : "";
            print "<input type='checkbox' name='patientcolumns[]' value='$coln' $checked>$descr</input>";
            print "<br>";
        }
        print "</td></tr>";
        // customize number of items displayed in web pages
        print "<tr><td>";
        print pacsone_gettext("Customize Web Page Size");
        print "</td><td>";
        print pacsone_gettext("Enter the number of items that will be displayed in each web page");
        print "</td><td>";
        $value = $row["pagesize"];
        print "<input type='text' name='pagesize' size=2 maxlength=3 value=$value></input>";
        print "</td></tr>";
        // whether or not to display the Study Notes URL link
        $value = $row['studynoteicon'];
        $checkedYes = $value? "checked" : "";
        $checkedNo = $value? "" : "checked";
        print "<tr><td>";
        print pacsone_gettext("Show Study Notes URL Link");
        print "</td><td>";
        print pacsone_gettext("If enabled, a URL-linked Study Notes icon will be displayed in the checkbox column in front of each subject study");
        print "</td><td>";
        print "<input type=radio name='studynoteicon' value=1 $checkedYes>";
        print pacsone_gettext("Yes");
        print "&nbsp;<input type=radio name='studynoteicon' value=0 $checkedNo>";
        print pacsone_gettext("No");
        print "</td></tr>";
        // automatic refresh period for web browsers
        print "<tr><td>";
        print pacsone_gettext("Browser Refresh Period");
        print "</td><td>";
        print pacsone_gettext("Enter the time interval in seconds for automatically refresh web browsers");
        print "</td><td>";
        $value = $row["refreshperiod"];
        print "<input type='text' name='refreshperiod' size=6 maxlength=6 value=$value></input>";
        print "</td></tr>";
        print "</table>\n";
        print "<p><input class='btn btn-primary' type='submit' value='";
        print pacsone_gettext("Modify");
        print "'>\n";
        print "</form>\n";
    }
    else {
        print "<h3><font color=red>";
        printf(pacsone_gettext("<u>%s</u> not found in database"), $user);
        print "</font></h3>\n";
    }
}

?>
