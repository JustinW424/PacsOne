<?php
//
// deencrypt.php
//
// Module for encrypt/decrypting strings
//
// CopyRight (c) 2003-2018 RainbowFish Software
//

class DeEncrypt {
    var $KEY_FILE = "email.key";
    var $key;

    function DeEncrypt() {
        if(version_compare(PHP_VERSION,"5.0.0","<")) {
            $args = func_get_args();
            register_shutdown_function( array( &$this, '__destruct' ) );
            call_user_func_array( array( &$this, '__construct' ), $args );
        }
    }
    function __construct($dir = "") {
        $keyfile = (strlen($dir)? $dir : dirname($_SERVER['SCRIPT_FILENAME'])) . "/$this->KEY_FILE";
        // create the email key if not exists
        if (!file_exists($keyfile)) {
            if ($fp = fopen($keyfile, "w")) {
                fwrite($fp, date("r") . "\n");
                fclose($fp);
            }
        }
        $fp = fopen($keyfile, "r");
        $this->key = fread($fp, filesize($keyfile));
        fclose($fp);
    }

    function __destruct() {
    }

    function encrypt($string) {
        $result = '';
        for($i=0; $i<strlen($string); $i++) {
            $char = substr($string, $i, 1);
            $keychar = substr($this->key, ($i % strlen($this->key))-1, 1);
            $char = chr(ord($char)+ord($keychar));
            $result.=$char;
        }
        return base64_encode($result);
    }

    function decrypt($string) {
        $result = '';
        $string = base64_decode($string);
        for($i=0; $i<strlen($string); $i++) {
            $char = substr($string, $i, 1);
            $keychar = substr($this->key, ($i % strlen($this->key))-1, 1);
            $char = chr(ord($char)-ord($keychar));
            $result.=$char;
        }
        return $result;
    }
}

?>
