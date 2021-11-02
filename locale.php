<?php
//
// locale.php
//
// Module for localization of php scripts for PacsOne Server
//
// CopyRight (c) 2003-2011 RainbowFish Software
//
// case insensitive locale table

$LOCALE_TBL = array(
    "zh-cn"         => array("zh_CN", stristr(getenv("OS"), "Window")? "UTF-8" : "gb2312"),
    "zh-tw"         => array("zh_TW", "big5"),
    "es-ar"         => array("es_AR", "UTF-8"),
    "fr-fr"         => array("fr_FR", "UTF-8"),
    "pl-pl"         => array("pl_PL", "UTF-8"),
    "it-it"         => array("it_IT", "UTF-8"),
    "pt-br"         => array("pt_BR", "UTF-8"),
    "ru-ru"         => array("ru_RU", "UTF-8"),
);
$locale = "";
// check the browser agent first
if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
    $tokens = explode(",", $_SERVER['HTTP_ACCEPT_LANGUAGE']);
    $locale = $tokens[0];
}
// check the browser URL next
else if (isset($_GET['locale'])) {
    $locale = $_GET['locale'];
}
$charset = "";
if (strlen($locale)) {
    $key = strtolower($locale);
    if (isset($LOCALE_TBL[$key])) {
        $locale = $LOCALE_TBL[$key][0];
        $dir = dirname($_SERVER['SCRIPT_FILENAME']);
        // check if the translated message catalog for this locale exists or not
        if (file_exists("$dir/locale/$locale/LC_MESSAGES/$locale.mo")) {
            if (!extension_loaded('gettext')) {
                print "<h3><font color=red>";
                print "'gettext' PHP extension is requied for this locale: [$locale]";
                print "</h3></font>";
                exit();
            }
            $charset = $LOCALE_TBL[$key][1];
            putenv("LC_ALL=$locale");
            setlocale(LC_ALL, $locale);
            bindtextdomain($locale, "$dir/locale");
            textdomain($locale);
        }
    }
}
if (isset($_SESSION['authenticatedUser'])) {
    include_once 'database.php';
    $dbcon = new MyConnection();
    $config = $dbcon->getBrowserCharset();
    // configured charset takes precedence over client browser settings
    if (strlen($config))
        $charset = $config;
}
if (strlen($charset))
    header("Content-Type: text/html; charset=$charset");

?>
