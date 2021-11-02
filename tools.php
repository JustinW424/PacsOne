<?php
//
// tools.php
//
// Module for miscellaneous tools
//
// CopyRight (c) 2003-2018 RainbowFish Software, Inc.
//
session_start();

require_once 'locale.php';
include_once 'security.php';
include_once 'display.php';
include_once 'tabbedpage.php';
include_once 'utils.php';
require_once 'duplicates.php';
require_once 'matchorm.php';
require_once 'logfile.php';
require_once 'xferSyntax.php';

class CoercionPage extends TabbedPage {
	var $title;
	var $url;
	var $dbcon;

    function __construct(&$dbcon) {
        $this->title = pacsone_gettext("Data Element Coercion");
        $this->url = "tools.php?page=" . urlencode($this->title);
        $this->dbcon = $dbcon;
    }
    function __destruct() { }
    function showHtml() {
        $result = $this->dbcon->query("SELECT * FROM coercion");
        $num_rows = $result->rowCount();
        if ($num_rows > 1)
            $preface = sprintf(pacsone_gettext("There are %d Data Element Coercion rules defined:"), $num_rows);
        else
            $preface = sprintf(pacsone_gettext("There is %d Data Element Coercion rule defined:"), $num_rows);
        displayCoercion($result, $preface);
    }
}

class ExportPage extends TabbedPage {
	var $title;
	var $url;
	var $directory;

    function __construct($dir) {
        $this->title = pacsone_gettext("Export");
        $this->url = "tools.php?page=" . urlencode($this->title);
        $this->directory = $dir;
    }
    function __destruct() { }
    function showHtml() {
        $exportdir = $this->directory;
        print "<form method=POST action='exportStudy.php'><p>\n";
        displayExportForm("Study", $exportdir);
        print "<br><input type=submit value='";
        print pacsone_gettext("Export Studies");
        print "' title='";
        print pacsone_gettext("Export Selected Studies");
        print "'></input><br>";
        print "</form>\n";
    }
}

class ImportPage extends TabbedPage {
	var $title;
	var $url;
	var $directory;
	var $drive;
	var $dest;

    function __construct($dir, $drive, $dest) {
        $this->title = pacsone_gettext("Import");
        $this->url = "tools.php?page=" . urlencode($this->title);
        $this->directory = $dir;
        $this->drive = $drive;
        $this->dest = $dest;
    }
    function __destruct() { }
    function showHtml() {
        $importdir = $this->directory;
        print "<form method=POST action='importStudy.php'><p>\n";
        $checked = strlen($this->dest)? "" : "checked";
        print "<input type=radio name='type' value=0 $checked>";
        print pacsone_gettext("Import studies from a DICOM Part 10 formatted local directory:");
        print " </input>";
        print "<input type=text name='directory' size=64 maxlength=256 value='$importdir'></input><br>";
        $checked = strlen($this->dest)? "checked" : "";
        print "<input type=radio name='type' value=1 $checked>";
        print pacsone_gettext("Import studies from Removable Media drive:") . " </input>";
        if (stristr(getenv("OS"), "Windows")) {
            $value = "E:\\";
            $size = 8;
        } else {
            $value = "/media/cdrom/";
            $size = 16;
        }
        $value = strlen($this->drive)? $this->drive : $value;
        print "<input type=text name='drive' size=$size maxlength=64 value='$value'></input>";
        print pacsone_gettext(" to destination archive directory:") . "<br>";
        $value = strlen($this->dest)? $this->dest : "";
        print "<input type=text name='destdir' size=64 maxlength=256 value='$value'></input><br>";
        print "<p>";
        print "<input type=radio name='all' value=1 checked>";
        global $CUSTOMIZE_PATIENTS;
        printf(pacsone_gettext("Import all %s found"), $CUSTOMIZE_PATIENTS);
        print "</input><br>";
        print "<input type=radio name='all' value=0>";
        printf(pacsone_gettext("Select a list of %s to import"), $CUSTOMIZE_PATIENTS);
        print "</input><br>";
        print "<br><input type=submit value='";
        print pacsone_gettext("Import Studies");
        print "' title='";
        print pacsone_gettext("Import External Studies");
        print "'></input><br>";
        print "</form>\n";
    }
}

class StatisticsPage extends TabbedPage {
	var $title;
	var $url;
	var $dbcon;

    function __construct(&$dbcon) {
        $this->title = pacsone_gettext("Statistics Reports");
        $this->url = "tools.php?page=" . urlencode($this->title);
        $this->dbcon = $dbcon;
    }
    function __destruct() { }
    function showHtml() {
        print "<form method=POST action='report.php'>\n";
        print "<input type=radio name='type' value=0 checked>";
        print pacsone_gettext("Report on studies received yesterday") . "</input><br>";
        print "<input type=radio name='type' value=1>";
        print pacsone_gettext("Report on studies received this week") . "</input><br>";
        print "<input type=radio name='type' value=2>";
        print pacsone_gettext("Report on studies received this month") . "</input><br>";
        print "<input type=radio name='type' value=3>";
        print pacsone_gettext("Report on studies received this year") . "</input><br>";
        print "<input type=radio name='type' value=4>";
        print pacsone_gettext("Report on studies received from:") . " </input>";
        $euro = $this->dbcon->isEuropeanDateFormat();
        $pattern = ($euro)? pacsone_gettext("DD-MM-YYYY") : pacsone_gettext("YYYY-MM-DD");
        print "<input type=text name='from'> ($pattern)</input>";
        print "<input type=text name='to'> ($pattern)</input><br>";
        print "<input type=radio name='type' value=5>";
        print pacsone_gettext("Report on studies received from this source AE:") . "</input>";
        print " <input type=text name='sourceae'><br>";
        print "<input type=radio name='type' value=6>";
        $url = "applentity.php";
        printf(pacsone_gettext("Report on studies received from each source AE defined in <a href=\"%s\">Dicom AE</a> page"), $url) . "</input><br>";
        print "<br><input type=radio name='type' value=7>";
        print pacsone_gettext("Report on studies received with this Institution Name: ");
        print "<input type='text' name='institution' size=16 maxlength=64>";
        // report on the web user who reviewed the study
        $root = $this->dbcon->getAdminUsername();
        $result = $this->dbcon->query("select * from privilege where username!='$root'");
        if ($result && $result->rowCount()) {
            print "<br><input type=radio name='type' value=8>";
            print pacsone_gettext("Report on studies reviewed by this web user: ");
            print "<select name='reviewer'>";
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $user = $row['username'];
                $first = $row['firstname'];
                $last = $row['lastname'];
                $option = strcmp($first, "_GROUP")? "$user - $last, $first" : "$user - $last";
                print "<option>$option</option>";
            }
            print "</select>";
        }
        print "<p><input type=submit value='";
        print pacsone_gettext("Get Report");
        print "' title='";
        print pacsone_gettext("Get Statistics Report");
        print "'></input><br>";
        print "</form>\n";
    }
}

class AutoPurgePage extends TabbedPage {
	var $title;
	var $url;
	var $dbcon;

    function __construct(&$dbcon) {
        $this->title = pacsone_gettext("Automatic Purge Storage Directories");
        $this->url = "tools.php?page=" . urlencode($this->title);
        $this->dbcon = $dbcon;
    }
    function __destruct() { }
    function showHtml() {
        global $PRODUCT;
        $preface = pacsone_gettext("If automatic purging is <b><u>Enabled</u></b>, for each defined archive directory:");
        $preface .= "<br><ul><li>";
        $preface .= sprintf(pacsone_gettext("If purging by <b>Storage Capacity</b> option is selected, then once the disk free-space percentage drops below the defined <b><u>Low Water Mark</u></b>, %s will start purging older studies stored in the archive directory, until the disk free-space percentage rises above the defined <b><u>High Water Mark</u></b>.  %s will perform the above purging according to the specified 24-hour <b><u>Schedule</u></b>.<P>"), $PRODUCT, $PRODUCT);
        $preface .= "</li><li>";
        $preface .= sprintf(pacsone_gettext("If purging by <b>Study Received Date</b> option is selected, then %s will purge older studies stored in the archive directory which were <b>Received</b> before the user-specified date.  %s will perform the above purging according to the specified 24-hour <b><u>Schedule</u></b>.<P>"), $PRODUCT, $PRODUCT);
        $preface .= "</li><li>";
        $preface .= sprintf(pacsone_gettext("If purging by <b>Study Date</b> option is selected, then %s will purge older studies stored in the archive directory which were <b>Acquired</b> before the user-specified date.  %s will perform the above purging according to the specified 24-hour <b><u>Schedule</u></b>.<P>"), $PRODUCT, $PRODUCT);
        $preface .= "</li><li>";
        $preface .= sprintf(pacsone_gettext("If purging by <b>Source AE Title</b> option is selected, then %s will purge all studies stored in the archive directory that were received from the user-specified <b>Source AE Title</b>.  %s will perform the above purging according to the specified 24-hour <b><u>Schedule</u></b>.<P>"), $PRODUCT, $PRODUCT);
        $preface .= "</li></ul>";
        $query = "select * from autopurge where tag=0";
        $result = $this->dbcon->query($query);
        displayAutoPurgeSettings($result, $preface);
        // automatic purging by data element filters
        $result = $this->dbcon->query("select * from autopurge where tag!=0");
        $num_rows = $result->rowCount();
        if ($num_rows > 1)
            $preface = sprintf(pacsone_gettext("There are %d Automatic Purging Filters defined:"), $num_rows);
        else
            $preface = sprintf(pacsone_gettext("There is %d Automatic Purging Filter defined:"), $num_rows);
        displayAutoPurgeFilters($result, $preface);
    }
}

class UploadImagePage extends TabbedPage {
	var $title;
	var $url;

    function __construct() {
        $this->title = pacsone_gettext("Upload Dicom Image");
        $this->url = "tools.php?page=" . urlencode($this->title);
    }
    function __destruct() { }
    function showHtml() {
        global $PRODUCT;
        global $BGCOLOR;
        require_once "checkUncheck.js";
        print "<form method='POST' action='uploadImage.php' enctype='multipart/form-data'>\n";
        print "<input type='hidden' name='actionvalue'>\n";
        print "<p><table border=0 cellpadding=0 cellspacing=5>\n";
        print "<tr><td width='20%'>";
        printf(pacsone_gettext("Upload client Dicom Part-10 formatted raw images to %s"), $PRODUCT);
        print "</td>";
        print "</td>";
        print "<td width=1 bgcolor=$BGCOLOR><img src=blank.gif width=1 height=1></td>";
        print "<td>";
        print "<td width='80%'><input type=file name='uploadfile' size=64 maxlength=255>\n";
        print "<p><input type=submit name='action' value='";
        print pacsone_gettext("Attach");
        print "' onclick='switchText(this.form,\"actionvalue\",\"Attach\")'>";
        // display any uploaded images here
        if (isset($_SESSION['uploadimages'])) {
            $uploadimages = $_SESSION['uploadimages'];
            if (count($uploadimages)) {
                print "<br>";
                foreach ($uploadimages as $att) {
                    $value = $att['name'];
                    print "<input type=checkbox name='unattach[]' value='$value'>";
                    print "<img src='attachment.gif' border=0>";
                    print "<b>" . $att['name'] . "</b> (" . $att['size'] . " bytes)<br>\n";
                }
                print "<br><input type=submit name='action' value='";
                print pacsone_gettext("Unattach");
                print "' onclick='switchText(this.form,\"actionvalue\",\"Unattach\")'>\n";
            }
        }
        print "</input></td></tr>\n";
        print "</table>\n";
        print "<p><input type=submit name='action' value='";
        print pacsone_gettext("Upload");
        print "' onclick='switchText(this.form,\"actionvalue\",\"Upload\")'>";
        print "</form>\n";
    }
}

class IntegrityCheckPage extends TabbedPage {
	var $title;
	var $url;

    function __construct() {
        $this->title = pacsone_gettext("Database Integrity Check");
        $this->url = "tools.php?page=" . urlencode($this->title);
    }
    function __destruct() { }
    function showHtml() {
        global $PRODUCT;
        require_once "checkUncheck.js";
        print "<form method='POST' action='integrityCheck.php' enctype='multipart/form-data'>\n";
        print "<input type='hidden' name='actionvalue'>\n";
        print "<p>";
        printf(pacsone_gettext("This tool will check the <b>Image</b> table of the %s database,"), $PRODUCT);
        print pacsone_gettext(" verify that all raw Dicom images do exist and are not empty files under the archive directories.");
        print "<p><input type='radio' name='entirefile' value=0 checked>";
        print pacsone_gettext("Check only the Dicom Part-10 File Header (consumes less time and resources)");
        print "<br><input type='radio' name='entirefile' value=1>";
        print pacsone_gettext("Check the entire raw Dicom image file (consumes more time and resources)");
        print "<p><input type='checkbox' name='parallel'>&nbsp;";
        print pacsone_gettext("Run integrity check using <input type='text' name='threads' size=2 maxlength=2 value='5'> threads simultaneously");
        print pacsone_gettext(" (This will make the integrity check run faster but will require more memory resource)");
        print "<p><input type=submit name='action' value='";
        print pacsone_gettext("Integrity Check");
        print "' onclick='switchText(this.form,\"actionvalue\",\"Integrity Check\")'>";
        print "</form>\n";
    }
}

class ImportWorklistPage extends TabbedPage {
	var $title;
	var $url;

    function __construct() {
        $this->title = pacsone_gettext("Import Worklist");
        $this->url = "tools.php?page=" . urlencode($this->title);
    }
    function __destruct() { }
    function showHtml() {
        global $PRODUCT;
        global $BGCOLOR;
        require_once "checkUncheck.js";
        print "<form method='POST' action='importWorklist.php' enctype='multipart/form-data'>\n";
        print "<input type='hidden' name='actionvalue'>\n";
        print "<p><table border=0 cellpadding=0 cellspacing=5>\n";
        print "<tr><td width='20%'>";
        printf(pacsone_gettext("Upload Modality Worklist from Text File(s) to %s"), $PRODUCT);
        print "</td>";
        print "</td>";
        print "<td width=1 bgcolor=$BGCOLOR><img src=blank.gif width=1 height=1></td>";
        print "<td>";
        print "<td width='80%'><input type=file name='uploadfile' size=64 maxlength=255>\n";
        print "<p><input type=submit name='action' value='";
        print pacsone_gettext("Import Worklist");
        print "' onclick='switchText(this.form,\"actionvalue\",\"Import Worklist\")'>";
        print "</input></td></tr>\n";
        print "</table>\n";
        print "</form>\n";
    }
}

class LiveMonitorPage extends TabbedPage {
	var $title;
	var $url;
	var $dbcon;

    function __construct(&$dbcon) {
        $this->title = pacsone_gettext("Live Monitor");
        $this->url = "tools.php?page=" . urlencode($this->title);
        $this->dbcon = $dbcon;
    }
    function __destruct() { }
    function showHtml() {
        $result = $this->dbcon->query("SELECT * FROM monitor where finishtime is NULL");
        $num_rows = $result->rowCount();
        if ($num_rows) {
            $preface = sprintf(pacsone_gettext("There are currently %d live connections:"), $num_rows);
            displayLiveMonitor($result, $preface);
        } else {
            print "<br>";
            print pacsone_gettext("There is currently no live connection.");
        }
    }
}

class PatientReconciliationPage extends TabbedPage {
	var $title;
	var $url;
	var $dbcon;

    function __construct(&$dbcon) {
        global $CUSTOMIZE_PATIENT;
        $this->title = sprintf(pacsone_gettext("%s Reconciliation"), $CUSTOMIZE_PATIENT);
        $this->url = "tools.php?page=" . urlencode($this->title);
        $this->dbcon = $dbcon;
    }
    function __destruct() { }
    function showHtml() {
        global $BGCOLOR;
        global $CUSTOMIZE_PATIENT_ID;
        global $CUSTOMIZE_PATIENT_NAME;
        global $CUSTOMIZE_PATIENT_DOB;
        require_once "checkUncheck.js";
        print "<form method='POST' action='patientReconciliation.php' enctype='multipart/form-data'>\n";
        print "<input type='hidden' name='actionvalue'>\n";
        print "<p><table border=0 width=100% cellpadding=0 cellspacing=2>\n";
        $columns = array(
            pacsone_gettext("When")                                             => "timestamp",
            $CUSTOMIZE_PATIENT_ID                                               => "patientid",
            $CUSTOMIZE_PATIENT_DOB                                              => "",
            pacsone_gettext("Study ID")                                         => "",
            sprintf(pacsone_gettext("Original %s"), $CUSTOMIZE_PATIENT_NAME)    => "original",
            sprintf(pacsone_gettext("Modified %s"), $CUSTOMIZE_PATIENT_NAME)    => "modified",
        );
        print "<tr class=listhead bgcolor=$BGCOLOR>\n";
        print "<td></td>";
        foreach ($columns as $key => $field) {
            print "<td><b>$key</b></td>";
        }
        print "</tr>";
        $result = $this->dbcon->query("select * from matchworklist order by timestamp");
        $count = 0;
        while ($result && ($row = $result->fetch(PDO::FETCH_ASSOC))) {
            $uid = $row["studyuid"];
            $pid = $row["patientid"];
            print "<tr>";
			print "<td><input type='checkbox' name='entry[]' value='$uid'</td>\n";
            foreach ($columns as $key => $field) {
                if (strcasecmp($key, pacsone_gettext("Study ID")) == 0) {
                    $value = $this->dbcon->getStudyId($uid);
                } else if (strcasecmp($key, $CUSTOMIZE_PATIENT_DOB) == 0) {
                    $value = $this->dbcon->getDob($pid);
                } else {
                    $value = $row[$field];
                }
                if (strlen($value) == 0)
                    $value = pacsone_gettext("N/A");
                print "<td>$value</td>";
            }
            print "</tr>";
            $count++;
        }
        print "</table>\n";
        if ($count) {
    	    print "<p><table width=10% border=0 cellpadding=5>\n";
            print "<tr>";
            $check = pacsone_gettext("Check All");
            $uncheck = pacsone_gettext("Uncheck All");
            print "<td><input type=button value='$check' onClick='this.value=checkAll(this.form,\"entry\", \"$check\", \"$uncheck\")'</td>\n";
            print "<td><input type=submit name='action' value='";
            print pacsone_gettext("Delete");
            print "' onclick='switchText(this.form,\"actionvalue\",\"Delete\");return confirm(\"";
            print pacsone_gettext("Are you sure?");
            print "\");'></td>";
            print "</tr></table>";
        }
        print "</form>\n";
    }
}

class AnonymizePage extends TabbedPage {
	var $title;
	var $url;
	var $dbcon;

    function __construct(&$dbcon) {
        $this->title = pacsone_gettext("Anonymization Templates");
        $this->url = "tools.php?page=" . urlencode($this->title);
        $this->dbcon = $dbcon;
    }
    function __destruct() { }
    function showHtml() {
        $result = $this->dbcon->query("SELECT DISTINCT templname FROM anonymity");
        $num_rows = $result? $result->rowCount() : 0;
        if ($num_rows > 1)
            $preface = sprintf(pacsone_gettext("There are %d anonymization template defined:"), $num_rows);
        else
            $preface = sprintf(pacsone_gettext("There is %d anonymization template defined:"), $num_rows);
        displayAnonymization($result, $preface);
    }
}

class XscriptPage extends TabbedPage {
	var $title;
	var $url;
	var $dbcon;

    function __construct(&$dbcon) {
        $this->title = pacsone_gettext("Transcription Templates");
        $this->url = "tools.php?page=" . urlencode($this->title);
        $this->dbcon = $dbcon;
    }
    function __destruct() { }
    function showHtml() {
        $result = $this->dbcon->query("SELECT DISTINCT name FROM xscriptemplate");
        $num_rows = $result? $result->rowCount() : 0;
        if ($num_rows > 1)
            $preface = sprintf(pacsone_gettext("There are %d transcription templates defined:"), $num_rows);
        else
            $preface = sprintf(pacsone_gettext("There is %d transcription template defined:"), $num_rows);
        displayTranscription($result, $preface);
    }
}

class RestartPage extends TabbedPage {
	var $title;
	var $url;
	var $dbcon;

    function __construct(&$dbcon) {
        $this->title = pacsone_gettext("Restart Service");
        $this->url = "tools.php?page=" . urlencode($this->title);
        $this->dbcon = $dbcon;
    }
    function __destruct() { }
    function showHtml() {
        $result = $this->dbcon->query("SELECT * FROM monitor where finishtime is NULL");
        $num_rows = $result->rowCount();
        if ($num_rows) {
            $preface = sprintf(pacsone_gettext("There are currently %d live connections:"), $num_rows);
            displayLiveMonitor($result, $preface);
        } else {
            if (isHL7OptionInstalled())
                $preface = pacsone_gettext("Restart Dicom Server and HL7 Interface");
            else
                $preface = pacsone_gettext("Restart Dicom Server");
            displayRestartButton($preface);
        }
    }
}

class CompressDatabasePage extends TabbedPage {
	var $title;
	var $url;

    function __construct() {
        $this->title = pacsone_gettext("Compress Entire Database");
        $this->url = "tools.php?page=" . urlencode($this->title);
    }
    function __destruct() { }
    function showHtml() {
        global $PRODUCT;
        require_once "checkUncheck.js";
        print "<form method='POST' action='compressDatabase.php' enctype='multipart/form-data'>\n";
        print "<input type='hidden' name='actionvalue'>\n";
        print "<p>";
        printf(pacsone_gettext("This tool will compress all images (with valid pixel data) stored in the %s database"), $PRODUCT);
        print pacsone_gettext(" using one of the Dicom lossless transfer syntaxes below:");
        global $LOSSLESS_SYNTAX_TBL;
        print "<p><select name='xfersyntax'>";
        foreach ($LOSSLESS_SYNTAX_TBL as $uid => $entry) {
            $desc = $uid . " - " . $entry[2];
            print "<option>$desc</option>";
        }
        print "</select>";
        print "<p><b>";
        print pacsone_gettext("Note: since the lossless compression will be applied to all Dicom images stored in the database, it may take quite some time as well as CPU/memory resources for the compression to be completed depending on the size of the existing database. ");
        print pacsone_gettext("So please run this tool only during <u>off-hours</u>, e.g., night or weekends, etc, in order to have minimum impact on the normal workflows during the regular business hours.");
        print "</b>";
        print "<p><input type=submit name='action' value='";
        print pacsone_gettext("Compress Database");
        print "' onclick='switchText(this.form,\"actionvalue\",\"Compress Database\")'>";
        print "</form>\n";
    }
}

class StorageFormatPage extends TabbedPage {
	var $title;
	var $url;

    function __construct() {
        $this->title = pacsone_gettext("Change Storage Format");
        $this->url = "tools.php?page=" . urlencode($this->title);
    }
    function __destruct() { }
    function showHtml() {
        global $PRODUCT;
        require_once "checkUncheck.js";
        print "<form method='POST' action='storageFormat.php' enctype='multipart/form-data'>\n";
        print "<input type='hidden' name='actionvalue'>\n";
        print "<p>";
        printf(pacsone_gettext("This tool will modify all images stored in the %s database"), $PRODUCT);
        print pacsone_gettext(" and add the Dicom Part-10 Meta-Information Header");
        print "<p><select name='format'>";
        // only support standard Dicom Part-10 format
        $formats = array(
            "DicomPart10"   => "Standard Dicom Part-10 Format",
        );
        foreach ($formats as $format => $desc) {
            $entry = $format . " - " . $desc;
            print "<option>$entry</option>";
        }
        print "</select>";
        print "<p><b>";
        print pacsone_gettext("Note: since the change of storage format will be applied to all Dicom images stored in the database, it may take quite some time as well as CPU/memory resources for the compression to be completed depending on the size of the existing database. ");
        print pacsone_gettext("So please run this tool only during <u>off-hours</u>, e.g., night or weekends, etc, in order to have minimum impact on the normal workflows during the regular business hours.");
        print "</b>";
        print "<p><input type=submit name='action' value='";
        print pacsone_gettext("Change Storage Format");
        print "' onclick='switchText(this.form,\"actionvalue\",\"Change Storage Format\")'>";
        print "</form>\n";
    }
}

global $PRODUCT;

$dbcon = new MyConnection();
$username = $dbcon->username;
$directory = dirname($_SERVER['SCRIPT_FILENAME']);
$directory = dirname($directory);
$pages = array();
// default page
if ($dbcon->hasaccess("admin", $username)) {
    $pages[] = new IntegrityCheckPage($dbcon);
    if (stristr(getenv("OS"), "Windows"))
        $pages[] = new XscriptPage($dbcon);
    $pages[] = new RestartPage($dbcon);
    $pages[] = new CompressDatabasePage($dbcon);
    $ini = parseIniByAeTitle($_SESSION['aetitle']);
    if (isset($ini['StorageFormat']) && strcasecmp($ini['StorageFormat'], "DicomPart10"))
        $pages[] = new StorageFormatPage($dbcon);
}
// pages that require privileges
if ($dbcon->hasaccess("viewprivate", $username)) {
    $page = new StatisticsPage($dbcon);
    // default to Statistics page if 'View' privilege is enabled
    $current = $page->title;
    $pages[] = $page;
}
if ($dbcon->hasaccess("export", $username)) {
    // check if user has defined a preferred Export dir
    $preferred = "";
    $result = $dbcon->query("select exportdir from privilege where username='$username'");
    if ($result && $result->rowCount()) {
        $dir = $result->fetchColumn();
        if (strlen($dir) && file_exists($dir))
            $preferred = $dir;
    }
    $folder = strlen($preferred)? $preferred : ($directory . "/export/");
    $pages[] = new ExportPage($folder);
}
if ($dbcon->hasaccess("import", $username)) {
    // check if user has defined a preferred Import dir
    $preferred = "";
    $drive = "";
    $dest = "";
    $result = $dbcon->query("select importdir,importdrive,importdest from privilege where username='$username'");
    if ($result && $result->rowCount()) {
        $row = $result->fetch(PDO::FETCH_NUM);
        if (strlen($row[0]) && file_exists($row[0]))
            $preferred = $row[0];
        if (strlen($row[1]) && file_exists($row[1]))
            $drive = $row[1];
        if (strlen($row[2]) && file_exists($row[2]))
            $dest = $row[2];
    }
    $folder = strlen($preferred)? $preferred : ($directory . "/import/");
    $pages[] = new ImportPage($folder, $drive, $dest);
}
if ($dbcon->hasaccess("modifydata", $username)) {
    $pages[] = new CoercionPage($dbcon);
    $pages[] = new AutoPurgePage($dbcon);
    $pages[] = new DuplicatePage($dbcon);
    $pages[] = new AnonymizePage($dbcon);
    if (isHL7OptionInstalled())
        $pages[] = new MatchORMPage($dbcon);
    $config = $dbcon->query("select matchworklist from config where matchworklist=1");
    if ($config && (1 == $config->rowCount())) {
        $pages[] = new PatientReconciliationPage($dbcon);
    }
}
if ($dbcon->hasaccess("upload", $username)) {
    $pages[] = new UploadImagePage($dbcon);
}
if ($dbcon->hasaccess("import", $username)) {
    $pages[] = new ImportWorklistPage($dbcon);
}
if ($dbcon->hasaccess("monitor", $username)) {
    $hl7 = isset($_REQUEST['hl7'])? $_REQUEST['hl7'] : 0;
    $pages[] = new LogfilePage($dbcon, $hl7);
    $pages[] = new LiveMonitorPage($dbcon);
}
print "<html>";
print "<head><title>$PRODUCT - " . pacsone_gettext("Tools");
print "</title></head>";
print "<body>";
require_once 'header.php';
// check privileges
if (count($pages) == 0) {
    print "<h3><font color=red>";
    print pacsone_gettext("You do not have sufficient privileges to access this page");
    print "</font></h3>";
} else {
    if (isset($_REQUEST['page']))
        $current = stripslashes($_REQUEST['page']);
    else if (!isset($current) || !strlen($current))
        $current = $pages[0]->title;
    $tabs = new Tabs($pages, $current);
    $tabs->showHtml();
}
require_once 'footer.php';
print "</body>";
print "</html>";

?>
