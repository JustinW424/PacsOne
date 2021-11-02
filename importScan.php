<?php
//
// importScan.php
//
// Module for scanning a list of patients to be selected for import
//
// CopyRight (c) 2007-2020 RainbowFish Software
//
session_start();

require_once "locale.php";
include_once 'security.php';
include_once 'display.php';
include_once 'utils.php';

global $PRODUCT;
global $CUSTOMIZE_PATIENTS;
print "<html>";
print "<head><title>$PRODUCT - ";
printf(pacsone_gettext("Select %s to Import"), $CUSTOMIZE_PATIENTS);
print "</title></head>";
print "<body>";
require_once 'header.php';

$dbcon = new MyConnection();
$username = $dbcon->username;
$jobid = $_REQUEST["jobid"];
if (!is_numeric($jobid)) {
    print "<p><font color=red>";
    printf(pacsone_gettext("Error: Invalid Job ID: <b>[%s]</b>"), $jobid);
    print "</font>";
    exit();
}
// check if the import scan job has completed
$query = "select status,aetitle,details from dbjob where id=?";
$bindList = array($jobid);
$result = $dbcon->preparedStmt($query, $bindList);
$row = $result->fetch(PDO::FETCH_NUM);
print "<form method='POST' action='importScan.php'>\n";
print "<input type='hidden' name='jobid' value=$jobid></input>";
$status = $row[0];
$aetitle = $row[1];
$details = $row[2];
if (!strcasecmp($status, "failed")) {
    $url = "status.php";
    print "<p><b><font color=red>";
    printf(pacsone_gettext("Job: <a href=\"%s\">%d</a> failed."), $url, $jobid);
    print "</font></b>";
} else if (strcasecmp($status, "success")) {
    $url = "importScan.php?jobid=$jobid";
    print "<p>";
    printf(pacsone_gettext("Job: <a href='%s'>%d</a> is still being processed. Please check again later."), $url, $jobid);
    print "<p><input type='submit' value='" . pacsone_gettext("Check Again") . "'></input>";
} else if (isset($_POST['entry'])) {
    $entry = $_POST['entry'];
    $count = 0;
    foreach ($entry as $key) {
        // mark selected images
        $query = "update importscan set selected=1 where jobid=? and patientid=?";
        $bindList = array($jobid, $key);
        $result = $dbcon->preparedStmt($query, $bindList);
        // log activity to system journal
        $query = "select path from importscan where jobid=? and patientid=?";
        $result = $dbcon->preparedStmt($query, $bindList);
        while ($result && ($path = $result->fetchColumn())) {
            $dbcon->logJournal($username, "Import", "Image", $path);
            $count++;
        }
    }
    // submit database job to import images for selected patients
    $query = "insert into dbjob (username,aetitle,type,uuid,class,submittime,status,details) ";
    $query .= "values(?,?,'ImportFiles',?,'Image',";
    $query .= $dbcon->useOracle? "SYSDATE," : "NOW(),";
    $query .= "'submitted',?)";
    $bindList = array($username, $aetitle, $jobid, $details);
    $dbcon->preparedStmt($query, $bindList);
    $id = $dbcon->insert_id("dbjob");
    print "<p>";
    printf(pacsone_gettext("<a href=\"%s\">Database Job %d</a> have been scheduled to Import %d selected images."), "status.php", $id, $count);
    print "<p>";
} else {
    // scan job has completed, display the list of patients scanned
    $scanned = array();
    $query = "select * from importscan where jobid=?";
    $bindList = array($jobid);
    $result = $dbcon->preparedStmt($query, $bindList);
    while ($result && ($row = $result->fetch(PDO::FETCH_ASSOC))) {
        $patientId = strtoupper($row["patientid"]);
        $patientName = $row["patientname"];
        $fileSize = $row["filesize"];
        if (isset($scanned[$patientId])) {
            $scanned[$patientId]["count"]++;
            $scanned[$patientId]["total"] += $fileSize;
        } else {
            $record = array("name" => $patientName, "count" => 1, "total" => $fileSize);
            $scanned[$patientId] = $record;
        }
    }
    if (count($scanned)) {
        print "<p><table width=100% border=0 cellpadding=5>\n";
        global $BGCOLOR;
        global $CUSTOMIZE_PATIENT_ID;
        global $CUSTOMIZE_PATIENT_NAME;
        $columns = array(
            $CUSTOMIZE_PATIENT_ID,
            $CUSTOMIZE_PATIENT_NAME,
            pacsone_gettext("Number of Images"),
            pacsone_gettext("Total Size"),
        );
        print "<tr class=listhead bgcolor=$BGCOLOR><td></td>";
        foreach ($columns as $key)
            print "<td><b>$key</b></td>";
        print "</tr>";
        foreach ($scanned as $key => $record) {
            print "<tr>";
	        print "<td align=center width='1%'>\n";
	        print "<input type='checkbox' name='entry[]' value='$key'></td>\n";
            print "<td>$key</td>";
            print "<td>" . $record["name"] . "</td>";
            print "<td>" . $record["count"] . "</td>";
            $total = (int)($record["total"] / 1024);
            if ($total >= 1024)
                $total = sprintf("%d %s", (int)($total / 1024), pacsone_gettext("MBytes"));
            else
                $total .= pacsone_gettext(" KBytes");
            print "<td>$total</td>";
            print "</tr>";
        }
        print "</table>";
        include_once "checkUncheck.js";
        print "<table width=20% border=0 cellspacing=0 cellpadding=5>\n";
        print "<tr>";
        $check = pacsone_gettext("Check All");
        $uncheck = pacsone_gettext("Uncheck All");
        print "<td><input type=button value='$check' name='checkUncheck' onClick='checkAll(this.form,\"entry\", \"$check\", \"$uncheck\")'</td>\n";
        print "<td><input type='submit' value='";
        printf(pacsone_gettext("Import Selected %s"), $CUSTOMIZE_PATIENTS);
        print "'></input></td>";
        print "</tr></table>";
    } else {
        print pacsone_gettext("No patients found.");
    }
}
print "</form>";

require_once 'footer.php';
print "</body>";
print "</html>";

?>
