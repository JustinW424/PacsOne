<?php
//
// radiationDoseReport.php
//
// Module for parsing radiation dose structured reports (SR)
//
// CopyRight (c) 2018-2020 RainbowFish Software
//
if (!isset($argv))
    session_start();

include_once 'database.php';
include_once 'sharedData.php';
include_once 'dicom.php';
if (!isset($argv))
    include_once 'header.php';

// radiation dose related concept name codes that need to be parsed
$ACCUMULATED_CODES = array(
    "113722", "113725", "113726", "113727", "113728", "113729", "113730", "113780", "113812", "113813", "113855", 
);
$IRRADIATION_EVENT_CODES = array(
    "113733", "113734", "113735", "113736", "113738", "113846", "113847", "113750", "113770", "113793", "113823",
    "113824", "113825", "113826", "113827", "113833", "113897", "113898", "122130",
);

function insertIntoTable(&$dbcon, $table, &$fields)
{
    $error = "";
    $bindList = array();
    $sql = sprintf("insert into %s (", $table);
    $index = 1;
    foreach ($fields as $column => $value) {
        $sql .= $column;
        if ($index++ < count($fields))
            $sql .= ",";
    }
    $sql .= ") values(";
    $index = 1;
    foreach ($fields as $column => $value) {
        $sql .= "?";
        $bindList[] = $value;
        if ($index++ < count($fields))
            $sql .= ",";
    }
    $sql .= ")";
    if (!$dbcon->preparedStmt($sql, $bindList))
        $error = sprintf("Error running query [%s], error = %s", $sql, $dbcon->getError());
    return $error;
}

// main
$verbose = 0;
if (isset($argv) && count($argv)) {
    require_once "utils.php";
    $database = $argv[1];
    $username = $argv[2];
    $password = $argv[3];
    $aetitle = $argv[4];
    $path = $argv[5];
    $hostname = getDatabaseHost($aetitle);
    $dbcon = new MyDatabase($hostname, $database, $username, $password, $aetitle);
} else {
    if (!isset($_GET['path']))
        die("Full path to the radiation dose report must be specified!");
    $dbcon = new MyConnection();
    $path = urldecode($_GET['path']);
    if (isset($_GET['verbose']))
        $verbose = $_GET['verbose'];
}
if (!file_exists($path)) {
    print "<p>File [$path] does not exist!";
    exit();
}
if (!$dbcon->connection) {
    print "<p>Failed to connect to database [$database] as [$username]";
    exit();
}
print "<p>";
printf(pacsone_gettext("Started parsing radiation dose report [%s] on %s"), $path, date("r"));
print "<p>";
$report = new StructuredReport($path);
// parse study level radiation dose information
$root = $report->root;
// find the Study Instance UID
$studyUid = "";
$contexts = $root->obsContexts;
foreach ($contexts as $context) {
    if (!count($context->properties))
        continue;
    foreach ($context->properties as $property) {
        if (!strcasecmp($property->conceptNameCode, "Study Instance UID")) {
            $studyUid = $property->value;
            break;
        }
    }
}
if (strlen($studyUid) && count($root->contains)) {
    foreach ($root->contains as $item) {
        if ($verbose)
            printf("<p>item [%s]<br>", $item->conceptNameCode);
        $table = "";
        $fields = array();
        $eventUid = "";
        // search for Irradiation Event UID
        foreach ($item->contains as $subItem) {
            if (is_a($subItem, "UidItem")) {
                // parse the Irradiation Event UID
                if (!strcasecmp($subItem->conceptNameCode, "Irradiation Event UID")) {
                    $eventUid = $subItem->value;
                    break;
                }
            }
        }
        // look for Numeric sub-items
        foreach ($item->contains as $subItem) {
            if ($verbose)
                printf("\tSub-item [%s]<br>", $subItem->conceptNameCode);
            if (is_a($subItem, "NumericItem")) {
                $code = $subItem->code;
                $numeric = $subItem->value->getAttr(0x0040A30A);
                $seq = $subItem->value->getItem(0x004008EA);
                $unit = $seq->getAttr(0x00080100);
                $meaning = addslashes($seq->getAttr(0x00080104));
                $fields["code"] = sprintf("'%s'", $code);
                $fields["value"] = $numeric;
                $fields["meaning"] = sprintf("'%s'", addslashes($subItem->conceptNameCode));
                $fields["unit"] = sprintf("'%s'", $unit);
                if (in_array($code, $ACCUMULATED_CODES)) {
                    $table = "studydosereport";
                    $fields["uuid"] = sprintf("'%s'", $studyUid);
                    if (strlen($table) && count($fields)) {
                        $error = insertIntoTable($dbcon, $table, $fields);
                        if (strlen($error))
                            printf("<h2><font color=red>Table [%s] error: %s</font></h2>", $table, $error);
                    }
                } else if (in_array($code, $IRRADIATION_EVENT_CODES) && strlen($eventUid)) {
                    $table = "irradiationevent";
                    $fields["uuid"] = sprintf("'%s'", $eventUid);
                    $fields["studyuid"] = sprintf("'%s'", $studyUid);
                    if (strlen($table) && count($fields)) {
                        $error = insertIntoTable($dbcon, $table, $fields);
                        if (strlen($error))
                            printf("<h2><font color=red>Table [%s] error: %s</font></h2>", $table, $error);
                    }
                }
            }
        }
    }
}
print "<p>";
printf(pacsone_gettext("Finished parsing radiation dose report [%s] on %s"), $path, date("r"));
print "<br>";
if (!isset($argv))
    include_once 'footer.php';

?>
