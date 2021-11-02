<?php
//
// moveForm.php
//
// Main form for selecting destination AE for sending C-MOVE requests as a SCU
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
if (!session_id())
    session_start();

require_once 'locale.php';
include_once 'sharedData.php';

global $PRODUCT;
$level = $_POST['level'];
$entry = $_POST['entry'];
$source = $_POST['source'];
$patients = array();
if (isset($_POST['patientids'])) {
    $values = array_values($_POST['patientids']);
    foreach ($values as $value) {
        $tokens = explode("=", urldecode($value));
        if (count($tokens) == 2)
            $patients[$tokens[0]] = $tokens[1];
    }
}
$studies = array();
if (isset($_POST['studyuids'])) {
    $values = array_values($_POST['studyuids']);
    foreach ($values as $value) {
        $tokens = explode("=", urldecode($value));
        if (count($tokens) == 2)
            $studies[$tokens[0]] = $tokens[1];
    }
}
$series = array();
if (isset($_POST['seriesuids'])) {
    $values = array_values($_POST['seriesuids']);
    foreach ($values as $value) {
        $tokens = explode("=", urldecode($value));
        if (count($tokens) == 2)
            $series[$tokens[0]] = $tokens[1];
    }
}
// display Move Destination form
print "<html>\n";
print "<head><title>$PRODUCT - ";
printf(pacsone_gettext("Move From Remote AE %s"), $source);
print "</title></head>\n";
print "<body>\n";
require_once 'header.php';
$dbcon = new MyConnection();
$result = $dbcon->query("select title from applentity where port is not NULL");
if ($result->rowCount() == 0) {
	print "<h3><font color=red>";
    print pacsone_gettext("There is no valid AE to move to.");
    print "</font></h3>";
}
else {
	$count = count($entry);
    print "<form method='POST' action='cmove.php'>\n";
	print "<input type='hidden' name='source' value=$source></input>";
	print "<table width=100% border=1 cellpadding=1>\n";
	print "<P>";
    if ($count > 1)
        printf(pacsone_gettext("Move the following items FROM remote AE: <b>%s</b>"), $source);
    else
        printf(pacsone_gettext("Move the following item FROM remote AE: <b>%s</b>"), $source);
    print "</P>\n";
	for ($i = 0; $i < $count; $i++) {
		$type = $level{$i};
		$xid = $entry{$i};
		$uid = urldecode($xid);
		print "<tr><td>$type</td><td>$xid</td></tr>\n";
		print "<input type=hidden name='level[]' value=$type></input>\n";
		print "<input type=hidden name='entry[]' value=\"$xid\"></input>\n";
        if (count($patients)) {
            $value = http_build_query(array($uid => $patients[$uid]));
            print "<input type='hidden' name='patientids[]' value=\"$value\"></input>\n";
        }
        if (count($studies)) {
            $value = http_build_query(array($uid => $studies[$uid]));
            print "<input type='hidden' name='studyuids[]' value=\"$value\"></input>\n";
        }
        if (count($series)) {
            $value = http_build_query(array($uid => $series[$uid]));
            print "<input type='hidden' name='seriesuids[]' value=\"$value\"></input>\n";
        }
	}
    print "</table>\n";
	print "<P>";
    print pacsone_gettext("Please enter the Destination Application Entity to move TO: ");
    $value = isset($_SESSION['aetitle'])? $_SESSION['aetitle'] : "";
	print "<input type=textbox size=16 maxlength=16 name='dest' value='$value'></input>";
    print "<p><input type='submit' value='";
    print pacsone_gettext("Move");
    print "'></input>\n";
    print "</form>\n";
}
require_once 'footer.php';
print "</body>\n";
print "</html>\n";

?>
