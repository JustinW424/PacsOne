<?php
//
// modifyPatient.php
//
// Module for modifying Patient Table
//
// CopyRight (c) 2004-2021 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once 'database.php';
include_once 'sharedData.php';

// main
global $PRODUCT;
global $CUSTOMIZE_PATIENT;
global $CUSTOMIZE_PATIENT_NAME;
$dbcon = new MyConnection();
$username = $dbcon->username;
if (isset($_POST['modified'])) {
    ob_start();
    $toResolve = array();
    $patientId = $_POST['patientId'];
    $column = $_POST['column'];
    $modified = get_magic_quotes_gpc()? stripslashes($_POST['modified']) : $_POST['modified'];
    // schedule jobs to modify Patient ID's stored in raw image files
    if (strcasecmp($column, "origid") == 0) {
        $title = "_patientid";
        $query = "select uuid from study where patientid=?";
        $bindList = array($patientId);
        $result = $dbcon->preparedStmt($query, $bindList);
        if ($result && $result->rowCount()) {
            while ($row = $result->fetch(PDO::FETCH_NUM)) {
                $toResolve[ $row[0] ] = $modified;
            }
        }
        $query = "update study set patientid=? where patientid=?";
        $bindList = array($modified, $patientId);
        $dbcon->preparedStmt($query, $bindList);
        // update Patient table
        $query = "update patient set origid=? where origid=?";
        $dbcon->preparedStmt($query, $bindList);
        // log activity to system journal
        $dbcon->logJournal($username, "Modify", $CUSTOMIZE_PATIENT, "$patientId|$column|$modified");
        $patientId = stripslashes($modified);
    } else if (strcasecmp($column, "sex") == 0) {
        $title = "_gender";
        $query = "select uuid from study where patientid=?";
        $bindList = array($patientId);
        $result = $dbcon->preparedStmt($query, $bindList);
        if ($result && $result->rowCount()) {
            while ($row = $result->fetch(PDO::FETCH_NUM)) {
                $toResolve[ $row[0] ] = $modified;
            }
        }
        // update Patient table
        $query = "update patient set sex=? where origid=?";
        $bindList = array($modified, $patientId);
        $dbcon->preparedStmt($query, $bindList);
        // log activity to system journal
        $what = $_POST['original'] . "->" . $modified;
        $dbcon->logJournal($username, "Modify", $CUSTOMIZE_PATIENT, "$patientId|Gender|$what");
    } else if (strcasecmp($column, "birthdate") == 0) {
        $title = "_birthdate";
        // log activity to system journal
        $what = $_POST['original'] . "->" . $modified;
        $dbcon->logJournal($username, "Modify", $CUSTOMIZE_PATIENT, "$patientId|BirthDate|$what");
        // update by study
        if ($dbcon->isEuropeanDateFormat())
            $modified = reverseDate($modified);
        $query = "select uuid from study where patientid=?";
        $bindList = array($patientId);
        $result = $dbcon->preparedStmt($query, $bindList);
        if ($result && $result->rowCount()) {
            while ($row = $result->fetch(PDO::FETCH_NUM)) {
                $toResolve[ $row[0] ] = $modified;
            }
        }
        // update Patient table
        $query = "update patient set birthdate=? where origid=?";
        $bindList = array($modified, $patientId);
        $dbcon->preparedStmt($query, $bindList);
    } else if (!strcasecmp($column, "institution") || !strcasecmp($column, "history")) {
        $title = strcasecmp($column, "institution")? "_history" : "_institution";
        $query = "select uuid from study where patientid=?";
        $bindList = array($patientId);
        $result = $dbcon->preparedStmt($query, $bindList);
        if ($result && $result->rowCount()) {
            while ($row = $result->fetch(PDO::FETCH_NUM)) {
                $toResolve[ $row[0] ] = $modified;
            }
        }
        // update Patient table
        $query = "update patient set $column=? where origid=?";
        $bindList = array($modified, $patientId);
        $dbcon->preparedStmt($query, $bindList);
        // log activity to system journal
        $what = $_POST['original'] . "->" . $modified;
        $field = strcasecmp($column, "institution")? "Patient History" : "Institution Name";
        $dbcon->logJournal($username, "Modify", $CUSTOMIZE_PATIENT, "$patientId|$field|$what");
    } else {
        $bindList = array();
        $title = "_patientname";
        $modified = "";
        $query = "update patient set ";
        $lastname = $_POST['lastname'];
        $value = get_magic_quotes_gpc()? stripslashes($lastname) : $lastname;
        $query .= "lastname=?,";
        $bindList[] = $value;
        if (strlen($lastname))
            $modified .= $value;
        $modified .= "^";
        $firstname = $_POST['firstname'];
        $value = get_magic_quotes_gpc()? stripslashes($firstname) : $firstname;
        $query .= "firstname=?,";
        $bindList[] = $value;
        if (strlen($firstname))
            $modified .= $value;
        $modified .= "^";
        $middlename = $_POST['middlename'];
        $value = get_magic_quotes_gpc()? stripslashes($middlename) : $middlename;
        $query .= "middlename=?";
        $bindList[] = $value;
        if (strlen($middlename))
            $modified .= $value;
        // support patient names encoded in non-default charsets
        $esc = $dbcon->getCharsetEscape();
        if (isset($_POST['ideographic']) && strlen($_POST['ideographic'])) {
            $value = $_POST['ideographic'];
            if (strlen($esc)) {
                // escape the ideographic name
                $tokens = explode("^", $value);
                $value = "";
                foreach ($tokens as $token) {
                    if (strlen($value))
                        $value .= "^";
                    $value .= $esc . $token;
                }
            }
            $query .= ",ideographic=?";
            $bindList[] = $value;
            $modified .= "=" . $value;
        } else {
            $query .= ",ideographic=NULL";
        }
        if (isset($_POST['phonetic']) && strlen($_POST['phonetic'])) {
            $value = $_POST['phonetic'];
            if (strlen($esc)) {
                // escape the phonetic name
                $tokens = explode("^", $value);
                $value = "";
                foreach ($tokens as $token) {
                    if (strlen($value))
                        $value .= "^";
                    $value .= $esc . $token;
                }
            }
            $query .= ",phonetic=?";
            $bindList[] = $value;
            $modified .= "=" . $value;
        } else {
            $query .= ",phonetic=NULL";
        }
        $subq = "select uuid from study where patientid=?";
        $subList = array($patientId);
        $result = $dbcon->preparedStmt($subq, $subList);
        if ($result && $result->rowCount()) {
            while ($row = $result->fetch(PDO::FETCH_NUM)) {
                $toResolve[ $row[0] ] = $modified;
            }
        }
        // update Patient table
        $query .= " where origid=?";
        $bindList[] = $patientId;
        $dbcon->preparedStmt($query, $bindList);
        // log activity to system journal
        $what = $_POST['original'] . "->" . $modified;
        $dbcon->logJournal($username, "Modify", $CUSTOMIZE_PATIENT, "$patientId|$CUSTOMIZE_PATIENT_NAME|$what");
    }
    if (count($toResolve)) {
        foreach ($toResolve as $uid => $resolved) {
            $query = "insert into dbjob (username,type,aetitle,class,uuid,details,submittime,status) ";
            $query .= "values(?,'resolve',?,'study',?,?,";
            $bindList = array($username, $title, $uid, $resolved);
            $query .= $dbcon->useOracle? "SYSDATE," : "NOW(),";
            $query .= "'submitted')";
            $dbcon->preparedStmt($query, $bindList);
        }
    }
    header('Location: patient.php?patientId=' . urlencode($patientId));
    exit;
} else {
    $patientId = urldecode($_REQUEST['patientId']);
    if (get_magic_quotes_gpc())
        $patientId = stripslashes($patientId);
    if (preg_match("/[;\"]/", $patientId)) {
        $error = pacsone_gettext("Invalid Patient ID");
        print "<h2><font color=red>$error</font></h2>";
        exit();
    }
    $patientName = $dbcon->getPatientName($patientId);
    $htmlPid = htmlentities($patientId, ENT_QUOTES);
    $column = $_REQUEST['column'];
    global $CUSTOMIZE_PATIENT_ID;
    global $CUSTOMIZE_PATIENT_NAME;
    global $CUSTOMIZE_PATIENT_DOB;
    global $CUSTOMIZE_PATIENT_SEX;
    $columnTbl = array(
        "origid"        =>  $CUSTOMIZE_PATIENT_ID,
        "firstname"     =>  $CUSTOMIZE_PATIENT_NAME,
        "lastname"      =>  $CUSTOMIZE_PATIENT_NAME,
        "middlename"    =>  $CUSTOMIZE_PATIENT_NAME,
        "sex"           =>  $CUSTOMIZE_PATIENT_SEX,
        "birthdate"     =>  $CUSTOMIZE_PATIENT_DOB,
        "institution"   =>  pacsone_gettext("Institution Name"),
        "history"       =>  pacsone_gettext("Additional Patient History"),
        "phonetic"      =>  pacsone_gettext("Phonetic Name"),
        "ideographic"   =>  pacsone_gettext("Ideographic Name"),
    );
    // display Modify Patient Table form
    print "<html>\n";
    print "<head><title>$PRODUCT - ";
    printf(pacsone_gettext("Modify %s Information"), $CUSTOMIZE_PATIENT);
    print "</title></head>\n";
    print "<body>\n";
    require_once 'header.php';
    print "<form method='POST' action='modifyPatient.php'>\n";
    print "<input type='hidden' name='patientId' value='$htmlPid'></input>\n";
    print "<input type='hidden' name='column' value='$column'></input>\n";
    $what = $columnTbl[$column];
    $url = "patient.php?patientId=" . urlencode($patientId);
    print "<p>";
    printf(pacsone_gettext("Modify <b>%s</b> for %s: <a href='%s'>%s</a>"), $what, $CUSTOMIZE_PATIENT, $url, $patientName);
    print "</p>\n";
    $bindList = array($patientId);
    if (strcasecmp($column, "origid") == 0) {
        print "<input type='text' size=16 maxlength=64 name='modified' value='$htmlPid'><br>\n";
    } else if (strcasecmp($column, "sex") == 0) {
        $query = "select sex from patient where origid=?";
        $result = $dbcon->preparedStmt($query, $bindList);
        $value = $result->fetchColumn();
        print "<input type='text' size=8 maxlength=16 name='modified' value='$value'><br>\n";
        print "<input type='hidden' name='original' value='$value'><br>\n";
    } else if (strcasecmp($column, "birthdate") == 0) {
        $query = "select birthdate from patient where origid=?";
        $result = $dbcon->preparedStmt($query, $bindList);
        $value = $result->fetchColumn();
        if ($dbcon->isEuropeanDateFormat())
            $value = reverseDate($value);
        print "<input type='text' size=12 maxlength=16 name='modified' value='$value'><br>\n";
        print "<input type='hidden' name='original' value='$value'><br>\n";
    } else if (strcasecmp($column, "institution") == 0) {
        $query = "select institution from patient where origid=?";
        $result = $dbcon->preparedStmt($query, $bindList);
        $value = htmlentities($result->fetchColumn(), ENT_QUOTES);
        print "<input type='text' size=64 maxlength=64 name='modified' value='$value'><br>\n";
        print "<input type='hidden' name='original' value='$value'><br>\n";
    } else if (strcasecmp($column, "history") == 0) {
        $query = "select history from patient where origid=?";
        $result = $dbcon->preparedStmt($query, $bindList);
        $value = htmlentities($result->fetchColumn(), ENT_QUOTES);
        print "<textarea rows=8 cols=80 name='modified'>$value</textarea><br>\n";
        print "<input type='hidden' name='original' value='$value'><br>\n";
    } else {
        $charset = $dbcon->getBrowserCharset();
        print "<input type='hidden' name='modified' value='PatientName'></input>\n";
        $query = "select lastname,firstname,middlename,ideographic,phonetic,charset from patient where origid=?";
        $result = $dbcon->preparedStmt($query, $bindList);
        $row = $result->fetch(PDO::FETCH_NUM);
        print "<table width=20% border=0 cellpadding=2>";
        $value = $dbcon->convertCharset($row[0], $row[5]);
        if (!strcasecmp($value, $row[0]))
            $value = htmlentities($value, ENT_QUOTES, $charset);
        print "<tr><td>" . pacsone_gettext("LastName:") . "</td>";
        print "<td><input type='text' size=16 maxlength=64 name='lastname' value='$value'></td></tr>\n";
        $value = $dbcon->convertCharset($row[1], $row[5]);
        if (!strcasecmp($value, $row[1]))
            $value = htmlentities($value, ENT_QUOTES, $charset);
        print "<tr><td>" . pacsone_gettext("FirstName:") . "</td>";
        print "<td><input type='text' size=16 maxlength=64 name='firstname' value='$value'></td></tr>\n";
        $value = $dbcon->convertCharset($row[2], $row[5]);
        if (!strcasecmp($value, $row[2]))
            $value = htmlentities($value, ENT_QUOTES, $charset);
        print "<tr><td>" . pacsone_gettext("Middlename:") . "</td>";
        print "<td><input type='text' size=16 maxlength=64 name='middlename' value='$value'></td></tr>\n";
        $original = htmlentities($row[0] . "^" . $row[1] . "^" . $row[2], ENT_QUOTES, $charset);
        // remove any Dicom escape sequence
        $esc = $dbcon->getCharsetEscape();
        if (isset($row[3]) && strlen($row[3])) {
            $value = $row[3];
            if (strlen($esc))
                $value = str_replace($esc, "", $value);
            print "<tr><td>" . pacsone_gettext("Ideographic Name:") . "</td>";
            print "<td><input type='text' size=16 maxlength=64 name='ideographic' value='$value'></td></tr>\n";
            $original .= "=" . $row[3];
        }
        if (isset($row[4]) && strlen($row[4])) {
            $value = $row[4];
            if (strlen($esc))
                $value = str_replace($esc, "", $value);
            print "<tr><td>" . pacsone_gettext("Phonetic Name:") . "</td>";
            print "<td><input type='text' size=16 maxlength=64 name='phonetic' value='$value'></td></tr>\n";
            $original .= "=" . $row[4];
        }
        print "<input type='hidden' name='original' value='$original'><br>\n";
        print "</tr>\n";
        print "</table>";
    }
    print "<p><input type='submit' value='";
    print pacsone_gettext("Modify") . "'></form>\n";
	require_once 'footer.php';
    print "</body>\n";
    print "</html>\n";
}

?>
