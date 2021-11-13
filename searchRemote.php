<?php
//
// searchRemote.php
//
// Main page for searching remote Query/Retrieve SCP applications
//
// CopyRight (c) 2003-2017 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once "checkDate.js";

if (!isset($_REQUEST['aetitle']))
	die ("<font color=red>" . pacsone_gettext("A remote AE title must be selected for search operations") . "</font>");

$remote = $_REQUEST["aetitle"];

print "<html>\n";
print "<head>\n";
print "<title>" . pacsone_gettext("Search Remote PACS Database") . " - $remote</title>\n";
print "</head>\n";
print "<body>\n";
require_once 'header.php';

print "<P>";
printf(pacsone_gettext("Search Remote Application Entity: <b><u>%s</u>"), $remote);
print "</b><P>";
print "<ul type=disc>\n";
print "<li><form action='remotePatient.php' method=POST>\n";
print "<input type=hidden name='aetitle' value=$remote></input>\n";
print "<input class='btn btn-primary' type=submit value='";
global $CUSTOMIZE_PATIENTS;
printf(pacsone_gettext("List All %s"), $CUSTOMIZE_PATIENTS);
print "'>\n";
print "</form>\n";
print "<li><b>";
global $CUSTOMIZE_PATIENT;
printf(pacsone_gettext("Search By %s Attribute(s)"), $CUSTOMIZE_PATIENT) . "</b>\n";
print "<form action='remotePatient.php' method=POST>\n";
print "<input type=hidden name='aetitle' value=$remote></input>\n";
global $CUSTOMIZE_PATIENT_ID;
print "<P>$CUSTOMIZE_PATIENT_ID:\n";
print "<input type=text name='id'>\n";
print "<input class='btn btn-primary' type=submit value='";
print pacsone_gettext("Search") . "'></P>\n";
global $CUSTOMIZE_PATIENT_NAME;
print "<P>$CUSTOMIZE_PATIENT_NAME:\n";
print "<br>";
print pacsone_gettext("Lastname:");
print " <input type=text name='lastname'> ";
print pacsone_gettext("Firstname:");
print "<input type=text name='firstname'>\n";
print "<input class='btn btn-primary' type=submit value='";
print pacsone_gettext("Search") . "'></P>\n";
print "<P>" . pacsone_gettext("Institution Name:");
print "<input type=text name='instname'>\n";
print "<input class='btn btn-primary' type=submit value='";
print pacsone_gettext("Search") . "'></P>\n";
print "</form>\n";
print "<li><form action='remoteStudy.php' method=POST>\n";
print "<input type=hidden name='aetitle' value=$remote></input>\n";
print "<input class='btn btn-primary' type=submit value='";
print pacsone_gettext("List All Studies");
print "'>\n";
print "</form>\n";
print "<li><b>";
print pacsone_gettext("Search By Study Attribute(s)") . "</b>\n";
print "<form action='remoteStudy.php' method=POST>\n";
print "<input type=hidden name='aetitle' value=$remote></input>\n";
print "<P>" . pacsone_gettext("Study ID:") . "\n";
print "<input type=text name='id'>\n";
print "<input class='btn btn-primary' type=submit value='";
print pacsone_gettext("Search");
print "'></P>\n";
print "<P>" . pacsone_gettext("Accession Number:") . "\n";
print "<input type=text name='accession'>\n";
print "<input class='btn btn-primary' type=submit value='";
print pacsone_gettext("Search");
print "'></P>\n";
global $CUSTOMIZE_REFERRING_DOC;
print "<P>$CUSTOMIZE_REFERRING_DOC:\n";
print "<br>Last: <input type=text name='doclast'> \n";
print "First: <input type=text name='docfirst'> \n";
print "<input class='btn btn-primary' type=submit value='";
print pacsone_gettext("Search") . "'></P>\n";
print "<P>" . pacsone_gettext("Institution Name:");
print "<input type=text name='instname'>\n";
print "<input class='btn btn-primary' type=submit value='";
print pacsone_gettext("Search") . "'></P>\n";
print "</form>\n";
print "<form action='remoteStudy.php' onSubmit='return checkDate(this.date) && checkDate(this.from) && checkDate(this.to);' method=POST>\n";
print "<input type=hidden name='aetitle' value=$remote></input>\n";
print "<P>" . pacsone_gettext("Study Date:") . " \n";
print "<input class='btn btn-primary' type=submit value='";
print pacsone_gettext("Search");
print "'><br>\n";
print "<input type=radio name='datetype' value='1' checked>";
print pacsone_gettext("Today") . "</input><br>\n";
print "<input type=radio name='datetype' value='2'>";
print pacsone_gettext("Yesterday") . "</input><br>\n";
print "<input type=radio name='datetype' value='3'>";
print pacsone_gettext("On This Date:") . " </input>\n";
print "<input type=text name='date'> ";
print pacsone_gettext("(YYYY-MM-DD Format)") . "<br>\n";
print "<input type=radio name='datetype' value='4'>";
print pacsone_gettext("From:") . " </input>\n";
print "<input type=text name='from'>";
print pacsone_gettext(" (YYYY-MM-DD Format)") . "   </input>\n";
print pacsone_gettext("To: <input type=text name='to'> (YYYY-MM-DD Format)");
print "</input></P>\n";
print "</form>\n";
print "<li><b>" . pacsone_gettext("Search By Series Attribute(s)") . "</b>\n";
print "<form action='remoteSeries.php' method=POST>\n";
print "<input type=hidden name='aetitle' value=$remote></input>\n";
print "<P>" . pacsone_gettext("Modality:") . "\n";
print "<input type=text name='modality'>\n";
print "<input class='btn btn-primary' type=submit value='";
print pacsone_gettext("Search");
print "'></P>\n";
print "<P>" . pacsone_gettext("Institution Name:");
print "<input type=text name='instname'>\n";
print "<input class='btn btn-primary' type=submit value='";
print pacsone_gettext("Search") . "'></P>\n";
print "</form>\n";
print "<form action='remoteSeries.php' onSubmit='return checkDate(this.date) && checkDate(this.from) && checkDate(this.to);' method=POST>\n";
print "<input type=hidden name='aetitle' value=$remote></input>\n";
print "<P>" . pacsone_gettext("Series Date:") . " \n";
print "<input class='btn btn-primary' type=submit value='";
print pacsone_gettext("Search");
print "'><br>\n";
print "<input type=radio name='datetype' value='1' checked>";
print pacsone_gettext("Today") . "</input><br>\n";
print "<input type=radio name='datetype' value='2'>";
print pacsone_gettext("Yesterday") . "</input><br>\n";
print "<input type=radio name='datetype' value='3'>";
print pacsone_gettext("On This Date:") . " </input>\n";
print "<input type=text name='date'>";
print pacsone_gettext(" (YYYY-MM-DD Format)") . "<br>\n";
print "<input type=radio name='datetype' value='4'>";
print pacsone_gettext("From:") . " </input>\n";
print "<input type=text name='from'>";
print pacsone_gettext(" (YYYY-MM-DD Format)") . "   </input>\n";
print "To: <input type=text name='to'>";
print pacsone_gettext(" (YYYY-MM-DD Format)") . "</input></P>\n";
print "</form>\n";
print "</ul>\n";
require_once 'footer.php';
print "</body>\n";
print "</html>\n";

?>
