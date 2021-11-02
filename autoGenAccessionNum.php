<?php
if (!isset($_SESSION['authenticatedDatabase']))
    session_start();

include_once 'database.php';
include_once 'sharedData.php';

function autoGenAccessionNumber($seq)
{
    return (date("ymdHis") . sprintf(".%03.3d", $seq));
}

set_time_limit(0);
$fromDate = 0;
$toDate = 0;
if (isset($_REQUEST['fromDate']))
    $fromDate = $_REQUEST['fromDate'];
if (isset($_REQUEST['toDate']))
    $toDate = $_REQUEST['toDate'];
$dbcon = new MyConnection();
// limit the number of simultaneous resolve jobs
$dbcon->query("insert into applentity (title,maxsessions) values('_AccessionNum',1)");
$query = "select uuid,patientid,received from study";
$key = "";
$bindList = array();
if ($fromDate) {
    if ($toDate) {
        // (from, to) range
        $key = $dbcon->useOracle? "TRUNC(TO_DATE(?,'YYYYMMDD')) < TRUNC(received) AND TRUNC(received) < TRUNC(TO_DATE(?,'YYYYMMDD')" : "TO_DAYS(?) < TO_DAYS(received) AND TO_DAYS(received) < TO_DAYS(?)";
        $bindList = array($fromDate, $toDate);
    } else {
        // From $fromDate till now
        $key = $dbcon->useOracle? "TRUNC(received) > TRUNC(TO_DATE(?,'YYYYMMDD'))" : "TO_DAYS(received) > TO_DAYS(?)";
        $bindList = array($fromDate);
    }
} else if ($toDate) {
    // till $toDate
    $key = $dbcon->useOracle? "TRUNC(received) < TRUNC(TO_DATE(?,'YYYYMMDD'))" : "TO_DAYS(received) < TO_DAYS(?)";
    $bindList = array($toDate);
}
if (strlen($key))
    $query .= " where $key";
if (empty($bindList))
    $result = $dbcon->query($query);
else
    $result = $dbcon->preparedStmt($query, $bindList);
$count = 0;
while ($result && ($row = $result->fetch(PDO::FETCH_NUM))) {
    $uid = $row[0];
    $patientid = $row[1];
    $received = $row[2];
    $accessNum = autoGenAccessionNumber($count % 1000);
    // submit a job to resolve the Dicom images
    $username = $dbcon->username;
    $query = "insert into dbjob (username,type,aetitle,class,uuid,details,submittime,status) VALUES(";
    $query .= "'$username','resolve','_AccessionNum','study','$uid','$accessNum',";
    $query .= $dbcon->useOracle? "SYSDATE," : "NOW(),";
    $query .= "'submitted')";
    if ($dbcon->query($query)) {
        $dbcon->query("update study set accessionnum='$accessNum' where uuid='$uid'");
        $count++;
        global $CUSTOMIZE_PATIENT_ID;
        print "Modified Accession Number to: <u>$accessNum</u> for Study <b>$uid</b> of $CUSTOMIZE_PATIENT_ID: <b>$patientid</b> received on $received<br>";
    }
}
// remove the limit for the number of simultaneous resolve jobs
$dbcon->query("delete from applentity where title='_AccessionNum'");
print "<p><h2>Found and replaced $count studies.</h2>";
?>
