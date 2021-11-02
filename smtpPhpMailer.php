<?php
//
// smtpPhpMailer.php
//
// PHP class derived from the open-source PHPMailer class
//
// CopyRight (c) 2016-2018 RainbowFish Software
//
require_once "deencrypt.php" ;
require_once "PHPMailer/PHPMailerAutoload.php";

class smtpPhpMailer extends PHPMailer
{
    function __construct(&$row, $dir = "") {
        parent::__construct();
        $this->isSMTP();
        if ($row["encryption"]) {
            global $SMTP_PORTS;
            $this->SMTPSecure = strtolower($SMTP_PORTS[ $row["encryption"] ][1]);
            $this->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
        }
        $this->SMTPAutoTLS = false;
        $this->Host = $row["hostname"];
        if ($row["port"] != 25)
            $this->Port = $row["port"];
        $this->Timeout = 10;
        if (isset($row["timeout"]))
            $this->Timeout = $row["timeout"];
        $this->SMTPDebug = 0;
        $this->do_verp = false;
        $this->Debugoutput = 'html';
        $data = "";
        $decrypt = new DeEncrypt($dir);
        if (isset($row["username"]))
            $data = $decrypt->decrypt($row["username"]);
        $this->Username = $data;
        $data = "";
        if (isset($row["password"]))
            $data = $decrypt->decrypt($row["password"]);
        $this->Password = $data;
        $this->AuthType = isset($row["mechanism"])? $row["mechanism"] : "";
        $this->SMTPAuth = strlen($this->AuthType)? true : false;
        $this->Realm = "";
        $this->Workstation = isset($row["ntlmhost"])? $row["ntlmhost"] : "";
        $this->isHTML(true);
        $this->From = $row["myemail"];
        if (isset($row["myname"]))
            $this->FromName = $row["myname"];
    }
    function __destruct() {}
};

?>
