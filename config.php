<?php
//
// config.php
//
// Module for system configurations
//
// CopyRight (c) 2003-2021 RainbowFish Software
//
if (!session_id())
    session_start();

require_once 'locale.php';
include_once 'security.php';
include_once 'display.php';

global $PRODUCT;
global $HOUR_TBL;
global $WEEKDAY_TBL;
global $WORKLIST_FROM_HL7_ORM_TBL;
global $WADO_BYPASS_FILE;
global $WADO_SECURITY_TBL;
global $WADO_SECURITY_FIXED;
// file to bypass authentication if present
$dir = dirname($_SERVER['SCRIPT_FILENAME']);
$dir = substr($dir, 0, strlen($dir) - 3);
$bypass = $dir . $WADO_BYPASS_FILE;

$dbcon = new MyConnection();
$username = $dbcon->username;
if (!$dbcon->hasaccess("admin", $username)) {
    print "<h3><font color=red>";
    print pacsone_gettext("You must have the Admin privilege in order to access this page.");
    print "</font></h3>";
    exit();
}
// email statistics report bit-mask
$DAILY_REPORT = 0x1;
$WEEKLY_REPORT = 0x2;
$MONTHLY_REPORT = 0x4;
$JOURNAL_REPORT = 0x8;
$ini = parseIniByAeTitle($_SESSION['aetitle']);
if (isset($_POST['update'])) {
    global $BGCOLOR;
    $bindList = array();
    $autologout = $_POST['autologout'];
    $passwordexpire = $_POST['passwordexpire'];
    $maxupload = $_POST['maxupload'];
    $format = $_POST['archiveformat'];
    $archiveage = $_POST['archiveage'];
    $worklistage = $_POST['worklistage'];
    $query = "update config set autologout=?,passwordexpire=?,maxupload=?,";
    array_push($bindList, $autologout, $passwordexpire, $maxupload);
    // default archive directory format
    $query .= "archiveformat=?,";
    $bindList[] = $format;
    // default short-term archive directory
    if (isset($_POST['archivedir']) && strlen($_POST['archivedir'])) {
        $dir = cleanPostPath($_POST['archivedir']);
        if (!file_exists($dir)) {
            print "<h3><font color=red>";
            printf(pacsone_gettext("Default Short-Term Archive Directory %s does not exist!"), $dir);
            print "</font></h3>";
            exit();
        }
        $query .= "archivedir=?,";
        $bindList[] = $dir;
    } else {
        $query .= "archivedir=NULL,";
    }
    // default long-term archive directory
    if (isset($_POST['longtermdir']) && strlen($_POST['longtermdir'])) {
        $dir = cleanPostPath($_POST['longtermdir']);
        if (!file_exists($dir)) {
            print "<h3><font color=red>";
            printf(pacsone_gettext("Default Long-Term Archive Directory %s does not exist!"), $dir);
            print "</font></h3>";
            exit();
        }
        $query .= "longtermdir=?,";
        $bindList[] = $dir;
    } else {
        $query .= "longtermdir=NULL,";
    }
    // automatic aging from default short-term to default long-term archive directory
    if ($archiveage) {
        $archiveage = $_POST['age'];
    }
    $query .= "archiveage=?,";
    $bindList[] = $archiveage;
    // aging period for purging old worklist records
    $query .= "worklistage=?,";
    $bindList[] = $worklistage;
    if (isset($_POST['attachment'])) {
        // store attachment into database table directly
        $query .= "attachment=?,";
        $bindList[] = $_POST['attachment'];
    }
    // store attachment under the following upload directory
    if (isset($_POST['uploaddir']) && strlen($_POST['uploaddir'])) {
        $dir = cleanPostPath($_POST['uploaddir']);
        if (!file_exists($dir)) {
            print "<h3><font color=red>";
            printf(pacsone_gettext("Upload Directory %s does not exist!"), $dir);
            print "</font></h3>";
            exit();
        }
        // change to Unix-style path
        $dir = str_replace("\\", "/", $dir);
        // append '/' at the end if not so already
        if (strcmp(substr($dir, strlen($dir)-1, 1), "/"))
            $dir .= "/";
        $query .= "uploaddir=?,";
        $bindList[] = $dir;
    } else
        $query .= "uploaddir=NULL,";
    // store generated thumbnail images under the following directory
    if (isset($_POST['thumbnaildir'])) {
        if (strlen($_POST['thumbnaildir'])) {
            $dir = cleanPostPath($_POST['thumbnaildir']);
            if (!file_exists($dir)) {
                print "<h3><font color=red>";
                printf(pacsone_gettext("Thumbnails Directory %s does not exist!"), $dir);
                print "</font></h3>";
                exit();
            }
            // change to Unix-style path
            $dir = str_replace("\\", "/", $dir);
            // append '/' at the end if not so already
            if (strcmp(substr($dir, strlen($dir)-1, 1), "/"))
                $dir .= "/";
            $query .= "thumbnaildir=?,";
            $bindList[] = $dir;
        } else {
            $query .= "thumbnaildir=NULL,";
        }
    }
    // store generated jpg/gif images the following directory
    if (isset($_POST['imagedir'])) {
        if (strlen($_POST['imagedir'])) {
            $dir = cleanPostPath($_POST['imagedir']);
            if (!file_exists($dir)) {
                print "<h3><font color=red>";
                printf(pacsone_gettext("Images Directory %s does not exist!"), $dir);
                print "</font></h3>";
                exit();
            }
            // change to Unix-style path
            $dir = str_replace("\\", "/", $dir);
            // append '/' at the end if not so already
            if (strcmp(substr($dir, strlen($dir)-1, 1), "/"))
                $dir .= "/";
            $query .= "imagedir=?,";
            $bindList[] = $dir;
        } else {
            $query .= "imagedir=NULL,";
        }
    }
    // whether or not to bypass displaying of the Series level
    if (isset($_POST['skipseries'])) {
        $query .= "skipseries=?,";
        $bindList[] = $_POST['skipseries'];
    }
    // Administrator's Email Address
    if (isset($_POST['adminemail'])) {
        $email = $_POST['adminemail'];
        if (strlen($email)) {
            $query .= "adminemail=?,";
            $bindList[] = $email;
        }
    }
    // path to PHP runtime executable (php.exe)
    if (isset($_POST['phpexe'])) {
        $phpexe = cleanPostPath($_POST['phpexe']);
        if (!file_exists($phpexe) || !is_executable($phpexe)) {
            print "<h3><font color=red>";
            printf(pacsone_gettext("%s does not exist"), $phpexe);
            print "</font></h3>";
            exit();
        }
        $query .= "phpexe=?,";
        $bindList[] = $phpexe;
    }
    // whether or not email users about failed database jobs
    if (isset($_POST['emailfailedjobs'])) {
        $query .= "emailfailedjobs=?,";
        $bindList[] = $_POST['emailfailedjobs'];
    }
    // whether or not to enable the Patient Reconciliation feature
    if (isset($_POST['matchworklist'])) {
        $query .= "matchworklist=?,";
        $bindList[] = $_POST['matchworklist'];
    }
    // whether or not to enable the Study Reconciliation feature
    if (isset($_POST['studyreconcil'])) {
        $query .= "studyreconcil=?,";
        $bindList[] = $_POST['studyreconcil'];
    }
    // whether or not to send statistics report emails
    $mask = 0;
    if (isset($_POST['dailyreport']))
        $mask |= $DAILY_REPORT;
    if (isset($_POST['weeklyreport']))
        $mask |= $WEEKLY_REPORT;
    if (isset($_POST['monthlyreport']))
        $mask |= $MONTHLY_REPORT;
    if (isset($_POST['journalreport']))
        $mask |= $JOURNAL_REPORT;
    $query .= "emailreport=$mask,";
    // date and time formats
    if (isset($_POST['dateformat'])) {
        global $DATE_FORMATS, $DATE_FORMATS_ORACLE;
        $choices = $DATE_FORMATS;
        if ($dbcon->useOracle)
            $choices = $DATE_FORMATS_ORACLE;
        $format = $_POST['dateformat'];
        if (isset($choices[$format])) {
            if (strcasecmp($format, "US")) {
                $query .= "dateformat=?,";
                $bindList[] = $choices[$format];
            } else {
                $query .= "dateformat=NULL,";
            }
        }
    }
    if (isset($_POST['datetimeformat'])) {
        global $DATETIME_FORMATS, $DATETIME_FORMATS_ORACLE;
        $choices = $DATETIME_FORMATS;
        if ($dbcon->useOracle)
            $choices = $DATETIME_FORMATS_ORACLE;
        $format = $_POST['datetimeformat'];
        if (isset($choices[$format])) {
            if (strcasecmp($format, "US")) {
                $query .= "datetimeformat=?,";
                $bindList[] = $choices[$format];
            } else {
                $query .= "datetimeformat=NULL,";
            }
        }
    }
    // automatic aging schedule
    if (isset($WEEKDAY_TBL[$_POST['agingwday']]) && isset($HOUR_TBL[$_POST['aginghour']])) {
        $weekday = $WEEKDAY_TBL[$_POST['agingwday']];
        $hour = $HOUR_TBL[$_POST['aginghour']];
        $schedule = ($weekday << 8) + $hour;
        $query .= "ageschedule=$schedule,";
    }
    // whether or not to auto-convert received images into thumbnail/full-size JPG/GIF
    if (isset($_POST['autoconvert'])) {
        $query .= "autoconvert=?,";
        $bindList[] = $_POST['autoconvert'];
    }
    // maximum limit for automatic conversion jobs to be run in one batch
    if (isset($_POST['convertlimit'])) {
        $query .= "convertlimit=?,";
        $bindList[] = $_POST['convertlimit'];
    }
    // Dicom video conversion format
    if (isset($_POST['videoformat'])) {
        $query .= "videoformat=?,";
        $bindList[] = $_POST['videoformat'];
        if (isset($_POST['webmargs'])) {
            $query .= "webmargs=?,";
            $bindList[] = $_POST['webmargs'];
        }
        if (isset($_POST['mp4args'])) {
            $query .= "mp4args=?,";
            $bindList[] = $_POST['mp4args'];
        }
        if (isset($_POST['swfargs'])) {
            $query .= "swfargs=?,";
            $bindList[] = $_POST['swfargs'];
        }
    }
    // enable veterinary view
    if (isset($_POST['veterinary'])) {
        $query .= "veterinary=?,";
        $bindList[] = $_POST['veterinary'];
    }
    // non-default Dicom charset
    global $DICOM_CHARSET_TBL;
    $charset = $_POST['charset'];
    $selected = "";
    foreach ($DICOM_CHARSET_TBL as $key => $entry) {
        $description = $entry[0];
        if (strcasecmp($description, $charset) == 0) {
            $selected = $key;
            break;
        }
    }
    if (strlen($selected)) {
        if (strcasecmp($selected, "Default")) {
            $query .= "charset=?,";
            $bindList[] = $selected;
        } else {
            $query .= "charset=NULL,";
        }
    }
    if (isHL7OptionInstalled()) {
        // HL7 ORM to DMWL generation
        $dbcon->query("delete from worklistfromhl7");
        if ($_POST['worklistfromhl7']) {
            foreach ($WORKLIST_FROM_HL7_ORM_TBL as $key => $entry) {
                $tag = $entry[2];
                $table = $entry[3];
                $column = $entry[4];
                $subq = "insert into worklistfromhl7 (tag,tablename,fieldname) values(?,?,?)";
                $subList = array($tag,$table,$column);
                if (!$dbcon->preparedStmt($subq, $subList))
                    die("Error running query [$subq], error = " . $dbcon->getError());
            }
            $query .= "worklistfromhl7=1,";
        } else {
            $query .= "worklistfromhl7=0,";
        }
    }
    // WADO security model
    if (isset($_POST['wadosecmodel'])) {
        $value = $_POST['wadosecmodel'];
        $query .= "wadosecmodel=?,";
        $bindList[] = $value;
        if ($value == $WADO_SECURITY_FIXED) {
            $username = $_POST['wadousername'];
            $password = $_POST['wadopassword'];
            // validate supplied username/password
            $test = new MyDatabase($dbcon->hostname, $dbcon->database, $username, $password);
            if (!$test->connection) {
                print "<p><h2><font color=red>";
                printf(pacsone_gettext("Failed to login to database: <u>%s</u> as Username: <u>%s</u>"), $dbcon->database, $username);
                print "</font></h2><p>";
                exit;
            }
            // write WADO security bypass file
            $fp = fopen($bypass, "w");
            if ($fp) {
                $encoded = base64_encode($username);
                fputs($fp, "Username = \"$encoded\"\r\n");
                $encoded = base64_encode($password);
                fputs($fp, "Password = \"$encoded\"\r\n");
                fclose($fp);
            }
        } else if (file_exists($bypass)) {
            unlink($bypass);
        }
    }
    // external access URL
    if (isset($_POST['externalAccessUrl'])) {
        $query .= "externalAccessUrl=?,";
        $bindList[] = $_POST['externalAccessUrl'];
    } else {
        $query .= "externalAccessUrl=NULL,";
    }
    // enable user filters
    if (isset($_POST['userfilter'])) {
        $query .= "userfilter=?,";
        $bindList[] = $_POST['userfilter'];
    }
    // customized PHP scripts
    if ($_POST['customphp']) {
        $freq = $_POST['customphp'];
        $day = 0;
        $hour = 0;
        switch ($freq) {
        case 1: // daily
            $hour = $HOUR_TBL[$_POST['customphpdhour']];
            break;
        case 2: // weekly
            $day = $WEEKDAY_TBL[$_POST['customphpwday']];
            $hour = $HOUR_TBL[$_POST['customphpwhour']];
            break;
        case 3: // monthly
            $day = $_POST['customphpmday'];
            $hour = $HOUR_TBL[$_POST['customphpmhour']];
            break;
        default:
            break;
        }
        $schedule = ($freq << 16) + ($day << 8) + $hour;
        $query .= "customphp=$schedule,";
    } else {
        $query .= "customphp=0,";
    }
    // path to non-default or user-configured directory of PHP scripts
    if (isset($_POST['phpdir'])) {
        $phpdir = cleanPostPath($_POST['phpdir']);
        if (strlen($phpdir) && !file_exists($phpdir)) {
            print "<h3><font color=red>";
            printf(pacsone_gettext("Directory %s does not exist or not accessible!"), $phpdir);
            print "</font></h3>";
            exit();
        }
        $query .= "phpdir=?,";
        $bindList[] = $phpdir;
    }
    // parallel processing thread pool size
    if (isset($_POST['pthreadpoolsize'])) {
        $query .= "pthreadpoolsize=?,";
        $bindList[] = $_POST['pthreadpoolsize'];
    }
    // remove the last ','
    $npos = strrpos($query, ",");
    if ($npos != false)
        $query = substr($query, 0, $npos);
    if (!$dbcon->preparedStmt($query, $bindList))
        die("Error running query [$query], error = " . $dbcon->getError());
    // update AUTOSCAN table if necessary
    unset($query);
    $bindList = array();
    $autoscan = $_POST['autoscan'];
    $scansrc = cleanPostPath($_POST['scansrc']);
    if ($autoscan) {
        $ok = true;
        if (!file_exists($scansrc)) {
            print "<p><font color=red>";
            printf(pacsone_gettext("Invalid Scan Source Directory: [%s]"), $scansrc);
            print "</font><p>";
            $ok = false;
        }
        $scandest = cleanPostPath($_POST['scandest']);
        if (!file_exists($scandest)) {
            print "<p><font color=red>";
            printf(pacsone_gettext("Invalid Scan Destination Directory: [%s]"), $scandest);
            print "</font><p>";
            $ok = false;
        }
        $scaninterval = $_POST['scaninterval'];
        if ($scaninterval < 60) {
            print "<p><font color=red>";
            print pacsone_gettext("Scan Interval Must Be Greater Than 60 Seconds");
            print "</font><p>";
            $ok = false;
        }
        if ($ok) {
            $dbcon->query("delete from autoscan where scandest is not null");
            // change to Unix-style path
            $scansrc = str_replace("\\", "/", $scansrc);
            // append '/' at the end if not so already
            if (strcmp(substr($scansrc, strlen($scansrc)-1, 1), "/"))
                $scansrc .= "/";
            $scandest = str_replace("\\", "/", $scandest);
            if (strcmp(substr($scandest, strlen($scandest)-1, 1), "/"))
                $scandest .= "/";
            $query = "insert into autoscan (scansrc,scandest,scaninterval,enabled) values(?,?,?,1)";
            $bindList = array($scansrc, $scandest, $scaninterval);
        }
    } else {
        $query = "delete from autoscan where scandest is not null";
    }
    if (isset($query)) {
        if (count($bindList))
            $dbcon->preparedStmt($query, $bindList);
        else
            $dbcon->query($query);
    }
    // configure automatic scanning for worklist data
    unset($query);
    $bindList = array();
    $worklistscan = $_POST['worklistscan'];
    $worklistsrc = cleanPostPath($_POST['worklistsrc']);
    if ($worklistscan) {
        $ok = true;
        if (!file_exists($worklistsrc)) {
            print "<p><font color=red>";
            printf(pacsone_gettext("Invalid Worklist Scan Source Directory: [%s]"), $worklistsrc);
            print "</font><p>";
            $ok = false;
        }
        $worklistinterval = $_POST['worklistinterval'];
        if ($worklistinterval < 60) {
            print "<p><font color=red>";
            print pacsone_gettext("Worklist Scan Interval Must Be Greater Than 60 Seconds");
            print "</font><p>";
            $ok = false;
        }
        if ($ok) {
            $dbcon->query("delete from autoscan where scandest is null");
            // change to Unix-style path
            $worklistsrc = str_replace("\\", "/", $worklistsrc);
            // append '/' at the end if not so already
            if (strcmp(substr($worklistsrc, strlen($worklistsrc)-1, 1), "/"))
                $worklistsrc .= "/";
            $query = "insert into autoscan (scansrc,scandest,scaninterval,enabled) values(?,null,?,1)";
            $bindList = array($worklistsrc, $worklistinterval);
        }
    } else {
        $query = "delete from autoscan where scandest is null";
    }
    if (isset($query)) {
        if (count($bindList))
            $dbcon->preparedStmt($query, $bindList);
        else
            $dbcon->query($query);
    }
    // update LDAP Server configurations
    if ($_POST['ldap']) {
        $ini['LdapHost'] = $_POST['ldapHost'];
        $ini['LdapPort'] = $_POST['ldapPort'];
    } else {
        // disable LDAP Server
        unset($ini['LdapHost']);
        unset($ini['LdapPort']);
    }
    writeIniByAeTitle($_SESSION['aetitle'], $ini);
    // refresh this page
    $status = pacsone_gettext("System Configurations Updated.");
    header('Location: config.php?status=' . urlencode($status));
    exit();
}
print "<html>";
print "<head><title>$PRODUCT - ";
print pacsone_gettext("System Configurations");
print "</title></head>";
print "<body>";
require_once 'header.php';
if (isset($_GET['status'])) {
    print "<p><font color='$BGCOLOR'>";
    print urldecode($_GET['status']);
    print "</font>";
}
print "<table width=100% cellpadding=0 cellspacing=0 border=0>\n";
print "<tr><td>\n";
print "<br><b><u>";
print pacsone_gettext("Current System Configurations:");
print "</u></b>\n";
print "</td></tr>\n";
print "<tr><td>&nbsp;</td></tr>\n";
print "<tr><td>\n";
print "<table width=80% border=1 cellpadding=2 cellspacing=0>\n";
print "<form method='POST' action='config.php'>\n";
print "<input type='hidden' name='update' value=1>\n";
$result = $dbcon->query("select * from config");
$row = $result->fetch(PDO::FETCH_ASSOC);
print "<tr><td>" . pacsone_gettext("Default Short-Term Archive Directory:") . "</td><td>\n";
$data = $row['archivedir'];
$value = "<input type='text' size=32 maxlength=255 name='archivedir'";
if (isset($data))
    $value .= "value='$data'";
$value .= ">\n";
print $value;
print "</td></tr>\n";
print "<tr><td>";
global $ARCHIVE_DIR_FORMAT_FLAT;
global $ARCHIVE_DIR_FORMAT_HIERARCHY;
global $ARCHIVE_DIR_FORMAT_STUDYUID;
global $ARCHIVE_DIR_FORMAT_COMBO;
global $ARCHIVE_DIR_FORMAT_PID_STUDYDATE;
$data = $row['archiveformat'];
$checked = ($data == $ARCHIVE_DIR_FORMAT_FLAT)? "checked" : "";
print pacsone_gettext("Default Archive Directory Format:") . "</td><td>\n";
print "<input type='radio' name='archiveformat' value=$ARCHIVE_DIR_FORMAT_FLAT $checked><b>";
print pacsone_gettext("Flat") . "</b> ";
print pacsone_gettext("(Received images are stored under <b>%Assigned Directory%/YYYY-MM-DD-WEEKDAY/</b> sub-folders)<br>\n");
$checked = ($data == $ARCHIVE_DIR_FORMAT_HIERARCHY)? "checked" : "";
print "<input type='radio' name='archiveformat' value=$ARCHIVE_DIR_FORMAT_HIERARCHY $checked><b>";
print pacsone_gettext("Hierarchical") . "</b> ";
print pacsone_gettext("(Received images are stored under <b>%Assigned Directory%/YYYY/MM/DD/</b> sub-folders)<br>\n");
$checked = ($data == $ARCHIVE_DIR_FORMAT_STUDYUID)? "checked" : "";
print "<input type='radio' name='archiveformat' value=$ARCHIVE_DIR_FORMAT_STUDYUID $checked><b>";
print pacsone_gettext("Study Instance UID") . "</b> ";
print pacsone_gettext("(Received images are stored under <b>%Assigned Directory%/\$StudyInstanceUid/</b> sub-folders)<br>\n");
$checked = ($data == $ARCHIVE_DIR_FORMAT_COMBO)? "checked" : "";
print "<input type='radio' name='archiveformat' value=$ARCHIVE_DIR_FORMAT_COMBO $checked><b>";
print pacsone_gettext("Combination") . "</b> ";
print pacsone_gettext("(Received images are stored under <b>%AssignedDirectory%/YYYY-MM-DD-WEEKDAY/\$StudyInstanceUid/</b> sub-folders)<br>\n");
$checked = ($data == $ARCHIVE_DIR_FORMAT_PID_STUDYDATE)? "checked" : "";
print "<input type='radio' name='archiveformat' value=$ARCHIVE_DIR_FORMAT_PID_STUDYDATE $checked><b>";
print pacsone_gettext("Patient ID/Study Date") . "</b> ";
print pacsone_gettext("(Received images are stored under <b>&lt;AssignedDirectory&gt;/\$PatientID/\$StudyDate/</b> sub-folders)<br>\n");
print "</td></tr>\n";
print "<tr><td>" . pacsone_gettext("Default Long-Term Archive Directory:") . "</td><td>\n";
$data = $row['longtermdir'];
$value = "<input type='text' size=32 maxlength=255 name='longtermdir'";
if (isset($data))
    $value .= "value='$data'";
$value .= ">\n";
print $value;
print "</td></tr>\n";
print "<tr><td>";
print pacsone_gettext("Automatically Age From Default Short-Term Archive Directory To Default Long-Term Archive Directory:") . "</td><td>";
$data = $row['archiveage'];
$checked = ($data == 0)? "checked" : "";
print "<input type='radio' name='archiveage' value=0 $checked>";
print pacsone_gettext("Disabled") . "<br>";
$checked = ($data == 0)? "" : "checked";
print "<input type='radio' name='archiveage' value=1 $checked>";
printf(pacsone_gettext("Age Images Received More Than <input type='text' name='age' size=6 maxlength=6 value='%d'> Days Ago"), $data);
print "</td></tr>\n";
// Automatic Aging Schedule
$schedule = $row['ageschedule'];
$weekday = ($schedule >> 8);
$hour = ($schedule & 0xFF);
print "<tr><td>";
print pacsone_gettext("Date and Time Schedule for Automatic Aging from Short-term to Long-term Archive Directory: ");
print "</td><td>\n";
print pacsone_gettext("Run Automatic Aging Weekly On Every ");
print "<select name='agingwday'>";
foreach ($WEEKDAY_TBL as $wday => $value) {
    $selected = ($value == $weekday)? "selected" : "";
    print "<option $selected>$wday</option>";
}
print "</select>\n";
print "&nbsp;";
print pacsone_gettext("At: ");
print "<select name='aginghour'>";
foreach ($HOUR_TBL as $descr => $value) {
    $selected = ($value == $hour)? "selected" : "";
    print "<option $selected>$descr</option>";
}
print "</select>\n";
print "</td></tr>";
print "<tr><td>";
print pacsone_gettext("Automatically Logout Browser Sessions After This Idle Period: ");
print "</td>\n";
$value = $row['autologout'];
print "<td><input type='text' size=5 maxlength=5 name='autologout' value='$value'>";
print pacsone_gettext("Minutes");
print "</td>\n";
print "</tr>\n";
$value = $row['passwordexpire'];
print "<tr><td>";
print pacsone_gettext("Automatically Expire User Passwords After: ");
print "</td>\n";
print "<td><input type='text' size=5 maxlength=5 name='passwordexpire' value='$value'>";
print pacsone_gettext("Days");
print "</td>\n";
print "</tr>\n";
$value = $row['maxupload'];
print "<tr><td>";
print pacsone_gettext("Maximum Upload File Size: ");
print "</td>\n";
printf("<td><input type='text' size=5 maxlength=5 name='maxupload' value='$value'> %s</td>\n", pacsone_gettext("MBytes"));
print "</tr>\n";
// whether or not to bypass displaying of the Series level
$value = $row['skipseries'];
print "<tr><td>";
print pacsone_gettext("Bypass Displaying of the Series Level and Display All Images of a Study Directly, Without Displaying Series of That Study First: ");
print "</td>\n";
$checked = $value? "checked" : "";
print "<td><input type='radio' name='skipseries' value='1' $checked>";
print pacsone_gettext("Yes");
$checked = $value? "" : "checked";
print "&nbsp;<input type='radio' name='skipseries' value='0' $checked>";
print pacsone_gettext("No");
print "</td></tr>\n";
$value = $row['uploaddir'];
print "<tr><td>";
print pacsone_gettext("Upload Directory for Storing User-uploaded Attachments and Dicom Images: ");
print "</td>\n";
print "<td><input type='text' size=64 maxlength=255 name='uploaddir' value='$value'>";
print "</td></tr>\n";
print "<tr><td>";
print pacsone_gettext("Upload Attachments: ");
print "</td>\n";
$value = $row['attachment'];
$checked = (strlen($value) && strcasecmp($value, "table"))? "" : "checked";
print "<td><input type='radio' name='attachment' value='table' $checked>";
if (strlen($checked))
    print "<b>";
print pacsone_gettext("Store uploaded attachment into database table directly");
if (strlen($checked))
    print "</b>";
print "<br>";
$checked = (strlen($value) && strcasecmp($value, "table"))? "checked" : "";
print "<input type='radio' name='attachment' value='directory' $checked>";
if (strlen($checked))
    print "<b>";
print pacsone_gettext("Store uploaded attachment under the above Upload Directory: ");
if (strlen($checked))
    print "</b>";
print "</td></tr>\n";
// thumbnail directory
$value = $row['thumbnaildir'];
print "<tr><td>";
print pacsone_gettext("Directory for Storing Converted Thumbnail JPG/GIF Images:");
print "</td>\n";
print "<td>";
printf(pacsone_gettext("Default directory is the <b>php/thumbnails</b> sub-folder where %s is installed"), $PRODUCT);
print "<p><input type='text' size=64 maxlength=255 name='thumbnaildir' value='$value'>";
print "</td></tr>\n";
// image directory
$value = $row['imagedir'];
print "<tr><td>";
print pacsone_gettext("Directory for Storing Converted JPG/GIF Images:");
print "</td>\n";
print "<td>";
printf(pacsone_gettext("Default directory is the <b>php/images</b> sub-folder where %s is installed") , $PRODUCT);
print "<p><input type='text' size=64 maxlength=255 name='imagedir' value='$value'>";
print "</td></tr>\n";
// Auto-Scan parameters
$result = $dbcon->query("select * from autoscan where scandest is not null");
if ($result && $result->rowCount()) {
    // allow one Auto-Scan directory for now
    $scanrow = $result->fetch(PDO::FETCH_ASSOC);
}
print "<tr><td>";
print pacsone_gettext("Auto-Scan Directory for Importing Dicom Images: ");
print "</td>\n";
$checked = (isset($scanrow) && $scanrow["enabled"])? "" : "checked";
print "<td><input type='radio' name='autoscan' value=0 $checked>";
if (strlen($checked))
    print "<b>";
print pacsone_gettext("Disable Auto-Scan: ");
if (strlen($checked))
    print "</b>";
$checked = (isset($scanrow) && $scanrow["enabled"])? "checked" : "";
print "<br><input type='radio' name='autoscan' value=1 $checked>";
if (strlen($checked))
    print "<b>";
print pacsone_gettext("Enable Auto-Scan: ");
if (strlen($checked))
    print "</b>";
print "<br><ul><li>";
$value = isset($scanrow)? $scanrow["scansrc"] : "";
print pacsone_gettext("Auto-Scan Source Directory: ");
printf(pacsone_gettext("(%s will move any Dicom Part-10 formatted image from this source directory to the destination folder below, and <a href=\"tools.php?page=%s\">Import</a> it to the %s database)"), $PRODUCT, urlencode(pacsone_gettext("Import")), $PRODUCT);
print " <input type='text' size=64 maxlength=255 name='scansrc' value='$value'></li>";
$value = isset($scanrow)? $scanrow["scandest"] : "";
print pacsone_gettext("<li>Auto-Scan Destination Folder: ");
print "<input type='text' size=64 maxlength=255 name='scandest' value='$value'></li>";
$value = isset($scanrow)? $scanrow["scaninterval"] : 60;
print pacsone_gettext("<li>Scan Interval: ");
print "<input type='text' size=6 maxlength=6 name='scaninterval' value='$value'>";
print pacsone_gettext("Seconds") . "</li>";
print "</ul></td></tr>\n";
print "<tr><td>";
// Administrator's Email Address
$value = isset($row)? $row["adminemail"] : "";
print pacsone_gettext("Enter Administrator's Email Address:") . "</td>";
print "<td>";
print pacsone_gettext("(This email address is where all the system-generated emails, including reports, notifications, etc., will be delivered to)");
print "<br>";
print "<input type=text name='adminemail' value='$value' size=64 maxlength=255>";
print "</td></tr>\n";
// path to PHP runtime executable (php.exe)
print "<tr><td>";
print pacsone_gettext("Enter Path to PHP Runtime Executable (<b>php.exe</b>):") . "</td>";
print "<td>";
$value = isset($row)? $row["phpexe"] : "";
if (!strlen($value))
    $value = stristr(getenv("OS"), "Windows")? "C:/php/php.exe" : "/usr/local/bin/php";
print "<input type=text name='phpexe' value='$value' size=64 maxlength=255>";
print "</td></tr>\n";
// whether or not to email users about failed jobs
print "<tr><td>";
print pacsone_gettext("Email Notify Users About Failed Jobs") . "</td>";
$value = $row['emailfailedjobs'];
$checked = $value? "checked" : "";
print "<td><input type='radio' name='emailfailedjobs' value='1' $checked>";
print pacsone_gettext("Yes");
$checked = $value? "" : "checked";
print "&nbsp;<input type='radio' name='emailfailedjobs' value='0' $checked>";
print pacsone_gettext("No");
print "</td></tr>\n";
// aging period for purging old worklist records
print "<tr><td>";
print pacsone_gettext("Aging Period for Purging Old Worklist Records:") . "</td>";
$value = $row['worklistage'];
print "<td><input type='text' size=5 maxlength=5 name='worklistage' value='$value'>&nbsp;";
print pacsone_gettext("Days");
print "&nbsp;&nbsp;&nbsp;";
printf(pacsone_gettext("(Worklist Records Received More Than %d Days Ago Will Be Purged Automatically)"), $value);
print "</td></tr>\n";
// whether or not to enable Patient Reconciliation feature
print "<tr><td>";
global $CUSTOMIZE_PATIENT;
printf(pacsone_gettext("Enable %s Reconciliation Feature:"), $CUSTOMIZE_PATIENT) . "</td>";
$value = $row['matchworklist'];
$checked = $value? "checked" : "";
print "<td><input type='radio' name='matchworklist' value='1' $checked>";
print pacsone_gettext("Yes");
$checked = $value? "" : "checked";
print "&nbsp;<input type='radio' name='matchworklist' value='0' $checked>";
print pacsone_gettext("No");
print "</td></tr>\n";
// whether or not to enable Study Reconciliation feature
print "<tr><td>";
print pacsone_gettext("Enable Study Reconciliation Feature:") . "</td>";
$value = $row['studyreconcil'];
$checked = $value? "checked" : "";
print "<td><input type='radio' name='studyreconcil' value='1' $checked>";
print pacsone_gettext("Yes");
$checked = $value? "" : "checked";
print "&nbsp;<input type='radio' name='studyreconcil' value='0' $checked>";
print pacsone_gettext("No");
print "</td></tr>\n";
// automatic scanning of worklist data
$result = $dbcon->query("select * from autoscan where scandest is null");
if ($result && $result->rowCount()) {
    // allow one Auto-Scan directory for now
    $worklistrow = $result->fetch(PDO::FETCH_ASSOC);
}
print "<tr><td>";
print pacsone_gettext("Auto-Scan Directory for Worklist Data: ");
print "</td>\n";
$checked = (isset($worklistrow) && $worklistrow["enabled"])? "" : "checked";
print "<td><input type='radio' name='worklistscan' value=0 $checked>";
if (strlen($checked))
    print "<b>";
print pacsone_gettext("Disable Auto-Scan Worklist: ");
if (strlen($checked))
    print "</b>";
$checked = (isset($worklistrow) && $worklistrow["enabled"])? "checked" : "";
print "<br><input type='radio' name='worklistscan' value=1 $checked>";
if (strlen($checked))
    print "<b>";
print pacsone_gettext("Enable Auto-Scan Worklist: ");
if (strlen($checked))
    print "</b>";
print "<br><ul><li>";
$value = isset($worklistrow)? $worklistrow["scansrc"] : "";
print pacsone_gettext("Source Directory for Worklist Auto-Scan: ");
printf(pacsone_gettext("(%s will scan any text file in this source directory, and import properly-formated worklist data to the %s database)"), $PRODUCT, $PRODUCT);
print " <input type='text' size=64 maxlength=255 name='worklistsrc' value='$value'></li>";
$value = isset($worklistrow)? $worklistrow["scaninterval"] : 60;
print pacsone_gettext("<li>Scan Interval: ");
print "<input type='text' size=6 maxlength=6 name='worklistinterval' value='$value'>";
print pacsone_gettext("Seconds") . "</li>";
print "</ul></td></tr>\n";
// statistics report emails
print "<tr><td>";
print pacsone_gettext("Enable Statistics Report Emails: ");
print "</td>\n";
$mask = $row["emailreport"];
print "<td>";
$checked = ($mask & $DAILY_REPORT)? "checked" : "";
print "<input type='checkbox' name='dailyreport' $checked>";
print pacsone_gettext("Enable Daily Statistics Report Emails");
$checked = ($mask & $WEEKLY_REPORT)? "checked" : "";
print "<br><input type='checkbox' name='weeklyreport' $checked>";
print pacsone_gettext("Enable Weekly Statistics Report Emails");
$checked = ($mask & $MONTHLY_REPORT)? "checked" : "";
print "<br><input type='checkbox' name='monthlyreport' $checked>";
print pacsone_gettext("Enable Monthly Statistics Report Emails");
$checked = ($mask & $JOURNAL_REPORT)? "checked" : "";
print "<br><input type='checkbox' name='journalreport' $checked>";
print pacsone_gettext("Enable Monthly System Journal Report Emails");
print "</td></tr>";
// Date and Time formats
print "<tr><td>";
print pacsone_gettext("Date Format: ");
print "</td>\n";
$format = isset($row["dateformat"])? $row["dateformat"] : "";
$us = strlen($format)? "" : "checked";
$euro = strlen($format)? "checked" : "";
print "<td>";
print "<input type='radio' name='dateformat' value='US' $us>&nbsp;";
print pacsone_gettext("United States format (YYYY-MM-DD)");
print "<br><input type='radio' name='dateformat' value='EURO' $euro>&nbsp;";
print pacsone_gettext("European format (DD.MM.YYYY)");
print "</td></tr>";
print "<tr><td>";
print pacsone_gettext("DateTime Format: ");
print "</td>\n";
$format = isset($row["datetimeformat"])? $row["datetimeformat"] : "";
$us = strlen($format)? "" : "checked";
$euro = strlen($format)? "checked" : "";
print "<td>";
print "<input type='radio' name='datetimeformat' value='US' $us>&nbsp;";
print pacsone_gettext("United States format (YYYY-MM-DD HH:MM:SS)");
print "<br><input type='radio' name='datetimeformat' value='EURO' $euro>&nbsp;";
print pacsone_gettext("European format (DD.MM.YYYY HH:MM:SS)");
print "</td></tr>";
// whether or not to auto-convert received images into thumbnail/full-size JPG/GIF
print "<tr><td>";
print pacsone_gettext("Enable Automatic Conversion of Received Dicom Images into Thumbnail/Full-size JPG/GIF images:") . "</td>";
$value = $row['autoconvert'];
$checked = $value? "checked" : "";
print "<td><input type='radio' name='autoconvert' value='1' $checked>";
print pacsone_gettext("Yes");
$checked = $value? "" : "checked";
print "&nbsp;<input type='radio' name='autoconvert' value='0' $checked>";
print pacsone_gettext("No");
print "</td></tr>\n";
// maximum number of automatic conversions that can be run at once time
print "<tr><td>";
print pacsone_gettext("Maximum Limit of Automatic Conversion Jobs To be Run in One Batch:") . "</td>";
$value = $row['convertlimit'];
print "<td><input type='text' name='convertlimit' value='$value' size=3></td></tr>\n";
// Dicom video conversion options
print "<tr><td>";
print pacsone_gettext("Convert Received Dicom Video into HTML5 or Flash Video Format:") . "</td>";
$value = $row['videoformat'];
global $CONVERT_VIDEO_NONE;
global $CONVERT_VIDEO_WEBM;
global $CONVERT_VIDEO_MP4;
global $CONVERT_VIDEO_SWF;
global $CONVERT_VIDEO_FORMAT_TBL;
$webmArgs = $row['webmargs'];
if (strlen($webmArgs) == 0)
    $webmArgs = $CONVERT_VIDEO_FORMAT_TBL[$CONVERT_VIDEO_WEBM][1];
$mp4Args = $row['mp4args'];
if (strlen($mp4Args) == 0)
    $mp4Args = $CONVERT_VIDEO_FORMAT_TBL[$CONVERT_VIDEO_MP4][1];
$swfArgs = $row['swfargs'];
if (strlen($swfArgs) == 0)
    $swfArgs = $CONVERT_VIDEO_FORMAT_TBL[$CONVERT_VIDEO_SWF][1];
$checked = ($value == $CONVERT_VIDEO_NONE)? "checked" : "";
print "<td><input type='radio' name='videoformat' value='$CONVERT_VIDEO_NONE' $checked>";
print $CONVERT_VIDEO_FORMAT_TBL[$CONVERT_VIDEO_NONE][0];
$checked = ($value == $CONVERT_VIDEO_WEBM)? "checked" : "";
print "<br><input type='radio' name='videoformat' value='$CONVERT_VIDEO_WEBM' $checked>";
print $CONVERT_VIDEO_FORMAT_TBL[$CONVERT_VIDEO_WEBM][0];
print "</input>&nbsp;&nbsp;&nbsp;";
print pacsone_gettext("Conversion Settings:");
print "&nbsp;<input type='text' size=64 maxlength=255 name='webmargs' value='$webmArgs'></input>";
$checked = ($value == $CONVERT_VIDEO_MP4)? "checked" : "";
print "<br><input type='radio' name='videoformat' value='$CONVERT_VIDEO_MP4' $checked>";
print $CONVERT_VIDEO_FORMAT_TBL[$CONVERT_VIDEO_MP4][0];
print "</input>&nbsp;&nbsp;&nbsp;";
print pacsone_gettext("Conversion Settings:");
print "&nbsp;<input style='margin-left:11px;' type='text' size=64 maxlength=255 name='mp4args' value='$mp4Args'></input>";
$checked = ($value == $CONVERT_VIDEO_SWF)? "checked" : "";
print "<br><input type='radio' name='videoformat' value='$CONVERT_VIDEO_SWF' $checked>";
print $CONVERT_VIDEO_FORMAT_TBL[$CONVERT_VIDEO_SWF][0];
print "</input>&nbsp;&nbsp;&nbsp;";
print pacsone_gettext("Conversion Settings:");
print "&nbsp;<input style='margin-left:9px;' type='text' size=64 maxlength=255 name='swfargs' value='$swfArgs'></input>";
print "</td></tr>\n";
// whether or not to enable veterinary information view
print "<tr><td>";
print pacsone_gettext("Enable Display of Veterinary Specific Information:") . "</td>";
$value = $row['veterinary'];
$checked = $value? "checked" : "";
print "<td><input type='radio' name='veterinary' value='1' $checked>";
print pacsone_gettext("Yes");
$checked = $value? "" : "checked";
print "&nbsp;<input type='radio' name='veterinary' value='0' $checked>";
print pacsone_gettext("No");
print "</td></tr>\n";
// non-default Dicom charset
global $DICOM_CHARSET_TBL;
print "<tr><td>";
print pacsone_gettext("Select Specific Character Set:") . "</td>";
print "<td>";
print "<select name='charset'>";
$value = $row['charset'];
foreach ($DICOM_CHARSET_TBL as $charset => $entry) {
    $selected = (strlen($value) && !strcasecmp($value, $charset))? "selected" : "";
    $description = $entry[0];
    print "<option $selected>$description</option>";
}
print "</select>\n";
print "</td></tr>\n";
if (isHL7OptionInstalled()) {
    // HL7 ORM to DMWL generation
    global $BGCOLOR;
    print "<tr><td>";
    print pacsone_gettext("Enable Automatic Conversion of Received HL7 ORM Message into Dicom Modality Worklist (DMWL) Record:") . "</td>";
    $value = $row['worklistfromhl7'];
    $checked = $value? "" : "checked";
    print "<td><input type='radio' name='worklistfromhl7' value='0' $checked>";
    print pacsone_gettext("Disabled");
    $checked = $value? "checked" : "";
    print "<br><input type='radio' name='worklistfromhl7' value='1' $checked>";
    print pacsone_gettext("Use The Following Look-up Table:");
    print "<p><table width=100% cellpadding=0 cellpadding=5 border=0>";
    print "<tr class='listhead' bgcolor=$BGCOLOR>";
    print "<td>" . pacsone_gettext("Dicom Modality Worklist Data") . "</td>";
    print "<td>" . pacsone_gettext("HL7 ORM Message Field") . "</td>";
    foreach ($WORKLIST_FROM_HL7_ORM_TBL as $key => $entry) {
        $dmwlname = $entry[0];
        $hl7field = $entry[1];
        $tag = $entry[2];
        print "<tr class='evenrows'>";
        $value = sprintf("%s (0x%08X)", $dmwlname, $tag);
        print "<td>$value</td>";
        print "<td><input type='text' name='$key' value='$hl7field' disabled size=16 maxlength=16></td>";
        print "</tr>";
    }
    print "</table>";
    print "</td></tr>\n";
}
// WADO security model
print "<tr><td>";
print pacsone_gettext("Web Access to DICOM Persistent Objects (WADO) Security Model:") . "</td><td>";
$value = $row['wadosecmodel'];
foreach ($WADO_SECURITY_TBL as $key => $descr) {
    $checked = ($value == $key)? "checked" : "";
    print "<input type='radio' name='wadosecmodel' value=$key $checked>$descr<br>";
    if ($key == $WADO_SECURITY_FIXED) {
        $username = "";
        $password = "";
        if (file_exists($bypass)) {
            $entries = parse_ini_file($bypass);
            if (function_exists("array_change_key_case"))
                $entries = array_change_key_case($entries);
            if (count($entries) && isset($entries["username"]))
                $username = base64_decode($entries["username"]);
            if (count($entries) && isset($entries["password"]))
                $password = base64_decode($entries["password"]);
        }
        print "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
        print pacsone_gettext("Username: ");
        print "<input type=text name='wadousername' value='$username' size=16 maxlength=64>&nbsp;";
        print pacsone_gettext("Password: ");
        print "<input type=password name='wadopassword' value='$password' size=16 maxlength=64><br>";
    }
}
print "</td></tr>\n";
// external access URL
print "<tr><td>";
print pacsone_gettext("Website URL Prefix to embed for external access (e.g., statistics report emails, HL7 ORU reports, etc):") . "</td><td>";
$value = $row['externalaccessurl'];
print "<br>" . pacsone_gettext("For example, <a href='http://192.168.0.100/yoursite/'>http://192.168.0.100/yoursite/</a>, etc") . "<p>";
print "<input type=text name='externalAccessUrl' value='$value' size=64 maxlength=255>";
print "</td></tr>\n";
// user filters
print "<tr><td>";
print pacsone_gettext("Enable User Access Filters Based on Attribute Values from Received Dicom Images:") . "</td>";
$value = $row['userfilter'];
$checked = $value? "checked" : "";
print "<td><input type='radio' name='userfilter' value='1' $checked>";
print pacsone_gettext("Yes");
$checked = $value? "" : "checked";
print "&nbsp;<input type='radio' name='userfilter' value='0' $checked>";
print pacsone_gettext("No");
print "</td></tr>\n";
// remote LDAP server configurations
print "<tr><td>";
print pacsone_gettext("Enable Remote LDAP Server for User Authentication:") . "</td>";
$checked = isset($ini['LdapHost'])? "checked" : "";
print "<td><input type='radio' name='ldap' value='1' $checked>&nbsp;";
print pacsone_gettext("Remote LDAP Server Host:");
$value = isset($ini['LdapHost'])? $ini['LdapHost'] : "";
print "<input type='text' name='ldapHost' size=32 maxlength=64 value='$value'>&nbsp;";
print pacsone_gettext("Remote LDAP Server Port:");
$value = isset($ini['LdapPort'])? $ini['LdapPort'] : "";
print "<input type='text' name='ldapPort' size=8 maxlength=8 value='$value'><br>";
$checked = isset($ini['LdapHost'])? "" : "checked";
print "<input type='radio' name='ldap' value='0' $checked>";
print pacsone_gettext("No");
print "</td></tr>\n";
// customized PHP scripts
print "<tr><td>";
$schedule = $row['customphp'];
$freq = $schedule >> 16;
$weekday = ($schedule >> 8) & 0xFF;
$hour = ($schedule & 0xFF);
print pacsone_gettext("Enable Customized PHP Scripts: ");
print "</td><td>\n";
$checked = ($freq == 0)? "checked" : "";
print "<input type='radio' name='customphp' value='0' $checked>";
print pacsone_gettext("No");
$checked = ($freq == 1)? "checked" : "";
print "<br><input type='radio' name='customphp' value='1' $checked>";
print pacsone_gettext("Run Customized PHP Scripts Daily At: ");
print "<select name='customphpdhour'>";
foreach ($HOUR_TBL as $descr => $value) {
    $selected = ($value == $hour)? "selected" : "";
    print "<option $selected>$descr</option>";
}
print "</select>\n";
$checked = ($freq == 2)? "checked" : "";
print "<br><input type='radio' name='customphp' value='2' $checked>";
print pacsone_gettext("Run Customized PHP Scripts Weekly On Every ");
print "<select name='customphpwday'>";
foreach ($WEEKDAY_TBL as $wday => $value) {
    $selected = ($value == $weekday)? "selected" : "";
    print "<option $selected>$wday</option>";
}
print "</select>\n";
print "&nbsp;";
print pacsone_gettext("At: ");
print "<select name='customphpwhour'>";
foreach ($HOUR_TBL as $descr => $value) {
    $selected = ($value == $hour)? "selected" : "";
    print "<option $selected>$descr</option>";
}
print "</select>\n";
$checked = ($freq == 3)? "checked" : "";
print "<br><input type='radio' name='customphp' value='3' $checked>";
print pacsone_gettext("Run Customized PHP Scripts Monthly On Every ");
print "<select name='customphpmday'>";
for ($i = 1; $i < 29; $i++) {
    $selected = ($i == $weekday)? "selected" : "";
    print "<option $selected>$i</option>";
}
print "</select>\n";
print "&nbsp;";
print pacsone_gettext(" Day of the Month At: ");
print "<select name='customphpmhour'>";
foreach ($HOUR_TBL as $descr => $value) {
    $selected = ($value == $hour)? "selected" : "";
    print "<option $selected>$descr</option>";
}
print "</select>\n";
print "</td></tr>";
// path to non-default or user-configured directory of PHP scripts
print "<tr><td>";
print pacsone_gettext("Enter Full Path to Site Specific/Non-default Directory of PHP Scripts:") . "</td>";
print "<td>";
$value = isset($row["phpdir"])? $row["phpdir"] : "";
print "<input type=text name='phpdir' value='$value' size=64 maxlength=255>";
print "</td></tr>\n";
// parallel processing thread pool size
global $DEFAULT_PARALLEL_THREAD_POOL_SIZE;
print "<tr><td>";
print pacsone_gettext("Number of Threads in Parallel Processing Thread Pool:") . "</td>";
print "<td>";
$value = isset($row["pthreadpoolsize"])? $row["pthreadpoolsize"] : $DEFAULT_PARALLEL_THREAD_POOL_SIZE;
print "<input type=text name='pthreadpoolsize' value='$value' size=3 maxlength=3>";
print "</td></tr>\n";
// end of table
print "</table>\n";
print "</td></tr>\n";
print "<tr><td colspan=2>&nbsp;</td></tr>\n";
print "<tr><td>";
printf("<input class='btn btn-primary' type=submit value='%s' title='", pacsone_gettext("Modify"));
print pacsone_gettext("Update System Configuration");
print "'>\n";
print "</td></tr>\n";
print "</table>\n";
print "</form>\n";

require_once 'footer.php';
print "</table>";
print "</body>";
print "</html>";
?>

