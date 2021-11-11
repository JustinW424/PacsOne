<?php
//
// tabbedpage.php
//
// Module for displaying tabbed HTML pages
//
// CopyRight (c) 2003-2009 RainbowFish Software, Inc.
//
require_once "classroot.php";

class TabbedPage extends PacsOneRoot {
	var $title;
	var $url;

    function __construct($title, $url) {
		$this->title = $title;
		$this->url = $url;
	}
    function __destruct() { }
}

class Tabs extends PacsOneRoot {
	var $pages = array();
	var $current;

    function __construct(&$pages, &$current) {
		$this->pages = $pages;
		$this->current = $current;
	}
    function __destruct() { }
    function showHtml() {
        
        print "<p><div>";
        print "<ul class=\"nav nav-tabs\">";
        foreach ($this->pages as $page) {
            $title = $page->title;
            $url = $page->url;
            if (!strcasecmp($this->current, $title)) {
                $current = $page;
                print "<li class=\"active\"><a href='$url'><span>$title</span></a></li>\n";
            } else {
                print "<li><a href='$url'><span>$title</span></a></li>\n";
            }
        }
        print "</ul></div>";

        print "<div class=\"tabbed-box\">";
        if (!$current)
            debug_print_backtrace();
        
        $current->showHtml();
        print "</div><p>";

        /*print "<p><div class=\"rounded-tab\">";
        print "<ul>";
        foreach ($this->pages as $page) {
            $title = $page->title;
            $url = $page->url;
            if (!strcasecmp($this->current, $title)) {
                $current = $page;
                print "<li class=\"selected\"><a href='$url'><span>$title</span></a></li>\n";
            } else {
                print "<li class=\"notselected\"><a href='$url'><span>$title</span></a></li>\n";
            }
        }
        print "</ul></div>";
        print "<div class=\"tabbed-box\">";
        if (!$current)
            debug_print_backtrace();
        $current->showHtml();
        print "</div><p>";*/
    }
}

?>
