<?php
//
// print.php
//
// Module for printing entries from database tables to remote Dicom printer
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once 'security.php';
include_once 'sharedData.php';

function kodakAnnotation(&$aetitle, &$annotFormat)
{
    global $dbcon;
    if (strcasecmp($annotFormat, "Label") == 0)
        $text = $_POST['labelText'];
    else if (strcasecmp($annotFormat, "Combined") == 0)
        $text = $_POST['combinedText'];
    else 
        return;
    $position = 0;
    $query = "select count(text) from annotation where printer=? and format=? and position=0";
    $bindList = array($aetitle, $annotFormat);
    $result = $dbcon->preparedStmt($query, $bindList);
    $row = $result->fetch(PDO::FETCH_NUM);
    $oper = ($row[0])? "update " : "insert into ";
    $query = $oper . "annotation ";
    if ($row[0]) {
        $query .= "set text=? where printer=? ";
        $query .= "and format=? and position=$position";
        $bindList = array($text, $aetitle, $annotFormat);
    } else {
        $query .= "(printer,format,position,text) values(?,?,$position,?)";
        $bindList = array($aetitle, $annotFormat, $text);
    }
    if (!$dbcon->preparedStmt($query, $bindList))
        print pacsone_gettext("Warning: Database Error: ") . $dbcon->getError() . "<br>";
}

global $PRODUCT;
$option = $_POST['option'];
print "<html>\n";
print "<head><title>$PRODUCT - ";
printf(pacsone_gettext("Printing %s"), $option);
print "</title></head>\n";
print "<body>\n";
require_once 'header.php';

$dbcon = new MyConnection();
$username = $dbcon->username;
$aetitle = $_POST['aetitle'];
$entry = $_POST['entry'];
$copies = $_POST['copies'];
$polarity = $_POST['polarity'];
$priority = $_POST['priority'];
$medium = $_POST['medium'];
$destination = $_POST['destination'];
if (stristr($destination, "Bin"))
    $destination .= $_POST['bin'];
if (!ctype_digit($copies)) {
    print "<font color=red>";
    print pacsone_gettext("Number of Copies must be an integer!");
    print "</font>";
    exit();
}
$orientation = $_POST['orientation'];
$format = $_POST['format'];
$param = "";
if (!strcasecmp($format, "Standard")) {
    $param = $_POST['column'] . "," . $_POST['row'];
}
else if (!strcasecmp($format, "Row")) {
    $param = $_POST['rows'];
}
else if (!strcasecmp($format, "Col")) {
    $param = $_POST['columns'];
}
else if (!strcasecmp($format, "Custom")) {
    $param = $_POST['custom'];
}
if (strlen($param)) {
    $check = trim($param);
    $check = str_replace(",", "0", $check);
    if (!ctype_digit($check)) {
        print "<font color=red>";
        printf(pacsone_gettext("Print parameters [%s] must consist of digits and ',' only!"), $param);
        print "</font>";
        exit();
    }
    $format .= "\\" . $param;
}
// encode print parameters
$details = "c:$copies|o:$orientation|f:$format|m:$medium|d:$destination|p:$polarity";
// check optional Annotation Display Format
if (isset($_POST['annotFormat'])) {
    $printerType = $_POST['printerType'];
    $annotFormat = $_POST['annotFormat'];
    $modifyAccess = $dbcon->hasaccess("modifydata", $username);
    if (strcasecmp($printerType, "Kodak DryView 8900") == 0) {
        if ($modifyAccess)
            kodakAnnotation($aetitle, $annotFormat);
    } else if ( (strcasecmp($printerType, "Agfa DS5300") == 0) ||
                (strcasecmp($printerType, "Fuji DRYPIX3000") == 0) ) {
        $posList = isset($_POST['pos'])? $_POST['pos'] : array();
        $textList = isset($_POST['text'])? $_POST['text'] : array();
        $bindList = array($aetitle);
        // remove unchecked annotation boxes
        if ($modifyAccess) {
            for ($pos = 1; $pos <= 6; $pos++) {
                if (!in_array($pos, $posList)) {
                    $dbcon->preparedStmt("delete from annotation where printer=? and position=$pos", $bindList);
                }
            }
        }
        foreach ($posList as $pos) {
            $text = $textList[$pos - 1];
            if ($modifyAccess) {
                $update = false;
                $bindList = array($aetitle);
                $annot = $dbcon->preparedStmt("select * from annotation where printer=? and format='$pos' and position=$pos", $bindList);
                if ($annot && $annot->rowCount())
                    $update = true;
                if ($update) {
                    $query = "update annotation set ";
                    $query .= "printer=?,format='$pos',";
                    $query .= "position=$pos,text=?";
                    $query .= " where printer=? and format='$pos' and position=$pos";
                    $bindList = array($aetitle, $text, $aetitle);
                } else {
                    $query = "insert into annotation (printer,format,position,text) values(";
                    $query .= "?,'$pos',$pos,?)";
                    $bindList = array($aetitle, $text); 
                }
                if (!$dbcon->preparedStmt($query, $bindList)) {
                    print "<font color=red>";
                    print pacsone_gettext("Failed to set annotation format: [$query]");
                    print $dbcon->getError() . "</font>";
                    exit();
                }
            }
            $annotFormat .= $pos . ",";
        }
    }
    if (strlen($annotFormat))
        $details .= "|a:$annotFormat";
}
// check optional File Size ID
if (isset($_POST['filmsizeid'])) {
    $id = $_POST['filmsizeid'];
    if (strcasecmp($id, "DEFAULT")) {
        $details .= "|s:$id";
    }
}
// check if need to convert color to grayscale images
if (isset($_POST['convertColor']) && $_POST['convertColor'])
    $details .= "|C:1";

if (strcasecmp($option, "Image")) {
  foreach ($entry as $uid) {
    if (strcasecmp($option, "Patient") == 0) {
        $uid = urldecode($uid);
    }
    $query = "insert into dbjob (username,aetitle,priority,type,class,uuid,submittime,status,details) ";
    $query .= "values(?,?,$priority,'Print','$option',?,";
    $query .= $dbcon->useOracle? "SYSDATE," : "NOW(),";
	$query .= "'submitted',?)";
    $bindList = array($username, $aetitle, $uid, $details);
	if ($dbcon->preparedStmt($query, $bindList)) {
        printf(pacsone_gettext("Printing of %s: %s has been scheduled successfully"), $option, $uid);
        print "<br>\n";
        // log activity to system journal
        $dbcon->logJournal($username, "Print", $option, $uid);
	}
	else {
		$error = sprintf(pacsone_gettext("Failed to print %s: %s."), $option, $uid);
		$error .= "<br>" . sprintf(pacsone_gettext("Database Error: %s"), $dbcon->getError());
		print "<h3><font color=red>$error</font></h3>";
	}
  }
} else {
  $query = "insert into dbjob (username,aetitle,priority,type,class,uuid,submittime,status,details) ";
  $query .= "values(?,?,$priority,'Print',?,?,";
  $bindList = array($username, $aetitle, $option, $details);
  $query .= $dbcon->useOracle? "SYSDATE," : "NOW(),";
  $query .= "'submitted','";
  foreach ($entry as $uid) {
    $query .= "$uid,";
  }
  $query = substr($query, 0, strlen($query) - 1);
  $query .= "')";
  if ($dbcon->preparedStmt($query, $bindList)) {
    printf(pacsone_gettext("Printing of the following %s: has been scheduled successfully"), $option);
    print "<br><p>\n";
    foreach ($entry as $uid) {
        print "$uid<br>\n";
        // log activity to system journal
        $dbcon->logJournal($username, "Print", $option, $uid);
    }
  }
  else {
	$error = sprintf(pacsone_gettext("Failed to print %s:"), $option);
	$error .= "<br>" . sprintf(pacsone_gettext("Database Error: %s"), $dbcon->getError());
	print "<h3><font color=red>$error</font></h3>";
  }
}
print "<p><a href='status.php' title='";
print pacsone_gettext("Check Printing Job Status");
print "'>" . pacsone_gettext("Printing Status") . "<p>\n";
print "</body>\n";
print "</html>\n";

require_once 'footer.php';
?>
