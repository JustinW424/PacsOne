<?php
//
// statistics.php
//
// Module for generating statistics
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
include_once 'sharedData.php';
require_once 'utils.php';
require_once 'locale.php';

function generateStats(&$dbcon, $type, &$images, &$totalSize, $from, $to, $sourceae, $institution = "", $reviewer = "")
{
    global $ONE_DAY;
    $bindList = array();
    $rows = array();
    $images = 0;
    $totalSize = 0;
    $query = "SELECT DISTINCT * FROM study LEFT JOIN patient ON study.patientid = patient.origid WHERE ";
    if ($type == 0) {           // studies received yesterday
        if ($dbcon->useOracle)
            $query .= "TRUNC(SYSDATE-1) = TRUNC(received)";
        else
            $query .= "(TO_DAYS(NOW()) - TO_DAYS(received)) = 1";
    } else if ($type == 1) {    // studies received this week
        $since = getdate();
        $since = date("Ymd", $since[0] - $since['wday'] * $ONE_DAY);
        if ($dbcon->useOracle)
            $since = "TO_DATE('$since','YYYYMMDD')";
        $query .= "received >= $since";
    } else if ($type == 2) {    // studies received this month
        $since = getdate();
        $since = date("Ymd", $since[0] - ($since['mday'] - 1) * $ONE_DAY);
        if ($dbcon->useOracle)
            $since = "TO_DATE('$since','YYYYMMDD')";
        $query .= "received >= $since";
    } else if ($type == 3) {    // studies received this year
        $since = getdate();
        $since = date("Ymd", $since[0] - $since['yday'] * $ONE_DAY);
        if ($dbcon->useOracle)
            $since = "TO_DATE('$since','YYYYMMDD')";
        $query .= "received >= $since";
    } else if ($type == 4) {    // studies received from the specified date range
        if ($from == null)
            $from = $_POST['from'];
        if ($to == null)
            $to = $_POST['to'];
        if ($dbcon->isEuropeanDateFormat()) {
            $from = reverseDate($from);
            $to = reverseDate($to);
        }
        $from = strtotime($from);
        $to = strtotime($to);
        if ($to < $from)
            die("<h3><font color=red><b>TO</b> date must be equal or newer than <b>FROM</b> date!</font></h3>");
        if ($dbcon->useOracle) {
            $from = "TO_DATE('$from','YYYYMMDD')";
            $to = "TO_DATE('$to','YYYYMMDD')";
            $query .= "TO_DATE(received,'YYYYMMDD') >= $from AND TO_DATE(received,'YYYYMMDD') <= $to";
        } else {
            $from = date("Ymd", $from);
            $to = date("Ymd", $to);
            $query .= "DATE(received) >= $from AND DATE(received) <= $to";
        }
    } else if ($type == 5) {    // studies received from the specified source AE
        if (strlen($sourceae) == 0) {
            print "<h3><font color=red>";
            print pacsone_gettext("You must enter a Source AE Title!");
            print "</font></h3>";
            exit();
        }
        if (($from == null) && isset($_POST['from']))
            $from = $_POST['from'];
        if (($to == null) && isset($_POST['to']))
            $to = $_POST['to'];
        if (strlen($from) && strlen($to)) {
            if ($dbcon->isEuropeanDateFormat()) {
                $from = reverseDate($from);
                $to = reverseDate($to);
            }
            $from = strtotime($from);
            $to = strtotime($to);
            if ($to < $from)
                die("<h3><font color=red><b>TO</b> date must be equal or newer than <b>FROM</b> date!</font></h3>");
            if ($dbcon->useOracle) {
                $from = "TO_DATE('$from','YYYYMMDD')";
                $to = "TO_DATE('$to','YYYYMMDD')";
                $query .= "(TO_DATE(received,'YYYYMMDD') >= $from AND TO_DATE(received,'YYYYMMDD') <= $to) AND ";
            } else {
                $from = date("Ymd", $from);
                $to = date("Ymd", $to);
                $query .= "(DATE(received) >= $from AND DATE(received) <= $to) AND ";
            }
        }
        if ($dbcon->isAeGroup($sourceae)) {
            $members = array();
            $subList = array($sourceae);
            $applentity = $dbcon->preparedStmt("select memberae from aegroup where aetitle=?", $subList);
            while ($applentity && ($aerow = $applentity->fetch(PDO::FETCH_NUM))) {
                $members[] = $aerow[0];
            }
            $total = count($members);
            if ($total) {
                $query .= "(";
                for ($i = 0; $i < $total; $i++) {
                    $member = $members[$i];
                    $query .= "sourceae = '$member'";
                    if ($i + 1 < $total)
                        $query .= " || ";
                }
                $query .= ")";
            }
        } else {
            $query .= "sourceae" . preparedStmtWildcard($sourceae, $sourceae);
            $bindList[] = $sourceae;
        }
    } else if ($type == 6) {    // studies received from each source AE defined in "Dicom AE" page
        $applentity = $dbcon->query("select title from applentity where aegroup=0 order by lower(title) asc");
        while ($applentity && ($aetitle = $applentity->fetchColumn())) {
            $subq = $query . "sourceae" . preparedStmtWildcard($aetitle, $aetitle);
            $subList = array($aetitle);
            $result = $dbcon->preparedStmt($subq, $subList);
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $size = 0;
                $count = 0;
                $dbcon->getStudySizeCount($row['uuid'], $size, $count);
                // skip 0-sized studies
                if (!$count || !$size)
                    continue;
                $images += $count;
                $totalSize += $size;
                $row['images'] = $count;
                $row['size'] = $size;
                $rows[] = $row;
            }
        }
        return $rows;
    } else if ($type == 7) {    // studies received from this Institution Name
        $query .= "patient.institution" . preparedStmtWildcard($institution, $institution);
        $bindList[] = $institution;
    } else if ($type == 8) {    // studies reviewed by this web user
        $tokens = explode(" - ", $reviewer);
        $user = count($tokens)? $tokens[0] : "";
        $query .= "reviewed=?";
        $bindList[] = $user;
    } else {
        die("<h3><font color=red>Invalid report type: $type!</font></h3>");
    }
    if (count($bindList))
        $result = $dbcon->preparedStmt($query, $bindList);
    else
        $result = $dbcon->query($query);
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $size = 0;
        $count = 0;
        $dbcon->getStudySizeCount($row['uuid'], $size, $count);
        // skip 0-sized studies
        if (!$count || !$size)
            continue;
        $images += $count;
        $totalSize += $size;
        $row['images'] = $count;
        $row['size'] = $size;
        $rows[] = $row;
    }
    return $rows;
}

function emailStats($html, &$subject, &$dbcon, &$rows, $images, $totalSize)
{
    global $BGCOLOR;
    global $CUSTOMIZE_PATIENT_ID;
    global $CUSTOMIZE_PATIENT_NAME;
    $NL = ($html)? "<br>" : "\n";
    if ($html) {
        $msg = "<html>";
        $msg .= "<head><title>$subject</title></head>";
        $msg .= "<body>";
        $msg .= "<p>$subject<p>";
    } else
        $msg = "$subject" . $NL;
    $count = count($rows);
    $size = $dbcon->displayFileSize($totalSize);
    $msg .= $NL;
    $msg .= sprintf(pacsone_gettext("Total of %d studies, %d images of %s"), $count, $images, $size);
    $msg .= $NL;
    // display the following columns: column name <=> database field
    $columns = array(
        pacsone_gettext("Study ID")                 => "id",
        pacsone_gettext("Date")                     => "studydate",
        pacsone_gettext("Modalities")               => "modalities",
        pacsone_gettext("Accession Number")         => "accessionnum",
        pacsone_gettext("Received On")              => "received",
        pacsone_gettext("Source AE")                => "sourceae",
        pacsone_gettext("Images")                   => "images",
        pacsone_gettext("Total Size")               => "size");
    if ($html) {
        $msg .= "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">";
        $msg .= "<tr bgcolor=$BGCOLOR>";
        $msg .= "<td><b>$CUSTOMIZE_PATIENT_ID</b></td>";
        $msg .= "<td><b>$CUSTOMIZE_PATIENT_NAME</b></td>";
        foreach (array_keys($columns) as $key)
            $msg .= "<td><b>$key</b></td>";
        $msg .= "</tr>";
        foreach ($rows as $row) {
            $uid = $row["uuid"];
            $msg .= "<tr>";
                $value = $row["patientid"];
            $msg .= "<td>$value</td>";
            $patName = $dbcon->getPatientName($value);
            $msg .= "<td>$patName</td>";
            foreach ($columns as $key => $field) {
                $value = $row[$field];
                if (strcasecmp($field, "size") == 0) {
                    $value = $dbcon->displayFileSize($value);
                } else if (strcasecmp($field, "modalities") == 0) {
                    if (strlen($value) == 0)
                        $value = $dbcon->getStudyModalities($uid);
                } else if (strcasecmp($field, "studydate") == 0) {
                    if (strlen($value))
                        $value = $dbcon->formatDate($value);
                } else if (!strcasecmp($field, "id")) {
                    $site = $dbcon->getExternalAccessUrl();
                    if (strlen($site)) {
                        // embed URL link to access this study
                        $url = $site . "series.php?patientId=" . urlencode($row['patientid']) . "&studyId=$uid";
                        $value = "<a href='$url'>$value</a>";
                    }
                }
                if (!isset($value) || !strlen($value)) {
                    $value = pacsone_gettext("N/A");
                }
    	        $msg .= "<td>$value</td>";
            }
            $msg .= "</tr>";
        }
        $msg .= "</table>";
        $msg .= "<br>";
        $msg .= sprintf(pacsone_gettext("Total of %d studies, %d images of %s"), $count, $images, $size);
        $msg .= "</body></html>";
    } else {
        $msg .= "--------------------------------------------------------------------------------\n";
        $msg .= $CUSTOMIZE_PATIENT_ID;
        $msg .= "|";
        $msg .= $CUSTOMIZE_PATIENT_NAME;
        foreach (array_keys($columns) as $key)
            $msg .= "|$key";
        $msg .= $NL;
        $msg .= "--------------------------------------------------------------------------------\n";
        foreach ($rows as $row) {
            $uid = $row["uuid"];
            $value = $row["patientid"];
            $msg .= "$value";
            $patName = $dbcon->getPatientName($value);
            $msg .= "|$patName";
            foreach ($columns as $key => $field) {
                $value = $row[$field];
                if (strcasecmp($field, "size") == 0) {
                    $value = $dbcon->displayFileSize($value);
                } else if (strcasecmp($field, "modalities") == 0) {
                    if (strlen($value) == 0)
                        $value = $dbcon->getStudyModalities($uid);
                } else if (strcasecmp($field, "studydate") == 0) {
                    if (strlen($value))
                        $value = $dbcon->formatDate($value);
                }
                if (!isset($value) || !strlen($value)) {
                    $value = pacsone_gettext("N/A");
                }
                $msg .= "|$value";
            }
            $msg .= "\n";
        }
        $msg .= "--------------------------------------------------------------------------------\n";
        $msg .= $NL;
        $msg .= sprintf(pacsone_gettext("Total of %d studies, %d images of %s\n"), $count, $images, $size);
    }
    return $msg;
}

function emailReport($html, &$preface, &$dbcon, &$uids)
{
    global $BGCOLOR;
    global $CUSTOMIZE_PATIENT_ID;
    global $CUSTOMIZE_PATIENT_NAME;
    $NL = ($html)? "<br>" : "\n";
    if ($html) {
        $msg = "<html>";
        $msg .= "<head><title>$preface</title></head>";
        $msg .= "<body>";
        $msg .= "<p>$preface<p>";
    } else
        $msg = "$preface" . $NL;
    $count = count($uids);
    $images = 0;
    $totalSize = 0;
    // display the following columns: column name <=> database field
    $columns = array(
        pacsone_gettext("Study ID")                 => "id",
        pacsone_gettext("Date")                     => "studydate",
        pacsone_gettext("Modalities")               => "modalities",
        pacsone_gettext("Accession Number")         => "accessionnum",
        pacsone_gettext("Received On")              => "received",
        pacsone_gettext("Source AE")                => "sourceae",
        pacsone_gettext("Images")                   => "images",
        pacsone_gettext("Total Size")               => "size");
    if ($html) {
        $msg .= "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">";
        $msg .= "<tr bgcolor=$BGCOLOR>";
        $msg .= "<td><b>$CUSTOMIZE_PATIENT_ID</b></td>";
        $msg .= "<td><b>$CUSTOMIZE_PATIENT_NAME</b></td>";
        foreach (array_keys($columns) as $key)
            $msg .= "<td><b>$key</b></td>";
        $msg .= "</tr>";
        foreach ($uids as $uid) {
            $bindList = array($uid);
            $result = $dbcon->preparedStmt("select * from study where uuid=?", $bindList);
            $row = $result->fetch(PDO::FETCH_ASSOC);
            $size = 0;
            $instances = 0;
            $dbcon->getStudySizeCount($uid, $size, $instances);
            $row['images'] = $instances;
            $row['size'] = $size;
            $totalSize += $size;
            $images += $instances;
            $msg .= "<tr>";
            $value = $row["patientid"];
            $msg .= "<td>$value</td>";
            $patName = $dbcon->getPatientName($value);
            $msg .= "<td>$patName</td>";
            foreach ($columns as $key => $field) {
                $value = $row[$field];
                if (strcasecmp($field, "size") == 0) {
                    $value = $dbcon->displayFileSize($value);
                } else if (strcasecmp($field, "modalities") == 0) {
                    if (strlen($value) == 0)
                        $value = $dbcon->getStudyModalities($uid);
                } else if (strcasecmp($field, "studydate") == 0) {
                    if (strlen($value))
                        $value = $dbcon->formatDate($value);
                } else if (!strcasecmp($field, "id")) {
                    $site = $dbcon->getExternalAccessUrl();
                    if (strlen($site)) {
                        // embed URL link to access this study
                        $url = $site . "series.php?patientId=" . urlencode($row['patientid']) . "&studyId=$uid";
                        $value = "<a href='$url'>$value</a>";
                    }
                }
                if (!isset($value) || !strlen($value)) {
                    $value = pacsone_gettext("N/A");
                }
    	          $msg .= "<td>$value</td>";
            }
            $msg .= "</tr>";
        }
        $msg .= "</table>";
        $msg .= "<br>";
        $msg .= sprintf(pacsone_gettext("Total of %d studies, %d images of %s"), $count, $images, $dbcon->displayFileSize($totalSize));
        $msg .= "</body></html>";
    } else {
        $msg .= "--------------------------------------------------------------------------------\n";
        $msg .= $CUSTOMIZE_PATIENT_ID;
        $msg .= "|";
        $msg .= $CUSTOMIZE_PATIENT_NAME;
        foreach (array_keys($columns) as $key)
            $msg .= "|$key";
        $msg .= $NL;
        $msg .= "--------------------------------------------------------------------------------\n";
        foreach ($uids as $uid) {
            $bindList = array($uid);
            $result = $dbcon->preparedStmt("select * from study where uuid=?", $bindList);
            $row = $result->fetch(PDO::FETCH_ASSOC);
            $size = 0;
            $instances = 0;
            $dbcon->getStudySizeCount($uid, $size, $instances);
            $row['images'] = $instances;
            $row['size'] = $size;
            $totalSize += $size;
            $images += $instances;
            $value = $row["patientid"];
            $msg .= "$value";
            $patName = $dbcon->getPatientName($value);
            $msg .= "|$patName";
            foreach ($columns as $key => $field) {
                $value = $row[$field];
                if (strcasecmp($field, "size") == 0) {
                    $value = $dbcon->displayFileSize($value);
                } else if (strcasecmp($field, "modalities") == 0) {
                    if (strlen($value) == 0)
                        $value = $dbcon->getStudyModalities($uid);
                } else if (strcasecmp($field, "studydate") == 0) {
                    if (strlen($value))
                        $value = $dbcon->formatDate($value);
                }
                if (!isset($value) || !strlen($value)) {
                    $value = pacsone_gettext("N/A");
                }
                $msg .= "|$value";
            }
            $msg .= "\n";
        }
        $msg .= "--------------------------------------------------------------------------------\n";
        $msg .= sprintf(pacsone_gettext("\nTotal of %d studies, %d images of %s\n"), $count, $images, $dbcon->displayFileSize($totalSize));
    }
    return $msg;
}

function emailJournal($html, &$subject, &$dbcon, &$rows)
{
    global $BGCOLOR;
    $NL = ($html)? "<br>" : "\n";
    if ($html) {
        $msg = "<html>";
        $msg .= "<head><title>$subject</title></head>";
        $msg .= "<body>";
        $msg .= "<p>$subject<p>";
    } else
        $msg = "$subject" . $NL;
    // display the following columns: column name <=> database field
    $columns = array(
        pacsone_gettext("When")           => "timestamp",
        pacsone_gettext("Username")       => "username",
        pacsone_gettext("Operation")      => "did",
        pacsone_gettext("Level")          => "what",
        pacsone_gettext("UID")            => "uuid",
        pacsone_gettext("Details")        => "details",
    );
    if ($html) {
        $msg .= "<table width=\"100%\" border=\"1\" cellspacing=\"0\" cellpadding=\"0\">";
        $msg .= "<tr bgcolor=$BGCOLOR>";
        foreach (array_keys($columns) as $key)
            $msg .= "<td><b>$key</b></td>";
        $msg .= "</tr>";
        foreach ($rows as $row) {
            foreach ($columns as $key => $field) {
                $value = $row[$field];
                if (!isset($value) || !strlen($value)) {
                    $value = pacsone_gettext("N/A");
                } else if (strcasecmp($field, "timestamp") == 0) {
                    $value = $dbcon->formatDateTime($value);
                }
    	        $msg .= "<td>$value</td>";
            }
            $msg .= "</tr>";
        }
        $msg .= "</table>";
        $msg .= "</body></html>";
    } else {
        $msg .= "--------------------------------------------------------------------------------\n";
        $count = 1;
        foreach (array_keys($columns) as $key) {
            $msg .= "$key";
            if ($count++ < count($columns))
                $msg .= "|";
        }
        $msg .= $NL;
        $msg .= "--------------------------------------------------------------------------------\n";
        foreach ($rows as $row) {
            $count = 1;
            foreach ($columns as $key => $field) {
                $value = $row[$field];
                if (strcasecmp($field, "details") == 0) {
                    $value = str_replace("<br>", ",", $value);
                }
                else if (!isset($value) || !strlen($value)) {
                    $value = pacsone_gettext("N/A");
                } else if (strcasecmp($field, "timestamp") == 0) {
                    $value = $dbcon->formatDateTime($value);
                }
                $msg .= "$value";
                if ($count++ < count($columns))
                    $msg .= "|";
            }
            $msg .= "\n";
        }
        $msg .= "--------------------------------------------------------------------------------\n";
    }
    return $msg;
}

?>
