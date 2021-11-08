<?php
//
// authenticatedSession.php
//
// Module for encrypt/decrypting session data
//
// CopyRight (c) 2018-2020 RainbowFish Software
//
require_once 'classroot.php';

class EncryptedSession extends PacsOneRoot {
    // cookie expiration
    const EXPIRE = 604800;  // 7*24*60*60
    // encrypted session data
    var $username;
    var $password;

    function EncryptedSession() {
        //if(version_compare(PHP_VERSION,"5.0.0","<")) {
            $args = func_get_args();
            register_shutdown_function( array( &$this, '__destruct' ) );
            call_user_func_array( array( &$this, '__construct' ), $args );
        //}
    }
    function __construct($user, $password) {
        if (!session_id())
            session_start();
        if (function_exists("sodium_crypto_secretbox_keygen")) {  // use Sodium instead of Mcrypt extension
            if (isset($_COOKIE['sessionCookie']))
                $key = $_COOKIE['sessionCookie'];
            else {
                $key = sodium_crypto_secretbox_keygen();
                setcookie("sessionCookie", $key, time()+self::EXPIRE);
            }
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $this->username = base64_encode($nonce . sodium_crypto_secretbox($user, $nonce, $key));
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $this->password = base64_encode($nonce . sodium_crypto_secretbox($password, $nonce, $key));
            sodium_memzero($user);
            sodium_memzero($password);
            sodium_memzero($key);
        } else if (function_exists("mcrypt_module_open")) {    // obsolete as of PHP 7.2
            $key = function_exists("hash")? hash("MD5", session_id()) : session_id();
            setcookie("sessionCookie", $key, time()+self::EXPIRE);
            $td = mcrypt_module_open('tripledes', '', 'ecb', '');
            $key = substr($key, 0, mcrypt_enc_get_key_size($td));
            $iv = mcrypt_create_iv (mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
            mcrypt_generic_init($td, $key, $iv);
            $this->username = mcrypt_generic($td, $user);
            $this->password = mcrypt_generic($td, $password);
            mcrypt_generic_deinit($td);
            mcrypt_module_close($td);
        } else {    // un-encrypted
            $this->username = $user;
            $this->password = $password;
        }
    }

    function __destruct() {}

    function getUsername() {
        return $this->username;
    }

    function getPassword() {
        return $this->password;
    }
}

class DecryptedSession extends PacsOneRoot {
    // decrypted session data
    var $username;
    var $password;

    function DecryptedSession() {
        //if(version_compare(PHP_VERSION,"5.0.0","<")) {
            $args = func_get_args();
            register_shutdown_function( array( &$this, '__destruct' ) );
            call_user_func_array( array( &$this, '__construct' ), $args );
        //}
    }
    function __construct() {

       // echo("authentificatedSession.php-84-".(!isset($_SESSION['authenticatedUser']) || !isset($_SESSION['authenticatedPassword'])));

        if (!session_id())
            session_start();
        if (!isset($_SESSION['authenticatedUser']) || !isset($_SESSION['authenticatedPassword']))
            return;
        if (function_exists("sodium_crypto_secretbox_keygen")) {  // use Sodium instead of Mcrypt extension
            $user = base64_decode($_SESSION['authenticatedUser']);
            $password = base64_decode($_SESSION['authenticatedPassword']);
            if (!defined('CRYPTO_SECRETBOX_MACBYTES')) define('CRYPTO_SECRETBOX_MACBYTES', 16);
            if ((mb_strlen($user, '8bit') < (SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + CRYPTO_SECRETBOX_MACBYTES)) ||
                (mb_strlen($password, '8bit') < (SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + CRYPTO_SECRETBOX_MACBYTES))) {
                throw new \Exception('Encrypted Username/Password was truncated');
            }
            $key = $_COOKIE['sessionCookie'];
            $nonce = mb_substr($user, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
            $ciphertext = mb_substr($user, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');
            $this->username = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
            sodium_memzero($ciphertext);
            $nonce = mb_substr($password, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
            $ciphertext = mb_substr($password, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');
            $this->password = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
            sodium_memzero($ciphertext);
            sodium_memzero($key);
        } else if (function_exists("mcrypt_module_open")) {    // obsolete as of PHP 7.2
            $td = mcrypt_module_open('tripledes', '', 'ecb', '');
            $key = substr($_COOKIE['sessionCookie'], 0, mcrypt_enc_get_key_size($td));
            $iv = mcrypt_create_iv (mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
            mcrypt_generic_init($td, $key, $iv);
            $this->username = rtrim(mdecrypt_generic($td, $_SESSION['authenticatedUser']), "\0");
            $this->password = rtrim(mdecrypt_generic($td, $_SESSION['authenticatedPassword']), "\0");
            mcrypt_generic_deinit($td);
            mcrypt_module_close($td);
        } else {    // un-encrypted
            $this->username = $_SESSION['authenticatedUser'];
            $this->password = $_SESSION['authenticatedPassword'];
        }
    }

    function __destruct() {}

    function getUsername() {
        return $this->username;
    }

    function getPassword() {
        return $this->password;
    }
}

?>
