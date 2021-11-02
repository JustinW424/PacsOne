<?php 
if (!session_id())
    session_start();

require_once "locale.php";
/* 

Zip file creation class 
makes zip files on the fly... 

use the functions add_dir() and add_file() to build the zip file; 
see example code below 

by Eric Mueller 
http://www.themepark.com 

v1.1 9-20-01 
  - added comments to example 

v1.0 2-5-01 

initial version with: 
  - class appearance 
  - add_file() and file() methods 
  - gzcompress() output hacking 
by Denis O.Philippov, webmaster@atlant.ru, http://www.atlant.ru 

*/   

// official ZIP file format: http://www.pkware.com/appnote.txt 

class zipfile    
{    
    var $num_entries = 0;
    var $datasec = "";
    var $ctrl_dir = ""; // central directory     
    var $eof_ctrl_dir = "\x50\x4b\x05\x06\x00\x00\x00\x00"; //end of Central directory record 
    var $old_offset = 0;   

    /**
     * Converts an Unix timestamp to a four byte DOS date and time format (date
     * in high two bytes, time in low two bytes allowing magnitude comparison).
     *
     * @param  integer  the current Unix timestamp
     *
     * @return integer  the current date in a four byte DOS format
     *
     * @access private
     */
    function unix2DosTime($unixtime = 0) {
        $timearray = ($unixtime == 0) ? getdate() : getdate($unixtime);

        if ($timearray['year'] < 1980) {
            $timearray['year']    = 1980;
            $timearray['mon']     = 1;
            $timearray['mday']    = 1;
            $timearray['hours']   = 0;
            $timearray['minutes'] = 0;
            $timearray['seconds'] = 0;
        } // end if

        return (($timearray['year'] - 1980) << 25) | ($timearray['mon'] << 21) | ($timearray['mday'] << 16) |
                ($timearray['hours'] << 11) | ($timearray['minutes'] << 5) | ($timearray['seconds'] >> 1);
    } // end of the 'unix2DosTime()' method

    function add_dir($name, $time = 0)     

    // adds "directory" to archive - do this before putting any files in directory! 
    // $name - name of directory... like this: "path/" 
    // ...then you can add files using add_file with names like "path/file.txt" 
    {    
        $name = str_replace("\\", "/", $name);    
        $dtime    = dechex($this->unix2DosTime($time));
        $hexdtime = '\x' . $dtime[6] . $dtime[7]
                  . '\x' . $dtime[4] . $dtime[5]
                  . '\x' . $dtime[2] . $dtime[3]
                  . '\x' . $dtime[0] . $dtime[1]; 
        eval('$hexdtime = "' . $hexdtime . '";');

        $this->datasec .= "\x50\x4b\x03\x04";   
        $this->datasec .= "\x0a\x00";    // ver needed to extract 
        $this->datasec .= "\x00\x00";    // gen purpose bit flag 
        $this->datasec .= "\x00\x00";    // compression method 
        $this->datasec .= $hexdtime;     // last mod time and date 

        $this->datasec .= pack("V",0); // crc32 
        $this->datasec .= pack("V",0); //compressed filesize 
        $this->datasec .= pack("V",0); //uncompressed filesize 
        $this->datasec .= pack("v", strlen($name) ); //length of pathname 
        $this->datasec .= pack("v", 0 ); //extra field length 
        $this->datasec .= $name;    
        // end of "local file header" segment 

        // no "file data" segment for path 

        // "data descriptor" segment (optional but necessary if archive is not served as file) 
        $this->datasec .= pack("V",0); //crc32 
        $this->datasec .= pack("V",0); //compressed filesize 
        $this->datasec .= pack("V",0); //uncompressed filesize 

        $new_offset = strlen($this->datasec);   

        // ext. file attributes mirrors MS-DOS directory attr byte, detailed 
        // at http://support.microsoft.com/support/kb/articles/Q125/0/19.asp 

        // now add to central record 
        $cdrec = "\x50\x4b\x01\x02";   
        $cdrec .="\x00\x00";    // version made by 
        $cdrec .="\x0a\x00";    // version needed to extract 
        $cdrec .="\x00\x00";    // gen purpose bit flag 
        $cdrec .="\x00\x00";    // compression method 
        $cdrec .= $hexdtime;    // last mod time & date 
        $cdrec .= pack("V",0); // crc32 
        $cdrec .= pack("V",0); //compressed filesize 
        $cdrec .= pack("V",0); //uncompressed filesize 
        $cdrec .= pack("v", strlen($name) ); //length of filename 
        $cdrec .= pack("v", 0 ); //extra field length     
        $cdrec .= pack("v", 0 ); //file comment length 
        $cdrec .= pack("v", 0 ); //disk number start 
        $cdrec .= pack("v", 0 ); //internal file attributes 
        $ext = "\x00\x00\x10\x00";   
        $ext = "\xff\xff\xff\xff";    
        $cdrec .= pack("V", 16 ); //external file attributes  - 'directory' bit set 

        $cdrec .= pack("V", $this -> old_offset ); //relative offset of local header 
        $this -> old_offset = $new_offset;   

        $cdrec .= $name;    
        // optional extra field, file comment goes here 
        // save to array 
        $this -> ctrl_dir .= $cdrec;
        $this -> num_entries++;
           
    }   


    function add_file(&$data, $name, $time = 0)     

    // adds "file" to archive     
    // $data - file contents 
    // $name - name of file in archive. Add path if your want 

    {    
        $name = str_replace("\\", "/", $name);    
        //$name = str_replace("\\", "\\\\", $name); 
        $dtime    = dechex($this->unix2DosTime($time));
        $hexdtime = '\x' . $dtime[6] . $dtime[7]
                  . '\x' . $dtime[4] . $dtime[5]
                  . '\x' . $dtime[2] . $dtime[3]
                  . '\x' . $dtime[0] . $dtime[1]; 
        eval('$hexdtime = "' . $hexdtime . '";');

        $this->datasec .= "\x50\x4b\x03\x04";   
        $this->datasec .= "\x14\x00";    // ver needed to extract 
        $this->datasec .= "\x00\x00";    // gen purpose bit flag 
        $this->datasec .= "\x08\x00";    // compression method 
        $this->datasec .= $hexdtime;     // last mod time and date 

        $unc_len = strlen($data);    
        $crc = crc32($data);    
        $zdata = gzcompress($data);    
        $zdata = substr( substr($zdata, 0, strlen($zdata) - 4), 2); // fix crc bug 
        $c_len = strlen($zdata);    
        $this->datasec .= pack("V",$crc); // crc32 
        $this->datasec .= pack("V",$c_len); //compressed filesize 
        $this->datasec .= pack("V",$unc_len); //uncompressed filesize 
        $this->datasec .= pack("v", strlen($name) ); //length of filename 
        $this->datasec .= pack("v", 0 ); //extra field length 
        $this->datasec .= $name;    
        // end of "local file header" segment 
           
        // "file data" segment 
        $this->datasec .= $zdata;    

        // "data descriptor" segment (optional but necessary if archive is not served as file) 
        /*
        $this->datasec .= pack("V",$crc); //crc32 
        $this->datasec .= pack("V",$c_len); //compressed filesize 
        $this->datasec .= pack("V",$unc_len); //uncompressed filesize 
        */

        $new_offset = strlen($this->datasec);   

        // now add to central directory record 
        $cdrec = "\x50\x4b\x01\x02";   
        $cdrec .="\x00\x00";    // version made by 
        $cdrec .="\x14\x00";    // version needed to extract 
        $cdrec .="\x00\x00";    // gen purpose bit flag 
        $cdrec .="\x08\x00";    // compression method 
        $cdrec .= $hexdtime;    // last mod time & date 
        $cdrec .= pack("V",$crc); // crc32 
        $cdrec .= pack("V",$c_len); //compressed filesize 
        $cdrec .= pack("V",$unc_len); //uncompressed filesize 
        $cdrec .= pack("v", strlen($name) ); //length of filename 
        $cdrec .= pack("v", 0 ); //extra field length     
        $cdrec .= pack("v", 0 ); //file comment length 
        $cdrec .= pack("v", 0 ); //disk number start 
        $cdrec .= pack("v", 0 ); //internal file attributes 
        $cdrec .= pack("V", 32 ); //external file attributes - 'archive' bit set 

        $cdrec .= pack("V", $this -> old_offset ); //relative offset of local header 
//        echo "old offset is ".$this->old_offset.", new offset is $new_offset<br>"; 
        $this -> old_offset = $new_offset;   

        $cdrec .= $name;    
        // optional extra field, file comment goes here 
        // save to central directory 
        $this -> ctrl_dir .= $cdrec;    
        $this -> num_entries++;
    }   

    function file() { // dump out file     
        return     
            $this->datasec.    
            $this -> ctrl_dir.    
            $this -> eof_ctrl_dir.    
            pack("v", $this -> num_entries).	    // total # of entries "on this disk" 
            pack("v", $this -> num_entries).        // total # of entries overall 
            pack("V", strlen($this->ctrl_dir)).     // size of central dir 
            pack("V", strlen($this->datasec)).      // offset to start of central dir 
            "\x00\x00";                             // .zip file comment length 
    }   
}    

function addFile(&$zipfile, $uid, $path, $dicom) {
	$handle = fopen($path, "rb");
	$data = fread($handle, filesize($path));
	fclose($handle);
	$name = $dicom? ($uid . ".dcm") : basename($path);
    // add the binary data stored in the string 'filedata' 
    $zipfile -> add_file($data, "$name");    
	return $data;
}

function zipFiles(&$files, $filename, $dicom)
{
    if (count($files) == 0) {
        die ("<p><font color=red>" . pacsone_gettext("No files to compress.") . "</font></p>");
    }
    // Allow sufficient execution time to the script:
    set_time_limit(0);

    $zipfile = new zipfile();
    foreach ($files as $uid => $path) {
	    addFile($zipfile, $uid, $path, $dicom);
    }
    $data = $zipfile -> file();
    /*
     * must use a temporary file as buffer when downloading a large number of
     * images because of the PHP output buffer control
     */
    $tempname = tempnam(getenv("TEMP"), "PacsOne");
    unlink($tempname);
    $tempname = $tempname . ".zip";
    $fp = fopen($tempname, "w+b");
    fwrite($fp, $data);
    fclose($fp);
    // save the URL parameteres for download
    $seq = 0;
    if (isset($_SESSION['downloadSeq']))
        $seq = $_SESSION['downloadSeq'];
    $_SESSION['downloadSeq'] = $seq + 1;
    $_SESSION["downloadFilename-$seq"] = $filename;
    $_SESSION["downloadPath-$seq"] = $tempname;
    $json = array(
        "seq"   => $seq,
    );
    header('Content-Type: application/json');
    echo json_encode($json);
}

?>
