<?php 
//
// logfile.php
//
// Module for feeding PacsOne Server log file to browsers
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
require_once "tabbedpage.php";
require_once "utils.php";

class LogfilePage extends TabbedPage {
	var $title;
	var $url;
	var $dbcon;
	var $hl7;

    function __construct(&$dbcon, $hl7 = 0) {
        $this->hl7 = $hl7;
        $this->title = pacsone_gettext("Today's Log");
        $this->url = "tools.php?&hl7=$hl7&page=" . urlencode($this->title) . "#end";
        $this->dbcon = $dbcon;
    }
    function __destruct() { }
    function showHtml() {
        $cwd = dirname($_SERVER['SCRIPT_FILENAME']);
        // change to Unix-style path
        $cwd = str_replace("\\", "/", $cwd);
        $cwd = substr($cwd, 0, strrpos($cwd, '/') + 1);
        $result = $this->dbcon->query("select logdir,aetitle from config");
        $dir = "";
        $aetitle = "";
        if ($result && $result->rowCount()) {
            $row = $result->fetch(PDO::FETCH_NUM);
            $dir = $row[0];
            $aetitle = $row[1];
        }
        if (!file_exists($dir)) {
            $dir = $cwd . "log/";
        }
        // change to Unix-style path
        $dir = str_replace("\\", "/", $dir);
        $dir .= $aetitle;
        $today = getdate();
        $weekday = $today["weekday"];
        $logfile = sprintf("%s/%s-%s.log", $dir, ($this->hl7? "HL7Server" : "PacsOne"), $weekday);
        if (!file_exists($logfile)) {   // try the "*.ini" configuration file
            $ini = $cwd . $aetitle . ".ini";
            if (file_exists($ini)) {
                $parsed = parseIniFile($ini);
                if (count($parsed) && isset($parsed['LogDirectory'])) {
                    $dir = $parsed['LogDirectory'];
                    // change to Unix-style path
                    $dir = str_replace("\\", "/", $dir);
                    $dir .= $aetitle;
                    $logfile = sprintf("%s/%s-%s.log", $dir, ($this->hl7? "HL7Servver" : "PacsOne"), $weekday);
                }
            }
        }
        if (!file_exists($logfile)) {
            print "<font color=red>";
            printf(pacsone_gettext("Log files: \"%s\" does not exist!"), $logfile);
            print "</font>";
            exit();
        }
        if (filesize($logfile) < 6 * 1024 * 1024) {
            $refresh = $this->dbcon->getAutoRefresh($this->dbcon->username);
            print "<META HTTP-EQUIV=REFRESH CONTENT=$refresh>";
        }
        $fp = fopen($logfile, "r");
        $bulk = 10240;
        while (!feof($fp)) {
            $data = fread($fp, $bulk);
            $data = str_replace("\r\n", "<br>", strip_tags($data));
            print $data;
        }    
        fclose($fp);
        print "<a name=\"end\"></a>";
        if (isHL7OptionInstalled()) {
            // toggle between the Dicom Server and HL7 Interface logs
            if ($this->hl7) {
                $url = "tools.php?&hl7=0&page=" . urlencode($this->title) . "#end";
                $which = pacsone_gettext("Dicom Server Logs");
            } else {
                $url = "tools.php?&hl7=1&page=" . urlencode($this->title) . "#end";
                $which = pacsone_gettext("HL7 Interface Logs");
            }
            print "<p><a href='$url'>$which</a>";
        }
    }
}

?> 
