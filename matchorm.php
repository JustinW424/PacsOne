<?php
//
// matchorm.php
//
// Tool page for matching received ORM message with received Dicom studies,
// and modify the Accession Number, Patient ID in the Dicom studies with
// those information in the ORM message
//
// CopyRight (c) 2003-2017 RainbowFish Software
//

require_once 'locale.php';
include_once 'security.php';

class MatchORMPage extends TabbedPage {
	var $title;
	var $url;
	var $dbcon;

    function __construct(&$dbcon) {
        $this->title = pacsone_gettext("Match ORM Message");
        $this->url = "tools.php?page=" . urlencode($this->title);
        $this->dbcon = $dbcon;
    }
    function __destruct() { }
    function hl7GetPatientName(&$uid) {
        $name = "";
        $query = "select * from hl7patientname where uuid=?";
        $bindList = array($uid);
        $subres = $this->dbcon->preparedStmt($query, $bindList);
        if ($subrow = $subres->fetch(PDO::FETCH_ASSOC)) {
            if (strlen($subrow["firstname"]))
                $name .= $subrow["firstname"] . " ";
            if (strlen($subrow["middlename"]))
                $name .= $subrow["middlename"] . " ";
            if (strlen($subrow["lastname"]))
                $name .= $subrow["lastname"] . " ";
        }
        return $name;
    }
    function hl7GetUniversalServiceId(&$uid) {
        $id = "";
        $query = "select * from hl7universalserviceid where uuid=?";
        $bindList = array($uid);
        $subres = $this->dbcon->preparedStmt($query, $bindList);
        if ($subrow = $subres->fetch(PDO::FETCH_ASSOC)) {
            if (strlen($subrow["id"]))
                $id .= $subrow["id"];
            if (strlen($subrow["text"]))
                $id .= "^" . $subrow["text"];
        }
        return $id;
    }
    function showHtml() {
        include_once 'toggleRowColor.js';
        global $BGCOLOR;
        global $username;
        global $CUSTOMIZE_PATIENT_ID;
        global $CUSTOMIZE_PATIENT_NAME;
        global $CUSTOMIZE_PATIENT_SEX;
        global $CUSTOMIZE_PATIENT_DOB;
        $filter = 0;
        if (isset($_POST['filter']))
            $filter = $_POST['filter'];
        $studyfilter = 0;
        if (isset($_POST['studyfilter']))
            $studyfilter = $_POST['studyfilter'];
        $lasthour = 6;
        if (isset($_POST['lasthour']))
            $lasthour = $_POST['lasthour'];
        if ((isset($_POST['button']) && !strcasecmp($_POST['button'], "Match")) ||
            isset($_POST['confirm'])) {
            $uid = $_POST['uid'];
            if (isset($_POST['studyuid'])) {
                $studies = $_POST['studyuid'];
                $forward = isset($_POST['forward'])? 1 : 0;
                $destae = $forward? $_POST['destae'] : "_";
                $details = "";
                // match Patient ID
                $query = "select id from hl7patientid where uuid=?";
                $bindList = array($uid);
                $result = $this->dbcon->preparedStmt($query, $bindList);
                $patientid = $result->fetchColumn();
                if (!isset($_POST['confirm'])) {
                    // make sure this Patient ID does not already exist
                    $query = "select * from patient where origid=?";
                    $bindList = array($patientid);
                    $result = $this->dbcon->preparedStmt($query, $bindList);
                    if ($result && $result->rowCount()) {
                        print "<form method=POST action='" . $this->url . "'>\n";
                        print "<input type='hidden' name='uid' value='$uid'>\n";
                        foreach ($studies as $studyUid)
                            print "<input type='hidden' name='studyuid[]' value='$studyUid'>\n";
                        if (isset($_POST['forward']))
                            print "<input type='hidden' name='forward' value='$forward'>\n";
                        if (isset($_POST['destae']))
                            print "<input type='hidden' name='destae' value='$destae'>\n";
                        print "<p><b><font color=red>";
                        printf(pacsone_gettext("%s: [%s] already exists!"), $CUSTOMIZE_PATIENT_ID, $patientid);
                        print "<p>";
                        print pacsone_gettext("Do you want to continue?");
                        print "</b></font>";
                        print "<p><input type='submit' name='confirm' value='" . pacsone_gettext("Confirm") . "'>\n";
                        print "&nbsp;&nbsp;&nbsp;";
                        print "<input type='submit' name='cancel' value='" . pacsone_gettext("Cancel") . "'>\n";
                        print "</form>";
                        return;
                    }
                }
                $details .= "00100020=" . $patientid;
                // match Accession Number
                $query = "select placerfield1 from hl7segobr where uuid=?";
                $bindList = array($uid);
                $result = $this->dbcon->preparedStmt($query, $bindList);
                if ($result && $result->rowCount()) {
                    $details .= "|00080050=" . $result->fetchColumn();
                }
                // match Study Description
                $query = "select text from hl7universalserviceid where uuid=?";
                $result = $this->dbcon->preparedStmt($query, $bindList);
                if ($result && $result->rowCount()) {
                    $details .= "|00081030=" . $result->fetchColumn();
                }
                // match DOB and Sex
                $dateFormat = $this->dbcon->useOracle? "SUBSTR(birthdatetime,1,8)" : "DATE_FORMAT(birthdatetime,'%Y%m%d')";
                $query = "select $dateFormat,sex from hl7segpid where uuid=?";
                $result = $this->dbcon->preparedStmt($query, $bindList);
                if ($result && $result->rowCount()) {
                    $row = $result->fetch(PDO::FETCH_NUM);
                    if (strlen($row[0]))
                        $details .= "|00100030=" . $row[0];
                    if (strlen($row[1]))
                        $details .= "|00100040=" . $row[1];
                }
                // match Patient Name
                $query = "select * from hl7patientname where uuid=?";
                $result = $this->dbcon->preparedStmt($query, $bindList);
                if ($result && $result->rowCount()) {
                    $row = $result->fetch(PDO::FETCH_ASSOC);
                    $name = "";
                    if (strlen($row["lastname"]))
                        $name .= $row["lastname"];
                    $name .= "^";
                    if (strlen($row["firstname"]))
                        $name .= $row["firstname"];
                    $name .= "^";
                    if (strlen($row["middlename"]))
                        $name .= $row["middlename"];
                    $name .= "^";
                    if (strlen($row["prefix"]))
                        $name .= $row["prefix"];
                    $name .= "^";
                    if (strlen($row["suffix"]))
                        $name .= $row["suffix"];
                    $details .= "|00100010=" . $name;
                }
                foreach ($studies as $studyuid) {
                    // schedule a database job to do the modifications
                    $query = "insert into dbjob (username,type,class,uuid,status,submittime,aetitle,details) ";
                    $query .= "values(?,'MatchORM','Study',?,'submitted',";
                    $query .= $this->dbcon->useOracle? "SYSDATE," : "NOW(),";
                    $query .= "?,?)";
                    $bindList = array($username, $studyuid, $destae, $details);
                    $this->dbcon->preparedStmt($query, $bindList);
                    // log this activity
                    $this->dbcon->logJournal($username, "MatchOrm", "Study", $studyuid);
                    // mark this study already been matched
                    $query = "update study set matched=1 where uuid=?";
                    $bindList = array($studyuid);
                    $this->dbcon->preparedStmt($query, $bindList);
                }
                // mark the ORM message already been matched
                $query = "update hl7message set status='matched' where controlid=?";
                $bindList = array($uid);
                $result = $this->dbcon->preparedStmt($query, $bindList);
            } else {
                print "<p><b><font color=red>";
                print pacsone_gettext("You need to select one or more received Dicom studies.");
                print "</b></font>";
            }
        }
        $query = "select controlid from hl7message where type like 'orm%' and status != 'matched' and ";
        $key = "(TO_DAYS(NOW()) = TO_DAYS(received))";
        $bindList = array();
        if ($this->dbcon->useOracle)
            $key = "(TRUNC(SYSDATE) = TRUNC(received))";
        // apply message filter
        if (isset($_POST['button'])) {
            switch ($_POST['filter']) {
                case 1:
                    // messages received yesterday
                    $key = "(TO_DAYS(NOW()) - TO_DAYS(received) = 1)";
                    if ($this->dbcon->useOracle)
                        $key = "(TRUNC(SYSDATE-1) = TRUNC(received))";
                    break;
                case 2:
                    // messages received this week
                    $key = "(WEEK(NOW()) = WEEK(received))";
                    if ($this->dbcon->useOracle)
                        $key = "(TRUNC(SYSDATE,'d') = TRUNC(received,'d'))";
                    break;
                case 3:
                    // messages received this month
                    $key = "(MONTH(NOW()) = MONTH(received))";
                    if ($this->dbcon->useOracle)
                        $key = "(TRUNC(SYSDATE,'mon') = TRUNC(received,'mon'))";
                    break;
                case 4:
                    $sendApp = "";
                    // messages received from sending application
                    $key = "sendingapp" . preparedStmtWildcard($_POST['sendingapp'], $sendApp);
                    $bindList[] = $sendApp;
                    break;
                case 5:
                    // messages received with lastname
                    $query = "select controlid from hl7message left join hl7patientname on hl7patientname.uuid=hl7message.controlid where type like 'orm%' and status != 'matched' and ";
                    $lastname = "";
                    $key = "lastname" . preparedStmtWildcard($_POST['lastname'], $lastname);
                    $bindList[] = $lastname;
                    break;
                case 6:
                    // messages received during the last X hours
                    $key = "(received > SUBTIME(NOW(), SEC_TO_TIME(60 * 60 * $lasthour)))";
                    if ($this->dbcon->useOracle)
                        $key = "(received > (SYSDATE - ($lasthour * 3600) / 86400))";
                    break;
                default:
                    break;
            }
        }
        $query .= $key;
        if (count($bindList))
            $result = $this->dbcon->preparedStmt($query, $bindList);
        else
            $result = $this->dbcon->query($query);
        $numMessages = $result->rowCount();
        print "<form method=POST action='" . $this->url . "'>\n";
        print "<p><table width=100% cellpadding=0 cellspacing=5 border=0>\n";
        // show the ORM message list
        print "<tr><td width=50%><table width=100% cellpadding=5 border=0>\n";
        $dateFormat = $this->dbcon->useOracle? "TO_CHAR(TO_DATE(SUBSTR(birthdatetime,1,8),'YYYYMMDD'),'YYYY-MM-DD')" : "DATE_FORMAT(birthdatetime,'%Y-%m-%d')";
        $columns = array(
            "Patient ID"            => array($CUSTOMIZE_PATIENT_ID, "hl7patientid", "id", "uuid"),
            "Patient Name"          => array($CUSTOMIZE_PATIENT_NAME),
            "Date of Birth"         => array($CUSTOMIZE_PATIENT_DOB, "hl7segpid", $dateFormat, "uuid"),
            "Sex"                   => array($CUSTOMIZE_PATIENT_SEX, "hl7segpid", "sex", "uuid"),
            "Universal Service ID"  => array(pacsone_gettext("Universal Service ID")),
            "Accession Number"      => array(pacsone_gettext("Accession Number"), "hl7segobr", "placerfield1", "uuid"),
            "Sending Application"   => array(pacsone_gettext("Sending Application"), "hl7message", "sendingapp", "controlid"),
        );
        $colspan = count($columns) + 1;
        if ($numMessages == 0) {
            print "<p><tr><td><b>";
            print pacsone_gettext("There is no matching ORM message found.");
            print "</b></td></tr>";
        } else {
            print "<tr><td><table class='table table-hover table-bordered table-striped' width=100% border=0 class='mouseover radiorow'>\n";
            print "<th align=center colspan=$colspan><b>";
            print pacsone_gettext("ORM Messages Received:");
            print "</b></th>\n";
            print "<tr class='tableHeadForBGUp' ><td></td>\n";
            foreach ($columns as $key => $field) {
                print "\t<td><b>" . $field[0] . "</b></td>\n";
            }
            print "</tr>\n";
            $index = 0;
            while ($row = $result->fetch(PDO::FETCH_NUM)) {
                $uid = $row[0];
                print "<tr class='row'><td><input type=radio name='uid' value='$uid'></td>";
                foreach ($columns as $key => $field) {
                    if (strcasecmp($key, "Patient Name") == 0)
                        $value = $this->hl7GetPatientName($uid);
                    else if (strcasecmp($key, "Universal Service ID") == 0)
                        $value = $this->hl7GetUniversalServiceId($uid);
                    else {
                        $table = $field[1];
                        $column = $field[2];
                        $key = $field[3];
                        $subres = $this->dbcon->query("select $column from $table where $key='$uid'");
                        if ($subres && ($subrow = $subres->fetch(PDO::FETCH_NUM))) {
                            $value = strlen($subrow[0])? $subrow[0] : pacsone_gettext("N/A");
                        } else {
                            $value = pacsone_gettext("N/A");
                        }
                    }
                    print "<td>$value</td>";
                }
                print "</tr>\n";
                $index++;
            }
            print "</table></td></tr>\n";
        }
        // message filters
        print "<p><tr><td colspan=$colspan><u>";
        print pacsone_gettext("Filter ORM Message By:") . "</u><p>";
        $checked = ($filter == 6)? "checked" : "";
        print "<input type=radio name='filter' value=6 $checked>";
        print pacsone_gettext("Messages Received During Last ");
        print "<input type=text name='lasthour' size=2 maxlength=2 value='$lasthour'></input>";
        print " Hours<br>";
        $checked = ($filter == 0)? "checked" : "";
        print "<input type=radio name='filter' value=0 $checked>";
        print pacsone_gettext("Messages Received Today") . "<br>";
        $checked = ($filter == 1)? "checked" : "";
        print "<input type=radio name='filter' value=1 $checked>";
        print pacsone_gettext("Messages Received Yesterday") . "<br>";
        $checked = ($filter == 2)? "checked" : "";
        print "<input type=radio name='filter' value=2 $checked>";
        print pacsone_gettext("Messages Received This Week") . "<br>";
        $checked = ($filter == 3)? "checked" : "";
        print "<input type=radio name='filter' value=3 $checked>";
        print pacsone_gettext("Messages Received This Month") . "<br>";
        $checked = ($filter == 4)? "checked" : "";
        print "<input type=radio name='filter' value=4 $checked>";
        print pacsone_gettext("Messages Received From Sending Application: ");
        $value = "";
        if (isset($_POST['sendingapp']) && strlen($_POST['sendingapp'] && strlen($checked)))
            $value = "value='" . $_POST['sendingapp'] . "'";
        print "<input type=text name='sendingapp' size=16 maxlength=32 $value><br>";
        $checked = ($filter == 5)? "checked" : "";
        print "<input type=radio name='filter' value=5 $checked>";
        print pacsone_gettext("Messages With Lastname Like: ");
        $value = "";
        if (isset($_POST['lastname']) && strlen($_POST['lastname']) && strlen($checked))
            $value = "value='" . $_POST['lastname'] . "'";
        print "<input type=text name='lastname' size=8 maxlength=16 $value><p>";
        print "<input class='btn btn-primary' type=submit name='button' value='";
        print pacsone_gettext("Filter") . "'><br>";
        print "</td></tr>";
        print "</table></td>\n";
        // separator
        print "<td width=1 bgcolor=$BGCOLOR><img src=blank.gif width=1 height=1></td>";
        // show the received Dicom studies
        print "<td width=50%><table width=100% cellpadding=5 border=0>\n";
        $columns = array(
            "Patient ID"            => array($CUSTOMIZE_PATIENT_ID, "patientid"),
            "Patient Name"          => array($CUSTOMIZE_PATIENT_NAME),
            "Date of Birth"         => array($CUSTOMIZE_PATIENT_DOB),
            "Sex"                   => array($CUSTOMIZE_PATIENT_SEX),
            "Accession Number"      => array(pacsone_gettext("Accession Number"), "accessionnum"),
            "Source AE"             => array(pacsone_gettext("Source AE"), "sourceae"),
            "Study Date"            => array(pacsone_gettext("Study Date"), "studydate"),
            "Modalities"            => array(pacsone_gettext("Modalities"), ""),
            "Number of Images"      => array(pacsone_gettext("Number of Images"), ""),
        );
        $colspan = count($columns) + 1;
        $query = "select * from study where matched=0 and ";
        $key = "(TO_DAYS(NOW()) = TO_DAYS(received))";
        $bindList = array();
        if ($this->dbcon->useOracle)
            $key = "(TRUNC(SYSDATE) = TRUNC(received))";
        $lastname = "";
        $firstname = "";
        $wildname = isset($_REQUEST['wildname'])? $_REQUEST['wildname'] : false;
        // apply study filter
        if (isset($_POST['button'])) {
            switch ($_POST['studyfilter']) {
                case 1:
                    // studies received yesterday
                    $key = "(TO_DAYS(NOW()) - TO_DAYS(received) = 1)";
                    if ($this->dbcon->useOracle)
                        $key = "(TRUNC(SYSDATE-1) = TRUNC(received))";
                    break;
                case 2:
                    // studies received this week
                    $key = "(WEEK(NOW()) = WEEK(received))";
                    if ($this->dbcon->useOracle)
                        $key = "(TRUNC(SYSDATE,'d') = TRUNC(received,'d'))";
                    break;
                case 3:
                    // studies received this month
                    $key = "(MONTH(NOW()) = MONTH(received))";
                    if ($this->dbcon->useOracle)
                        $key = "(TRUNC(SYSDATE,'mon') = TRUNC(received,'mon'))";
                    break;
                case 4:
                    // studies received from source ae
                    $source = "";
                    $key = "sourceae" . preparedStmtWildcard($_POST['sourceae'], $source);
                    $bindList[] = $source;
                    break;
                case 5:
                    // filter by Patient Name
                    if (isset($_REQUEST['lastname'])) {
                        $lastname = urldecode($_REQUEST['lastname']);
                        if (get_magic_quotes_gpc())
                            $lastname = stripslashes($lastname);
                    }
                    if (isset($_REQUEST['firstname'])) {
                        $firstname = urldecode($_REQUEST['firstname']);
                        if (get_magic_quotes_gpc())
                            $firstname = stripslashes($firstname);
                    }
                    if (preg_match("/[;\"]/", $lastname) || preg_match("/[';\"]/", $firstname)) {
                        $error = sprintf(pacsone_gettext("Invalid First <u>%s</u> or Last Name <u>%s</u>"), $firstname, $lastname);
                        print "<h2><font color=red>$error</font></h2>";
                        exit();
                    }
                    $query = "SELECT DISTINCT * FROM study LEFT JOIN patient ON study.patientid=patient.origid WHERE ";
                    $key = "";
                    if (strlen($firstname)) {
                        $value = $firstname;
                        // automatically append wild-card character
                        if ($wildname)
                            $value .= "*";
                        $key = "firstname" . preparedStmtWildcard($value, $value);
                        $bindList[] = $value;
                    }
                    if (strlen($lastname)) {
                        if (strlen($key))
                            $key .= " AND ";
                        $value = $lastname;
                        // automatically append wild-card character
                        if ($wildname)
                            $value .= "*";
                        $key .= "lastname" . preparedStmtWildcard($value, $value);
                        $bindList[] = $value;
                    }
                    break;
                default:
                    break;
            }
        }
        $query .= $key;
        if (count($bindList))
            $result = $this->dbcon->preparedStmt($query, $bindList);
        else
            $result = $this->dbcon->query($query);
        $numStudies = $result->rowCount();
        if ($numStudies == 0) {
            print "<p><tr><td colspan=$colspan><b>";
            print pacsone_gettext("There is no matching Dicom study found.");
            print "</b></td></tr>";
        } else {
            print "<tr><td><table class='table table-hover table-bordered table-striped' width=100% border=0 class='mouseover optionrow'>";
            print "<th align=center colspan=$colspan><b>";
            print pacsone_gettext("Dicom Studies Received");
            print "</b></th>\n";
            print "<tr class='tableHeadForBGUp'><td></td>\n";
            foreach ($columns as $key => $column) {
                print "\t<td><b>" . $column[0] . "</b></td>\n";
            }
            print "</tr>\n";
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $uid = $row["uuid"];
                $patientid = $row["patientid"];
                $query = "select * from patient where origid=?";
                $bindList = array($patientid);
                $subres = $this->dbcon->preparedStmt($query, $bindList);
                $subrow = $subres->fetch(PDO::FETCH_ASSOC);
                print "<tr class='row'><td><input type=checkbox name='studyuid[]' value=$uid></td>";
                foreach ($columns as $key => $column) {
                    if (strcasecmp($key, "Patient Name") == 0)
                        $value = $this->dbcon->getPatientNameByStudyUid($uid);
                    else if (strcasecmp($key, "Date of Birth") == 0)
                        $value = (isset($subrow["birthdate"]) && strlen($subrow["birthdate"]))? $this->dbcon->formatDate($subrow["birthdate"]) : "N/A";
                    else if (strcasecmp($key, "Sex") == 0)
                        $value = (isset($subrow["sex"]) && strlen($subrow["sex"]))? $subrow["sex"] : "N/A";
                    else if (strcasecmp($key, "Number of Images") == 0) {
                        $size = $count = 0;
                        $this->dbcon->getStudySizeCount($uid, $size, $count);
                        $value = $count;
                    } else if (strcasecmp($key, "Study Date") == 0) {
                        $value = strlen($row["studydate"])? $this->dbcon->formatDate($row["studydate"]) : "N/A";
                    } else if (strcasecmp($key, "Modalities") == 0) {
                        $value = $this->dbcon->getStudyModalities($uid);
                    } else {
                        $value = strlen($row[$column[1]])? $row[$column[1]] : "N/A";
                    }
                    if (!strcasecmp($key, "Patient Name") || !strcasecmp($key, "Patient ID")) {
                        $url = "study.php?patientId=" . urlencode($patientid);
                        $value = "<a href=\"$url\">$value</a>";
                    }
                    print "<td>$value</td>";
                }
                print "</tr>\n";
            }
            print "</table></td></tr>\n";
        }
        // study filters
        print "<p><tr><td colspan=$colspan><u>";
        print pacsone_gettext("Filter Received Dicom Studies By:") . "</u><p>";
        $checked = ($studyfilter == 0)? "checked" : "";
        print "<input type=radio name='studyfilter' value=0 $checked>";
        print pacsone_gettext("Studies Received Today") . "<br>";
        $checked = ($studyfilter == 1)? "checked" : "";
        print "<input type=radio name='studyfilter' value=1 $checked>";
        print pacsone_gettext("Studies Received Yesterday") . "<br>";
        $checked = ($studyfilter == 2)? "checked" : "";
        print "<input type=radio name='studyfilter' value=2 $checked>";
        print pacsone_gettext("Studies Received This Week") . "<br>";
        $checked = ($studyfilter == 3)? "checked" : "";
        print "<input type=radio name='studyfilter' value=3 $checked>";
        print pacsone_gettext("Studies Received This Month") . "<br>";
        $checked = ($studyfilter == 4)? "checked" : "";
        print "<input type=radio name='studyfilter' value=4 $checked>";
        print pacsone_gettext("Studies Received From Source AE Title: ");
        $value = "";
        if (isset($_POST['sourceae']) && strlen($_POST['sourceae']) && strlen($checked))
            $value = "value='" . $_POST['sourceae'] . "'";
        print "<input type=text name='sourceae' size=16 maxlength=16 $value><br>";
        $checked = ($studyfilter == 5)? "checked" : "";
        print "<input type=radio name='studyfilter' value=5 $checked>";
        print pacsone_gettext("Patient with Lastname: ");
        print "<input type=text name='lastname' value='$lastname'>&nbsp;";
        print pacsone_gettext("Firstname: ");
        print "<input type=text name='firstname' value='$firstname'><br>";
        $checked = $wildname? "checked" : "";
        print "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type='checkbox' name='wildname' value=1 $checked>";
        print pacsone_gettext("Append wild-card character '<b>*</b>' to search pattern");
        print "<p><input class='btn btn-primary' type=submit name='button' value='";
        print pacsone_gettext("Filter");
        print "'><br>";
        print "</table></td>\n";
        print "</tr>";
        // usage description
        print "<tr><td colspan=3>&nbsp;</td></tr>\n";
        print "<tr><td colspan=3><b>" . pacsone_gettext("NOTE") . "</b>: ";
        global $PRODUCT;
        printf(pacsone_gettext("With this matching of selected ORM message and received Dicom studies, %s will modify the following data elements in the received Dicom studies with the information contained in the selected ORM message"), $PRODUCT);
        print "<br><ul>";
        print "<li>$CUSTOMIZE_PATIENT_ID</li>";
        print "<li>$CUSTOMIZE_PATIENT_NAME</li>";
        print "<li>$CUSTOMIZE_PATIENT_DOB</li>";
        print "<li>$CUSTOMIZE_PATIENT_SEX</li>";
        print "<li>" . pacsone_gettext("Accession Number") . "</li>";
        print "<li>" . pacsone_gettext("Study Description") . "</li>";
        print "</ul></td></tr>";
        print "</table>\n";
        // show option to forward modified Dicom studies to a destination AE
        if ($numMessages && $numStudies) {
            $result = $this->dbcon->query("select title from applentity where port is not NULL order by title asc");
            if ($result->rowCount()) {
                print "<br><input type=checkbox name='forward'>";
                print pacsone_gettext(" Forward modified Dicom studies to this destination AE: ");
                print "<select name='destae'>";
                $index = 0;
                while ($title = $result->fetchColumn()) {
                    $selected = ($index == 0)? "selected" : "";
                    print "<option $selected>" . $title;
                    $index++;
                }
                print "</select>\n";
            }
            print "<p><input class='btn btn-primary' type=submit name='button' value='";
            print pacsone_gettext("Match") . "' title='";
            print pacsone_gettext("Match selected ORM message with Dicom studies");
            print "'>";
        }
        print "</form>\n";
    }
}

?>
