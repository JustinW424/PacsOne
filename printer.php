<?php
//
// printer.php
//
// Module for querying DICOM Print SCP as a SCU
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once "dicom.php";
include_once 'sharedData.php';
global $PRODUCT;
print "<html>\n";
print "<head><title>$PRODUCT - ";
print pacsone_gettext("Printing Options");
print "</title></head>\n";
print "<body>\n";
require_once "header.php";

function annotationDisplayFormat(&$username, &$printer, &$type)
{
    global $dbcon;
    $bindList = array($printer);
    $readonly = $dbcon->hasaccess("modifydata", $username)? "" : "readonly";
    if (stristr($type, "Kodak DryView")) {
        print "<tr><td>";
        print pacsone_gettext("Annotation Display Format") . "</td><td>";
        print "<input type=radio name='annotFormat' value='1'>1</input><br>";
        print "<input type=radio name='annotFormat' value='6'>6</input><br>";
        $value = "";
        $result = $dbcon->preparedStmt("select text from annotation where printer=? and format='LABEL' and position=0", $bindList);
        if ($result) {
            $value = $result->fetchColumn();
        }
        print "<input type=radio name='annotFormat' value='LABEL'>";
        print pacsone_gettext("LABEL") . " </input>";
        print pacsone_gettext("with Custom Annotation Text: ");
        print "<input type=text name='labelText' size=64 maxlength=64 value='$value' $readonly><br>";
        print "<input type=radio name='annotFormat' value='BOTTOM'>";
        print pacsone_gettext("Bottom") . "</input><br>";
        $value = "";
        $result = $dbcon->preparedStmt("select text from annotation where printer=? and format='COMBINED' and position=0", $bindList);
        if ($result) {
            $value = $result->fetchColumn();
        }
        print "<input type=radio name='annotFormat' value='COMBINED'>";
        print pacsone_gettext("COMBINED") . " </input>";
        print pacsone_gettext("with Custom Annotation Text: ");
        print "<input type=text name='combinedText' size=64 maxlength=64 value='$value' $readonly><br>";
        print "<input type=radio name='annotFormat' value='NONE' checked>None</input><br>";
        print "<input type=hidden name='printerType' value='$type'>";
        print "</td></tr>";
    }
    else if (stristr($type, "Agfa DS5300")) {
        $location = array(
            1 => pacsone_gettext("Upper-Left Corner"),
            2 => pacsone_gettext("Lower-Left Corner"),
            3 => pacsone_gettext("Upper-Left Corner Below Annotation Box 1"),
            4 => pacsone_gettext("Upper-Left Corner Below Annotation Box 3"),
            5 => pacsone_gettext("Lower-Left Corner Below Annotation Box 2"),
            6 => pacsone_gettext("Lower-Left Corner Below Annotation Box 5"),
        );
        $defaults = array(
            1 => "%PATIENTNAME% %PATIENTID% %PATIENTBIRTHDATE% %PATIENTSEX%",
            3 => "%STUDYID%",
            4 => "%STUDYDATE%");
        $formats = array();
        $result = $dbcon->preparedStmt("select position,text from annotation where printer=?", $bindList);
        if ($result) {
            while ($row = $result->fetch(PDO::FETCH_NUM))
                $formats[ $row[0] ] = $row[1];
        }
        print "<tr><td>";
        print pacsone_gettext("Annotation Display Format");
        print "</td><td>";
        for ($pos = 1; $pos <= 6; $pos++) {
            $checked = array_key_exists($pos, $formats)? "checked" : "";
            // use defaults if undefined
            if (count($formats) == 0)
                $checked = array_key_exists($pos, $defaults)? "checked" : "";
            print "<input type=checkbox name='pos[]' value=$pos $checked>";
            print "<b>Box $pos</b> - " . $location[$pos] . "&nbsp;";
            $text = isset($formats[$pos])? $formats[$pos] : "";
            // use defaults if undefined
            if (!count($formats) && isset($defaults[$pos]))
                $text = $defaults[$pos];
            print "<input type=text name='text[]' size=80 maxlength=64 value='$text' $readonly><br>";
        }
        print "<input type=hidden name='printerType' value='$type'>";
        print "<input type=hidden name='annotFormat' value=''>";
        print "</td></tr>";
    }
    else if (stristr($type, "Fuji DRYPIX3000")) {
        $location = array(
            1 => pacsone_gettext("Upper Left"),
            2 => pacsone_gettext("Upper Center"),
            3 => pacsone_gettext("Upper Right"),
            4 => pacsone_gettext("Lower Left"),
            5 => pacsone_gettext("Lower Center"),
            6 => pacsone_gettext("Lower Right"),
        );
        $defaults = array(
            1 => "%Insitution Name (0008,0080)%",
            2 => "%Plate ID (0018,1004)% %Acquisition Device Processing Code (0018,1401)% %Sensitivity (0018,6000)%",
            4 => "%Derivation Description (0008,2111)% %Study Description (0008,1030)% %Requesting Service (0032,1033)% %Accession Number (0008,0050)%",
            5 => "%Patient ID (0010,0020)% %Patient's Name (0010,0010)% %Patient's Sex (0010,0040)% %Patient's Birth Date (0010,0030)% %Acquisition Date (0008,0022)% %Acquisition Time (0008,0032)%",
        );
        $formats = array();
        $result = $dbcon->preparedStmt("select position,text from annotation where printer=?", $bindList);
        if ($result) {
            while ($row = $result->fetch(PDO::FETCH_NUM))
                $formats[ $row[0] ] = $row[1];
        }
        print "<tr><td>";
        print pacsone_gettext("Annotation Display Format");
        print "</td><td>";
        for ($pos = 1; $pos <= 6; $pos++) {
            $checked = array_key_exists($pos, $formats)? "checked" : "";
            // use defaults if undefined
            if (count($formats) == 0)
                $checked = array_key_exists($pos, $defaults)? "checked" : "";
            print "<input type=checkbox name='pos[]' value=$pos $checked>";
            print "<b>Box $pos</b> - " . $location[$pos] . "&nbsp;";
            $text = isset($formats[$pos])? $formats[$pos] : "";
            // use defaults if undefined
            if (!count($formats) && isset($defaults[$pos]))
                $text = $defaults[$pos];
            print "<input type=text name='text[]' size=128 maxlength=256 value=\"$text\" $readonly><br>";
        }
        print "<input type=hidden name='printerType' value='$type'>";
        print "<input type=hidden name='annotFormat' value=''>";
        print "</td></tr>";
    }
}

function checkIsColorImage(&$dbcon, &$uid)
{
    $color = 0;
    $query = "select photometric from image where uuid=?";
    $bindList = array($uid);
    $result = $dbcon->preparedStmt($query, $bindList);
    if ($result && $result->rowCount()) {
        $photometric = $result->fetchColumn();
        if (!strcasecmp($photometric, "RGB") ||
            !strcasecmp($photometric, "PALETTE COLOR") ||
            stristr($photometric, "YBR"))
            $color = 1;
    }
    return $color;
}

function checkSeriesHasColorImage(&$dbcon, &$uid)
{
    $color = 0;
    $query = "select photometric from image where seriesuid=?";
    $bindList = array($uid);
    $result = $dbcon->preparedStmt($query, $bindList);
    while ($photometric = $result->fetchColumn()) {
        if (!strcasecmp($photometric, "RGB") ||
            !strcasecmp($photometric, "PALETTE COLOR") ||
            stristr($photometric, "YBR")) {
            $color = 1;
            break;
        }
    }
    return $color;
}

function checkStudyHasColorImage(&$dbcon, &$uid)
{
    $color = 0;
    $query = "select uuid from series where studyuid=?";
    $bindList = array($uid);
    $result = $dbcon->preparedStmt($query, $bindList);
    while ($seriesUid = $result->fetchColumn()) {
        $color = checkSeriesHasColorImage($dbcon, $seriesUid);
        if ($color)
            break;
    }
    return $color;
}

function checkPatientHasColorImage(&$dbcon, &$uid)
{
    $color = 0;
    $query = "select uuid from study where patientid=?";
    $bindList = array($uid);
    $result = $dbcon->preparedStmt($query, $bindList);
    while ($studyUid = $result->fetchColumn()) {
        $color = checkStudyHasColorImage($dbcon, $studyUid);
        if ($color)
            break;
    }
    return $color;
}

function checkHasColorImage(&$option, &$uid)
{
    global $dbcon;
    $result = 0;
    if (strcasecmp($option, "Patient") == 0)
        $result = checkPatientHasColorImage($dbcon, $uid);
    else if (strcasecmp($option, "Study") == 0)
        $result = checkStudyHasColorImage($dbcon, $uid);
    else if (strcasecmp($option, "Series") == 0)
        $result = checkSeriesHasColorImage($dbcon, $uid);
    else if (strcasecmp($option, "Image") == 0)
        $result = checkIsColorImage($dbcon, $uid);
    return $result;
}

$dbcon = new MyConnection();
$username = $dbcon->username;
if (isset($_POST['printer'])) {
    $printer = $_POST['printer'];
    $query = "SELECT printerType FROM applentity WHERE title=?";
    $bindList = array($printer);
    $result = $dbcon->preparedStmt($query, $bindList);
    $printerType = $result->fetchColumn();
    $entry = $_POST['entry'];
    $option = $_POST['option'];
    print "<form method='POST' action='print.php'>\n";
    print "<input type=hidden name='aetitle' value='$printer'></input>";
    print "<table width=100% cellpadding=0 cellspacing=0 border=0>\n";
    print "<tr><td>";
    printf(pacsone_gettext("Print the following %s to <u>%s</u>: <b>%s</b>"), $option, $printerType, $printer);
    print "</td></tr>\n";
    print "<tr><td><br></td></tr>\n";
    $color = 0;
    foreach ($entry as $uid) {
        // check if there is at least one color image
        if (!$color)
            $color = checkHasColorImage($option, $uid);
        if (strcasecmp($option, "Patient") == 0)
            $uid = urldecode($uid);
        print "<tr><td>$uid</td></tr>\n";
    }
    print "<tr><td><br></td></tr>\n";
    print "<tr><td>";
    print "<table width=100% cellpadding=0 cellspacing=0 border=1>\n";
    print "<tr><td>" . pacsone_gettext("Number of Copies") . "</td>";
    print "<td><input type=text size=4 maxlength=4 name='copies' value=1></td>";
    print "</td></tr>\n";
    print "<tr><td>" . pacsone_gettext("Orientation") . "</td>";
    print "<td><input type=radio name='orientation' value='P' checked>";
    print pacsone_gettext("Portrait") . "<br>";
    print "<input type=radio name='orientation' value='L'>";
    print pacsone_gettext("Landscape");
    print "</td></tr>";
    print "<tr><td>" . pacsone_gettext("Image Display Format") . "</td>";
    print "<td><input type=radio name='format' value='STANDARD' checked>";
    print pacsone_gettext("Standard: <input type=text size=4 maxlength=4 name='row' value=2> Rows by <input type=text size=4 maxlength=4 name='column' value=2> Columns<br>");
    print "<input type=radio name='format' value='ROW'>";
    print pacsone_gettext("R1,R2,R3 Rows ");
    print "<input type=text size=16 maxlength=16 name='rows'><br>";
    print "<input type=radio name='format' value='COL'>";
    print pacsone_gettext("C1,C2,C3 Columns");
    print "<input type=text size=16 maxlength=16 name='columns'><br>";
    print "<input type=radio name='format' value='SLIDE'>";
    print pacsone_gettext("35mm Slides") . "<br>";
    print "<input type=radio name='format' value='SUPERSLIDE'>";
    print pacsone_gettext("40mm Slides") . "<br>";
    print "<input type=radio name='format' value='CUSTOM'>";
    print pacsone_gettext("Printer Custom Format: ");
    print "<input type=text size=4 maxlength=4 name='custom'><br>";
    print "</td></tr>";
    print "<tr><td>" . pacsone_gettext("Polarity") . "</td>";
    print "<td><input type=radio name='polarity' value=0 checked>";
    print pacsone_gettext("Normal") . "<br>";
    print "<input type=radio name='polarity' value=1>";
    print pacsone_gettext("Reverse") . "<br>";
    print "</td></tr>";
    print "<tr><td>" . pacsone_gettext("Print Priority") . "</td>";
    print "<td><input type=radio name='priority' value=0>";
    print pacsone_gettext("LOW") . "<br>";
    print "<input type=radio name='priority' value=1 checked>";
    print pacsone_gettext("MEDIUM") . "<br>";
    print "<input type=radio name='priority' value=2>";
    print pacsone_gettext("HIGH") . "<br>";
    print "</td></tr>";
    print "<tr><td>" . pacsone_gettext("Print Medium") . "</td>";
    print "<td><input type=radio name='medium' value='PAPER' checked>";
    print pacsone_gettext("Paper") . "<br>";
    print "<input type=radio name='medium' value='CLEARFILM'>";
    print pacsone_gettext("Clear Film") . "<br>";
    print "<input type=radio name='medium' value='BLUEFILM'>";
    print pacsone_gettext("Blue Film") . "<br>";
    print "</td></tr>";
    print "<tr><td>" . pacsone_gettext("Film Size ID") . "</td>";
    print "<td><input type=radio name='filmsizeid' value='Default' checked>";
    print pacsone_gettext("Default") . "<br>";
    global $FILM_SIZE_ID_TBL;
    foreach ($FILM_SIZE_ID_TBL as $id => $descrp) {
        print "<input type=radio name='filmsizeid' value='$id'>$descrp" . "<br>";
    }
    print "</td></tr>";
    print "<tr><td>" . pacsone_gettext("Film Destination") . "</td>";
    print "<td><input type=radio name='destination' value='MAGAZINE' checked>";
    print pacsone_gettext("Magazine") . "<br>";
    print "<input type=radio name='destination' value='PROCESSOR'>";
    print pacsone_gettext("Processor") . "<br>";
    print "<input type=radio name='destination' value='BIN'>";
    print pacsone_gettext("Bin: ");
    print "<input type=text size=4 maxlength=4 name='bin'><br>";
    print "</td></tr>";
    annotationDisplayFormat($username, $printer, $printerType);
    // check if need to convert color images to grayscale
    if ($color) {
        print "<tr><td>" . pacsone_gettext("Convert All Color Images to Grayscale") . "</td>";
        print "<td><input type=radio name='convertColor' value=1>";
        print pacsone_gettext("Yes") . "<br>";
        print "<input type=radio name='convertColor' value=0 checked>";
        print pacsone_gettext("No");
        print "</td></tr>";
    }
    print "</table>\n";
    print "</td></tr>";
    print "</table>\n";
    print "<input type=hidden name='option' value=$option>\n";
    foreach ($entry as $uid) {
        print "<input type=hidden name='entry[]' value='$uid'>\n";
    }
    print "<p><input type='submit' value='";
    print pacsone_gettext("Print");
    print "'>\n";
    print "</form>\n";
} else {
    $ipaddr = $_REQUEST['ipaddr'];
    $hostname = $_REQUEST['hostname'];
    $port = $_REQUEST['port'];
    $aetitle = $_REQUEST['aetitle'];
    $mytitle = $_REQUEST['mytitle'];
    $tls = $_REQUEST['tls'];
    $error = '';
    $assoc = new Association($ipaddr, $hostname, $port, $aetitle, $mytitle, $tls);
    $list = $assoc->getPrinter($error);
    if (!$list) {
        print '<br><font color=red>';
        printf(pacsone_gettext('Failed to get printer properties: error = %s'), $error);
        print '</font><br>';
    }
    else {
        require_once "display.php";
        displayPrinterAttrs($aetitle, $list);
    }
}
require_once 'footer.php';
print "</body>\n";
print "</html>\n";

?>
