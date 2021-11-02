<?php
//
// footer.php
//
// Common HTML footer file
//
// CopyRight (c) 2003-2008 RainbowFish Software
//

require_once "locale.php";
include_once "sharedData.php";

global $PRODUCT;
global $VERSION;
global $COPYRIGHT;

function isOnTrial($dir)
{
    if (file_exists($dir . "license.expire"))
        return time();
    // check whether to skip displaying the trial license expiration message
    $bypass = $dir . "license.aes";
    if (file_exists($bypass))
        return false;
    global $TRIAL_PERIOD;
    $result = false;
    $eval = $TRIAL_PERIOD * 24 * 3600;
    // goto the parent directory of "/php"
    $license = $dir . "license.dat";
    if (file_exists($license)) {
        $now = time();
        $diff = $now - filemtime($license);
        if ($diff < $eval) {
            // return the calculated expiration date
            $result = filemtime($license) + $eval;
        }
    }
    return $result;
}

print "</td></tr>";
$dir = dirname($_SERVER['SCRIPT_FILENAME']);
$dir = substr($dir, 0, strlen($dir) - 3);
// check if license file exists or not
if (!file_exists($dir . "license.dat") || file_exists($dir . "license.expired")) {
    $message = sprintf(pacsone_gettext("Error: %s license is either missing or has expired!"), $PRODUCT);
    print "<script language=\"JavaScript\">\n";
    print "<!--\n";
    print "alert(\"$message\");";
    print "history.go(-1);\n";
    print "//-->\n";
    print "</script>\n";
    exit();
}
print "<tr><td><HR></td></tr>";
print "<tr><td><TABLE width=100% border=0 cellpadding=0>";
print "<TR>";
print "<TD ALIGN=LEFT>";
print "<SMALL>$PRODUCT $VERSION</SMALL>";
print "</TD>";
$trial = "";
$expire = isOnTrial($dir);
if ($expire) {
    $trial = sprintf(pacsone_gettext("Your trial will expire on %s"), date("r", $expire));
    $file = $dir . "license.expire";
    if (file_exists($file) && ($fp = fopen($file, "r"))) {
        if ($expire = fgets($fp))
            $trial = sprintf(pacsone_gettext("Your trial will expire on %s"), $expire);
        fclose($fp);
    }
    print "<TD ALIGN=CENTER>";
    print "<SMALL>$trial</SMALL>";
    $file = $dir . "license.reminder";
    if (file_exists($file) && ($fp = fopen($file, "r"))) {
        if (fscanf($fp, "%d", $expire) == 1) {
            print "<p>";
            print "<h2><font color=red><b>";
            print sprintf(pacsone_gettext("Warning: Your trial will expire in %d days!"), $expire);
            print "</b></font></h2>";
        }
        fclose($fp);
    }
    print "</TD>";
} else {
    // dislay the expiration date of the annual technical support option if applicable
    $file = $dir . "upgrade.password.expire";
    if (file_exists($file) && ($fp = fopen($file, "r"))) {
        if ($expire = fgets($fp))
            $message = sprintf(pacsone_gettext("Your current annual technical support option will expire on <u>%s</u>"), $expire);
        fclose($fp);
        if (strlen($expire)) {
            print "<TD ALIGN=CENTER>";
            $timestamp = strtotime($expire);
            if ($timestamp > time()) {
                print "<SMALL>$message</SMALL>";
            } else {
                // annual support option has expired
                print "<small><font color=red><b>";
                print pacsone_gettext("Warning: Your annual technical support option has expired!");
                print "</b></font></small>";
            }
        }
    }
}
print "<TD ALIGN=RIGHT>";
print "<SMALL>$COPYRIGHT</SMALL>";
print "</TD>";
print "</TR>";
print "</TABLE></td></tr>";
print "</TABLE>";
?>
