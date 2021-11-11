<?php
//
// modifyEmail.php
//
// Module for modifying SMTP server configurations
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once 'locale.php';
include_once 'database.php';
include_once 'sharedData.php';

// authentication mechanism table
$AUTH_TBL = array ("LOGIN", "PLAIN", "CRAM-MD5");

// main
global $PRODUCT;
$dbcon = new MyConnection();
$username = $dbcon->username;
if (!$dbcon->hasaccess("admin", $username)) {
    print "<h3><font color=red>";
    print pacsone_gettext("You must have the Admin privilege in order to access this page.");
    print "</font></h3>";
    exit();
}
if (isset($_POST['server']))
    $server = $_POST['server'];
if (isset($_POST['action']))
   	$action = $_POST['actionvalue'];
if (isset($_POST['entry']))
   	$entry = $_POST['entry'];
$result = NULL;
if (isset($_GET['server'])) {
    modifyEntryForm($_GET['server']);
}
else {
    if (isset($action) && strcasecmp($action, "Delete") == 0) {
        $result = deleteEntries($entry);
    }
    else if (isset($action) && strcasecmp($action, "Add") == 0) {
        if (isset($server)) {
            $result = addEntry($server);
        }
        else {
            addEntryForm();
        }
    }
    else if (isset($action) && strcasecmp($action, "Modify") == 0) {
        $result = modifyEntry($server);
    }
}
if (isset($result)) {       // display results
    if (empty($result)) {   // success
        header('Location: email.php');
    }
    else {                  // error
        print "<html>\n";
        print "<head><title>$PRODUCT - ";
        print pacsone_gettext("SMTP Server Configuration Error");
        print "</title></head>\n";
        print "<body>\n";
        require_once 'header.php';
        print $result;
        require_once 'footer.php';
        print "</body>\n";
        print "</html>\n";
    }
}

function deleteEntries($entry)
{
    global $dbcon;
    $ok = array();
    $errors = array();
    foreach ($entry as $value) {
        $query = "delete from smtp where hostname=?";
        $bindList = array($value);
        if (!$dbcon->preparedStmt($query, $bindList)) {
            $errors[$value] = sprintf(pacsone_gettext("Database Error: %s"), $dbcon->getError());
            continue;
        }
        $ok[] = $value;
    }
    $result = "";
    if (!empty($errors)) {
        if (count($errors) == 1)
            $result = pacsone_gettext("Error deleting the following SMTP Server:");
        else
            $result = pacsone_gettext("Error deleting the following SMTP Servers:");
        $result .= "<P>\n";
        foreach ($errors as $key => $value) {
            $result .= "<h3><font color=red><u>$key</u> - $value</font></h3><P>\n";
        }
    }
    return $result;
}

function addEntry(&$server)
{
    global $dbcon;
    require_once "deencrypt.php";
    $result = "";
    $columns = array();
    $bindList = array();
    $query = "insert into smtp (";
    $columns[] = "hostname";
    $values = "?";
    $bindList[] = $server;
    $columns[] = "description";
    if (isset($_POST['description']) && strlen($_POST['description'])) {
        $values .= ",?";
        $bindList[] = $_POST['description'];
    } else
        $values .= ",NULL";
    $columns[] = "port";
    if (isset($_POST['port']) && strlen($_POST['port'])) {
        $values .= ",?";
        $bindList[] = $_POST['port'];
    } else
        $values .= ",NULL";
    $columns[] = "myemail";
    if (isset($_POST['myemail']) && strlen($_POST['myemail'])) {
        $values .= ",?";
        $bindList[] = $_POST['myemail'];
    }
    else
        $values .= ",NULL";
    if (isset($_POST['myname']) && strlen($_POST['myname'])) {
        $columns[] = "myname";
        $values .= ",?";
        $bindList[] = $_POST['myname'];
    }
    if ($_POST['encryption'] && !extension_loaded('openssl')) {
        $result .= "<h3><font color=red>";
        $result .= pacsone_gettext("OpenSSL extension is required for TLS/SSL encryption. Please enable it in your PHP.INI configuration file and try again.");
        $result .= "</font></h3><p>\n";
        return $result;
    }
    $columns[] = "encryption";
    $values .= ",?";
    $bindList[] = $_POST['encryption'];
    if (isset($_POST['timeout'])) {
        $columns[] = "timeout";
        $values .= ",?";
        $bindList[] = $_POST['timeout'];
    }
    if (isset($_POST['auth'])) {
        $auth = $_POST['auth'];
        if ($auth) {
            $encrypt = new DeEncrypt();
            $columns[] = "mechanism";
            $values .= ",?";
            $bindList[] = $_POST['mechanism'];
            $columns[] = "username";
            $values .= ",?";
            $bindList[] = $encrypt->encrypt($_POST['username']);
            $columns[] = "password";
            $values .= ",?";
            $bindList[] = $encrypt->encrypt($_POST['password']);
            if ($auth == 2) {
                $columns[] = "ntlmhost";
                $values .= ",?";
                $bindList[] = $_POST['ntlmhost'];
            }
        }
    }
    for ($i = 0; $i < count($columns); $i++) {
        if ($i)
            $query .= ",";
        $query .= $columns[$i];
    }
    $query .= ") values($values)";
    // execute SQL query
    if (!$dbcon->preparedStmt($query, $bindList)) {
        $result .= "<h3><font color=red>";
        $result .= sprintf(pacsone_gettext("Error adding SMTP Server <u>%s</u>: "), $server);
        $result .= $dbcon->getError();
        $result .= "</font></h3><p>\n";
    }
    return $result;
}

function modifyEntry(&$server)
{
    global $dbcon;
    require_once "deencrypt.php";
    $result = "";
    $query = "update smtp set ";
    $bindList = array();
    $description = $_POST['description'];
    if ($description) {
        $query .= "description=?,";
        $bindList[] = $description;
    }
    $port = $_POST['port'];
    if ($port) {
        $query .= "port=?,";
        $bindList[] = $port;
    } else
        $query .= "port=NULL,";
    $myemail = $_POST['myemail'];
    if ($myemail) {
        $query .= "myemail=?,";
        $bindList[] = $myemail;
    }
    else
        $query .= "myemail=NULL,";
    if (isset($_POST['myname'])) {
        $query .= "myname=?,";
        $bindList[] = $_POST['myname'];
    }
    else
        $query .= "myname='',";
    if ($_POST['encryption'] && !extension_loaded('openssl')) {
        $result .= "<h3><font color=red>";
        $result .= pacsone_gettext("OpenSSL extension is required for TLS/SSL encryption. Please enable it in your PHP.INI configuration file and try again.");
        $result .= "</font></h3><p>\n";
        return $result;
    }
    $query .= "encryption=?,";
    $bindList[] = $_POST['encryption'];
    if (isset($_POST['timeout'])) {
        $query .= "timeout=?,";
        $bindList[] = $_POST['timeout'];
    }
    $auth = $_POST['auth'];
    if ($auth) {
        $encrypt = new DeEncrypt();
        $query .= "mechanism=?,";
        $bindList[] = $_POST['mechanism'];
        $query .= "username=?,";
        $bindList[] = $encrypt->encrypt($_POST['username']);
        $query .= "password=?,";
        $bindList[] = $encrypt->encrypt($_POST['password']);
        if ($auth == 2) {
            $query .= "mechanism='NTLM',ntlmhost=?,";
            $bindList[] = $_POST['ntlmhost'];
        } else
            $query .= "ntlmhost=NULL,";
    } else {
        $query .= "mechanism=NULL,";
        $query .= "username=NULL,";
        $query .= "password=NULL,";
        $query .= "pop3host=NULL,";
        $query .= "ntlmhost=NULL,";
    }
    // replace the last ',' with ';'
    $npos = strrpos($query, ",");
    if ($npos != false)
        $query = substr($query, 0, $npos);
    $query .= " where hostname=?";
    $bindList[] = $server;
    // execute SQL query
    if (!$dbcon->preparedStmt($query, $bindList)) {
        $result .= "<h3><font color=red>";
        $result .= sprintf(pacsone_gettext("Error updating SMTP Server <u>%s</u>: "), $server);
        $result .= $dbcon->getError();
        $result .= "</font></h3><p>\n";
    }
    return $result;
}

function addEntryForm()
{
    include_once 'checkUncheck.js';
    global $PRODUCT;
    global $AUTH_TBL;
    global $SMTP_DEFAULT_TIMEOUT;
    global $SMTP_SECURE_NONE;
    global $SMTP_SECURE_TLS;
    global $SMTP_SECURE_SSL;
    global $SMTP_PORTS;
    // display Add New SMTP Server form
    print "<html>\n";
    print "<head><title>$PRODUCT - ";
    print pacsone_gettext("Add SMTP Server");
    print "</title></head>\n";
    print "<body>\n";
    require_once 'header.php';
    print "<form method='POST' action='modifyEmail.php'>\n";
    print "<input type='hidden' name='actionvalue'>\n";
    print "<p><table border=1 cellpadding=2 cellspacing=0>\n";
    print "<tr><td>";
    print pacsone_gettext("Enter SMTP Server Hostname or IP Address:") . "</td>\n";
    print "<td><input type='text' size=32 maxlength=128 name='server'></td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Enter Port Number:") . "</td>\n";
    $default = $SMTP_PORTS[$SMTP_SECURE_NONE][0];
    print "<td><input type='text' size=10 maxlength=10 id='smtpport' name='port' value=$default></td></tr>\n";
    print "<tr><td>" . pacsone_gettext("Encryption:") . "</td>";
    print "<td><input type=radio name='encryption' value=$SMTP_SECURE_NONE checked onClick=\"document.getElementById('smtpport').value=$default\">";
    print pacsone_gettext("None");
    $value = $SMTP_PORTS[$SMTP_SECURE_TLS][0];
    print "<br><input type=radio name='encryption' value=$SMTP_SECURE_TLS onClick=\"document.getElementById('smtpport').value=$value\">";
    print pacsone_gettext("TLS");
    $value = $SMTP_PORTS[$SMTP_SECURE_SSL][0];
    print "<br><input type=radio name='encryption' value=$SMTP_SECURE_SSL onClick=\"document.getElementById('smtpport').value=$value\">";
    print pacsone_gettext("SSL");
    print "</td></tr>\n";
    print "<tr><td>" . pacsone_gettext("Connection Timeout:") . "</td>";
    print "<td><input type='text' size=6 maxlength=6 name='timeout' value=$SMTP_DEFAULT_TIMEOUT>";
    print "&nbsp;" . pacsone_gettext("Seconds") . "</td></tr>";
    print "<tr><td>";
    print pacsone_gettext("Enter Description of SMTP Server:") . "</td>\n";
    print "<td><input type='text' size=64 maxlength=255 name='description'></td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Enter <b>FROM</b> Email Address: ") . "<br>";
    print pacsone_gettext("<br>(This address will be used in the <b>FROM:</b> field in all emails sent using this SMTP server)") . "</td>\n";
    print "<td><input type='text' size=64 maxlength=255 name='myemail'></td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Enter <b>FROM</b> Person Name: ") . "<br>";
    print pacsone_gettext("<br>(This Person Name will be used in the <b>FROM:</b> field in all emails sent using this SMTP server)") . "</td>\n";
    print "<td><input type='text' size=64 maxlength=255 name='myname'></td></tr>\n";
    print "<tr><td>";
    print pacsone_gettext("Enter Authentication Type:") . "</td><td>";
    print "<input type=radio name='auth' value=0 checked>";
    print pacsone_gettext("None") . "<br>";
    print "<input type=radio name='auth' value=1>";
    print pacsone_gettext("Mechanism:");
    print "<select name='mechanism'>";
    foreach ($AUTH_TBL as $mechanism) {
        $selected = strcasecmp($mechanism, "Login")? "" : "selected";
        print "<option $selected>$mechanism</option>";
    }
    print "</select><br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
    print pacsone_gettext("Username:");
    print " <input type=text name='username' size=16 maxlength=64>";
    print pacsone_gettext(" Password:");
    print " <input type=password name='password' size=16 maxlength=64>";
    print "<br><input type=radio name='auth' value=2>";
    print pacsone_gettext("NTLM Workstation: ");
    print "<input type=text name='ntlmhost' size=32 maxlength=255>";
    print "</td></tr>\n";
    print "</table>\n";
    print "<p><input class='btn btn-primary' type='submit' name='action' value='";
    print pacsone_gettext("Add");
    print "' onclick='switchText(this.form,\"actionvalue\",\"Add\")'></form>\n";
    require_once 'footer.php';
    print "</body>\n";
    print "</html>\n";
}

function modifyEntryForm($server)
{
    include_once 'checkUncheck.js';
    require_once "deencrypt.php";
    global $PRODUCT;
    global $dbcon;
    global $AUTH_TBL;
    global $SMTP_SECURE_NONE;
    global $SMTP_SECURE_TLS;
    global $SMTP_SECURE_SSL;
    global $SMTP_PORTS;
    // display Modify SMTP Server form
    print "<html>\n";
    print "<head><title>$PRODUCT - ";
    print pacsone_gettext("Modify SMTP Server") . "</title></head>\n";
    print "<body>\n";
    require_once 'header.php';
    $query = "select * from smtp where hostname=?";
    $bindList = array($server);
    $result = $dbcon->preparedStmt($query, $bindList);
    if ($result && $result->rowCount() == 1) {
        $row = $result->fetch(PDO::FETCH_ASSOC);
        print "<form method='POST' action='modifyEmail.php'>\n";
        print "<input type='hidden' name='actionvalue'>\n";
        print "<p><table border=1 cellpadding=2 cellspacing=0>\n";
        print "<tr><td>" . pacsone_gettext("SMTP Server:") . "</td>\n";
        $value = "<td><input type='text' size=16 maxlength=16 name='server' ";
        $value .= "value='$server' ";
        $value .= "readonly></td>\n";
        print $value;
        print "</tr>\n";
        print "<tr><td>" . pacsone_gettext("Port Number:") . "</td>\n";
        $data = $row['port'];
        $value = "<td><input type='text' size=10 maxlength=10 id='smtpport' name='port'";
        if (isset($data))
            $value .= "value='$data'";
        $value .= "></td>\n";
        print $value;
        print "</tr>\n";
        print "<tr><td>" . pacsone_gettext("Encryption:") . "</td>";
        $data = $row['encryption'];
        $value = $SMTP_PORTS[$SMTP_SECURE_NONE][0];
        $checked = ($data == $SMTP_SECURE_NONE)? "checked" : "";
        print "<td><input type=radio name='encryption' value=$SMTP_SECURE_NONE $checked onClick=\"document.getElementById('smtpport').value=$value\">";
        print pacsone_gettext("None");
        $value = $SMTP_PORTS[$SMTP_SECURE_TLS][0];
        $checked = ($data == $SMTP_SECURE_TLS)? "checked" : "";
        print "<br><input type=radio name='encryption' value=$SMTP_SECURE_TLS $checked onClick=\"document.getElementById('smtpport').value=$value\">";
        print pacsone_gettext("TLS");
        $value = $SMTP_PORTS[$SMTP_SECURE_SSL][0];
        $checked = ($data == $SMTP_SECURE_SSL)? "checked" : "";
        print "<br><input type=radio name='encryption' value=$SMTP_SECURE_SSL $checked onClick=\"document.getElementById('smtpport').value=$value\">";
        print pacsone_gettext("SSL");
        print "</td></tr>\n";
        print "<tr><td>" . pacsone_gettext("Connection Timeout:") . "</td>";
        $data = $row['timeout'];
        print "<td><input type='text' size=6 maxlength=6 name='timeout' value=$data>";
        print "&nbsp;" . pacsone_gettext("Seconds") . "</td></tr>";
        print "<tr><td>";
        print pacsone_gettext("Description:") . "</td>\n";
        $data = $row['description'];
        $value = "<td><input type='text' size=16 maxlength=64 name='description'";
        if (isset($data))
            $value .= "value='$data'";
        $value .= "></td>\n";
        print $value;
        print "</tr>\n";
        print "<tr><td>";
        print pacsone_gettext("<b>FROM</b> Email Address:") . "</td>\n";
        $data = $row['myemail'];
        $value = "<td><input type='text' size=32 maxlength=255 name='myemail'";
        if (isset($data))
            $value .= "value='$data'";
        $value .= "></td>\n";
        print $value;
        print "</tr>\n";
        print "<tr><td>";
        print pacsone_gettext("<b>FROM</b> Person Name:") . "</td>\n";
        $data = $row['myname'];
        $value = "<td><input type='text' size=32 maxlength=255 name='myname'";
        if (!isset($data))
            $data = '';
        $value .= "value='$data'";
        $value .= "></td>\n";
        print $value;
        print "</tr>\n";
        print "<tr><td>";
        print pacsone_gettext("Authentication:") . "</td>\n";
        print "<td>";
        $data = $row['mechanism'];
        $checked0 = isset($data)? "" : "checked";
        $checked1 = isset($data)? "checked" : "";
        print "<input type=radio name='auth' value=0 $checked0>";
        print pacsone_gettext("None") . "<br>";
        print "<input type=radio name='auth' value=1 $checked1>";
        print pacsone_gettext("Mechanism:");
        print "<select name='mechanism'>";
        foreach ($AUTH_TBL as $mechanism) {
            $selected = strcasecmp($mechanism, $data)? "" : "selected";
            print "<option $selected>$mechanism</option>";
        }
        print "</select><br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
        $decrypt = new DeEncrypt();
        $data = $decrypt->decrypt($row['username']);
        print pacsone_gettext("Username:") . " <input type=text name='username' value='$data' size=16 maxlength=64>";
        print pacsone_gettext(" Password:") . " <input type=password name='password' ";
        if (strlen($data))
            print "value='*****' ";
        print "size=16 maxlength=64>";
        $data = $row['ntlmhost'];
        $checked = isset($data)? "checked" : "";
        print "<br><input type=radio name='auth' value=2 $checked>";
        print pacsone_gettext("NTLM Workstation: ");
        print "<input type=text name='ntlmhost' value='$data' size=32 maxlength=255>";
        print "</td></tr>\n";
        print "</table>\n";
        print "<p><input class='btn btn-primary' type='submit' name='action' value='";
        print pacsone_gettext("Modify");
        print "' onclick='switchText(this.form,\"actionvalue\",\"Modify\")'>\n";
        print "</form>\n";
    }
    else {
        print "<h3><font color=red>";
        printf(pacsone_gettext("<u>%s</u> not found in database"), $server);
        print "</font></h3>\n";
    }
    require_once 'footer.php';
    print "</body>\n";
    print "</html>\n";
}

?>
