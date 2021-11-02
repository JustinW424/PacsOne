<?php
//
// login.php
//
// 
// Module for displaying user login page
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
session_start();

require_once "locale.php";
include_once 'sharedData.php';

function makeAntiSpamGraphics($text)
{
    if (!function_exists("imagick_readimage"))
        return "";
    $tempfile = "";
    $error = false;
    $dir = dirname($_SERVER["SCRIPT_FILENAME"]);
    $handle = imagick_readimage( "$dir/antispam.jpg" ) ;
    if (imagick_iserror( $handle ) ) {
        $error = true;
    }
    if (!$error) {
	    imagick_begindraw( $handle ) ;
        $font = stristr(getenv("OS"), "Window")? "Courier-New-Bold" : "Courier-Bold";
	    if ( !imagick_setfontface( $handle, $font ) ) {
            $error = true;
	    }
    }
    if (!$error && !imagick_setfillcolor( $handle, "#0000ff" ) ) {
        $error = true;
	}
	if (!$error && !imagick_setfontsize( $handle, 16 ) ) {
        $error = true;
	}
	if (!$error && !imagick_setfontstyle( $handle, IMAGICK_FONTSTYLE_NORMAL ) ) {
        $error = true;
	}
	if (!$error && !imagick_drawannotation( $handle, 50, 20, $text ) ) {
        $error = true;
	}
    if (!$error) {
        $tempname = tempnam(getenv("TEMP"), "ImageMagick");
        unlink($tempname);
        $tempname = $tempname . ".jpg";
        if (!file_exists("$dir/antispam/"))
            mkdir("$dir/antispam/");
        $tempname = "$dir/antispam/" . basename($tempname);
        if (imagick_writeimage( $handle, $tempname ) )
            $tempfile = $tempname;
    }
    return $tempfile;
}

function login($errorMessage, $cookie)
{
    if (strstr($errorMessage, "<"))
        die("Invalid message: <font color=red>" . htmlspecialchars($errorMessage) . "</font>");
    $errorMessage = str_replace("\n", "<p>", $errorMessage);
    include_once "checkUncheck.js";
    global $PRODUCT;
    print "<html>\n";
    print "<head><title>" . pacsone_gettext("Login") . "</title></head>\n";
    print "<body>\n";
    // check if logo exists
    $dir = dirname($_SERVER["SCRIPT_FILENAME"]);
    if (file_exists("$dir/largeLogo.jpg")) {
        print "<img src=\"largeLogo.jpg\">";
    } else {
        print "<h2>";
        printf(pacsone_gettext("%s Login"), $PRODUCT);
        print "</h2>\n";
    }
    print "<form AUTOCOMPLETE=\"off\" method=POST action='authenticate.php'>\n";
    print "<input type='hidden' name='loginvalue'>\n";
    // include the formatted error message
    if (isset($errorMessage))
        echo "<h3><font color=red>$errorMessage</font></h3>";
    // display Login form
    print "<table>\n";
    $ldap = false;
    $oracle = false;
    $databases = getDatabaseNames($oracle);
    $count = count($databases);
    if ($count) {
      $first = reset($databases);
      $db = $first['Database'];
      if ($count > 1) {     // drop-down list
        $databaseTbl = array();
        foreach ($databases as $aetitle => $entry) {
            $databaseTbl[$aetitle] = $entry['Database'];
        }
        print "<script language=\"JavaScript\">\n";
        print "<!--\n";
        print "var databaseTbl = " . json_encode($databaseTbl);
        print ";\n";
        print "function onSelect() {\n";
        print "var selectBox = document.getElementById(\"selectBox\");\n";
        print "var selectedValue = selectBox.options[selectBox.selectedIndex].value;\n";
        print "var dbName = document.getElementById(\"dbName\");\n";
        print "dbName.value = databaseTbl[selectedValue];\n";
        print "}";
        print "//-->\n";
        print "</script>\n";
        print "<tr><td>" . pacsone_gettext("Select AE Title:") . "</td>\n";
        print "<td><select name='aetitle' id='selectBox' onchange=\"onSelect();\">\n";
        $index = 0;
        foreach ($databases as $aetitle => $entry) {
            if (!$index && isset($entry['LdapHost']))
                $ldap = true;
            $selected = ($index == 0)? "selected" : "";
            print "<option $selected>$aetitle</option>";
            $index++;
        }
        print "</select></td></tr>\n";
      } else {              // single entry
        $aetitle = key($databases);
        if (isset($databases[$aetitle]['LdapHost']))
            $ldap = true;
        print "<input type='hidden' name='aetitle' value='$aetitle'></input>";
      }
      print "<tr><td>" . pacsone_gettext("Current Database:") . "</td>\n";
      print "<td><input type=text name='formDatabase' id='dbName' value='$db' size=16 maxlength=64 readonly></td>";
    } else {
        // no valid configuration file found
        print "<p><h3><font color=red>";
        printf(pacsone_gettext("Error: No Valid Configuration File Found for %s"), $PRODUCT);
        print "<p>";
        printf(pacsone_gettext("Please double check and make sure %s is installed properly"), $PRODUCT);
        print "</font></h3>";
        exit();
    }
    print "<tr><td>" . pacsone_gettext("Enter Username:") . "</td>\n";
    print "<td><input type=text size=16 maxlength=32 name='formUsername'></td>\n";
    print "</tr>\n";
    print "<tr><td>" . pacsone_gettext("Enter Password:") . "</td>\n";
    print "<td><input type=password size=16 maxlength=32 name='formPassword'></td>\n";
    print "</tr>\n";
    // check whether to bypass the Anti-spam code
    $dir = dirname($_SERVER['SCRIPT_FILENAME']);
    $dir = substr($dir, 0, strlen($dir) - 3);
    $bypass = $dir . "no.antispam.code";
    if (file_exists($bypass)) {
        print "<input type='hidden' name='formAntiSpam' value=$cookie>\n";
    } else {
        print "<tr><td>" . pacsone_gettext("Enter Anti-Spam Code From Below:") . "</td>\n";
        print "<td><input type=text size=16 maxlength=10 name='formAntiSpam'></td>\n";
        print "</tr>\n";
        $tempfile = makeAntiSpamGraphics($cookie);
        if (strlen($tempfile)) {
            $url = "tempimage.php?antispam=1&path=" . urlencode($tempfile) . "&purge=1";
            print "<tr><td colspan=2><img SRC='$url' BORDER='0' ALIGN='left' ALT='";
            print pacsone_gettext("AntiSpam Image");
            print "'>";
            print "</td></tr>";
        } else {
            print "<tr><td><font color=blue><b>";
            printf(pacsone_gettext(" *** Anti-Spam Code: %d *** "), $cookie);
            print "</b></font></td>";
            print "</tr>";
        }
    }
    print "<tr><td><input type=submit name='login' value='";
    print pacsone_gettext("Login");
    print "' onclick='switchText(this.form, \"loginvalue\", \"login\")'>\n";
    print "</td></tr>";
    print "</table>\n";
    $bypass = $dir . "no.user.signup";
    if (!$ldap && !file_exists($bypass)) {
        print "<p>" . pacsone_gettext("Not a registered user?");
        print "&nbsp;<input type=submit name='login' value='" . pacsone_gettext("Sign Up Now");
        print "' onclick='switchText(this.form, \"loginvalue\", \"signup\")'>\n"; 
    }
    print "</form>\n";
    require_once 'footer.php';
    print "</body>\n";
    print "</html>\n";
}

// main

if (isset($_REQUEST['message'])) {
	$message = urldecode($_REQUEST['message']);
} else {
	$message = pacsone_gettext("Not authorized to access this URL: ");
    // IIS does not supply $SERVER['REQUEST_URI'] to PHP while Apache does
    $uri = isset($_SERVER['REQUEST_URI'])? $_SERVER['REQUEST_URI'] : $_SERVER['SCRIPT_NAME'];
	$message .= "http://" . $_SERVER['SERVER_NAME'] . $uri;
}

$cookie = rand(501,1999);

// check if session is established
if (!isset($_SESSION['authenticatedUser']))
{
	$_SESSION['antispamcode'] = $cookie;
    login($message, $cookie);
}
else
{
    $url = "home.php";
    if (isset($_SESSION['requestUri']) && strlen($_SESSION['requestUri']))
        $url = $_SESSION['requestUri'];
    header("Location: $url");
}

?>
