<?php
//
// ldap.php
//
// Module for interfacing with a LDAP Server
//
// CopyRight (c) 2017 RainbowFish Software
//
require_once "classroot.php";

class ldapAPI extends PacsOneRoot {
    var $ldap;
    var $connected = false;
    function __construct($host, $port, $user, $passwd) {
        if (!extension_loaded("ldap"))
            die("<font color=red>Error: LDAP PHP extension required but NOT loaded</font>");
        if ($port)
            $this->ldap = ldap_connect($host, $port);
        else
            $this->ldap = ldap_connect($host);
        if ($this->ldap) {
            ldap_set_option($this->ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
            if (ldap_bind($this->ldap, $user, $passwd))
                $this->connected = true;
        }
    }
    function __destruct() {
        if ($this->ldap)
            ldap_close($this->ldap);
        $this->connected = false;
    }
    function isConnected() {
        return $this->connected;
    }
    function getLastError() {
        $errno = ldap_errno($this->ldap);
        $err = "LDAP Error: $errno " . ldap_err2str($errno);
        return $err;
    }
    function search(&$dn, &$filter, &$attrs = array()) {
        return ldap_search($this->ldap, $dn, $filter, $attrs);
    }
    function numEntries(&$result) {
        return ldap_count_entries($this->ldap, $result);
    }
    function getEntries(&$result) {
        return ldap_get_entries($this->ldap, $result);
    }
}

?>
