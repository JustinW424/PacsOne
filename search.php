<?php
//
// search.php
//
// Main page for local database search
//
// CopyRight (c) 2003-2015 RainbowFish Software
//
session_start();

require_once "locale.php";
include_once "security.php";
include_once "checkDate.js";
include_once "sharedData.php";
include_once 'tabbedpage.php';

class SearchPatientPage extends TabbedPage {
	var $title;
	var $url;
	var $eurodate;

    function __construct($eurodate) {
        global $CUSTIMIZE_SEARCH_BY_PATIENT;
        $this->title = $CUSTIMIZE_SEARCH_BY_PATIENT;
        $this->url = "search.php?page=" . urlencode($this->title);
        $this->eurodate = $eurodate;
    }
    function __destruct() { }
    function showHtml() {
        print "<form action='searchPatient.php' onSubmit='return checkDate(this.fromdate) && checkDate(this.todate);' method='POST'>\n";
        global $CUSTOMIZE_PATIENT_ID;
        print "<P>$CUSTOMIZE_PATIENT_ID:\n";
        print "<input type='text' name='id'>\n";
        print "<input type='checkbox' name='wildid' value=1 checked>";
        print pacsone_gettext("Append wild-card character '<b>*</b>' to search pattern");
        print "<br>\n";
        global $CUSTOMIZE_PATIENT_NAME;
        if (!strcasecmp($CUSTOMIZE_PATIENT_NAME, pacsone_gettext("Patient Name"))) {
            print "<P>$CUSTOMIZE_PATIENT_NAME:\n";
            print "<br>" . pacsone_gettext("Lastname: ");
            print "<input type='text' name='lastname'>";
            print pacsone_gettext(" Firstname: ");
            print "<input type='text' name='firstname'>\n";
            print "<input type='checkbox' name='wildname' value=1 checked>";
            print pacsone_gettext("Append wild-card character '<b>*</b>' to search pattern");
            //print "<br>\n";
            print "<div style='height:0px;'></div>\n";
            print pacsone_gettext("Full Name Search (ignore order of first, middle and last names):");
            print "<input type='text' name='fullname' size=64 maxlength=128>\n";
        }
        global $CUSTOMIZE_PATIENT_DOB;
        print "<P>$CUSTOMIZE_PATIENT_DOB:<br>\n";
        print "<select name='compare'>\n";
        print "<option>" . pacsone_gettext("Equal") . "\n";
        print "<option>" . pacsone_gettext("Before") . "\n";
        print "<option>" . pacsone_gettext("After") . "\n";
        print "<option selected>" . pacsone_gettext("From") . "\n";
        print "</select>\n";
        $format = (!$this->eurodate)? pacsone_gettext("(YYYY-MM-DD Format)") : pacsone_gettext("(DD-MM-YYYY Format)");
        print "<input type='text' name='fromdate'> $format";
        print "<br>\n";
        print "<select name='till'>\n";
        print "<option>" . pacsone_gettext("To") . "\n";
        print "</select>\n";
        print "<input style='margin-left:27px;' type='text' name='todate'>";
        print "<P>" . pacsone_gettext("Institution Name:") . "\n";
        print "&nbsp;<input type='text' name='institution' size=32 maxlength=64>";
        print "<P>" . pacsone_gettext("Study Date:") . "<br>\n";
        print "<select name='studycompare'>\n";
        print "<option>" . pacsone_gettext("Equal") . "\n";
        print "<option>" . pacsone_gettext("Before") . "\n";
        print "<option>" . pacsone_gettext("After") . "\n";
        print "<option selected>" . pacsone_gettext("From") . "\n";
        print "</select>\n";
        print "<input type='text' name='studyfromdate'> $format";
        print "<br><select name='till'>\n";
        print "<option>" . pacsone_gettext("To") . "\n";
        print "</select>\n";
        print "<input style='margin-left:27px;' type='text' name='studytodate'> $format";
        global $CUSTIMIZE_SEARCH_BY_PATIENT;
        print "<P><input class='btn btn-primary' type='submit' value='$CUSTIMIZE_SEARCH_BY_PATIENT'></P>\n";
        print "</form>\n";
    }
}

class SearchStudyPage extends TabbedPage {
	var $title;
	var $url;
	var $eurodate;

    function __construct($eurodate) {
        $this->title = pacsone_gettext("Search By Study");
        $this->url = "search.php?page=" . urlencode($this->title);
        $this->eurodate = $eurodate;
    }
    function __destruct() { }
    function showHtml() {
        print "<form action='searchStudy.php' onSubmit='return checkDate(this.fromdate) && checkDate(this.todate);' method='POST'>\n";
        global $CUSTOMIZE_PATIENT_ID;
        print "<P>$CUSTOMIZE_PATIENT_ID:\n";
        print "<input style='margin-left:78px;' type='text' name='patientid'>\n";
        print "<input type='checkbox' name='wildid' value=1 checked>";
        print pacsone_gettext("Append wild-card character '<b>*</b>' to search pattern");
        global $CUSTOMIZE_PATIENT_NAME;
        print "<P>$CUSTOMIZE_PATIENT_NAME:\n";
        print "<br>" . pacsone_gettext("Lastname: ");
        print "<input style='margin-left:78px;' type='text' name='lastname'>";
        print pacsone_gettext(" Firstname: ");
        print "<input type='text' name='firstname'>\n";
        print "<input type='checkbox' name='wildname' value=1 checked>";
        print pacsone_gettext("Append wild-card character '<b>*</b>' to search pattern");
        print "<P>" . pacsone_gettext("Study Unique ID (UID):") . "\n";
        print "<input type='text' name='uid'>\n";
        print "<P>" . pacsone_gettext("Study ID:") . "\n";
        print "<input style='margin-left:86px;' type='text' name='id'>\n";
        $today = (!$this->eurodate)? date("Y-m-d") : date("d-m-Y");
        print "<P>" . pacsone_gettext("Study Date:") . "<br>\n";
        print "<select name='compare'>\n";
        print "<option>" . pacsone_gettext("Equal") . "\n";
        print "<option>" . pacsone_gettext("Before") . "\n";
        print "<option>" . pacsone_gettext("After") . "\n";
        print "<option selected>" . pacsone_gettext("From") . "\n";
        print "</select>\n";
        $format = (!$this->eurodate)? pacsone_gettext("(YYYY-MM-DD Format)") : pacsone_gettext("(DD-MM-YYYY Format)");
        print "<input type='text' name='fromdate'> $format";
        print "<br><select name='till'>\n";
        print "<option>" . pacsone_gettext("To") . "\n";
        print "</select>\n";
        print "<input style='margin-left:27px;' type='text' name='todate'> $format";
        print "<P>" . pacsone_gettext("Accession Number:") . "\n";
        print "<input style='margin-left:77px;' type='text' name='accession'></P>\n";
        global $CUSTOMIZE_REFERRING_DOC;
        print "<P>$CUSTOMIZE_REFERRING_DOC:\n";
        print "<input style='margin-left:21px;' type='text' name='referdoc'></P>\n";
        global $CUSTOMIZE_READING_DOC;
        print "<P>$CUSTOMIZE_READING_DOC:\n";
        print "<input style='margin-left:26px;' type='text' name='readingdoc'></P>\n";
        print "<P>" . pacsone_gettext("Source AE:") . "\n";
        print "<input style='margin-left:127px;' type='text' name='sourceae' maxlength=16></P>\n";
        print "<P>" . pacsone_gettext("Modalities:") . "\n";
        print "<input style='margin-left:130px;' type='text' name='modalities'></P>\n";
        print "<P>" . pacsone_gettext("Date When Study Was Received:") . "<br>\n";
        print "<select name='rcompare'>\n";
        print "<option>" . pacsone_gettext("Equal") . "\n";
        print "<option>" . pacsone_gettext("Before") . "\n";
        print "<option>" . pacsone_gettext("After") . "\n";
        print "<option selected>" . pacsone_gettext("From") . "\n";
        print "</select>\n";
        print "<input type='text' name='rfromdate'> $format";
        print "<br><select name='rtill'>\n";
        print "<option>" . pacsone_gettext("To") . "\n";
        print "</select>\n";
        print "<input style='margin-left:27px;' type='text' name='rtodate'> $format";
        print "<P>" . pacsone_gettext("Study Description:") . "\n";
        print "<input type='text' name='description' size=32 maxlength=64></P>\n";
        print "<P><input class='btn btn-primary' type='submit' value='";
        print pacsone_gettext("Search By Study");
        print "'></P>\n";
        print "</form>\n";
        print "<hr>\n";
        print "<li><b>" . pacsone_gettext("Search By Study Notes") . "</b>\n";
        print "<form action='searchStudyNotes.php' onSubmit='return checkDate(this.fromdate) && checkDate(this.todate);' method='POST'>\n";
        print "<P>" . pacsone_gettext("Entered By User:") . "\n";
        print "<input type='text' name='author'>\n";
        print "<P>" . pacsone_gettext("Subject:") . "\n";
        print "<input style='margin-left:57px;' type='text' name='subject'>\n";
        print "<P>" . pacsone_gettext("Date:") . "<br>\n";
        print "<select name='compare'>\n";
        print "<option>" . pacsone_gettext("Equal") . "\n";
        print "<option>" . pacsone_gettext("Before") . "\n";
        print "<option>" . pacsone_gettext("After") . "\n";
        print "<option selected>" . pacsone_gettext("From") . "\n";
        print "</select>\n";
        print "<input type='text' name='fromdate' value='$today'> $format";
        print "<br><select name='till'>\n";
        print "<option>" . pacsone_gettext("To") . "\n";
        print "</select>\n";
        print "<input style='margin-left:27px;' type='text' name='todate'> $format";
        print "<P><input class='btn btn-primary' type='submit' value='";
        print pacsone_gettext("Search By Study Notes");
        print "'></P>\n";
        print "</form>\n";
        print "<hr>\n";
        print "<li><b>" . pacsone_gettext("Search Exported Studies") . "</b>\n";
        print "<form action='searchExport.php' onSubmit='return checkDate(this.fromdate) && checkDate(this.todate);' method='POST'>\n";
        print "<P>" . pacsone_gettext("Study Unique ID (UID):") . "\n";
        print "<input type='text' name='uid'>\n";
        print "<P>" . pacsone_gettext("Study ID:") . "\n";
        print "<input style='margin-left:86px;' type='text' name='id'>\n";
        print "<P>" . pacsone_gettext("Study Date:") . "<br>\n";
        print "<select name='compare'>\n";
        print "<option>" . pacsone_gettext("Equal") . "\n";
        print "<option>" . pacsone_gettext("Before") . "\n";
        print "<option>" . pacsone_gettext("After") . "\n";
        print "<option selected>" . pacsone_gettext("From") . "\n";
        print "</select>\n";
        print "<input type='text' name='fromdate' value='$today'> $format";
        print "<br><select name='till'>\n";
        print "<option>" . pacsone_gettext("To") . "\n";
        print "</select>\n";
        print "<input style='margin-left:27px;' type='text' name='todate'> $format";
        print "<P>" . pacsone_gettext("Export Date:") . "<br>\n";
        print "<select name='exportcompare'>\n";
        print "<option>" . pacsone_gettext("Equal") . "\n";
        print "<option>" . pacsone_gettext("Before") . "\n";
        print "<option>" . pacsone_gettext("After") . "\n";
        print "<option selected>" . pacsone_gettext("From") . "\n";
        print "</select>\n";
        print "<input type='text' name='exportfromdate'> $format";
        print "<br><select name='till'>\n";
        print "<option>" . pacsone_gettext("To") . "\n";
        print "</select>\n";
        print "<input style='margin-left:27px;' type='text' name='exporttodate'> $format";
        print "<P><input class='btn btn-primary' type='submit' value='";
        print pacsone_gettext("Search Exported Studies") . "'></P>\n";
        print "</form>\n";
    }
}

class SearchSeriesPage extends TabbedPage {
	var $title;
	var $url;
	var $eurodate;

    function __construct($eurodate) {
        $this->title = pacsone_gettext("Search By Series");
        $this->url = "search.php?page=" . urlencode($this->title);
        $this->eurodate = $eurodate;
    }
    function __destruct() { }
    function showHtml() {
        print "<form action='searchSeries.php' onSubmit='return checkDate(this.fromdate) && checkDate(this.todate);' method='POST'>\n";
        print "<P>" . pacsone_gettext("Series Unique ID (UID):") . "\n";
        print "<input type='text' name='uid'>\n";
        $today = (!$this->eurodate)? date("Y-m-d") : date("d-m-Y");
        print "<P>" . pacsone_gettext("Series Date:") . "<br>\n";
        print "<select name='compare'>\n";
        print "<option>" . pacsone_gettext("Equal") . "\n";
        print "<option>" . pacsone_gettext("Before") . "\n";
        print "<option>" . pacsone_gettext("After") . "\n";
        print "<option selected>" . pacsone_gettext("From") . "\n";
        print "</select>\n";
        $format = (!$this->eurodate)? pacsone_gettext("(YYYY-MM-DD Format)") : pacsone_gettext("(DD-MM-YYYY Format)");
        print "<input type='text' name='fromdate' value='$today'> $format";
        print "<br><select name='till'>\n";
        print "<option>" . pacsone_gettext("To") . "\n";
        print "</select>\n";
        print "<input style='margin-left:27px;' type='text' name='todate'> $format";
        print "<P>" . pacsone_gettext("Modality:") . "\n";
        print "<input style='margin-left:91px;' type='text' name='modality'></P>\n";
        print "<P>" . pacsone_gettext("Series Description:") . "\n";
        print "<input style='margin-left:30px;' type='text' name='description'></P>\n";
        print "<P><input class='btn btn-primary' type='submit' value='";
        print pacsone_gettext("Search Series") . "'></P>\n";
        print "</form>\n";
    }
}

class SearchImagePage extends TabbedPage {
	var $title;
	var $url;
	var $eurodate;

    function __construct($eurodate) {
        $this->title = pacsone_gettext("Search By Image");
        $this->url = "search.php?page=" . urlencode($this->title);
        $this->eurodate = $eurodate;
    }
    function __destruct() { }
    function showHtml() {
        print "<form action='searchImage.php' onSubmit='return checkDate(this.fromdate) && checkDate(this.todate);' method='POST'>\n";
        print "<P>" . pacsone_gettext("Image Unique ID (UID):") . "\n";
        print "<input style='margin-left:214px;' type='text' name='uid'>\n";
        $today = (!$this->eurodate)? date("Y-m-d") : date("d-m-Y");
        print "<P>" . pacsone_gettext("Photometric Interpretation (MONOCHROME1, RGB, etc.):") . "\n";
        print "<input type='text' name='photometric'>\n";
        print "<P>" . pacsone_gettext("Image Date:") . "<br>\n";
        print "<select name='compare'>\n";
        print "<option>" . pacsone_gettext("Equal") . "\n";
        print "<option>" . pacsone_gettext("Before") . "\n";
        print "<option>" . pacsone_gettext("After") . "\n";
        print "<option selected>" . pacsone_gettext("From") . "\n";
        print "</select>\n";
        $format = (!$this->eurodate)? pacsone_gettext("(YYYY-MM-DD Format)") : pacsone_gettext("(DD-MM-YYYY Format)");
        print "<input type='text' name='fromdate' value='$today'> $format";
        print "<br><select name='till'>\n";
        print "<option>" . pacsone_gettext("To") . "\n";
        print "</select>\n";
        print "<input style='margin-left:27px;' type='text' name='todate'> $format";
        print "<P><input class='btn btn-primary' type='submit' value='";
        print pacsone_gettext("Search") . "'></P>\n";
        print "</form>\n";
        print "<hr>\n";
        print "<li><b>" . pacsone_gettext("Search By Image Notes") . "</b>\n";
        print "<form action='searchImageNotes.php' onSubmit='return checkDate(this.fromdate) && checkDate(this.todate);' method='POST'>\n";
        print "<P>" . pacsone_gettext("Entered By User:") . "\n";
        print "<input type='text' name='author'>\n";
        print "<P>" . pacsone_gettext("Subject:") . "\n";
        print "<input style='margin-left:57px;' type='text' name='subject'>\n";
        print "<P>" . pacsone_gettext("Date:") . "<br>\n";
        print "<select name='compare'>\n";
        print "<option>" . pacsone_gettext("Equal") . "\n";
        print "<option>" . pacsone_gettext("Before") . "\n";
        print "<option>" . pacsone_gettext("After") . "\n";
        print "<option selected>" . pacsone_gettext("From") . "\n";
        print "</select>\n";
        print "<input type='text' name='fromdate' value='$today'> $format";
        print "<br><select name='till'>\n";
        print "<option>" . pacsone_gettext("To") . "\n";
        print "</select>\n";
        print "<input style='margin-left:27px;' type='text' name='todate'> $format";
        print "<P><input class='btn btn-primary' type='submit' value='";
        print pacsone_gettext("Search By Image Notes") . "'></P>\n";
        print "</form>\n";
    }
}

class SearchWorklistPage extends TabbedPage {
	var $title;
	var $url;
	var $eurodate;

    function __construct($eurodate) {
        $this->title = pacsone_gettext("Search Modality Worklist");
        $this->url = "search.php?page=" . urlencode($this->title);
        $this->eurodate = $eurodate;
    }
    function __destruct() { }
    function showHtml() {
        print "<form action='searchWorklist.php' onSubmit='return checkDate(this.fromdate) && checkDate(this.todate);' method='POST'>\n";
        print "<P>" . pacsone_gettext("Study Unique ID (UID):") . "\n";
        print "<input type='text' name='studyuid'>\n";
        global $CUSTOMIZE_PATIENT_ID;
        print "<P>$CUSTOMIZE_PATIENT_ID:\n";
        print "<input style='margin-left:78px;' type='text' name='patientid'>\n";
        global $CUSTOMIZE_PATIENT_NAME;
        print "<P>$CUSTOMIZE_PATIENT_NAME:\n";
        print "<input style='margin-left:54px;' type='text' name='patientname'>\n";
        print "<P>" . pacsone_gettext("Accession Number:") . "\n";
        print "<input style='margin-left:22px;' type='text' name='accession'></P>\n";
        print "<P>" . pacsone_gettext("Modality:") . "\n";
        print "<input style='margin-left:87px;' type='text' name='modality'></P>\n";
        print "<P>" . pacsone_gettext("Scheduled AE:") . "\n";
        print "<input style='margin-left:50px;' type='text' name='scheduledae' maxlength=16></P>\n";
        $today = (!$this->eurodate)? date("Y-m-d") : date("d-m-Y");
        print "<P>" . pacsone_gettext("Date of Service:") . "<br>\n";
        print "<select name='compare'>\n";
        print "<option>" . pacsone_gettext("Equal") . "\n";
        print "<option>" . pacsone_gettext("Before") . "\n";
        print "<option>" . pacsone_gettext("After") . "\n";
        print "<option selected>" . pacsone_gettext("From") . "\n";
        print "</select>\n";
        $format = (!$this->eurodate)? pacsone_gettext("(YYYY-MM-DD Format)") : pacsone_gettext("(DD-MM-YYYY Format)");
        print "<input type='text' name='fromdate' value='$today'> $format";
        print "<br><select name='till'>\n";
        print "<option>" . pacsone_gettext("To") . "\n";
        print "</select>\n";
        print "<input style='margin-left:27px;' type='text' name='todate'> $format";
        global $CUSTOMIZE_REFERRING_DOC;
        print "<P>$CUSTOMIZE_REFERRING_DOC:\n";
        print "<input style='margin-left:13px;' type='text' name='referdoc'></P>\n";
        global $CUSTOMIZE_REQUESTING_DOC;
        print "<P>$CUSTOMIZE_REQUESTING_DOC:\n";
        print "<input type='text' name='requestdoc'></P>\n";
        print "<P><input class='btn btn-primary' type='submit' value='";
        print pacsone_gettext("Search Modality Worklist");
        print "'></P>\n";
        print "</form>\n";
    }
}

class SearchUserPage extends TabbedPage {
	var $title;
	var $url;

    function __construct() {
        $this->title = pacsone_gettext("Search Registered User");
        $this->url = "search.php?page=" . urlencode($this->title);
    }
    function __destruct() { }
    function showHtml() {
        print "<form action='searchUser.php' onSubmit='return checkDate(this.fromdate) && checkDate(this.todate);' method='POST'>\n";
        print "<P>" . pacsone_gettext("Registered Username:") . "\n";
        print "<input type='text' name='username'>\n";
        print "<input type='checkbox' name='wilduser' value=1 checked>";
        print pacsone_gettext("Append wild-card character '<b>*</b>' to search pattern");
        print "<P>" . pacsone_gettext("Lastname:");
        print "<input style='margin-left:79px;' type='text' name='lastname'>\n";
        print "<input type='checkbox' name='wildlast' value=1 checked>";
        print pacsone_gettext("Append wild-card character '<b>*</b>' to search pattern");
        print "<P>" . pacsone_gettext("Firstname:") . "\n";
        print "<input style='margin-left:74px;' type='text' name='firstname'>\n";
        print "<input type='checkbox' name='wildfirst' value=1 checked>";
        print pacsone_gettext("Append wild-card character '<b>*</b>' to search pattern");
        print "<P>" . pacsone_gettext("Email Address:") . "\n";
        print "<input style='margin-left:47px;' type='text' name='email'>\n";
        print "<input type='checkbox' name='wildemail' value=1 checked>";
        print pacsone_gettext("Append wild-card character '<b>*</b>' to search pattern");
        print "<P><input class='btn btn-primary' type='submit' value='";
        print pacsone_gettext("Search Registered User");
        print "'></P>\n";
        print "</form>\n";
    }
}

class SearchDicomAePage extends TabbedPage {
	var $title;
	var $url;

    function __construct() {
        $this->title = pacsone_gettext("Search Dicom AE");
        $this->url = "search.php?page=" . urlencode($this->title);
    }
    function __destruct() { }
    function showHtml() {
        print "<form action='searchDicomAe.php' onSubmit='return checkDate(this.fromdate) && checkDate(this.todate);' method='POST'>\n";
        print "<P>" . pacsone_gettext("AE Title:") . "\n";
        print "<input style='margin-left:31px;' type='text' name='aetitle'>\n";
        print "<input type='checkbox' name='wildtitle' value=1 checked>";
        print pacsone_gettext("Append wild-card character '<b>*</b>' to search pattern");
        print "<P>" . pacsone_gettext("Description:");
        print "<input style='margin-left:13px;'type='text' name='description'>\n";
        print "<input type='checkbox' name='wilddesc' value=1 checked>";
        print pacsone_gettext("Append wild-card character '<b>*</b>' to search pattern");
        print "<P>" . pacsone_gettext("Hostname:") . "\n";
        print "<input style='margin-left:15px;' type='text' name='hostname'>\n";
        print "<input type='checkbox' name='wildhost' value=1 checked>";
        print pacsone_gettext("Append wild-card character '<b>*</b>' to search pattern");
        print "<P>" . pacsone_gettext("IP Address:") . "\n";
        print "<input style='margin-left:12px;' type='text' name='ipaddr'>\n";
        print "<input type='checkbox' name='wildip' value=1 checked>";
        print pacsone_gettext("Append wild-card character '<b>*</b>' to search pattern");
        print "<P>" . pacsone_gettext("Port Number:") . "\n";
        print "<input type='text' name='port'>\n";
        print "<input type='checkbox' name='wildport' value=0>";
        print pacsone_gettext("Append wild-card character '<b>*</b>' to search pattern");
        print "<P><input class='btn btn-primary' type='submit' value='";
        print pacsone_gettext("Search Dicom AE");
        print "'></P>\n";
        print "</form>\n";
    }
}

global $PRODUCT;
print "<html>";
print "<head>";
print "<title>";
printf(pacsone_gettext("Search Local %s Database"), $PRODUCT);
print "</title>";
print "</head>";
print "<body>";
require_once 'header.php';

$dbcon = new MyConnection();
$eurodate = $dbcon->isEuropeanDateFormat();
$default = new SearchPatientPage($eurodate);
$current = $default->title;
$pages = array(
    $default,
    (new SearchStudyPage($eurodate)),
    (new SearchSeriesPage($eurodate)),
    (new SearchImagePage($eurodate)),
    (new SearchWorklistPage($eurodate)),
);
if ($dbcon->isAdministrator($dbcon->username)) {
    $pages[] = new SearchUserPage();
    $pages[] = new SearchDicomAePage();
}
if (isset($_REQUEST['page']))
    $current = $_REQUEST['page'];
$tabs = new Tabs($pages, $current);
$tabs->showHtml();

require_once 'footer.php';

print "</body>";
print "</html>";
?>
