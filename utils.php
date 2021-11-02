<?php
//
// utils.php
//
// Module for various utility functions
//
// CopyRight (c) 2004-2020 RainbowFish Software
//
function my_usort(&$a, $sort, $toggle)
{
    if (!strlen($sort))
        return;
    usort($a, $sort);
    if ($toggle)
        $a = array_reverse($a);
}

function cmp_timestamp($rowa, $rowb)
{
	$a = $rowa['lastaccess'];
	$b = $rowb['lastaccess'];
    if (isset($_SESSION["_isOracle"])) {
        $a = strtotime($a);
        $b = strtotime($b);
    }
	if ($a < $b) return 1;
	if ($a > $b) return -1;

	return 0;
}

function hour2schedule($hour, $ampm)
{
	if ($hour == 12) {
		$schedule = ($ampm)? 12 : 0;
	} else {
		$schedule = ($ampm)? ($hour + 12) : $hour;
	}
	return $schedule;
}

function cmp_id($rowa, $rowb)
{
	$a = addslashes($rowa['origid']);
	$b = addslashes($rowb['origid']);
	if ($a < $b) return -1;
	if ($a > $b) return 1;

	return 0;
}

function cmp_name($rowa, $rowb)
{
	$a = $rowa['lastname'];
	$b = $rowb['lastname'];

	return strcasecmp($a, $b);
}

function cmp_birthdate($rowa, $rowb)
{
	$a = $rowa['birthdate'];
	$b = $rowb['birthdate'];
    if (isset($_SESSION["_isOracle"])) {
        $a = strtotime($a);
        $b = strtotime($b);
    }
	if ($a < $b) return -1;
	if ($a > $b) return 1;

	return 0;
}

function cmp_patientid($rowa, $rowb)
{
	$a = addslashes($rowa['patientid']);
	$b = addslashes($rowb['patientid']);
	if ($a < $b) return -1;
	if ($a > $b) return 1;

	return 0;
}

function cmp_studyid($rowa, $rowb)
{
	$a = $rowa['id'];
	$b = $rowb['id'];
	if ($a < $b) return -1;
	if ($a > $b) return 1;

	return 0;
}

function cmp_studydate($rowa, $rowb)
{
	$a = $rowa['studydate'];
	$b = $rowb['studydate'];
    if (isset($_SESSION["_isOracle"])) {
        $a = strtotime($a);
        $b = strtotime($b);
    }
	$aa = $rowa['studytime'];
	$bb = $rowb['studytime'];
	if ($a < $b) return -1;
	if ($a > $b) return 1;
	if ($aa < $bb) return -1;
	if ($aa > $bb) return 1;

	return 0;
}

function cmp_seriesdate($rowa, $rowb)
{
	$a = $rowa['seriesdate'];
	$b = $rowb['seriesdate'];
    if (isset($_SESSION["_isOracle"])) {
        $a = strtotime($a);
        $b = strtotime($b);
    }
	$aa = $rowa['seriestime'];
	$bb = $rowb['seriestime'];
	if ($a < $b) return -1;
	if ($a > $b) return 1;
	if ($aa < $bb) return -1;
	if ($aa > $bb) return 1;

	return 0;
}

function cmp_accession($rowa, $rowb)
{
	$a = $rowa['accessionnum'];
	$b = $rowb['accessionnum'];
	if ($a < $b) return -1;
	if ($a > $b) return 1;

	return 0;
}

function cmp_seriesnum($rowa, $rowb)
{
	$a = $rowa['seriesnumber'];
	$b = $rowb['seriesnumber'];
	if ($a < $b) return -1;
	if ($a > $b) return 1;

	return 0;
}

function cmp_received($rowa, $rowb)
{
    global $dbcon;
    $a = addslashes($rowa['origid']);
    $b = addslashes($rowb['origid']);
    $result = $dbcon->query("select received from study where patientid='$a' order by received desc");
    $a = $result->fetchColumn();
    $result = $dbcon->query("select received from study where patientid='$b' order by received desc");
    $b = $result->fetchColumn();
    if (isset($_SESSION["_isOracle"])) {
        $a = strtotime($a);
        $b = strtotime($b);
    }
    if ($a < $b) return 1;
    if ($a > $b) return -1;

    return 0;
}

function cmp_received_opt($rowa, $rowb)
{
    $a = $rowa['received'];
    $b = $rowb['received'];
    if (isset($_SESSION["_isOracle"])) {
        $a = strtotime($a);
        $b = strtotime($b);
    }
    if ($a < $b) return 1;
    if ($a > $b) return -1;

    return 0;
}

function cmp_when($rowa, $rowb)
{
	$a = $rowa['timestamp'];
	$b = $rowb['timestamp'];
    if (isset($_SESSION["_isOracle"])) {
        $a = strtotime($a);
        $b = strtotime($b);
    }
	if ($a < $b) return -1;
	if ($a > $b) return 1;

	return 0;
}

function cmp_username($rowa, $rowb)
{
	$a = $rowa['username'];
	$b = $rowb['username'];
	if ($a < $b) return -1;
	if ($a > $b) return 1;

	return 0;
}

function cmp_what($rowa, $rowb)
{
	$a = $rowa['what'];
	$b = $rowb['what'];
	if ($a < $b) return -1;
	if ($a > $b) return 1;

	return 0;
}

function cmp_description($rowa, $rowb)
{
	$a = $rowa['description'];
	$b = $rowb['description'];
	if ($a < $b) return -1;
	if ($a > $b) return 1;

	return 0;
}

function cmp_referdoc($rowa, $rowb)
{
	$a = $rowa['referringphysician'];
	$b = $rowb['referringphysician'];
	if ($a < $b) return -1;
	if ($a > $b) return 1;

	return 0;
}

function cmp_readingdoc($rowa, $rowb)
{
	$a = $rowa['readingphysician'];
	$b = $rowb['readingphysician'];
	if ($a < $b) return -1;
	if ($a > $b) return 1;

	return 0;
}

function cmp_sourceae($rowa, $rowb)
{
	$a = $rowa['sourceae'];
	$b = $rowb['sourceae'];
	if ($a < $b) return -1;
	if ($a > $b) return 1;

	return 0;
}

function cmp_sex($rowa, $rowb)
{
	$a = $rowa['sex'];
	$b = $rowb['sex'];
	if ($a < $b) return -1;
	if ($a > $b) return 1;

	return 0;
}

function cmp_institution($rowa, $rowb)
{
	$a = $rowa['institution'];
	$b = $rowb['institution'];

	return strcasecmp($a, $b);
}

function getSopClassName($uid)
{
	$sopClassTbl = array(
		"1.2.840.10008.5.1.4.1.1.1"			=> "Computed Radiogaphy",
		"1.2.840.10008.5.1.4.1.1.2"			=> "CT",
		"1.2.840.10008.5.1.1.30"			=> "Hardcopy Color Image",
		"1.2.840.10008.5.1.1.29"			=> "Hardcopy Grayscale Image",
		"1.2.840.10008.5.1.4.1.1.4"			=> "MR",
		"1.2.840.10008.5.1.4.1.1.20"		=> "Nuclear Medicine",
		"1.2.840.10008.5.1.4.1.1.128"		=> "Positron Emission Tomography",
		"1.2.840.10008.5.1.4.1.1.481.2"		=> "RT Dose",
		"1.2.840.10008.5.1.4.1.1.481.1"		=> "RT Image",
		"1.2.840.10008.5.1.4.1.1.481.5"		=> "RT Plan",
		"1.2.840.10008.5.1.4.1.1.481.3"		=> "RT Structure Set",
		"1.2.840.10008.5.1.4.1.1.481.4"		=> "RT Beams Treatment Record",
		"1.2.840.10008.5.1.4.1.1.481.6"		=> "RT Brachy Treatment Record",
		"1.2.840.10008.5.1.4.1.1.481.7"		=> "RT Treatment Summary Record",
		"1.2.840.10008.5.1.4.1.1.7"			=> "Secondary Capture",
		"1.2.840.10008.5.1.4.1.1.7.1"		=> "Multi-frame Single Bit Secondary Capture",
		"1.2.840.10008.5.1.4.1.1.7.2"		=> "Multi-frame Grayscale Byte Secondary Capture",
		"1.2.840.10008.5.1.4.1.1.7.3"		=> "Multi-frame Grayscale Word Secondary Capture",
		"1.2.840.10008.5.1.4.1.1.7.4"		=> "Multi-frame True Color Secondary Capture",
		"1.2.840.10008.5.1.4.1.1.9"			=> "Stand-alone Curve",
		"1.2.840.10008.5.1.4.1.1.9.1"		=> "12-lead ECG Waveform",
		"1.2.840.10008.5,1,4,1,1,9,2"		=> "General ECG Waveform",
		"1.2.840.10008.5.1.4.1.1.9.3"		=> "Ambulatory ECG Waveform",
		"1.2.840.10008.5.1.4.1.1.9.2.1"		=> "Hemodynamic Waveform",
		"1.2.840.10008.5.1.4.1.1.9.3.1"		=> "Cardiac Eletrophysiology Waveform",
		"1.2.840.10008.5.1.4.1.1.9.4.1"		=> "Basic Voice Audio Waveform",
		"1.2.840.10008.5.1.4.1.1.10"		=> "Stand-alone Modality LUT",
		"1.2.840.10008.5.1.4.1.1.8"			=> "Stand-alone Overlay",
		"1.2.840.10008.5.1.4.1.1.11"		=> "Stand-alone VOI LUT",
		"1.2.840.10008.5.1.4.1.1.11.1"		=> "Grayscale Softcopy Presentation State",
		"1.2.840.10008.5.1.4.1.1.129"		=> "Stand-alone PET Curve",
		"1.2.840.10008.5.1.1.27"			=> "Stored Print",
		"1.2.840.10008.5.1.4.1.1.6.1"		=> "Ultrasound",
		"1.2.840.10008.5.1.4.1.1.6"			=> "Ultrasound (Retired)",
		"1.2.840.10008.5.1.4.1.1.3.1"		=> "Ultrasound Multi-frame Image",
		"1.2.840.10008.5.1.4.1.1.3"			=> "Ultrasound Multi-frame Image (Retired)",
		"1.2.840.10008.5.1.4.1.1.12.1"		=> "X-Ray Angiographic Image",
		"1.2.840.10008.5.1.4.1.1.12.2"		=> "X-Ray Radiofluoroscopic Image",
		"1.2.840.10008.5.1.4.1.1.1.1"		=> "Digital X-Ray - For Presentation",
		"1.2.840.10008.5.1.4.1.1.1.1.1"		=> "Digital X-Ray - For Processing",
		"1.2.840.10008.5.1.4.1.1.1.2"		=> "Digital Mammography - For Presentation",
		"1.2.840.10008.5.1.4.1.1.1.2.1"		=> "Digital Mammography - For Processing",
		"1.2.840.10008.5.1.4.1.1.1.3"		=> "Digital Intra-oral X-Ray - For Presentation",
		"1.2.840.10008.5.1.4.1.1.1.3.1"		=> "Digital Intra-oral X-Ray - For Processing",
		"1.2.840.10008.5.1.4.1.1.77.1.1"	=> "VL Endoscopic",
		"1.2.840.10008.5.1.4.1.1.77.1.2"	=> "VL Microscopic",
		"1.2.840.10008.5.1.4.1.1.77.1.3"	=> "VL Slide-Coordinates Microscopic",
		"1.2.840.10008.5.1.4.1.1.77.4"		=> "VL Photographic",
	);
	$value = "";
	if (isset($sopClassTbl[$uid]))
		$value = $sopClassTbl[$uid];
	return $value;
}

function isHL7OptionInstalled()
{
    $dir = dirname($_SERVER['SCRIPT_FILENAME']);
    $dir = str_replace("\\", "/", $dir);
    $dir = substr($dir, 0, strrpos($dir, '/') + 1);
    return (file_exists($dir . "PacsOneHL7.exe") || file_exists($dir . "MediPacsHL7.exe"));
}

function encodeHeader($input, $charset = 'ISO-8859-1')
{
    preg_match_all('/(\w*[\x80-\xFF]+\w*)/', $input, $matches);
    foreach ($matches[1] as $value) {
        $replacement = preg_replace('/([\x80-\xFF])/e', '"=" . strtoupper(dechex(ord("\1")))', $value);
        $input = str_replace($value, '=?' . $charset . '?Q?' . $replacement . '?=', $input);
    }
    return $input;
}

function MakeStudyUid()
{
    $uid = "1.2.826.0.1.3680043.2.737." . rand(100, 32768) . ".";
    $uid .= date("Y.n.j.G");
    $mins = 0 + date("i");
    $secs = 0 + date("s");
    $uid .= ".$mins.$secs";
    return $uid;
}

function reverseDate($date)
{
    $value = $date;
    $tokens = explode("-", str_replace(".", "-", $date));
    if (count($tokens) == 3) {
        $value = sprintf("%s-%s-%s", $tokens[2], $tokens[1], $tokens[0]);
    }
    return $value;
}

function urlReplace($url, $param, $value)
{
    $after = stristr($url, "$param=");
    if ($after) {
        if (strchr($after, '&')) {
            $pattern = "/(.*)$param=(.*?)&(.*)/i";
            $repl = '${1}' . "$param=$value&" . '${3}';
        } else {
            $pattern = "/(.*)$param=(.*)/i";
            $repl = '${1}' . "$param=$value";
        }
        $url = preg_replace($pattern, $repl, $url);
    } else {
        // parameter not found, append it
        $and = (strrpos($url, "?") == false)? "?" : "&";
        $url .= $and . "$param=$value";
    }
    return $url;
}

function cmp_reqdoc($rowa, $rowb)
{
	$a = $rowa['requestingphysician'];
	$b = $rowb['requestingphysician'];
	if ($a < $b) return -1;
	if ($a > $b) return 1;

	return 0;
}

function cleanPostPath($path, $toUnixPath = true)
{
    // strip the extra '\' added by the magic quotes
    $ret = get_magic_quotes_gpc()? stripslashes($path) : $path;
    if ($toUnixPath) {
        // change to Unix-style path
        $ret = str_replace("\\", "/", $ret);
    }
    return $ret;
}

function parseIniFile(&$inifile)
{
    $result = array();
    $parsed = parse_ini_file($inifile);
    foreach ($parsed as $key => $value) {
        // allow the follow case-insensitive keys
        if (strcasecmp($key, "Database") == 0) {
            $result['Database'] = $value;
        }
        else if (strcasecmp($key, "Schema") == 0) {
            $result['Schema'] = $value;
        }
        else if (strcasecmp($key, "DatabaseHost") == 0) {
            $result['DatabaseHost'] = $value;
        } else {
            $result[$key] = $value;
        }
    }
    return $result;
}

function parseIniByAeTitle(&$aetitle)
{
    $dir = dirname($_SERVER['SCRIPT_FILENAME']);
    $dir = substr($dir, 0, strlen($dir) - 3);
    $ini = $dir . $aetitle . ".ini";
    return parseIniFile($ini);
}

function getServerInstances()
{
    $result = array();
    $dir = dirname($_SERVER['SCRIPT_FILENAME']);
    // goto the parent directory of "/php"
    $dir = substr($dir, 0, strlen($dir) - 3);
    if (is_dir($dir)) {
        if ($dh = opendir($dir)) {
            while (($file = readdir($dh)) !== false) {
                if (strcasecmp(filetype($dir . $file), "dir")) {
                    $tokens = explode(".", $file);
                    if ( count($tokens) == 2 &&
                         (strcasecmp($tokens[1], "ini") == 0) )
                    {
                        $inifile = $dir . $file;
                        $keyValue = parseIniFile($inifile);
                        $result[ strtolower($tokens[0]) ] = $keyValue['Database'];
                    }
                }
            }
            closedir($dh);
        }
    }
    return $result;
}

function getDatabaseHost($aetitle)
{
    $hostname = "localhost";
    $dir = dirname($_SERVER['SCRIPT_FILENAME']);
    $dir = substr($dir, 0, strlen($dir) - 3);
    $ini = $dir . $aetitle . ".ini";
    if (file_exists($ini)) {
        $parsed = parseIniFile($ini);
        if (count($parsed) && isset($parsed['DatabaseHost'])) {
            $hostname = $parsed['DatabaseHost'];
        }
    }
    return $hostname;
}

function pacsone_gettext($text)
{
    return function_exists("gettext")? _($text) : $text;
}

function getDatabaseName($aetitle)
{
    $value = "";
    $dir = dirname($_SERVER['SCRIPT_FILENAME']);
    $dir = substr($dir, 0, strlen($dir) - 3);
    $ini = $dir . $aetitle . ".ini";
    if (file_exists($ini)) {
        $parsed = parseIniFile($ini);
        if (count($parsed) && isset($parsed['Database'])) {
            $value = $parsed['Database'];
        }
    }
    return $value;
}

function getDatabaseNames(&$oracle)
{
    $result = array();
    $dir = dirname($_SERVER['SCRIPT_FILENAME']);
    // goto the parent directory of "/php"
    $dir = substr($dir, 0, strlen($dir) - 3);
    if (is_dir($dir)) {
        // check if this is an Oracle database
        global $ORACLE_CONFIG_FILE;
        $file = $dir . $ORACLE_CONFIG_FILE;
        if (file_exists($file))
            $oracle = true;
        if ($dh = opendir($dir)) {
            while (($file = readdir($dh)) !== false) {
                if (strcasecmp(filetype($dir . $file), "dir")) {
                    $tokens = explode(".", $file);
                    if ( count($tokens) == 2 &&
                         (strcasecmp($tokens[1], "ini") == 0) )
                    {
                        $aetitle = $tokens[0];
                        $inifile = $dir . $file;
                        $database = parseIniFile($inifile);
                        if (count($database))
                            $result[$aetitle] = $database;
                    }
                }
            }
            closedir($dh);
        }
    }
    return $result;
}

function getThumbnailImageFlashDirs(&$dbcon, &$thumbnaildir, &$imagedir, &$flashdir) {
    $flashdir = dirname($_SERVER['SCRIPT_FILENAME']);
    $thumbnaildir = $flashdir . "/";
    $imagedir = $flashdir . "/";
    $flashdir .= "/flash/";
    $result = $dbcon->query("select thumbnaildir,imagedir,flashdir from config");
    if ($result && ($row = $result->fetch(PDO::FETCH_NUM))) {
        if (strlen($row[0]))
            $thumbnaildir = $row[0];
        if (strcmp(substr($thumbnaildir, strlen($thumbnaildir)-1, 1), "/"))
    	    $thumbnaildir .= "/";
        if (strlen($row[1]))
            $imagedir = $row[1];
        if (strcmp(substr($imagedir, strlen($imagedir)-1, 1), "/"))
    	    $imagedir .= "/";
        if (strlen($row[2]))
            $flashdir = $row[2];
        if (strcmp(substr($flashdir, strlen($flashdir)-1, 1), "/"))
    	    $flashdir .= "/";
    }
}

function deleteImages($entry)
{
    if (count($entry) == 0)
        return "home.php";
    global $dbcon;
	$url = "image.php";
	$ok = array();
	$errors = array();
	// find patient, study and series ids
	$query = "select seriesuid from image where uuid=?";
    $bindList = array($entry[0]);
    $result = $dbcon->preparedStmt($query, $bindList);
	$seriesid = $result->fetchColumn();
	$result = $dbcon->query("select studyuid from series where uuid='$seriesid'");
	$studyid = $result->fetchColumn();
	$result = $dbcon->query("select patientid from study where uuid='$studyid'");
	$patientid = $result->fetchColumn();
	// update the URL for refreshing
	$url .= "?patientId=" . urlencode($patientid) . "&studyId=" . urlencode($studyid) . "&seriesId=" . urlencode($seriesid);
    $thumbnaildir = $imagedir = $flashdir = "";
    getThumbnailImageFlashDirs($dbcon, $thumbnaildir, $imagedir, $flashdir);
    $sitedir = strtr(dirname($_SERVER['SCRIPT_FILENAME']), "\\", "/");
    // append '/' at the end if not so already
    if (strcmp(substr($sitedir, strlen($sitedir)-1, 1), "/"))
        $sitedir .= "/";
    $sitedir .= "flash/";
	foreach ($entry as $value) {
		$junks = array(
        	$thumbnaildir . "thumbnails/$value.jpg",
        	$thumbnaildir . "thumbnails/$value.gif",
        	$imagedir . "images/$value.jpg",
        	$imagedir . "images/$value.gif",
        	$imagedir . "images/temp.$value.jpg",
        	$imagedir . "images/temp.$value.gif",
		);
        // delete any related attachments
        $query = "select path from attachment where uuid=?";
        $bindList = array($value);
        $result = $dbcon->preparedStmt($query, $bindList);
        if ($result) {
            while ($path = $result->fetchColumn())
                unlink($path);
        }
        $query = "delete from attachment where uuid=?";
        $dbcon->preparedStmt($query, $bindList);
        // delete image notes
        $query = "delete from imagenotes where uuid=?";
        $dbcon->preparedStmt($query, $bindList);
        // remove physical storage file
        $query = "select * from image where uuid=?";
        $result = $dbcon->preparedStmt($query, $bindList);
        if ($result->rowCount() == 1) {
            $row = $result->fetch(PDO::FETCH_ASSOC);
            if (file_exists( $row['path'] ))
                unlink( $row['path'] );
            // remove post-receive compressed files
            $junks[] = $row['path'] . ".ls";
            $junks[] = $row['path'] . ".ly";
            $junks[] = $row['path'] . ".rle";
            $junks[] = $row['path'] . ".j2k";
            $junks[] = $row['path'] . ".encap";
            // remove converted video clips
            $junks[] = $flashdir . basename($row['path']) . ".swf";
            $junks[] = $flashdir . basename($row['path']) . ".mp4";
            $junks[] = $flashdir . basename($row['path']) . ".webm";
            // remove any symlinks as well
            $junks[] = $sitedir . basename($row['path']) . ".swf";
            $junks[] = $sitedir . basename($row['path']) . ".mp4";
            $junks[] = $sitedir . basename($row['path']) . ".webm";
        }
		$query = "delete from image where uuid=?";
		if (!$dbcon->preparedStmt($query, $bindList)) {
			$errors[$value] = "Database Error: " . $dbcon->getError();
		}
		else
			$ok[] = $value;
		// remove any thumbnail and cached ImageMagick files
		foreach ($junks as $file) {
			if (file_exists($file))
				unlink($file);
		}
		// delete any derived tables
		$query = "delete from conceptname where uuid=?";
		$dbcon->preparedStmt($query, $bindList);
		$query = "delete from commitsopref where sopinstance=?";
		$dbcon->preparedStmt($query, $bindList);
		$query = "delete from imagedose where uuid=?";
		$dbcon->preparedStmt($query, $bindList);
	}
	return $url;
}

function deleteSeries($entry)
{
    if (count($entry) == 0)
        return "home.php";
    global $dbcon;
	$url = "series.php";
	$ok = array();
	$errors = array();
	// find study ID and patient ID
	$query = "select studyuid from series where uuid=?";
    $bindList = array($entry[0]);
    $result = $dbcon->preparedStmt($query, $bindList);
    if (!$result || ($result->rowCount() == 0))
        return "home.php";
	$studyid = $result->fetchColumn();
	$result = $dbcon->query("select patientid from study where uuid='$studyid'");
	$patientid = urlencode($result->fetchColumn());
	// update URL for refreshing
	$url .= "?patientId=$patientid&studyId=$studyid";
	foreach ($entry as $value) {
        // find all related image rows
        $images = array();
        $query = "select * from image where seriesuid=?";
        $bindList = array($value);
        $result = $dbcon->preparedStmt($query, $bindList);
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $images[] = $row['uuid'];
        }
        // delete all related image rows
        deleteImages($images);
		$query = "delete from series where uuid=?";
		if (!$dbcon->preparedStmt($query, $bindList)) {
			$errors[$value] = "Database Error: " . $dbcon->getError();
		}
		else
			$ok[] = $value;
	}
	return $url;
}

function deleteStudies($entry)
{
    if (count($entry) == 0)
        return "home.php";
    global $dbcon;
	$url = "study.php";
	$ok = array();
	$errors = array();
	// find the patient id
	$query = "select patientid from study where uuid=?";
    $bindList = array($entry[0]);
    $result = $dbcon->preparedStmt($query, $bindList);
    if (!$result || ($result->rowCount() == 0))
        return "home.php";
	$patientid = urlencode($result->fetchColumn());
	// update the URL for refreshing
	$url .= "?patientId=$patientid";
	foreach ($entry as $value) {
        // delete any related attachments
        $query = "select path from attachment where uuid=?";
        $bindList = array($value);
        $result = $dbcon->preparedStmt($query, $bindList);
        if ($result) {
            while ($path = $result->fetchColumn()) {
                if (file_exists($path))
                    unlink($path);
            }
        }
        $query = "delete from attachment where uuid=?";
        $dbcon->preparedStmt($query, $bindList);
        // delete study notes
        $query = "delete from studynotes where uuid=?";
        $dbcon->preparedStmt($query, $bindList);
        // find all related series rows
        $series = array();
        $query = "select * from series where studyuid=?";
        $result = $dbcon->preparedStmt($query, $bindList);
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $series[] = $row['uuid'];
        }
        // delete all related series rows
        deleteSeries($series);
		$query = "delete from study where uuid=?";
		if (!$dbcon->preparedStmt($query, $bindList)) {
			$errors[$value] = "Database Error: " . $dbcon->getError();
		}
		else
			$ok[] = $value;
        // delete all derived tables
        $query = "delete from studydosereport where uuid=?";
        $dbcon->preparedStmt($query, $bindList);
        $query = "delete from irradiationevent where studyuid=?";
        $dbcon->preparedStmt($query, $bindList);
	}
	return $url;
}

function deletePatients($entry)
{
    global $dbcon;
	$url = "browse.php";
    $subTables = array(
        "otherpatientids",
        "patientspeciescode",
        "patientbreedcode",
        "breedregistration",
    );
	foreach ($entry as $value) {
        $pid = urldecode($value);
        $bindList = array($pid);
        // find all related study rows
        $studies = array();
        $query = "select * from study where patientid=?";
        $result = $dbcon->preparedStmt($query, $bindList);
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $studies[] = $row['uuid'];
        }
        // delete all related study rows
        deleteStudies($studies);
        // delete sub-tables
        foreach ($subTables as $sub) {
            $query = "delete from $sub where patientid=?";
		    if (!$dbcon->preparedStmt($query, $bindList)) {
			    die("Database Error: " . $dbcon->getError());
		    }
        }
		$query = "delete from patient where origid=?";
		if (!$dbcon->preparedStmt($query, $bindList)) {
			die("Database Error: " . $dbcon->getError());
		}
	}
	return $url;
}

function deleteWorklists($entry)
{
    global $dbcon;
	$url = "worklist.php";
	$ok = array();
	$errors = array();
    $tables = array(
        "worklist", "scheduledps", "requestedprocedure", "protocolcode", "procedurecode",
        "referencedpps", "referencedstudy", "referencedpatient", "referencedvisit",
    );
	foreach ($entry as $uid) {
        $success = true;
        // delete all related table rows
        foreach ($tables as $table) {
		    $query = "delete from $table where studyuid=?";
            $bindList = array($uid);
		    if (!$dbcon->preparedStmt($query, $bindList)) {
			    $errors[$uid] = "Database Error deleting '$table' table rows: " . $dbcon->getError();
                print "<p><h3><font color=red>";
                print pacsone_gettext("Fatal Error: ") . $errors[$uid] . "</font></h3>\n";
                exit();
		    }
        }
        if ($success)
            $ok[] = $uid;
	}
    return $url;
}

function getDateTimeStamp()
{
    return date("YmdHis");
}

function MakeHL7MsgControlId()
{
    $ret = getDateTimeStamp();
    $ret .= rand(32769, 65536);
    return $ret;
}

function validatePassword(&$password)
{
    $dir = dirname($_SERVER['SCRIPT_FILENAME']);
    $dir = substr($dir, 0, strlen($dir) - 3);
    $bypass = $dir . "no.strict.password";
    if (file_exists($bypass)) {
        return 1;
    }
    // must be at least 8 characters
    if (strlen($password) < 8) return 0;
    // must contain at least 1 number
    if (!preg_match('/\d/', $password)) return 0;
    // must contain at least 1 capital character
    if (!preg_match('/[A-Z]/', $password)) return 0;
    // must contain at least 1 special char from $PASSWD_SPECIAL_CHARS
    global $PASSWD_SPECIAL_CHARS;
    if (!preg_match("/[$PASSWD_SPECIAL_CHARS]/", $password)) return 0;
    return 1;
}

function alertBox(&$message, $url)
{
    print "<script language=\"JavaScript\">\n";
    print "<!--\n";
    print "alert(\"$message\");";
    print "window.location.replace(\"$url\");";
    print "//-->\n";
    print "</script>\n";
}

//
// compare version string with (major,minior,release) digits
//
// Returns:
//
// 0    - if they are the same
// >0   - if version string is higher/newer
// <0   - if version digits are higher/newer
//
function versionCompare($version, $major, $minor, $release)
{
    $ret = 0;
    $digits = preg_split("/[.,-]/", $version);
    if (count($digits) >= 3) {
        if ($digits[0] > $major)
            return 1;
        if ($digits[0] < $major)
            return -1;
        // now check the minor number
        if ($digits[1] > $minor)
            return 1;
        if ($digits[1] < $minor)
            return -1;
        // now check the release number
        $ret = $digits[2] - $release;
    }
    return $ret;
}

function parseUrlSelfPrefix($match = "")
{
    $scheme = (isset($_SERVER['HTTPS']) && !strcasecmp($_SERVER['HTTPS'], 'on'))?
        "https://" : "http://";
    $url = $scheme . $_SERVER['SERVER_NAME'] . ":" . $_SERVER["SERVER_PORT"];
    // dirname() function does not seem to work reliably, so use the following
    $tokens = explode("/", $_SERVER['PHP_SELF']);
    for ($i = 0; $i < count($tokens) - 1; $i++) {
        if (strlen($tokens[$i])) {
            // stop when the matching pattern is found
            if (strlen($match) && stristr($tokens[$i], $match))
                break;
            $url .= "/" . $tokens[$i];
        }
    }
    return $url;
}

function parseDicomWebPrefix()
{
    return parseUrlSelfPrefix("rs.php");
}

function checkForConvertedVideo(&$flashdir, &$path)
{
    $url = "";
    $sitedir = strtr(dirname($_SERVER['SCRIPT_FILENAME']), "\\", "/");
    // append '/' at the end if not so already
    if (strcmp(substr($sitedir, strlen($sitedir)-1, 1), "/"))
        $sitedir .= "/";
    $sitedir .= "flash/";
    if (file_exists($flashdir . basename($path) . ".webm")) {
        $webm = basename($path) . ".webm";
        // create a symlink to the converted video if the default directory is not used
        if (!file_exists($sitedir . $webm))
            symlink($flashdir . $webm, $sitedir . $webm);
        $embed = "flash/$webm";
        $url = "<video controls><source src=\"$embed\"> type=\"video/webm\">";
        $url .= pacsone_gettext("Your browser does not support the VIDEO tag");
        $url .= "</video>";
    } else if (file_exists($flashdir . basename($path) . ".mp4")) {
        $mp4 = basename($path) . ".mp4";
        // create a symlink to the converted video if the default directory is not used
        if (!file_exists($sitedir . $mp4))
            symlink($flashdir . $mp4, $sitedir . $mp4);
        $embed = "flash/$mp4";
        $url = "<video controls><source src=\"$embed\"> type=\"video/mp4\">";
        $url .= pacsone_gettext("Your browser does not support the VIDEO tag");
        $url .= "</video>";
    } else if (file_exists($flashdir . basename($path) . ".swf")) {
        $swf = basename($path) . ".swf";
        // create a symlink to the converted video if the default directory is not used
        if (!file_exists($sitedir . $swf))
            symlink($flashdir . $swf, $sitedir . $swf);
        $embed = "flash/$swf";
        $url = "<object classid=\"clsid:D27CDB6E-AE6D-11cf-96B8-444553540000\" codebase=\"http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=6,0,29,0\" width=\"400\" height=\"400\">";
        $url .= "<param name=\"movie\" value=\"$swf\">";
        $url .= "<param name=\"quality\" value=\"high\">";
        $url .= "<param name=\"LOOP\" value=\"false\">";
        $url .= "<embed src=\"$embed\" width=\"400\" height=\"400\" loop=\"false\" quality=\"high\" pluginspage=\"http://www.macromedia.com/go/getflashplayer\" type=\"application/x-shockwave-flash\"></embed>";
        $url .= "</object>";
    }
    return $url;
}

function array2ini(array $a, array $parent = array())
{
    $out = '';
    foreach ($a as $k => $v) {
        if (is_array($v)) {
            //subsection case
            //merge all the sections into one array...
            $sec = array_merge((array) $parent, (array) $k);
            //add section information to the output
            $out .= '[' . join('.', $sec) . ']' . PHP_EOL;
            //recursively traverse deeper
            $out .= arr2ini($v, $sec);
        } else {
            trim($v);
            //plain key->value case
            if (preg_match('/^(\S*)\s/', $v) || preg_match('/[=\(\)]/', $v))
                $out .= "$k = \"$v\"" . PHP_EOL;
            else
                $out .= "$k = $v" . PHP_EOL;
        }
    }
    return $out;
}

function writeIniByAeTitle(&$aetitle, &$data)
{
    $dir = dirname($_SERVER['SCRIPT_FILENAME']);
    $dir = substr($dir, 0, strlen($dir) - 3);
    $ini = $dir . $aetitle . ".ini";
    if ($fp = fopen($ini, "w")) {
        fwrite($fp, array2ini($data));
        fclose($fp);
    }
}

function getFirstFromFullLastNames($fullname, $lastname)
{
    $first = "";
    $parts = preg_split("/[\s,]+/", $fullname);
    $count = count($parts);
    if ($count > 0) {
        if (strcasecmp($parts[0], $lastname))
            $first = $parts[0];
        else if ($count > 1)
            $first = $parts[1];
    }
    return $first;
}

function parseLdapComponent($value, $key)
{
    $comp = "";
    $tokens = explode(",", $value);
    foreach ($tokens as $token) {
        if (stristr($token, "$key=")) {
            $parts = explode("=", $token);
            if (count($parts == 2)) {
                $comp = trim($parts[1]);
                break;
            }
        }
    }
    return $comp;
}

// Function to get the client IP address
function get_client_ip() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'Unknown';
    return $ipaddress;
}

function urlClean($input, $maxLength)
{
    $input = substr($input, 0, $maxLength);
    $input = EscapeShellCmd($input);
    return $input;
}

function isWildcardFirst($pattern)
{
    if ($pattern) {
        if (($pattern[0] == '*') || ($pattern[0] == '?'))
            return 1;
    }
    return 0;
}

function isDateValid(&$date, $eurodate)
{
    $sqldate = $eurodate? reverseDate($date) : $date;
    if (!preg_match("/^([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})/", $sqldate, $regs))
        return 0;
    if (($regs[1] < 1900) || ($regs[1] > 2200) ||
        ($regs[2] < 1) || ($regs[2] > 12) ||
        ($regs[3] < 1) || ($regs[3] > 31))
        return 0;
    // convert to YYYY-MM-DD standard format
    $date = $eurodate? sprintf("%'02d-%'02d-%4d", $regs[3], $regs[2], $regs[1]) : sprintf("%4d-%'02d-%'02d", $regs[1], $regs[2], $regs[3]);
    return 1;
}

function isUidValid($uid)
{
    return !preg_match("[^0-9*?.]", $uid);
}

function wildcardReplace($query)
{
    if (strstr($query, '*')) {
        $rep = " LIKE '";
        $rep .= str_replace('*', '%', $query) . "'";
    }
    else if (strstr($query, '?')) {
        $rep = " LIKE '";
        $rep .= str_replace('?', '_', $query) . "'";
    }
    else {
        $rep = "='$query'";
    }
    return $rep;
}

function preparedStmtWildcard($in, &$out)
{
    if (strstr($in, '*')) {
        $rep = " LIKE ?";
        $out = str_replace('*', '%', $in);
    }
    else if (strstr($in, '?')) {
        $rep = " LIKE ?";
        $out = str_replace('?', '_', $in);
    }
    else {
        $rep = "=?";
        $out = $in;
    }
    return $rep;
}

function reverseEmbedDate($string)
{
    $modified = array();
    $tokens = explode(" ", $string);
    foreach ($tokens as $token) {
        if (strstr($token, "-"))
            $token = reverseDate($token);
        $modified[] = $token;
    }
    return implode(" ", $modified);
}

function reverseLogicalExpDate($string)
{
    $modified = array();
    $tokens = explode("%", $string);
    foreach ($tokens as $token) {
        if (strstr($token, "-"))
            $token = reverseEmbedDate($token);
        $modified[] = $token;
    }
    return implode("%", $modified);
}

function escapeHtmlKeepTags($in, $ent = 0)
{
    if (!$ent)
        $ent = ENT_COMPAT | ENT_HTML401;
    $matches = Array();
    $sep = '###HTMLTAG###';
    preg_match_all(":</{0,1}[a-z]+[^>]*>:i", $in, $matches);
    $out = preg_replace(":</{0,1}[a-z]+[^>]*>:i", $sep, $in);
    $out = explode($sep, $out);
    for ($i=0; $i<count($out); $i++)
        $out[$i] = htmlentities($out[$i], $ent, 'UTF-8', false);
    $out = join($sep, $out);
    for ($i=0; $i<count($matches[0]); $i++)
        $out = preg_replace(":$sep:", $matches[0][$i], $out, 1);
    return $out;
}

?>
