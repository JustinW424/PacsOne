<?php
//
// classroot.php
//
// Root class for PacsOne Server objects
//
// CopyRight (c) 2008-2018 RainbowFish Software, Inc.
//

class PacsOneRoot {
    function PacsOneRoot() {
        //if(version_compare(PHP_VERSION,"5.0.0","<")) {
            $args = func_get_args();
            register_shutdown_function( array( &$this, '__destruct' ) );
            call_user_func_array( array( &$this, '__construct' ), $args );
        //}
    }
    function __construct() { }
    function __destruct() { }
}

?>
