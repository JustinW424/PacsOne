<?php
//
// header.php
//
// Common HTML header file
//
// CopyRight (c) 2003-2017 RainbowFish Software
//
if (!session_id())
    session_start();

require_once "locale.php";
include_once 'database.php';
include_once 'sharedData.php';

global $BGCOLOR;
$dbcon = new MyConnection();

//echo("database.php-19-".$dbcon->username." BG = ". $BGCOLOR);

$database = $dbcon->database;
$username = $dbcon->username;

print "<LINK rel=\"stylesheet\" type=\"text/css\" href=\"template_style.css\">";
?>

<?php

// add by rina 2021.11.4  --------------------------------------------------------

function AddLogout($pdatabase, $pusername)
{
    if (isset($_SESSION['fullname']))
        $fullname = $_SESSION['fullname'];
    if (isset($_SESSION['authenticatedUser'])) {
        $url = "logout.php";
        $value = sprintf("%s %s @ %s",
            pacsone_gettext("Logout"),
            isset($fullname)? $fullname : $pusername,
            $pdatabase);
    } else {
        $url = "login.php";
        $value = pacsone_gettext("Login");
    }

    print "<a href='$url'><img id='logout_img' src=\"logoutimg.png\"></a>";   // Log Out
}

function BuildModal($pdbcon, $pusername, $pview, $pmodify, $pquery)
{
    // add by rina 2021-11-7
    //  <!-- Modal -->
  print "<div class=\"modal fade\" id=\"myModal\" role=\"dialog\">\n";
    print "<div class=\"modal-dialog\">\n";
    
      //<!-- Modal content-->
      print "<div class=\"modal-content\">\n";
        print "<div class=\"modal-body\" style=\"background-color:#5BC0DE8F;\" >\n";
          //print "<p>Some text in the modal.</p>\n";  ---------------  put here custom item ----

          $prev = "<button type=\"button\" style=\"width:100%;box-shadow: 1px 2px 5px #000000;\" class=\"btn btn-success btn-lg\" onclick=\"window.location.assign('";

          $middle = "')\">";
          $end = "</button>\n";

          if ($pdbcon->isAdministrator($pusername)) {
            $ini = parseIniByAeTitle($_SESSION['aetitle']);
            if (isset($ini['LdapHost']))
                print $prev."ldapUser.php".$middle.pacsone_gettext("LDAP User Administration").$end;
            else
                print $prev."user.php" . $middle. pacsone_gettext("User Administration") .$end;
          }

          if ($pdbcon->hasaccess("admin", $pusername)) {
            print $prev."config.php".$middle . pacsone_gettext("Configuration") . $end;
            print $prev."email.php".$middle . pacsone_gettext("Email") . $end;
            print $prev."journal.php".$middle . pacsone_gettext("Journal") . $end;
            }


            $menu = array();
            $menu[pacsone_gettext("Home")] = "home.php";
            $menu[pacsone_gettext("Unread Studies")] = "unread.php";
            $menu[pacsone_gettext("Browse")] = "browse.php";
            $menu[pacsone_gettext("Search")] = "search.php";
            if ($pview || $pmodify || $pquery) {
                $menu[pacsone_gettext("Dicom AE")] = "applentity.php";
                // check if the HL-7 listener option is installed
                if (isHL7OptionInstalled())
                    $menu[pacsone_gettext("HL7 Application")] = "hl7app.php";
                $menu[pacsone_gettext("Auto Route")] = "autoroute.php";
            }
            $menu[pacsone_gettext("Job Status")] = "status.php";
            if ($pview || $pquery) {
                $menu[pacsone_gettext("Modality Worklist")] = "worklist.php";
            }
            $menu[pacsone_gettext("Tools")] = "tools.php#end";
            $encoded = urlencode($pusername);
            $menu[pacsone_gettext("Profile")] = "profile.php?user=$encoded";
            $menu[pacsone_gettext("Help")] = "manual.pdf";

            foreach ($menu as $key => $url){
                print $prev.$url.$middle.$key.$end;
            }       

        print "</div>\n";
        //print "<div class=\"modal-footer\">\n";
        //  print "<button type=\"button\" class=\"btn btn-default\" data-dismiss=\"modal\">Close</button>\n";
        //print "</div>\n";
      print "</div>\n";
      
    print "</div>\n";
  print "</div>\n";
}

function AddHeaderDropDownMenuToList($pdbcon, $pusername,$pview, $pmodify, $pquery)
{
    if ($pdbcon->isAdministrator($pusername)) {
    $ini = parseIniByAeTitle($_SESSION['aetitle']);
    if (isset($ini['LdapHost']))
     print "<li><a href='ldapUser.php'>" . pacsone_gettext("LDAP User Administration") . "</a></li>\n";
    else
     print "<li><a href='user.php'>" . pacsone_gettext("User Administration") . "</a></li>\n";
    }

    if ($pdbcon->hasaccess("admin", $pusername)) {
    print "<li><a href='config.php'>" . pacsone_gettext("Configuration") . "</a></li>\n";
    print "<li><a href='email.php'>" . pacsone_gettext("Email") . "</a></li>\n";
    print "<li><a href='journal.php'>" . pacsone_gettext("Journal") . "</a></li>\n";
    }

    $menu = array();
    $menu[pacsone_gettext("Home")] = "home.php";
    $menu[pacsone_gettext("Unread Studies")] = "unread.php";
    $menu[pacsone_gettext("Browse")] = "browse.php";
    $menu[pacsone_gettext("Search")] = "search.php";
    if ($pview || $pmodify || $pquery) {
        $menu[pacsone_gettext("Dicom AE")] = "applentity.php";
        // check if the HL-7 listener option is installed
        if (isHL7OptionInstalled())
            $menu[pacsone_gettext("HL7 Application")] = "hl7app.php";
        $menu[pacsone_gettext("Auto Route")] = "autoroute.php";
    }
    $menu[pacsone_gettext("Job Status")] = "status.php";
    if ($pview || $pquery) {
        $menu[pacsone_gettext("Modality Worklist")] = "worklist.php";
    }
    $menu[pacsone_gettext("Tools")] = "tools.php#end";
    $encoded = urlencode($pusername);
    $menu[pacsone_gettext("Profile")] = "profile.php?user=$encoded";
    $menu[pacsone_gettext("Help")] = "manual.pdf";

    foreach ($menu as $key => $url){
        print "<li><a href='$url'>$key</a></li>\n";
    }

}

// Hello World !!!  ---------------   Let's go !! --------------------------- 2021.11.3
// include bootstrap
print "<link href=\"cornerstone/bootstrap.min.css\" rel=\"stylesheet\">\n";  // 3.3.6
print "<link href=\"font-awesome.min.css\" rel=\"stylesheet\">\n";
print "<link href=\"cornerstoneViewer.css\" rel=\"stylesheet\">\n";
print "<link href=\"cornerstone/cornerstone.min.css\" rel=\"stylesheet\">\n";

print "<script src=\"cornerstone/jquery.min.js\"></script>\n";
print "<script src=\"cornerstone/bootstrap.min.js\"></script>\n";
print "<link rel=\"stylesheet\" type=\"text/css\" href=\"assets/css/style.css\">";

$view = $dbcon->hasaccess("viewprivate", $username);
$modify = $dbcon->hasaccess("modifydata", $username);
$query = $dbcon->hasaccess("query", $username);

BuildModal($dbcon, $username, $view, $modify, $query);

print "<div style=\"width:100%;\">\n";

            // 2021.11.7 rebuild for using Modal Nav menu. ========================

            print "<table class=\"table\" width=\"100%\">\n";
            print "<tr>\n";
            print "<td>\n";
               print "<div class='row'><a href='#' data-toggle=\"modal\" data-target=\"#myModal\"><img id='header_menu_img' src=\"../assets/img/OIP.png\" alt=\"logo\"></a></div>\n";
            //print "<button type=\"button\" class=\"btn btn-info btn-lg\" data-toggle=\"modal\" data-target=\"#myModal\">Open Modal</button>\n";

            print "</td>\n";
            print "<td style='text-align:center'>\n";
                print "<div class = \"btn\">\n";
                    $dir = dirname($_SERVER["SCRIPT_FILENAME"]);
                    if (file_exists("$dir/smallLogo.jpg")) {
                        print "<a href='home.php'><img id='smallLog_img' src=\"smallLogo.jpg\" alt=\"logo\"></a>\n";
                    } 
                print "</div>\n";
            print "</td>\n";
            print "<td>\n";
               AddLogOut($database, $username);
            print "</td>\n";
            print "</tr>\n";
            print "</table>\n";

print "</div>\n";

?>