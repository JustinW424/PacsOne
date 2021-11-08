<?php
//
// display.php
//
// Module for displaying database tables
//
// CopyRight (c) 2003-2020 RainbowFish Software
//

require_once 'locale.php';
include_once "checkUncheck.js";
include_once "sharedData.php";
include_once "applet.php";
include_once "xferSyntax.php";

function displayPageControl($what, &$list, &$preface, &$url, &$offset, $all)
{
    global $dbcon;
    $total = count($list);
    $pageSize = $dbcon->getPageSize();
    if (strcasecmp($what, pacsone_gettext("Instances")) == 0) {
        global $IMAGE_MATRIX;
        $xdim = $IMAGE_MATRIX % 10;
        $ydim = (int)($IMAGE_MATRIX / 10);
        $pageSize = $xdim * $ydim;
    }
    $page = ($all)? $total : $pageSize;
    // build a page of entries to be displayed
    $rows = array();
    for ($count = 0; ($count < $page) && ($count < $total) && (($count + $offset) < $total); $count++) {
        $rows[] = $list[$offset+$count];
    }
    print "<table class=\"table\" style='width:100%; margin-bottom:0px'>\n";
    print "<tr><td>$preface</td>\n";  
    //print "<tr><td colspan=2><br></td></tr>\n";
     print "<td align=right>\n";
        if (!$all && ($total > $page)) {
            $link = urlReplace($url, "all", 1);
            print "<a href=\"$link\">";
            print pacsone_gettext("Display All");
            print "</a>&nbsp;&nbsp;&nbsp;";
        }
        else if ($all) {
            $link = urlReplace($url, "all", 0);
            print "<a href=\"$link\">";
            print pacsone_gettext("Paginate");
            print "</a>&nbsp;&nbsp;&nbsp;";
        }
        $start = ($total)? ($offset+1) : 0;
        printf(pacsone_gettext("Displaying %d-%d of %d %s:"), $start, $count+$offset, $total, $what);
        print "</td></tr>\n";
    print "</table>\n";
    
    if ($total) {
        // display Previous, Next and Page Number links
        $pagination_html = "";
        $and = (strrpos($url, "?") == false)? "?" : "&";
        $previous = $offset - $page;
        if ($offset > 0) {
            $pagination_html .= "<a href=\"$url" . $and . "offset=" . urlencode($previous) . "\">";
            $pagination_html .= pacsone_gettext("Previous");
            $pagination_html .= "</a> ";
        } else {
            $pagination_html .= pacsone_gettext("Previous ");
        }
        if ($total > $page) {
            $start = $offset - 10 * $pageSize;
            if ($start < 0)
                $start = 0;
            $end = $offset + 10 * $pageSize;
            if ($end > $total)
                $end = $total;
            for ($i = $start, $p = ($i / $pageSize + 1); $i < $end; $i += $page, $p++) {
                if ($i < $offset || $i > ($offset+$page-1))
                    $pagination_html .= "<a href=\"$url" . $and . "offset=" . urlencode($i) . "\">$p</a> ";
                else
                    $pagination_html .= "$p ";
            }
        }
        $next = $offset + $page;
        if ($total > $next) {
            $pagination_html .= "<a href=\"$url" . $and . "offset=" . urlencode($next) . "\">";
            $pagination_html .= pacsone_gettext("Next");
            $pagination_html .= "</a> ";
        } else {
            $pagination_html .= pacsone_gettext("Next ");
        }
    }

    $url = urlReplace($url, "all", $all);
    $data = array();
    $data["rows"] = $rows;
    $data["pagination"] = $pagination_html;
    return $data;
}

function displayButtons($level, &$buttons, $hidden, $checkButton = 1, $pagination)
{
    // add by rina  2021.11.06
    
    print "<table class='table' style='width:100%; margin-bottom:0px'>
            <tr>
            <td style='width:62%'>\n";
    print "<div class=\"btn-group\">\n";

    $check = pacsone_gettext("Check All");
    $uncheck = pacsone_gettext("Uncheck All");
    print "<input type='hidden' name='actionvalue'>\n";
    $ajaxButton = false;
    
    if ($checkButton)
        print "<input type=\"button\" class=\"btn btn-primary\" value='$check' name='checkUncheck' onClick='checkAll(this.form,\"entry\", \"$check\", \"$uncheck\")'></button>\n";


    foreach ($buttons as $key => $values) {
        $text = $values[0];
        $title = $values[1];
        $access = $values[2];
        if ($access) {
            $line = "<input type=\"submit\"  class=\"btn btn-primary\" value='$text' name='action' title='$title' ";
            // applet-specific pre-Show handler
            if (strcasecmp($key, "Show") == 0) {
                require "applet.js";
                $handler = "switchText(this.form,\"actionvalue\",\"$key\"); return appletPreShow()";
            } else {
                $handler = "switchText(this.form,\"actionvalue\",\"$key\")";
            }
            if (strcasecmp($key, "Delete") == 0) {
                $confirm = pacsone_gettext("Are you sure?");
                print "<input type='hidden' name='confirm' value='$confirm'>\n";
            }
            if (strcasecmp($key, "Show Filters") == 0) {
                $line = "<input type=\"button\" class=\"btn btn-primary\" value='$text' id='filterButton' title='$title' ";
                $show = pacsone_gettext("Show Filters");
                $hide = pacsone_gettext("Hide Filters");
                $handler = "toggleFilter(this.form, \"$show\", \"$hide\");return false;";
            } else if (stristr($key, "Download") || stristr($key, "Delete")) {
                require_once "ajaxLoader.js";
                if (stristr($key, "Download"))
                    $className = strcasecmp($key, "Download")? "ajaxbuttonJPG" : "ajaxbuttonDicom";
                else
                    $className = "ajaxbuttonDelete";
                $line = "<input type=\"button\" class=\"btn btn-primary\" value='$text' title='$title' class='$className' ";
                $ajaxButton = true;
            }
            $line .= "onclick='$handler'>\n";
            print $line;
        }
    }
    print "</div></td>
            <td style='line-height:30px'>
            $pagination</td>\n";
    print "<td style='float:right'><input class='form-control' id='myInput' type='text' placeholder='Search..'></td>\n";
    print "</tr></table>\n";
    

    /*
    print "<p><table width=20% border=0 cellspacing=0 cellpadding=5>\n";
    print "<tr>\n";
    $check = pacsone_gettext("Check All");
    $uncheck = pacsone_gettext("Uncheck All");
    print "<input type='hidden' name='actionvalue'>\n";
    $ajaxButton = false;
    if ($checkButton)
        print "<td><input type=button value='$check' name='checkUncheck' onClick='checkAll(this.form,\"entry\", \"$check\", \"$uncheck\")'></td>\n";
    foreach ($buttons as $key => $values) {
        $text = $values[0];
        $title = $values[1];
        $access = $values[2];
        if ($access) {
            $line = "<td><input type=submit value='$text' name='action' title='$title' ";
            // applet-specific pre-Show handler
            if (strcasecmp($key, "Show") == 0) {
                require "applet.js";
                $handler = "switchText(this.form,\"actionvalue\",\"$key\"); return appletPreShow()";
            } else {
                $handler = "switchText(this.form,\"actionvalue\",\"$key\")";
            }
            if (strcasecmp($key, "Delete") == 0) {
                $confirm = pacsone_gettext("Are you sure?");
                print "<input type='hidden' name='confirm' value='$confirm'>\n";
            }
            if (strcasecmp($key, "Show Filters") == 0) {
                $line = "<td><input type=button value='$text' id='filterButton' title='$title' ";
                $show = pacsone_gettext("Show Filters");
                $hide = pacsone_gettext("Hide Filters");
                $handler = "toggleFilter(this.form, \"$show\", \"$hide\");return false;";
            } else if (stristr($key, "Download") || stristr($key, "Delete")) {
                require_once "ajaxLoader.js";
                if (stristr($key, "Download"))
                    $className = strcasecmp($key, "Download")? "ajaxbuttonJPG" : "ajaxbuttonDicom";
                else
                    $className = "ajaxbuttonDelete";
                $line = "<td><input type=button value='$text' title='$title' class='$className' ";
                $ajaxButton = true;
            }
            $line .= "onclick='$handler'></td>\n";
            print $line;
        }
    }
    print "<td><input type=hidden value='$level' name='option'></td>\n";
    if (isset($hidden)) {
        foreach ($hidden as $name => $value) {
            print "<input type=hidden value='$value' name='$name'>\n";
        }
    }
    print "</tr>\n";
    if ($ajaxButton) {
        print "<tr>";
        print "<td colspan=6><div style=\"display:none\" id=\"preloader\"><img src=\"ajax-loader.gif\"/>";
        print "<br><h3>";
        print pacsone_gettext("Please wait while your request is being processed...");
        print "</h3></div>";
        print "<div id=\"resultDiv\" style=\"display:none\"></div></td>";
        print "</tr>\n";
    }
    print "</table>\n";
    */
}

function displayPatients($list, $preface, $url, $offset, $all, $duplicates = 0)
{
    include_once 'toggleRowColor.js';
    global $MYFONT;
    global $dbcon;
    global $CUSTOMIZE_PATIENTS;
    global $BGCOLOR;
    $rows = displayPageControl($CUSTOMIZE_PATIENTS, $list, $preface, $url, $offset, $all);
    // check if Patient Reconciliation is enabled
    $matchWorklist = 0;
    $config = $dbcon->query("select matchworklist from config");
    if ($config && ($configRow = $config->fetch(PDO::FETCH_NUM))) {
        $matchWorklist = $configRow[0];
    }
    // check user privileges
    $checkbox = 0;
	$username = $dbcon->username;
	$modifyAccess = $dbcon->hasaccess("modifydata", $username);
	$forwardAccess = $dbcon->hasaccess("forward", $username);
	$downloadAccess = $dbcon->hasaccess("download", $username);
    $printAccess = $dbcon->hasaccess("print", $username);
    if ($printAccess) {
        $printers = $dbcon->query("select printscp from applentity where printscp=1");
        if ($printers && $printers->rowCount() == 0)
            $printAccess = 0;
    }
    $exportAccess = $dbcon->hasaccess("export", $username);
    // check if Java Applet viewer exists
    $showDicom = 0;
    if (appletExists()) {
        $showDicom = 1;
    }
    if (($modifyAccess + $forwardAccess + $printAccess + $exportAccess + $downloadAccess + $showDicom) && sizeof($rows)) {
        $checkbox = 1;
        $buttons = array(
            'Forward'   => array(pacsone_gettext('Forward'), sprintf(pacsone_gettext('Forward checked %s'), $CUSTOMIZE_PATIENTS), $forwardAccess),
            'Delete'    => array(pacsone_gettext('Delete'), sprintf(pacsone_gettext('Delete checked %s'), $CUSTOMIZE_PATIENTS), $modifyAccess),
            'Print'     => array(pacsone_gettext('Print'), sprintf(pacsone_gettext('Print checked %s'), $CUSTOMIZE_PATIENTS), $printAccess),
            'Export'    => array(pacsone_gettext('Export'), sprintf(pacsone_gettext('Export checked %s'), $CUSTOMIZE_PATIENTS), $exportAccess),
            'Show'      => array(pacsone_gettext('Show'), sprintf(pacsone_gettext('Show images of checked %s'), $CUSTOMIZE_PATIENTS), $showDicom),
            'Download'  => array(pacsone_gettext('Download'), sprintf(pacsone_gettext('Download images of checked %s'), $CUSTOMIZE_PATIENTS), $downloadAccess),
        );
        if ($dbcon->getAutoConvertJPG()) {
            $buttons['Download JPG'] = array(pacsone_gettext('Download JPG'), pacsone_gettext('Download converted JPG/GIF images of checked patients'), $downloadAccess);
        }
        if ($duplicates) {
            $buttons['Apply Duplicate Patient ID Filter'] = array(pacsone_gettext('Apply Duplicate Patient ID Filter'), pacsone_gettext('Apply Selected Duplicate Patient ID Display Filter'), $modifyAccess);
        }
        print "<form method='POST' action='actionItem.php'>\n";
        if ($duplicates) {
            global $DUPLICATE_FILTER_NONE;
            global $DUPLICATE_FILTER_THIS_WEEK;
            global $DUPLICATE_FILTER_THIS_MONTH;
            global $DUPLICATE_FILTER_THIS_YEAR;
            global $DUPLICATE_FILTER_DATE_RANGE;
            print "<table width=100% border=0 cellpadding=5>\n";
            print "<tr class=listhead bgcolor=$BGCOLOR><td>\n";
            print pacsone_gettext("Limit the display of Duplicate Patient IDs by the following filter:");
            print "</td></tr>";
            print "<tr class=listhead><td>\n";
            $filter = isset($_REQUEST['dupfilter'])? $_REQUEST['dupfilter'] : $DUPLICATE_FILTER_NONE;
            $checked = ($filter == $DUPLICATE_FILTER_NONE)? "checked" : "";
            print "<p><input type='radio' name='dupfilter' value=$DUPLICATE_FILTER_NONE $checked>";
            print pacsone_gettext("None");
            $checked = ($filter == $DUPLICATE_FILTER_THIS_WEEK)? "checked" : "";
            print "<br><input type='radio' name='dupfilter' value=$DUPLICATE_FILTER_THIS_WEEK $checked>";
            print pacsone_gettext("Only Show Duplicate Patient IDs Received This Week");
            $checked = ($filter == $DUPLICATE_FILTER_THIS_MONTH)? "checked" : "";
            print "<br><input type='radio' name='dupfilter' value=$DUPLICATE_FILTER_THIS_MONTH $checked>";
            print pacsone_gettext("Only Show Duplicate Patient IDs Received This Month");
            $checked = ($filter == $DUPLICATE_FILTER_THIS_YEAR)? "checked" : "";
            print "<br><input type='radio' name='dupfilter' value=$DUPLICATE_FILTER_THIS_YEAR $checked>";
            print pacsone_gettext("Only Show Duplicate Patient IDs Received This Year");
            $checked = ($filter == $DUPLICATE_FILTER_DATE_RANGE)? "checked" : "";
            print "<br><input type='radio' name='dupfilter' value=$DUPLICATE_FILTER_DATE_RANGE $checked>";
            print pacsone_gettext("Only Show Duplicate Patient IDs Received During This Date Range");
            print "&nbsp;" . pacsone_gettext("From:");
            print "<input type='text' name='dupfrom' size=16 maxlength=16>";
            print "&nbsp;" . pacsone_gettext("To:");
            print "<input type='text' name='dupto' size=16 maxlength=16>";
            print "</td></tr>";
            print "</table>\n";
            $buttons['Apply Duplicate Patient ID Filter'] = array(pacsone_gettext('Apply Duplicate Patient ID Filter'), pacsone_gettext('Apply Selected Duplicate Patient ID Display Filter'), $modifyAccess);
        }
        displayButtons("patient", $buttons, null);
    }
    // display patient table
    print "<table width=100% border=0 cellpadding=5 class='mouseover optionrow'>\n";
    print "<tr class=listhead bgcolor=$BGCOLOR>\n";
    if ($checkbox) {
        print "\t<td></td>\n";
    }
	if ($modifyAccess)
    	print "\t<td><b>" . pacsone_gettext("Privacy") . "</b></td>\n";
    // check if need to toggle sorting order
    if (isset($_SESSION['sortToggle'])) {
        $toggle = 1 - $_SESSION['sortToggle'];
        $link = urlReplace($url, "toggle", $toggle);
    } else {
        $link = $url;
    }
    global $CUSTOMIZE_PATIENT_ID;
    global $CUSTOMIZE_PATIENT_NAME;
    global $CUSTOMIZE_PATIENT_SEX;
    global $CUSTOMIZE_PATIENT_DOB;
    $links = array(
        $CUSTOMIZE_PATIENT_ID                   => urlReplace($link, "sort", "cmp_id"),
        $CUSTOMIZE_PATIENT_NAME                 => urlReplace($link, "sort", "cmp_name"),
        $CUSTOMIZE_PATIENT_DOB                  => urlReplace($link, "sort", "cmp_birthdate"),
        $CUSTOMIZE_PATIENT_SEX                  => urlReplace($link, "sort", "cmp_sex"),
        pacsone_gettext("Institution Name")     => urlReplace($link, "sort", "cmp_institution"),
    );
    $subTables = array(
        "speciescode"   => "patientspeciescode",
        "breedcode"     => "patientbreedcode",
        "breedreg"      => "breedregistration",
    );
    $subTblColumns = array(
        "patientspeciescode"    => array("value"            => pacsone_gettext("Code Value"),
                                         "schemedesignator" => pacsone_gettext("Coding Scheme Designator"),
                                         "schemeversion"    => pacsone_gettext("Coding Scheme Version"),
                                         "meaning"          => pacsone_gettext("Code Meaning")),
        "patientbreedcode"      => array("value"            => pacsone_gettext("Code Value"),
                                         "schemedesignator" => pacsone_gettext("Coding Scheme Designator"),
                                         "schemeversion"    => pacsone_gettext("Coding Scheme Version"),
                                         "meaning"          => pacsone_gettext("Code Meaning")),
        "breedregistration"     => array("regnumber"        => pacsone_gettext("Breed Registration Number"),
                                         "value"            => pacsone_gettext("Code Value"),
                                         "schemedesignator" => pacsone_gettext("Coding Scheme Designator"),
                                         "schemeversion"    => pacsone_gettext("Coding Scheme Version"),
                                         "meaning"          => pacsone_gettext("Code Meaning")),
    );
    // display the following columns: column name <=> database field
    $columns = $dbcon->getPatientViewColumns($username);
    foreach ($columns as $key => $field) {
        if (isset($links[$key])) {
            $link = $links[$key];
            print "\t<td><b><a href='$link'>$key</a></b></td>\n";
        }
        else {
            if (array_key_exists($field, $subTables)) {
                $subColumns = $subTblColumns[ $subTables[$field] ];
                $colspan = count($subColumns);
                print "\t<td align=center colspan=$colspan><b>$key</b>";
                print "<table width=100% border=1 cellpadding=0 cellspacing=0>";
                print "<tr class=listhead bgcolor=$BGCOLOR>\n";
                foreach ($subColumns as $sub => $descr) {
                    print "<td>$descr</td>";
                }
                print "</tr></table></td>";
            }
            else
                print "\t<td><b>$key</b></td>";
        }
    }
    print "\t<td><b>";
    print pacsone_gettext("Number of Studies");
    print "</b></td>\n";
    if ($matchWorklist) {
        print "\t<td><b>";
        print pacsone_gettext("Consistency");
        print "</b></td>\n";
    }
    // embed URL link to patient details
    print "\t<td><b>";
    print pacsone_gettext("Details");
    print "</b></td>\n";
    if ($duplicates) {
        print "\t<td><b>";
        print pacsone_gettext("Duplicate");
        print "</b></td>\n";
    }
    print "</tr>\n";
    $count = 0;
    foreach ($rows as $row) { 
        $patientId = $row["origid"];
        $urlId = urlencode($patientId);
        $style = ($count++ & 0x1)? "oddrows" : "evenrows";
        print "<tr class='$style'>\n";
        if ($checkbox) {
	        print "\t<td align=center width='1%'>\n";
            $data = urlencode($row['origid']);
	        print "\t\t<input type='checkbox' name='entry[]' value='$data'></td>\n";
        }
		if ($modifyAccess) {
			$current = $row["private"];
			$value = ($current)? pacsone_gettext("Private ") : pacsone_gettext("Public ");
            if ($current)
			    $toggle = "<font color=red>" . pacsone_gettext("Change to Public") . "</font>";
            else
                $toggle = pacsone_gettext("Change to Private");
			print "\t<td>$MYFONT$value</font><br>";
			print "<a href='markPatient.php?id=$urlId&current=$current'>$toggle</a></font></td>\n";
		}
        $patientName = $dbcon->getPatientName($patientId);
        foreach ($columns as $key => $field) {
            if (isset($row[$field])) {
                $value = ucfirst($row[$field]);
				if (strcasecmp($field, "origid") == 0) {
                    $value = strlen($value)? $value : pacsone_gettext("(Blank)");
					printf("\t<td>%s<a href='study.php?patientId=%s'>%s</a></font></td>\n",
						$MYFONT, urlencode($row[$field]), $value);
                } else {
                    if (strcasecmp($field, "birthdate") == 0) {
                        $value = $dbcon->formatDate($value);
                    }
               	    printf("\t<td>%s%s</font></td>\n", $MYFONT, $value);
                }
            // Patient Name is a synthetic field
            } else if (strcasecmp($field, "patientname") == 0) {
                $value = $patientName;
               	printf("\t<td>%s%s</font></td>\n", $MYFONT, $value);
            } else if (array_key_exists($field, $subTables)) {
                $sub = $subTables[$field];
                $subColumns = $subTblColumns[$sub];
                $colspan = count($subColumns);
                print "\t<td align=center colspan=$colspan>";
                print "<table width=100% border=1 cellpadding=0 cellspacing=0><tr>";
                $query = "select * from $sub where patientid=?";
                $subList = array($patientId);
                $subq = $dbcon->preparedStmt($query, $subList);
                if ($subq && ($subrow = $subq->fetch(PDO::FETCH_ASSOC))) {
                    foreach ($subColumns as $sub => $descr) {
                        $value = isset($subrow[$sub])? $subrow[$sub] : "";
                        if (!strlen($value))
                            $value = pacsone_gettext("N/A");
                        print "<td>$value</td>";
                    }
                } else {
                    print "<td align=center colspan=$colspan>" . pacsone_gettext("N/A") . "</td>";
                }
                print "</tr></table></td>";
            }
            else
                print "\t<td>$MYFONT" . pacsone_gettext("N/A") . "</font></td>\n";
        }
        $value = $dbcon->getNumAccessibleStudies($patientId, $username);
        print "<td>$value</td>\n";
        if ($matchWorklist) {
            $value = "";
            if ($row["patientmatchworklist"] == 1)
                $value = "<img src='ok.gif' title='Consistent With Worklist Data'>";
            else if ($row["patientmatchworklist"] == 2)
                $value = "<img src='warning.gif' title='Inconsistent With Worklist Data'>";
            print "<td>$value</td>\n";
        }
        print "<td>$MYFONT<a href='patient.php?patientId=$urlId'>";
        print pacsone_gettext("More Information");
        print "</a></font></td>\n";
        if ($duplicates) {
            if ($modifyAccess) {
                print "\t<td>$MYFONT<a href='resolveDup.php?duplicate=$urlId'>";
                print pacsone_gettext("Resolve");
                print "</a></font></td>\n";
            } else {
                print "\t<td>$MYFONT";
                print pacsone_gettext("Resolve");
                print "</font></td>\n";
            }
        }
        print "</tr>\n";
    }
    print "</table>\n";
    if ($checkbox) {
        displayButtons("patient", $buttons, null);
	    print "</form>\n";
    }
}

function getStudyDisplayStyle(&$row)
{
    global $STUDY_NEED_VERIFICATION;
    // return one of the following styles:
    //
    // - Not reviewed
    // - Reviewed but not yet verified
    // - Reviewed, verified but needs attention/re-verification
    // - Reviewed and verified to be Ok
    if (!isset($row['reviewed']) || !strlen($row['reviewed']))
        return "StudyNotReviewed";
    if (!isset($row['verified']) || !strlen($row['verified']))
        return "StudyReviewed";
    return strcasecmp($row['verified'], $STUDY_NEED_VERIFICATION)? "StudyVerified" : "StudyNeedVerification";
}

function showFilter_Rina($pfiltersEnabled, $pfilters, $peurodate)
{
        global $MYFONT;
        global $dbcon;
        global $PATIENT_INFO_STUDY_VIEW_TBL;
        global $STUDY_MODIFY_COLUMNS;
        global $BGCOLOR;


        global $CUSTOMIZE_REFERRING_DOC;
        global $CUSTOMIZE_READING_DOC;
        global $CUSTOMIZE_REQUESTING_DOC;


        global $STUDY_FILTER_STATUS_READ;
        global $STUDY_FILTER_STATUS_UNREAD;
        global $STUDY_FILTER_STATUS_BOTH;
        global $STUDY_FILTER_STUDYDATE_MASK;
        global $STUDY_FILTER_STUDYDATE_MASK_BITS;
        global $STUDY_FILTER_STUDYDATE_ALL;
        global $STUDY_FILTER_STUDYDATE_TODAY;
        global $STUDY_FILTER_STUDYDATE_YESTERDAY;
        global $STUDY_FILTER_STUDYDATE_DAY_BEFORE_YESTERDAY;
        global $STUDY_FILTER_STUDYDATE_LAST_N_DAYS;
        global $STUDY_FILTER_STUDYDATE_FROM_TO;
        global $STUDY_FILTER_BY_REFERRING_DOC;
        global $STUDY_FILTER_BY_READING_DOC;
        global $STUDY_FILTER_BY_DATE_RECEIVED;

        $display = $pfiltersEnabled ? "display:inline-block" : "display:none";

    print "<div id=\"filterSettings\" style=\"overflow:hidden; $display; width:100%;\">";

        print "<table class=\"table\" style=\"width:100%;\">\n";
            print "<thead>\n";
                print "<tr class=\"success\"> \n";
                    print "<th>".pacsone_gettext("Study Status")."</th>\n";
                    print "<th>".pacsone_gettext("Show Studies From:")."</th>\n";
                    print "<th>".pacsone_gettext("Filter By:")."</th>\n";
                    print "<th>".pacsone_gettext("Configurations")."</th>\n";
                print "</tr>\n";
            print "</thead>\n";

            print "<tbody>\n";
                // filter table contents --------------------------------------------
            print "<tr class=\"active\">\n";
            // study status column
            print "<td>";

                $checked = "";
                if (isset($pfilters['status']))
                {
                    $checked = ($pfilters['status'] == $STUDY_FILTER_STATUS_READ)? "checked" : "";
                }
                
                print "<input type=radio name='studyStatus' value=$STUDY_FILTER_STATUS_READ $checked>&nbsp;";
                print pacsone_gettext("Read");

                $checked = "";
                if (isset($pfilters['status']))
                {
                    $checked = ($pfilters['status'] == $STUDY_FILTER_STATUS_UNREAD)? "checked" : "";
                }
                
                print "<br><input type=radio name='studyStatus' value=$STUDY_FILTER_STATUS_UNREAD $checked>&nbsp;";
                print pacsone_gettext("Unread");

                $checked = "";
                if (isset($pfilters['status']))
                {
                    $checked = ($pfilters['status'] == $STUDY_FILTER_STATUS_BOTH)? "checked" : "";
                }
                
                print "<br><input type=radio name='studyStatus' value=$STUDY_FILTER_STATUS_BOTH $checked>&nbsp;";
                print pacsone_gettext("Both");
            print "</td>\n";
            // study date filter column
            print "<td>";
                $dateType = 0;
                $datePeriod=0;
                if(isset($pfilters['studydate']))
                {
                    $dateType = ($pfilters['studydate'] & $STUDY_FILTER_STUDYDATE_MASK);
                    $datePeriod = ($pfilters['studydate'] >> $STUDY_FILTER_STUDYDATE_MASK_BITS);
                }
                $checked = ($dateType == $STUDY_FILTER_STUDYDATE_ALL)? "checked" : "";
                print "<input type=radio name='studyDate' value=$STUDY_FILTER_STUDYDATE_ALL $checked>&nbsp;";
                print pacsone_gettext("All");
                $checked = ($dateType == $STUDY_FILTER_STUDYDATE_TODAY)? "checked" : "";
                print "<br><input type=radio name='studyDate' value=$STUDY_FILTER_STUDYDATE_TODAY $checked>&nbsp;";
                print pacsone_gettext("Today");
                $checked = ($dateType == $STUDY_FILTER_STUDYDATE_YESTERDAY)? "checked" : "";
                print "<br><input type=radio name='studyDate' value=$STUDY_FILTER_STUDYDATE_YESTERDAY $checked>&nbsp;";
                print pacsone_gettext("Yesterday");
                $checked = ($dateType == $STUDY_FILTER_STUDYDATE_DAY_BEFORE_YESTERDAY)? "checked" : "";
                print "<br><input type=radio name='studyDate' value=$STUDY_FILTER_STUDYDATE_DAY_BEFORE_YESTERDAY $checked>&nbsp;";
                print pacsone_gettext("The Day Before Yesterday");
                $checked = ($dateType == $STUDY_FILTER_STUDYDATE_LAST_N_DAYS)? "checked" : "";
                print "<br><input type=radio name='studyDate' value=$STUDY_FILTER_STUDYDATE_LAST_N_DAYS $checked>&nbsp;";
                printf(pacsone_gettext("Last <input type=text name='filterNdays' value='%s'size=4 maxlength=6> Days"), $datePeriod? "$datePeriod" : "");
                $checked = ($dateType == $STUDY_FILTER_STUDYDATE_FROM_TO)? "checked" : "";
                print "<br><input type=radio name='studyDate' value=$STUDY_FILTER_STUDYDATE_FROM_TO $checked>&nbsp;";
                print $peurodate? pacsone_gettext("From: (DD-MM-YYYY)") : pacsone_gettext("From: (YYYY-MM-DD)");
                print "<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";

                $value = "";
                if(isset($pfilters['datefrom']))
                {
                    $value = $pfilters['datefrom'];    
                }
                
                if ($peurodate)
                    $value = reverseDate($value);
                printf("<input type=text name='studyDateFrom' value='%s' size=10 maxlength=16>", strlen($value)? $value : "");
                print "<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
                print $peurodate? pacsone_gettext("To: (DD-MM-YYYY)") : pacsone_gettext("To: (YYYY-MM-DD)");
                print "<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
                if(isset($pfilters['dateto']))
                {
                    $value = $pfilters['dateto'];    
                }
                if ($peurodate)
                    $value = reverseDate($value);
                printf("<input type=text name='studyDateTo' value='%s' size=10 maxlength=16>", strlen($value)? $value : "");
            print "</td>\n";

            // filter by column
            print "<td>";

                $filterBy = false;
                if(isset($pfilters['filterby']))
                {
                    $filterBy = $pfilters['filterby'];    
                }
                
                $checked = ($filterBy & $STUDY_FILTER_BY_REFERRING_DOC)? "checked" : "";
                print "<input type=checkbox name='filterBy[]' value=$STUDY_FILTER_BY_REFERRING_DOC $checked>&nbsp;";
                print $CUSTOMIZE_REFERRING_DOC;
                if(isset($pfilters['referdoc'])){
                    $value = $pfilters['referdoc'];    
                }
                
                printf("&nbsp;<input type=text name='referdoc' value='%s' size=16 maxlength=32>", strlen($value)? $value : "");
                $checked = ($filterBy & $STUDY_FILTER_BY_READING_DOC)? "checked" : "";
                print "<br><input type=checkbox name='filterBy[]' value=$STUDY_FILTER_BY_READING_DOC $checked>&nbsp;";
                print $CUSTOMIZE_READING_DOC;
                $value = isset($pfilters['readdoc'])?$pfilters['readdoc'] : "";
                printf("&nbsp;<input type=text name='readdoc' value='%s' size=16 maxlength=32>", strlen($value)? $value : "");
                $checked = ($filterBy & $STUDY_FILTER_BY_DATE_RECEIVED)? "checked" : "";
                print "<br><input type=checkbox name='filterBy[]' value=$STUDY_FILTER_BY_DATE_RECEIVED $checked>&nbsp;";
                print pacsone_gettext("Date When Study Was Received");
                print "<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
                print $peurodate? pacsone_gettext("From: (DD-MM-YYYY)") : pacsone_gettext("From: (YYYY-MM-DD)");
                print "<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
                $value = isset($pfilters['receivedfrom']) ? $pfilters['receivedfrom'] : "";
                if ($peurodate)
                    $value = reverseDate($value);
                printf("<input type=text name='receivedfrom' value='%s' size=16 maxlength=32>", strlen($value)? $value : "");
                print "<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
                print $peurodate? pacsone_gettext("To: (DD-MM-YYYY)") : pacsone_gettext("To: (YYYY-MM-DD)");
                print "<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
                $value = isset($pfilters['receivedto']) ? $pfilters['receivedto'] : "";
                if ($peurodate)
                    $value = reverseDate($value);
                printf("<input type=text name='receivedto' value='%s' size=16 maxlength=32>", strlen($value)? $value : "");

            print "</td>\n";

            // configurations column
            print "<td>";
                print "<p>" . pacsone_gettext("Always Display Study Filter Settings");
                
                if(isset($pfilters['showsettings']))
                {
                    $checked = $pfilters['showsettings']? "checked" : "";    
                }
                
                print "<br><input type=radio name='showsettings' value=1 $checked>";
                print pacsone_gettext("Yes");
                if(isset($pfilters['showsettings']))
                {
                    $checked = $pfilters['showsettings']? "" : "checked";    
                }
                
                print "<br><input type=radio name='showsettings' value=0 $checked>";
                print pacsone_gettext("No");
                print "</td></tr>";

            // filters buttons-------------------------------------------------------------

            print "<tr class=\"danger\">\n";
                print "<td colspan=2 align='left' style=\"border:0\">";
                $value = pacsone_gettext("Clear Filters");
                $title = pacsone_gettext("Clear Filter Settings");
                print "<input type=\"submit\" class=\"btn btn-primary\" value='$value' name='action' title='$title' onclick='switchText(this.form, \"actionvalue\", \"Clear Filters\")'>";
                print "</td><td colspan=2 align='right' style=\"border:0\">";
                $value = pacsone_gettext("Apply Filters");
                $title = pacsone_gettext("Apply Filter Settings");
                print "<input type=\"submit\" class=\"btn btn-primary\" value='$value' name='action' title='$title' onclick='switchText(this.form, \"actionvalue\", \"Apply Filters\")'>";
                print "</td>\n";

            print "</tr>\n";
        
        print "</tbody>\n";
    print "</table>\n";

    print "</div>\n";



        /*
        $display = $pfiltersEnabled? "display:inline-block" : "display:none";
        print "<div id=\"filterSettings\" style=\"overflow:hidden; $display;\">";
        print "<table width=100% border=1 cellspacing=0 cellpadding=3>";
        print "<tr class=listhead bgcolor=$BGCOLOR>\n";
        print "<td align='center'>" . pacsone_gettext("Study Status") . "</td>";
        print "<td align='center'>" . pacsone_gettext("Show Studies From:") . "</td>";
        print "<td align='center'>" . pacsone_gettext("Filter By:") . "</td>";
        print "<td align='center'>" . pacsone_gettext("Configurations") . "</td>";
        print "</tr>";
        // study status column
        print "<tr><td>";
        $checked = ($pfilters['status'] == $STUDY_FILTER_STATUS_READ)? "checked" : "";
        print "<input type=radio name='studyStatus' value=$STUDY_FILTER_STATUS_READ $checked>&nbsp;";
        print pacsone_gettext("Read");
        $checked = ($pfilters['status'] == $STUDY_FILTER_STATUS_UNREAD)? "checked" : "";
        print "<br><input type=radio name='studyStatus' value=$STUDY_FILTER_STATUS_UNREAD $checked>&nbsp;";
        print pacsone_gettext("Unread");
        $checked = ($pfilters['status'] == $STUDY_FILTER_STATUS_BOTH)? "checked" : "";
        print "<br><input type=radio name='studyStatus' value=$STUDY_FILTER_STATUS_BOTH $checked>&nbsp;";
        print pacsone_gettext("Both");
        print "</td>";
        // study date filter column
        print "<td>";
        $dateType = ($pfilters['studydate'] & $STUDY_FILTER_STUDYDATE_MASK);
        $datePeriod = ($pfilters['studydate'] >> $STUDY_FILTER_STUDYDATE_MASK_BITS);
        $checked = ($dateType == $STUDY_FILTER_STUDYDATE_ALL)? "checked" : "";
        print "<input type=radio name='studyDate' value=$STUDY_FILTER_STUDYDATE_ALL $checked>&nbsp;";
        print pacsone_gettext("All");
        $checked = ($dateType == $STUDY_FILTER_STUDYDATE_TODAY)? "checked" : "";
        print "<br><input type=radio name='studyDate' value=$STUDY_FILTER_STUDYDATE_TODAY $checked>&nbsp;";
        print pacsone_gettext("Today");
        $checked = ($dateType == $STUDY_FILTER_STUDYDATE_YESTERDAY)? "checked" : "";
        print "<br><input type=radio name='studyDate' value=$STUDY_FILTER_STUDYDATE_YESTERDAY $checked>&nbsp;";
        print pacsone_gettext("Yesterday");
        $checked = ($dateType == $STUDY_FILTER_STUDYDATE_DAY_BEFORE_YESTERDAY)? "checked" : "";
        print "<br><input type=radio name='studyDate' value=$STUDY_FILTER_STUDYDATE_DAY_BEFORE_YESTERDAY $checked>&nbsp;";
        print pacsone_gettext("The Day Before Yesterday");
        $checked = ($dateType == $STUDY_FILTER_STUDYDATE_LAST_N_DAYS)? "checked" : "";
        print "<br><input type=radio name='studyDate' value=$STUDY_FILTER_STUDYDATE_LAST_N_DAYS $checked>&nbsp;";
        printf(pacsone_gettext("Last <input type=text name='filterNdays' value='%s'size=4 maxlength=6> Days"), $datePeriod? "$datePeriod" : "");
        $checked = ($dateType == $STUDY_FILTER_STUDYDATE_FROM_TO)? "checked" : "";
        print "<br><input type=radio name='studyDate' value=$STUDY_FILTER_STUDYDATE_FROM_TO $checked>&nbsp;";
        print $peurodate? pacsone_gettext("From: (DD-MM-YYYY)") : pacsone_gettext("From: (YYYY-MM-DD)");
        print "<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
        $value = $pfilters['datefrom'];
        if ($peurodate)
            $value = reverseDate($value);
        printf("<input type=text name='studyDateFrom' value='%s' size=10 maxlength=16>", strlen($value)? $value : "");
        print "<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
        print $peurodate? pacsone_gettext("To: (DD-MM-YYYY)") : pacsone_gettext("To: (YYYY-MM-DD)");
        print "<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
        $value = $pfilters['dateto'];
        if ($peurodate)
            $value = reverseDate($value);
        printf("<input type=text name='studyDateTo' value='%s' size=10 maxlength=16>", strlen($value)? $value : "");
        print "</td>";
        // filter by column
        $filterBy = $pfilters['filterby'];
        print "<td>";
        $checked = ($filterBy & $STUDY_FILTER_BY_REFERRING_DOC)? "checked" : "";
        print "<input type=checkbox name='filterBy[]' value=$STUDY_FILTER_BY_REFERRING_DOC $checked>&nbsp;";
        print $CUSTOMIZE_REFERRING_DOC;
        $value = $pfilters['referdoc'];
        printf("&nbsp;<input type=text name='referdoc' value='%s' size=16 maxlength=32>", strlen($value)? $value : "");
        $checked = ($filterBy & $STUDY_FILTER_BY_READING_DOC)? "checked" : "";
        print "<br><input type=checkbox name='filterBy[]' value=$STUDY_FILTER_BY_READING_DOC $checked>&nbsp;";
        print $CUSTOMIZE_READING_DOC;
        $value = $pfilters['readdoc'];
        printf("&nbsp;<input type=text name='readdoc' value='%s' size=16 maxlength=32>", strlen($value)? $value : "");
        $checked = ($filterBy & $STUDY_FILTER_BY_DATE_RECEIVED)? "checked" : "";
        print "<br><input type=checkbox name='filterBy[]' value=$STUDY_FILTER_BY_DATE_RECEIVED $checked>&nbsp;";
        print pacsone_gettext("Date When Study Was Received");
        print "<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
        print $peurodate? pacsone_gettext("From: (DD-MM-YYYY)") : pacsone_gettext("From: (YYYY-MM-DD)");
        print "<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
        $value = $pfilters['receivedfrom'];
        if ($peurodate)
            $value = reverseDate($value);
        printf("<input type=text name='receivedfrom' value='%s' size=16 maxlength=32>", strlen($value)? $value : "");
        print "<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
        print $peurodate? pacsone_gettext("To: (DD-MM-YYYY)") : pacsone_gettext("To: (YYYY-MM-DD)");
        print "<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
        $value = $pfilters['receivedto'];
        if ($peurodate)
            $value = reverseDate($value);
        printf("<input type=text name='receivedto' value='%s' size=16 maxlength=32>", strlen($value)? $value : "");
        print "</td>";
        // configurations column
        print "<td>";
        print "<p>" . pacsone_gettext("Always Display Study Filter Settings");
        $checked = $pfilters['showsettings']? "checked" : "";
        print "<br><input type=radio name='showsettings' value=1 $checked>";
        print pacsone_gettext("Yes");
        $checked = $pfilters['showsettings']? "" : "checked";
        print "<br><input type=radio name='showsettings' value=0 $checked>";
        print pacsone_gettext("No");
        print "</td></tr>";
        print "<tr><td colspan=2 align='left' style=\"border:0\">";
        $value = pacsone_gettext("Clear Filters");
        $title = pacsone_gettext("Clear Filter Settings");
        print "<input type=submit value='$value' name='action' title='$title' onclick='switchText(this.form, \"actionvalue\", \"Clear Filters\")'>";
        print "</td><td colspan=2 align='right' style=\"border:0\">";
        $value = pacsone_gettext("Apply Filters");
        $title = pacsone_gettext("Apply Filter Settings");
        print "<input type=submit value='$value' name='action' title='$title' onclick='switchText(this.form, \"actionvalue\", \"Apply Filters\")'>";
        print "</td></tr>";
        print "</table>";
        print "</div>";
        */

}

function displayStudies($list, $preface, $url, $offset, $showPatientId, $all, $showFilters = 0)
{
    include_once 'toggleRowColor.js';
    global $MYFONT;
    global $dbcon;
    global $PATIENT_INFO_STUDY_VIEW_TBL;
    global $STUDY_MODIFY_COLUMNS;
    global $BGCOLOR;
    print "<form style='margin-top:-75px' method='POST' action='actionItem.php'>\n";
    $eurodate = $dbcon->isEuropeanDateFormat();
    // check if Patient Reconciliation is enabled
    $matchWorklist = 0;
    $config = $dbcon->query("select matchworklist from config");
    if ($config && ($configRow = $config->fetch(PDO::FETCH_NUM))) {
        $matchWorklist = $configRow[0];
    }
    // check whether to bypass Series level
    $skipSeries = 0;
    $result = $dbcon->query("select skipseries from config");
    if ($result && ($row = $result->fetch(PDO::FETCH_NUM)))
        $skipSeries = $row[0];
    
    $data = displayPageControl(pacsone_gettext("Studies"), $list, $preface, $url, $offset, $all);
    $rows = $data["rows"];
    $pagination = $data["pagination"];
    // check user privileges
    $checkbox = 0;
	$username = $dbcon->username;
	$modifyAccess = $dbcon->hasaccess("modifydata", $username);
	$forwardAccess = $dbcon->hasaccess("forward", $username);
	$downloadAccess = $dbcon->hasaccess("download", $username);
    $printAccess = $dbcon->hasaccess("print", $username);
    $markAccess = $dbcon->hasaccess("mark", $username);
    $moveAccess = $dbcon->hasaccess("changestore", $username);
    if ($printAccess) {
        $printers = $dbcon->query("select printscp from applentity where printscp=1");
        if ($printers && ($printers->rowCount() == 0))
            $printAccess = 0;
    }
    $exportAccess = $dbcon->hasaccess("export", $username);
    $showStudyNotesIcon = $dbcon->showStudyNotesIcon($username);
    $viewAccess = $dbcon->hasaccess("viewprivate", $username);
    $filters = $dbcon->getStudyFilters($username);
    
    $filtersEnabled = false;
    if(isset($filters['showsettings']))
    {
        $filtersEnabled = $filters['showsettings'];    
    }

    //print("init show setting =".$filters['showsettings']);
    
    // check if Java Applet viewer exists
    $showDicom = 0;
    if (appletExists()) {
        $showDicom = 1;
    }
    $buttons = array();
    if (($modifyAccess + $forwardAccess + $printAccess + $exportAccess + $downloadAccess + $markAccess + $showDicom + $moveAccess) && sizeof($rows)) {
        $checkbox = 1;
        $buttons['Forward'] = array(pacsone_gettext('Forward'), pacsone_gettext('Forward checked studies'), $forwardAccess);
        $buttons['Delete'] = array(pacsone_gettext('Delete'), pacsone_gettext('Delete checked studies'), $modifyAccess);
        $buttons['Print'] = array(pacsone_gettext('Print'), pacsone_gettext('Print checked studies'), $printAccess);
        $buttons['Export'] = array(pacsone_gettext('Export'), pacsone_gettext('Export checked studies'), $exportAccess);
        $buttons['Show'] = array(pacsone_gettext('Show'), pacsone_gettext('Show images of checked studies'), $showDicom);
        $buttons['Download'] = array(pacsone_gettext('Download'), pacsone_gettext('Download images of checked studies'), $downloadAccess);
        if ($dbcon->getAutoConvertJPG()) {
            $buttons['Download JPG'] = array(pacsone_gettext('Download JPG'), pacsone_gettext('Download converted JPG/GIF images of checked studies'), $downloadAccess);
        }
        $buttons['Change Storage'] = array(pacsone_gettext('Change Storage'), pacsone_gettext('Change storage location of checked studies'), $moveAccess);
        // check if any study is read or not read
        $notRead = 0;
        $read = 0;
        foreach ($rows as $row) {
            if (isset($row['reviewed']) && strlen($row['reviewed'])) {
                $read = 1;
            } else {
                $notRead = 1;
            }
        }
        if ($notRead)
            $buttons['Mark Study As Read'] = array(pacsone_gettext('Mark Study As Read'), pacsone_gettext('Mark checked studies as Read'), $markAccess);
        if ($read)
            $buttons['Mark Study As Un-Read'] = array(pacsone_gettext('Mark Study As Un-Read'), pacsone_gettext('Mark checked studies as Un-Read'), $markAccess);
    }
    if ($showFilters) {
        $text = $filtersEnabled? pacsone_gettext("Hide Filters") : pacsone_gettext('Show Filters');
        $buttons['Show Filters'] = array($text, pacsone_gettext('Toggle display of filter settings for study view pages'), $viewAccess);
    }
    displayButtons("study", $buttons, null, sizeof($rows), $pagination); // ----------------------------
    
    // check if need to toggle sorting order
    if (isset($_SESSION['sortToggle'])) {
        $toggle = 1 - $_SESSION['sortToggle'];
        $link = urlReplace($url, "toggle", $toggle);
    } else {
        $link = $url;
    }
    // links to different sorting methods
    $links = array();
	if ($showPatientId) {
        global $CUSTOMIZE_PATIENT_ID;
        global $CUSTOMIZE_PATIENT_NAME;
        global $CUSTOMIZE_PATIENT_DOB;
        $links[$CUSTOMIZE_PATIENT_ID] = urlReplace($link, "sort", "cmp_patientid");
        $links[$CUSTOMIZE_PATIENT_NAME] = urlReplace($link, "sort", "cmp_name");
        $links[$CUSTOMIZE_PATIENT_DOB] = urlReplace($link, "sort", "cmp_birthdate");
        $links[pacsone_gettext("Institution Name")] = urlReplace($link, "sort", "cmp_institution");
    }
    $links[pacsone_gettext("Study ID")] = urlReplace($link, "sort", "cmp_studyid");
    $links[pacsone_gettext("Study Date")] = urlReplace($link, "sort", "cmp_studydate");
    $links[pacsone_gettext("Accession Number")] = urlReplace($link, "sort", "cmp_accession");
    global $CUSTOMIZE_REFERRING_DOC;
    $links[$CUSTOMIZE_REFERRING_DOC] = urlReplace($link, "sort", "cmp_referdoc");
    $links[pacsone_gettext("Description")] = urlReplace($link, "sort", "cmp_description");
    global $CUSTOMIZE_READING_DOC;
    $links[$CUSTOMIZE_READING_DOC] = urlReplace($link, "sort", "cmp_readingdoc");
    $links[pacsone_gettext("Source AE")] = urlReplace($link, "sort", "cmp_sourceae");
    $links[pacsone_gettext("Received On")] = urlReplace($link, "sort", "cmp_received_opt");
    global $CUSTOMIZE_REQUESTING_DOC;
    $links[$CUSTOMIZE_REQUESTING_DOC] = urlReplace($link, "sort", "cmp_reqdoc");
    // display the following columns: column name <=> database field
    $columns = $dbcon->getStudyViewColumns($username, $showPatientId);
    if ($matchWorklist)
        $columns[pacsone_gettext("Consistency")] = "studymatchworklist";

    if ($showFilters) {
        showFilter_Rina($filtersEnabled, $filters, $eurodate);
    }

    // display studies. ========================================
    print "<div class=\"row\">\n";

    print "<div class=\"tableFixHead\">\n";
    
 print "<table class=\"table table-hover  table-bordered  table-striped\" style=\"margin-bottom: 0px;\">\n";  // attatch the bottom btn group without gap.=>margin-bottom: 0px;
    print "<thead>\n";
      print "<tr class=\"Info\">\n";

        if ($checkbox) {
            print "\t<th></th>\n";
        }
        if ($modifyAccess) {
            print "\t<th><b>";
            print pacsone_gettext("Privacy");
            print "</b></th>\n";
        }
        foreach (array_keys($columns) as $key) {
            if (count($rows) && isset($links[$key])) {
                $link = $links[$key];
                print "\t<th><b><a href=$link>$key</a></b></th>\n";
            } else {
                print "\t<th><b>$key</b></th>\n";
            }
        }
        print "\t<th><b>";
        print pacsone_gettext("Total Number of Instances");
        print "</b></th>\n";

      print "</tr>\n";
    print "</thead>\n";

    // -------------------------  Tbody -----
    print "<tbody id='studyTable'>\n";

    foreach ($rows as $row) {
        $patientId = $row["patientid"];
        $uid = isset($row['studyuid'])? $row['studyuid'] : $row['uuid'];
        $instances = $dbcon->getStudyInstanceCount($uid);
        $style = getStudyDisplayStyle($row);
        print "<tr>\n";
        print "\t<td align=center width='1%'>\n";
        if ($checkbox)
            print "\t\t<input type='checkbox' name='entry[]' value='$uid'>";
        // display icon for expanding view of the study
        $skip = ($skipSeries && $dbcon->hasSRseries($uid))? false : $skipSeries;
        $expandUrl = $skip? "image.php" : "series.php";
        $expandUrl .= sprintf("?patientId=%s&studyId=%s", urlencode($patientId), $uid);
        $alt = pacsone_gettext("Expand to Series Level");
        print "<a href='$expandUrl'><img src='expand.png' border=0 title='$alt'></a>";
        if ($showStudyNotesIcon) {
            // display icon for study notes and attachments
            $notes = $dbcon->query("select count(*) from studynotes where uuid='$uid'");
            $count = 0;
            if ($notes && ($notesRow = $notes->fetch(PDO::FETCH_NUM)))
                $count = $notesRow[0];
            $notes = $count;
            $atts = $dbcon->query("select count(*) from attachment where uuid='$uid'");
            $count = 0;
            if ($atts && ($attsRow = $atts->fetch(PDO::FETCH_NUM)))
                $count = $attsRow[0];
            $atts = $count;
            $img = $notes? "notes.png" : "notes_none.png";
            $url = "studyNotes.php?view=1&uid=$uid";
            $alt = sprintf(pacsone_gettext("%d Notes and %d Attachments"), $notes, $atts);
            print "<a href='$url'><img src='$img' border=0 title='$alt' alt='$alt'></a>";
        }
        print "</td>\n";
        if ($modifyAccess) {
            $current = $row["private"];
            $value = ($current)? pacsone_gettext("Private ") : pacsone_gettext("Public ");
            if ($current) {
                $toggle = "<font color=red>";
                $toggle .= pacsone_gettext("Change to Public");
                print "</font>";
            } else {
                $toggle = pacsone_gettext("Change to Private");
            }
            print "\t<td>$MYFONT$value</font><br>";
            print "<a href='markStudy.php?id=$uid&current=$current'>$toggle</a></font></td>\n";
        }
        foreach ($columns as $key => $field) {
            $value = "";
            if (!strcasecmp($field, "commitreport") && $instances) {
                // get Storage Commitment Report status for this study
                $screport = $dbcon->getStorageCommitStatus($uid);
                if ($screport) {
                    $icon = $screport['icon'];
                    $descr = $screport['descr'];
                    $value = "<IMG SRC=\"$icon\" title=\"$descr\" ALT=\"$descr\">$descr</IMG>";
                }
            } else if (isset($row[$field])) {
                $value = $row[$field];
                if (!strcasecmp($field, "studydate") || !strcasecmp($field, "birthdate"))
                    $value = $dbcon->formatDate($value);
                else if (!strcasecmp($field, "sourceae")) {
                    // get the description information for the Source AE
                    $subq = "SELECT description FROM applentity WHERE title=?";
                    $subList = array($value);
                    $aet = $dbcon->preparedStmt($subq, $subList);
                    if ($aet && ($desc = $aet->fetchColumn())) {
                        if (strlen($desc))
                            $value .= " - " . $desc;
                    }
                }
            }
            if ($showPatientId && in_array($field, $PATIENT_INFO_STUDY_VIEW_TBL)) {
                if (!strcasecmp($field, "patientid")) {
                    $value = strlen($patientId)? $patientId : pacsone_gettext("(Blank)");
                    if ($modifyAccess)
                        $value = "<a href='splitStudy.php?uid=$uid&patientId=" . urlencode($patientId) . "'>$value</a>";
                } else if (!strcasecmp($field, "patientname")) {
                    $name = $dbcon->getPatientName($patientId);
                    $value = "<a href='study.php?patientId=" . urlencode($patientId) . "'>";
                    $value .= strlen($name)? $name : pacsone_gettext("(Blank)");
                    $value .= "</a>";
                }
            }
            if (strcasecmp($key, pacsone_gettext("Study ID")) == 0) {
                if (!strlen($value))
                    $value = pacsone_gettext("Study Details");
                $value = sprintf("<a href='%s'>%s</a>", $expandUrl, $value);
            }
            else if (in_array(strtolower($field), $STUDY_MODIFY_COLUMNS)) {
                $encoded = urlencode($value);
                $reportUrl = "";
                $celldata = "";
                if (strlen($value) && !strcasecmp($key, pacsone_gettext("Accession Number"))) {
                    // check if any ORU report is available for this accession number
                    $controlId = $dbcon->getObservationReports($value);
                    if (strlen($controlId)) {
                        $reportUrl = "oruReports.php?uuid=" . urlencode($controlId);
                        $reportUrl .= "&accessionnum=" . urlencode($value);
                    }
                }
                if ($modifyAccess) {
                    $key = urlencode($key);
                    $url = "modifyStudy.php?uid=$uid&key=$key&column=$field&value=$encoded";
                    $celldata = "<a href='$url'>$value</a>";
                } else {
                    $celldata = $value;
                }
                if (strlen($reportUrl)) {
                    $celldata .= "<br>&nbsp;";
                    $celldata .= "<br><a href=\"$reportUrl\">";
                    $celldata .= pacsone_gettext("Observation Reports");
                    $celldata .= "</a>";
                }
                if (strlen($value) && strlen($celldata))
                    $value = $celldata;
            }
            else if (!strcasecmp($key, pacsone_gettext("Read By")) && strlen($value)) {
                if ($dbcon->isAdministrator($value))
                    $value = pacsone_gettext("Administrator");
                else {
                    $subq = "select * from privilege where username=?";
                    $subList = array($value);
                    $profile = $dbcon->preparedStmt($subq, $subList);
                    if ($profile && ($userRow = $profile->fetch(PDO::FETCH_ASSOC))) {
                        $name = $userRow['firstname'] . " " . $userRow['lastname'];
                        $email = $userRow['email'];
                        if (strlen($email))
                            $value = "<a href='mailto:$email'>$name</a>";
                        else
                            $value = $name;
                    }
                }
            }
            else if (!strcasecmp($key, pacsone_gettext("Consistency"))) {
                $imgsrc = "";
                if ($value == 1)
                    $imgsrc = "<img src='ok.gif' title='Consistent With Worklist Data'>";
                else if ($value == 2)
                    $imgsrc = "<img src='warning.gif' title='Inconsistent With Worklist Data'>";
                $value = $imgsrc;
            }
            else if (!strcasecmp($field, "received")) {
                $value = $dbcon->formatDateTime($value);
            }
            else if (strcasecmp($field, "modalities") == 0) {
                if (strlen($value) == 0)
                    $value = $dbcon->getStudyModalities($uid);
            }
            if (!strlen($value))
                $value = pacsone_gettext("N/A");
            print "\t<td>$MYFONT$value</font></td>\n";
        }
        print "\t<td>$instances</td>\n";
        print "</tr>\n";
    }
      
    print "</tbody>\n";
  print "</table>\n";
  
  print "</div>\n";
  print "</div>\n";

/*
    print "<table width=100% border=0 cellpadding=3 class='mouseover optionrow'>\n";
    print "<tr class=listhead bgcolor=$BGCOLOR>\n";
    if ($checkbox) {
        print "\t<td></td>\n";
    }
	if ($modifyAccess) {
    	print "\t<td><b>";
        print pacsone_gettext("Privacy");
        print "</b></td>\n";
    }
    foreach (array_keys($columns) as $key) {
        if (count($rows) && isset($links[$key])) {
            $link = $links[$key];
            print "\t<td><b><a href=$link>$key</a></b></td>\n";
        } else {
            print "\t<td><b>$key</b></td>\n";
        }
    }
    print "\t<td><b>";
    print pacsone_gettext("Total Number of Instances");
    print "</b></td>\n";
    print "</tr>\n";
    foreach ($rows as $row) {
		$patientId = $row["patientid"];
        $uid = isset($row['studyuid'])? $row['studyuid'] : $row['uuid'];
        $instances = $dbcon->getStudyInstanceCount($uid);
        $style = getStudyDisplayStyle($row);
        print "<tr class='$style'>\n";
	    print "\t<td align=center width='1%'>\n";
        if ($checkbox)
            print "\t\t<input type='checkbox' name='entry[]' value='$uid'>";
        // display icon for expanding view of the study
        $skip = ($skipSeries && $dbcon->hasSRseries($uid))? false : $skipSeries;
        $expandUrl = $skip? "image.php" : "series.php";
        $expandUrl .= sprintf("?patientId=%s&studyId=%s", urlencode($patientId), $uid);
        $alt = pacsone_gettext("Expand to Series Level");
        print "<a href='$expandUrl'><img src='expand.png' border=0 title='$alt'></a>";
        if ($showStudyNotesIcon) {
            // display icon for study notes and attachments
            $notes = $dbcon->query("select count(*) from studynotes where uuid='$uid'");
            $count = 0;
            if ($notes && ($notesRow = $notes->fetch(PDO::FETCH_NUM)))
                $count = $notesRow[0];
            $notes = $count;
            $atts = $dbcon->query("select count(*) from attachment where uuid='$uid'");
            $count = 0;
            if ($atts && ($attsRow = $atts->fetch(PDO::FETCH_NUM)))
                $count = $attsRow[0];
            $atts = $count;
            $img = $notes? "notes.png" : "notes_none.png";
            $url = "studyNotes.php?view=1&uid=$uid";
            $alt = sprintf(pacsone_gettext("%d Notes and %d Attachments"), $notes, $atts);
            print "<a href='$url'><img src='$img' border=0 title='$alt' alt='$alt'></a>";
        }
        print "</td>\n";
		if ($modifyAccess) {
			$current = $row["private"];
			$value = ($current)? pacsone_gettext("Private ") : pacsone_gettext("Public ");
            if ($current) {
			    $toggle = "<font color=red>";
                $toggle .= pacsone_gettext("Change to Public");
                print "</font>";
            } else {
                $toggle = pacsone_gettext("Change to Private");
            }
			print "\t<td>$MYFONT$value</font><br>";
			print "<a href='markStudy.php?id=$uid&current=$current'>$toggle</a></font></td>\n";
		}
        foreach ($columns as $key => $field) {
            $value = "";
            if (!strcasecmp($field, "commitreport") && $instances) {
                // get Storage Commitment Report status for this study
                $screport = $dbcon->getStorageCommitStatus($uid);
                if ($screport) {
                    $icon = $screport['icon'];
                    $descr = $screport['descr'];
                    $value = "<IMG SRC=\"$icon\" title=\"$descr\" ALT=\"$descr\">$descr</IMG>";
                }
            } else if (isset($row[$field])) {
                $value = $row[$field];
                if (!strcasecmp($field, "studydate") || !strcasecmp($field, "birthdate"))
                    $value = $dbcon->formatDate($value);
                else if (!strcasecmp($field, "sourceae")) {
                    // get the description information for the Source AE
                    $subq = "SELECT description FROM applentity WHERE title=?";
                    $subList = array($value);
                    $aet = $dbcon->preparedStmt($subq, $subList);
                    if ($aet && ($desc = $aet->fetchColumn())) {
                        if (strlen($desc))
                            $value .= " - " . $desc;
                    }
                }
            }
            if ($showPatientId && in_array($field, $PATIENT_INFO_STUDY_VIEW_TBL)) {
			    if (!strcasecmp($field, "patientid")) {
                    $value = strlen($patientId)? $patientId : pacsone_gettext("(Blank)");
                    if ($modifyAccess)
                        $value = "<a href='splitStudy.php?uid=$uid&patientId=" . urlencode($patientId) . "'>$value</a>";
                } else if (!strcasecmp($field, "patientname")) {
                    $name = $dbcon->getPatientName($patientId);
                    $value = "<a href='study.php?patientId=" . urlencode($patientId) . "'>";
                    $value .= strlen($name)? $name : pacsone_gettext("(Blank)");
                    $value .= "</a>";
                }
            }
            if (strcasecmp($key, pacsone_gettext("Study ID")) == 0) {
            	if (!strlen($value))
					$value = pacsone_gettext("Study Details");
                $value = sprintf("<a href='%s'>%s</a>", $expandUrl, $value);
            }
            else if (in_array(strtolower($field), $STUDY_MODIFY_COLUMNS)) {
                $encoded = urlencode($value);
                $reportUrl = "";
                $celldata = "";
                if (strlen($value) && !strcasecmp($key, pacsone_gettext("Accession Number"))) {
                    // check if any ORU report is available for this accession number
                    $controlId = $dbcon->getObservationReports($value);
                    if (strlen($controlId)) {
                        $reportUrl = "oruReports.php?uuid=" . urlencode($controlId);
                        $reportUrl .= "&accessionnum=" . urlencode($value);
                    }
                }
                if ($modifyAccess) {
                    $key = urlencode($key);
                    $url = "modifyStudy.php?uid=$uid&key=$key&column=$field&value=$encoded";
                    $celldata = "<a href='$url'>$value</a>";
                } else {
    	            $celldata = $value;
                }
                if (strlen($reportUrl)) {
                    $celldata .= "<br>&nbsp;";
                    $celldata .= "<br><a href=\"$reportUrl\">";
                    $celldata .= pacsone_gettext("Observation Reports");
                    $celldata .= "</a>";
                }
                if (strlen($value) && strlen($celldata))
                    $value = $celldata;
            }
            else if (!strcasecmp($key, pacsone_gettext("Read By")) && strlen($value)) {
                if ($dbcon->isAdministrator($value))
                    $value = pacsone_gettext("Administrator");
                else {
                    $subq = "select * from privilege where username=?";
                    $subList = array($value);
                    $profile = $dbcon->preparedStmt($subq, $subList);
                    if ($profile && ($userRow = $profile->fetch(PDO::FETCH_ASSOC))) {
                        $name = $userRow['firstname'] . " " . $userRow['lastname'];
                        $email = $userRow['email'];
                        if (strlen($email))
                            $value = "<a href='mailto:$email'>$name</a>";
                        else
    	                    $value = $name;
                    }
                }
            }
            else if (!strcasecmp($key, pacsone_gettext("Consistency"))) {
                $imgsrc = "";
                if ($value == 1)
                    $imgsrc = "<img src='ok.gif' title='Consistent With Worklist Data'>";
                else if ($value == 2)
                    $imgsrc = "<img src='warning.gif' title='Inconsistent With Worklist Data'>";
                $value = $imgsrc;
            }
            else if (!strcasecmp($field, "received")) {
                $value = $dbcon->formatDateTime($value);
            }
            else if (strcasecmp($field, "modalities") == 0) {
                if (strlen($value) == 0)
                    $value = $dbcon->getStudyModalities($uid);
            }
            if (!strlen($value))
                $value = pacsone_gettext("N/A");
    	    print "\t<td>$MYFONT$value</font></td>\n";
        }
        print "\t<td>$instances</td>\n";
        print "</tr>\n";
    }
    print "</table>\n";
    */

  // ----------------------------------------------------

    // if ($checkbox) {
    //     displayButtons("study", $buttons, null);
    // }
    print "</form>\n";
    print "<script src=\"cornerstone/jquery.min.js\"></script>\n";
    print   "<script>
                $(document).ready(function(){
                    $(\"#myInput\").on(\"keyup\", function() {
                        var value = $(this).val().toLowerCase();
                        $(\"#studyTable tr\").filter(function() {
                        $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
                        });\n
                    });\n
                });
            </script>\n";
}

function displaySeries(&$list, $preface, $url, $offset, $all, $tagged, $showStudyNotes)
{
    // display Study Notes
    global $BGCOLOR;
    global $MYFONT;
    global $CUSTOMIZE_PATIENT;
    print "<table class=\"table table-bordered\" width=100% border=0 cellspacing=5 cellpadding=0>\n";
    print "<tr valign=top>";
    if ($showStudyNotes) {
        print "<td width=\"20%\">"; // -----  Left panel
        $params = array();
        parse_str($url, $params);
        $studyUid = $params["studyId"];
        displayStudyNotes($studyUid);
        print "</td>";
        //print "<td width=1 bgcolor=$BGCOLOR><img src=blank.gif width=1 height=1></td>";
    }
    global $dbcon;
    if ($showStudyNotes && count($list) == 0) {
        // study is empty, check if it has been exported
        $query = "select * from exportedstudy where uuid=? order by exported desc";
        $bindList = array($studyUid);
        $result = $dbcon->preparedStmt($query, $bindList);
        if ($result && $result->rowCount()) {
            $row = $result->fetch(PDO::FETCH_ASSOC);
            print "<td = width=\"20%\"><br>";   // -----  Left panel
            printf(pacsone_gettext("This study has been <a href='tools.php'>Exported</a> by <b>%s</b> to media label \"<u><b>%s</b></u>\" on %s<br>"), $row['username'], $row['label'], $row['exported']);
            print "<br>";
            printf(pacsone_gettext("To restore all series of this study, please insert the media labeled \"<b><u>%s</u></b>\" and <a href='tools.php'>Import</a> from that media<br>"), $row['label']);
            print "</td></tr>";
            print "</table>";
            return;
        }
    }
    print "<td>";  // right panel
    $rows = displayPageControl(pacsone_gettext("Series"), $list, $preface, $url, $offset, $all);
    $checkbox = 0;
	$username = $dbcon->username;
	$modifyAccess = $dbcon->hasaccess("modifydata", $username);
	$forwardAccess = $dbcon->hasaccess("forward", $username);
	$downloadAccess = $dbcon->hasaccess("download", $username);
    $printAccess = $dbcon->hasaccess("print", $username);
    if ($printAccess) {
        $printers = $dbcon->query("select printscp from applentity where printscp=1");
        if ($printers && ($printers->rowCount() == 0))
            $printAccess = 0;
    }
    $exportAccess = $dbcon->hasaccess("export", $username);
	// check if Java Applet viewer exists
	$showDicom = 0;
	if (appletExists()) {
		$showDicom = 1;
	}
    if (($modifyAccess + $forwardAccess + $printAccess + $exportAccess + $downloadAccess + $showDicom) && count($rows)) {
        $checkbox = 1;
        print "<form method='POST' action='actionItem.php'>\n";
        $buttons = array(
            'Forward'   => array(pacsone_gettext('Forward'), pacsone_gettext('Forward checked series'), $forwardAccess),
            'Delete'    => array(pacsone_gettext('Delete'), pacsone_gettext('Delete checked series'), $modifyAccess),
            'Print'     => array(pacsone_gettext('Print'), pacsone_gettext('Print checked series'), $printAccess),
            'Export'    => array(pacsone_gettext('Export'), pacsone_gettext('Export checked series'), $exportAccess),
            'Show'      => array(pacsone_gettext('Show'), pacsone_gettext('Show images of checked series'), $showDicom),
            'Download'  => array(pacsone_gettext('Download'), pacsone_gettext('Download images of checked series'), $downloadAccess),
        );
        if ($dbcon->getAutoConvertJPG()) {
            $buttons['Download JPG'] = array(pacsone_gettext('Download JPG'), pacsone_gettext('Download converted JPG/GIF images of checked series'), $downloadAccess);
        }
        displayButtons("series", $buttons, null);
    }
    // check if need to toggle sorting order
    if (isset($_SESSION['sortToggle'])) {
        $toggle = 1 - $_SESSION['sortToggle'];
        $link = urlReplace($url, "toggle", $toggle);
    } else {
        $link = $url;
    }
    // links to different sorting methods
    $links = array(
        pacsone_gettext("Series Number")       => urlReplace($link, "sort", "cmp_seriesnum"),
        pacsone_gettext("Date")                => urlReplace($link, "sort", "cmp_seriesdate"),
    );
    // display the following columns: column name <=> database field
    $columns = array(
        pacsone_gettext("Series Number")    => "seriesnumber",
        pacsone_gettext("Date")             => "seriesdate",
        pacsone_gettext("Time")             => "seriestime",
        pacsone_gettext("Modality")         => "modality",
        pacsone_gettext("Body Part")        => "bodypart",
        pacsone_gettext("Operator Name")    => "operatorname",
        pacsone_gettext("Total Instances")  => "instances",
        pacsone_gettext("Description")      => "description");
    print "<table class=\"table\" width=100% border=0 cellpadding=5>\n";
    print "<tr class=\"success\">\n";
    if ($checkbox) {
        print "\t<td></td>\n";
    }
    if (!$showStudyNotes) {
        global $CUSTOMIZE_PATIENT_ID;
        global $CUSTOMIZE_PATIENT_NAME;
        print "\t<td><b>$CUSTOMIZE_PATIENT_ID</b></td>\n";
        print "\t<td><b>$CUSTOMIZE_PATIENT_NAME</b></td>\n";
        print "\t<td><b>" . pacsone_gettext("Study ID") . "</b></td>\n";
        print "\t<td><b>" . pacsone_gettext("Accession Number") . "</b></td>\n";
    }
    foreach (array_keys($columns) as $key) {
        if (count($rows) && isset($links[$key])) {
            $link = $links[$key];
            print "\t<td><b><a href=$link>$key</a></b></td>\n";
        } else {
            print "\t<td><b>$key</b></td>\n";
        }
    }
    print "</tr class=\"Danger\">\n";
    // find patient id
    if ($showStudyNotes && count($rows)) {
        $studyUid = $rows[0]['studyuid'];
        $res = $dbcon->query("SELECT * FROM study WHERE uuid='$studyUid'");
        $studyRow = $res->fetch(PDO::FETCH_ASSOC);
        $origid = $studyRow['patientid'];
        $patientId = urlencode($origid);
        // log this patient access into the journal
        $journal = 0;
        if (isset($_SESSION['lastPatient'])) {
            $journal = strcasecmp($origid, $_SESSION['lastPatient']);
        } else {
            $journal = 1;
        }
        if ($journal) {
            $_SESSION['lastPatient'] = $origid;
            $dbcon->logJournal($username, "View", $CUSTOMIZE_PATIENT, $origid);
        }
    }
    foreach ($rows as $row) {
        $uid = $row['uuid'];
        print "<tr class=\"Info\">\n";
        if ($checkbox) {
	        print "\t<td align=center width='1%'>\n";
	        print "\t\t<input type='checkbox' name='entry[]' value='$uid'></td>\n";
        }
        if (!$showStudyNotes) {
            $studyUid = $row['studyuid'];
            $res = $dbcon->query("SELECT * FROM study WHERE uuid='$studyUid'");
            $studyRow = $res->fetch(PDO::FETCH_ASSOC);
            $origid = $studyRow['patientid'];
            $patientId = urlencode($origid);
            $patientName = $dbcon->getPatientName($origid);
            $studyId = $studyRow['id'];
            $accession = $studyRow['accessionnum'];
            $value = pacsone_gettext("N/A");
            if (strlen($patientId))
                print "\t<td><a href='study.php?patientId=$patientId'>$origid</a></td>\n";
            else
    	        print "\t<td>$MYFONT$value</font></td>\n";
    	    print "\t<td>$MYFONT$patientName</font></td>\n";
            $value = pacsone_gettext("N/A");
            $value = strlen($studyId)? $studyId : pacsone_gettext("Study Details");
            print "\t<td><a href='series.php?patientId=$patientId&studyId=$studyUid'>$value</a></td>\n";
            $value = strlen($accession)? $accession : pacsone_gettext("N/A");
    	    print "\t<td>$MYFONT$value</font></td>\n";
        }
		// count how many instances belong to this serie
        $result = $dbcon->query("SELECT COUNT(uuid) FROM image WHERE seriesuid='$uid'");
        $instances = $result->fetchColumn();
		$page = "image.php";
		if (isset($row['modality']) && (!strcasecmp($row['modality'], "SR") || !strcasecmp($row['modality'], "KO")))
			$page = "sreport.php";
        foreach ($columns as $key => $field) {
            if (strcasecmp($field, "instances"))
            {
                if (isset($row[$field])) {
            	    $value = $row[$field];
                    if (strcasecmp($field, "seriesdate") == 0)
                        $value = $dbcon->formatDate($value);
                }
                else
                    $value = pacsone_gettext("N/A");
				if (!strcasecmp($key, pacsone_gettext("Modality")) && !strcasecmp($value, "SR"))
					$value = pacsone_gettext("Structured Report");
	            if (strcasecmp($key, pacsone_gettext("Series Number")) == 0) {
					if (!strcmp($value, pacsone_gettext("N/A")))
						$value = pacsone_gettext("Series Details");
    	            printf("\t<td>%s<a href='$page?patientId=%s&studyId=%s&seriesId=%s'>%s</a></td>\n",
                        $MYFONT, $patientId, $studyUid, $uid, $value);
				}
	            else
    	            print "\t<td>$MYFONT$value</font></td>\n";
            } else {
				// use the database instance count instead
                print "\t<td>$MYFONT$instances</font></td>\n";
			}
        }
        print "</tr>\n";
    }
    print "</table>\n";
    if ($checkbox) {
        displayButtons("series", $buttons, null);
	    print "</form>\n";
    }
    // show link to display all tagged images
    if ($showStudyNotes && $tagged) {
        if ($tagged > 1) {
            $preface = sprintf(pacsone_gettext("There are total of %d <a href='taggedImage.php?studyId=%s'>Tagged Images</a> in Study: %s for %s: <a href='study.php?patientId=%s'>%s</a>"), $tagged, $studyUid, $dbcon->getStudyId($studyUid), $CUSTOMIZE_PATIENT, $patientId, $dbcon->getPatientName($origid));
        } else {
            $preface = sprintf(pacsone_gettext("There is total of %d <a href='taggedImage.php?studyId=%s'>Tagged Image</a> in Study: %s for %s: <a href='study.php?patientId=%s'>%s</a>"), $tagged, $studyUid, $dbcon->getStudyId($studyUid), $CUSTOMIZE_PATIENT, $patientId, $dbcon->getPatientName($origid));
        }
        print "<p>$preface<br>\n";
    }
    print "</td></tr>";
    print "</table>";
}

function displayImage(&$list, $preface, $url, $offset, $all, $showStudyNotes)
{
    $taggedOnly = stristr($url, "tagged=1")? 1 : 0;
    // display Study Notes
    global $BGCOLOR;
    global $TAGCOLOR;
    print "<table width=100% border=0 cellspacing=5 cellpadding=0>\n";
    print "<tr valign=top>";
    if ($showStudyNotes) {
        print "<td class=notes>";
        $params = array();
        $suburl = stristr($preface, "studyId");
        $suburl = substr($suburl, 0, strpos($suburl, "'>"));
        parse_str($suburl, $params);
        displayStudyNotes($params['studyId']);
        print "</td>";
        print "<td width=1 bgcolor=$BGCOLOR><img src=blank.gif width=1 height=1></td>";
    }
    print "<td>";
    global $MYFONT;
    global $dbcon;
    $rows = displayPageControl(pacsone_gettext("Instances"), $list, $preface, $url, $offset, $all);
    // check user privileges
	$username = $dbcon->username;
	$checkbox = 0;
	$access = $dbcon->hasaccess("modifydata", $username);
	$downloadAccess = $dbcon->hasaccess("download", $username);
    $printAccess = $dbcon->hasaccess("print", $username);
    if ($printAccess) {
        $printers = $dbcon->query("select printscp from applentity where printscp=1");
        if ($printers && ($printers->rowCount() == 0))
            $printAccess = 0;
    }
    $exportAccess = $dbcon->hasaccess("export", $username);
	$forwardAccess = $dbcon->hasaccess("forward", $username);
	if (($access || $downloadAccess || $printAccess || $exportAccess || $forwardAccess) && sizeof($rows)) {
    	$checkbox = 1;
	}
	// check if Java Applet viewer exists
	$showDicom = 0;
	if (appletExists()) {
		$showDicom = 1;
		$checkbox = 1;
	}
	if ($checkbox && count($rows)) {
    	print "<form method='POST' action='actionItem.php'>\n";
        $buttons = array(
            'Show'      => array(pacsone_gettext('Show'), pacsone_gettext('Show checked images'), $showDicom),
            'Forward'   => array(pacsone_gettext('Forward'), pacsone_gettext('Forward checked images'), $forwardAccess),
            'Delete'    => array(pacsone_gettext('Delete'), pacsone_gettext('Delete checked images'), $access),
            'Print'     => array(pacsone_gettext('Print'), pacsone_gettext('Print checked images'), $printAccess),
            'Export'    => array(pacsone_gettext('Export'), pacsone_gettext('Export checked images'), $exportAccess),
            'Download'  => array(pacsone_gettext('Download'), pacsone_gettext('Download checked images'), $downloadAccess),
            'Tag'       => array(pacsone_gettext('Tag'), pacsone_gettext('Tag checked images'), 1),
            'Un-Tag'    => array(pacsone_gettext('Un-Tag'), pacsone_gettext('Un-Tag checked images'), 1),
        );
        if ($dbcon->getAutoConvertJPG()) {
            $buttons['Download JPG'] = array(pacsone_gettext('Download JPG'), pacsone_gettext('Download converted JPG/GIF images of checked images'), $downloadAccess);
        }
        displayButtons("image", $buttons, null);
	}
    // get the thumbnail directory if configured
    $thumbnaildir = "";
    $flashdir = "";
    $result = $dbcon->query("select thumbnaildir,flashdir from config");
    if ($result && $row = $result->fetch(PDO::FETCH_NUM)) {
        if (strlen($row[0]) && file_exists($row[0]))
            $thumbnaildir = $row[0];
        if (strlen($row[1]) && file_exists($row[1]))
            $flashdir = $row[1];
    }
	global $IMAGE_MATRIX;
    $xdim = $IMAGE_MATRIX % 10;
    $ydim = $IMAGE_MATRIX / 10;
    $cellwidth = 100 / $xdim;
	print "<table width=100% border=0 cellpadding=5>\n";
    $count = 0;
    set_time_limit(0);
	foreach ($rows as $row) {
    	$uid = $row['uuid'];
    	$seriesUid = $row['seriesuid'];
        $mimetype = isset($row['mimetype'])? $row['mimetype'] : "";
        if ($count % $xdim == 0)
            print "<tr>";
        $tagged = $row["tagged"]? "bgcolor=$TAGCOLOR" : "";
		print "\t<td valign=center align='center' $tagged>\n";
        print "\t<table width=100% border=0 cellpadding=2>\n";
        print "<tr>";
		if ($checkbox) {
			print "\t\t<td width='1%'><input type='checkbox' name='entry[]' value='$uid'</td>\n";
		}
        $imgsize = "width=100 height=100";
        $thumbUp = 1;
        $flashVideo = 0;
        $thumbdir = "thumbnails";
        $path = $row["path"];
        $xfersyntax = $row["xfersyntax"];
        $dir = strlen($thumbnaildir)? $thumbnaildir : getcwd();
        $dir = strtr($dir, "\\", "/");
        // append '/' at the end if not so already
        if (strcmp(substr($dir, strlen($dir)-1, 1), "/"))
            $dir .= "/";
        $dir .= "$thumbdir/";
        // create the thumbnails directory if it doesn't exist
        if (!is_dir($dir))
            mkdir($dir);
        $thumbnail = $dir . $uid;
        if (file_exists($thumbnail . ".gif"))
            $thumbnail .= ".gif";
        else if (file_exists($thumbnail . ".jpg"))
            $thumbnail .= ".jpg";
        else if (!strcmp($xfersyntax, "1.2.840.10008.1.2.4.100") ||
                 !strcmp($xfersyntax, "1.2.840.10008.1.2.4.102") ||
                 !strcmp($xfersyntax, "1.2.840.10008.1.2.4.103")) {
            $thumbUp = 0;
            if (strlen($flashdir) == 0) {
                // default flash video directory
                $flashdir = strtr(dirname($_SERVER['SCRIPT_FILENAME']), "\\", "/");
                // append '/' at the end if not so already
                if (strcmp(substr($flashdir, strlen($flashdir)-1, 1), "/"))
                    $flashdir .= "/";
                $flashdir .= "flash/";
            }
            $flashdir = strtr($flashdir, "\\", "/");
            // append '/' at the end if not so already
            if (strcmp(substr($flashdir, strlen($flashdir)-1, 1), "/"))
                $flashdir .= "/";
            // check if converted flash video exists
            $embeddedVideoUrl = checkForConvertedVideo($flashdir, $path);
            if (strlen($embeddedVideoUrl)) {
                $flashVideo = 1;
            } else {
                $thumbnail = "error.png";
                $thumbdir = ".";
                $imgsize = "";
            }
        } else if (strlen($mimetype)) {
            // encapsulated documents
            $thumbUp = 0;
            $encapsulated = $path . ".encap";
            if (!file_exists($encapsulated)) {
                $thumbnail = "error.png";
                $thumbdir = ".";
                $imgsize = "";
                $mimetype = "";
            }
        // do not bother to convert to dynaic GIF for large number of frames
        } else if (isset($row['numframes']) && $row['numframes'] > 2048) {
            $thumbUp = 0;
            $thumbnail = "error.png";
            $thumbdir = ".";
            $imgsize = "";
        } else {
            $ok = 0;
            // create a thumbnail image if not there
            $src = function_exists("imagick_readimage")? imagick_readimage($path) : false;
            if ($src && !imagick_iserror($src)) {
                // write thumbnail image
                if (imagick_getlistsize($src) > 1)
                    $thumbnail .= ".gif";
                else
                    $thumbnail .= ".jpg";
                if (imagick_writeimage($src, $thumbnail)) {
                    $handle = imagick_readimage($thumbnail);
                    imagick_scale($handle, 100, 100, "!");
                    imagick_writeimage($handle, $thumbnail);
                    imagick_destroyhandle($handle);
                    $ok = 1;
                }
            }
            if (!$ok) {
                $thumbUp = 0;
                $thumbnail = "error.png";
                $thumbdir = ".";
                $imgsize = "";
            }
            if ($src)
                imagick_destroyhandle($src);
        }
        $basename = basename($thumbnail);
        print "\t<td width='$cellwidth%'><table width=100% border=0 cellpadding=2>\n";
        $instance = $row["instance"];
        $alt = sprintf(pacsone_gettext("Instance %d"), $instance);
        if ($thumbUp) {
            $imgsrc = strlen($thumbnaildir)? ("tempimage.php?path=" . urlencode($thumbnail) . "&purge=0") : "$thumbdir/$basename";
            print "\t<tr><td align='center'><a href='imageMatrix.php?seriesId=$seriesUid&offset=0'><img src='$imgsrc' alt='$alt' border=0 $imgsize></a></td></tr>\n";
        } else if ($flashVideo) {
            print $embeddedVideoUrl;
        } else if (strlen($mimetype) && isset($encapsulated)) {
            // encapsulated documents
            global $ENCAPSULATED_DOC_ICON_TBL;
            $imgsrc = isset($ENCAPSULATED_DOC_ICON_TBL[strtoupper($mimetype)])? $ENCAPSULATED_DOC_ICON_TBL[strtoupper($mimetype)] : "question.jpg";
            $link = "encapsulatedDoc.php?path=" . urlencode($encapsulated) . "&mimetype=" . urlencode($mimetype);
            print "\t<tr><td align='center'><a href=\"$link\"><img src='$imgsrc' border=0 $imgsize></a></td></tr>\n";
        } else
            print "\t<tr><td align='center'><img src='$thumbdir/$basename' alt='$alt' border=0 $imgsize></td></tr>\n";
        print "\t<tr><td align='center'>$MYFONT<a href='showTags.php?uid=$uid'>";
        printf(pacsone_gettext("Instance %d"), $instance);
        print "</a></font></td></tr>";
        print "\t</table></td>\n";
        print "\t</tr></table>\n";
        print "</td>\n";
        if ($count % $xdim == ($xdim - 1))
		    print "</tr>\n";
        $count++;
	}
    $left = $count % $xdim;
    if ($left > 0) {
        $left = $xdim - $left;
        for ($i = 0; $i < $left; $i++)
            print "<td width='$cellwidth%'>&nbsp;</td>";
        print "</tr>";
    }
	print "</table>\n";
	if ($checkbox && count($rows)) {
        displayButtons("image", $buttons, null);
    	print "</form>\n";
	}
    print "</td></tr>";
    print "</table>";
}

function displayApplEntity($result, $preface)
{
    include_once 'toggleRowColor.js';
    global $dbcon;
    $mytitle = $dbcon->getMyAeTitle();
    print "<table width=100% cellpadding=0 cellspacing=0 border=0>\n";
    print "<tr><td>\n";
    print "<br>$preface\n";
    print "</td></tr>\n";
    print "<tr><td>\n";
    $records = $result->rowCount();
	// check if user has write access to table
	$access = 0;
	$username = $dbcon->username;
	$queryAccess = $dbcon->hasaccess("query", $username);
	if ($dbcon->hasaccess("modifydata", $username)) {
    	$access = 1;
        print "<form method='POST' action='modifyAeTitle.php'>\n";
        print "<input type='hidden' name='actionvalue'>\n";
	}
    // display all columns
    global $BGCOLOR;
    global $MYFONT;
    global $XFER_SYNTAX_TBL;
    print "<table width=100% border=0 cellpadding=5 class='mouseover optionrow'>\n";
    print "<tr class=listhead bgcolor=$BGCOLOR>\n";
	if ($access && $records) {
        print "\t<td></td>\n";
	}
    // display the following columns: column name <=> database field
    $columns = array(
        pacsone_gettext("AE Title")                          => "title",
        pacsone_gettext("Description")                       => "description",
        pacsone_gettext("Host")                              => "hostname",
        pacsone_gettext("IP Address")                        => "ipaddr",
        pacsone_gettext("Port Number")                       => "port",
        pacsone_gettext("Enable Access")                     => "allowaccess",
        pacsone_gettext("Archive Directory")                 => "archivedir",
        pacsone_gettext("Allow Dicom Command")               => "privilege",
        pacsone_gettext("Maximum Connections")               => "maxsessions",
        pacsone_gettext("Preferred Transfer Syntax Tx")      => "xfersyntax",
        pacsone_gettext("Preferred Transfer Syntax Rx")      => "xfersyntaxrx",
        pacsone_gettext("Group")                             => "aegroup",
    );
    foreach (array_keys($columns) as $key) {
        print "\t<td><b>$key</b></td>\n";
    }
    print "<td><b>";
    print pacsone_gettext("Disk Usage");
    print "</b></td>\n";
    print "<td><b>";
    print pacsone_gettext("Verify Connection");
    print "</b></td>\n";
    print "<td><b>";
    print pacsone_gettext("Application Type");
    print "</b></td>\n";
    print "\t<td><b>";
    print pacsone_gettext("Edit");
    print "</b></td>\n";
    print "</tr>\n";
    $count = 0;
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $style = ($count++ & 0x1)? "oddrows" : "evenrows";
        print "<tr class='$style'>\n";
        $title = $row['title'];
        $url = "<a href='modifyAeTitle.php?title=$title'>";
		if ($access) {
			print "\t<td align=center width='1%'>\n";
			print "\t\t<input type='checkbox' name='entry[]' value='$title'</td>\n";
		}
        foreach ($columns as $key => $field) {
            if (isset($row[$field])) {
            	$value = $row[$field];
                $align = "left";
                if (!strcasecmp($field, "allowaccess")) {
                    $align = "center";
                    $enabled = ($value == 1)? pacsone_gettext("Enabled") : pacsone_gettext("Disabled");
                    $value = "<img src=\"" . (($value == 1)? "enabled.gif" : "disabled.gif");
                    $value .= "\" alt=\"$enabled\">";
                }
                else if (!strcasecmp($field, "privilege")) {
                    global $DICOM_CMDACCESS_TBL;
                    $command = "";
                    foreach ($DICOM_CMDACCESS_TBL as $cmdaccess => $cmd) {
                        if ($value & $cmdaccess) {
                            if (strlen($command))
                                $command .= "<br>";
                            $command .= $cmd;
                        }
                    }
                    if (strlen($command)) {
                        $value = $command;
                        // check if there is any AE Filter defined
                        $subq = "select * from aefilter where sourceae=?";
                        $subList = array($title);
                        $filters = $dbcon->preparedStmt($subq, $subList);
                        if ($filters && $filters->rowCount()) {
                            $value .= "<br>$url";
                            $value .= pacsone_gettext("Command Filters") . "</a>";
                        }
                    } else {
                        $value = pacsone_gettext("None");
                    }
                }
                else if (!strcasecmp($field, "xfersyntax") ||
                         !strcasecmp($field, "xfersyntaxrx")) {
                    if (array_key_exists($value, $XFER_SYNTAX_TBL))
                        $value = $XFER_SYNTAX_TBL[$value][2] . " - $value";     
                    else
                        $value = pacsone_gettext("N/A");
                }
                else if (strcasecmp($field, "archivedir") == 0) {
                    // check if Long-Term Archive Directory is defined
                    $longterm = $row['longtermdir'];
                    if (strlen($longterm)) {
                        $value .= "<br>$url" . pacsone_gettext("More...") . "</a>";
                    }
                }
                else if (strcasecmp($field, "port") == 0 && $row['tlsoption']) {
                    $alt = pacsone_gettext("Dicom TLS Option enabled");
                    $value = "<img src='ssl.png' border=0 title='$alt' alt='$alt'>&nbsp;" . $value;
                }
                print "\t<td align='$align'>$MYFONT$value</font></td>\n";
			} else {
                print "\t<td>$MYFONT";
                print pacsone_gettext("N/A");
                print "</font></td>\n";
            }
        }
        $dir = $row['archivedir'];
        if (strlen($dir) && is_dir($dir)) {
			$free = disk_free_space($dir);
			$total = disk_total_space($dir);
			$usage = (1.0 - $free/$total) * 100;
			$value = number_format($usage, 2, '.', '') . "%";
			if ($usage > 90)
            	print "\t<td><font color=red><b>$value</b></font></td>\n";
			else
            	print "\t<td>$value</td>\n";
        }
        else
            print "\t<td>" . pacsone_gettext("N/A") . "</td>\n";
        if (isset($row['port'])) {
            $ipaddr = $row['ipaddr'];
            $host = urlencode($row['hostname']);
            $port = $row['port'];
            $tls = $row['tlsoption'];
            $href = "cecho.php?ipaddr=$ipaddr&hostname=$host&port=$port&aetitle=$title&mytitle=$mytitle&tls=$tls";
            print "\t<td>$MYFONT<a href=$href>";
            print pacsone_gettext("Echo");
            print "</a></font></td>\n";
			if ($queryAccess) {
                $appltype = 0;
           		print "\t<td nowrap>";                
				// remote Query/Retrieve SCP
				if ($row['queryscp']) {
                    if ($appltype) print "<br>";
            		$href = "searchRemote.php?aetitle=$title";
                    print "$MYFONT<a href=$href>";
                    print pacsone_gettext("Query/Retrieve");
                    print "</a></font>";
                    $appltype++;
				}
				// remote Modality Worklist SCP
				if ($row['worklistscp']) {
                    if ($appltype) print "<br>";
            		$href = "searchRemoteWorklist.php?aetitle=$title";
                    print "$MYFONT<a href=$href>";
                    print pacsone_gettext("Get Worklist");
                    print "</a></font>";
                    $appltype++;
				}
                // remote Print SCP
				if ($row['printscp']) {
                    if ($appltype) print "<br>";
            		$href = "printer.php?ipaddr=$ipaddr&hostname=$host&port=$port&aetitle=$title&mytitle=$mytitle&tls=$tls";
            		print "$MYFONT<a href=$href>";
                    print pacsone_gettext("Printer Properties");
                    print "</a></font>";
                    $appltype++;
                }
                // remote Storage Commitment Report SCP
				if ($row['commitscp']) {
                    if ($appltype) print "<br>";
                    print pacsone_gettext("Storage Commitment Report SCP");
                    $appltype++;
                }
                if (!$appltype)
            		print "$MYFONT" . pacsone_gettext("N/A") . "</font>";
                print "</td>\n";
			}
			else {
                print "\t<td>$MYFONT" . pacsone_gettext("N/A") . "</font></td>\n";
			}
        }
        else {
            print "\t<td>$MYFONT" . pacsone_gettext("N/A") . "</font></td>\n";
            print "\t<td>$MYFONT" . pacsone_gettext("N/A") . "</font></td>\n";
        }
		if ($access) {
			print "\t<td>$MYFONT$url";
            print pacsone_gettext("Edit");
            print "</a></font></td>\n";
        } else {
			print "\t<td>$MYFONT" . pacsone_gettext("N/A") . "</font></td>\n";
        }
        print "</tr>\n";
    }
    print "</table>\n";
    if ($access) {
    	print "<p><table width=20% border=0 cellpadding=5>\n";
        print "<tr>\n";
        if ($records) {
            $check = pacsone_gettext("Check All");
            $uncheck = pacsone_gettext("Uncheck All");
            print "<td><input type=button value='$check' onClick='this.value=checkAll(this.form,\"entry\", \"$check\", \"$uncheck\")'></td>\n";
            $value = pacsone_gettext("Delete");
            print "<td><input type=submit value='$value' name='action' title='";
            print pacsone_gettext("Delete checked application entities");
            print "' onclick='switchText(this.form,\"actionvalue\",\"Delete\");return confirm(\"";
            print pacsone_gettext("Are you sure?");
            print "\");'></td>\n";
        }
        $noAdd = false;
        $result = $dbcon->query("select maxaes from config");
        if ($result && ($config = $result->fetch(PDO::FETCH_NUM))) {
            if ($records >= $config[0])
                $noAdd = true;
        }
        if (!$noAdd) {
            print "<td><input type=submit value='";
            print pacsone_gettext("Add");
            print "' name='action' title='";
            print pacsone_gettext("Add new application entity");
            print "' onclick='switchText(this.form,\"actionvalue\",\"Add\")'></td>\n";
        }
        print "</tr>\n";
    	print "</table>\n";
	    print "</form>\n";
    }
    print "</tr></td>\n";
    print "</table>\n";
}

function displayJobStatus($result, $preface, $status, $type, $url, $offset, $all)
{
    include_once 'toggleRowColor.js';
    global $dbcon;
    $list = array();
    while ($row = $result->fetch(PDO::FETCH_ASSOC))
        $list[] = $row;
    $rows = displayPageControl(pacsone_gettext("Jobs"), $list, $preface, $url, $offset, $all);
    // check whether to bypass Series level
    $skipSeries = 0;
    $config = $dbcon->query("select skipseries from config");
    if ($config && ($row = $config->fetch(PDO::FETCH_NUM)))
        $skipSeries = $row[0];
    print "<table width=100% cellpadding=0 cellspacing=0 border=0>\n";
    $records = count($rows);
	$failed = 0;
	$forwardjob = 0;
	if ($records) {
        print "<form method='POST' action='status.php'>\n";
        print "<input type='hidden' name='actionvalue'>\n";
        if ($type >= 0)
            print "<input type='hidden' name='type' value=$type>\n";
	}
    // display all columns
    global $BGCOLOR;
    global $MYFONT;
    global $SCHEDULE_TBL;
    global $DBJOB_PRIORITY_TBL;
    global $CUSTOMIZE_PATIENT_ID;
    global $CUSTOMIZE_PATIENT_NAME;
    global $CUSTOMIZE_PATIENT_SEX;
    global $CUSTOMIZE_PATIENT_DOB;
    print "<tr><td>\n";
    print "<table width=100% border=0 cellpadding=1 class='mouseover optionrow'>\n";
    print "<tr class=listhead bgcolor=$BGCOLOR>\n";
	if ($records) {
        global $JOB_STATUS_COLUMNS_TBL;
        print "\t<td></td>\n";
        $columns = array_keys($rows[0]);
        foreach ($columns as $column) {
            $header = ucfirst($column);
            $desc = isset($JOB_STATUS_COLUMNS_TBL[$column])? $JOB_STATUS_COLUMNS_TBL[$column] : pacsone_gettext("N/A");
            $header = "<a href=\"status.php?type=$type&order=$header\">$desc</a>";
            print "\t<td><b>$header</b></td>\n";
        }
	}
    print "</tr>\n";
    $count = 0;
    foreach ($rows as $row) {
        $style = ($count++ & 0x1)? "oddrows" : "evenrows";
        print "<tr class='$style'>\n";
		if (strcasecmp($row["status"], "Failed") == 0 ) {
			$failed++;
            if (strcasecmp($row["type"], "Forward") == 0)
                $forwardjob++;
		}
		if ($records) {
			print "\t<td align=center width='1%'>\n";
			$jobid = $row["id"];
			print "\t\t<input type='checkbox' name='entry[]' value=$jobid>";
			print "</td>\n";
		}
        foreach ($row as $key => $value) {
            $uid = $row['uuid'];
            if (isset($value)) {
				// hide built-in or reserved values
				if ((strcasecmp($key, "aetitle") == 0) && ($value[0] == '_'))
					$value = pacsone_gettext("N/A");
                else if (!strcasecmp($key, "Schedule")) {
                    $value = isset($SCHEDULE_TBL[$value])? $SCHEDULE_TBL[$value] : pacsone_gettext("N/A");
                }
                else if (strcasecmp($key, "Priority") == 0) {
                    $value = isset($DBJOB_PRIORITY_TBL[$value])? $DBJOB_PRIORITY_TBL[$value] : pacsone_gettext("N/A");
                }
                else if (!strcasecmp($key, "Details")) {
                    if (!strcasecmp($row["type"], "MatchORM")) {
                        $tokens = explode("|", $value);
                        $value = "";
                        foreach ($tokens as $token) {
                            list($tag, $data) = sscanf($token, "%x=%s");
                            switch ($tag) {
                            case 0x00080050:
                                $value .= sprintf(pacsone_gettext("Accession Number: %s<br>"), $data);
                                break;
                            case 0x00081030:
                                $value .= sprintf(pacsone_gettext("Study Description: %s<br>"), $data);
                                break;
                            case 0x00100010:
                                $value .= sprintf("%s: %s<br>", $CUSTOMIZE_PATIENT_NAME, $data);
                                break;
                            case 0x00100020:
                                $value .= sprintf("%s: %s<br>", $CUSTOMIZE_PATIENT_ID, $data);
                                break;
                            case 0x00100030:
                                $value .= sprintf("%s: %s<br>", $CUSTOMIZE_PATIENT_DOB, $data);
                                break;
                            case 0x00100040:
                                $value .= sprintf("%s: %s<br>", $CUSTOMIZE_PATIENT_SEX, $data);
                                break;
                            default:
                                break;
                            }
                        }
                        if (strlen($value) == 0)
					        $value = pacsone_gettext("N/A");
                    } else {
                        // escape-quote any HTML special characters
                        $value = escapeHtmlKeepTags($value);
                    }
                }
                else if ((strcasecmp($key, "uuid") == 0) &&
                         (strcasecmp($row['class'], "Study") == 0) &&
                         !stristr($row['type'], "port")) {
                    $patientId = $dbcon->getPatientIdByStudyUid($uid);
                    $patName = $dbcon->getPatientNameByStudyUid($uid);
                    if ($patName && strlen($patName)) {
                        $value = sprintf("%s: %s<br>", $CUSTOMIZE_PATIENT_NAME, $patName);
                    } else {
                        if (strlen($patientId) == 0)
                            $patientId = pacsone_gettext("(Blank)");
                        $value = sprintf("%s: %s<br>", $CUSTOMIZE_PATIENT_ID, $patientId);
                    }
                    $studyId = $dbcon->getStudyId($uid);
                    if (strlen($studyId) && strcasecmp($studyId, $uid))
                        $value .= sprintf(pacsone_gettext("Study ID: %s<br>"), $studyId);
                    $accession = $dbcon->getAccessionNumber($uid);
                    if (strlen($accession))
                        $value .= sprintf(pacsone_gettext("Accession Number: %s<br>"), $accession);
                    $skip = ($skipSeries && $dbcon->hasSRseries($uid))? false : $skipSeries;
                    $url = $skip? "image.php" : "series.php";
                    $url .= "?patientId=" . urlencode($patientId);
                    $url .= "&studyId=" . urlencode($uid);
                    $value = "<a href=\"$url\">$value</a>";
                }
                else if ((strcasecmp($key, "uuid") == 0) &&
                         (strcasecmp($row['class'], "Patient") == 0) &&
                         !stristr($row['type'], "port")) {
                    $patName = $dbcon->getPatientName($uid);
                    if ($patName && strlen($patName)) {
                        $value = sprintf("%s: %s<br>", $CUSTOMIZE_PATIENT_NAME, $patName);
                    } else {
                        if (strlen($uid) == 0)
                            $uid = pacsone_gettext("(Blank)");
                        $value = sprintf("%s: %s<br>", $CUSTOMIZE_PATIENT_ID, $uid);
                    }
                    $url = "study.php?patientId=" . urlencode($uid);
                    $value = "<a href=\"$url\">$value</a>";
                }
                else if ((strcasecmp($key, "uuid") == 0) &&
                         (!strcasecmp($row['class'], "series") || !strcasecmp($row['class'], "image")) &&
                         !stristr($row['type'], "port") && !stristr($row['type'], "IntegrityCheck")) {
                    if (strcasecmp($row['type'], "Upload")) {
                        $url = strcasecmp($row['class'], "image")?
                            "searchSeries.php" : "searchImage.php";
                        $url .= "?uid=" . urlencode($uid);
                        $seriesUid = $uid;
                        $level = pacsone_gettext("Series");
                        if (strcasecmp($row['class'], "series")) {
                            $level = pacsone_gettext("Image");
                            $series = $dbcon->query("select seriesuid from image where uuid='$uid'");
                            if ($series && ($seriesRow = $series->fetch(PDO::FETCH_NUM)))
                                $seriesUid = $seriesRow[0];
                        }
                        $value = $dbcon->getPatientNameBySeriesUid($seriesUid);
                    } else {
                        $url = "home.php";
                        $level = pacsone_gettext("Upload");
                        $value = "";
                    }
                    $value = sprintf("%s: %s<br>", $CUSTOMIZE_PATIENT_NAME, (strlen($value)? $value : pacsone_gettext("N/A")));
                    $value .= "<a href='$url'>$level Uid: $uid</a>";
                }
                // display the URL link to scanned Patient List
                else if (!strcasecmp($key, "type") &&
                         !strcasecmp($value, "ImportScan")) {
                    $url = "importScan.php?jobid=$jobid";
                    $value = "<a href=\"$url\">$value</a>";
                }
                else if (!strcasecmp($key, "submittime") ||
                         !strcasecmp($key, "starttime") ||
                         !strcasecmp($key, "finishtime")) {
                    $value = $dbcon->formatDateTime($value);
                }
                print "\t<td>$MYFONT$value</font></td>\n";
			}
            else
                print "\t<td>$MYFONT" . pacsone_gettext("N/A") . "</font></td>\n";
        }
        print "</tr>\n";
    }
    print "</table>\n";
    print "</td></tr>\n";
    if ($records) {
        // show preface at bottom of page if there're many rows
        if ($records > 10) {
            print "<tr><td>\n";
            print "<br>$preface\n";
            print "</td></tr>\n";
            print "<tr><td><br></td></tr>\n";
        }
        print "<tr><td>\n";
    	print "<p><table width=20% border=0 cellpadding=5>\n";
        print "<tr>\n";
        $check = pacsone_gettext("Check All");
        $uncheck = pacsone_gettext("Uncheck All");
        print "<td><input type=button value='$check' onClick='this.value=checkAll(this.form,\"entry\", \"$check\", \"$uncheck\")'></input></td>\n";
		if ($failed && $forwardjob && strcasecmp($status, "Failed") == 0) {
            print "<td><input type=submit value='";
            print pacsone_gettext("Retry");
            print "' name='action' title='";
            print pacsone_gettext("Retry Failed Job");
            print "' onclick='switchText(this.form,\"actionvalue\",\"Retry\")'></input></td>\n";
		}
        print "<td><input type=submit value='";
        print pacsone_gettext("Delete");
        print "' name='action' title='";
        print pacsone_gettext("Delete Selected Jobs");
        print "' onclick='switchText(this.form,\"actionvalue\",\"Delete\");return confirm(\"";
        print pacsone_gettext("Are you sure?");
        print "\");'></td>\n";
        if (!strcasecmp($status, "Submitted") && ($type == 4)) {
            // show a button to change later-scheduled jobs to immediate jobs
            print "<td><input type=submit value='";
            print pacsone_gettext("Run Immediately");
            print "' name='action' title='";
            print pacsone_gettext("Run Selected Jobs Immediately");
            print "' onclick='switchText(this.form,\"actionvalue\",\"Run Immediately\")'></td>\n";
        }
        print "</tr>\n";
    	print "</table>\n";
        print "</td></tr>\n";
	    print "</form>\n";
    }
    print "</table>\n";
}

function displayRemotePatients($aetitle, &$identifier, &$matches)
{
    global $BGCOLOR;
    global $MYFONT;
    global $dbcon;
	$count = 0;
    // some AEs (e.g., CONQUEST) returns 2 C-FIND datasets with one of them being empty
	foreach ($matches as $match) {
        if (count($match->attrs))
            $count++;
    }
	// check user privileges
	$username = $dbcon->username;
	$moveAccess = $dbcon->hasaccess("move", $username);
    if ($count > 1) {
	    printf(pacsone_gettext("Remote AE <b>%s</b> returned %d matches:<P>"), $aetitle, $count);
    } else {
	    printf(pacsone_gettext("Remote AE <b>%s</b> returned %d match:<P>"), $aetitle, $count);
    }
    print "<table width=100% border=0 cellpadding=5>\n";
    print "<form method='POST' action='moveForm.php'>\n";
	print "<input type='hidden' name='source' value='$aetitle'></input>";
	$attrs = $identifier->attrs;
    print "<tr class=listhead bgcolor=$BGCOLOR>\n";
	$checkbox = 0;
	if ($moveAccess && $count) {
		$checkbox = 1;
		print "<td></td>";
	}
	foreach ($attrs as $attr) {
		$name = $identifier->getAttributeName($attr);
		print "<td>$name</td>";
	}
    print "</tr>\n";
	foreach ($matches as $match) {
        if (!count($match->attrs))
            continue;
		print "<tr>";
		$level = $match->getQueryLevel();
		print "<input type='hidden' name='level[]' value=$level>";
		if ($checkbox) {
			$uid = urlencode($match->getPatientId());
			print "<td><input type='checkbox' name='entry[]' value='$uid'></input></td>";
		}
		foreach ($attrs as $key) {
			if ($match->hasKey($key)) {
				$value = $match->attrs[$key];
				if ($key == 0x00100020) {
					$href = "remoteStudy.php?aetitle=$aetitle&patientid=" . urlencode($value);
					print "<td><a href=$href>$value</a></td>";
				} else {
					$value = trim($value);
					if (strlen($value))
						print "<td>$value</td>";
					else
						print "<td>" . pacsone_gettext("N/A") . "</td>";
				}
			}
			else
				print "<td>" . pacsone_gettext("N/A") . "</td>";
		}
		print "</tr>";
	}
	print "</table>\n";
	if ($checkbox) {
   		print "<p><table width=20% border=0 cellpadding=5>\n";
        $check = pacsone_gettext("Check All");
        $uncheck = pacsone_gettext("Uncheck All");
    	print "<td><input type=button value='$check' onClick='this.value=checkAll(this.form,\"entry\", \"$check\", \"$uncheck\")'</input></td>\n";
    	print "<td><input type=submit value='";
        print pacsone_gettext("Move");
        print "' title='";
        print pacsone_gettext("Move Selected Patients");
        print "'></input></td>\n";
   		print "</table>\n";
	}
    print "</form>\n";
}

function displayRemoteStudies($aetitle, &$identifier, &$matches)
{
    global $BGCOLOR;
    global $MYFONT;
    global $dbcon;
	$count = 0;
    // some AEs (e.g., CONQUEST) returns 2 C-FIND datasets with one of them being empty
	foreach ($matches as $match) {
        if (count($match->attrs))
            $count++;
    }
	// check user privileges
	$username = $dbcon->username;
	$moveAccess = $dbcon->hasaccess("move", $username);
    if ($count > 1) {
	    printf(pacsone_gettext("Remote AE <b>%s</b> returned %d matches:<P>"), $aetitle, $count);
    } else {
	    printf(pacsone_gettext("Remote AE <b>%s</b> returned %d match:<P>"), $aetitle, $count);
    }
    print "<table width=100% border=0 cellpadding=5>\n";
    print "<form method='POST' action='moveForm.php'>\n";
	print "<input type='hidden' name='source' value='$aetitle'></input>";
	$attrs = $identifier->attrs;
    print "<tr class=listhead bgcolor=$BGCOLOR>\n";
	$checkbox = 0;
	if ($moveAccess && $count) {
		$checkbox = 1;
		print "<td></td>";
	}
	foreach ($attrs as $attr) {
        if ($attr == 0x00200010) continue;
		$name = $identifier->getAttributeName($attr);
        // display Study ID instead of Study UID
        if ($attr == 0x0020000d)
            $name = pacsone_gettext("Study ID");
		print "<td>$name</td>";
	}
    print "</tr>\n";
	foreach ($matches as $match) {
        if (!count($match->attrs))
            continue;
		print "<tr>";
		$level = $match->getQueryLevel();
		print "<input type='hidden' name='level[]' value=$level>";
		if ($checkbox) {
			$uid = $match->getStudyUid();
            $xid = urlencode($uid);
			print "<td><input type='checkbox' name='entry[]' value='$xid'></input></td>";
            // pass along the Patient ID of the returned study
            if ($match->hasKey(0x00100020)) {
                $value = http_build_query(array($uid => $match->getPatientId()));
                print "<input type='hidden' name='patientids[]' value=\"$value\">";
            }
		}
		foreach ($attrs as $key) {
            if ($key == 0x00200010) continue;
			if ($match->hasKey($key)) {
				$value = $match->attrs[$key];
				if ($key == 0x0020000d) {
                    $patientid = urlencode($match->getPatientId());
					$href = "remoteSeries.php?aetitle=$aetitle&patientid=$patientid&uid=$value";
                    $value = "Study Details";
                    if (isset($match->attrs[0x00200010]) &&
                        strlen($match->attrs[0x00200010]))
                        $value = $match->attrs[0x00200010];
					print "<td><a href=$href>$value</a></td>";
				} else {
					$value = trim($value);
					if (strlen($value))
						print "<td>$value</td>";
					else
						print "<td>" . pacsone_gettext("N/A") . "</td>";
				}
			}
			else
				print "<td>" . pacsone_gettext("N/A") . "</td>";
		}
		print "</tr>";
	}
	print "</table>\n";
	if ($checkbox) {
   		print "<p><table width=20% border=0 cellpadding=5>\n";
        $check = pacsone_gettext("Check All");
        $uncheck = pacsone_gettext("Uncheck All");
    	print "<td><input type=button value='$check' onClick='this.value=checkAll(this.form,\"entry\", \"$check\", \"$uncheck\")'</input></td>\n";
    	print "<td><input type=submit value='";
        print pacsone_gettext("Move");
        print "' title='";
        print pacsone_gettext("Move Selected Studies");
        print "'></input></td>\n";
   		print "</table>\n";
	}
    print "</form>\n";
}

function displayRemoteSeries($aetitle, &$identifier, &$matches)
{
    global $BGCOLOR;
    global $MYFONT;
    global $dbcon;
	$count = count($matches);
	// check user privileges
	$username = $dbcon->username;
	$moveAccess = $dbcon->hasaccess("move", $username);
    if ($count > 1) {
	    printf(pacsone_gettext("Remote AE <b>%s</b> returned %d matches:<P>"), $aetitle, $count);
    } else {
	    printf(pacsone_gettext("Remote AE <b>%s</b> returned %d match:<P>"), $aetitle, $count);
    }
    print "<table width=100% border=0 cellpadding=5>\n";
    print "<form method='POST' action='moveForm.php'>\n";
	print "<input type='hidden' name='source' value='$aetitle'></input>";
	$attrs = $identifier->getDisplayAttrs();
    print "<tr class=listhead bgcolor=$BGCOLOR>\n";
	$checkbox = 0;
	if ($moveAccess && $count) {
		$checkbox = 1;
		print "<td></td>";
	}
	foreach ($attrs as $attr) {
		$name = $identifier->getAttributeName($attr);
		print "<td>$name</td>";
	}
    print "</tr>\n";
	foreach ($matches as $match) {
        if (!count($match->attrs))
            continue;
		print "<tr>";
		$level = $match->getQueryLevel();
		print "<input type='hidden' name='level[]' value=$level>";
		if ($checkbox) {
			$uid = $match->getSeriesUid();
            $xid = urlencode($uid);
			print "<td><input type='checkbox' name='entry[]' value='$xid'></input></td>";
            // pass along the Patient ID, Study UID of the returned series
            if ($match->hasKey(0x00100020)) {
                $value = http_build_query(array($uid => $match->getPatientId()));
                print "<input type='hidden' name='patientids[]' value=\"$value\">";
            }
            if ($match->hasKey(0x0020000d)) {
                $value = http_build_query(array($uid => $match->getStudyUid()));
                print "<input type='hidden' name='studyuids[]' value=\"$value\">";
            }
		}
		foreach ($attrs as $key) {
			if ($match->hasKey($key)) {
				$value = $match->attrs[$key];
				if ($key == 0x0020000e) {
                    $patientid = urlencode($match->getPatientId());
                    $studyId = $match->getStudyUid();
					$href = "remoteImage.php?aetitle=$aetitle&patientid=$patientid&studyuid=$studyId&seriesuid=$value";
					print "<td><a href=$href>$value</a></td>";
				} else {
					$value = trim($value);
					if (strlen($value))
						print "<td>$value</td>";
					else
						print "<td>" . pacsone_gettext("N/A") . "</td>";
				}
			}
			else
				print "<td>" . pacsone_gettext("N/A") . "</td>";
		}
		print "</tr>";
	}
	print "</table>\n";
	if ($checkbox) {
   		print "<p><table width=20% border=0 cellpadding=5>\n";
        $check = pacsone_gettext("Check All");
        $uncheck = pacsone_gettext("Uncheck All");
    	print "<td><input type=button value='$check' onClick='this.value=checkAll(this.form,\"entry\", \"$check\", \"$uncheck\")'</input></td>\n";
    	print "<td><input type=submit value='";
        print pacsone_gettext("Move");
        print "' title='";
        print pacsone_gettext("Move Selected Series");
        print "'></input></td>\n";
   		print "</table>\n";
	}
    print "</form>\n";
}

function displayRemoteImage($aetitle, &$identifier, &$matches)
{
    global $BGCOLOR;
    global $MYFONT;
    global $dbcon;
	$count = count($matches);
	// check user privileges
	$username = $dbcon->username;
	$moveAccess = $dbcon->hasaccess("move", $username);
    if ($count > 1) {
	    printf(pacsone_gettext("Remote AE <b>%s</b> returned %d matches:<P>"), $aetitle, $count);
    } else {
	    printf(pacsone_gettext("Remote AE <b>%s</b> returned %d match:<P>"), $aetitle, $count);
    }
    print "<table width=100% border=0 cellpadding=5>\n";
    print "<form method='POST' action='moveForm.php'>\n";
	print "<input type='hidden' name='source' value='$aetitle'></input>";
	$attrs = $identifier->getDisplayAttrs();
    print "<tr class=listhead bgcolor=$BGCOLOR>\n";
	$checkbox = 0;
	if ($moveAccess && $count) {
		$checkbox = 1;
		print "<td></td>";
	}
	foreach ($attrs as $attr) {
		$name = $identifier->getAttributeName($attr);
		print "<td>$name</td>";
	}
    print "</tr>\n";
	foreach ($matches as $match) {
        if (!count($match->attrs))
            continue;
		print "<tr>";
		$level = $match->getQueryLevel();
		print "<input type='hidden' name='level[]' value=$level>";
		if ($checkbox) {
			$uid = $match->getSopInstanceUid();
            $xid = urlencode($uid);
			print "<td><input type='checkbox' name='entry[]' value='$xid'></input></td>";
            // pass along the Patient ID, Study UID and Series UID of the returned image
            if ($match->hasKey(0x00100020)) {
                $value = http_build_query(array($uid => $match->getPatientId()));
                print "<input type='hidden' name='patientids[]' value=\"$value\">";
            }
            if ($match->hasKey(0x0020000d)) {
                $value = http_build_query(array($uid => $match->getStudyUid()));
                print "<input type='hidden' name='studyuids[]' value=\"$value\">";
            }
            if ($match->hasKey(0x0020000e)) {
                $value = http_build_query(array($uid => $match->getSeriesUid()));
                print "<input type='hidden' name='seriesuids[]' value=\"$value\">";
            }
		}
		foreach ($attrs as $key) {
			if ($match->hasKey($key)) {
				$value = $match->attrs[$key];
				if ($key == 0x00080018) {
                    $patientid = urlencode($match->getPatientId());
                    $studyId = $match->getStudyUid();
                    $seriesId = $match->getSeriesUid();
					$href = "remoteImage.php?aetitle=$aetitle&patientid=$patientid&studyuid=$studyId&seriesuid=$seriesId&uid=$value";
					print "<td><a href=$href>$value</a></td>";
				} else {
					$value = trim($value);
					if (strlen($value))
						print "<td>$value</td>";
					else
						print "<td>" . pacsone_gettext("N/A") . "</td>";
				}
			}
			else
				print "<td>" . pacsone_gettext("N/A") . "</td>";
		}
		print "</tr>";
	}
	print "</table>\n";
	if ($checkbox) {
   		print "<p><table width=20% border=0 cellpadding=5>\n";
    	print "<tr>\n";
        $check = pacsone_gettext("Check All");
        $uncheck = pacsone_gettext("Uncheck All");
    	print "<td><input type=button value='$check' onClick='this.value=checkAll(this.form,\"entry\", \"$check\", \"$uncheck\")'</input></td>\n";
    	print "<td><input type=submit value='";
        print pacsone_gettext("Move");
        print "' title='";
        print pacsone_gettext("Move Selected Images");
        print "'></input></td>\n";
   		print "</table>\n";
	}
    print "</tr></form>\n";
}

function displayRemoteImageDetails($aetitle, &$identifier, &$matches)
{
    global $BGCOLOR;
    global $MYFONT;
    global $dbcon;
	$count = count($matches);
	// check user privileges
	$username = $dbcon->username;
	$moveAccess = $dbcon->hasaccess("move", $username);
    if ($count > 1) {
	    printf(pacsone_gettext("Remote AE <b>%s</b> returned %d matches:<P>"), $aetitle, $count);
    } else {
	    printf(pacsone_gettext("Remote AE <b>%s</b> returned %d match:<P>"), $aetitle, $count);
    }
    print "<table width=100% border=0 cellpadding=5>\n";
    print "<form method='POST' action='moveForm.php'>\n";
	print "<input type='hidden' name='source' value='$aetitle'></input>";
	$attrs = $identifier->getDisplayAttrs();
    print "<tr class=listhead bgcolor=$BGCOLOR>\n";
	$checkbox = 0;
	if ($moveAccess && $count) {
		$checkbox = 1;
		print "<td></td>";
	}
	foreach ($attrs as $attr) {
		$name = $identifier->getAttributeName($attr);
		print "<td>$name</td>";
	}
    print "</tr>\n";
	foreach ($matches as $match) {
        if (!count($match->attrs))
            continue;
		print "<tr>";
		$level = $match->getQueryLevel();
		print "<input type='hidden' name='level[]' value=$level>";
		if ($checkbox) {
			$uid = $match->getSopInstanceUid();
            $xid = urlencode($uid);
			print "<td><input type='checkbox' name='entry[]' value='$xid'></input></td>";
            // pass along the Patient ID, Study UID and Series UID of the returned image
            if ($match->hasKey(0x00100020)) {
                $value = http_build_query(array($uid => $match->getPatientId()));
                print "<input type='hidden' name='patientids[]' value=\"$value\">";
            }
            if ($match->hasKey(0x0020000d)) {
                $value = http_build_query(array($uid => $match->getStudyUid()));
                print "<input type='hidden' name='studyuids[]' value=\"$value\">";
            }
            if ($match->hasKey(0x0020000e)) {
                $value = http_build_query(array($uid => $match->getSeriesUid()));
                print "<input type='hidden' name='seriesuids[]' value=\"$value\">";
            }
		}
		foreach ($attrs as $key) {
			if ($match->hasKey($key)) {
				$value = $match->attrs[$key];
				$value = trim($value);
				if (strlen($value))
					print "<td>$value</td>";
				else
					print "<td>" . pacsone_gettext("N/A") . "</td>";
			}
			else
				print "<td>" . pacsone_gettext("N/A") . "</td>";
		}
		print "</tr>";
	}
	print "</table>\n";
	if ($checkbox) {
   		print "<p><table width=20% border=0 cellpadding=5>\n";
    	print "<tr>\n";
        $check = pacsone_gettext("Check All");
        $uncheck = pacsone_gettext("Uncheck All");
    	print "<td><input type=button value='$check' onClick='this.value=checkAll(this.form,\"entry\", \"$check\", \"$uncheck\")'</input></td>\n";
    	print "<td><input type=submit value='";
        print pacsone_gettext("Move");
        print "' title='";
        print pacsone_gettext("Move Selected Images");
        print "'></input></td>\n";
   		print "</table>\n";
	}
    print "</tr></form>\n";
}

function displayUsers($result, $preface, $ldap = 0)
{
    include_once 'toggleRowColor.js';
    global $dbcon;
    global $USER_PRIVILEGE_TBL;
    print "<table width=100% cellpadding=0 cellspacing=0 border=0>\n";
    print "<tr><td>\n";
    print "<br>$preface\n";
    print "</td></tr>\n";
    print "</table>\n";
    $records = $result->rowCount();
	$failed = 0;
    $users = array();
    while ($row = $result->fetch(PDO::FETCH_ASSOC))
        $users[] = $row;
	// translate privilege values into strings
	$privTbl = array( 0 => "Disabled", 1 => "Enabled" );
    // display all columns
    global $BGCOLOR;
    global $MYFONT;
    print "<table width=100% border=0 cellpadding=3 class='mouseover optionrow'>\n";
    print "<form method='POST' action='modifyUser.php'>\n";
    print "<input type='hidden' name='actionvalue'>\n";
    if ($ldap)
        print "<input type='hidden' name='ldap' value=1>\n";
    print "<tr class=listhead bgcolor=$BGCOLOR>\n";
	if ($records) {
    	print "\t<td></td>\n";
        foreach ($USER_PRIVILEGE_TBL as $column => $descr) {
            print "\t<td><b>$descr</b></td>\n";
        }
        print "\t<td><b>" . pacsone_gettext("Edit") . "</b></td>\n";
    }
    print "</tr>\n";
    $count = 0;
    foreach ($users as $row) {
		$user = $row["username"];
        $style = ($count++ & 0x1)? "oddrows" : "evenrows";
        print "<tr class='$style'>\n";
		if ($records) {
			print "\t<td align=center width='1%'>\n";
			print "\t\t<input type='checkbox' name='entry[]' value='$user'>";
			print "</td>\n";
		}
        foreach ($USER_PRIVILEGE_TBL as $key => $descr) {
            $value = $row[$key];
            if (!strcasecmp($key, "usergroup")) {
                unset($value);
                $query = "select username,lastname from privilege where username in (select groupname from groupmember where username=?)";
                $bindList = array($user);
                $groups = $dbcon->preparedStmt($query, $bindList);
                if ($groups && $groups->rowCount()) {
                    $value = "<ul>";
                    while ($g = $groups->fetch(PDO::FETCH_NUM)) {
                        $group = $g[0];
                        $descr = $g[1];
                        $value .= "<li>$group ($descr)</li>";
                    }
                    $value .= "</ul>";
                }
            }
            if (isset($value)) {
				if (isset($privTbl[$value])) {
					$enabled = $privTbl[$value];
                    $value = "<img src=\"" . (($value)? "enabled.gif" : "disabled.gif");
                    $value .= "\" alt=\"$enabled\">";
                }
                $align = (strstr($value, "img src"))? "center" : "left";
                print "\t<td align=$align>";
                if (strcasecmp($key, "Email"))
                    print "$MYFONT$value</font>";
                else
                    print "<a href=\"mailto:$value\">$value</a>";
                print "</td>\n";
			}
            else
                print "\t<td>$MYFONT" . pacsone_gettext("N/A") . "</font></td>\n";
        }
        $encoded = urlencode($user);
		print "\t<td>$MYFONT<a href='modifyUser.php?user=$encoded&ldap=$ldap'>";
        print pacsone_gettext("Edit");
        print "</a></font></td>\n";
        print "</tr>\n";
    }
    print "</table>\n";
    print "<p><table width=20% border=0 cellpadding=5>\n";
    print "<tr>\n";
	if ($records) {
        $check = pacsone_gettext("Check All");
        $uncheck = pacsone_gettext("Uncheck All");
    	print "<td><input type=button value='$check' onClick='this.value=checkAll(this.form,\"entry\", \"$check\", \"$uncheck\")'></td>\n";
    	print "<td><input type=submit value='";
        print pacsone_gettext("Delete");
        print "' name='action' title='";
        print pacsone_gettext("Delete Selected Users");
        print "' onclick='switchText(this.form,\"actionvalue\",\"Delete\");return confirm(\"";
        print pacsone_gettext("Are you sure?");
        print "\");'></td>\n";
	}
    if (!$ldap) {
        print "<td><input type=submit value='";
        print pacsone_gettext("Add");
        print "' name='action' title='";
        print pacsone_gettext("Add New User");
        print "' onclick='switchText(this.form,\"actionvalue\",\"Add\")'></td>\n";
    }
    print "</tr>\n";
    print "</table>\n";
	print "</form>\n";
}

function displayExistingUsers($users, $preface, $external)
{
    print "<table width=50% cellpadding=0 cellspacing=0 border=0>\n";
    print "<tr><td>\n";
    print "<br>$preface\n";
    print "</td></tr>\n";
    print "<form method='POST' action='upgradeUser.php'>\n";
    foreach ($users as $user) {
		print "<tr>\n";
		print "<td><input type='checkbox' name='entry[]' value='$user'> </input>";
		print "$user</td>\n";
		print "</tr>\n";
    }
    print "</table>\n";
    print "<p><table width=20% border=0 cellpadding=5>\n";
    print "<tr>\n";
    $check = pacsone_gettext("Check All");
    $uncheck = pacsone_gettext("Uncheck All");
    print "<td><input type=button value='$check' onClick='this.value=checkAll(this.form,\"entry\", \"$check\", \"$uncheck\")'></td>\n";
    print "<td><input type=submit value='";
    print pacsone_gettext("Upgrade");
    print "' name='action' title='";
    print pacsone_gettext("Upgrade Selected Users");
    print "'></td>\n";
    print "<td><input type=hidden value=$external name='external'></td>\n";
    print "</tr>\n";
    print "</table>\n";
	print "</form>\n";
    print "</table>\n";
}

function displayRouteEntry($result, $preface, $mpps = false)
{
    include_once 'toggleRowColor.js';
    global $ROUTE_DATE_KEY_TBL;
    global $ROUTE_KEY_TBL;
    global $ROUTE_MPPS_TBL;
    global $SCHEDULE_TBL;
    global $dbcon;
    print "<p><table width=100% cellpadding=0 cellspacing=0 border=0>\n";
    print "<tr><td>\n";
    print "<br>$preface\n";
    print "</td></tr>\n";
    $records = $result->rowCount();
	// check if user has write access to table
	$access = 0;
	$username = $dbcon->username;
	$queryAccess = $dbcon->hasaccess("query", $username);
	if ($dbcon->hasaccess("modifydata", $username)) {
    	$access = 1;
        print "<form method='POST' action='modifyRoute.php'>\n";
        print "<input type='hidden' name='actionvalue'>\n";
        if ($mpps)
            print "<input type='hidden' name='mpps' value=1>\n";
	}
    global $BGCOLOR;
    global $MYFONT;
    global $WEEKDAY_MASK;
    print "<tr><td>\n";
    print "<table width=100% border=0 cellpadding=5 class='mouseover optionrow'>\n";
    print "<tr class=listhead bgcolor=$BGCOLOR>\n";
	if ($access && $records) {
        print "\t<td></td>\n";
	}
    // display the following columns: column name <=> database field
    $columns = array(
        pacsone_gettext("Source AE")                => "source",
        pacsone_gettext("Key Attribute")            => "keytag",
        pacsone_gettext("Match Pattern")            => "pattern",
        pacsone_gettext("Destination AE")         	=> "destination",
        pacsone_gettext("Hourly Schedule")          => "schedule",
        pacsone_gettext("Weekday Schedule")         => "weekday",
        pacsone_gettext("Priority")                 => "priority",
        pacsone_gettext("More Options")             => "fetchmore",
    );
    if (!$mpps)
        $columns[pacsone_gettext("Purge After Route")] = "autopurge";
    foreach (array_keys($columns) as $key) {
        print "\t<td><b>$key</b></td>\n";
    }
    print "\t<td><b>";
    print pacsone_gettext("Edit");
    print "</b></td>\n";
    $control = $access? pacsone_gettext("Control") : pacsone_gettext("Enabled");
    print "\t<td><b>$control</b></td>\n";
    print "</tr>\n";
    $count = 0;
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $style = ($count++ & 0x1)? "oddrows" : "evenrows";
        print "<tr class='$style'>\n";
        $source = $row['source'];
        $keytag = $row['keytag'];
        $destination = $row['destination'];
        $schedule = $row["schedule"];
        $window = $row["schedwindow"];
        $enabled = $row['enabled'];
        $pattern = isset($row['pattern'])? $row['pattern'] : "";
        $weekday = $row["weekday"];
		if ($access) {
			print "\t<td align=center width='1%'>\n";
			print "\t\t<input type='checkbox' name='entry[]' value='$source||$keytag||$destination||$window||$pattern||$schedule||$weekday'</td>\n";
		}
        foreach ($columns as $key => $field) {
            $value = $row[ $field ];
            if (isset($value)) {
                if (!strcasecmp($field, "Source") && ($value[0] == '_'))
                    $value = pacsone_gettext("Any");
                if (!strcasecmp($field, "Keytag")) {
                    if ($value == 0)
                        $value = pacsone_gettext("N/A");
                    else if ($value == 0xFFFFFFFF)
                        $value = pacsone_gettext("Advanced Logical Expressions");
                    else {
                        $table = $mpps? $ROUTE_MPPS_TBL : $ROUTE_KEY_TBL;
                        $value = array_search($value, $table);
                    }
                } else if (!strcasecmp($field, "Schedule")) {
                    if ($window) {
                        $from = ($window & 0xFF00) >> 8;
                        $to = ($window & 0x00FF);
                        if ($value == -1)
                            $value = sprintf(pacsone_gettext("Between %s and %s"), $SCHEDULE_TBL[$from], $SCHEDULE_TBL[$to]);
                        else if ($value == -2)
                            $value = sprintf(pacsone_gettext("Delayed until local time is from %s to %s"), $SCHEDULE_TBL[$from], $SCHEDULE_TBL[$to]);
                        else
                            $value = sprintf(pacsone_gettext("Immediately if local time is from %s to %s"), $SCHEDULE_TBL[$from], $SCHEDULE_TBL[$to]);
                    } else if (isset($SCHEDULE_TBL[$value])) {
                        $value = $SCHEDULE_TBL[$value];
                    } else {
                        $value = pacsone_gettext("Invalid schedule");
                    }
                } else if (!strcasecmp($field, "Weekday")) {
                    if ($value == 0x7F)
                        $value = pacsone_gettext("Any Day");
                    else {
                        $days = "";
                        foreach ($WEEKDAY_MASK as $bit => $wday) {
                            if ($bit & $value)
                                $days .= $wday . "<br>";
                        }
                        $value = strlen($days)? $days : pacsone_gettext("N/A");
                    }
                } else if (!strcasecmp($field, "AutoPurge"))
                    $value = ($value)? pacsone_gettext("Yes") : pacsone_gettext("No");
                else if (!strcasecmp($field, "fetchmore")) {
                    if (!$mpps) {
                        if ($value) {
                            $format = ($value < 0)? pacsone_gettext("Forward Existing %d Oldest Studies to Destination AE") : pacsone_gettext("Forward Existing %d Latest Studies to Destination AE");
                            $value = sprintf($format, abs($value));
                        } else
                            $value = "";
                        $data = $row['delayedstudy'];
                        if ($data) {
                            if (strlen($value))
                                $value .= "<br>";
                            if ($data < 1.0)
                                $wait = sprintf("%d %s", (int)($data * 60), pacsone_gettext("seconds"));
                            else
                                $wait = sprintf("%d %s", (int)$data, pacsone_gettext("minutes"));
                            $value .= sprintf(pacsone_gettext("Wait %s for all instances of a study to be received and forward the entire study"), $wait);
                        } else {
                            $data = $row['delayedseries'];
                            if ($data) {
                                if (strlen($value))
                                    $value .= "<br>";
                                if ($data < 1.0)
                                    $wait = sprintf("%d %s", (int)($data * 60), pacsone_gettext("seconds"));
                                else
                                    $wait = sprintf("%d %s", (int)$data, pacsone_gettext("minutes"));
                                $value .= sprintf(pacsone_gettext("Wait %s for all instances of a series to be received and forward the entire series"), $wait);
                            }
                        }
                    }
                    $data = $row['sendingaet'];
                    if ($data) {
                        if (strlen($value))
                            $value .= "<br>";
                        if (strcasecmp($data, "\$SOURCE\$") == 0)
                            $value .= sprintf(pacsone_gettext("Use Original/Source AE Title when forwarding to destination AE"), $data);
                        else
                            $value .= sprintf(pacsone_gettext("Use this AE Title: <u>%s</u> when forwarding to destination AE"), $data);
                    }
                } else if (!strcasecmp($field, "destination") && strlen($row['destfolder'])) {
                    $value = $row['destfolder'];
                } else if (!strcasecmp($field, "pattern") && $dbcon->isEuropeanDateFormat()) {
                    if (in_array($keytag, $ROUTE_DATE_KEY_TBL))
                        $value = reverseEmbedDate($value);
                    else if ($keytag == 0xFFFFFFFF)
                        $value = reverseLogicalExpDate($value);
                }
                if (!strlen($value))
                    $value = pacsone_gettext("N/A");
                print "\t<td>$MYFONT$value</font></td>\n";
            }
            else
                print "\t<td>$MYFONT" . pacsone_gettext("N/A") . "</font></td>\n";
        }
		if ($access) {
            $append = $mpps? "&mpps=1" : "";
            $pattern = urlencode($pattern);
			print "\t<td>$MYFONT<a href='modifyRoute.php?source=$source&destination=$destination&keytag=$keytag&window=$window&pattern=$pattern&weekday=$weekday$append'>";
            print pacsone_gettext("Edit");
            print "</a></font></td>\n";
            $toggle = ($enabled)? pacsone_gettext("Disable") : pacsone_gettext("Enable");
            $enabled = 1 - $enabled;
			print "\t<td>$MYFONT<a href='modifyRoute.php?source=$source&destination=$destination&keytag=$keytag&window=$window&enabled=$enabled&pattern=$pattern&weekday=$weekday$append'>$toggle</a></font></td>\n";
		} else {
			print "\t<td>$MYFONT" . pacsone_gettext("Edit") . "</font></td>\n";
            $toggle = ($enabled)? pacsone_gettext("Yes") : pacsone_gettext("No");
			print "\t<td>$MYFONT" . "$toggle</font></td>\n";
        }
        print "</tr>\n";
    }
    print "</table>\n";
    print "</td></tr>\n";
    if ($access) {
        print "<tr><td>\n";
    	print "<p><table width=20% border=0 cellpadding=5>\n";
        print "<tr>\n";
        if ($records) {
            $check = pacsone_gettext("Check All");
            $uncheck = pacsone_gettext("Uncheck All");
            print "<td><input type=button value='$check' onClick='this.value=checkAll(this.form,\"entry\", \"$check\", \"$uncheck\")'></td>\n";
        }
        print "<td><input type=submit value='";
        print pacsone_gettext("Add");
        print "' name='action' title='";
        print pacsone_gettext("Add New Routing Rule");
        print "' onclick='switchText(this.form,\"actionvalue\",\"Add\")'></td>\n";
        if ($records) {
            print "<td><input type=submit value='";
            print pacsone_gettext("Delete");
            print "' name='action' title='";
            print pacsone_gettext("Delete Checked Routing Rules");
            print "' onclick='switchText(this.form,\"actionvalue\",\"Delete\");return confirm(\"";
            print pacsone_gettext("Are you sure?");
            print "\");'></td>\n";
            print "<td><input type=submit value='";
            print pacsone_gettext("Enable All");
            print "' name='action' title='";
            print pacsone_gettext("Enable All Routing Rules");
            print "' onclick='switchText(this.form,\"actionvalue\",\"Enable All\")'></td>\n";
            print "<td><input type=submit value='";
            print pacsone_gettext("Disable All");
            print "' name='action' title='";
            print pacsone_gettext("Disable All Routing Rules");
            print "' onclick='switchText(this.form,\"actionvalue\",\"Disable All\")'></td>\n";
        }
        print "</tr>\n";
    	print "</table>\n";
        print "</td></tr>\n";
	    print "</form>\n";
    }
    print "</table>\n";
}

function displayRemoteWorklist($aetitle, &$identifier, &$matches, $imported)
{
    global $BGCOLOR;
    global $MYFONT;
    global $CUSTOMIZE_PATIENT;
	$count = count($matches);
    if ($count > 1) {
	    printf(pacsone_gettext("<br>Remote Modality Worklist SCP <b>%s</b> returned <b>%d</b> worklist items:"), $aetitle, $count);
    } else {
	    printf(pacsone_gettext("<br>Remote Modality Worklist SCP <b>%s</b> returned <b>%d</b> worklist item:"), $aetitle, $count);
    }
    print "<table width=100% border=1 cellpadding=1>\n";
    // display the following tabed pages
    $tabs = array (
        sprintf(pacsone_gettext("%s Identification"), $CUSTOMIZE_PATIENT)    => array (
                                                        0x00100010 => 0,
                                                        0x00100020 => 0),
        sprintf(pacsone_gettext("%s Demographic"), $CUSTOMIZE_PATIENT)       => array (
                                                        0x00100030 => 0,
                                                        0x00100040 => 0,
                                                        0x00101010 => 0,
                                                        0x00101020 => 0,
                                                        0x00101030 => 0,
                                                        0x001021B0 => 0,),
        pacsone_gettext("Scheduled Procedure Step")              => array (
                                                        0x00321070 => 0x00400100,
                                                        0x00400001 => 0x00400100,
                                                        0x00400002 => 0x00400100,
                                                        0x00400003 => 0x00400100,
                                                        0x00080060 => 0x00400100,
                                                        0x00400006 => 0x00400100,
                                                        0x00400007 => 0x00400100,
                                                        0x00400009 => 0x00400100,
                                                        0x00400010 => 0x00400100,
                                                        0x00400011 => 0x00400100,
                                                        0x00400012 => 0x00400100,
                                                        0x00400020 => 0x00400100,),
        pacsone_gettext("Scheduled Protocol Code Sequence")      => array (
                                                        0x00080100 => 0x00400008,
                                                        0x00080102 => 0x00400008,
                                                        0x00080103 => 0x00400008,
                                                        0x00080104 => 0x00400008),
        pacsone_gettext("Requested Procedure")                   => array (
                                                        0x0020000d => 0,
                                                        0x00321060 => 0,
                                                        0x00401001 => 0,
                                                        0x00401003 => 0),
        pacsone_gettext("Requested Procedure Code Sequence")     => array (
                                                        0x00080100 => 0x00321064,
                                                        0x00080102 => 0x00321064,
                                                        0x00080103 => 0x00321064,
                                                        0x00080104 => 0x00321064),
        pacsone_gettext("Imaging Service Request")               => array (
                                                        0x00080050 => 0,
                                                        0x00080090 => 0,
                                                        0x00321032 => 0,
                                                        0x00081080 => 0,),
    );
    // check if there's any optional sequences
    $referencedStudy = false;
    $referencedPatient = false;
    foreach ($matches as $match) {
        if ($match->hasKey(0x00081110))
            $referencedStudy = true;
        if ($match->hasKey(0x00081120))
            $referencedPatient = true;
    }
    if ($referencedStudy)
        $tabs[pacsone_gettext("Referenced Study Sequence")] = array (
                                                   0x00081150 => 0x00081110,
                                                   0x00081155 => 0x00081110
                                                   );
    if ($referencedPatient)
        $tabs[pacsone_gettext("Referenced Patient Sequence")] = array (
                                                     0x00081150 => 0x00081120,
                                                     0x00081155 => 0x00081120
                                                     );
    // display the returned matches
    print "<tr class=listhead bgcolor=$BGCOLOR>\n";
	foreach ($tabs as $key => $tab) {
	    print "<td><b>$key</b></td>";
	}
    print "</tr>\n";
    foreach ($matches as $match) {
        print "<tr>\n";
	    foreach ($tabs as $key => $tab) {
		    print "<td><table width=100% border=1 cellpadding=0>\n";
		    foreach ($tab as $tabkey => $tabvalue) {
                print "<tr>";
                $name = $identifier->getAttributeName($tabkey);
                print "<td><b>$name</b></td>";
                $search = ($tabvalue)? $tabvalue : $tabkey;
			    if ($match->hasKey($search)) {
                    if ($tabvalue) {
                        $seq = $match->getItem($tabvalue);
                        $value = $seq? $seq->getAttr($tabkey) : "";
                    } else {
				        $value = $match->getAttr($tabkey);
                    }
				    $value = trim($value);
				    if (strlen($value))
					    print "<td>$value</td>";
				    else
					    print "<td>" . pacsone_gettext("N/A") . "</td>";
			    }
			    else
				    print "<td>" . pacsone_gettext("N/A") . "</td>";
                print "</tr>";
		    }
		    print "</table></td>";
	    }
        print "</tr>\n";
    }
	print "</table>\n";
    if ($imported > 1) {
	    printf(pacsone_gettext("<p><b>%d</b> out of <b>%d</b> worklist items have been imported into the <a href='worklist.php'>Modality Worklist Table</a>.<p>"), $imported, $count);
    } else {
	    printf(pacsone_gettext("<p><b>%d</b> out of <b>%d</b> worklist item has been imported into the <a href='worklist.php'>Modality Worklist Table</a>.<p>"), $imported, $count);
    }
}

function displayWorklist($preface, &$list, $url, $offset, $all)
{
    global $BGCOLOR;
    global $MYFONT;
    global $dbcon;
    global $CUSTOMIZE_PATIENT_ID;
    global $CUSTOMIZE_PATIENT_NAME;
    global $CUSTOMIZE_REFERRING_DOC;
    // check user privileges
    $checkbox = 0;
	$username = $dbcon->username;
	$modifyAccess = $dbcon->hasaccess("modifydata", $username);
    if ($modifyAccess && count($list)) {
        $checkbox = 1;
        print "<form method='POST' action='actionItem.php'>\n";
    }
    print "<br><table width=100% border=0 cellpadding=5 cellspacing=0>\n";
    global $BGCOLOR;
    print "<tr><td>";
    $rows = displayPageControl(pacsone_gettext("Worklist"), $list, $preface, $url, $offset, $all);
    print "</td></tr>";
    // table headers
    print "<tr><td><table width=100% border=0 cellpadding=5>\n";
    $columns = array (
        $CUSTOMIZE_PATIENT_NAME                         => array(true, "patientname"),
        $CUSTOMIZE_PATIENT_ID                           => array(true, "patientid"),
        pacsone_gettext("Accession Number")             => array(true, "accessionnum"),
        pacsone_gettext("Modality")                     => array(true, "modality"),
        pacsone_gettext("Date of Service")              => array(true, "startdate"),
        pacsone_gettext("Procedure Code")               => array(true, "value"),
        $CUSTOMIZE_REFERRING_DOC                        => array(false, "referringphysician"),
        pacsone_gettext("Scheduled Procedure Description")  => array(false, "description"),
        pacsone_gettext("Scheduled Station AE Title")   => array(true, "aetitle"),
    );
    // display the table header
    if (count($rows)) {
        print "<tr class=listhead>\n";
        if ($checkbox) {
            print "\t<td bgcolor=$BGCOLOR></td>\n";
        }
	    foreach ($columns as $key => $pair) {
            $sort = $pair[0];
            $column = $pair[1];
            if ($sort) {
                if (!strcasecmp($column, "startdate") || !strcasecmp($column, "aetitle"))
                    $column = "scheduledps." . $column;
                $column = urlencode($column);
                $link = urlReplace($url, "sort", $column);
                print "<td bgcolor=$BGCOLOR><b><a href=\"$link\">$key</a></b></td>\n";
            } else {
	            print "<td bgcolor=$BGCOLOR><b>$key</b></td>\n";
            }
	    }
        print "</tr>\n";
    }
    global $STUDY_COLORS;
    foreach ($rows as $row) {
        $color = "";
        $query = "select status from worklist where studyuid=?";
        $bindList = array($row[0]);
        $result = $dbcon->preparedStmt($query, $bindList);
        if ($result && $result->rowCount()) {
            $status = $result->fetchColumn();
            if (isset($STUDY_COLORS[$status]))
                $color = "bgcolor=" . $STUDY_COLORS[$status];
        }
        print "<tr $color>\n";
        $studyUid = $row[0];
        $uid = urlencode($row[0]);
        if ($checkbox) {
	        print "\t<td align=center width='1%'>\n";
	        print "\t\t<input type='checkbox' name='entry[]' value='$uid'></td>\n";
        }
	    foreach ($columns as $key => $pair) {
            $field = $pair[1];
            $value = "";
            if (isset($row[ $field ])) {
                $value = $row[ $field ];
                if (strcasecmp($field, "startdate") == 0)
                    $value = $dbcon->formatDate($value);
            }
            if (!strlen($value))
                $value = pacsone_gettext("N/A");
            if (strcasecmp($key, pacsone_gettext("Accession Number")) == 0) {
                if (strcasecmp($value, pacsone_gettext("N/A")) == 0)
                    $value = pacsone_gettext("Worklist Details");
	            print "<td><a href='worklist.php?uid=$uid'>$value</a></td>\n";
            } else if (!strcasecmp($field, "patientname") &&
                       $dbcon->entryExists("study", "uuid", $studyUid)) {
                $patientId = urlencode($row["patientid"]);
                $url = "series.php?patientId=$patientId&studyId=$studyUid";
	            print "<td><a href='$url'>$value</a></td>\n";
            } else {
	            print "<td>$value</td>\n";
            }
	    }
        print "</tr>\n";
    }
    if ($checkbox) {
    	print "<p><table width=20% border=0 cellspacing=0 cellpadding=5>\n";
        print "<tr>\n";
        $check = pacsone_gettext("Check All");
        $uncheck = pacsone_gettext("Uncheck All");
        print "<td><input type=button value='$check' onClick='this.value=checkAll(this.form,\"entry\", \"$check\", \"$uncheck\")'></td>\n";
		if ($modifyAccess) {
        	print "<td><input type=submit value='";
            print pacsone_gettext("Delete");
            print "' name='action' title='";
            print pacsone_gettext("Delete checked worklist items");
            print "' onclick='return confirm(\"";
            print pacsone_gettext("Are you sure?");
            print "\");'></td>\n";
        }
        print "<td><input type=hidden value='";
        print pacsone_gettext("worklist");
        print "' name='option'></td>\n";
        print "</tr>\n";
    	print "</table>\n";
	    print "</form>\n";
    }
	print "</table></td></tr>\n";
    print "</table>\n";
}

function displayWorklistItem($page, $row)
{
    global $BGCOLOR;
    global $MYFONT;
    global $dbcon;
    global $CUSTOMIZE_PATIENT_ID;
    global $CUSTOMIZE_PATIENT_NAME;
    global $CUSTOMIZE_PATIENT_SEX;
    global $CUSTOMIZE_PATIENT_AGE;
    global $CUSTOMIZE_PATIENT_SIZE;
    global $CUSTOMIZE_PATIENT_WEIGHT;
    global $CUSTOMIZE_PATIENT_DOB;
    global $CUSTOMIZE_PATIENT;
    $uid = $row["studyuid"];
    // check whether to bypass Series level
    $skipSeries = 0;
    $result = $dbcon->query("select skipseries from config");
    if ($result && ($config = $result->fetch(PDO::FETCH_NUM)))
        $skipSeries = $config[0];
    // display the patient information
    $columns = array (
        $CUSTOMIZE_PATIENT_NAME                         => "patientname",
        $CUSTOMIZE_PATIENT_ID                           => "patientid",
        $CUSTOMIZE_PATIENT_DOB                          => "birthdate",
        $CUSTOMIZE_PATIENT_SEX                          => "sex",
        pacsone_gettext("Additional Patient History")   => "patienthistory",
        pacsone_gettext("Pregnancy Status")             => "pregnancystat",
        pacsone_gettext("Last Menstrual Date")          => "lastmenstrual",
        pacsone_gettext("Institution Name")             => "institution",
        $CUSTOMIZE_PATIENT_AGE                          => "age",
        $CUSTOMIZE_PATIENT_SIZE                         => "size",
        $CUSTOMIZE_PATIENT_WEIGHT                       => "weight",
    );
    if ($dbcon->isVeterinary()) {
        $columns[pacsone_gettext("Patient Species Description")] = "speciesdescr";
        $columns[pacsone_gettext("Patient Breed Description")] = "breeddescr";
        $columns[pacsone_gettext("Responsible Person")] = "respperson";
        $columns[pacsone_gettext("Responsible Person Role")] = "resppersonrole";
    }
    print "<table width=100% cellpadding=0 cellspacing=0 border=0>\n";
    print "<tr><td><br><b><u>";
    printf(pacsone_gettext("%s Information:"), $CUSTOMIZE_PATIENT);
    print "</u></b><br></td></tr>\n";
    print "<tr><td>&nbsp;</td></tr>\n";
    print "<tr><td><table width=100% cellpadding=0 cellspacing=0 border=0>\n";
    print "<tr>\n";
    foreach ($columns as $name => $field)
        print "<td bgcolor=$BGCOLOR><b>$name:</b></td>\n";
    print "</tr>\n";
    print "<tr>\n";
    foreach ($columns as $name => $field) {
        $value = $row[ $field ];
        if (!strlen($value))
            $value = pacsone_gettext("N/A");
        else if (!strcasecmp($field, "birthdate") ||
                 !strcasecmp($field, "lastmenstrual"))
            $value = $dbcon->formatDate($value);
        if (!strcasecmp($field, "patientid") && $dbcon->entryExists("patient", "origid", $value)) {
            $url = "study.php?patientId=" . urlencode($value);
            print "<td><a href=\"$url\">$value</a></td>\n";
        } else {
            print "<td><a href='enterWorklist.php?uid=$uid'>$value</a></td>\n";
        }
    }
    print "</tr>\n";
    print "</table></td></tr>\n";
    global $CUSTOMIZE_REFERRING_DOC;
    global $CUSTOMIZE_REQUESTING_DOC;
    // display the study information
    $columns = array (
        pacsone_gettext("UID")                              => "studyuid",
        pacsone_gettext("Status")                           => "state",
        pacsone_gettext("Accession Number")                 => "accessionnum",
        pacsone_gettext("Admitting Diagnoses Description")  => "admittingdiagnoses",
        $CUSTOMIZE_REFERRING_DOC                            => "referringphysician",
        $CUSTOMIZE_REQUESTING_DOC                           => "requestingphysician",
    );
    print "<tr><td><br><b><u>";
    print pacsone_gettext("Study Information:");
    print "</u></b><br></td></tr>\n";
    print "<tr><td>&nbsp;</td></tr>\n";
    print "<tr><td><table width=100% cellpadding=0 cellspacing=0 border=0>\n";
    print "<tr>\n";
    foreach ($columns as $name => $field)
        print "<td bgcolor=$BGCOLOR><b>$name:</b></td>\n";
    print "</tr>\n";
    print "<tr>\n";
    // check if this study is stored locally in the database
    $result = $dbcon->query("SELECT patientid FROM study WHERE uuid='$uid'");
    $present = ($result->rowCount() == 1);
    $patientId = urlencode($result->fetchColumn());
    foreach ($columns as $name => $field) {
        $value = $row[ $field ];
        if (!strlen($value))
            $value = pacsone_gettext("N/A");
        if (!strcasecmp($name, pacsone_gettext("UID")) && $present) {
            $studyId = urlencode($value);
            $skip = ($skipSeries && $dbcon->hasSRseries($value))? false : $skipSeries;
            $url = $skip? "image.php" : "series.php";
            print "<td><a href='$url?patientId=$patientId&studyId=$studyId'>$value</a></td>\n";
        } else {
            print "<td><a href='enterWorklist.php?uid=$uid'>$value</a></td>\n";
        }
    }
    print "</tr>\n";
    print "</table></td></tr>\n";
    // display worklist information
    print "<tr><td><br><b><u>";
    print pacsone_gettext("Worklist Information:");
    print "</u></b><br></td></tr>\n";
    print "<tr><td>&nbsp;</td></tr>\n";
    print "<tr><td><table width=100% border=0 cellpadding=0 cellspacing=2>\n";
    $table = $page->table;
    if (!strcasecmp($table, "worklist")) {
        $tablerow = $row;
    } else {
        $result = $dbcon->query("SELECT * FROM $table WHERE studyuid='$uid'");
        $tablerow = $result->fetch(PDO::FETCH_ASSOC);
    }
    print "<tr>\n";
    foreach ($page->columns as $name => $field)
        print "<td style='background-color: $BGCOLOR;font-weight: bold'><b>$name</b></td>\n";
    print "</tr>\n";
    print "<tr>\n";
    foreach ($page->columns as $name => $field) {
        $value = isset($tablerow[ $field ])? $tablerow[ $field ] : "";
        $link = false;
        if (strlen($value) && !strcasecmp($field, "instanceuid")) {
            // check if referenced study exists in the local database
            if (strcasecmp($table, "referencedstudy") == 0) {
                $query = "SELECT patientid FROM study where uuid=?";
                $bindList = array($value);
                $result = $dbcon->preparedStmt($query, $bindList);
                $link = ($result->rowCount() == 1);
                if ($link) {
                    $patientId = urlencode($result->fetchColumn());
                    $studyId = urlencode($value);
                    $skip = ($skipSeries && $dbcon->hasSRseries($value))? false : $skipSeries;
                    $url = $skip? "image.php" : "series.php";
                    $url .="?patientId=$patientId&studyId=$studyId";
                }
            }
            // check if referenced patient exists in the local database
            else if (strcasecmp($table, "referencedpatient") == 0) {
                $query = "SELECT origid FROM patient where origid=?";
                $bindList = array($value);
                $result = $dbcon->preparedStmt($query, $bindList);
                $link = ($result->rowCount() == 1);
                if ($link) {
                    $patientId = urlencode($result->fetchColumn());
                    $url ="study.php?patientId=$patientId";
                }
            }
        }
        if (!strlen($value))
            $value = pacsone_gettext("N/A");
        else if (!strcasecmp($field, "startdate"))
            $value = $dbcon->formatDate($value);
        if ($link)
            print "<td><a href='$url'>$value</a></td>\n";
        else
            print "<td><a href='enterWorklist.php?uid=$uid'>$value</a></td>\n";
    }
    print "</tr>\n";
    print "</table></td></tr>\n";
    // end of tabed pages
	print "</table></td></tr>\n";
    // check user privileges
	$username = $dbcon->username;
	if ($dbcon->hasaccess("modifydata", $username)) {
        print "<tr><td>&nbsp;</td></tr>\n";
        print "<form method='POST' action='actionItem.php'>\n";
    	print "<tr><td><table width=20% border=0 cellspacing=0 cellpadding=5>\n";
        print "<tr>\n";
       	print "<td><input type=submit value='";
        print pacsone_gettext("Delete");
        print "' name='action' title='";
        print pacsone_gettext("Delete current worklist item");
        print "' onclick='return confirm(\"";
        print pacsone_gettext("Are you sure?");
        print "\");'></td>\n";
        print "<td><input type=hidden value='worklist' name='option'></td>\n";
	    print "<input type=hidden name='entry[]' value='$uid'></td>\n";
        print "</tr>\n";
    	print "</table></td></tr>\n";
	    print "</form>\n";
    }
    print "</table>\n";
}

function displayCoercion($result, $preface)
{
    global $dbcon;
    print "<table width=100% cellpadding=0 cellspacing=0 border=0>\n";
    print "<tr><td>\n";
    print "<br>$preface\n";
    print "</td></tr>\n";
    $records = $result->rowCount();
	// check if user has write access to table
	$access = 0;
	$username = $dbcon->username;
	$queryAccess = $dbcon->hasaccess("query", $username);
	if ($dbcon->hasaccess("modifydata", $username)) {
    	$access = 1;
        print "<form method='POST' action='modifyCoercion.php'>\n";
        print "<input type='hidden' name='actionvalue'>\n";
	}
    // display all columns
    global $BGCOLOR;
    global $MYFONT;
    print "<tr><td>\n";
    print "<table width=100% border=0 cellpadding=5>\n";
    print "<tr class=listhead bgcolor=$BGCOLOR>\n";
	if ($access && $records) {
        print "\t<td></td>\n";
	}
    // display the following columns: column name <=> database field
    $columns = array(
        pacsone_gettext("Source AE Title")          => "aetitle",
        pacsone_gettext("Key Attribute")            => "keytag",
        pacsone_gettext("Matching Pattern")         => "pattern",
        pacsone_gettext("Data Element To Coerce")   => "tag",
        pacsone_gettext("Coercion Syntax")          => "syntax",
        pacsone_gettext("Description")      		=> "description",
        pacsone_gettext("Order")                    => "sequence",
    );
	// data elements supported for coersion
    global $COERCION_TBL;
    global $ROUTE_KEY_TBL;
    foreach (array_keys($columns) as $key) {
        print "\t<td><b>$key</b></td>\n";
    }
    print "\t<td><b>";
    print pacsone_gettext("Edit");
    print "</b></td>\n";
    print "</tr>\n";
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        print "<tr>\n";
		if ($access) {
			print "\t<td align=center width='1%'>\n";
            $title = $row['aetitle'];
            $seq = $row['sequence'];
            $value = urlencode("$title $seq");
			print "\t\t<input type='checkbox' name='entry[]' value='$value'</td>\n";
		}
        foreach ($columns as $key => $field) {
            if (isset($row[$field])) {
            	$value = $row[$field];
				if (strcasecmp($field, "tag") == 0)
                	$value = sprintf("%s (0x%08x)", $COERCION_TBL[$value], $value);
                else if (strcasecmp($field, "keytag") == 0) {
                    if ($value)
                	    $value = array_search($value, $ROUTE_KEY_TBL);
                    else
                        $value = pacsone_gettext("N/A");
                } else if (!strlen($value))
                    $value = pacsone_gettext("N/A");
			}
            else
                $value = pacsone_gettext("N/A");
            print "\t<td>$MYFONT$value</font></td>\n";
        }
		if ($access) {
			print "\t<td>$MYFONT<a href='modifyCoercion.php?title=$title&seq=$seq'>";
            print pacsone_gettext("Edit");
            print "</a></font></td>\n";
        }
		else
			print "\t<td>$MYFONT" . pacsone_gettext("Edit") . "</font></td>\n";
        print "</tr>\n";
    }
    print "</table>\n";
    print "</td></tr>\n";
    if ($access) {
        print "<tr><td>\n";
    	print "<p><table width=20% border=0 cellpadding=5>\n";
        print "<tr>\n";
        if ($records) {
            $check = pacsone_gettext("Check All");
            $uncheck = pacsone_gettext("Uncheck All");
            print "<td><input type=button value='$check' onClick='this.value=checkAll(this.form,\"entry\", \"$check\", \"$uncheck\")'></td>\n";
            print "<td><input type=submit value='";
            print pacsone_gettext("Delete");
            print "' name='action' title='";
            print pacsone_gettext("Delete checked Data Element Coercion rules");
            print "' onclick='switchText(this.form,\"actionvalue\",\"Delete\");return confirm(\"";
            print pacsone_gettext("Are you sure?");
            print "\");'></td>\n";
        }
        print "<td><input type=submit value='";
        print pacsone_gettext("Add");
        print "' name='action' title='";
        print pacsone_gettext("Add new Data Element Coercion rule");
        print "' onclick='switchText(this.form,\"actionvalue\",\"Add\")'></td>\n";
        if (isset($seq))
            print "<input type=hidden name='sequence' value=$seq>\n";
        print "</tr>\n";
    	print "</table>\n";
	    print "</form>\n";
        print "</td></tr>\n";
    }
    print "</table><br>\n";
}

function displayStudiesForExport(&$selected, $preface, $url, $offset, $all, $zip, $viewer, $purge)
{
    include_once "utils.php";
    global $MYFONT;
    global $dbcon;
    global $CUSTOMIZE_PATIENT_ID;
    global $CUSTOMIZE_PATIENT_NAME;
    // display the following columns: column name <=> database field
    $columns = array(
        $CUSTOMIZE_PATIENT_ID                       => "patientid",
        $CUSTOMIZE_PATIENT_NAME                     => "fullname",
        pacsone_gettext("Study ID")                 => "id",
        pacsone_gettext("Study Date")               => "studydate",
        pacsone_gettext("Accession Number")         => "accessionnum",
        pacsone_gettext("Study Description")        => "description",
    );
    // links to different sorting methods
    $index = strpos($url, "sort=");
    if ($index) {
        $sort = trim(substr($url, $index+5));
    }
    // check if need to toggle sorting order
    if (isset($_SESSION['sortToggle'])) {
        $toggle = 1 - $_SESSION['sortToggle'];
        $link = urlReplace($url, "toggle", $toggle);
    } else {
        $link = $url;
    }
    $links = array(
        pacsone_gettext("Study ID")            => urlReplace($link, "sort", "cmp_studyid"),
        pacsone_gettext("Study Date")          => urlReplace($link, "sort", "cmp_studydate"),
        pacsone_gettext("Accession Number")    => urlReplace($link, "sort", "cmp_accession"),
    );
    // group studies by patient
    $studies = array();
    $result = $dbcon->query("SELECT origid,firstname,middlename,lastname FROM patient");
    while ($patient = $result->fetch(PDO::FETCH_NUM)) {
        $patientid = $patient[0];
        $patientname = $patient[1];
        if (isset($patient[2]) && strlen($patient[2]))
            $patientname .= " " . $patient[2];
        $patientname .= " " . $patient[3];
        // query study information
        $subq = "SELECT * FROM study WHERE patientid=? ORDER BY id";
        $subList = array($patientid);
        $studyResult = $dbcon->preparedStmt($subq, $subList);
        while ($studyRow = $studyResult->fetch(PDO::FETCH_ASSOC)) {
            $study = array();
            $study['UID'] = $studyRow['uuid'];
            $study[$CUSTOMIZE_PATIENT_NAME] = $patientname;
            foreach ($columns as $key => $field) {
                if (strcasecmp($key, $CUSTOMIZE_PATIENT_NAME) == 0)
                    continue;
                $study[$field] = $studyRow[$field];
            }
            // insert this study
            $studies[] = $study;
        }       
    }
    // sort the rows based on Study ID by default
    usort($studies, $sort);
    $rows = displayPageControl(pacsone_gettext("Studies"), $studies, $preface, $url, $offset, $all);
    $buttons = array(
        'Update'    => array(pacsone_gettext('Update'), pacsone_gettext('Update Selected Studies'), 1),
        'Export'    => array(pacsone_gettext('Export'), pacsone_gettext('Export Selected Studies'), 1),
    );
    $hidden = array(
        "zip"     => $zip,
        "viewer"  => $viewer,
        "purge"  => $purge,
        "sort"    => $sort);
    print "<form method='POST' action='export.php'>\n";
    displayButtons("study", $buttons, $hidden);
    print "<table width=100% border=0 cellpadding=5>\n";
    global $BGCOLOR;
    print "<tr class=listhead bgcolor=$BGCOLOR>\n";
    print "\t<td></td>\n";
    foreach (array_keys($columns) as $key) {
        if (isset($links[$key])) {
            $link = $links[$key];
            print "\t<td><b><a href=$link>$key</a></b></td>\n";
        } else {
            print "\t<td><b>$key</b></td>\n";
        }
    }
    print "</tr>\n";
    // display the sorted studies
    foreach ($rows as $study) {
        $uid = $study['UID'];
        print "<tr>\n";
        print "\t<td align=center width='1%'>\n";
        $checked = "";
        foreach ($selected as $entry) {
            if (strcasecmp($uid, $entry) == 0) {
                $checked = "CHECKED";
                break;
            }
        }
        print "\t\t<input type='checkbox' name='entry[]' value='$uid' $checked></td>\n";
        foreach ($columns as $key => $field) {
            $value = strcasecmp($key, $CUSTOMIZE_PATIENT_NAME)?
                $study[$field] : $study[$key];
            if (isset($value) && strlen($value)) {
                if (strcasecmp($field, "studydate") == 0)
                    $value = $dbcon->formatDate($value);
                print "\t<td>$MYFONT$value</font></td>\n";
            }
            else
                print "\t<td>$MYFONT" . pacsone_gettext("N/A") . "</font></td>\n";
        }
        print "</tr>\n";
    }
    print "</table>\n";
    displayButtons("study", $buttons, $hidden);
    displayPageControl(pacsone_gettext("Studies"), $studies, $preface, $url, $offset, $all);
    print "</form>\n";
}

function displayPrinterAttrs($aetitle, &$list) {
    global $ATTR_TBL;
    global $BGCOLOR;
    print "<table width=100% cellpadding=0 cellspacing=0 border=0>\n";
    print "<tr><td>\n";
    print "<br>";
    printf(pacsone_gettext("Attributes for Dicom printer: <b>%s</b>\n"), $aetitle);
    print "</td></tr>\n";
    print "<tr><td><br></td></tr>\n";
    print "<tr><td>\n";
    print "<table width=100% cellpadding=3 cellspacing=0 border=1>\n";
    print "<tr class=listhead bgcolor=$BGCOLOR>\n";
    $columns = array(pacsone_gettext("Property"), pacsone_gettext("Value"));
    foreach ($columns as $key) {
        print "<td><b>$key</b></td>\n";
    }
    print "</tr>\n";
    foreach ($list->attrs as $key => $attr) {
        $group = ($key >> 16);
        $element = ($key & 0xffff);
        if (isset($ATTR_TBL[$key]))
            $name = $ATTR_TBL[$key]->name;
        else
            $name = ($element == 0)? pacsone_gettext("Group Length") : pacsone_gettext("N/A");
        $key = sprintf("%04x,%04x", $group, $element);
	    if (is_a($attr, 'Sequence'))
            $value = pacsone_gettext("Sequence");
        else
            $value = trim($attr);
        print "<tr><td>$name</td>";
        if (empty($value))
            $value = "&nbsp;";
        print "<td>$value</td></tr>\n";
    }
    print "</table>\n";
    print "</td></tr>\n";
    print "</table>\n";
}

function displayExportItem(&$level, &$uids)
{
    if (strcasecmp($level, pacsone_gettext("Patient")) == 0)
        displayExportedPatients($uids);
    else if (strcasecmp($level, pacsone_gettext("Study")) == 0)
        displayExportedStudies($uids);
    else if (strcasecmp($level, pacsone_gettext("Series")) == 0)
        displayExportedSeries($uids);
    else if (strcasecmp($level, pacsone_gettext("Image")) == 0)
        displayExportedImages($uids);
    else {
        print "<font color=red>";
        printf(pacsone_gettext("Invalid Export Level: %s"), $level);
        print "</font>";
        exit();
    }
}

function displayExportedPatients(&$uids)
{
    global $MYFONT;
    global $dbcon;
    global $CUSTOMIZE_PATIENT_ID;
    global $CUSTOMIZE_PATIENT_NAME;
    global $CUSTOMIZE_PATIENT_SEX;
    global $CUSTOMIZE_PATIENT_DOB;
    global $CUSTOMIZE_PATIENT_AGE;
    $columns = array(
        $CUSTOMIZE_PATIENT_ID               => "origid",
        $CUSTOMIZE_PATIENT_NAME             => "",
        $CUSTOMIZE_PATIENT_DOB              => "birthdate",
        $CUSTOMIZE_PATIENT_SEX              => "sex",
        $CUSTOMIZE_PATIENT_AGE              => "age",
    );
    print "<table width=100% cellpadding=0 cellspacing=0 border=1>\n";
    global $BGCOLOR;
    print "<tr bgcolor=$BGCOLOR>\n";
    foreach (array_keys($columns) as $key) {
        print "\t<td><b>$key</b></td>\n";
    }
    print "</tr>\n";
    foreach ($uids as $uid) {
        $uid = urldecode($uid);
        $query = "SELECT * FROM patient WHERE origid=?";
        $bindList = array($uid);
        $result = $dbcon->preparedStmt($query, $bindList);
        if ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            print "<tr>\n";
            foreach ($columns as $key => $field) {
                if (strcasecmp($key, $CUSTOMIZE_PATIENT_NAME) == 0) {
                    $value = $row["firstname"] . " ";
                    if ($row["middlename"])
                        $value .= $row["middlename"] . " ";
                    $value .= $row["lastname"];
                } else {
                    $value = $row[$field];
                }
                if (!strlen($value))
                    $value = pacsone_gettext("N/A");
                else if (!strcasecmp($field, "birthdate"))
                    $value = $dbcon->formatDate($value);
                print "<td>$value</td>\n";
            }
            print "</tr>\n";
        }
    }
    print "</table>\n";
}

function displayExportedStudies(&$uids)
{
    global $MYFONT;
    global $dbcon;
    global $CUSTOMIZE_PATIENT_ID;
    global $CUSTOMIZE_PATIENT_NAME;
    $columns = array(
        pacsone_gettext("Study ID")             => "id",
        pacsone_gettext("Study Date")           => "studydate",
        pacsone_gettext("Accession Number")     => "accessionnum",
        pacsone_gettext("Study Description")    => "description",
    );
    print "<table width=100% cellpadding=0 cellspacing=0 border=1>\n";
    global $BGCOLOR;
    print "<tr bgcolor=$BGCOLOR>\n";
    print "\t<td><b>$CUSTOMIZE_PATIENT_ID</b></td>\n";
    print "\t<td><b>$CUSTOMIZE_PATIENT_NAME</b></td>\n";
    foreach (array_keys($columns) as $key) {
        print "\t<td><b>$key</b></td>\n";
    }
    print "</tr>\n";
    foreach ($uids as $uid) {
        $query = "SELECT * FROM study WHERE uuid=?";
        $bindList = array($uid);
        $result = $dbcon->preparedStmt($query, $bindList);
        if ($result && $study = $result->fetch(PDO::FETCH_ASSOC)) {
            print "<tr>\n";
            $patientId = $study["patientid"];
            print "<td>" . $patientId . "</td>\n";
            $subq = "SELECT * FROM patient WHERE origid=?";
            $subList = array($patientId);
            $result = $dbcon->preparedStmt($subq, $subList);
            $patient = $result->fetch(PDO::FETCH_ASSOC);
            $name = $patient["firstname"] . " ";
            if ($patient["middlename"])
                $name .= $patient["middlename"] . " ";
            $name .= $patient["lastname"];
            print "<td>$name</td>\n";
            foreach ($columns as $key => $field) {
                $value = $study[$field];
                if (!strlen($value))
                    $value = pacsone_gettext("N/A");
                else if (!strcasecmp($field, "studydate"))
                    $value = $dbcon->formatDate($value);
                print "<td>$value</td>\n";
            }
            print "</tr>\n";
        }
    }
    print "</table>\n";
}

function displayExportedSeries(&$uids)
{
    global $MYFONT;
    global $dbcon;
    global $CUSTOMIZE_PATIENT_ID;
    global $CUSTOMIZE_PATIENT_NAME;
    $columns = array(
        pacsone_gettext("Series Number")    => "seriesnumber",
        pacsone_gettext("Series Date")      => "seriesdate",
        pacsone_gettext("Modality")         => "modality",
        pacsone_gettext("Body Part")        => "bodypart",
        pacsone_gettext("Description")      => "description",
    );
    print "<table width=100% cellpadding=0 cellspacing=0 border=1>\n";
    global $BGCOLOR;
    print "<tr bgcolor=$BGCOLOR>\n";
    print "\t<td><b>$CUSTOMIZE_PATIENT_ID</b></td>\n";
    print "\t<td><b>$CUSTOMIZE_PATIENT_NAME</b></td>\n";
    print "\t<td><b>";
    print pacsone_gettext("Study ID");
    print "</b></td>\n";
    foreach (array_keys($columns) as $key) {
        print "\t<td><b>$key</b></td>\n";
    }
    print "</tr>\n";
    foreach ($uids as $uid) {
        $query = "SELECT * FROM series WHERE uuid=?";
        $bindList = array($uid);
        $result = $dbcon->preparedStmt($query, $bindList);
        if ($result && $series = $result->fetch(PDO::FETCH_ASSOC)) {
            print "<tr>\n";
            $studyUid = $series["studyuid"];
            $subq = "SELECT patientid,id FROM study WHERE uuid=?";
            $subList = array($studyUid);
            $result = $dbcon->preparedStmt($subq, $subList);
            $study = $result->fetch(PDO::FETCH_NUM);
            $patientId = $study[0];
            $studyId = $study[1];
            print "<td>" . $patientId . "</td>\n";
            $subq = "SELECT * FROM patient WHERE origid=?";
            $subList = array($patientId);
            $result = $dbcon->preparedStmt($subq, $subList);
            $patient = $result->fetch(PDO::FETCH_ASSOC);
            $name = $patient["firstname"] . " ";
            if ($patient["middlename"])
                $name .= $patient["middlename"] . " ";
            $name .= $patient["lastname"];
            print "<td>$name</td>\n";
            print "<td>$studyId</td>\n";
            foreach ($columns as $key => $field) {
                $value = $series[$field];
                if (!strlen($value))
                    $value = pacsone_gettext("N/A");
                else if (!strcasecmp($field, "seriesdate"))
                    $value = $dbcon->formatDate($value);
                print "<td>$value</td>\n";
            }
            print "</tr>\n";
        }
    }
    print "</table>\n";
}

function displayExportedImages(&$uids)
{
    global $MYFONT;
    global $dbcon;
    global $CUSTOMIZE_PATIENT_ID;
    global $CUSTOMIZE_PATIENT_NAME;
    $columns = array(
        pacsone_gettext("Instance Number")          => "instance",
    );
    print "<table width=100% cellpadding=0 cellspacing=0 border=1>\n";
    global $BGCOLOR;
    print "<tr bgcolor=$BGCOLOR>\n";
    print "\t<td><b>$CUSTOMIZE_PATIENT_ID</b></td>\n";
    print "\t<td><b>$CUSTOMIZE_PATIENT_NAME</b></td>\n";
    print "\t<td><b>";
    print pacsone_gettext("Study ID");
    print "</b></td>\n";
    print "\t<td><b>";
    print pacsone_gettext("Series Number");
    print "</b></td>\n";
    print "\t<td><b>";
    print pacsone_gettext("Series Date");
    print "</b></td>\n";
    foreach (array_keys($columns) as $key) {
        print "\t<td><b>$key</b></td>\n";
    }
    print "</tr>\n";
    foreach ($uids as $uid) {
        $bindList = array($uid);
        $result = $dbcon->preparedStmt("SELECT * FROM image WHERE uuid=?", $bindList);
        if ($instance = $result->fetch(PDO::FETCH_ASSOC)) {
            print "<tr>\n";
            $seriesUid = $instance["seriesuid"];
            $result = $dbcon->query("SELECT studyuid,seriesnumber,seriesdate FROM series WHERE uuid='$seriesUid'");
            $series = $result->fetch(PDO::FETCH_NUM);
            $studyUid = $series[0];
            $seriesNum = $series[1];
            $seriesDate = $series[2];
            if (!strlen($seriesDate))
                $seriesDate = pacsone_gettext("N/A");
            else
                $seriesDate = $dbcon->formatDate($seriesDate);
            $result = $dbcon->query("SELECT patientid,id FROM study WHERE uuid='$studyUid'");
            $study = $result->fetch(PDO::FETCH_NUM);
            $patientId = $study[0];
            $studyId = $study[1];
            print "<td>" . urlencode($patientId) . "</td>\n";
            $bindList = array($patientId);
            $result = $dbcon->preparedStmt("SELECT * FROM patient WHERE origid=?", $bindList);
            $patient = $result->fetch(PDO::FETCH_ASSOC);
            $name = $patient["firstname"] . " ";
            if ($patient["middlename"])
                $name .= $patient["middlename"] . " ";
            $name .= $patient["lastname"];
            print "<td>$name</td>\n";
            print "<td>$studyId</td>\n";
            print "<td>$seriesNum</td>\n";
            print "<td>$seriesDate</td>\n";
            foreach ($columns as $key => $field) {
                $value = $instance[$field];
                if (!strlen($value))
                    $value = pacsone_gettext("N/A");
                print "<td>$value</td>\n";
            }
            print "</tr>\n";
        }
    }
    print "</table>\n";
}

function displayJournal($list, $preface, $url, $offset, $all)
{
    global $MYFONT;
    global $dbcon;
    print "<br><table width=100% border=0 cellpadding=5 cellspacing=0>\n";
    global $BGCOLOR;
    print "<tr><td>";
    $rows = displayPageControl(pacsone_gettext("Events"), $list, $preface, $url, $offset, $all);
    print "</td></tr>";
    // display the current page
    print "<tr><td>";
    print "<table width=100% border=1 cellpadding=0 cellspacing=0>\n";
    // check if need to toggle sorting order
    if (isset($_SESSION['sortToggle'])) {
        $toggle = 1 - $_SESSION['sortToggle'];
        $link = urlReplace($url, "toggle", $toggle);
    } else {
        $link = $url;
    }
    // links to different sorting methods
    $links = array(
        pacsone_gettext("When")                    => urlReplace($link, "sort", "cmp_when"),
        pacsone_gettext("Username")                => urlReplace($link, "sort", "cmp_username"),
        pacsone_gettext("Level")                   => urlReplace($link, "sort", "cmp_what"),
    );
    // display the following columns: column name <=> database field
    $columns = array(
        pacsone_gettext("When")           => "timestamp",
        pacsone_gettext("Username")       => "username",
        pacsone_gettext("Operation")      => "did",
        pacsone_gettext("Level")          => "what",
        pacsone_gettext("UID")            => "uuid",
        pacsone_gettext("Details")        => "details",
    );
    print "<tr class=listhead bgcolor=$BGCOLOR>\n";
    foreach (array_keys($columns) as $key) {
        if (count($rows) && isset($links[$key])) {
            $link = $links[$key];
            print "\t<td><b><a href=$link>$key</a></b></td>\n";
        } else {
            print "\t<td><b>$key</b></td>\n";
        }
    }
    print "</tr>\n";
    foreach ($rows as $row) {
        print "<tr>\n";
        foreach ($columns as $key => $field) {
            $value = $row[$field];
            if (isset($value) && strlen($value)) {
                if (strcasecmp($field, "timestamp") == 0)
                    $value = $dbcon->formatDateTime($value);
                print "\t<td>$MYFONT$value</font></td>\n";
            }
            else
               print "\t<td>$MYFONT" . pacsone_gettext("N/A") . "</font></td>\n";
        }
        print "</tr>\n";
    }
    print "</table>";
    print "</td></tr></table>";
}

function displayStatReport($list, $preface, $url, $offset, $all, $type)
{
    global $MYFONT;
    global $dbcon;
    // check whether to bypass Series level
    $skipSeries = 0;
    $result = $dbcon->query("select skipseries from config");
    if ($result && ($row = $result->fetch(PDO::FETCH_NUM)))
        $skipSeries = $row[0];
    $rows = displayPageControl(pacsone_gettext("Studies"), $list, $preface, $url, $offset, $all);
    // check user privileges
    $checkbox = 0;
	$username = $dbcon->username;
	$modifyAccess = $dbcon->hasaccess("modifydata", $username);
	$forwardAccess = $dbcon->hasaccess("forward", $username);
    $printAccess = $dbcon->hasaccess("print", $username);
    if ($printAccess) {
        $printers = $dbcon->query("select printscp from applentity where printscp=1");
        if ($printers && ($printers->rowCount() == 0))
            $printAccess = 0;
    }
    $exportAccess = $dbcon->hasaccess("export", $username);
    if (($modifyAccess || $forwardAccess || $printAccess || $exportAccess) && sizeof($rows)) {
        $checkbox = 1;
        $buttons = array(
            'Forward'   => array(pacsone_gettext('Forward'), pacsone_gettext('Forward checked studies'), $forwardAccess),
            'Delete'    => array(pacsone_gettext('Delete'), pacsone_gettext('Delete checked studies'), $modifyAccess),
            'Print'     => array(pacsone_gettext('Print'), pacsone_gettext('Print checked studies'), $printAccess),
            'Export'    => array(pacsone_gettext('Export'), pacsone_gettext('Export checked studies'), $exportAccess),
        );
        print "<form method='POST' action='actionItem.php'>\n";
        displayButtons("study", $buttons, null);
    }
    $url = urlReplace($url, "type", $type);
    // check if need to toggle sorting order
    if (isset($_SESSION['sortToggle'])) {
        $toggle = 1 - $_SESSION['sortToggle'];
        $link = urlReplace($url, "toggle", $toggle);
    } else {
        $link = $url;
    }
    global $CUSTOMIZE_REFERRING_DOC;
    // links to different sorting methods
    $links = array(
        pacsone_gettext("Study ID")             => urlReplace($link, "sort", "cmp_studyid"),
        pacsone_gettext("Date")                 => urlReplace($link, "sort", "cmp_studydate"),
        pacsone_gettext("Accession Number")     => urlReplace($link, "sort", "cmp_accession"),
        pacsone_gettext("Received On")          => urlReplace($link, "sort", "cmp_received_opt"),
        $CUSTOMIZE_REFERRING_DOC                => urlReplace($link, "sort", "cmp_referdoc"),
        pacsone_gettext("Source AE")            => urlReplace($link, "sort", "cmp_sourceae"),
    );
    // display the following columns: column name <=> database field
    $columns = array(
        pacsone_gettext("Study ID")                 => "id",
        pacsone_gettext("Date")                     => "studydate",
        pacsone_gettext("Modalities")               => "modalities",
        pacsone_gettext("Accession Number")         => "accessionnum",
        $CUSTOMIZE_REFERRING_DOC                    => "referringphysician",
        pacsone_gettext("Received On")              => "received",
        pacsone_gettext("Source AE")                => "sourceae",
        pacsone_gettext("Number of Images")         => "images",
        pacsone_gettext("Total Size")               => "size");
    print "<table width=100% border=0 cellpadding=5>\n";
    global $BGCOLOR;
    print "<tr class=listhead bgcolor=$BGCOLOR>\n";
    if ($checkbox) {
        print "\t<td></td>\n";
    }
	if ($modifyAccess)
    	print "\t<td><b>" . pacsone_gettext("Privacy") . "</b></td>\n";
    $link = urlReplace($link, "sort", "cmp_patientid");
    global $CUSTOMIZE_PATIENT_ID;
    print "\t<td><b><a href=\"$link\">$CUSTOMIZE_PATIENT_ID</a></b></td>\n";
    $link = urlReplace($link, "sort", "cmp_name");
    global $CUSTOMIZE_PATIENT_NAME;
    print "\t<td><b><a href=\"$link\">$CUSTOMIZE_PATIENT_NAME</a></b></td>\n";
    foreach (array_keys($columns) as $key) {
        if (count($rows) && isset($links[$key])) {
            $link = $links[$key];
            print "\t<td><b><a href=$link>$key</a></b></td>\n";
        } else {
            print "\t<td><b>$key</b></td>\n";
        }
    }
    print "</tr>\n";
    foreach ($rows as $row) {
		$uid = $row['uuid'];
        print "<tr>\n";
        if ($checkbox) {
	        print "\t<td align=center width='1%'>\n";
            $data = $row['uuid'];
	        print "\t\t<input type='checkbox' name='entry[]' value='$data'></td>\n";
        }
		if ($modifyAccess) {
			$current = $row["private"];
			$value = ($current)? pacsone_gettext("Private ") : pacsone_gettext("Public ");
            if ($current) {
               $toggle = pacsone_gettext("<font color=red>Change to Public</font>");
            } else {
               $toggle = pacsone_gettext("Change to Private");
            }
			print "\t<td>$MYFONT$value</font><br>";
			print "<a href='markStudy.php?id=$uid&current=$current'>$toggle</a></font></td>\n";
		}
		$value = $row["patientid"];
		print "\t<td>$MYFONT";
        print "<a href='study.php?patientId=" . urlencode($value) . "'>$value</a></font></td>\n";
        $patName = $dbcon->getPatientName($value);
        print "\t<td>$patName</td>\n";
        foreach ($columns as $key => $field) {
            $value = $row[$field];
            if (strcasecmp($key, pacsone_gettext("Study ID")) == 0) {
            	if (!strlen($value))
					$value = pacsone_gettext("Study Details");
                $skip = ($skipSeries && $dbcon->hasSRseries($uid))? false : $skipSeries;
                $url = $skip? "image.php" : "series.php";
                $value = sprintf("<a href='$url?patientId=%s&studyId=%s'>%s</a>",
                    urlencode($row["patientid"]), $uid, $value);
            } else if (strcasecmp($key, pacsone_gettext("Total Size")) == 0) {
                $value = $dbcon->displayFileSize($value);
            } else if (strcasecmp($field, "modalities") == 0) {
                if (strlen($value) == 0)
                    $value = $dbcon->getStudyModalities($uid);
            } else if (strcasecmp($field, "studydate") == 0) {
                if (strlen($value))
                    $value = $dbcon->formatDate($value);
            } else if (strcasecmp($field, "received") == 0) {
                if (strlen($value))
                    $value = $dbcon->formatDateTime($value);
            } else if (!strcasecmp($field, "sourceae")) {
                // get the description information for the Source AE
                $bindList = array($value);
                $aet = $dbcon->preparedStmt("SELECT description FROM applentity WHERE title=?", $bindList);
                if ($aet && ($aerow = $aet->fetch(PDO::FETCH_NUM))) {
                    $desc = $aerow[0];
                    if (strlen($desc))
                        $value .= " - " . $desc;
                }
            }
            if (isset($value) && strlen($value))
    	        print "\t<td>$MYFONT$value</font></td>\n";
			else
    	        print "\t<td>$MYFONT" . pacsone_gettext("N/A") . "</font></td>\n";
        }
        print "</tr>\n";
    }
    print "</table>\n";
    if ($checkbox) {
        displayButtons("study", $buttons, null);
	    print "</form>\n";
    }
    $result = $dbcon->query("select * from smtp");
    // email report to user's email address
    if ($result && $result->rowCount() && count($list) &&
        ($email = $dbcon->getEmailAddress($username))) {
        print "<form method='POST' action='emailme.php'>\n";
        print "<input type=hidden name='to' value='$email'>";
        print "<input type=hidden name='preface' value='$preface'>";
        foreach ($list as $row) {
            $uid = $row['uuid'];
            print "<input type=hidden name='studies[]' value='$uid'>";
        }
        print "<input type=submit value='";
        print pacsone_gettext("Email Report To Me");
        print "'>";
        print "</form>\n";
    }
}

function displaySmtpServer($result, $preface)
{
    global $dbcon;
    print "<table width=100% cellpadding=0 cellspacing=0 border=0>\n";
    print "<tr><td>\n";
    print "<br>$preface\n";
    print "</td></tr>\n";
    print "<tr><td>\n";
    print "<form method='POST' action='modifyEmail.php'>\n";
    print "<input type='hidden' name='actionvalue'>\n";
    $records = $result->rowCount();
    // display all columns
    global $BGCOLOR;
    global $MYFONT;
    global $SMTP_PORTS;
    print "<table width=100% border=0 cellpadding=5>\n";
    print "<tr class=listhead bgcolor=$BGCOLOR>\n";
    if ($records) {
        print "\t<td></td>\n";
    }
    // display the following columns: column name <=> database field
    $columns = array(
        pacsone_gettext("SMTP Server")                  => "hostname",
        pacsone_gettext("TCP Port")                     => "port",
        pacsone_gettext("Encryption")                   => "encryption",
        pacsone_gettext("Connection Timeout")           => "timeout",
        pacsone_gettext("Description")                  => "description",
        pacsone_gettext("From Email Address")           => "myemail",
        pacsone_gettext("From Person Name")             => "myname",
        pacsone_gettext("Authentication")		        => "mechanism",
    );
    foreach (array_keys($columns) as $key) {
        print "\t<td><b>$key</b></td>\n";
    }
    print "\t<td><b>" . pacsone_gettext("Edit") . "</b></td>\n";
    print "</tr>\n";
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        print "<tr>\n";
        print "\t<td align=center width='1%'>\n";
        $server = $row['hostname'];
        print "\t\t<input type='checkbox' name='entry[]' value='$server'</td>\n";
        foreach ($columns as $key => $field) {
            if (isset($row[$field])) {
           	    $value = $row[$field];
                if (!strcasecmp($field, "encryption"))
                    $value = $SMTP_PORTS[$value][1];
                else if (!strcasecmp($field, "timeout"))
                    $value .= " " . pacsone_gettext("Seconds");
                print "\t<td>$MYFONT$value</font></td>\n";
            }
            else
                print "\t<td>$MYFONT" . pacsone_gettext("N/A") . "</font></td>\n";
        }
        print "\t<td>$MYFONT<a href='modifyEmail.php?server=$server'>";
        print pacsone_gettext("Edit");
        print "</a></font></td>\n";
        print "</tr>\n";
    }
    print "</table>\n";
    print "<p><table width=20% border=0 cellpadding=5>\n";
    print "<tr>\n";
    if ($records) {
        $check = pacsone_gettext("Check All");
        $uncheck = pacsone_gettext("Uncheck All");
        print "<td><input type=button value='$check' onClick='this.value=checkAll(this.form,\"entry\", \"$check\", \"$uncheck\")'></td>\n";
        print "<td><input type=submit value='";
        print pacsone_gettext("Delete");
        print "' name='action' title='";
        print pacsone_gettext("Delete checked SMTP server configurations");
        print "' onclick='switchText(this.form,\"actionvalue\",\"Delete\");return confirm(\"";
        print pacsone_gettext("Are you sure?");
        print "\");'></td>\n";
    } else {
        print "<td><input type=submit value='";
        print pacsone_gettext("Add");
        print "' name='action' title='";
        print pacsone_gettext("Add new SMTP server");
        print "' onclick='switchText(this.form,\"actionvalue\",\"Add\")'></td>\n";
    }
    print "</tr>\n";
    print "</table>\n";
    print "</form>\n";
    print "</tr></td>\n";
    print "</table>\n";
}

function displayStudyNotes(&$uid)
{
    global $dbcon;
    $notes = array();
    $bindList = array($uid);
    $result = $dbcon->preparedStmt("select * from studynotes where uuid=? order by created asc", $bindList);
    if ($result) {
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $notes[] = $row;
        }
    }
    $count = count($notes);
    if ($count > 1) {
        printf(pacsone_gettext("There are %d notes for this study<br>"), $count);
    } else {
        printf(pacsone_gettext("There is %d note for this study<br>"), $count);
    }
    $username = $dbcon->username;
    if ($count) {
        print "<DL>";
        foreach ($notes as $note) {
            $url = "studyNotes.php?view=1&uid=" . urlencode($note['uuid']);
            print "<DT><a href='$url'>" . $note['headline'] . "</a></DT>";
            $user = $note['username'];
            $email = $dbcon->getEmailAddress($user);
            if ($email)
                $user = "<a href=\"mailto:$email\">$user</a>";
            else
                $user = "<b>$user</b>";
            $when = $dbcon->formatDateTime($note['created']);
            printf(pacsone_gettext("<DD>by User: %s on %s</DD>"), $user, $when);
        }
        print "</DL>";
    }
    if (isset($uid)) {
        print "<form method='POST' action='studyNotes.php'>\n";
        print "<input type='hidden' name='studynoteaction'>\n";
        print "<p><input type=hidden name='uid' value='$uid'>\n";
        print "<input class=\"btn btn-primary\" type=submit value='";
        print pacsone_gettext("Add");
        print "' name='action' title='";
        print pacsone_gettext("Add New Study Note");
        print "' onclick='switchText(this.form,\"studynoteaction\",\"Add\")'>\n";
        print "</form>\n";
    }
}

function displayImageNotes(&$uid)
{
    global $dbcon;
    $notes = array();
    $bindList = array($uid);
    $result = $dbcon->preparedStmt("select * from imagenotes where uuid=? order by created asc", $bindList);
    if ($result) {
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $notes[] = $row;
        }
    }
    $count = count($notes);
    if ($count > 1) {
        printf(pacsone_gettext("There are %d notes for this image<br>"), $count);
    } else {
        printf(pacsone_gettext("There is %d note for this image<br>"), $count);
    }
    $username = $dbcon->username;
    if ($count) {
        print "<DL>";
        foreach ($notes as $note) {
            $url = "imageNotes.php?view=1&uid=" . urlencode($note['uuid']);
            print "<DT><a href='$url'>" . $note['headline'] . "</a></DT>";
            $user = $note['username'];
            $email = $dbcon->getEmailAddress($user);
            if ($email)
                $user = "<a href=\"mailto:$email\">$user</a>";
            else
                $user = "<b>$user</b>";
            $when = $dbcon->formatDateTime($note['created']);
            printf(pacsone_gettext("<DD>by User: %s on %s</DD>"), $user, $when);
        }
        print "</DL>";
    }
    if (isset($uid)) {
        print "<form method='POST' action='imageNotes.php'>\n";
        print "<input type='hidden' name='imagenoteaction'>\n";
        print "<p><input type=hidden name='uid' value='$uid'>\n";
        print "<input type=submit value='";
        print pacsone_gettext("Add");
        print "' name='action' title='";
        print pacsone_gettext("Add New Image Note");
        print "' onclick='switchText(this.form,\"imagenoteaction\",\"Add\")'>\n";
        print "</form>\n";
    }
}

function displayNotes($table, &$rows, $username, $url, $checkbox, $showExtra)
{
    global $dbcon;
    global $MYFONT;
    global $BGCOLOR;
    // check whether to bypass Series level
    $skipSeries = 0;
    $result = $dbcon->query("select skipseries from config");
    if ($result && ($row = $result->fetch(PDO::FETCH_NUM)))
        $skipSeries = $row[0];
    $count = 0;
    $modifyAccess = $dbcon->hasaccess("modifydata", $username);
    $download = $dbcon->hasaccess("download", $username);
    // display all notes for this study
    $columns = array(
        pacsone_gettext("Subject")       => "headline",
        pacsone_gettext("User")          => "username",
        pacsone_gettext("When")          => "created",
        pacsone_gettext("Notes")         => "notes",
    );
    global $CUSTOMIZE_PATIENT_NAME;
    $extras = array($CUSTOMIZE_PATIENT_NAME, pacsone_gettext("Study ID"));
    print "<p>";
    $notes = count($rows);
    $what = strcasecmp($table, "studynotes")? "image" : "study";
    if ($notes < 2)
        printf(pacsone_gettext("There is %d note for this %s"), $notes, $what);
    else
        printf(pacsone_gettext("There are %d notes for this %s"), $notes, $what);
    print "<p><table class=\"table table-bordered  table-striped\" width='100%' border=0 cellpadding=2 cellspacing=0>\n";

    //print "<tr class=listhead bgcolor=$BGCOLOR>\n";
    print "<tr class=\"danger\">\n"; // -------------------

    if ($modifyAccess && $checkbox)
        print "<td>&nbsp;</td>";
    foreach ($columns as $key => $field) {
        print "<td><b>$key</b></td>\n";
    }
    if ($showExtra) {
        foreach ($extras as $key)
            print "<td><b>$key</b></td>\n";
    }
    print "<td><b>" . pacsone_gettext("Attachments") . "</b></td>\n";
    print "<td><b>" . pacsone_gettext("Action") . "</b></td>\n";
    print "</tr>\n";  // ----------------------------------------

    foreach ($rows as $row) {
        $noteid = $row['id'];
        $uid = $row['uuid'];
        $author = $row['username'];
        $modifyNote = $dbcon->checkIsMyNote($table, $username, $noteid, $author);
        if (strcasecmp($table, "studynotes") == 0) {
            $studyUid = $uid;
        } else {
            // find the Study UID from this instance UID
            $result = $dbcon->query("select seriesuid from image where uuid='$uid'");
            if (!$result || $result->rowCount() != 1)
                die("<font color=red>" . pacsone_gettext("Failed to find Series UID!") . "</font>");
            $series = $result->fetch(PDO::FETCH_NUM);
            $seriesUid = $series[0];
            $result = $dbcon->query("select studyuid from series where uuid='$seriesUid'");
            if (!$result || $result->rowCount() != 1)
                die("<font color=red>" . pacsone_gettext("Failed to find Study UID!") . "</font>");
            $studyUid = $result->fetchColumn();
        }
        print "<tr class=\"info\">\n"; // ----------------------- for tr ----------------
        if (($modifyAccess || $download) || $modifyNote) {
            $count++;
            if ($checkbox) {
                print "<td align=center width='1%'>\n";
                print "<input type='checkbox' name='entry[]' value=$noteid></td>\n";
            }
        }
        else
            print "<td>&nbsp;</td>\n";
        foreach ($columns as $key => $field) {
            $value = $row[$field];
            if ($modifyNote && !strcasecmp($field, "headline")) {
                $link = "$url?view=1&uid=" . urlencode($uid);
                print "<td><a href='$link'>$value</a></td>\n";
            } else if (strcasecmp($field, "created") == 0) {
                $value = $dbcon->formatDateTime($value);
                printf("<td>$MYFONT%s</font></td>\n", $value);
            } else if (strcasecmp($field, "notes") == 0) {
                // convert line breaks into HTML
                $value = str_replace("\r\n", "<br>", $value);
                $value = str_replace("\n", "<br>", $value);
                $value = str_replace("\r", "<br>", $value);
                printf("<td>$MYFONT%s</font></td>\n", $value);
            } else {
                printf("<td>$MYFONT%s</font></td>\n", $value);
            }
        }
        // display extra information if requested
        if ($showExtra) {
            $patientId = $dbcon->getPatientIdByStudyUid($studyUid);
            $value = $dbcon->getPatientNameByStudyUid($studyUid);
            $link = "study.php?patientId=" . urlencode($patientId);
            print "<td><a href='$link'>$value</a></td>\n";
            $value = $dbcon->getStudyId($studyUid);
            $skip = ($skipSeries && $dbcon->hasSRseries($studyUid))? false : $skipSeries;
            $link = $skip? "image.php" : "series.php";
            $link = "$link?patientId=" . urlencode($patientId) . "&studyId=" . urlencode($studyUid);
            print "<td><a href='$link'>$value</a></td>\n";
        }
        // display attachments if any
        $attach = $dbcon->query("select * from attachment where id=$noteid and uuid='$uid'");
        if ($attach && $attach->rowCount()) {
            print "<td>";
            while ($attRow = $attach->fetch(PDO::FETCH_ASSOC)) {
                $path = $attRow['path'];
                $file = basename($path);
                $size = file_exists($path)? filesize($path) : $attRow['totalsize'];
                if ($download) {
                    $downloadUrl = "downloadAttachment.php?";
                    $downloadUrl .= "seq=" . $attRow['seq'];
                    $downloadUrl .= "&id=$noteid&uid=" . urlencode($uid);
                    print "<a href='$downloadUrl'><img src='attachment.gif' border=0>$file</a>";
                }
                else
                    print "<img src='attachment.gif' border=0>$file";
                print " ($size bytes)<br>";
            }
            print "</td>\n";
        } else {
            print "<td>" . pacsone_gettext("N/A") . "</td>\n";
        }
        if ($modifyNote) {
            $link = "$url?modify=1&id=$noteid&uid=" . urlencode($uid);
            print "<td><a href='$link'>" . pacsone_gettext("Edit") . "</a></td>\n";
        } else {
            print "<td>" . pacsone_gettext("N/A") . "</td>\n";
        }
        print "</tr>\n"; // ----------------------- for tr -----------------------
    }
    print "</table><p>\n";
    return $count;

    /*
    global $dbcon;
    global $MYFONT;
    global $BGCOLOR;
    // check whether to bypass Series level
    $skipSeries = 0;
    $result = $dbcon->query("select skipseries from config");
    if ($result && ($row = $result->fetch(PDO::FETCH_NUM)))
        $skipSeries = $row[0];
    $count = 0;
    $modifyAccess = $dbcon->hasaccess("modifydata", $username);
    $download = $dbcon->hasaccess("download", $username);
    // display all notes for this study
    $columns = array(
        pacsone_gettext("Subject")       => "headline",
        pacsone_gettext("User")          => "username",
        pacsone_gettext("When")          => "created",
        pacsone_gettext("Notes")         => "notes",
    );
    global $CUSTOMIZE_PATIENT_NAME;
    $extras = array($CUSTOMIZE_PATIENT_NAME, pacsone_gettext("Study ID"));
    print "<p>";
    $notes = count($rows);
    $what = strcasecmp($table, "studynotes")? "image" : "study";
    if ($notes < 2)
        printf(pacsone_gettext("There is %d note for this %s"), $notes, $what);
    else
        printf(pacsone_gettext("There are %d notes for this %s"), $notes, $what);
    print "<p><table width='100%' border=0 cellpadding=2 cellspacing=0>\n";
    print "<tr class=listhead bgcolor=$BGCOLOR>\n";
    if ($modifyAccess && $checkbox)
        print "<td>&nbsp;</td>";
    foreach ($columns as $key => $field) {
        print "<td><b>$key</b></td>\n";
    }
    if ($showExtra) {
        foreach ($extras as $key)
            print "<td><b>$key</b></td>\n";
    }
    print "<td><b>" . pacsone_gettext("Attachments") . "</b></td>\n";
    print "<td><b>" . pacsone_gettext("Action") . "</b></td>\n";
    print "</tr>\n";
    foreach ($rows as $row) {
        $noteid = $row['id'];
        $uid = $row['uuid'];
        $author = $row['username'];
        $modifyNote = $dbcon->checkIsMyNote($table, $username, $noteid, $author);
        if (strcasecmp($table, "studynotes") == 0) {
            $studyUid = $uid;
        } else {
            // find the Study UID from this instance UID
            $result = $dbcon->query("select seriesuid from image where uuid='$uid'");
            if (!$result || $result->rowCount() != 1)
                die("<font color=red>" . pacsone_gettext("Failed to find Series UID!") . "</font>");
            $series = $result->fetch(PDO::FETCH_NUM);
            $seriesUid = $series[0];
            $result = $dbcon->query("select studyuid from series where uuid='$seriesUid'");
            if (!$result || $result->rowCount() != 1)
                die("<font color=red>" . pacsone_gettext("Failed to find Study UID!") . "</font>");
            $studyUid = $result->fetchColumn();
        }
        print "<tr class=msgold>\n";
        if (($modifyAccess || $download) || $modifyNote) {
            $count++;
            if ($checkbox) {
                print "<td align=center width='1%'>\n";
                print "<input type='checkbox' name='entry[]' value=$noteid></td>\n";
            }
        }
        else
            print "<td>&nbsp;</td>\n";
        foreach ($columns as $key => $field) {
            $value = $row[$field];
            if ($modifyNote && !strcasecmp($field, "headline")) {
                $link = "$url?view=1&uid=" . urlencode($uid);
                print "<td><a href='$link'>$value</a></td>\n";
            } else if (strcasecmp($field, "created") == 0) {
                $value = $dbcon->formatDateTime($value);
        	    printf("<td>$MYFONT%s</font></td>\n", $value);
            } else if (strcasecmp($field, "notes") == 0) {
                // convert line breaks into HTML
                $value = str_replace("\r\n", "<br>", $value);
                $value = str_replace("\n", "<br>", $value);
                $value = str_replace("\r", "<br>", $value);
        	    printf("<td>$MYFONT%s</font></td>\n", $value);
            } else {
        	    printf("<td>$MYFONT%s</font></td>\n", $value);
            }
        }
        // display extra information if requested
        if ($showExtra) {
            $patientId = $dbcon->getPatientIdByStudyUid($studyUid);
            $value = $dbcon->getPatientNameByStudyUid($studyUid);
            $link = "study.php?patientId=" . urlencode($patientId);
            print "<td><a href='$link'>$value</a></td>\n";
            $value = $dbcon->getStudyId($studyUid);
            $skip = ($skipSeries && $dbcon->hasSRseries($studyUid))? false : $skipSeries;
            $link = $skip? "image.php" : "series.php";
            $link = "$link?patientId=" . urlencode($patientId) . "&studyId=" . urlencode($studyUid);
            print "<td><a href='$link'>$value</a></td>\n";
        }
        // display attachments if any
        $attach = $dbcon->query("select * from attachment where id=$noteid and uuid='$uid'");
        if ($attach && $attach->rowCount()) {
            print "<td>";
            while ($attRow = $attach->fetch(PDO::FETCH_ASSOC)) {
                $path = $attRow['path'];
                $file = basename($path);
                $size = file_exists($path)? filesize($path) : $attRow['totalsize'];
                if ($download) {
                    $downloadUrl = "downloadAttachment.php?";
                    $downloadUrl .= "seq=" . $attRow['seq'];
                    $downloadUrl .= "&id=$noteid&uid=" . urlencode($uid);
                    print "<a href='$downloadUrl'><img src='attachment.gif' border=0>$file</a>";
                }
                else
                    print "<img src='attachment.gif' border=0>$file";
                print " ($size bytes)<br>";
            }
            print "</td>\n";
        } else {
            print "<td>" . pacsone_gettext("N/A") . "</td>\n";
        }
        if ($modifyNote) {
            $link = "$url?modify=1&id=$noteid&uid=" . urlencode($uid);
            print "<td><a href='$link'>" . pacsone_gettext("Edit") . "</a></td>\n";
        } else {
            print "<td>" . pacsone_gettext("N/A") . "</td>\n";
        }
        print "</tr>\n";
    }
    print "</table><p>\n";
    return $count;
    */
}

function displayExportForm($level, &$exportdir)
{
    global $dbcon;
    global $EXPORT_MEDIA;
    print pacsone_gettext("Export To Local Directory: \n");
    print "<input type=text name='exportdir' size=64 maxlength=256 value='$exportdir'></input><br>";
    print pacsone_gettext("Export Media Size: ");
    print "<select name='media'>\n";
    foreach ($EXPORT_MEDIA as $type => $size) {
        $selected = strcasecmp($type, "CD")? "" : "selected";
        $descr = $size[0];
        print "<option $selected>$type - $descr</option>\n";
    }
    print "</select>\n";
    $label = "";
    if (isset($_SESSION['ExportMediaLabel']))
        $label = $_SESSION['ExportMediaLabel'];
    print "<br>" . pacsone_gettext("Media Label: ");
    print "<input type=text name='label' size=16 maxlength=16 value=$label></input>";
    print "<br><input type=checkbox name='zip' value=1>";
    print pacsone_gettext("Compress exported content into ZIP file");
    print "</input><br>";
    $viewerdir = "";
    if ($dbcon->isAdministrator($dbcon->username))
        $query = "select viewerdir from config";
    else
        $query = sprintf("select viewerdir from privilege where username='%s'", $dbcon->username);
    $result = $dbcon->query($query);
    if ($result) {
        $row = $result->fetch(PDO::FETCH_NUM);
        if (strlen($row[0])) {
            $viewerdir = $row[0];
            // append '/' at the end if not so already
            if (strcmp(substr($viewerdir, strlen($viewerdir)-1, 1), "/"))
	            $viewerdir .= "/";
        }
    }
    print "<input type=checkbox name='viewer' value=100>";
    print pacsone_gettext("Include External Viewer Program Files From Folder: \n");
    print "<input type='text' name='viewerdir' size=64 maxlength=256 value='$viewerdir'><br>";
    $modifyAccess = $dbcon->hasaccess("modifydata", $dbcon->username);
    if ($modifyAccess && !strcasecmp($level, pacsone_gettext("Study"))) {
        print "<input type='checkbox' name='purge' value=1>";
        print pacsone_gettext("Purge raw images of study after export");
        print "</input><br>";
    }
}

function displayHL7App($result, $preface)
{
    include_once 'toggleRowColor.js';
    global $dbcon;
    $mytitle = $dbcon->getMyAeTitle();
    print "<table width=100% cellpadding=0 cellspacing=0 border=0>\n";
    print "<tr><td>\n";
    print "<br>$preface\n";
    print "</td></tr>\n";
    print "<tr><td>\n";
    $records = $result->rowCount();
	// check if user has write access to table
	$access = 0;
	$username = $dbcon->username;
	$queryAccess = $dbcon->hasaccess("query", $username);
	if ($dbcon->hasaccess("modifydata", $username)) {
    	$access = 1;
        print "<form method='POST' action='modifyHL7App.php'>\n";
        print "<input type='hidden' name='actionvalue'>\n";
	}
    // display all columns
    global $BGCOLOR;
    global $MYFONT;
    print "<table width=100% border=0 cellpadding=5 class='mouseover optionrow'>\n";
    print "<tr class=listhead bgcolor=$BGCOLOR>\n";
	if ($access && $records) {
        print "\t<td></td>\n";
	}
    // display the following columns: column name <=> database field
    $columns = array(
        pacsone_gettext("Application")                       => "name",
        pacsone_gettext("Facility")                          => "facility",
        pacsone_gettext("Description")                       => "description",
        pacsone_gettext("Host")                              => "hostname",
        pacsone_gettext("IP Address")                        => "ipaddr",
        pacsone_gettext("Port Number")                       => "port",
        pacsone_gettext("Maximum Connections")               => "maxsessions",
        pacsone_gettext("ORU Report")                        => "orureport",
    );
    foreach (array_keys($columns) as $key) {
        print "\t<td><b>$key</b></td>\n";
    }
    print "\t<td><b>" . pacsone_gettext("Edit") . "</b></td>\n";
    print "</tr>\n";
    $count = 0;
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $style = ($count++ & 0x1)? "oddrows" : "evenrows";
        print "<tr class='$style'>\n";
		if ($access) {
			print "\t<td align=center width='1%'>\n";
            $name = $row['name'];
			print "\t\t<input type='checkbox' name='entry[]' value='$name'</td>\n";
		}
        foreach ($columns as $key => $field) {
            if (isset($row[$field])) {
            	$value = $row[$field];
                if (strcasecmp($field, "orureport") == 0)
                    $value = $value? pacsone_gettext("Enabled") : pacsone_gettext("Disabled");
                print "\t<td>$MYFONT$value</font></td>\n";
			}
            else
                print "\t<td>$MYFONT" . pacsone_gettext("N/A") . "</font></td>\n";
        }
		if ($access) {
			print "\t<td>$MYFONT<a href='modifyHL7App.php?name=$name'>";
            print pacsone_gettext("Edit");
            print "</a></font></td>\n";
        } else
			print "\t<td>$MYFONT" . pacsone_gettext("N/A") . "</font></td>\n";
        print "</tr>\n";
    }
    print "</table>\n";
    if ($access) {
    	print "<p><table width=20% border=0 cellpadding=5>\n";
        print "<tr>\n";
        if ($records) {
            $check = pacsone_gettext("Check All");
            $uncheck = pacsone_gettext("Uncheck All");
            print "<td><input type=button value='$check' onClick='this.value=checkAll(this.form,\"entry\", \"$check\", \"$uncheck\")'></td>\n";
            print "<td><input type=submit value='";
            print pacsone_gettext("Delete");
            print "' name='action' title='";
            print pacsone_gettext("Delete checked HL7 applications");
            print "' onclick='switchText(this.form,\"actionvalue\",\"Delete\");return confirm(\"";
            print pacsone_gettext("Are you sure?");
            print "\");'></td>\n";
        }
        print "<td><input type=submit value='";
        print pacsone_gettext("Add");
        print "' name='action' title='";
        print pacsone_gettext("Add new HL7 application");
        print "' onclick='switchText(this.form,\"actionvalue\",\"Add\")'></td>\n";
        print "</tr>\n";
    	print "</table>\n";
	    print "</form>\n";
    }
    print "</tr></td>\n";
    print "</table>\n";
}

function displayHL7Route($result, $preface)
{
    global $HL7ROUTE_KEY_TBL;
    global $SCHEDULE_TBL;
    global $dbcon;
    print "<p><table width=100% cellpadding=0 cellspacing=0 border=0>\n";
    print "<tr><td>\n";
    print "<br>$preface\n";
    print "</td></tr>\n";
    $records = $result->rowCount();
	// check if user has write access to table
	$access = 0;
	$username = $dbcon->username;
	$queryAccess = $dbcon->hasaccess("query", $username);
	if ($dbcon->hasaccess("modifydata", $username)) {
    	$access = 1;
        print "<form method='POST' action='modifyHL7Route.php'>\n";
        print "<input type='hidden' name='actionvalue'>\n";
	}
    global $BGCOLOR;
    global $MYFONT;
    print "<tr><td>\n";
    print "<table width=100% border=0 cellpadding=5>\n";
    print "<tr class=listhead bgcolor=$BGCOLOR>\n";
	if ($access && $records) {
        print "\t<td></td>\n";
	}
    // display the following columns: column name <=> database field
    $columns = array(
        pacsone_gettext("Source Application")       => "source",
        pacsone_gettext("Key Attribute")            => "keyname",
        pacsone_gettext("Match Pattern")            => "pattern",
        pacsone_gettext("Destination Application")  => "destination",
        pacsone_gettext("Schedule")                 => "schedule",
    );
    foreach (array_keys($columns) as $key) {
        print "\t<td><b>$key</b></td>\n";
    }
    print "\t<td><b>" . pacsone_gettext("Edit") . "</b></td>\n";
    $control = $access? pacsone_gettext("Control") : pacsone_gettext("Enabled");
    print "\t<td><b>$control</b></td>\n";
    print "</tr>\n";
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        print "<tr>\n";
        $source = $row['source'];
        $keyname = $row['keyname'];
        $destination = $row['destination'];
        $window = $row["schedwindow"];
        $enabled = $row['enabled'];
		if ($access) {
			print "\t<td align=center width='1%'>\n";
			print "\t\t<input type='checkbox' name='entry[]' value='$source;$keyname;$destination;$window'</td>\n";
		}
        foreach ($columns as $key => $field) {
            $value = $row[ $field ];
            if (isset($value)) {
                if (!strcasecmp($field, "Source") && ($value[0] == '_'))
                    $value = pacsone_gettext("Any");
                if (!strcasecmp($field, "keyname")) {
                    if (strlen($value) == 0)
                        $value = pacsone_gettext("N/A");
                    else
                        $value = array_search($value, $HL7ROUTE_KEY_TBL);
                }
                if (!strcasecmp($field, "Schedule")) {
                    if ($window) {
                        $from = ($window & 0xFF00) >> 8;
                        $to = ($window & 0x00FF);
                        $value = sprintf(pacsone_gettext("From %s To %s"), $SCHEDULE_TBL[$from], $SCHEDULE_TBL[$to]);
                    } else if (isset($SCHEDULE_TBL[$value])) {
                        $value = $SCHEDULE_TBL[$value];
                    } else {
                        $value = pacsone_gettext("Invalid schedule");
                    }
                }
                print "\t<td>$MYFONT$value</font></td>\n";
            }
            else
                print "\t<td>$MYFONT" . pacsone_gettext("N/A") . "</font></td>\n";
        }
		if ($access) {
			print "\t<td>$MYFONT<a href='modifyHL7Route.php?source=$source&destination=$destination&keyname=$keyname&window=$window'>Edit</a></font></td>\n";
            $toggle = ($enabled)? pacsone_gettext("Disable") : pacsone_gettext("Enable");
            $enabled = 1 - $enabled;
			print "\t<td>$MYFONT<a href='modifyHL7Route.php?source=$source&destination=$destination&keyname=$keyname&window=$window&enabled=$enabled'>$toggle</a></font></td>\n";
		} else {
			print "\t<td>$MYFONT" . pacsone_gettext("Edit") . "</font></td>\n";
            $toggle = ($enabled)? pacsone_gettext("Yes") : pacsone_gettext("No");
			print "\t<td>$MYFONT" . "$toggle</font></td>\n";
        }
        print "</tr>\n";
    }
    print "</table>\n";
    print "</td></tr>\n";
    if ($access) {
        print "<tr><td>\n";
    	print "<p><table width=20% border=0 cellpadding=5>\n";
        print "<tr>\n";
        if ($records) {
            $check = pacsone_gettext("Check All");
            $uncheck = pacsone_gettext("Uncheck All");
            print "<td><input type=button value='$check' onClick='this.value=checkAll(this.form,\"entry\", \"$check\", \"$uncheck\")'></td>\n";
        }
        print "<td><input type=submit value='";
        print pacsone_gettext("Add");
        print "' name='action' title='";
        print pacsone_gettext("Add New HL7 Message Routing Rule");
        print "' onclick='switchText(this.form,\"actionvalue\",\"Add\")'></td>\n";
        if ($records) {
            print "<td><input type=submit value='";
            print pacsone_gettext("Delete");
            print "' name='action' title='";
            print pacsone_gettext("Delete Checked HL7 Message Routing Rules");
            print "' onclick='switchText(this.form,\"actionvalue\",\"Delete\");return confirm(\"";
            print pacsone_gettext("Are you sure?");
            print "\");'></td>\n";
            print "<td><input type=submit value='";
            print pacsone_gettext("Enable All");
            print "' name='action' title='";
            print pacsone_gettext("Enable All HL7 Message Routing Rules");
            print "' onclick='switchText(this.form,\"actionvalue\",\"Enable All\")'></td>\n";
            print "<td><input type=submit value='";
            print pacsone_gettext("Disable All");
            print "' name='action' title='";
            print pacsone_gettext("Disable All HL7 Message Routing Rules");
            print "' onclick='switchText(this.form,\"actionvalue\",\"Disable All\")'></td>\n";
        }
        print "</tr>\n";
    	print "</table>\n";
        print "</td></tr>\n";
	    print "</form>\n";
    }
    print "</table>\n";
}

function displayGroups($result, $preface, $ldap = 0)
{
    global $USER_PRIVILEGE_TBL;
    print "<table width=100% cellpadding=0 cellspacing=0 border=0>\n";
    print "<tr><td>\n";
    print "<br>$preface\n";
    print "</td></tr>\n";
    print "</table>\n";
    $records = $result->rowCount();
	$failed = 0;
    $groups = array();
    while ($row = $result->fetch(PDO::FETCH_ASSOC))
        $groups[] = $row;
	// translate privilege values into strings
	$privTbl = array( 0 => "Disabled", 1 => "Enabled" );
    // display all columns
    global $BGCOLOR;
    global $MYFONT;
    print "<table width=100% border=0 cellpadding=3>\n";
    print "<form method='POST' action='modifyUser.php'>\n";
    print "<input type='hidden' name='actionvalue'>\n";
    if ($ldap)
        print "<input type='hidden' name='ldap' value=1>\n";
    print "<tr class=listhead bgcolor=$BGCOLOR>\n";
    $columns = $USER_PRIVILEGE_TBL;
    $columns["lastname"] = pacsone_gettext("Group Name");
    unset($columns["firstname"]);
    unset($columns["middlename"]);
    unset($columns["usergroup"]);
    unset($columns["notifynewstudy"]);
    $columns["matchgroup"] = pacsone_gettext("Group Share");
    $columns["substring"] = pacsone_gettext("Sub-String Group Matching");
	if ($records) {
    	print "\t<td></td>\n";
        foreach ($columns as $column => $descr) {
            print "\t<td><b>";
            print pacsone_gettext($descr);
            print "</b></td>\n";
        }
        print "\t<td><b>" . pacsone_gettext("Edit") . "</b></td>\n";
    }
    print "</tr>\n";
    foreach ($groups as $row) {
        print "<tr>\n";
		if ($records) {
			print "\t<td align=center width='1%'>\n";
			$user = $row["username"];
			print "\t\t<input type='checkbox' name='entry[]' value='$user'>";
			print "</td>\n";
		}
        foreach ($columns as $key => $descr) {
            $value = $row[$key];
            if (isset($value)) {
				if (isset($privTbl[$value])) {
					$enabled = $privTbl[$value];
                    $value = "<img src=\"" . (($value)? "enabled.gif" : "disabled.gif");
                    $value .= "\" alt=\"$enabled\">";
                }
                $align = (strstr($value, "img src"))? "center" : "left";
                print "\t<td align=$align>";
                if (strcasecmp($key, "Email"))
                    print "$MYFONT$value</font>";
                else
                    print "<a href=\"mailto:$value\">$value</a>";
                print "</td>\n";
			}
            else
                print "\t<td>$MYFONT" . pacsone_gettext("N/A") . "</font></td>\n";
        }
        $encoded = urlencode($user);
		print "\t<td>$MYFONT<a href='modifyUser.php?user=$encoded&group=1&ldap=$ldap'>";
        print pacsone_gettext("Edit");
        print "</a></font></td>\n";
        print "</tr>\n";
    }
    print "</table>\n";
    print "<p><table width=20% border=0 cellpadding=5>\n";
    print "<tr>\n";
	if ($records) {
        $check = pacsone_gettext("Check All");
        $uncheck = pacsone_gettext("Uncheck All");
    	print "<td><input type=button value='$check' onClick='this.value=checkAll(this.form,\"entry\", \"$check\", \"$uncheck\")'></td>\n";
    	print "<td><input type=submit value='";
        print pacsone_gettext("Delete User Group");
        print "' name='action' title='";
        print pacsone_gettext("Delete Selected User Groups");
        print "' onclick='switchText(this.form,\"actionvalue\",\"Delete User Group\");return confirm(\"";
        print pacsone_gettext("Are you sure?");
        print "\");'></td>\n";
	}
    if (!$ldap) {
        print "<td><input type=submit value='";
        print pacsone_gettext("Add User Group");
        print "' name='action' title='";
        print pacsone_gettext("Add User Group");
        print "' onclick='switchText(this.form,\"actionvalue\",\"Add User Group\")'></td>\n";
    }
    print "</tr>\n";
    print "</table>\n";
	print "</form>\n";
}

function displayLiveMonitor($result, $preface)
{
    global $dbcon;
    print "<table width=100% cellpadding=0 cellspacing=0 border=0>\n";
    print "<tr><td>\n";
    print "<br>$preface\n";
    print "</td></tr>\n";
    $records = $result->rowCount();
    global $BGCOLOR;
    global $MYFONT;
    print "<tr><td>\n";
    print "<table width=100% border=0 cellpadding=5>\n";
    print "<tr class=listhead bgcolor=$BGCOLOR>\n";
    // display the following columns: column name <=> database field
    global $CUSTOMIZE_PATIENT_NAME;
    $columns = array(
        pacsone_gettext("Session ID")       => "id",
        pacsone_gettext("IP Address")       => "ipaddr",
        pacsone_gettext("Port")             => "port",
        pacsone_gettext("Source AE")      	=> "sourceae",
        pacsone_gettext("Destination AE")   => "destae",
        pacsone_gettext("Session Type")     => "type",
        $CUSTOMIZE_PATIENT_NAME             => "patientname",
        pacsone_gettext("Study UID")        => "studyuid",
        pacsone_gettext("Study ID")         => "studyid",
        pacsone_gettext("Accession Number") => "accessionnum",
        pacsone_gettext("Study Date")       => "studydate",
        pacsone_gettext("Start Time")       => "starttime",
    );
    if ($records) {
        print "<td></td>\n";
        print "<form method='POST' action='liveMonitor.php' enctype='multipart/form-data'>\n";
        print "<input type='hidden' name='actionvalue'>\n";
    }
    foreach (array_keys($columns) as $key) {
        print "\t<td><b>$key</b></td>\n";
    }
    print "</tr>\n";
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        print "<tr>\n";
        print "\t<td align=center width='1%'>\n";
        $data = urlencode($row['id']);
        print "\t\t<input type='checkbox' name='entry[]' value='$data'></td>\n";
        foreach ($columns as $key => $field) {
            if (isset($row[$field])) {
            	$value = $row[$field];
                if (strlen($value)) {
                    if (!strcasecmp($field, "studydate"))
                        $value = $dbcon->formatDate($value);
                    else if (!strcasecmp($field, "starttime"))
                        $value = $dbcon->formatDateTime($value);
                }
            }
            else
                $value = pacsone_gettext("N/A");
            print "\t<td>$MYFONT$value</font></td>\n";
        }
        print "</tr>\n";
    }
    print "</table>\n";
    print "</td></tr>\n";
    if ($records) {
        print "<tr><td>\n";
	    print "<p><table width=10% border=0 cellpadding=5>\n";
        print "<tr>";
        $check = pacsone_gettext("Check All");
        $uncheck = pacsone_gettext("Uncheck All");
        print "<td><input type=button value='$check' onClick='this.value=checkAll(this.form,\"entry\", \"$check\", \"$uncheck\")'></td>\n";
        print "<td><input type=submit name='action' value='";
        print pacsone_gettext("Delete");
        print "' onclick='switchText(this.form,\"actionvalue\",\"Delete\");return confirm(\"";
        print pacsone_gettext("Are you sure?");
        print "\");'></td>";
        print "</tr></table>";
        print "</td></tr>\n";
        print "</form>\n";
    }
    print "</table><br>\n";
}

function displayAutoPurgeFilters($result, $preface)
{
    global $dbcon;
    global $SCHEDULE_TBL;
    print "<table width=100% cellpadding=0 cellspacing=0 border=0>\n";
    print "<tr><td>\n";
    print "<br>$preface\n";
    print "</td></tr>\n";
    $records = $result->rowCount();
	// check if user has write access to table
	$access = 0;
	$username = $dbcon->username;
	$queryAccess = $dbcon->hasaccess("query", $username);
	if ($dbcon->hasaccess("modifydata", $username)) {
    	$access = 1;
        print "<form method='POST' action='modifyAutopurgeFilter.php'>\n";
        print "<input type='hidden' name='actionvalue'>\n";
	}
    // display all columns
    global $BGCOLOR;
    global $MYFONT;
    global $AUTOPURGE_FILTER_TBL;
    print "<tr><td>\n";
    print "<table width=100% border=0 cellpadding=5>\n";
    print "<tr class=listhead bgcolor=$BGCOLOR>\n";
	if ($access && $records) {
        print "\t<td></td>\n";
	}
    // display the following columns: column name <=> database field
    $columns = array(
        pacsone_gettext("Data Element")             => "tag",
        pacsone_gettext("Pattern")               	=> "pattern",
        pacsone_gettext("Description")      		=> "description",
        pacsone_gettext("Schedule")                 => "schedule",
        pacsone_gettext("Aging Period")             => "aging",
        pacsone_gettext("Purge Empty Patient")      => "delpatient",
    );
	// data elements supported for coersion
    foreach (array_keys($columns) as $key) {
        print "\t<td><b>$key</b></td>\n";
    }
    print "\t<td><b>";
    print pacsone_gettext("Edit");
    print "</b></td>\n";
    print "</tr>\n";
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        print "<tr>\n";
		if ($access) {
			print "\t<td align=center width='1%'>\n";
            $key = $row['directory'] . "|" . $row['tag'] . "|" . $row['pattern'];
			print "\t\t<input type='checkbox' name='entry[]' value='$key'</td>\n";
		}
        foreach ($columns as $column => $field) {
            if (isset($row[$field])) {
            	$value = $row[$field];
				if (strcasecmp($field, "tag") == 0)
                	printf("\t<td>%s%s (0x%08x)</font></td>\n", $MYFONT, $AUTOPURGE_FILTER_TBL[$value], $value);
				else {
                    if (strcasecmp($field, "schedule") == 0)
                        $value = $SCHEDULE_TBL[$value];
                    else if (strcasecmp($field, "aging") == 0) {
                        $value = sprintf(pacsone_gettext("%d Days"), $value);
                    } else if (strcasecmp($field, "delpatient") == 0) {
                        $value = $value? pacsone_gettext("Yes") : pacsone_gettext("No");
                    }
                	print "\t<td>$MYFONT$value</font></td>\n";
                }
			}
            else
                print "\t<td>$MYFONT" . pacsone_gettext("N/A") . "</font></td>\n";
        }
		if ($access) {
            $key = urlencode($key);
			print "\t<td>$MYFONT<a href='modifyAutopurgeFilter.php?key=$key'>";
            print pacsone_gettext("Edit");
            print "</a></font></td>\n";
        }
		else
			print "\t<td>$MYFONT" . pacsone_gettext("Edit") . "</font></td>\n";
        print "</tr>\n";
    }
    print "</table>\n";
    print "</td></tr>\n";
    if ($access) {
        print "<tr><td>\n";
    	print "<p><table width=20% border=0 cellpadding=5>\n";
        print "<tr>\n";
        if ($records) {
            $check = pacsone_gettext("Check All");
            $uncheck = pacsone_gettext("Uncheck All");
            print "<td><input type=button value='$check' onClick='this.value=checkAll(this.form,\"entry\", \"$check\", \"$uncheck\")'></td>\n";
            print "<td><input type=submit value='";
            print pacsone_gettext("Delete");
            print "' name='action' title='";
            print pacsone_gettext("Delete checked Automatic Purging filters");
            print "' onclick='switchText(this.form,\"actionvalue\",\"Delete\");return confirm(\"";
            print pacsone_gettext("Are you sure?");
            print "\");'></td>\n";
        }
        print "<td><input type=submit value='";
        print pacsone_gettext("Add");
        print "' name='action' title='";
        print pacsone_gettext("Add new Automatic Purging filters");
        print "' onclick='switchText(this.form,\"actionvalue\",\"Add\")'></td>\n";
        print "</tr>\n";
    	print "</table>\n";
	    print "</form>\n";
        print "</td></tr>\n";
    }
    print "</table><br>\n";
}

function displayAnonymization($result, $preface)
{
    global $dbcon;
    global $ANONYMIZE_TEMPLATE_TBL;
    print "<table width=100% cellpadding=0 cellspacing=0 border=0>\n";
    print "<tr><td>\n";
    print "<br>$preface\n";
    print "</td></tr>\n";
    $records = $result? $result->rowCount() : 0;
	// check if user has write access to table
	$access = 0;
	$username = $dbcon->username;
	$queryAccess = $dbcon->hasaccess("query", $username);
	if ($dbcon->hasaccess("modifydata", $username)) {
    	$access = 1;
        print "<form method='POST' action='modifyAnonymize.php'>\n";
        print "<input type='hidden' name='actionvalue'>\n";
	}
    // display all columns
    global $BGCOLOR;
    global $MYFONT;
    print "<tr><td>\n";
    print "<table width=100% border=1 cellpadding=5 cellspacing=0>\n";
    print "<tr class=listhead bgcolor=$BGCOLOR>\n";
	if ($access && $records) {
        print "\t<td></td>\n";
	}
    print "<td><b>";
    print pacsone_gettext("Template Name");
    print "</b></td>";
    // display the following columns: column name <=> database field
    $columns = array(
        pacsone_gettext("Data Element")             => "tag",
        pacsone_gettext("Syntax")               	=> "syntax",
        pacsone_gettext("Description")      		=> "description",
    );
    print "\t<td><b>";
    print pacsone_gettext("Details");
    print "</b></td>\n";
    print "\t<td><b>";
    print pacsone_gettext("Edit");
    print "</b></td>\n";
    print "</tr>\n";
    while ($result && ($row = $result->fetch(PDO::FETCH_NUM))) {
        print "<tr>\n";
		if ($access) {
			print "\t<td align=center width='1%'>\n";
            $value = $row[0];
			print "\t\t<input type='checkbox' name='entry[]' value='$value'</td>\n";
		}
        print "<td>" . $row[0] . "</td>";
        // all tags defined for this template
        print "<td>";
        print "<table width=100% border=0 cellpadding=3>\n";
        print "<tr>";
        foreach (array_keys($columns) as $key) {
            print "\t<td><b><u>$key</u></b></td>\n";
        }
        print "</tr>";
        $q = "select tag,syntax,description from anonymity where templname='" . $row[0] . "' order by tag";
        $template = $dbcon->query($q);
        while ($template && ($temprow = $template->fetch(PDO::FETCH_NUM))) {
            print "<tr>";
            $tag = $temprow[0];
            foreach ($temprow as $key => $value) {
                if (!$key && isset($ANONYMIZE_TEMPLATE_TBL[$tag])) {
                    //$value = $ANONYMIZE_TEMPLATE_TBL[$tag][0];
                    $value = $ANONYMIZE_TEMPLATE_TBL[$tag][0] . sprintf(" (0x%08X)", $tag);
                }
                if (!strlen($value))
                    $value = pacsone_gettext("N/A");
                print "<td>$value</td>";
            }
            print "</tr>";
        }
        print "</table>";
        print "</td>";
		if ($access) {
            $name = urlencode($row[0]);
			print "\t<td>$MYFONT<a href='modifyAnonymize.php?name=$name'>";
            print pacsone_gettext("Edit");
            print "</a></font></td>\n";
        }
		else
			print "\t<td>$MYFONT" . pacsone_gettext("Edit") . "</font></td>\n";
        print "</tr>\n";
    }
    print "</table>\n";
    print "</td></tr>\n";
    if ($access) {
        print "<tr><td>\n";
    	print "<p><table width=20% border=0 cellpadding=5>\n";
        print "<tr>\n";
        if ($records) {
            $check = pacsone_gettext("Check All");
            $uncheck = pacsone_gettext("Uncheck All");
            print "<td><input type=button value='$check' onClick='this.value=checkAll(this.form,\"entry\", \"$check\", \"$uncheck\")'></td>\n";
            print "<td><input type=submit value='";
            print pacsone_gettext("Delete");
            print "' name='action' title='";
            print pacsone_gettext("Delete checked Anonymization Templates");
            print "' onclick='switchText(this.form,\"actionvalue\",\"Delete\");return confirm(\"";
            print pacsone_gettext("Are you sure?");
            print "\");'></td>\n";
        }
        print "<td><input type=submit value='";
        print pacsone_gettext("Add");
        print "' name='action' title='";
        print pacsone_gettext("Add new Anonymization Template");
        print "' onclick='switchText(this.form,\"actionvalue\",\"Add\")'></td>\n";
        print "</tr>\n";
    	print "</table>\n";
	    print "</form>\n";
        print "</td></tr>\n";
    }
    print "</table><br>\n";
}

function displayAutoPurgeSettings($result, $preface)
{
    global $dbcon;
    global $SCHEDULE_TBL;
    print "<table width=100% cellpadding=0 cellspacing=0 border=0>\n";
    print "<tr><td>\n";
    print "<br>$preface\n";
    print "</td></tr>\n";
    $records = $result->rowCount();
	// check if user has write access to table
	$access = 0;
	$username = $dbcon->username;
	$queryAccess = $dbcon->hasaccess("query", $username);
	if ($dbcon->hasaccess("modifydata", $username)) {
    	$access = 1;
        print "<form method='POST' action='autoPurge.php'>\n";
        print "<input type='hidden' name='actionvalue'>\n";
	}
    // display all columns
    global $BGCOLOR;
    global $MYFONT;
    print "<tr><td>\n";
    print "<table width=100% border=0 cellpadding=5>\n";
    print "<tr class=listhead bgcolor=$BGCOLOR>\n";
	if ($access && $records) {
        print "\t<td></td>\n";
	}
    // display the following columns: column name <=> database field
    $columns = array(
        pacsone_gettext("Purge By"),
        pacsone_gettext("Purge Operation"),
        pacsone_gettext("Schedule"),
        pacsone_gettext("Purge Empty Patient"),
        pacsone_gettext("Control"),
        pacsone_gettext("Edit"),
    );
	// data elements supported for coersion
    foreach ($columns as $key) {
        print "\t<td><b>$key</b></td>\n";
    }
    print "</tr>\n";
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        print "<tr>\n";
		if ($access) {
			print "\t<td align=center width='1%'>\n";
            $key = $row['seq'];
			print "\t\t<input type='checkbox' name='entry[]' value='$key'</td>\n";
		}
        // Purge By column
        print "<td>";
        $sourceAe = isset($row['sourceae'])? $row['sourceae'] : "";
        $aging = $row['aging'];
        if (!$aging && !strlen($sourceAe)) {
            $lowWater = $row['lowwater'];
            $highWater = $row['highwater'];
            printf(pacsone_gettext("Storage Capacity with Low Watermark at %d percent and High Watermark at %d percent"), $lowWater, $highWater);
        } else {
            if ($aging > 0) {
                printf(pacsone_gettext("Purge Study Received More Than %d Days Ago"), $aging);
            } else if ($aging < 0) {
                printf(pacsone_gettext("Purge Study Acquired (Study Date) More Than %d Days Ago"), abs($aging));
            }
            if (strlen($sourceAe)) {
                if ($aging)
                    printf(pacsone_gettext(" From This Source AE: <u>%s</u>"), $sourceAe);
                else
                    printf(pacsone_gettext("All Studies Received From This Source AE: <u>%s</u>"), $sourceAe);
            }
        }
        print "</td>";
        // Purge Operation column
        $destdir = $row['destdir'];
        print "<td>";
        if (strlen($destdir))
            printf(pacsone_gettext("Move Image Files to Destination Folder: <u>%s</u>"), $destdir);
        else
            print pacsone_gettext("Delete Image Files");
        print "</td>";
        // Schedule column
        $schedule = $row['schedule'];
        $hour = $schedule % 12;
        if ($hour == 0)
            $hour = 12;
        $hour .= " ";
        $hour .= ($schedule >= 12)? pacsone_gettext("P.M.") : pacsone_gettext("A.M.");
        print "<td>$hour</td>";
        // Delete Patient After Purge column
        $delpatient = $row['delpatient']? pacsone_gettext("Yes") : pacsone_gettext("No");
        print "<td>$delpatient</td>";
        // Control column
        $value = $row['enable']? pacsone_gettext("Disable") : pacsone_gettext("Enable");
        $control = $row['enable']? 0 : 1;
		if ($access)
			print "\t<td>$MYFONT<a href='autoPurge.php?actionvalue=toggle&key=$key&enable=$control'>$value</a></font></td>\n";
		else
			print "\t<td>$MYFONT" . $value . "</font></td>\n";
        // Edit column
		if ($access) {
			print "\t<td>$MYFONT<a href='autoPurge.php?actionvalue=edit&key=$key'>";
            print pacsone_gettext("Edit");
            print "</a></font></td>\n";
        }
		else
			print "\t<td>$MYFONT" . pacsone_gettext("Edit") . "</font></td>\n";
        print "</tr>\n";
    }
    print "</table>\n";
    print "</td></tr>\n";
    if ($access) {
        print "<tr><td>\n";
    	print "<p><table width=20% border=0 cellpadding=5>\n";
        print "<tr>\n";
        if ($records) {
            $check = pacsone_gettext("Check All");
            $uncheck = pacsone_gettext("Uncheck All");
            print "<td><input type=button value='$check' onClick='this.value=checkAll(this.form,\"entry\", \"$check\", \"$uncheck\")'></td>\n";
            print "<td><input type=submit value='";
            print pacsone_gettext("Delete");
            print "' name='action' title='";
            print pacsone_gettext("Delete checked Automatic Purging Rules");
            print "' onclick='switchText(this.form,\"actionvalue\",\"Delete\");return confirm(\"";
            print pacsone_gettext("Are you sure?");
            print "\");'></td>\n";
        }
        print "<td><input type=submit value='";
        print pacsone_gettext("Add");
        print "' name='action' title='";
        print pacsone_gettext("Add new Automatic Purging Rule");
        print "' onclick='switchText(this.form,\"actionvalue\",\"Add\")'></td>\n";
        print "</tr>\n";
    	print "</table>\n";
	    print "</form>\n";
        print "</td></tr>\n";
    }
    print "</table><br>\n";
}

function displayMatrixControl($what, &$list, &$preface, &$url, &$offset, $all)
{
    print "<script language=\"javascript\">";
    print "function resizeJpegImage(percent, count) {";
    print "    var i = 0;";
    print "    for (i = 0; i < count; i++) {";
    print "    var img = document.getElementById(\"jpegimage\" + i);";
    print "    var wpx = document.getElementById(\"jpegwidth\" + i).style.width;";
    print "    var hpx = document.getElementById(\"jpegheight\" + i).style.height;";
    print "    var ww = wpx.split('p');";
    print "    var width = parseInt(ww[0]);";
    print "    var hh = hpx.split('p');";
    print "    var height = parseInt(hh[0]);";
    print "    img.width = width * percent / 100;";
    print "    img.height = height * percent / 100;";
    print "    }";
    print "}";
    print "</script>";
    $total = count($list);
    $pageSize = 10;
    global $FULLSIZE_MATRIX;
    $xdim = $FULLSIZE_MATRIX % 10;
    $ydim = (int)($FULLSIZE_MATRIX / 10);
    $pageSize = $xdim * $ydim;
    $page = ($all)? $total : $pageSize;
    // build a page of entries to be displayed
    $rows = array();
    for ($count = 0; ($count < $page) && ($count < $total) && (($count + $offset) < $total); $count++) {
        $rows[] = $list[$offset+$count];
    }
    print "<table width=100% cellpadding=0 cellspacing=0 border=0>\n";
    global $BGCOLOR;
    print "<tr style=\"background-color: $BGCOLOR;color: white; font-weight: bold\">";
    print "<td align=\"left\" valign=\"middle\"><a href=\"home.php\">";
    print pacsone_gettext("Main Menu");
    print "</a></td></tr>";
    print "<tr><td>$preface</td></tr>\n";
    print "<tr><td>&nbsp;</td></tr>\n";
    if ($total) {
        // display Previous, Next and Page Number links
        print "<tr><td align=left>\n";
        $and = (strrpos($url, "?") == false)? "?" : "&";
        $previous = $offset - $page;
        if ($offset > 0) {
            print "<a id=\"previous\" href=\"$url" . $and . "offset=" . urlencode($previous) . "\">";
            print pacsone_gettext("Previous");
            print "</a> ";
        } else {
            print pacsone_gettext("Previous ");
        }
        if ($total > $page) {
            $start = $offset - 10 * $pageSize;
            if ($start < 0)
                $start = 0;
            $end = $offset + 10 * $pageSize;
            if ($end > $total)
                $end = $total;
            for ($i = $start, $p = ($i / $pageSize + 1); $i < $end; $i += $page, $p++) {
                if ($i < $offset || $i > ($offset+$page-1))
                    print "<a href=\"$url" . $and . "offset=" . urlencode($i) . "\">$p</a> ";
                else
                    print "$p ";
            }
        }
        $next = $offset + $page;
        if ($total > $next) {
            print "<a id=\"next\" href=\"$url" . $and . "offset=" . urlencode($next) . "\">";
            print pacsone_gettext("Next");
            print "</a> ";
        } else {
            print pacsone_gettext("Next ");
        }
        print "</td></tr>";
        print "<tr><td>&nbsp;</td></tr>";
        print "<tr><td align=left>\n";
        if (!$all && ($total > $page)) {
            $link = urlReplace($url, "all", 1);
            print "<a href=\"$link\">";
            print pacsone_gettext("Display All");
            print "</a>&nbsp;&nbsp;&nbsp;";
        }
        else if ($all) {
            $link = urlReplace($url, "all", 0);
            print "<a href=\"$link\">";
            print pacsone_gettext("Paginate");
            print "</a>&nbsp;&nbsp;&nbsp;";
        }
        $start = ($total)? ($offset+1) : 0;
        printf(pacsone_gettext("Displaying %d-%d of %d %s:"), $start, $count+$offset, $total, $what);
        print "</td></tr>\n";
    }
    print "</table>\n";
    $url = urlReplace($url, "all", $all);
    return $rows;
}

function displayImageMatrix(&$list, $preface, $url, $offset, $all)
{
    $taggedOnly = stristr($url, "tagged=1")? 1 : 0;
    // display navigation links
    global $BGCOLOR;
    global $TAGCOLOR;
    print "<table width=100% border=0 cellspacing=5 cellpadding=0>\n";
    print "<tr valign=top>";
    print "<td class=notes width=\"20%\">";
    $rows = displayMatrixControl(pacsone_gettext("Images"), $list, $preface, $url, $offset, $all);
    if (count($rows)) {
        $count = count($rows);
        // different size options
        $sizes = array(
            25      => pacsone_gettext("75% Smaller"),
            50      => pacsone_gettext("50% Smaller"),
            75      => pacsone_gettext("25% Smaller"),
            100     => pacsone_gettext("Original Size"),
            125     => pacsone_gettext("25% Larger"),
            150     => pacsone_gettext("50% Larger"),
            175     => pacsone_gettext("75% Larger"),
        );
        print "<br>";
        foreach ($sizes as $key => $value) {
            print "<br><br><SPAN onMouseOver=\"resizeJpegImage($key, $count);\"><U>$value</U>&nbsp;</SPAN><br>\n";
        }    
    }
    print "</td>";
    print "<td width=1 bgcolor=$BGCOLOR><img src=blank.gif width=1 height=1></td>";
    print "<td>";
    global $MYFONT;
    global $dbcon;
    // check user privileges
	$username = $dbcon->username;
	$checkbox = 0;
	$access = $dbcon->hasaccess("modifydata", $username);
	$downloadAccess = $dbcon->hasaccess("download", $username);
    $printAccess = $dbcon->hasaccess("print", $username);
    if ($printAccess) {
        $printers = $dbcon->query("select printscp from applentity where printscp=1");
        if ($printers && ($printers->rowCount() == 0))
            $printAccess = 0;
    }
    $exportAccess = $dbcon->hasaccess("export", $username);
	$forwardAccess = $dbcon->hasaccess("forward", $username);
	if (($access || $downloadAccess || $printAccess || $exportAccess || $forwardAccess) && sizeof($rows)) {
    	$checkbox = 1;
	}
	// check if Java Applet viewer exists
	$showDicom = 0;
	if (appletExists()) {
		$showDicom = 1;
		$checkbox = 1;
	}
    // get the thumbnail directory if configured
    $imagedir = "";
    $flashdir = "";
    $result = $dbcon->query("select imagedir,flashdir from config");
    if ($result && $result->rowCount()) {
        $row = $result->fetch(PDO::FETCH_NUM);
        if (strlen($row[0]) && file_exists($row[0]))
            $imagedir = $row[0];
        if (strlen($row[1]) && file_exists($row[1]))
            $flashdir = $row[1];
    }
	global $FULLSIZE_MATRIX;
    $xdim = $FULLSIZE_MATRIX % 10;
    $ydim = $FULLSIZE_MATRIX / 10;
    $cellwidth = 100 / $xdim;
	print "<table width=100% border=0 cellpadding=0>\n";
    $count = 0;
    set_time_limit(0);
	foreach ($rows as $row) {
    	$uid = $row['uuid'];
        $mimetype = isset($row['mimetype'])? $row['mimetype'] : "";
        if ($count % $xdim == 0)
            print "<tr>";
        $tagged = $row["tagged"]? "bgcolor=$TAGCOLOR" : "";
		print "\t<td valign=center align='center' $tagged>\n";
        print "\t<table width=100% border=0 cellpadding=0>\n";
        print "<tr>";
        $thumbUp = 1;
        $flashVideo = 0;
        $thumbdir = "images";
        $path = $row["path"];
        $xfersyntax = $row["xfersyntax"];
        $dir = strlen($imagedir)? $imagedir : getcwd();
        $dir = strtr($dir, "\\", "/");
        // append '/' at the end if not so already
        if (strcmp(substr($dir, strlen($dir)-1, 1), "/"))
            $dir .= "/";
        $dir .= "$thumbdir/";
        // create the full-size images directory if it doesn't exist
        if (!is_dir($dir))
            mkdir($dir);
        $thumbnail = $dir . $uid;
        if (file_exists($thumbnail . ".gif"))
            $thumbnail .= ".gif";
        else if (file_exists($thumbnail . ".jpg"))
            $thumbnail .= ".jpg";
        else if (!strcmp($xfersyntax, "1.2.840.10008.1.2.4.100") ||
                 !strcmp($xfersyntax, "1.2.840.10008.1.2.4.102") ||
                 !strcmp($xfersyntax, "1.2.840.10008.1.2.4.103")) {
            $thumbUp = 0;
            if (strlen($flashdir) == 0) {
                // default flash video directory
                $flashdir = strtr(dirname($_SERVER['SCRIPT_FILENAME']), "\\", "/");
                // append '/' at the end if not so already
                if (strcmp(substr($flashdir, strlen($flashdir)-1, 1), "/"))
                    $flashdir .= "/";
                $flashdir .= "flash/";
            }
            $flashdir = strtr($flashdir, "\\", "/");
            // append '/' at the end if not so already
            if (strcmp(substr($flashdir, strlen($flashdir)-1, 1), "/"))
                $flashdir .= "/";
            // check if converted flash video exists
            if (file_exists($flashdir . basename($path) . ".swf")) {
                $flashVideo = 1;
            } else {
                $thumbnail = "error.png";
                $thumbdir = ".";
            }
        } else if (strlen($mimetype)) {
            // encapsulated documents
            $thumbUp = 0;
            $encapsulated = $path . ".encap";
            if (!file_exists($encapsulated)) {
                $thumbnail = "error.png";
                $thumbdir = ".";
                $mimetype = "";
            }
        } else {
            // create a thumbnail image if not there
            $src = imagick_readimage($path);
            $ok = 0;
            if (!imagick_iserror($src)) {
                // write thumbnail image
                if (imagick_getlistsize($src) > 1)
                    $thumbnail .= ".gif";
                else
                    $thumbnail .= ".jpg";
                if (imagick_writeimage($src, $thumbnail)) {
                    $ok = 1;
                }
            }
            if (!$ok) {
                $thumbUp = 0;
                $thumbnail = "error.png";
                $thumbdir = ".";
            }
            imagick_destroyhandle($src);
        }
        $basename = basename($thumbnail);
        print "\t<td width='$cellwidth%'><table width=100% border=0 cellpadding=0>\n";
        $instance = $row["instance"];
        $alt = sprintf(pacsone_gettext("Instance %d"), $instance);
        if ($thumbUp) {
            $imgsrc = strlen($imagedir)? ("tempimage.php?path=" . urlencode($thumbnail) . "&purge=0") : "$thumbdir/$basename";
            print "\t<tr><td align='center'><a href='showImage.php?id=$uid&tagged=$taggedOnly'><img src='$imgsrc' alt='$alt' id=\"jpegimage$count\" border=0></a></td></tr>\n";
            // hide with/height information
            $jpg = imagick_readimage($thumbnail);
            if (!imagick_iserror($jpg)) {
                $width = imagick_getwidth($jpg);
                $height = imagick_getheight($jpg);
                imagick_destroyhandle($jpg);
                print "<div id=\"jpegwidth$count\" style=\"display:none;width:$width\"></div>";
                print "<div id=\"jpegheight$count\" style=\"display:none;height:$height\"></div>";
            }
        } else if ($flashVideo) {
            $swf = basename($path) . ".swf";
            $embed = "flash/$swf";
            print "<object classid=\"clsid:D27CDB6E-AE6D-11cf-96B8-444553540000\" codebase=\"http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=6,0,29,0\" width=\"400\" height=\"400\">";
            print "<param name=\"movie\" value=\"$swf\">";
            print "<param name=\"quality\" value=\"high\">";
            print "<param name=\"LOOP\" value=\"false\">";
            print "<embed src=\"$embed\" width=\"400\" height=\"400\" loop=\"false\" quality=\"high\" pluginspage=\"http://www.macromedia.com/go/getflashplayer\" type=\"application/x-shockwave-flash\"></embed>";
            print "</object>";
        } else if (strlen($mimetype) && isset($encapsulated)) {
            // encapsulated documents
            global $ENCAPSULATED_DOC_ICON_TBL;
            $imgsrc = isset($ENCAPSULATED_DOC_ICON_TBL[strtoupper($mimetype)])? $ENCAPSULATED_DOC_ICON_TBL[strtoupper($mimetype)] : "question.jpg";
            $link = "encapsulatedDoc.php?path=" . urlencode($encapsulated) . "&mimetype=" . urlencode($mimetype);
            print "\t<tr><td align='center'><a href=\"$link\"><img src='$imgsrc' border=0></a></td></tr>\n";
        } else
            print "\t<tr><td align='center'><img src='$thumbdir/$basename' alt='$alt' border=0></td></tr>\n";
        print "\t</table></td>\n";
        print "\t</tr></table>\n";
        print "</td>\n";
        if ($count % $xdim == ($xdim - 1))
		    print "</tr>\n";
        $count++;
	}
    $left = $count % $xdim;
    if ($left > 0) {
        $left = $xdim - $left;
        for ($i = 0; $i < $left; $i++)
            print "<td width='$cellwidth%'>&nbsp;</td>";
        print "</tr>";
    }
	print "</table>\n";
    print "</td></tr>";
    print "</table>";
}

function displayTranscription($result, $preface)
{
    global $dbcon;
    global $XSCRIPT_BOOKMARK_FIELD_TBL;
    print "<table width=100% cellpadding=0 cellspacing=0 border=0>\n";
    print "<form method='POST' action='modifyTranscript.php'>\n";
    print "<input type='hidden' name='actionvalue'>\n";
    print "<tr><td>\n";
    print "<br>$preface\n";
    print "</td></tr>\n";
    $records = $result? $result->rowCount() : 0;
    // display all columns
    global $BGCOLOR;
    global $MYFONT;
    print "<tr><td>\n";
    print "<table width=100% border=1 cellpadding=5 cellspacing=0>\n";
    print "<tr class=listhead bgcolor=$BGCOLOR>\n";
	if ($records) {
        print "\t<td></td>\n";
	}
    print "<td><b>";
    print pacsone_gettext("Template Name");
    print "</b></td>";
    print "\t<td><b>";
    print pacsone_gettext("Details");
    print "</b></td>\n";
    print "\t<td><b>";
    print pacsone_gettext("Edit");
    print "</b></td>\n";
    print "</tr>\n";
    // display the following columns: column name <=> database field
    $columns = array(
        pacsone_gettext("Bookmark Name")            => "bookmark",
        pacsone_gettext("Database Field ID")        => "id",
        pacsone_gettext("Description")      		=> "description",
    );
    while ($result && ($row = $result->fetch(PDO::FETCH_NUM))) {
        print "<tr>\n";
		print "\t<td align=center width='1%'>\n";
        $name = $row[0];
		print "\t\t<input type='checkbox' name='entry[]' value='$name'</td>\n";
        print "<td>" . $name . "</td>";
        // all bookmarks defined for this template
        print "<td>";
        print "<table width=100% border=0 cellpadding=3>\n";
        print "<tr>";
        foreach (array_keys($columns) as $key) {
            print "\t<td><b><u>$key</u></b></td>\n";
        }
        print "</tr>";
        $q = "select id,bookmark,description from xscriptbookmark where template='" . $name . "' order by id";
        $template = $dbcon->query($q);
        while ($template && ($temprow = $template->fetch(PDO::FETCH_NUM))) {
            print "<tr>";
            $fieldid = $XSCRIPT_BOOKMARK_FIELD_TBL[$temprow[0]][0];
            $bookmark = $temprow[1];
            $descr = $temprow[2];
            if (!strlen($descr))
                $descr = pacsone_gettext("N/A");
            print "<td>$bookmark</td>";
            print "<td>$fieldid</td>";
            print "<td>$descr</td>";
            print "</tr>";
        }
        print "</table>";
        print "</td>";
        $name = urlencode($row[0]);
		print "\t<td>$MYFONT<a href='modifyTranscript.php?name=$name'>";
        print pacsone_gettext("Edit");
        print "</a></font></td>\n";
        print "</tr>\n";
    }
    print "</table>\n";
    print "</td></tr>\n";
    print "<tr><td>\n";
    print "<p><table width=20% border=0 cellpadding=5>\n";
    print "<tr>\n";
    if ($records) {
        $check = pacsone_gettext("Check All");
        $uncheck = pacsone_gettext("Uncheck All");
        print "<td><input type=button value='$check' onClick='this.value=checkAll(this.form,\"entry\", \"$check\", \"$uncheck\")'></td>\n";
        print "<td><input type=submit value='";
        print pacsone_gettext("Delete");
        print "' name='action' title='";
        print pacsone_gettext("Delete checked Transcription Templates");
        print "' onclick='switchText(this.form,\"actionvalue\",\"Delete\");return confirm(\"";
        print pacsone_gettext("Are you sure?");
        print "\");'></td>\n";
    }
    print "<td><input type=submit value='";
    print pacsone_gettext("Add");
    print "' name='action' title='";
    print pacsone_gettext("Add new Transcription Template");
    print "' onclick='switchText(this.form,\"actionvalue\",\"Add\")'></td>\n";
    print "</tr>\n";
    print "</table>\n";
	print "</form>\n";
    print "</td></tr>\n";
    print "</table><br>\n";
}

function displayRestartButton($preface)
{
    print "<table width=100% cellpadding=0 cellspacing=0 border=0>\n";
    print "<form method='POST' action='systemService.php' enctype='multipart/form-data'>\n";
    print "<input type='hidden' name='actionvalue'>\n";
    print "<tr><td>\n";
    print "<br>$preface\n";
    print "</td></tr>\n";
    print "<tr><td>&nbsp;</td></tr>\n";
    print "<tr><td><input type=submit name='action' value='";
    print pacsone_gettext("Restart");
    print "' title='";
    print pacsone_gettext("Restart Service");
    print "' onclick='switchText(this.form,\"actionvalue\",\"Restart\");return confirm(\"";
    print pacsone_gettext("Are you sure?");
    print "\");'></td>";
    print "</td></tr>\n";
    print "</form>\n";
    print "</table><br>\n";
}

function displayUserSignups($result, $preface)
{
    print "<table width=100% cellpadding=0 cellspacing=0 border=0>\n";
    print "<tr><td>\n";
    print "<br>$preface\n";
    print "</td></tr>\n";
    print "</table>\n";
    $records = $result->rowCount();
    $requests = array();
    while ($row = $result->fetch(PDO::FETCH_ASSOC))
        $requests[] = $row;
    // display all columns
    global $BGCOLOR;
    global $MYFONT;
    print "<table width=100% border=0 cellpadding=3>\n";
    print "<form method='POST' action='modifyUserSignup.php'>\n";
    print "<input type='hidden' name='actionvalue'>\n";
    $columns = array(
        "username"      => pacsone_gettext("Username"),
        "firstname"     => pacsone_gettext("First Name"),
        "lastname"      => pacsone_gettext("Last Name"),
        "email"         => pacsone_gettext("Email Address"),
        "submitted"     => pacsone_gettext("Sign-Up Request Submitted"),
    );
    print "<tr class=listhead bgcolor=$BGCOLOR>\n";
   	print "\t<td></td>\n";
    foreach ($columns as $column => $descr) {
        print "\t<td><b>";
        print pacsone_gettext($descr);
        print "</b></td>\n";
    }
    print "</tr>\n";
    foreach ($requests as $row) {
        print "<tr>\n";
		print "\t<td align=center width='1%'>\n";
		$user = $row["username"];
		print "\t\t<input type='checkbox' name='entry[]' value='$user'>";
		print "</td>\n";
        foreach ($columns as $key => $descr) {
            $value = $row[$key];
            if (isset($value)) {
                print "\t<td>";
                if (strcasecmp($key, "Email"))
                    print "$MYFONT$value</font>";
                else
                    print "<a href=\"mailto:$value\">$value</a>";
                print "</td>\n";
			}
            else
                print "\t<td>$MYFONT" . pacsone_gettext("N/A") . "</font></td>\n";
        }
        print "</tr>\n";
    }
    print "</table>\n";
    print "<p><table width=20% border=0 cellpadding=5>\n";
    print "<tr>\n";
    $check = pacsone_gettext("Check All");
    $uncheck = pacsone_gettext("Uncheck All");
    print "<td><input type=button value='$check' onClick='this.value=checkAll(this.form,\"entry\", \"$check\", \"$uncheck\")'></td>\n";
    print "<td><input type=submit value='";
    print pacsone_gettext("Approve");
    print "' name='action' title='";
    print pacsone_gettext("Approve Selected User Sign-Up Request");
    print "' onclick='switchText(this.form,\"actionvalue\",\"Approve\")'></td>\n";
    print "<td><input type=submit value='";
    print pacsone_gettext("Reject");
    print "' name='action' title='";
    print pacsone_gettext("Reject Selected User Sign-Up Request");
    print "' onclick='switchText(this.form,\"actionvalue\",\"Reject\")'></td>\n";
    print "<td><input type=submit value='";
    print pacsone_gettext("Delete");
    print "' name='action' title='";
    print pacsone_gettext("Delete Selected User Sign-Up Requests");
    print "' onclick='switchText(this.form,\"actionvalue\",\"Delete\");return confirm(\"";
    print pacsone_gettext("Are you sure?");
    print "\");'></td>\n";
    print "</tr>\n";
    print "</table>\n";
	print "</form>\n";
}

?>
