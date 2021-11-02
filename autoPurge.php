<?php
//
// autoPurge.php
//
// Module for automatically purging storage directories
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();
ob_start();

require_once 'locale.php';
include_once 'database.php';
include_once 'security.php';
include_once 'utils.php';
include_once 'sharedData.php';
include_once 'checkUncheck.js';

$dbcon = new MyConnection();
$username = $dbcon->username;

function addEntryForm()
{
    global $PRODUCT;
    print "<html>";
    print "<head><title>$PRODUCT - " . pacsone_gettext("Add Automatic Purge Rule");
    print "</title></head>";
    print "<body>";
    require_once 'header.php';
    print "<form method='POST' action='autoPurge.php'>\n";
    print "<input type='hidden' name='actionvalue'>\n";
    print "<p><table border=1 cellpadding=5 cellspacing=0>\n";
    print "<tr><td>";
    print pacsone_gettext("Purge By:");
    print "</td><td>";
    print "<input type='radio' name='purgeby' value='Capacity' checked>";
    print pacsone_gettext("Storage Capacity: ");
    print "</input>&nbsp;";
    print pacsone_gettext("Enter Low Watermark Percentage: ");
    print "<input type='text' size=3 maxlength=3 name='low' value=10>%</input>";
    print "&nbsp;&nbsp;&nbsp;";
    print pacsone_gettext("Enter High Watermark Percentage: ");
    print "<input type='text' size=3 maxlength=3 name='high' value=30>%</input><br>\n";
    print "<input type='radio' name='purgeby' value='Received'>";
    print pacsone_gettext("Study Received Date (Date When Study is Received): ");
    print "</input>&nbsp;";
    print pacsone_gettext("Purge Study Received More Than <input type='text' size=5 maxlength=5 name='aging' value='30'> Days Ago");
    print "<br><input type='radio' name='purgeby' value='Date'>";
    print pacsone_gettext("Study Date (Date When Study is Acquired): ");
    print "</input>&nbsp;";
    print pacsone_gettext("Purge Study Acquired More Than <input type='text' size=5 maxlength=5 name='agingdate' value='30'> Days Ago");
    print "<br><input type='radio' name='purgeby' value='SourceAe'>";
    print pacsone_gettext("All Studies Received From This Source AE: ");
    print "<input type='text' size=16 maxlength=16 name='sourceae'></input>";
    print "</td></tr>";
    $hour = 12;
    $checkedAM = "checked";
    $checkedPM = "";
    print "<tr><td>";
    print pacsone_gettext("Run Automatic Purging Each Day At: ");
    print "</td>\n";
    print "<td><select name='hour'>\n";
    print "<option selected>$hour</option>";
    for ($i = 1; $i <= 12; $i++)
        if ($i != $hour)
            print "<option>$i</option>";
    print "</select> <input type='radio' name='ampm' value=0 $checkedAM>";
    print pacsone_gettext("A.M. ");
    print "<input type='radio' name='ampm' value=1 $checkedPM>";
    print pacsone_gettext("P.M.");
    print "</td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Purge Operation:");
    print "</td>\n";
    print "<td><input type='radio' name='oper' value='delete' checked>";
    print pacsone_gettext("Delete Image Files");
    print "<br>\n";
    print "<input type='radio' name='oper' value='move'>";
    print pacsone_gettext("Move Image Files to Destination Folder: ");
    print "<input type='text' name='destdir' size=32 maxlength=255><br>\n";
    print pacsone_gettext("(For Windows platforms, use Windows UNC format, e.g., \"\\\\RemoteHost\\RemotePath\\\" if the destination folder specified above is a network-shared folder)");
    print "</td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Delete patient record after all studies of the patient have been purged:");
    print "</td>\n";
    print "<td><input type='radio' name='delpatient' value=1 checked>";
    print pacsone_gettext("Yes");
    print "<br><input type='radio' name='delpatient' value=0>";
    print pacsone_gettext("No");
    print "</td></tr>\n";
    print "</tr></table>";
    print "<p><input type=submit value='";
    print pacsone_gettext("Add");
    print "' name='action' title='";
    print pacsone_gettext("Add new Automatic Purging Rule");
    print "' onclick='switchText(this.form,\"actionvalue\",\"Add\")'>\n";
    print "</form>\n";
    require_once 'footer.php';
    print "</table>";
    print "</body>";
    print "</html>";
}

function modifyEntryForm($row, $seq)
{
    global $PRODUCT;
    print "<html>";
    print "<head><title>$PRODUCT - " . pacsone_gettext("Modify Automatic Purge Rule");
    print "</title></head>";
    print "<body>";
    require_once 'header.php';
    print "<form method='POST' action='autoPurge.php'>\n";
    print "<input type='hidden' name='actionvalue'>\n";
    print "<input type='hidden' name='seq' value='$seq'>\n";
    print "<p><table border=1 cellpadding=5 cellspacing=0>\n";
    $enable = $row['enable'];
    $selected = ($enable)? "checked" : "";
    $otherwise = ($enable)? "" : "checked";
    print "<tr><td>";
    print pacsone_gettext("Enable Automatic Purging? ");
    print "</td>\n";
    print "<td><input type='radio' name='enable' value=1 $selected>\n";
    print pacsone_gettext("Enabled");
    print "<br><input type='radio' name='enable' value=0 $otherwise>\n";
    print pacsone_gettext("Disabled");
    print "</td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Purge By:");
    print "</td><td>";
    $aging = $row['aging'];
    $sourceAe = isset($row['sourceae'])? $row['sourceae'] : "";
    $capacity = (!strlen($sourceAe) && $aging == 0)? "checked" : "";
    $received = ($aging > 0)? "checked" : "";
    $studydate = ($aging < 0)? "checked" : "";
    $bySourceAe = (!$aging && strlen($sourceAe))? "checked" : "";
    print "<input type='radio' name='purgeby' value='Capacity' $capacity>";
    print pacsone_gettext("Storage Capacity: ");
    print "</input>&nbsp;";
    $lowWater = $row['lowwater'];
    $highWater = $row['highwater'];
    print pacsone_gettext("Enter Low Watermark Percentage: ");
    print "<input type='text' size=3 maxlength=3 name='low' value=$lowWater>%</input>";
    print "&nbsp;&nbsp;&nbsp;";
    print pacsone_gettext("Enter High Watermark Percentage: ");
    print "<input type='text' size=3 maxlength=3 name='high' value=$highWater>%</input><br>\n";
    print "<input type='radio' name='purgeby' value='Received' $received>";
    print pacsone_gettext("Study Received Date (Date When Study is Received): ");
    print "</input>&nbsp;";
    printf(pacsone_gettext("Purge Study Received More Than <input type='text' size=5 maxlength=5 name='aging' value='%d'> Days Ago"), ($aging > 0)? $aging : 30);
    print "<br><input type='radio' name='purgeby' value='Date' $studydate>";
    print pacsone_gettext("Study Date (Date When Study is Acquired): ");
    print "</input>&nbsp;";
    printf(pacsone_gettext("Purge Study Acquired More Than <input type='text' size=5 maxlength=5 name='agingdate' value='%d'> Days Ago"), ($aging < 0)? abs($aging) : 30);
    print "<br><input type='radio' name='purgeby' value='SourceAe' $bySourceAe>";
    print pacsone_gettext("All Studies Received From This Source AE: ");
    print "<input type='text' size=16 maxlength=16 name='sourceae' value=\"$sourceAe\"></input>";
    print "</td></tr>";
    $schedule = $row['schedule'];
    $hour = $schedule % 12;
    if ($hour == 0)
        $hour = 12;
    $ampm = ($schedule >= 12)? 1 : 0;
    $checkedAM = ($ampm)? "" : "checked";
    $checkedPM = ($ampm)? "checked" : "";
    print "<tr><td>";
    print pacsone_gettext("Run Automatic Purging Each Day At: ");
    print "</td>\n";
    print "<td><select name='hour'>\n";
    print "<option selected>$hour</option>";
    for ($i = 1; $i <= 12; $i++)
        if ($i != $hour)
       	    print "<option>$i</option>";
    print "</select> <input type='radio' name='ampm' value=0 $checkedAM>";
    print pacsone_gettext("A.M. ");
    print "<input type='radio' name='ampm' value=1 $checkedPM>";
    print pacsone_gettext("P.M.");
    print "</td></tr>\n";
    $destdir = $row['destdir'];
    $checkedDel = strlen($destdir)? "" : "checked";
    $checkedMov = strlen($destdir)? "checked" : "";
    print "<tr><td>";
    print pacsone_gettext("Purge Operation:");
    print "</td>\n";
    print "<td><input type='radio' name='oper' value='delete' $checkedDel>";
    print pacsone_gettext("Delete Image Files");
    print "<br>\n";
    print "<input type='radio' name='oper' value='move' $checkedMov>";
    print pacsone_gettext("Move Image Files to Destination Folder: ");
    print "<input type='text' name='destdir' size=32 maxlength=255 value='$destdir'><br>\n";
    print pacsone_gettext("(For Windows platforms, use Windows UNC format, e.g., \"\\\\RemoteHost\\RemotePath\\\" if the destination folder specified above is a network-shared folder)");
    print "</td></tr>\n";
    $delpatient = $row['delpatient'];
    print "<tr><td>";
    print pacsone_gettext("Delete patient record after all studies of the patient have been purged:");
    print "</td>\n";
    $yes = $delpatient? "checked" : "";
    $no = $delpatient? "" : "checked";
    print "<td><input type='radio' name='delpatient' value=1 $yes>";
    print pacsone_gettext("Yes");
    print "<br><input type='radio' name='delpatient' value=0 $no>";
    print pacsone_gettext("No");
    print "</td></tr>\n";
    print "</tr></table>";
    print "<p><input type=submit value='";
    print pacsone_gettext("Modify");
    print "' name='action' title='";
    print pacsone_gettext("Modify Automatic Purging Rule");
    print "' onclick='switchText(this.form,\"actionvalue\",\"Modify\")'>\n";
    print "</form>\n";
    require_once 'footer.php';
    print "</table>";
    print "</body>";
    print "</html>";
}

if (isset($_REQUEST['actionvalue'])) {
    $action = $_REQUEST['actionvalue'];
    if (strcasecmp($action, "Add") == 0) {
        if (isset($_POST['purgeby'])) {
            $purgeby = $_POST['purgeby'];
            if (strcasecmp($purgeby, "Capacity") == 0) {
                $lowWater = $_POST['low'];
                $highWater = $_POST['high'];
                if ($highWater <= $lowWater) {
                    print "<h3><font color=red>";
                    print pacsone_gettext("Error:");
                    print "<p>" . pacsone_gettext("High Water Mark must be greater than Low Water Mark");
                    print "</font></h3>";
                    exit();
                }
            }
	        $schedule = hour2schedule($_POST['hour'], $_POST['ampm']);
            $destdir = cleanPostPath($_POST['destdir']);
            $sourceAe = "";
            $columns = array("directory", "enable");
            $bindList = array("_all", 1);
            if (strcasecmp($purgeby, "Capacity") == 0) {
                $columns[] = "lowwater";
                $bindList[] = $lowWater;
                $columns[] = "highwater";
                $bindList[] = $highWater;
                $columns[] = "aging";
                $bindList[] = 0;
            } else {
                if (isset($_POST['sourceae']))
                    $sourceAe = $_POST['sourceae'];
                if (strcasecmp($purgeby, "Received") == 0) {
                    $aging = $_POST['aging'];
                    $columns[] = "aging";
                    $bindList[] = $aging;
                } else if (strcasecmp($purgeby, "Date") == 0) {
                    $aging = $_POST['agingdate'] * (-1);
                    $columns[] = "aging";
                    $bindList[] = $aging;
                } else if (strcasecmp($purgeby, "SourceAe")) {
                    print "<h3><font color=red>";
                    print "<p>" . printf(pacsone_gettext("Invalid Automatic Purge Option: %s"), $purgeby);
                    print "</font></h3>";
                    exit();
                }
            }
            $columns[] = "sourceae";
            $bindList[] = strlen($sourceAe)? $sourceAe : null;
            $columns[] = "delpatient";
            $bindList[] = $_POST['delpatient'];
            $columns[] = "schedule";
            $bindList[] = $schedule;
            $columns[] = "destdir";
            if (strcasecmp($_POST['oper'], "delete")) {
                // move image files to a destination folder
                $bindList[] = strlen($destdir)? $destdir : null;
            } else {
                // delete image files
                $bindList[] = null;
            }
            $entry = "insert into autopurge ";
            for ($i = 0; $i < count($columns); $i++) {
                $entry .= $i? "," : "(";
                $entry .= $columns[$i];     
            }
            $entry .= ") values";
            for ($i = 0; $i < count($bindList); $i++) {
                $entry .= $i? "," : "(";
                $entry .= "?";
            }
            $entry .= ")";
            if (!$dbcon->preparedStmt($entry, $bindList)) {
                print "<h3><font color=red>";
                printf(pacsone_gettext("Error running query: [%s]"), $entry);
                print ": " . $dbcon->getError() . "</font></h3>";
                exit();
            }
        } else {
            addEntryForm();
            exit();
        }
    } else if (strcasecmp($action, "Edit") == 0) {
        $key = $_GET['key'];
        $query = "select * from autopurge where seq=?";
        $bindList = array($key);
        $result = $dbcon->preparedStmt($query, $bindList);
        if ($result && $result->rowCount()) {
            $row = $result->fetch(PDO::FETCH_ASSOC);
            modifyEntryForm($row, $key);
        } else {
            print "<h3><font color=red>";
            printf(pacsone_gettext("Error: No Automatic Purging Rule found for: %d"), $key);
            print "</font></h3>";
        }
        exit();
    } else if (strcasecmp($action, "Delete") == 0) {
        $entry = $_POST['entry'];
        foreach ($entry as $key) {
            $query = "delete from autopurge where seq=?";
            $bindList = array($key);
            if (!$dbcon->preparedStmt($query, $bindList)) {
                print "<h3><font color=red>";
                printf(pacsone_gettext("Error running query [%s]:"), $query);
                print "<p>" . $dbcon->getError();
                print "</font></h3>";
                exit();
            }
        }
    } else if (strcasecmp($action, "Modify") == 0) {
        $key = $_POST['seq'];
        $purgeby = $_POST['purgeby'];
        if (strcasecmp($purgeby, "Capacity") == 0) {
            $lowWater = $_POST['low'];
            $highWater = $_POST['high'];
            if ($highWater <= $lowWater) {
                print "<h3><font color=red>";
                print pacsone_gettext("Error:");
                print "<p>" . pacsone_gettext("High Water Mark must be greater than Low Water Mark");
                print "</font></h3>";
                exit();
            }
        }
	    $schedule = hour2schedule($_POST['hour'], $_POST['ampm']);
        $destdir = cleanPostPath($_POST['destdir']);
        $sourceAe = "";
        $entry = "update autopurge set enable=?,";
        $bindList = array($_POST['enable']);
        if (strcasecmp($purgeby, "Capacity") == 0) {
            $entry .= "lowwater=?,highwater=?,aging=0,";
            $array_push($bindList, $lowWater, $highWater); 
        } else {
            if (isset($_POST['sourceae']))
                $sourceAe = $_POST['sourceae'];
            if (strcasecmp($purgeby, "Received") == 0) {
                $entry .= "aging=?,";
                $bindList[] = $_POST['aging'];
            } else if (strcasecmp($purgeby, "Date") == 0) {
                $aging = $_POST['agingdate'] * (-1);
                $entry .= "aging=$aging,";
            } else if (strcasecmp($purgeby, "SourceAe") == 0) {
                $entry .= "aging=0,";
            } else {
                print "<h3><font color=red>";
                print "<p>" . printf(pacsone_gettext("Invalid Automatic Purge Option: %s"), $purgeby);
                print "</font></h3>";
                exit();
            }
        }
        if (!strlen($sourceAe))
            $entry .= "sourceae=NULL,";
        else {
            $entry .= "sourceae=?,";
            $bindList[] = $sourceAe;
        }
        $entry .= "delpatient=?,";
        $bindList[] = $_POST['delpatient'];
        $entry .= "schedule=$schedule,destdir=";
        if (strcasecmp($_POST['oper'], "delete") && file_exists($destdir)) {
            // move image files to a destination folder
            $entry .= "?";
            $bindList[] = $destdir;
        } else {
            // delete image files
            $entry .= "NULL";
        }
        $entry .= " where seq=?";
        $bindList[] = $key;
        if (!$dbcon->preparedStmt($entry, $bindList)) {
            print "<h3><font color=red>";
            printf(pacsone_gettext("Error running query: [%s]"), $entry);
            print ": " . $dbcon->getError() . "</font></h3>";
            exit();
        }
    } else if (strcasecmp($action, "Toggle") == 0) {
        $entry = "update autopurge set enable=? where seq=?";
        $bindList = array($_GET['enable'], $_GET['key']);
        if (!$dbcon->preparedStmt($entry, $bindList)) {
            print "<h3><font color=red>";
            printf(pacsone_gettext("Error running query: [%s]"), $entry);
            print ": " . $dbcon->getError() . "</font></h3>";
            exit();
        }
    }
    // log activity to system journal
    $dbcon->logJournal($username, $action, "Automatic Purging", "");
}
// back to the Tools page
$url = "tools.php?page=" . urlencode(pacsone_gettext("Automatic Purge Storage Directories"));
header("Location: $url");
ob_end_clean();
exit();

?>
