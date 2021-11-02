<?php
//
// removeEmptyPid.php
//
// Tool for removing duplicate Patient IDs which contain no studies (empty)
//
// CopyRight (c) 2007-2020 RainbowFish Software
//
if (!isset($_SESSION['authenticatedDatabase']))
    session_start();

include_once 'database.php';
include_once 'sharedData.php';

global $CUSTOMIZE_PATIENT_ID;
set_time_limit(0);
$dbcon = new MyConnection();
$result = $dbcon->query("select patient.origid from patient left join study on patient.origid=study.patientid where study.uuid is null");
$delete = isset($_GET['delete'])? 1 : 0;
$count = 0;
while ($result && ($pid = $result->fetchColumn())) {
    $count++;
    printf("Found empty %s: <b>%s</b><br>", $CUSTOMIZE_PATIENT_ID, $pid);
    if ($delete) {
        $xpid = $dbcon->escapeQuote($pid);
        if (!$dbcon->query("delete from patient where origid='$xpid'")) {
            print "<p><h3><font color=red>";
            printf("Error deleting %s <b>%s</b>: ", $CUSTOMIZE_PATIENT_ID, $pid) . $dbcon->getError() . "</font></h3>\n";
            exit();
        }
        printf("Deleted empty %s: <b>%s</b><br>", $CUSTOMIZE_PATIENT_ID, $pid);
    }
}
if (!$count)
    printf("<p>No empty %s found", $CUSTOMIZE_PATIENT_ID);
?>
