<?php
//
// searchRemoteWorklist.php
//
// Main page for searching remote Modality Worklist Management SCP applications
//
// CopyRight (c) 2004-2013 RainbowFish Software
//
session_start();

require_once 'locale.php';
include "checkDate.js";

if (!isset($_REQUEST['aetitle']))
	die ("<font color=red>" . pacsone_gettext("A remote AE title must be selected for search operations") . "</font>");

$remote = $_REQUEST["aetitle"];

print "<html>\n";
print "<head>\n";
print "<title>";
print pacsone_gettext("Search Remote Modality Worklist Database");
print " - $remote</title>\n";
print "</head>\n";
print "<body>\n";
require_once 'header.php';

print "<P>";
printf(pacsone_gettext("Search Remote Modality Worklist SCP: <i><b><u>%s</u></b></i>"), $remote);
print "<P>";
print "<form action='remoteWorklist.php' onSubmit='return checkDate(this.date) && checkDate(this.from) && checkDate(this.to) && checkTime(this.time) && checkTime(this.fromtime) && checkTime(this.totime);' method=POST>\n";
print "<input type=hidden name='aetitle' value=$remote></input>\n";
global $CUSTOMIZE_PATIENT_ID;
print "<P>" . sprintf(pacsone_gettext("Enter %s:"), $CUSTOMIZE_PATIENT_ID) . "\n";
print "<input type=text name='patientid'>\n";
print "<P>";
global $CUSTOMIZE_PATIENT_NAME;
printf(pacsone_gettext("Enter %s: (Wild-card characters '*' and '?' are supported)\n"), $CUSTOMIZE_PATIENT_NAME);
print "<br>";
print pacsone_gettext("Lastname:");
print " <input type=text name='lastname'>";
print pacsone_gettext(" Firstname:") . " <input type=text name='firstname'>\n";
print "<P>";
print pacsone_gettext("Enter Scheduled Station AE Title:") . "\n";
print "<input type=text name='station' size=16 maxlength=16>\n";
print "<P>" . pacsone_gettext("Enter Modality:") . "\n";
print "<input type=text name='modality' size=16 maxlength=16>\n";
print "<P>";
global $CUSTOMIZE_PERFORMING_DOC;
printf(pacsone_gettext("Enter Scheduled %s: (Wild-card characters '*' and '?' are supported)\n"), $CUSTOMIZE_PERFORMING_DOC);
print "<br>" . pacsone_gettext("Lastname:") . " <input type=text name='doclast'> \n";
print pacsone_gettext("Firstname:") . " <input type=text name='docfirst'> \n";
print "<P>";
print pacsone_gettext("Enter Scheduled Procedure Step Start Date:") . "<br>\n";
print "<input type=radio name='datetype' value='0' checked>";
print pacsone_gettext("Any date") . "</input><br>\n";
print "<input type=radio name='datetype' value='1'>";
print pacsone_gettext("Today") . "</input><br>\n";
print "<input type=radio name='datetype' value='2'>";
print pacsone_gettext("Yesterday") . "</input><br>\n";
print "<input type=radio name='datetype' value='3'>";
print pacsone_gettext("On This Date:") . " </input>\n";
print "<input type=text name='date'>" . pacsone_gettext(" (YYYY-MM-DD Format)") . "<br>\n";
print "<input type=radio name='datetype' value='4'>";
print pacsone_gettext("From:") . " </input>\n";
print "<input type=text name='from'>";
print pacsone_gettext(" (YYYY-MM-DD Format)") . "   </input>\n";
print pacsone_gettext("To:") . " <input type=text name='to'>";
print pacsone_gettext(" (YYYY-MM-DD Format)") . "</input></P>\n";
print "<P>" . pacsone_gettext("Enter Scheduled Procedure Step Start Time:") . "<br>\n";
print "<input type=radio name='timetype' value='1' checked>";
print pacsone_gettext("Anytime") . "</input><br>\n";
print "<input type=radio name='timetype' value='2'>";
print pacsone_gettext("At This Time:") . " </input>\n";
print "<input type=text name='time'>";
print pacsone_gettext(" (HH:MM 24-Hour Clock Format)") . "<br>\n";
print "<input type=radio name='timetype' value='3'>";
print pacsone_gettext("From:") . " </input>\n";
print "<input type=text name='fromtime'>";
print pacsone_gettext(" (HH:MM 24-Hour Clock Format)") . "   </input>\n";
print "To: <input type=text name='totime'>";
print pacsone_gettext(" (HH:MM 24-Hour Clock Format)") . "</input></P>\n";
print "<input type=submit value='";
print pacsone_gettext("Find Modality Worklist");
print "'>\n";
print "</form>\n";
require_once 'footer.php';
print "</body>\n";
print "</html>\n";

?>
