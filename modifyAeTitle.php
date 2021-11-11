<?php
//
// modifyAeTitle.php
//
// Module for modifying entries in the Application Entity Table
//
// CopyRight (c) 2003-2021 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once 'database.php';
include_once 'sharedData.php';
include_once 'xferSyntax.php';
include_once 'customize.php';

// main
global $PRODUCT;
$PREFERRED_TBL = array(
    "1.2.840.10008.1.2",
    "1.2.840.10008.1.2.1",
    "1.2.840.10008.1.2.2",
    "1.2.840.10008.1.2.4.70",
    "1.2.840.10008.1.2.4.50",
    "1.2.840.10008.1.2.4.51",
    "1.2.840.10008.1.2.5",
    "1.2.840.10008.1.2.4.80",
    "1.2.840.10008.1.2.4.81",
    "1.2.840.10008.1.2.4.90",
    "1.2.840.10008.1.2.4.91",
    "1.2.840.10008.1.2.4.51",
    "1.2.840.10008.1.2.4.57",
    "1.2.840.10008.1.2.4.100",
);
$dbcon = new MyConnection();
$username = $dbcon->username;
if (isset($_POST['title']))
	$title = $_POST['title'];
if (isset($_POST['action']))
   	$action = $_POST['actionvalue'];
if (isset($_POST['entry']))
   	$entry = $_POST['entry'];
$result = NULL;
if (isset($_GET['title'])) {
    modifyEntryForm($_GET['title']);
}
else {
    if (isset($action) && strcasecmp($action, "Delete") == 0) {
	    $result = deleteEntries($username, $entry);
    }
    else if (isset($action) && strcasecmp($action, "Add") == 0) {
	    if (isset($title)) {
	        $result = addEntry($username, $title);
        }
        else {
            addEntryForm();
        }
    }
    else if (isset($action) && strcasecmp($action, "Modify") == 0) {
	    $result = modifyEntry($username);
    }
}
if (isset($result)) {       // display results
    if (empty($result)) {   // success
        header('Location: applentity.php');
    }
    else {                  // error
        print "<html>\n";
        print "<head><title>$PRODUCT - ";
        print pacsone_gettext("Application Entity Error");
        print "</title></head>\n";
        print "<body>\n";
        require_once 'header.php';
        print $result;
		require_once 'footer.php';
        print "</body>\n";
        print "</html>\n";
    }
}

function deleteEntries($username, $entry)
{
    global $dbcon;
	$ok = array();
	$errors = array();
	foreach ($entry as $value) {
		$query = "delete from applentity where title=?";
        $bindList = array($value);
		if (!$dbcon->preparedStmt($query, $bindList)) {
			$errors[$value] = sprintf(pacsone_gettext("Database Error: %s"), $dbcon->getError());
            continue;
		} else {
            // remove any cronjob associated with this AE
            $query = "delete from cronjob where aetitle=?";
		    if (!$dbcon->preparedStmt($query, $bindList)) {
			    $errors[$value] = sprintf(pacsone_gettext("Database Error: %s"), $dbcon->getError());
                continue;
            }
            // remove any defined annotation format for Dicom printers
            $query = "delete from annotation where printer=?";
		    if (!$dbcon->preparedStmt($query, $bindList)) {
			    $errors[$value] = sprintf(pacsone_gettext("Database Error: %s"), $dbcon->getError());
                continue;
            }
            // remove any defined AE filters
		    if (!$dbcon->preparedStmt("delete from aefilter where sourceae=?", $bindList)) {
			    $errors[$value] = sprintf(pacsone_gettext("Database Error: %s"), $dbcon->getError());
                continue;
            }
            // remove any web user assignments for this AE
            if ($dbcon->hasaccess("admin", $username) &&
                !$dbcon->preparedStmt("delete from aeassigneduser where aetitle=?", $bindList)) {
			    $errors[$value] = sprintf(pacsone_gettext("Database Error: %s"), $dbcon->getError());
                continue;
            }
            // remove any group membership assignments for this AE
            $bindList = array($value, $value);
            if ($dbcon->hasaccess("admin", $username) &&
                !$dbcon->preparedStmt("delete from aegroup where (aetitle=? or memberae=?)", $bindList)) {
			    $errors[$value] = sprintf(pacsone_gettext("Database Error: %s"), $dbcon->getError());
                continue;
            }
            // remove any related Instance Availability Notifications for this AE
            if (!$dbcon->preparedStmt("delete from iansubscr where (source=? or subscriber=?)", $bindList)) {
			    $errors[$value] = sprintf(pacsone_gettext("Database Error: %s"), $dbcon->getError());
                continue;
            }
        }
		$ok[] = $value;
    // log activity to system journal
    $dbcon->logJournal($username, "Delete", "AeTitle", $value);
	}
	$result = "";
	if (!empty($errors)) {
        if (count($errors) == 1)
		    $result = pacsone_gettext("Error deleting the following Application Entity:");
        else
		    $result = pacsone_gettext("Error deleting the following Application Entities:");
        $result .= "<P>\n";
		foreach ($errors as $key => $value) {
			$result .= "<h3><font color=red><u>$key</u> - $value</font></h3><P>\n";
		}
	}
    return $result;
}

function addEntry(&$username, &$title)
{
    global $dbcon;
    global $XFER_SYNTAX_TBL;
    global $DICOM_CMDACCESS_TBL;
    global $DICOM_CMDFILTER_TBL;
    $result = "";
    $columns = array();
    $bindList = array();
    $values = "";
    $query = "insert into applentity (";
    $columns[] = "title";
    $values .= "?";
    $bindList[] = $title;
	$columns[] = "description";
	if (isset($_POST['description']) && strlen($_POST['description'])) {
        $values .= ",?";
		$bindList[] = $_POST['description'];
	} else
		$values .= ",NULL";
	$columns[] = "hostname";
	if (isset($_POST['hostname']) && strlen($_POST['hostname'])) {
        $values .= ",?";
        $bindList[] = $_POST['hostname'];
    } else
		$values .= ",NULL";
	$columns[] = "ipaddr";
	if (isset($_POST['ipaddr']) && strlen($_POST['ipaddr'])) {
        $values .= ",?";
        $bindList[] = $_POST['ipaddr'];
    } else
		$values .= ",NULL";
	$columns[] = "port";
	if (isset($_POST['port']) && strlen($_POST['port'])) {
        $values .= ",?";
		$bindList[] = $_POST['port'];
    } else
		$values .= ",NULL";
    // Dicom TLS option
    if (isset($_POST['tlsoption'])) {
	    $columns[] = "tlsoption";
        $values .= ",?";
		$bindList[] = $_POST['tlsoption'];
    }
	$columns[] = "allowaccess";
    $enable = 0;
	if (isset($_POST['access'])) {
        if (strcasecmp($_POST['access'], pacsone_gettext("Enable")) == 0)
		    $enable = 1;
    }
	$values .= ",$enable";
    // access control for Dicom commands
    $mask = 0;
	if (isset($_POST['privilege'])) {
        $privilege = $_POST['privilege'];
        foreach ($privilege as $bit)
            $mask += $bit;
    }
    $columns[] = "privilege";
    $values .= ",$mask";
    // AE filters
    foreach ($DICOM_CMDACCESS_TBL as $priv => $cmd) {
        if (($mask & $priv) == 0)
            continue;
        foreach ($DICOM_CMDFILTER_TBL as $tag => $name) {
            $filtername = $cmd . "-" . $tag;
            if (isset($_POST["$filtername"]) && strlen($_POST["$filtername"])) {
                $tokens = explode(";", $_POST["$filtername"]);
                foreach ($tokens as $pattern) {
                    $pattern = trim($pattern);
                    $subq = "insert into aefilter values(?,?,?,?,1)";
                    $subList = array($cmd, $title, $tag, $pattern);
                    if (!$dbcon->preparedStmt($subq, $subList)) {
                        print "<h3><font color=red>";
                        print pacsone_gettext("Error adding AE Command Filter: ");
                        print $dbcon->getError();
                        print "</font></h3><p>\n";
                        exit();
                    }
                }
            }
        }
    }
	$columns[] = "archivedir";
	if (isset($_POST['archivedir']) && strlen($_POST['archivedir'])) {
		$archivedir = cleanPostPath($_POST['archivedir']);
        if (!file_exists($archivedir)) {
            print "<h2><font color=red>";
            printf(pacsone_gettext("Archive Directory %s Does Not Exist!"), $archivedir);
            print "</font></h2>";
            exit();
        }
        // change to Unix-style path
        $archivedir = str_replace("\\", "/", $archivedir);
        $values .= ",?";
		$bindList[] = $archivedir;
	}
	else
		$values .= ",NULL";
	if (isset($_POST['archiveformat'])) {
        $columns[] = "archiveformat";
        $values .= ",?";
        $bindList[] = $_POST['archiveformat'];
	}
	$columns[] = "longtermdir";
	if (isset($_POST['longtermdir']) && strlen($_POST['longtermdir'])) {
		$longtermdir = cleanPostPath($_POST['longtermdir']);
        if (!file_exists($longtermdir)) {
            print "<h2><font color=red>";
            printf(pacsone_gettext("Archive Directory %s Does Not Exist!"), $longtermdir);
            print "</font></h2>";
            exit();
        }
        // change to Unix-style path
        $longtermdir = str_replace("\\", "/", $longtermdir);
        $values .= ",?";
		$bindList[] = $longtermdir;
	}
	else
		$values .= ",NULL";
	$archiveage = $_POST['archiveage'];
    if ($archiveage) {
        $archiveage = $_POST['age'];
	}
    $columns[] = "archiveage";
    $values .= ",?";
    $bindList[] = $archiveage;
	if (isset($_POST['maxsessions'])) {
        $columns[] = "maxsessions";
        $values .= ",?";
        $bindList[] = $_POST['maxsessions'];
	}
	if (isset($_POST['xfersyntax'])) {
        $tokens = explode(" - ", $_POST['xfersyntax']);
        $syntax = trim($tokens[1]);
        if (!array_key_exists($syntax, $XFER_SYNTAX_TBL))
            $syntax = "";
        $columns[] = "xfersyntax";
        $values .= ",?";
        $bindList[] = $syntax;
        // configure lossy compression ratio and quality for lossy compressions
        if (!strcmp($syntax, "1.2.840.10008.1.2.4.91") ||
            !strcmp($syntax, "1.2.840.10008.1.2.4.81") ||
            !strcmp($syntax, "1.2.840.10008.1.2.4.50") ||
            !strcmp($syntax, "1.2.840.10008.1.2.4.51") ) {
            $ratio = strlen($_POST['txlossyratio'])? $_POST['txlossyratio'] : 0;
            if ($ratio) {
                $columns[] = "txlossyratio";
                $values .= ",?";
                $bindList[] = $ratio;
            }
            $quality = strlen($_POST['txlossyquality'])? $_POST['txlossyquality'] : 0;
            if ($quality) {
                $columns[] = "txlossyquality";
                $values .= ",?";
                $bindList[] = $quality;
            }
        }
	}
	if (isset($_POST['xfersyntaxrx'])) {
        $tokens = explode(" - ", $_POST['xfersyntaxrx']);
        $syntax = (count($tokens) > 1)? trim($tokens[1]) : "";
        if (!array_key_exists($syntax, $XFER_SYNTAX_TBL))
            $syntax = "";
        $columns[] = "xfersyntaxrx";
        $values .= ",?";
        $bindList[] = $syntax;
	}
	$multiplectx = $_POST['multiplectx'];
    $columns[] = "multiplectx";
    $values .= ",?";
    $bindList[] = $multiplectx;
    if (isset($_POST['appltype'])) {
        $appltype = $_POST['appltype'];
        foreach ($appltype as $type) {
            $columns[] = "$type";
            $values .= ",1";
            if (strcasecmp($type, "printScp") == 0) {
                $printerType = $_POST['printerType'];
                $columns[] = "printerType";
                $values .= ",?";
                $bindList[] = $printerType;
            } else if (strcasecmp($type, "queryScp") == 0) {
                $sync = $_POST['sync'];
                if ($sync) {
                    $schedule = $_POST['schedule'];
                    if (count($schedule) == 0)
                        die("<h3><font color=red>A schedule needs to be specified to synchronize remote studies.</font></h3><p>");
                    $syncBy = $_POST['syncBy'];
                    $overwrite = isset($_POST['overwrite'])? 1 : 0;
                    $twoway = isset($_POST['2waysync'])? 1 : 0;
                    $priority = ($twoway << 8) + $overwrite;
                    foreach ($schedule as $hour) {
                        $sync = "insert into cronjob (username,aetitle,type,class,schedule,priority,uuid) VALUES(";
                        $sync .= "?,?,'cron','sync',$hour,$priority,";
                        $subList = array($username, $title); 
                        if ($syncBy == 1) {   // sync by study date
                            $sync .= "?)";
                            $subList[] = "Date|" . $_POST['syncDay'];
                        } else {
                            $sync .= "'')";
                        }
                        if (!$dbcon->preparedStmt($sync, $subList)) {
                            $result .= "<h3><font color=red>";
                            $result .= pacsone_gettext("Error adding remote sync cronjob: ");
                            $result .= $dbcon->getError();
                            $result .= "</font></h3><p>\n";
                        }
                    }
                }
            } else if (strcasecmp($type, "commitScp") == 0) {
                $columns[] = "reqcommitment";
                $values .= ",?";
                $bindList[] = $_POST['reqcommit'];
            }
        }
    }
	$notifynewstudy = $_POST['notifynewstudy'];
    $columns[] = "notifynewstudy";
    $values .= ",?";
    $bindList[] = $notifynewstudy;
	$priority = $_POST['priority'];
    $columns[] = "priority";
    $values .= ",?";
    $bindList[] = $priority;
    global $COMPRESS_RX_IMAGE_TBL;
	$rxcompression = $_POST['rxcompression'];
    $value = array_search($rxcompression, $COMPRESS_RX_IMAGE_TBL);
    if ($value !== false) {
        $columns[] = "rxcompression";
        $values .= ",?";
        $bindList[] = $value;
        $ratio = strlen($_POST['lossyratio'])? $_POST['lossyratio'] : 0;
        if ($ratio) {
            $columns[] = "lossyratio";
            $values .= ",?";
            $bindList[] = $ratio;
        }
        $quality = strlen($_POST['lossyquality'])? $_POST['lossyquality'] : 0;
        if (($value == 5 || (($value == 2) && !$ratio)) && $quality) {
            $columns[] = "lossyquality";
            $values .= ",?";
            $bindList[] = $quality;
        }
    }
    if (isset($_POST['markstudy'])) {
        $value = $_POST['markstudy'];
        $columns[] = "markstudy";
        $values .= ",?";
        $bindList[] = $value;
    }
    if ($_POST['anonymize']) {
        $template = isset($_POST['template'])? $_POST['template'] : "";
        if (strlen($template)) {
            $columns[] = "anonymize";
            $values .= ",?";
            $bindList[] = $template;
        }
    }
    if ($_POST['anonymizetx']) {
        $template = isset($_POST['templatetx'])? $_POST['templatetx'] : "";
        if (strlen($template)) {
            $columns[] = "anonymizetx";
            $values .= ",?";
            $bindList[] = $template;
        }
    }
    if ($_POST['xscript']) {
        $template = isset($_POST['xstemplate'])? $_POST['xstemplate'] : "";
        if (strlen($template)) {
            $columns[] = "xscript";
            $values .= ",?";
            $bindList[] = $template;
        }
    }
    if (isset($_POST['aeassigned']) && $_POST['aeassigned']) {
        $webusers = $_POST['webusers'];
        foreach ($webusers as $entry) {
            $subq = "insert into aeassigneduser (aetitle,username,assigned) values(?,?,1)";
            $subList = array($title, $entry);
            if (!$dbcon->preparedStmt($subq, $subList)) {
                $result .= "<h3><font color=red>";
                $result .= sprintf(pacsone_gettext("Error running SQL query <u>%s</u>: "), $subq);
                $result .= $dbcon->getError();
                $result .= "</font></h3><p>\n";
            }
        }
    }
    if (isset($_POST['aegroup']) && $_POST['aegroup']) {
        $members = $_POST['memberaes'];
        foreach ($members as $entry) {
            $subq = "insert into aegroup (aetitle,memberae) values(?,?)";
            $subList = array($title, $entry);
            if (!$dbcon->preparedStmt($subq, $subList)) {
                $result .= "<h3><font color=red>";
                $result .= sprintf(pacsone_gettext("Error running SQL query <u>%s</u>: "), $subq);
                $result .= $dbcon->getError();
                $result .= "</font></h3><p>\n";
            }
        }
        if (count($members)) {
            $columns[] = "aegroup";
            $values .= ",1";
        }
    }
    if (isset($_POST['noupdate']) && $_POST['noupdate']) {
        $columns[] = "noupdate";
        $values .= ",1";
    }
    // whether or not to convert to Latin1 (ISO-8859-1) character set for DMWL query results
    if (isset($_POST['convert2latin1'])) {
        $columns[] = "convert2latin1";
        $yesno = $_POST['convert2latin1']? 1 : 0;
        $values .= ",$yesno";
    }
    // Instance Availability Notification (IAN) subscriptions
    if (isset($_POST['ianotify'])) {
        $columns[] = "ianotify";
        $ianotify = $_POST['ianotify'];
        $values .= ",$ianotify";
        if ($ianotify == 1 && isset($_POST['iansubscr'])) {
            foreach ($_POST['iansubscr'] as $source) {
                $subq = "insert into iansubscr (source,subscriber) values(?,?)";
                $subList = array($source, $title);
                if (!$dbcon->preparedStmt($subq, $subList)) {
                    $result .= "<h3><font color=red>";
                    $result .= sprintf(pacsone_gettext("Error running SQL query <u>%s</u>: "), $subq);
                    $result .= $dbcon->getError();
                    $result .= "</font></h3><p>\n";
                }
            }
        }
    }
    for ($i = 0; $i < count($columns); $i++){
        if ($i)
            $query .= ",";
        $query .= $columns[$i];
    }
    $query .= ") VALUES($values)";
    // execute SQL query
    if (!$dbcon->preparedStmt($query, $bindList)) {
        $result .= "<h3><font color=red>";
        $result .= sprintf(pacsone_gettext("Error adding Application Entity <u>%s</u>: "), $title);
        $result .= "<p>Query = $query<br>";
        $result .= $dbcon->getError();
        $result .= "</font></h3><p>\n";
    } else {
        // log activity to system journal
        $dbcon->logJournal($username, "Add", "AeTitle", $title);
    }
    return $result;
}

function modifyEntry(&$username)
{
    global $dbcon;
    global $XFER_SYNTAX_TBL;
    global $DICOM_CMDACCESS_TBL;
    global $DICOM_CMDFILTER_TBL;
    $result = "";
	$title = $_POST['title'];
	$query = "update applentity set ";
    $bindList = array();
	$description = $_POST['description'];
	if ($description) {
		$query .= "description=?,";
        $bindList[] = $description;
    }
	$hostname = $_POST['hostname'];
	if ($hostname) {
		$query .= "hostname=?,";
        $bindList[] = $hostname;
    }
	$ipaddr = $_POST['ipaddr'];
	if ($ipaddr) {
		$query .= "ipaddr=?,";
        $bindList[] = $ipaddr;
    } else
        $query .= "ipaddr=NULL,";
	$port = $_POST['port'];
	if ($port) {
		$query .= "port=?,";
        $bindList[] = $port;
    } else
        $query .= "port=NULL,";
    // Dicom TLS option
    if (isset($_POST['tlsoption']))
        $query .= sprintf("tlsoption=%d,", $_POST['tlsoption']);
	$access = $_POST['access'];
	if ($access) {
        if (strcasecmp($access, pacsone_gettext("Enable")) == 0)
		    $query .= "allowaccess=1,";
        else if (strcasecmp($access, pacsone_gettext("Disable")) == 0)
		    $query .= "allowaccess=0,";
    }
    else
        $query .= "allowaccess=NULL,";
    // access control for Dicom commands
    $mask = 0;
	if (isset($_POST['privilege'])) {
	    $privilege = $_POST['privilege'];
        foreach ($privilege as $bit)
            $mask += $bit;
    }
    $query .= "privilege=$mask,";
    // AE filters
    foreach ($DICOM_CMDACCESS_TBL as $priv => $cmd) {
        foreach ($DICOM_CMDFILTER_TBL as $tag => $name) {
            $filtername = $cmd . "-" . $tag;
            if (isset($_POST["$filtername"])) {
                // delete any existing filter first
                $subq = "delete from aefilter where command=? and sourceae=? and tag=?";
                $subList = array($cmd, $title, $tag);
                if (!$dbcon->preparedStmt($subq, $subList)) {
                    print "<h3><font color=red>";
                    print pacsone_gettext("Error deleting AE Command Filter: ");
                    print $dbcon->getError();
                    print "</font></h3><p>\n";
                    exit();
                }
                if (($mask & $priv) == 0)
                    continue;
                $tokens = trim($_POST["$filtername"]);
                if (strlen($tokens) == 0)
                    continue;
                $tokens = explode(";", $tokens);
                foreach ($tokens as $pattern) {
                    $pattern = trim($pattern);
                    $subq = "insert into aefilter values(?,?,?,?,1)";
                    $subList = array($cmd, $title, $tag, $pattern);
                    if (!$dbcon->preparedStmt($subq, $subList)) {
                        print "<h3><font color=red>";
                        print pacsone_gettext("Error modifying AE Command Filter: ");
                        print $dbcon->getError();
                        print "</font></h3><p>\n";
                        exit();
                    }
                }
            }
        }
    }
	$archivedir = cleanPostPath($_POST['archivedir']);
	if ($archivedir) {
        if (!file_exists($archivedir)) {
            print "<h2><font color=red>";
            printf(pacsone_gettext("Archive Directory %s Does Not Exist!"), $archivedir);
            print "</font></h2>";
            exit();
        }
        // change to Unix-style path
        $archivedir = str_replace("\\", "/", $archivedir);
		$query .= "archivedir=?,";
        $bindList[] = $archivedir;
	}
    else
        $query .= "archivedir=NULL,";
	if (isset($_POST['archiveformat'])) {
		$query .= "archiveformat=?,";
        $bindList[] = $_POST['archiveformat'];
	}
	$longtermdir = cleanPostPath($_POST['longtermdir']);
	if ($longtermdir) {
        if (!file_exists($longtermdir)) {
            print "<h2><font color=red>";
            printf(pacsone_gettext("Archive Directory %s Does Not Exist!"), $longtermdir);
            print "</font></h2>";
            exit();
        }
        // change to Unix-style path
        $longtermdir = str_replace("\\", "/", $longtermdir);
		$query .= "longtermdir=?,";
        $bindList[] = $longtermdir;
	}
    else
        $query .= "longtermdir=NULL,";
    $archiveage = $_POST['archiveage'];
    if ($archiveage) {
        $archiveage = $_POST['age'];
	}
	$query .= "archiveage=?,";
    $bindList[] = $archiveage;
	if (isset($_POST['maxsessions'])) {
		$query .= "maxsessions=?,";
        $bindList[] = $_POST['maxsessions'];
	}
	if (isset($_POST['xfersyntax'])) {
        $tokens = explode(" - ", $_POST['xfersyntax']);
        $syntax = trim($tokens[1]);
        if (!array_key_exists($syntax, $XFER_SYNTAX_TBL))
            $syntax = "";
		$query .= "xfersyntax=?,";
        $bindList[] = $syntax;
        // configure lossy compression ratio and quality for lossy compressions
        if (!strcmp($syntax, "1.2.840.10008.1.2.4.91") ||
            !strcmp($syntax, "1.2.840.10008.1.2.4.81") ||
            !strcmp($syntax, "1.2.840.10008.1.2.4.50") ||
            !strcmp($syntax, "1.2.840.10008.1.2.4.51") ) {
            $ratio = strlen($_POST['txlossyratio'])? $_POST['txlossyratio'] : 0;
            if ($ratio) {
                $query .= "txlossyratio=?,";
                $bindList[] = $ratio;
            }
            $quality = strlen($_POST['txlossyquality'])? $_POST['txlossyquality'] : 0;
            if ($quality) {
                $query .= "txlossyquality=?,";
                $bindList[] = $quality;
            }
        } else {
            $query .= "txlossyratio=0,txlossyquality=0,";
        }
	}
	if (isset($_POST['xfersyntaxrx'])) {
        $tokens = explode(" - ", $_POST['xfersyntaxrx']);
        $syntax = (count($tokens) > 1)? trim($tokens[1]) : "";
        if (!array_key_exists($syntax, $XFER_SYNTAX_TBL))
            $syntax = "";
		$query .= "xfersyntaxrx=?,";
        $bindList[] = $syntax;
    }
	$multiplectx = $_POST['multiplectx'];
	$query .= "multiplectx=?,";
    $bindList[] = $multiplectx;
    $appltype = array("queryScp" => 0, "worklistScp" => 0, "printScp" => 0, "commitScp" => 0);
    if (isset($_POST['appltype'])) {
        $selected = $_POST['appltype'];
        foreach ($selected as $type) {
            $appltype[$type] = 1;
        }
    }
    foreach ($appltype as $type => $value) {
        $query .= "$type=$value,";
        if (strcasecmp($type, "printScp") == 0) {
            $query .= "printerType=?,";
            $bindList[] = $_POST['printerType'];
        } else if (strcasecmp($type, "queryScp") == 0) {
            // remote existing entries first
            $sync = "delete from cronjob where aetitle=? and class='sync'";
            $subList = array($title);
            $dbcon->preparedStmt($sync, $subList);
            // add back any modifications
            $sync = $_POST['sync'];
            if ($value && $sync) {
                $schedule = $_POST['schedule'];
                if (count($schedule) == 0)
                    die("<h3><font color=red>A schedule needs to be specified to synchronize remote studies.</font></h3><p>");
                $syncBy = $_POST['syncBy'];
                $overwrite = isset($_POST['overwrite'])? 1 : 0;
                $twoway = isset($_POST['2waysync'])? 1 : 0;
                $priority = ($twoway << 8) + $overwrite;
                foreach ($schedule as $hour) {
                    $sync = "insert into cronjob (username,aetitle,type,class,schedule,priority,uuid) values(";
                    $sync .= "?,?,'cron','sync',$hour,$priority,";
                    $subList = array($username, $title);
                    if ($syncBy == 1) {   // sync by study date
                        $sync .= "?";
                        $subList[] = "Date|" . $_POST['syncDay'];
                    } else {
                        $sync .= "''";
                    }
                    $sync .= ")";
                    if (!$dbcon->preparedStmt($sync, $subList)) {
                        print "Query = [$sync]<br>";
                        print_r($subList);
                        $result .= "<h3><font color=red>";
                        print pacsone_gettext("Error updating remote sync cronjob: ");
                        $result .= $dbcon->getError();
                        $result .= "</font></h3><p>\n";
                    }
                }
            }
        } else if (strcasecmp($type, "commitScp") == 0) {
            $reqcommit = ($value == 0)? 0 : $_POST['reqcommit'];
            $query .= "reqcommitment=?,";
            $bindList[] = $reqcommit;
        }
    }
	$query .= "notifynewstudy=?,";
    $bindList[] = $_POST['notifynewstudy'];
	$query .= "priority=?,";
    $bindList[] = $_POST['priority'];
    global $COMPRESS_RX_IMAGE_TBL;
	$rxcompression = $_POST['rxcompression'];
    $value = array_search($rxcompression, $COMPRESS_RX_IMAGE_TBL);
    if ($value !== false) {
	    $query .= "rxcompression=?,";
        $bindList[] = $value;
        $ratio = 0;
        if (($value == 5) && strlen($_POST['lossyratio']))
            $ratio = $_POST['lossyratio'];
        $query .= "lossyratio=?,";
        $bindList[] = $ratio;
        $quality = 0;
        if (((!$ratio && ($value == 2)) || ($value == 5)) &&
            strlen($_POST['lossyquality']))
            $quality = $_POST['lossyquality'];
        $query .= "lossyquality=?,";
        $bindList[] = $quality;
    }
    if (isset($_POST['markstudy'])) {
        $value = $_POST['markstudy'];
        $query .= "markstudy=?,";
        $bindList[] = $value;
    }
    $value = "";
    if ($_POST['anonymize']) {
        $template = isset($_POST['template'])? $_POST['template'] : "";
        if (strlen($template))
            $value = $template;
    }
    if (strlen($value)) {
        $query .= "anonymize=?,";
        $bindList[] = $value;
    } else
        $query .= "anonymize=NULL,";
    $value = "";
    if ($_POST['anonymizetx']) {
        $template = isset($_POST['templatetx'])? $_POST['templatetx'] : "";
        if (strlen($template))
            $value = $template;
    }
    if (strlen($value)) {
        $query .= "anonymizetx=?,";
        $bindList[] = $value;
    } else
        $query .= "anonymizetx=NULL,";
    $value = "";
    if ($_POST['xscript']) {
        $template = isset($_POST['xstemplate'])? $_POST['xstemplate'] : "";
        if (strlen($template))
            $value = $template;
    }
    if (strlen($value)) {
        $query .= "xscript=?,";
        $bindList[] = $value;
    } else
        $query .= "xscript=NULL,";
    if (isset($_POST['aeassigned'])) {
        if ($_POST['aeassigned']) {
            // remove all existing assignments
            $subList = array($title);
            $dbcon->preparedStmt("delete from aeassigneduser where aetitle=?", $subList);
            // add new assignments
            $webusers = $_POST['webusers'];
            foreach ($webusers as $entry) {
                $subq = "insert into aeassigneduser (aetitle,username,assigned) values(?,?,1)";
                $subList = array($title, $entry);
                if (!$dbcon->preparedStmt($subq, $subList)) {
                    $result .= "<h3><font color=red>";
                    $result .= sprintf(pacsone_gettext("Error running SQL query <u>%s</u>: "), $subq);
                    $result .= $dbcon->getError();
                    $result .= "</font></h3><p>\n";
                }
            }
        } else {
            // remove all existing assignments
            $subList = array($title);
            $dbcon->preparedStmt("delete from aeassigneduser where aetitle=?", $subList);
        }
    }
    // remove current member assignment for this group
    $subList = array($title);
    $dbcon->preparedStmt("delete from aegroup where aetitle=?", $subList);
    if (isset($_POST['memberaes'])) {
        // add new member assignments
        $members = $_POST['memberaes'];
        foreach ($members as $entry) {
            $subq = "insert into aegroup (aetitle,memberae) values(?,?)";
            $subList = array($title, $entry);
            if (!$dbcon->preparedStmt($subq, $subList)) {
                $result .= "<h3><font color=red>";
                $result .= sprintf(pacsone_gettext("Error running SQL query <u>%s</u>: "), $subq);
                $result .= $dbcon->getError();
                $result .= "</font></h3><p>\n";
            }
        }
        $query .= "aegroup=1,";
    }
    // remove current group membership assignment for this ae
    $subList = array($title);
    $dbcon->preparedStmt("delete from aegroup where memberae=?", $subList);
    if (isset($_POST['groups'])) {
        // add new group assignments
        $groups = $_POST['groups'];
        foreach ($groups as $entry) {
            $subq = "insert into aegroup (aetitle,memberae) values(?,?)";
            $subList = array($entry, $title);
            if (!$dbcon->preparedStmt($subq, $subList)) {
                $result .= "<h3><font color=red>";
                $result .= sprintf(pacsone_gettext("Error running SQL query <u>%s</u>: "), $subq);
                $result .= $dbcon->getError();
                $result .= "</font></h3><p>\n";
            }
        }
        $query .= "aegroup=0,";
    }
    // whether or not to update database tables when receiving from this ae
    $doNotUpdate = (isset($_POST['noupdate']) && $_POST['noupdate'])? 1 : 0;
    $query .= "noupdate=$doNotUpdate,";
    // whether or not to convert to Latin1 (ISO-8859-1) character set for DMWL query results
    $yesno = (isset($_POST['convert2latin1']) && $_POST['convert2latin1'])? 1 : 0;
    $query .= "convert2latin1=$yesno,";
    // Instance Availability Notifcation (IAN) subscriptions
    $ianotify = isset($_POST['ianotify'])? $_POST['ianotify'] : 0;
    $query .= "ianotify=$ianotify,";
    $subList = array($title);
    $dbcon->preparedStmt("delete from iansubscr where subscriber=?", $subList);
    if ($ianotify == 1) {
        $iansubscr = $_POST['iansubscr'];
        foreach ($iansubscr as $source) {
            $subq = "insert into iansubscr (source,subscriber) values(?,?)";
            $subList = array($source, $title);
            if (!$dbcon->preparedStmt($subq, $subList)) {
                $result .= "<h3><font color=red>";
                $result .= sprintf(pacsone_gettext("Error running SQL query <u>%s</u>: "), $subq);
                $result .= $dbcon->getError();
                $result .= "</font></h3><p>\n";
            }
        }
    }
    // get rid of the last ','
    $npos = strrpos($query, ",");
    if ($npos != false)
        $query = substr($query, 0, $npos);
    $query .= " where title=?";
    $bindList[] = $title;
    // execute SQL query
    if (!$dbcon->preparedStmt($query, $bindList)) {
        $result .= "<h3><font color=red>";
        printf(pacsone_gettext("Error updating Application Entity <u>%s</u>: "), $title);
        $result .= $dbcon->getError();
        $result .= "</font></h3><p>\n";
    } else {
        // log activity to system journal
        $dbcon->logJournal($username, "Modify", "AeTitle", $title);
    }
    return $result;
}

function addEntryForm()
{
    include_once 'checkUncheck.js';
    include_once 'jpegLossy.js';
    global $PRODUCT;
    global $PRINTER_TBL;
    global $XFER_SYNTAX_TBL;
    global $PREFERRED_TBL;
    global $dbcon;
    // display Add New Application Entity form
    print "<html>\n";
    print "<head><title>$PRODUCT - ";
    print pacsone_gettext("Add Application Entity");
    print "</title></head>\n";
    print "<body>\n";
    require_once 'header.php';
    print "<form method='POST' action='modifyAeTitle.php'>\n";
    print "<input type='hidden' name='actionvalue'>\n";
    print "<p><table border=1 cellpadding=2 cellspacing=0>\n";
    print "<tr><td>";
    print pacsone_gettext("Enter Application Entity Title:") . "</td>\n";
    print "<td><input type='text' size=16 maxlength=16 name='title'></td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Enter Description of Application Entity:") . "</td>\n";
    print "<td><input type='text' size=16 maxlength=64 name='description'></td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Enter Hostname:") . "</td>\n";
    print "<td><input type='text' size=20 maxlength=20 name='hostname'></td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Enter IP Address:") . "</td>\n";
    print "<td><input type='text' size=20 maxlength=20 name='ipaddr'></td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Enter Port Number:") . "</td>\n";
    print "<td><input type='text' size=10 maxlength=10 name='port'></td></tr>\n";
    // Dicom TLS Security option
    print "<tr><td>";
    print pacsone_gettext("Dicom TLS Security Option:") . "</td>\n";
    print "<td><input type='radio' name='tlsoption' value=0 checked>";
    print pacsone_gettext("Disabled") . "<br>";
    print "<input type='radio' name='tlsoption' value=1>";
    print pacsone_gettext("Enabled") . "<br></td></tr>";
    print "<tr><td>";
    print pacsone_gettext("Enter Database Access:") . "</td>\n";
    print "<td><select name='access'><option>";
    print pacsone_gettext("Enable");
    print "<option>" . pacsone_gettext("Disable") . "</select></td></tr>\n";
    print "<tr><td>";
    // access control for Dicom commands
    print pacsone_gettext("Allow Dicom Commands From This AE:") . "</td>\n";
    print "<td><table border=0 width=100% cellpadding=5 cellspacing=1>";
    $columns = array(
        pacsone_gettext("Command"),
        pacsone_gettext("Enable"),
        pacsone_gettext("Institution Name Filters"),
        pacsone_gettext("Referring Physician Filters"),
        pacsone_gettext("Reading Physician Filters"),
    );
    global $BGCOLOR;
    print "<tr class='tableHeadForBGUp'>\n";
    foreach ($columns as $cmd)
        print "<td><b>$cmd</td></b>";
    print "</tr>\n";
    global $DICOM_CMDACCESS_TBL;
    global $DICOM_CMDFILTER_TBL;
    foreach ($DICOM_CMDACCESS_TBL as $priv => $cmd) {
        print "<tr ALIGN='center'>";
        print "<td>$cmd</td>";
        print "<td><input type=checkbox name='privilege[]' value='$priv' checked></input></td>";
        foreach ($DICOM_CMDFILTER_TBL as $tag => $name) {
            $filtername = $cmd . "-" . $tag;
            print "<td><textarea name='$filtername' wrap='soft' cols='17' rows='3'></textarea></td>";
        }
        print "</tr>";
    }
    print "</table></td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Enter Short-Term Archive Directory:") . "</td>\n";
    print "<td><input type='text' size=32 maxlength=255 name='archivedir'></td></tr>\n";
    global $ARCHIVE_DIR_FORMAT_FLAT;
    global $ARCHIVE_DIR_FORMAT_HIERARCHY;
    global $ARCHIVE_DIR_FORMAT_STUDYUID;
    global $ARCHIVE_DIR_FORMAT_COMBO;
    global $ARCHIVE_DIR_FORMAT_PID_STUDYDATE;
    print "<tr><td>";
    print pacsone_gettext("Enter Archive Directory Format:") . "</td><td>\n";
    print "<input type='radio' name='archiveformat' value=$ARCHIVE_DIR_FORMAT_FLAT checked><b>";
    print pacsone_gettext("Flat") . "</b> ";
    print pacsone_gettext("(Received images are stored under <b>&lt;AssignedDirectory&gt;/YYYY-MM-DD-WEEKDAY/</b> sub-folders)<br>\n");
    print "<input type='radio' name='archiveformat' value=$ARCHIVE_DIR_FORMAT_HIERARCHY><b>";
    print pacsone_gettext("Hierarchical") . "</b> ";
    print pacsone_gettext("(Received images are stored under <b>&lt;AssignedDirectory&gt;/YYYY/MM/DD/</b> sub-folders)<br>\n");
    print "<input type='radio' name='archiveformat' value=$ARCHIVE_DIR_FORMAT_STUDYUID><b>";
    print pacsone_gettext("Study Instance UID") . "</b> ";
    print pacsone_gettext("(Received images are stored under <b>&lt;AssignedDirectory&gt;/\$StudyInstanceUid/</b> sub-folders)<br>\n");
    print "<input type='radio' name='archiveformat' value=$ARCHIVE_DIR_FORMAT_COMBO><b>";
    print pacsone_gettext("Combination") . "</b> ";
    print pacsone_gettext("(Received images are stored under <b>&lt;AssignedDirectory&gt;/YYYY-MM-DD-WEEKDAY/\$StudyInstanceUid/</b> sub-folders)<br>\n");
    print "<input type='radio' name='archiveformat' value=$ARCHIVE_DIR_FORMAT_PID_STUDYDATE><b>";
    print pacsone_gettext("Patient ID/Study Date") . "</b> ";
    print pacsone_gettext("(Received images are stored under <b>&lt;AssignedDirectory&gt;/\$PatientID/\$StudyDate/</b> sub-folders)<br>\n");
    print "</td></tr>\n";
    print "<tr><td>" . pacsone_gettext("Enter Long-Term Archive Directory:") . "</td><td>\n";
    print "<input type='text' size=32 maxlength=255 name='longtermdir'>";
    print "</td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Automatically Age From Short-Term Archive Directory To Long-Term Archive Directory:") . "</td><td>";
    print "<input type='radio' name='archiveage' value=0 checked>";
    print pacsone_gettext("Disabled") . "<br>";
    print "<input type='radio' name='archiveage' value=1>";
    print pacsone_gettext("Age Images Received More Than <input type='text' name='age' size=6 maxlength=6> Days Ago");
    print "</td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Enter Maximum Number of Simultaneous Connections:") . "</td>\n";
    print "<td><input type='text' size=10 maxlength=10 name='maxsessions' value=10></td></tr>\n";
    print "<tr><td>";
    printf(pacsone_gettext("Enter Preferred Transfer Syntax When %s Send Images to This AE:"), $PRODUCT);
    print "</td>\n";
    print "<td><select id='txsyntax' name='xfersyntax'>";
    $selected = pacsone_gettext("None - Use Original Transfer Syntax of Received Images");
    $preferred = array(
        $selected               => "selected",
    );
    foreach ($PREFERRED_TBL as $syntax)
        $preferred[$syntax] = "";
    foreach ($preferred as $key => $selected) {
        if (strstr($key, "."))
            $name = $XFER_SYNTAX_TBL[$key][2] . " - $key";
        else
            $name =$key;
        print "<option $selected>$name</option>";
    }
    print "</select>\n";
    // configure compression ratio/quality for Lossy compressions
    print "<p><div id=\"lossyquality\" style=\"display:none\"><ul>";
    print "<div id=\"lossyratio\" style=\"display:none\"><li>";
    print pacsone_gettext("Compression ratio, e.g., 20 (for 20:1), 10 (for 10:1), 5 (for 5:1), etc.:");
    print "&nbsp;<input type='text' name='txlossyratio' value='5' size=3 maxlength=3>";
    print "</li></div>";
    print "<li>";
    print pacsone_gettext("Compressed image quality, e.g., 90 (for 90%), 80 (for 80%), etc.: ");
    print "&nbsp;<input type='text' name='txlossyquality' value='90' size=3 maxlength=3>";
    print "</UL></div>";
    print "</td></tr>\n";
    // propose a separate Presentation Context for each transfer syntax
    print "<tr><td>";
    print pacsone_gettext("Propose A Separate Dicom Presentation Context for Each Transfer Syntax:");
    print "</td><td>";
    print "<input type=radio name='multiplectx' value=0 checked>" . pacsone_gettext("No");
    print "<br><input type=radio name='multiplectx' value=1>" . pacsone_gettext("Yes");
    print "</td></tr>\n";
    // preferred transfer syntax when receiving images from this AE
    print "<tr><td>";
    printf(pacsone_gettext("Enter Preferred Transfer Syntax When %s Receive Images from This AE:"), $PRODUCT);
    print "</td>\n";
    print "<td><select name='xfersyntaxrx'>";
    $selected = pacsone_gettext("None");
    $preferred = array(
        $selected               => "selected",
    );
    foreach ($PREFERRED_TBL as $syntax)
        $preferred[$syntax] = "";
    foreach ($preferred as $key => $selected) {
        if (strstr($key, "."))
            $name = $XFER_SYNTAX_TBL[$key][2] . " - $key";
        else
            $name =$key;
        print "<option $selected>$name</option>";
    }
    print "</select></td></tr>\n";
    print "<tr><td colspan=2>";
    print pacsone_gettext("Enter Application Type:") . "<p>";
    print "<tr><td>";
    print "<input type=checkbox name='appltype[]' value='queryScp'>";
    print pacsone_gettext("Query/Retrieve SCP Server");
    print "</td>";
    print "<td><input type=radio name='sync' value=0 checked>";
    print pacsone_gettext("Do not synchronize remote studies") . "<br>";
    print "<input type=radio name='sync' value=1>";
    print pacsone_gettext("Synchronize remote studies based on the following 24-hour schedule:");
    print "<dl><dt></dt><dd>";
    for ($i = 0; $i < 24; $i++) {
        $value = ($i % 12)? ($i % 12) : 12;
        $value .= ($i < 12)? " am" : " pm";
        print "<input type=checkbox name='schedule[]' value=$i>$value";
        if ($i == 11) print "<br>";
    }
    print "</dd><br>";
    print "<dt></dt><dd>";
    print "<input type=radio name='syncBy' value=0 checked>";
    print pacsone_gettext("Synchronize all remote studies") . "<br>";
    print "</dd>";
    print "<dt></dt><dd>";
    print "<input type=radio name='syncBy' value=1>";
    print pacsone_gettext("Synchronize remote studies performed in the last <input type=text name='syncDay' size=2 maxlength=2 value=1> day(s)");
    print "</dd><br>";
    print "<dt></dt><dd>";
    print "<input type=checkbox name='overwrite' unchecked>";
    print pacsone_gettext("Update existing studies if already exist");
    print "</dd><dd>";
    print "<input type=checkbox name='2waysync' unchecked>";
    print pacsone_gettext("2-Way Synchronization (push local studies to remote AE)");
    print "</dd>";
    print "</dl></td></tr>";
    print "<tr><td>";
    print "<input type=checkbox name='appltype[]' value='worklistScp'>";
    print pacsone_gettext("Modality Worklist SCP Server");
    print "</td><td>&nbsp;</td></tr>";
    print "<tr><td>";
    print "<input type=checkbox name='appltype[]' value='printScp'>";
    print pacsone_gettext("Print SCP Server") . "</td>";
    print "<td>" . pacsone_gettext("Printer Type: ") . "<select name='printerType'>";
    foreach ($PRINTER_TBL as $printer) {
        $selected = strcasecmp($printer, "Default")? "" : "selected";
        print "<option $selected>$printer</option>";
    }
    print "</select></td></tr>\n";
    print "<tr><td>";
    print "<input type=checkbox name='appltype[]' value='commitScp'>";
    print pacsone_gettext("Storage Commitment SCP Server - Request Storage Commitment Report for Dicom images sent to this SCP");
    print "</td><td>";
    print "<input type=radio name='reqcommit' value=0 checked>" . pacsone_gettext("No");
    print "<br><input type=radio name='reqcommit' value=1>" . pacsone_gettext("Yes");
    print "</td></tr>";
    print "<tr><td>";
    print pacsone_gettext("Send Email Notifications to Users Registered as the Referring Physician When New Study From this AE is received");
    print "</td><td>";
    print "<input type=radio name='notifynewstudy' value=0 checked>" . pacsone_gettext("No");
    print "<br><input type=radio name='notifynewstudy' value=1>" . pacsone_gettext("Yes");
    print "</td></tr>";
    print "<tr><td>";
    print pacsone_gettext("Job queue priority when processing database jobs with this destination AE (default priority is 0 - Normal. Database jobs with higher priority destination AEs will be processed before those jobs with lower priority destination AEs)");
    print "</td><td>";
    print "<input type='text' name='priority' size=2 maxlength=2 value=0>";
    print "</td></tr>";
    // compression setting for images received from this AE
    print "<tr><td>";
    print pacsone_gettext("Compress images received from this AE");
    print "</td><td>";
    print "<UL><li>";
    print pacsone_gettext("Select Dicom Compression Transfer Syntax: ") . "<select name='rxcompression'>";
    global $COMPRESS_RX_IMAGE_TBL;
    foreach ($COMPRESS_RX_IMAGE_TBL as $key => $syntax) {
        $selected = $key? "" : "selected";
        print "<option $selected>$syntax</option>";
    }
    print "</select>";
    print "<li>";
    print pacsone_gettext("Compression ratio if JPEG2000 Lossy transfer syntax is selected, e.g., 20 (for 20:1), 10 (for 10:1), 5 (for 5:1), etc.:");
    print "<br><input type='text' name='lossyratio' size=3 maxlength=3>";
    print "<li>";
    print pacsone_gettext("Compressed image quality if either JPEG Lossy or JPEG2000 Lossy transfer syntax is selected, e.g., 90 (for 90%), 80 (for 80%), etc.: ");
    print "<br><input type='text' name='lossyquality' size=3 maxlength=3>";
    print "</UL></td></tr>\n";
    // mark study as Read if moved to this destination AE
    print "<tr><td>";
    print pacsone_gettext("Mark studies as <b>Read</b> by this AE Title after they have been retrieved/moved to this destination AE");
    print "</td><td>";
    print "<input type=radio name='markstudy' value=0 checked>" . pacsone_gettext("No");
    print "<br><input type=radio name='markstudy' value=1>" . pacsone_gettext("Yes");
    print "</td></tr>";
    // anonymize received study from defined anonymization template
    print "<tr><td>";
    print pacsone_gettext("Anonymize Studies Received From this AE according to a pre-defined template");
    print "</td><td>";
    print "<input type=radio name='anonymize' value=0 checked>" . pacsone_gettext("Disabled");
    print "<br><input type=radio name='anonymize' value=1>" . pacsone_gettext("Use This Template:");
    $choices = array();
    $anonymize = $dbcon->query("select distinct templname from anonymity");
    while ($anonymize && ($templname = $anonymize->fetchColumn()))
        $choices[] = $templname;
    if (count($choices) == 0) {
        $url = "tools.php?page=" . urlencode(pacsone_gettext("Anonymization Templates"));
        print "&nbsp;<a href=\"$url\">Add Anonymization Template</a>";
    } else {
        print "&nbsp;&nbsp;<select name='template'>";
        $index = 0;
        foreach ($choices as $name) {
            $selected = $index? "" : "selected";
            print "<option $selected>$name</option>";
            $index++;
        }
        print "</select>";
    }
    print "</td></tr>";
    // anonymize study by anonymization template when sending to this destination AE
    print "<tr><td>";
    print pacsone_gettext("Anonymize Studies when Sending to this AE according to a pre-defined template");
    print "</td><td>";
    print "<input type=radio name='anonymizetx' value=0 checked>" . pacsone_gettext("Disabled");
    print "<br><input type=radio name='anonymizetx' value=1>" . pacsone_gettext("Use This Template:");
    if (count($choices) == 0) {
        print "&nbsp;<a href=\"$url\">Add Anonymization Template</a>";
    } else {
        print "&nbsp;&nbsp;<select name='templatetx'>";
        $index = 0;
        foreach ($choices as $name) {
            $selected = $index? "" : "selected";
            print "<option $selected>$name</option>";
            $index++;
        }
        print "</select>";
    }
    print "</td></tr>";
    // whether or not to update database tables when receiving from this source AE
    print "<tr><td>";
    global $CUSTOMIZE_PATIENT;
    printf(pacsone_gettext("When Receiving Images from this Source AE Do Not Modify the <b>%s</b> and <b>Study</b> Tables"), $CUSTOMIZE_PATIENT);
    print "</td><td>";
    print "<input type=radio name='noupdate' value=0 checked>" . pacsone_gettext("No");
    print "<br><input type=radio name='noupdate' value=1>" . pacsone_gettext("Yes");
    print "</td></tr>";
    // settings that require System Administration privilege
    $username = $dbcon->username;
    if ($dbcon->hasaccess("admin", $username)) {
        if (stristr(getenv("OS"), "Windows")) {
            // assign transcription template for studies received from this source ae
            print "<tr><td>";
            print pacsone_gettext("Use Transcription Template for Dicom Studies Received From this AE");
            print "</td><td>";
            print "<input type=radio name='xscript' value=0 checked>" . pacsone_gettext("Disabled");
            print "<br><input type=radio name='xscript' value=1>" . pacsone_gettext("Use This Template:");
            $choices = array();
            $xscript = $dbcon->query("select name from xscriptemplate");
            while ($xscript && ($name = $xscript->fetchColumn()))
                $choices[] = $name;
            if (count($choices) == 0) {
                $url = "tools.php?page=" . urlencode(pacsone_gettext("Transcription Templates"));
                print "&nbsp;<a href=\"$url\">Add Transcription Template</a>";
            } else {
                print "&nbsp;&nbsp;<select name='xstemplate'>";
                $index = 0;
                foreach ($choices as $name) {
                    $selected = $index? "" : "selected";
                    print "<option $selected>$name</option>";
                    $index++;
                }
                print "</select>";
            }
            print "</td></tr>";
        }
        // AE group settings
        print "<tr><td>";
        print pacsone_gettext("AE Group Settings");
        print "</td><td>";
        print "<input type=radio name='aegroup' value=0 checked>" . pacsone_gettext("This entry is not an AE group");
        print "<br><input type=radio name='aegroup' value=1>" . pacsone_gettext("This entry is an AE group");
        print " - " . pacsone_gettext("Configure Group Members:");
        $allAes = array();
        $aes = $dbcon->query("select title from applentity where aegroup=0 order by title asc");
        while ($aes && ($title = $aes->fetchColumn()))
            $allAes[] = $title;
        print "<dl><dt></dt><dd>";
        foreach ($allAes as $entry)
            print "<input type=checkbox name='memberaes[]' value='$entry'>$entry";
        print "</dd><br>";
        print "<dt></dt><dd>";
        print "</td></tr>";
        // assign studies received from this AE to web users
        print "<tr><td>";
        print pacsone_gettext("Assign Dicom Studies Received From This AE To Web Users");
        print "</td><td>";
        print "<input type=radio name='aeassigned' value=0 checked>" . pacsone_gettext("Disabled");
        print "<br><input type=radio name='aeassigned' value=1>" . pacsone_gettext("Assign to the following web users:") . "</input><p>";
        $result = $dbcon->query("select username from privilege");
        if ($result && $result->rowCount()) {
            print "<dl><dt></dt><dd>";
            while ($webuser = $result->fetchColumn()) {
                if (!$dbcon->isAdministrator($webuser))
                    print "<input type=checkbox name='webusers[]' value='$webuser'>$webuser";
            }
            print "</dd><br>";
            print "<dt></dt><dd>";
        }
        print "</td></tr>";
        // convert to Latin1 (ISO-8859-1) character set for DMWL query results
        print "<tr><td>";
        print pacsone_gettext("Convert Person Names to Latin1 (ISO-8859-1) Character Set in Dicom Modality Worklist (DMWL) Query Results");
        print "</td><td>";
        print "<input type=radio name='convert2latin1' value=0 checked>" . pacsone_gettext("No");
        print "<br><input type=radio name='convert2latin1' value=1>" . pacsone_gettext("Yes");
        print "</td></tr>";
        // Instance Availability Notification (IAN) subscriptions
        print "<tr><td>";
        print pacsone_gettext("Subscribe to Instance Availability Notification (IAN) Messages");
        print "</td><td>";
        print "<input type=radio name='ianotify' value=0 checked>" . pacsone_gettext("Disabled");
        print "<br><input type=radio name='ianotify' value=-1>" . pacsone_gettext("Notify this AE when Dicom images are received from any source AE");
        print "<br><input type=radio name='ianotify' value=1>" . pacsone_gettext("Notify this AE when Dicom images are received from the following source AEs:");
        print "<dl><dt></dt><dd>";
        foreach ($allAes as $entry)
            print "<input type=checkbox name='iansubscr[]' value='$entry'>$entry";
        print "</dd><br>";
        print "<dt></dt><dd>";
        print "</td></tr>";
    }
    print "</table>\n";
    print "<p><input class='btn btn-primary' type='submit' name='action' value='";
    print pacsone_gettext("Add");
    print "' onclick='switchText(this.form,\"actionvalue\",\"Add\")'></form>\n";
	require_once 'footer.php';
    print "</body>\n";
    print "</html>\n";
}

function modifyEntryForm($title)
{
    include_once 'checkUncheck.js';
    include_once 'jpegLossy.js';
    global $PRODUCT;
    global $dbcon;
    global $PRINTER_TBL;
    global $XFER_SYNTAX_TBL;
    global $PREFERRED_TBL;
    // display Modify Application Entity form
    print "<html>\n";
    print "<head><title>$PRODUCT - ";
    print pacsone_gettext("Modify Application Entity");
    print "</title></head>\n";
    print "<body>\n";
    require_once 'header.php';
    $query = "select * from applentity where title=?";
    $bindList = array($title);
    $result = $dbcon->preparedStmt($query, $bindList);
    if ($result && $result->rowCount() == 1) {
        $row = $result->fetch(PDO::FETCH_ASSOC);
        print "<form method='POST' action='modifyAeTitle.php'>\n";
        print "<input type='hidden' name='actionvalue'>\n";
        print "<p><table border=1 cellpadding=2 cellspacing=0>\n";
        print "<tr><td>";
        print pacsone_gettext("Application Entity Title:") . "</td>\n";
        $data = $row['title'];
        $value = "<td><input type='text' size=16 maxlength=16 name='title' ";
        if (isset($data))
            $value .= "value='$data' ";
        $value .= "readonly></td>\n";
        print $value;
        print "</tr>\n";
        print "<tr><td>" . pacsone_gettext("Description:") . "</td>\n";
        $data = $row['description'];
        $value = "<td><input type='text' size=16 maxlength=64 name='description'";
        if (isset($data))
            $value .= "value='$data'";
        $value .= "></td>\n";
        print $value;
        print "</tr>\n";
        print "<tr><td>" . pacsone_gettext("Hostname:") . "</td>\n";
        $data = $row['hostname'];
        $value = "<td><input type='text' size=20 maxlength=20 name='hostname'";
        if (isset($data))
            $value .= "value='$data'";
        $value .= "></td>\n";
        print $value;
        print "</tr>\n";
        print "<tr><td>" . pacsone_gettext("IP Address:") . "</td>\n";
        $data = $row['ipaddr'];
        $value = "<td><input type='text' size=20 maxlength=20 name='ipaddr'";
        if (isset($data))
            $value .= "value='$data'";
        $value .= "></td>\n";
        print $value;
        print "</tr>\n";
        print "<tr><td>" . pacsone_gettext("Port Number:") . "</td>\n";
        $data = $row['port'];
        $value = "<td><input type='text' size=10 maxlength=10 name='port'";
        if (isset($data))
            $value .= "value='$data'";
        $value .= "></td>\n";
        print $value;
        print "</tr>\n";
        // Dicom TLS Security option
        $data = $row['tlsoption'];
        print "<tr><td>";
        print pacsone_gettext("Dicom TLS Security Option:") . "</td>\n";
        $checked = $data? "" : "checked";
        print "<td><input type='radio' name='tlsoption' value=0 $checked>";
        print pacsone_gettext("Disabled") . "<br>";
        $checked = $data? "checked" : "";
        print "<input type='radio' name='tlsoption' value=1 $checked>";
        print pacsone_gettext("Enabled") . "<br>";
        print "</td></tr>";
        print "<tr><td>" . pacsone_gettext("Database Access:") . "</td>\n";
        $data = $row['allowaccess'];
        $value = "<td><select name='access'>\n";
        if (isset($data) && $data) {
            $value .= "<option selected>" . pacsone_gettext("Enable") . "\n";
            $value .= "<option>" . pacsone_gettext("Disable") . "\n";
        }
        else {
            $value .= "<option>" . pacsone_gettext("Enable") . "\n";
            $value .= "<option selected>" . pacsone_gettext("Disable") . "\n";
        }
        $value .= "</select>\n";
        $value .= "</td>\n";
        print $value;
        print "</tr>\n";
        // access control for Dicom commands
        print "<tr><td>";
        $data = $row['privilege'];
        print pacsone_gettext("Allow Dicom Commands From This AE:") . "</td>\n";
        print "<td><table border=0 width=100% cellpadding=5 cellspacing=1>";
        $columns = array(
            pacsone_gettext("Command"),
            pacsone_gettext("Enable"),
            pacsone_gettext("Institution Name Filters"),
            pacsone_gettext("Referring Physician Filters"),
            pacsone_gettext("Reading Physician Filters"),
        );
        global $BGCOLOR;
        print "<tr class='tableHeadForBGUp'>\n";
        foreach ($columns as $cmd)
            print "<td><b>$cmd</td></b>";
        print "</tr>\n";
        global $DICOM_CMDACCESS_TBL;
        global $DICOM_CMDFILTER_TBL;
        foreach ($DICOM_CMDACCESS_TBL as $priv => $cmd) {
            print "<tr ALIGN='center'>";
            print "<td>$cmd</td>";
            $checked = ($data & $priv)? "checked" : "";
            print "<td><input type=checkbox name='privilege[]' value='$priv' $checked></input></td>";
            foreach ($DICOM_CMDFILTER_TBL as $tag => $name) {
                $pattern = "";
                $bindList = array($cmd, $title, $tag);
                $result = $dbcon->preparedStmt("select pattern from aefilter where command=? and sourceae=? and tag=?", $bindList);
                while ($result && ($value = $result->fetchColumn())) {
                    if (strlen($value)) {
                        if (strlen($pattern))
                            $pattern .= ";";
                        $pattern .= $value;
                    } 
                }
                $filtername = $cmd . "-" . $tag;
                print "<td><textarea name='$filtername' wrap='soft' cols='17' rows='3'>$pattern</textarea></td>";
            }
            print "</tr>";
        }
        print "</table></td></tr>\n";
        print "<tr><td>" . pacsone_gettext("Short-Term Archive Directory:") . "</td><td>\n";
        $data = $row['archivedir'];
        $value = "<input type='text' size=32 maxlength=255 name='archivedir'";
        if (isset($data))
            $value .= "value='$data'";
        $value .= "><br>\n";
        print $value;
        print "</td></tr>\n";
        print "<tr><td>";
        $data = $row['archiveformat'];
        global $ARCHIVE_DIR_FORMAT_FLAT;
        global $ARCHIVE_DIR_FORMAT_HIERARCHY;
        global $ARCHIVE_DIR_FORMAT_STUDYUID;
        global $ARCHIVE_DIR_FORMAT_COMBO;
        global $ARCHIVE_DIR_FORMAT_PID_STUDYDATE;
        $checked = ($data == $ARCHIVE_DIR_FORMAT_FLAT)? "checked" : "";
        print pacsone_gettext("Archive Directory Format:") . "</td><td>\n";
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
        print "<tr><td>" . pacsone_gettext("Long-Term Archive Directory:") . "</td><td>\n";
        $data = $row['longtermdir'];
        $value = "<input type='text' size=32 maxlength=255 name='longtermdir'";
        if (isset($data))
            $value .= "value='$data'";
        $value .= ">\n";
        print $value;
        print "</td></tr>\n";
        print "<tr><td>";
        print pacsone_gettext("Automatically Age From Short-Term Archive Directory To Long-Term Archive Directory:") . "</td><td>";
        $data = $row['archiveage'];
        $checked = ($data == 0)? "checked" : "";
        print "<input type='radio' name='archiveage' value=0 $checked>";
        print pacsone_gettext("Disabled") . "<br>";
        $checked = ($data == 0)? "" : "checked";
        print "<input type='radio' name='archiveage' value=1 $checked>";
        printf(pacsone_gettext("Age Images Received More Than <input type='text' name='age' size=6 maxlength=6 value='%d'> Days Ago"), $data);
        print "</td></tr>\n";
        print "<tr><td>";
        print pacsone_gettext("Maximum Number of Simultaneous Connections:") . "</td>\n";
        $data = $row['maxsessions'];
        $value = "<td><input type='text' size=10 maxlength=10 name='maxsessions'";
        if (isset($data))
            $value .= "value='$data'";
        $value .= "></td>\n";
        print $value;
        print "</tr>\n";
        // preferred transfer syntax for outbound sessions to this AE
        print "<tr><td>";
        print pacsone_gettext("Preferred Transfer Syntax When Sending Images to This AE:") . "</td>\n";
        print "<td><select id='txsyntax' name='xfersyntax'>";
        $data = $row['xfersyntax'];
        $preferred = array(
            "",
        );
        $preferred = array_merge($preferred, $PREFERRED_TBL);
        foreach ($preferred as $key) {
            if (strlen($key))
                $name = $XFER_SYNTAX_TBL[$key][2] . " - $key";
            else
                $name = pacsone_gettext("None - Use Original Transfer Syntax of Received Images");
            $selected = strcasecmp($key, $data)? "" : "selected";
            print "<option $selected>$name</option>";
        }
        print "</select>";
        // configure compression ratio/quality for Lossy compressions
        print "<p><div id=\"lossyquality\" style=\"display:none\"><ul>";
        print "<div id=\"lossyratio\" style=\"display:none\"><li>";
        $data = $row['txlossyratio'];
        $ratio = $data? $data : "5";
        print pacsone_gettext("Compression ratio, e.g., 20 (for 20:1), 10 (for 10:1), 5 (for 5:1), etc.:");
        print "&nbsp;<input type='text' name='txlossyratio' value=\"$ratio\" size=3 maxlength=3>";
        print "</li></div>";
        print "<li>";
        $data = $row['txlossyquality'];
        $quality = $data? $data : "90";
        print pacsone_gettext("Compressed image quality, e.g., 90 (for 90%), 80 (for 80%), etc.: ");
        print "&nbsp;<input type='text' name='txlossyquality' value=\"$quality\" size=3 maxlength=3>";
        print "</UL></div>";
        print "</td></tr>\n";
        // propose a separate Presentation Context for each transfer syntax
        print "<tr><td>";
        print pacsone_gettext("Propose A Separate Dicom Presentation Context for Each Transfer Syntax:");
        print "</td><td>";
        $data = $row['multiplectx'];
        $checked = $data? "" : "checked";
        print "<input type=radio name='multiplectx' value=0 $checked>" . pacsone_gettext("No");
        $checked = $data? "checked" : "";
        print "<br><input type=radio name='multiplectx' value=1 $checked>" . pacsone_gettext("Yes");
        print "</td></tr>\n";
        // preferred transfer syntax for inbound sessions from this AE
        print "<tr><td>";
        print pacsone_gettext("Preferred Transfer Syntax When Receiving Images from This AE:") . "</td>\n";
        print "<td><select name='xfersyntaxrx'>";
        $data = $row['xfersyntaxrx'];
        $preferred = array(
            "",
        );
        $preferred = array_merge($preferred, $PREFERRED_TBL);
        foreach ($preferred as $key) {
            if (strlen($key))
                $name = $XFER_SYNTAX_TBL[$key][2] . " - $key";
            else
                $name = pacsone_gettext("None");
            $selected = strcasecmp($key, $data)? "" : "selected";
            print "<option $selected>$name</option>";
        }
        print "</select>";
        print "</td></tr>\n";
        // application type
        $data = $row['queryscp'];
        $value = "unchecked";
        // remote sync is Disabled by default
        $sync = 0;
        $syncBy = 0;
        $overwrite = 0;
        $twoway = 0;
        $schedule = array();
        if (isset($data) && $data) {
            $value = "checked";
            // check if remote sync is enabled for this AE
            $query = "select * from cronjob where aetitle=? and class='sync'";
            $bindList = array($title);
            $result = $dbcon->preparedStmt($query, $bindList);
            if ($result && $result->rowCount()) {
                $sync = 1;
                while ($cronRow = $result->fetch(PDO::FETCH_ASSOC)) {
                    $schedule[] = $cronRow['schedule'];
                    if (isset($cronRow['priority'])) {
                        $overwrite = $cronRow['priority'] & 0x00FF;
                        $twoway = ($cronRow['priority'] >> 8);
                    }
                    // check if sync by study date
                    if (!$syncBy) {
                        $match = array();
                        $uuid = isset($cronRow['uuid'])? $cronRow['uuid'] : "";
                        if (strlen($uuid) && preg_match('/Date\|([0-9]+)/i', $uuid, $match)) {
                            $syncBy = 1;
                            $days = $match[1];
                        }
                    }
                }
            }
        }
        print "<tr><td colspan=2>";
        print pacsone_gettext("Application Type:") . "</td></tr>";
        print "<tr><td>";
        print "<input type=checkbox name='appltype[]' value='queryScp' $value>";
        print pacsone_gettext("Query/Retrieve SCP Server");
        print "</td><td>";
        $checked = ($sync)? "" : "checked";
        print "<input type=radio name='sync' value=0 $checked>";
        print pacsone_gettext("Do not synchronize remote studies") . "<br>";
        $checked = ($sync)? "checked" : "";
        print "<input type=radio name='sync' value=1 $checked>";
        print pacsone_gettext("Synchronize remote studies based on the following 24-hour schedule:");
        print "<dl><dt></dt><dd>";
        for ($i = 0; $i < 24; $i++) {
            $value = ($i % 12)? ($i % 12) : 12;
            $value .= ($i < 12)? " am" : " pm";
            $checked = in_array($i, $schedule)? "checked" : "";
            print "<input type=checkbox name='schedule[]' value=$i $checked>$value";
            if ($i == 11) print "<br>";
        }
        print "</dd><br>";
        print "<dt></dt><dd>";
        $checked = ($syncBy)? "" : "checked";
        print "<input type=radio name='syncBy' value=0 $checked>";
        print pacsone_gettext("Synchronize all remote studies") . "<br>";
        print "</dd>";
        print "<dt></dt><dd>";
        $checked = ($syncBy)? "checked" : "";
        print "<input type=radio name='syncBy' value=1 $checked>";
        $value = isset($days)? $days : 1;
        printf(pacsone_gettext("Synchronize remote studies performed in the last <input type=text name='syncDay' size=2 maxlength=2 value=%d> day(s)"), $value);
        print "</dd><br>";
        $checked = ($overwrite)? "checked" : "";
        print "<dt></dt><dd>";
        print "<input type=checkbox name='overwrite' $checked>";
        print pacsone_gettext("Update existing studies if already exist");
        print "</dd><dd>";
        $checked = ($twoway)? "checked" : "";
        print "<input type=checkbox name='2waysync' $checked>";
        print pacsone_gettext("2-Way Synchronization (push local studies to remote AE)");
        print "</dd>";
        print "</dl></td></tr>\n";
        $data = $row['worklistscp'];
        $value = "unchecked";
        if (isset($data) && $data) {
            $value = "checked";
        }
        print "<tr><td>";
        print "<input type=checkbox name='appltype[]' value='worklistScp' $value>";
        print pacsone_gettext("Modality Worklist SCP Server");
        print "</td><td>&nbsp;";
        print "</td></tr>\n";
        $data = $row['printscp'];
        $value = "unchecked";
        if (isset($data) && $data) {
            $value = "checked";
        }
        print "<tr><td>";
        print "<input type=checkbox name='appltype[]' value='printScp' $value>";
        print pacsone_gettext("Print SCP Server") . "</td>";
        $data = $row['printertype'];
        print "<td>" . pacsone_gettext("Printer Type:") . " <select name='printerType'>";
        foreach ($PRINTER_TBL as $printer) {
            $selected = strcasecmp($printer, $data)? "" : "selected";
            print "<option $selected>$printer</option>";
        }
        print "</select></td></tr>\n";
        print "<tr><td>";
        $data = $row['commitscp'];
        $checked = $data? "checked" : "unchecked"; 
        print "<input type=checkbox name='appltype[]' value='commitScp' $checked>";
        print pacsone_gettext("Storage Commitment SCP Server - Request Storage Commitment Report for Dicom images sent to this SCP");
        print "</td><td>";
        $data = $row['reqcommitment'];
        $checked = $data? "" : "checked";
        print "<input type=radio name='reqcommit' value=0 $checked>" . pacsone_gettext("No");
        $checked = $data? "checked" : "";
        print "<br><input type=radio name='reqcommit' value=1 $checked>" . pacsone_gettext("Yes");
        print "</td></tr>";
        print "<tr><td>";
        $data = $row['notifynewstudy'];
        print pacsone_gettext("Send Email Notifications to Users Registered as the Referring Physician When New Study From this AE is received");
        print "</td><td>";
        $checked = $data? "" : "checked";
        print "<input type=radio name='notifynewstudy' value=0 $checked>" . pacsone_gettext("No");
        $checked = $data? "checked" : "";
        print "<br><input type=radio name='notifynewstudy' value=1 $checked>" . pacsone_gettext("Yes");
        print "</td></tr>";
        $data = $row['priority'];
        print "<tr><td>";
        print pacsone_gettext("Job queue priority when processing database jobs with this destination AE (default priority is 0 - Normal. Database jobs with higher priority destination AEs will be processed before those jobs with lower priority destination AEs)");
        print "</td><td>";
        print "<input type='text' name='priority' size=2 maxlength=2 value=$data>";
        print "</td></tr>";
        // compression setting for images received from this AE
        print "<tr><td>";
        print pacsone_gettext("Compress images received from this AE");
        print "</td><td>";
        print "<UL><li>";
        print pacsone_gettext("Select Dicom Compression Transfer Syntax: ") . "<select name='rxcompression'>";
        $data = $row['rxcompression'];
        global $COMPRESS_RX_IMAGE_TBL;
        foreach ($COMPRESS_RX_IMAGE_TBL as $key => $syntax) {
            $selected = ($key == $data)? "selected" : "";
            print "<option $selected>$syntax</option>";
        }
        print "</select>";
        print "<li>";
        $data = $row['lossyratio'];
        $ratio = $data? $data : "";
        print pacsone_gettext("Compression ratio if JPEG2000 Lossy transfer syntax is selected, e.g., 20 (for 20:1), 10 (for 10:1), 5 (for 5:1), etc.:");
        print "<br><input type='text' name='lossyratio' value=\"$ratio\" size=3 maxlength=3>";
        print "<li>";
        $data = $row['lossyquality'];
        $quality = $data? $data : "";
        print pacsone_gettext("Compressed image quality if either JPEG Lossy or JPEG2000 Lossy transfer syntax is selected, e.g., 90 (for 90%), 80 (for 80%), etc.: ");
        print "<br><input type='text' name='lossyquality' value=\"$quality\" size=3 maxlength=3>";
        print "</UL></td></tr>\n";
        // mark study as Read if moved to this destination AE
        print "<tr><td>";
        print pacsone_gettext("Mark studies as <b>Read</b> by this AE Title after they have been retrieved/moved to this destination AE");
        print "</td><td>";
        $data = $row['markstudy'];
        $checked = $data? "" : "checked";
        print "<input type=radio name='markstudy' value=0 $checked>" . pacsone_gettext("No");
        $checked = $data? "checked" : "";
        print "<br><input type=radio name='markstudy' value=1 $checked>" . pacsone_gettext("Yes");
        print "</td></tr>";
        // anonymize received study from defined anonymization template
        $template = $row['anonymize'];
        print "<tr><td>";
        print pacsone_gettext("Anonymize Studies Received From this AE according to a pre-defined template");
        print "</td><td>";
        $checked = strlen($template)? "" : "checked";
        print "<input type=radio name='anonymize' value=0 $checked>" . pacsone_gettext("Disabled");
        $checked = strlen($template)? "checked" : "";
        print "<br><input type=radio name='anonymize' value=1 $checked>" . pacsone_gettext("Use This Template:");
        $choices = array();
        $anonymize = $dbcon->query("select distinct templname from anonymity");
        while ($anonymize && ($templname = $anonymize->fetchColumn()))
            $choices[] = $templname;
        if (count($choices) == 0) {
            $url = "tools.php?page=" . urlencode(pacsone_gettext("Anonymization Templates"));
            print "&nbsp;<a href=\"$url\">Add Anonymization Template</a>";
        } else {
            print "&nbsp;&nbsp;<select name='template'>";
            foreach ($choices as $name) {
                $selected = strcasecmp($name, $template)? "" : "selected";
                print "<option $selected>$name</option>";
            }
            print "</select>";
        }
        print "</td></tr>";
        // anonymize study by anonymization template when sending to this destination AE
        $template = $row['anonymizetx'];
        print "<tr><td>";
        print pacsone_gettext("Anonymize Studies when Sending to this AE according to a pre-defined template");
        print "</td><td>";
        $checked = strlen($template)? "" : "checked";
        print "<input type=radio name='anonymizetx' value=0 $checked>" . pacsone_gettext("Disabled");
        $checked = strlen($template)? "checked" : "";
        print "<br><input type=radio name='anonymizetx' value=1 $checked>" . pacsone_gettext("Use This Template:");
        if (count($choices) == 0) {
            print "&nbsp;<a href=\"$url\">Add Anonymization Template</a>";
        } else {
            print "&nbsp;&nbsp;<select name='templatetx'>";
            foreach ($choices as $name) {
                $selected = strcasecmp($name, $template)? "" : "selected";
                print "<option $selected>$name</option>";
            }
            print "</select>";
        }
        print "</td></tr>";
        // whether or not to update database tables when receiving from this source AE
        $doNotUpdate = $row['noupdate'];
        print "<tr><td>";
        global $CUSTOMIZE_PATIENT;
        printf(pacsone_gettext("When Receiving Images from this Source AE Do Not Modify the <b>%s</b> and <b>Study</b> Tables"), $CUSTOMIZE_PATIENT);
        print "</td><td>";
        $checked = $doNotUpdate? "" : "checked";
        print "<input type=radio name='noupdate' value=0 $checked>" . pacsone_gettext("No");
        $checked = $doNotUpdate? "checked" : "";
        print "<br><input type=radio name='noupdate' value=1 $checked>" . pacsone_gettext("Yes");
        print "</td></tr>";
        // settings that require System Administration privilege
        $username = $dbcon->username;
        if ($dbcon->hasaccess("admin", $username)) {
            if (stristr(getenv("OS"), "Windows")) {
                // assign transcription template for studies received from this source ae
                $template = $row['xscript'];
                print "<tr><td>";
                print pacsone_gettext("Use Transcription Template for Dicom Studies Received From this AE");
                print "</td><td>";
                $checked = strlen($template)? "" : "checked";
                print "<input type=radio name='xscript' value=0 $checked>" . pacsone_gettext("Disabled");
                $checked = strlen($template)? "checked" : "";
                print "<br><input type=radio name='xscript' value=1 $checked>" . pacsone_gettext("Use This Template:");
                $choices = array();
                $xscript = $dbcon->query("select name from xscriptemplate");
                while ($xscript && ($name = $xscript->fetchColumn()))
                    $choices[] = $name;
                if (count($choices) == 0) {
                    $url = "tools.php?page=" . urlencode(pacsone_gettext("Transcription Templates"));
                    print "&nbsp;<a href=\"$url\">Add Transcription Template</a>";
                } else {
                    print "&nbsp;&nbsp;<select name='xstemplate'>";
                    foreach ($choices as $name) {
                        $selected = strcasecmp($name, $template)? "" : "selected";
                        print "<option $selected>$name</option>";
                    }
                    print "</select>";
                }
                print "</td></tr>";
            }
            $bindList = array($title);
            // AE group settings
            print "<tr><td>";
            print pacsone_gettext("AE Group Settings");
            print "</td><td>";
            $isGroup = $row['aegroup'];
            $allAes = array();
            $aes = $dbcon->preparedStmt("select title from applentity where aegroup=0 and title!=?", $bindList);
            while ($aes && ($aerow = $aes->fetch(PDO::FETCH_NUM)))
                $allAes[] = $aerow[0];
            if ($isGroup) {
                print pacsone_gettext("Configure AE Group Members:");
                $members = array();
                $aegroup = $dbcon->preparedStmt("select memberae from aegroup where aetitle=? order by memberae asc", $bindList);
                while ($aegroup && ($member = $aegroup->fetchColumn()))
                    $members[] = $member;
                print "<dl><dt></dt><dd>";
                foreach ($allAes as $entry) {
                    $checked = in_array($entry, $members)? "checked" : "";
                    print "<input type=checkbox name='memberaes[]' value='$entry' $checked>$entry";
                }
                print "</dd><br>";
                print "<dt></dt><dd>";
            } else {
                $members = array();
                $groups = array();
                $aegroup = $dbcon->preparedStmt("select aetitle from aegroup where memberae=?", $bindList);
                while ($aegroup && ($aerow = $aegroup->fetch(PDO::FETCH_NUM)))
                    $members[] = $aerow[0];
                $aegroup = $dbcon->query("select aetitle from aegroup group by aetitle order by aetitle asc");
                while ($aegroup && ($aerow = $aegroup->fetch(PDO::FETCH_NUM)))
                    $groups[] = $aerow[0];
                if (count($groups)) {
                    print pacsone_gettext("Configure Group Membership:") . "<br>";
                    print "<dl><dt></dt><dd>";
                    foreach ($groups as $entry) {
                        $checked = in_array($entry, $members)? "checked" : "";
                        print "<input type=checkbox name='groups[]' value='$entry' $checked>$entry";
                    }
                    print "</dd><br>";
                    print "<dt></dt><dd>";
                } else {
                    print pacsone_gettext("No AE Group is defined");
                }
            }
            print "</td></tr>";
            // assign studies received from this AE to web users
            $assignedUsers = array();
            $query = "select username from aeassigneduser where aetitle=?";
            $result = $dbcon->preparedStmt($query, $bindList);
            while ($result && ($asRow = $result->fetch(PDO::FETCH_NUM)))
                $assignedUsers[] = $asRow[0];
            $assigned = count($assignedUsers) ? "checked" : "";
            $unassigned = count($assignedUsers) ? "" : "checked";
            print "<tr><td>";
            print pacsone_gettext("Assign Dicom Studies Received From This AE To Web Users");
            print "</td><td>";
            print "<input type=radio name='aeassigned' value=0 $unassigned>" . pacsone_gettext("Disabled");
            print "<br><input type=radio name='aeassigned' value=1 $assigned>" . pacsone_gettext("Assign to the following web users:") . "</input><p>";
            $result = $dbcon->query("select username from privilege");
            if ($result && $result->rowCount()) {
                print "<dl><dt></dt><dd>";
                while ($webuser = $result->fetchColumn()) {
                    if (!$dbcon->isAdministrator($webuser)) {
                        $checked = in_array($webuser, $assignedUsers)? "checked" : "";
                        print "<input type=checkbox name='webusers[]' value='$webuser' $checked>$webuser";
                    }
                }
                print "</dd><br>";
                print "<dt></dt><dd>";
            }
            print "</td></tr>";
            // convert to Latin1 (ISO-8859-1) character set for DMWL query results
            $convert2latin1 = $row['convert2latin1'];
            print "<tr><td>";
            print pacsone_gettext("Convert Person Names to Latin1 (ISO-8859-1) Character Set in Dicom Modality Worklist (DMWL) Query Results");
            print "</td><td>";
            $checked = $convert2latin1? "" : "checked";
            print "<input type=radio name='convert2latin1' value=0 $checked>" . pacsone_gettext("No");
            $checked = $convert2latin1? "checked" : "";
            print "<br><input type=radio name='convert2latin1' value=1 $checked>" . pacsone_gettext("Yes");
            print "</td></tr>";
            // Instance Availability Notification (IAN) subscriptions
            $ianotify = $row['ianotify'];
            print "<tr><td>";
            print pacsone_gettext("Subscribe to Instance Availability Notification (IAN) Messages");
            print "</td><td>";
            $checked = ($ianotify == 0)? "checked" : "";
            print "<input type=radio name='ianotify' value=0 $checked>" . pacsone_gettext("Disabled");
            $checked = ($ianotify == -1)? "checked" : "";
            print "<br><input type=radio name='ianotify' value=-1 $checked>" . pacsone_gettext("Notify this AE when Dicom images are received from any source AE");
            $checked = ($ianotify == 1)? "checked" : "";
            print "<br><input type=radio name='ianotify' value=1 $checked>" . pacsone_gettext("Notify this AE when Dicom images are received from the following source AEs:");
            print "<dl><dt></dt><dd>";
            $subscribed = array();
            $iansubscr = $dbcon->preparedStmt("select source from iansubscr where subscriber=?", $bindList);
            while ($aegroup && ($source = $iansubscr->fetchColumn()))
                $subscribed[] = strtolower($source);
            foreach ($allAes as $entry) {
                $checked = in_array(strtolower($entry), $subscribed)? "checked" : "";
                print "<input type=checkbox name='iansubscr[]' value='$entry' $checked>$entry";
            }
            print "</dd><br>";
            print "<dt></dt><dd>";
            print "</td></tr>";
        }
        print "</table>\n";
        print "<p><input class='btn btn-primary' type='submit' name='action' value='";
        print pacsone_gettext("Modify");
        print "' onclick='switchText(this.form,\"actionvalue\",\"Modify\")'>\n";
        print "</form>\n";
    }
    else {
        print "<h3><font color=red>";
        printf(pacsone_gettext("<u>%s</u> not found in database!"), $title);
        print "</font></h3>\n";
    }
	require_once 'footer.php';
    print "</body>\n";
    print "</html>\n";
}

?>
