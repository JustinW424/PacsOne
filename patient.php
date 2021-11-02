<?php
//
// patient.php
//
// Module for displaying Patient table
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once 'security.php';
include_once 'sharedData.php';

global $PRODUCT;
global $BGCOLOR;
global $MYFONT;
global $CUSTOMIZE_PATIENT;
print "<html>";
print "<head><title>$PRODUCT - ";
printf(pacsone_gettext("Detailed %s Information"), $CUSTOMIZE_PATIENT);
print "</title></head>";
print "<body>";
require_once 'header.php';
$patientId = urldecode($_REQUEST['patientId']);
if (preg_match("/[;\"]/", $patientId)) {
    $error = pacsone_gettext("Invalid Patient ID");
    print "<h2><font color=red>$error</font></h2>";
    exit();
}
$dbcon = new MyConnection();
$username = $dbcon->username;
$modify = $dbcon->hasaccess("modifydata", $username);
$patientId = get_magic_quotes_gpc()? stripslashes($patientId) : $patientId;
$query = "SELECT * FROM patient where origid=?";
$bindList = array($patientId);
$result = $dbcon->preparedStmt($query, $bindList);
if ($result && $result->rowCount()) {
    $row = $result->fetch(PDO::FETCH_ASSOC);
    require_once 'display.php';
    print "<p><table width=100% border=0 cellpadding=5>";
    print "<tr class=listhead bgcolor=$BGCOLOR>\n";
    $columns = array_keys($row);
    foreach ($columns as $column) {
        // hide veterinary columns
        if (!$dbcon->isVeterinary() && $dbcon->isVeterinaryColumn($column))
            continue;
        $header = ucfirst($column);
        print "\t<td><b>$header</b></td>\n";
    }
    print "</tr>\n";
    $patientId = urlencode($row["origid"]);
    print "<tr>\n";
    foreach ($row as $key => $field) {
        // hide veterinary columns
        if (!$dbcon->isVeterinary() && $dbcon->isVeterinaryColumn($key))
            continue;
        if ((!strcasecmp($key, "origid") || !strcasecmp($key, "middlename") ||
            !strcasecmp($key, "lastname") || !strcasecmp($key, "firstname") ||
            !strcasecmp($key, "sex") || !strcasecmp($key, "birthdate") ||
            !strcasecmp($key, "ideographic") || !strcasecmp($key, "phonetic") ||
            !strcasecmp($key, "institution") || !strcasecmp($key, "history")) && $modify) {
            if (strcasecmp($key, "birthdate") == 0)
                $field = $dbcon->formatDate($field);
            else if (!strcasecmp($key, "ideographic") || !strcasecmp($key, "phonetic")) {
                // remove any Dicom escape sequence
                $esc = $dbcon->getCharsetEscape();
                if (strlen($esc))
                    $field = str_replace($esc, "", $field);
            } else if (!strcasecmp($key, "lastname") || !strcasecmp($key, "firstname") ||
                       !strcasecmp($key, "middlename")) {
                $field = $dbcon->convertCharset($field, $row["charset"]);
            }
            $column = urlencode($key);
            $encoded = urlencode($field);
            $url = "modifyPatient.php?patientId=$patientId&column=$column&value=$encoded";
            if (strlen($field) == 0)
                $field = pacsone_gettext("(Blank)");
            print "\t<td><a href='$url'>$field</a></td>\n";
        } else {
            if (isset($field)) {
                if (strcasecmp($key, "lastaccess") == 0)
                    $field = $dbcon->formatDateTime($field);
            } else {
                $field = pacsone_gettext("N/A");
            }
            print "\t<td>$MYFONT$field</font></td>\n";
        }
    }
    print "</tr>\n";
    print "</table>";
} else {
    print "<p><br>";
    global $CUSTOMIZE_PATIENT_ID;
    printf(pacsone_gettext("%s: <b>%s</b> Not Found."), $CUSTOMIZE_PATIENT_ID, $patientId);
    print "<p>";
}

require_once 'footer.php';
print "</body>";
print "</html>";

?>
