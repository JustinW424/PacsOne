<?php
//
// xferSyntax.php
//
// Dicom Transfer Syntax Definitions
//
// CopyRight (c) 2004-2017 RainbowFish Software
//
require_once 'classroot.php';

// transfer syntax table: uid => (explicit vr, big endian, name, media type)
$XFER_SYNTAX_TBL = array (
    "1.2.840.10008.1.2"         => array (false, false, "Implicit VR, Little Endian (default)", "application/octet-stream"),
    "1.2.840.10008.1.2.1"       => array (true, false, "Explicit VR, Little Endian", "application/octet-stream"),
    "1.2.840.10008.1.2.2"       => array (true, true, "Explicit VR, Big Endian", "application/octet-stream"),
    "1.2.840.10008.1.2.5"       => array (true, false, "RLE Lossless", "image/x-dicom-rle"),
    "1.2.840.10008.1.2.4.50"    => array (true, false, "Explicit VR, JPEG Baseline (Process 1)", "image/jpeg"),
    "1.2.840.10008.1.2.4.51"    => array (true, false, "Explicit VR, JPEG Extended (Process 2 & 4)", "image/jpeg"),
    "1.2.840.10008.1.2.4.57"    => array (true, false, "Explicit VR, JPEG Lossless (Process 14)", "image/jpeg"),
    "1.2.840.10008.1.2.4.70"    => array (true, false, "Explicit VR, JPEG Lossless, Non-hierarchical, First-order prediction (Process 14)", "image/jpeg"),
    "1.2.840.10008.1.2.4.80"    => array (true, false, "JPEG-LS Compression, Lossless Mode", "image/x-jls"),
    "1.2.840.10008.1.2.4.81"    => array (true, false, "JPEG-LS Compression, Near-Lossless Mode", "image/x-jls"),
    "1.2.840.10008.1.2.4.90"    => array (true, false, "JPEG 2000 Compression, Lossless Mode", "image/jp2"),
    "1.2.840.10008.1.2.4.91"    => array (true, false, "JPEG 2000 Compression, Lossy Mode", "image/jp2"),
    "1.2.840.10008.1.2.4.92"    => array (true, false, "JPEG 2000 Lossless Mode Part 2", "image/jpx"),
    "1.2.840.10008.1.2.4.93"    => array (true, false, "JPEG 2000 Lossy Mode Part 2", "image/jpx"),
    "1.2.840.10008.1.2.4.94"    => array (true, false, "JPIP REFERENCED", "?"),
    "1.2.840.10008.1.2.4.95"    => array (true, false, "JPIP REFERENCED DEFLATE", "?"),
    "1.2.840.10008.1.2.1.99"    => array (true, false, "Explicit VR, Deflated Little Endian", "application/octet-stream"),
    "1.2.840.10008.1.2.4.100"   => array (true, false, "MPEG-2 Compression Main Level", "video/mpeg2"),
    "1.2.840.10008.1.2.4.101"   => array (true, false, "MPEG-2 Compression High Level", "video/mpeg2"),
    "1.2.840.10008.1.2.4.102"   => array (true, false, "MPEG-4 AVC/H.264 Compression", "video/mp4"),
    "1.2.840.10008.1.2.4.103"   => array (true, false, "MPEG-4 AVC/H.264 BD-compatible Compression", "video/mp4"),
    "1.2.840.10008.1.2.4.104"   => array (true, false, "MPEG-4 AVC/H.264 High Profile / Level 4.2 For 2D Video", "video/mp4"),
    "1.2.840.10008.1.2.4.105"   => array (true, false, "MPEG-4 AVC/H.264 MPEG-4 AVC/H.264 High Profile / Level 4.2 For 3D Video", "video/mp4"),
    "1.2.840.10008.1.2.4.106"   => array (true, false, "MPEG-4 AVC/H.264 MPEG-4 AVC/H.264 Stereo High Profile / Level 4.2", "video/mp4"),
);

$LOSSLESS_SYNTAX_TBL = array(
    "1.2.840.10008.1.2.5"       => array (true, false, "RLE Lossless"),
    "1.2.840.10008.1.2.4.70"    => array (true, false, "Explicit VR, JPEG Lossless, Non-hierarchical, First-order prediction (Process 14)"),
    "1.2.840.10008.1.2.4.80"    => array (true, false, "JPEG-LS Compression, Lossless Mode"),
    "1.2.840.10008.1.2.4.90"    => array (true, false, "JPEG 2000 Compression, Lossless Mode"),
);

class DicomTransferSyntaxHelper extends PacsOneRoot {
    function __construct() { }
    function __destruct() { }
    function isCompressed($syntax) {
        $compressed = true;
        if (!strcmp($syntax, "1.2.840.10008.1.2") || !strcmp($syntax, "1.2.840.10008.1.2.1") ||
            !strcmp($syntax, "1.2.840.10008.1.2.2") || !strcmp($syntax, "1.2.840.10008.1.2.1.99"))
            $compressed = false;
        return $compressed;
    }
    function getMediaType($syntax) {
        global $XFER_SYNTAX_TBL;
        $type = "";
        if (isset($XFER_SYNTAX_TBL[$syntax]))
            $type = $XFER_SYNTAX_TBL[$syntax][3];
        return $type;
    }
}

?>
