<?php
//
// constants.php
//
// Shared constant data among multiple modules
//
// CopyRight (c) 2004-2021 RainbowFish Software
//
require_once 'customize.php';

global $CUSTOMIZE_PATIENTS;
global $CUSTOMIZE_PATIENT_ID;
global $CUSTOMIZE_PATIENT_NAME;
global $CUSTOMIZE_PATIENT_SEX;
global $CUSTOMIZE_PATIENT_DOB;
global $CUSTOMIZE_PATIENT_AGE;
global $CUSTOMIZE_REFERRING_DOC;
global $CUSTOMIZE_READING_DOC;
global $CUSTOMIZE_REQUESTING_DOC;
global $CUSTOMIZE_ISSUER_OF_PATIENT_ID;
global $CUSTOMIZE_PERFORMING_DOC;

$ROUTE_DATE_KEY_TBL = array(
    "$CUSTOMIZE_PATIENT_DOB (0010,0030)"                            => 0x00100030,
    pacsone_gettext("Study Date (0008,0020)")                       => 0x00080020,
    pacsone_gettext("Series Date (0008,0021)")                      => 0x00080021,
);

$ROUTE_KEY_TBL = array (
    pacsone_gettext("Institution Name (0008,0080)")                 => 0x00080080,
    "$CUSTOMIZE_REFERRING_DOC (0008,0090)"                          => 0x00080090,
    "$CUSTOMIZE_PATIENT_ID (0010,0020)"                             => 0x00100020,
    pacsone_gettext("Protocol Name (0018,1030)")                    => 0x00181030,
    "$CUSTOMIZE_PERFORMING_DOC (0008,1050)"                         => 0x00081050,
    "$CUSTOMIZE_READING_DOC (0008,1060)"                            => 0x00081060,
    pacsone_gettext("Operator's Name (0008,1070)")                  => 0x00081070,
    pacsone_gettext("Study Description (0008,1030)")                => 0x00081030,
    pacsone_gettext("Series Description (0008,103E)")               => 0x0008103E,
    pacsone_gettext("Modality (0008,0060)")                         => 0x00080060,
    pacsone_gettext("Modalities In Study (0008,0061)")              => 0x00080061,
    pacsone_gettext("Accession Number (0008,0050)")                 => 0x00080050,
    "$CUSTOMIZE_REQUESTING_DOC (0032,1032)"                         => 0x00321032,
);
$ROUTE_KEY_TBL = array_merge($ROUTE_KEY_TBL, $ROUTE_DATE_KEY_TBL);
// supported Dicom printers
$PRINTER_TBL = array (
    pacsone_gettext("Default"),
    "Kodak DryView 8900",
    "Agfa DS5300",
    "Fuji DRYPIX3000",
);
// 24-hour schedule table
$HOUR_TBL = array(
    pacsone_gettext("12:00 AM")             => 0,
    pacsone_gettext("01:00 AM")             => 1,
    pacsone_gettext("02:00 AM")             => 2,
    pacsone_gettext("03:00 AM")             => 3,
    pacsone_gettext("04:00 AM")             => 4,
    pacsone_gettext("05:00 AM")             => 5,
    pacsone_gettext("06:00 AM")             => 6,
    pacsone_gettext("07:00 AM")             => 7,
    pacsone_gettext("08:00 AM")             => 8,
    pacsone_gettext("09:00 AM")             => 9,
    pacsone_gettext("10:00 AM")             => 10,
    pacsone_gettext("11:00 AM")             => 11,
    pacsone_gettext("12:00 PM")             => 12,
    pacsone_gettext("01:00 PM")             => 13,
    pacsone_gettext("02:00 PM")             => 14,
    pacsone_gettext("03:00 PM")             => 15,
    pacsone_gettext("04:00 PM")             => 16,
    pacsone_gettext("05:00 PM")             => 17,
    pacsone_gettext("06:00 PM")             => 18,
    pacsone_gettext("07:00 PM")             => 19,
    pacsone_gettext("08:00 PM")             => 20,
    pacsone_gettext("09:00 PM")             => 21,
    pacsone_gettext("10:00 PM")             => 22,
    pacsone_gettext("11:00 PM")             => 23,
    pacsone_gettext("12:00 AM Next Day")    => 24
);
// weekday table
$WEEKDAY_TBL = array(
    pacsone_gettext("Sunday")               => 0,
    pacsone_gettext("Monday")               => 1,
    pacsone_gettext("Tuesday")              => 2,
    pacsone_gettext("Wednesday")            => 3,
    pacsone_gettext("Thursday")             => 4,
    pacsone_gettext("Friday")               => 5,
    pacsone_gettext("Saturday")             => 6,
);
$ONE_DAY = 24 * 60 * 60;
$DAYS_TBL = array(0,31,28,31,30,31,30,31,31,30,31,30,31);
// Mime types
$MIME_TBL = array(
    "AVI"    => "video/x-msvideo",
    "BMP"    => "image/x-ms-bmp",
    "DOC"    => "application/msword",
    "DOCX"   => "application/msword",
    "XLS"    => "application/msexcel",
    "XLSX"   => "application/msexcel",
    "GIF"    => "image/gif",
    "JPG"    => "image/jpeg",
    "JPEG"   => "image/jpeg",
    "MP3"    => "audio/mpeg",
    "MPG"    => "video/mpeg",
    "MPEG"   => "video/mpeg",
    "PDF"    => "application/pdf",
    "PNG"    => "image/png",
    "RTF"    => "application/rtf",
    "TXT"    => "text/plain",
    "WAV"    => "audio/x-wav",
    "WMV"    => "video/x-ms-wmv",
    "XML"    => "text/xml",
    "ZIP"    => "application/zip",
    "TIF"    => "image/tiff",
);
// export media types and sizes
$EXPORT_MEDIA = array(
    "CD"                => array("650 MB", 650),
    "DVD"               => array("4.7 GB", 4.7 * 1024),
    "Dual-Layer DVD"    => array("8.5 GB", 8.5 * 1024),
    "Unlimited"         => array(pacsone_gettext("A single volume"), 2147483647),
);
// translate schedule values into strings
$SCHEDULE_TBL = array(
    -1	=> pacsone_gettext("Immediately"),
    0	=> pacsone_gettext("12:00 A.M."),
    1	=> pacsone_gettext("1:00 A.M."),
    2	=> pacsone_gettext("2:00 A.M."),
    3	=> pacsone_gettext("3:00 A.M."),
    4	=> pacsone_gettext("4:00 A.M."),
    5	=> pacsone_gettext("5:00 A.M."),
    6	=> pacsone_gettext("6:00 A.M."),
    7	=> pacsone_gettext("7:00 A.M."),
    8	=> pacsone_gettext("8:00 A.M."),
    9	=> pacsone_gettext("9:00 A.M."),
    10	=> pacsone_gettext("10:00 A.M."),
    11	=> pacsone_gettext("11:00 A.M."),
    12	=> pacsone_gettext("12:00 P.M."),
    13	=> pacsone_gettext("1:00 P.M."),
    14	=> pacsone_gettext("2:00 P.M."),
    15	=> pacsone_gettext("3:00 P.M."),
    16	=> pacsone_gettext("4:00 P.M."),
    17	=> pacsone_gettext("5:00 P.M."),
    18	=> pacsone_gettext("6:00 P.M."),
    19	=> pacsone_gettext("7:00 P.M."),
    20	=> pacsone_gettext("8:00 P.M."),
    21	=> pacsone_gettext("9:00 P.M."),
    22	=> pacsone_gettext("10:00 P.M."),
    23	=> pacsone_gettext("11:00 P.M."),
    24	=> pacsone_gettext("12:00 A.M. Next Day"),
);
// HL-7 Message Routing KeyName -> Key table
$HL7ROUTE_KEY_TBL = array (
    pacsone_gettext("Message Type")                  => "type",
    pacsone_gettext("Receiving Application")         => "recvingapp",
    pacsone_gettext("Receiving Facility")            => "recvingfac",
    pacsone_gettext("Sending Facility")              => "sendingfac",
);
// study/worklist status and color scheme
$STUDY_STATUS_SCHEDULED = 0;
$STUDY_STATUS_PATIENT_ARRIVED = 20;
$STUDY_STATUS_STARTED = 30;
$STUDY_STATUS_COMPLETED = 40;
$STUDY_STATUS_UPDATED = 50;
$STUDY_STATUS_VERIFIED = 60;
$STUDY_STATUS_DELETED = 70;
$STUDY_STATUS_DISCONTINUED = 80;
$STUDY_STATUS_IMAGE_ARRIVED = 900;
$STUDY_STATUS_IMAGE_ARRIVED_STAT = 901;
$STUDY_STATUS_READ = 200;
$STUDY_STATUS_DEFAULT = $STUDY_STATUS_SCHEDULED;
$STUDY_COLORS = array(
    $STUDY_STATUS_SCHEDULED             => "white",
    $STUDY_STATUS_PATIENT_ARRIVED       => "#3366FF",
    $STUDY_STATUS_STARTED               => "#6633FF",
    $STUDY_STATUS_COMPLETED             => "#CC33FF",
    $STUDY_STATUS_UPDATED               => "#FF33CC",
    $STUDY_STATUS_VERIFIED              => "#FF6633",
    $STUDY_STATUS_DISCONTINUED          => "#B88A00",
    $STUDY_STATUS_DELETED               => "black",
    $STUDY_STATUS_IMAGE_ARRIVED         => "yellow",
    $STUDY_STATUS_IMAGE_ARRIVED_STAT    => "red",
    $STUDY_STATUS_READ                  => "green",
);
// supported data element coercion table
$COERCION_TBL = array(
	0x00100020							=> $CUSTOMIZE_PATIENT_ID,
	0x00100021							=> $CUSTOMIZE_ISSUER_OF_PATIENT_ID,
	0x00080060							=> pacsone_gettext("Modality"),
	0x00080080							=> pacsone_gettext("Institution Name"),
	0x00080050                          => pacsone_gettext("Accession Number"),
	0x00081010                          => pacsone_gettext("Station Name"),
	0x00081030                          => pacsone_gettext("Study Description"),
	0x00081080                          => pacsone_gettext("Admitting Diagnoses Description"),
	0x00102160                          => pacsone_gettext("Ethnic Group"),
	0x00181004                          => pacsone_gettext("Plate ID"),
	0x00181310                          => pacsone_gettext("Acquisition Matrix"),
    0x00380400                          => pacsone_gettext("Patient's Institution Residence"),
    0x00731003                          => pacsone_gettext("Station Name"),
);
// plural table
$PLURAL_TBL = array(
    "PATIENT"   => $CUSTOMIZE_PATIENTS,
    "STUDY"     => pacsone_gettext("Studies"),
    "SERIES"    => pacsone_gettext("Series"),
    "IMAGE"     => pacsone_gettext("Images"),
);
// Dicom command access control table
$DICOM_CMDACCESS_TBL = array(
    0x1         => "C-STORE",
    0x2         => "C-FIND",
    0x4         => "C-MOVE",
    0x10        => "C-GET",
    0x8         => "WORKLIST-FIND",
);
// Dicom command filter table
$DICOM_CMDFILTER_TBL = array(
	0x00080080	=> pacsone_gettext("Institution Name"),
	0x00080090	=> $CUSTOMIZE_REFERRING_DOC,
	0x00081060	=> $CUSTOMIZE_READING_DOC,
);
// weekday mask table
$WEEKDAY_MASK = array(
    0x1         => pacsone_gettext("Sunday"),
    0x2         => pacsone_gettext("Monday"),
    0x4         => pacsone_gettext("Tuesday"),
    0x8         => pacsone_gettext("Wednesday"),
    0x10        => pacsone_gettext("Thursday"),
    0x20        => pacsone_gettext("Friday"),
    0x40        => pacsone_gettext("Saturday"),
);
// supported automatic purging by data element table
$AUTOPURGE_FILTER_TBL = array(
	0x00080060							=> pacsone_gettext("Modality"),
	0x00080080							=> pacsone_gettext("Institution Name"),
	0x00080090							=> $CUSTOMIZE_REFERRING_DOC,
	0X00081030							=> pacsone_gettext("Study Description"),
	0x00081060							=> $CUSTOMIZE_READING_DOC,
	0X00100010							=> $CUSTOMIZE_PATIENT_NAME,
);
// Date and Time formats
$DATE_FORMATS = array(
    "US"        => "%Y-%m-%d",
    "EURO"      => "%d.%m.%Y",
);
$DATE_FORMATS_ORACLE = array(
    "US"        => "YYYY-MM-DD",
    "EURO"      => "DD.MM.YYYY",
);
$DATETIME_FORMATS = array(
    "US"        => "%Y-%m-%d %T",
    "EURO"      => "%d.%m.%Y %T",
);
$DATETIME_FORMATS_ORACLE = array(
    "US"        => "YYYY-MM-DD HH24:MI:SS",
    "EURO"      => "DD.MM.YYYY HH24:MI:SS",
);
// icon image table for encapsulated documents
$ENCAPSULATED_DOC_ICON_TBL = array(
    strtoupper("application/pdf")       => "pdf.jpg",
);
// default wait time in minutes for all instanced of a study to be received,
// before forwarding the entire study
$DEFAULT_STUDY_WAIT = 10;
// default wait time in minutes for all instanced of a series to be received,
// before forwarding the entire series
$DEFAULT_SERIES_WAIT = 10;
// compress received study settings for source AE
$COMPRESS_RX_IMAGE_TBL = array(
    0   => pacsone_gettext("N/A"),
    1   => pacsone_gettext("JPEG Lossless Transfer Syntax"),
    2   => pacsone_gettext("JPEG Lossy Transfer Syntax"),
    3   => pacsone_gettext("RLE Compression Transfer Syntax"),
    4   => pacsone_gettext("JPEG2000 Part-1 Lossless Only Transfer Syntax"),
    5   => pacsone_gettext("JPEG2000 Part-1 Lossless Or Lossy Transfer Syntax"),
);
// Oracle database configuration file
$ORACLE_CONFIG_FILE = "database.oracle";
// supported data elements for anonymization template
$ANONYMIZE_TEMPLATE_TBL = array(
	0x00100010 => array($CUSTOMIZE_PATIENT_NAME, pacsone_gettext("Anonymized")),
	0x00100020 => array($CUSTOMIZE_PATIENT_ID, "\$MD5\$"),
	0x00100030 => array($CUSTOMIZE_PATIENT_DOB, pacsone_gettext("19700101")),
    0x00101040 => array(pacsone_gettext("Patient's Address"), pacsone_gettext("Anonymized")),
	0x00100040 => array($CUSTOMIZE_PATIENT_SEX, "Aonymized"),
	0x00101010 => array($CUSTOMIZE_PATIENT_AGE, "000Y"),
	0x00200010 => array(pacsone_gettext("Study ID"), pacsone_gettext("Anonymized")),
	0x00080050 => array(pacsone_gettext("Accession Number"), pacsone_gettext("Anonymized")),
	0x00080080 => array(pacsone_gettext("Institution Name"), pacsone_gettext("Anonymized")),
	0x00080081 => array(pacsone_gettext("Institution Address"), pacsone_gettext("Anonymized")),
	0x00080090 => array($CUSTOMIZE_REFERRING_DOC, pacsone_gettext("Anonymized")),
	0x00081030 => array(pacsone_gettext("Study Description"), pacsone_gettext("Anonymized")),
	0x00081050 => array(pacsone_gettext("Performing Physician's Name"), pacsone_gettext("Anonymized")),
	0x00081060 => array($CUSTOMIZE_READING_DOC, pacsone_gettext("Anonymized")),
	0x00081070 => array(pacsone_gettext("Operator's Name"), pacsone_gettext("Anonymized")),
	0x0008103E => array(pacsone_gettext("Series Description"), pacsone_gettext("Anonymized")),
);
// patient information that can be displayed in the Study List pages
$PATIENT_INFO_STUDY_VIEW_TBL = array(
    "patientid",
    "patientname",
    "sex",
    "birthdate",
    "age",
    "institution",
);
// customizable columns displayed in the Study List pages
$STUDY_VIEW_COLUMNS_TBL = array(
    "patientid"             => array($CUSTOMIZE_PATIENT_ID, 1),
    "patientname"           => array($CUSTOMIZE_PATIENT_NAME, 1),
    "sex"                   => array($CUSTOMIZE_PATIENT_SEX, 1),
    "birthdate"             => array($CUSTOMIZE_PATIENT_DOB, 1),
    "age"                   => array($CUSTOMIZE_PATIENT_AGE, 1),
    "institution"           => array(pacsone_gettext("Institution Name"), 1),
    "id"                    => array(pacsone_gettext("Study ID"), 1),
    "studydate"             => array(pacsone_gettext("Study Date"), 1),
    "studytime"             => array(pacsone_gettext("Study Time"), 1),
    "received"              => array(pacsone_gettext("Received On"), 1),
    "accessionnum"          => array(pacsone_gettext("Accession Number"), 1),
    "referringphysician"    => array($CUSTOMIZE_REFERRING_DOC, 1),
    "description"           => array(pacsone_gettext("Description"), 1),
    "readingphysician"      => array($CUSTOMIZE_READING_DOC, 1),
    "reviewed"              => array(pacsone_gettext("Read By"), 1),
    "requestingphysician"   => array($CUSTOMIZE_REQUESTING_DOC, 1),
    "commitreport"          => array(pacsone_gettext("Storage Commitment Report"), 0),
    "sourceae"              => array(pacsone_gettext("Source AE"), 1),
    "modalities"            => array(pacsone_gettext("Modalities"), 0),
    "admittingdiagnoses"    => array(pacsone_gettext("Admitting Diagnoses"), 0),
    "interpretationauthor"  => array(pacsone_gettext("Interpretation Author"), 0),
    "updated"               => array(pacsone_gettext("Last Update"), 0),
    "verified"              => array(pacsone_gettext("Verified By"), 0),
);
// shared user privilege column names
$USER_PRIVILEGE_TBL = array(
    "username"          => pacsone_gettext("Username"),
    "firstname"         => pacsone_gettext("First Name"),
    "lastname"          => pacsone_gettext("Last Name"),
    "middlename"        => pacsone_gettext("Middle Name"),
    "email"             => pacsone_gettext("Email"),
    "viewprivate"       => pacsone_gettext("View Private Data"),
    "modifydata"        => pacsone_gettext("Modify"),
    "forward"           => pacsone_gettext("Forward"),
    "query"             => pacsone_gettext("Query"),
    "move"              => pacsone_gettext("Move"),
    "download"          => pacsone_gettext("Download"),
    "print"             => pacsone_gettext("Print"),
    "export"            => pacsone_gettext("Export"),
    "import"            => pacsone_gettext("Import"),
    "upload"            => pacsone_gettext("Upload"),
    "monitor"           => pacsone_gettext("Monitor"),
    "mark"              => pacsone_gettext("Mark Study"),
    "admin"             => pacsone_gettext("System Administration"),
    "usergroup"         => pacsone_gettext("User Group"),
    "notifynewstudy"    => pacsone_gettext("Email Notification"),
    "changestore"       => pacsone_gettext("Change Storage Location"),
    "qidors"            => pacsone_gettext("QIDO-RS"),
    "wadors"            => pacsone_gettext("WADO-RS"),
    "stowrs"            => pacsone_gettext("STOW-RS"),
);
// user privilege defaults
$DEFAULT_PRIVILEGE_TBL = array(
    "viewprivate"           => false,
    "modifydata"            => false,
    "forward"               => true,
    "query"                 => true,
    "move"                  => true,
    "download"              => true,
    "print"                 => true,
    "export"                => true,
    "import"                => true,
    "upload"                => false,
    "monitor"               => false,
    "mark"                  => false,
    "changestore"           => false,
    "admin"                 => false,
    "qidors"                => false,
    "wadors"                => false,
    "stowrs"                => false,
);
// customizable columns displayed in the Patient List pages
$PATIENT_VIEW_COLUMNS_TBL = array(
    "origid"                => array($CUSTOMIZE_PATIENT_ID, 1),
    "patientname"           => array($CUSTOMIZE_PATIENT_NAME, 1),
    "birthdate"             => array($CUSTOMIZE_PATIENT_DOB, 1),
    "sex"                   => array($CUSTOMIZE_PATIENT_SEX, 1),
    "age"                   => array($CUSTOMIZE_PATIENT_AGE, 1),
    "weight"                => array($CUSTOMIZE_PATIENT_WEIGHT, 0),
    "institution"           => array(pacsone_gettext("Institution Name"), 0),
    "issuer"                => array($CUSTOMIZE_ISSUER_OF_PATIENT_ID, 0),
);

$PATIENT_VIEW_COLUMNS_TBL_VET = array(
    "origid"                => array($CUSTOMIZE_PATIENT_ID, 1),
    "patientname"           => array($CUSTOMIZE_PATIENT_NAME, 1),
    "birthdate"             => array($CUSTOMIZE_PATIENT_DOB, 1),
    "sexneutered"           => array(pacsone_gettext("Patient's Sex Neutered"), 1),
    "age"                   => array($CUSTOMIZE_PATIENT_AGE, 1),
    "weight"                => array($CUSTOMIZE_PATIENT_WEIGHT, 0),
    "speciesdescr"          => array(pacsone_gettext("Patient Species Description"), 1),
    "breeddescr"            => array(pacsone_gettext("Patient Breed Description"), 1),
    "respperson"            => array(pacsone_gettext("Responsible Person"), 1),
    "resppersonrole"        => array(pacsone_gettext("Responsible Person Role"), 0),
    "resppersonorg"         => array(pacsone_gettext("Responsible Organization"), 0),
    "speciescode"           => array(pacsone_gettext("Patient Species Code"), 0),
    "breedcode"             => array(pacsone_gettext("Patient Breed Code"), 0),
    "breedreg"              => array(pacsone_gettext("Breed Registration"), 0),
    "institution"           => array(pacsone_gettext("Institution Name"), 0),
    "issuer"                => array($CUSTOMIZE_ISSUER_OF_PATIENT_ID, 0),
);
// Dicom specific charset to locale/web-browser charset mapping:
//
// name     => array(0 => Description,
//                   1 => array(0   => web browser charset,
//                              1   => Dicom escape sequence),
//                  )
$DICOM_CHARSET_TBL = array(
    "default"               => array(pacsone_gettext("Default"), array()),
    "GB18030"               => array(pacsone_gettext("Simplified Chinese"), array("gb2312", "")),
    "ISO 2022 IR 149"       => array(pacsone_gettext("Korean"), array("euc-kr", pack("c4", 0x1b, 0x24, 0x29, 0x43))),
    "ISO 2022 IR 13"        => array(pacsone_gettext("Japanese JIS X 0201"), array("shift_JIS", pack("c3", 0x1b, 0x28, 0x49))),
    "ISO 2022 IR 87"        => array(pacsone_gettext("Japanese JIS X 0208"), array("iso-2022-jp", "")),
    "ISO 2022 IR 159"       => array(pacsone_gettext("Japanese JIS X 0212"), array("iso-2022-jp", pack("c4", 0x1b, 0x24, 0x28, 0x44))),
    "ISO_IR 192"            => array(pacsone_gettext("Unicode"), array("utf-8", "")),
    "ISO_IR 100"            => array(pacsone_gettext("Latin Alphabet Part 1"), array("iso-8859-1", "")),
    "Windows 1251"          => array(pacsone_gettext("Windows 1251"), array("windows-1251", "")),
    "ISO_IR 144"            => array(pacsone_gettext("Cyrillic"), array("iso-8859-5", "")),
);
// Film Size ID for Dicom printers
$FILM_SIZE_ID_TBL = array(
    "8INX10IN"              => pacsone_gettext("8-Inch x 10-Inch"),
    "10INX12IN"             => pacsone_gettext("10-Inch x 12-Inch"),
    "11INX14IN"             => pacsone_gettext("11-Inch x 14-Inch"),
    "14INX14IN"             => pacsone_gettext("14-Inch x 14-Inch"),
    "14INX17IN"             => pacsone_gettext("14-Inch x 17-Inch"),
);
// maximum failed login attempts allowed
$MAX_LOGIN_ATTEMPTS = 3;
// lockout period after the maximum failed login attempts have been exceeded
$LOCKOUT_HOURS = 12;
//
// transcription bookmark to database field mapping table
//
// Field ID => (Description, Default Bookmark Name, Database Table Column)
//
$XSCRIPT_BOOKMARK_FIELD_TBL = array(
    100                     => array($CUSTOMIZE_PATIENT_NAME, "PatientName", "patientname"),
    101                     => array($CUSTOMIZE_PATIENT_ID, "PatientID", "patientid"),
    102                     => array($CUSTOMIZE_PATIENT_DOB, "DateOfBirth", "birthdate"),
    103                     => array(pacsone_gettext("Study Date"), "StudyDate", "studydate"),
    104                     => array($CUSTOMIZE_REFERRING_DOC, "ReferDoc", "referringphysician"),
    105                     => array(pacsone_gettext("Institution Name"), "InstitutionName", "institution"),
    106                     => array(pacsone_gettext("Study Description"), "StudyDescription", "description"),
    107                     => array($CUSTOMIZE_PATIENT_SEX, "Gender", "sex"),
);
// user access filtering attributes
$USER_FILTER_TBL = array(
    "sourceae",
    "referringphysician",
    "readingphysician",
    "institution",
);
//
// required DMWL fields for automatic creation from HL7 ORM message
//
// postkey  => array(description, hl7 orm segment/field, table, column)
//
$WORKLIST_FROM_HL7_ORM_TBL = array(
    "patientname"       => array($CUSTOMIZE_PATIENT_NAME, "PID-5", 0x00100010, "hl7patientname", isset($_SESSION["_isOracle"])? "lastname||'^'||firstname||'^'||middlename" : "CONCAT(lastname, \"^\", firstname, \"^\", middlename)"),
    "patientid"         => array($CUSTOMIZE_PATIENT_ID, "PID-3", 0x00100020, "hl7patientid", "id"),
    "accessionnum"      => array(pacsone_gettext("Accession Number"), "ORC-2", 0x00080050, "hl7segorc", "placerordernum"),
    "aetitle"           => array(pacsone_gettext("Scheduled AE Station Title"), "ORC-4", 0x00400001, "hl7segorc", "placergroupnum"),
    "modality"          => array(pacsone_gettext("Modality"), "OBR-44.2", 0x00080060, "hl7procedurecode", "text"),
    "startdate"         => array(pacsone_gettext("Scheduled Procedure Start Date"), "OBR-36", 0x00400002, "hl7segobr", "DATE(scheduled)"),
    "starttime"         => array(pacsone_gettext("Scheduled Procedure Start Time"), "OBR-36", 0x00400003, "hl7segobr", isset($_SESSION["_isOracle"])? "TO_CHAR(scheduled,'HH24:MI:SS')" : "TIME(scheduled)"),
    "reqprocid"         => array(pacsone_gettext("Requested Procedure ID"), "OBR-4", 0x00401001, "hl7universalserviceid", "id"),
);
// default number of items displayed in each web page
$DEFAULT_PAGE_SIZE = 10;
// WADO security models
$WADO_BYPASS_FILE = "wado.authentication.bypass";
$WADO_SECURITY_HTTP = 0;
$WADO_SECURITY_FIXED = 1;
$WADO_SECURITY_TBL = array(
    $WADO_SECURITY_HTTP     => pacsone_gettext("Use Username/Password from HTTP GET/POST Request or Basic Authentication"),
    $WADO_SECURITY_FIXED    => pacsone_gettext("Use Pre-Configured Username/Password"),
);
$QIDORS_BYPASS_FILE = "qidors.authentication.bypass";
$WADORS_BYPASS_FILE = "wadors.authentication.bypass";
$STOWRS_BYPASS_FILE = "stowrs.authentication.bypass";
// columns/fields displayed in the Job Status page
$JOB_STATUS_COLUMNS_TBL = array(
    "id"            => pacsone_gettext("ID"),
    "username"      => pacsone_gettext("Username"),
    "aetitle"       => pacsone_gettext("AE Title"),
    "type"          => pacsone_gettext("Type"),
    "class"         => pacsone_gettext("Class"),
    "uuid"          => pacsone_gettext("Universally Unique ID (UUID)"),
    "schedule"      => pacsone_gettext("Schedule"),
    "priority"      => pacsone_gettext("Priority"),
    "submittime"    => pacsone_gettext("Submit Time"),
    "starttime"     => pacsone_gettext("Start Time"),
    "finishtime"    => pacsone_gettext("Finish Time"),
    "status"        => pacsone_gettext("Status"),
    "retries"       => pacsone_gettext("Retries"),
    "details"       => pacsone_gettext("Details"),
    "retryinterval" => pacsone_gettext("Retry Interval"),
);
// database job priority definitions
$DBJOB_PRIORITY_LOW = 0;
$DBJOB_PRIORITY_MEDIUM = 1;
$DBJOB_PRIORITY_HIGH = 2;
$DBJOB_PRIORITY_TBL = array(
    $DBJOB_PRIORITY_LOW     => pacsone_gettext("Low"),
    $DBJOB_PRIORITY_MEDIUM  => pacsone_gettext("Medium"),
    $DBJOB_PRIORITY_HIGH    => pacsone_gettext("High"),
);
// study page filters
$STUDY_FILTER_STATUS_READ = 1;
$STUDY_FILTER_STATUS_UNREAD = 2;
$STUDY_FILTER_STATUS_BOTH = 0;
// lower 8 bits stores the filter types, e.g., today, yesterday, etc,
// while higher bits store the value for the last N days filter
$STUDY_FILTER_STUDYDATE_MASK = 0xFF;
$STUDY_FILTER_STUDYDATE_MASK_BITS = 8;
$STUDY_FILTER_STUDYDATE_ALL = 0;
$STUDY_FILTER_STUDYDATE_TODAY = 1;
$STUDY_FILTER_STUDYDATE_YESTERDAY = 2;
$STUDY_FILTER_STUDYDATE_DAY_BEFORE_YESTERDAY = 3;
$STUDY_FILTER_STUDYDATE_LAST_N_DAYS = 4;
$STUDY_FILTER_STUDYDATE_FROM_TO = 5;
$STUDY_FILTER_BY_REFERRING_DOC = 0x1;
$STUDY_FILTER_BY_READING_DOC = 0x2;
$STUDY_FILTER_BY_DATE_RECEIVED = 0x4;
// compatible HL7 Version ID
$HL7VERSION = "2.3.1";
// archive directory hierarchy format
$ARCHIVE_DIR_FORMAT_FLAT = 0;
$ARCHIVE_DIR_FORMAT_HIERARCHY = 1;
$ARCHIVE_DIR_FORMAT_STUDYUID = 2;
$ARCHIVE_DIR_FORMAT_COMBO = 3;
$ARCHIVE_DIR_FORMAT_PID_STUDYDATE = 4;
// default auto-refresh time period in seconds
$AUTO_REFRESH_DEFAULT = 60;
// special value indicating study needs verification
$STUDY_NEED_VERIFICATION = "_attention";
// user signups
$SIGNUP_USERNAME = "usersignup";
$SIGNUP_PASSWORD = "SignUp1234#";
// duplicate Patient ID display filters
$DUPLICATE_FILTER_NONE = 0;
$DUPLICATE_FILTER_THIS_WEEK = 1;
$DUPLICATE_FILTER_THIS_MONTH = 2;
$DUPLICATE_FILTER_THIS_YEAR = 3;
$DUPLICATE_FILTER_DATE_RANGE = 4;
// list of special characters that must appear in user passwords
$PASSWD_SPECIAL_CHARS = "!#@^$";
// Modality Performed Procedure Step routing key attributes
$ROUTE_MPPS_TBL = array (
    pacsone_gettext("Modality (0008,0060)")                         => 0x00080060,
    pacsone_gettext("Accession Number (0008,0050)")                 => 0x00080050,
    pacsone_gettext("Study ID (0020,0010)")                         => 0x00200010,
    pacsone_gettext("Scheduled Procedure Step ID (0040,0009)")      => 0x00400009,
    pacsone_gettext("Requested Procedure ID (0040,1001)")           => 0x00401001,
    pacsone_gettext("Performed Procedure Step ID (0040,0253)")      => 0x00400253,
    pacsone_gettext("Performed Station Name (0040,0242)")           => 0x00400242,
    pacsone_gettext("Performed Location (0040,0243)")               => 0x00400243,
    pacsone_gettext("Performed Station AE Title (0040,0241)")       => 0x00400241,
    pacsone_gettext("Performed Procedure Step Start Date (0040,0244)")  => 0x00400244,
    pacsone_gettext("Performed Procedure Step Start Time (0040,0245)")  => 0x00400245,
    pacsone_gettext("Performed Procedure Step End Date (0040,0250)")    => 0x00400250,
    pacsone_gettext("Performed Procedure Step End Time (0040,0251)")    => 0x00400251,
    pacsone_gettext("Performed Procedure Step Status (0040,0252)")      => 0x00400252,
    pacsone_gettext("Performing Physician's Name (0008,1050)")      => 0x00081050,
);
// default timeout (in secs) for connecting to SMTP server
$SMTP_DEFAULT_TIMEOUT = 10;
// SMTP Security Options
$SMTP_SECURE_NONE = 0;
$SMTP_SECURE_TLS = 1;
$SMTP_SECURE_SSL = 2;
// SMTP Security Option - TCP Port table
$SMTP_PORTS = array(
    $SMTP_SECURE_NONE   => array(25, "None"),
    $SMTP_SECURE_TLS    => array(587, "TLS"),
    $SMTP_SECURE_SSL    => array(465, "SSL"),
);
// convert Dicom videos into HTML5 or Flash videos
$CONVERT_VIDEO_NONE = 0;
$CONVERT_VIDEO_WEBM = 1;
$CONVERT_VIDEO_MP4 = 2;
$CONVERT_VIDEO_SWF = 3;
// video conversion format table
//
// key => array(description, command-line arguments
//
$CONVERT_VIDEO_FORMAT_TBL = array(
    $CONVERT_VIDEO_NONE     => array(pacsone_gettext("No"), ""),
    $CONVERT_VIDEO_WEBM     => array("WebM", "-cpu-used 5 -deadline realtime"),
    $CONVERT_VIDEO_MP4      => array("MP4", ""),
    $CONVERT_VIDEO_SWF      => array("SWF", ""),
);
// LDAP query filters
$LDAP_QUERY_USER_FILTER = "(objectClass=person)";
$LDAP_QUERY_GROUP_FILTER = "(objectClass=groupOfUniqueNames)";
// maximum retries for automatic conversion
$AUTOCONVERT_MAX_RETRIES = 3;
// study view columns that can be modified via a URL link
$STUDY_MODIFY_COLUMNS = array(
    "referringphysician","readingphysician","description","admittingdiagnoses",
    "studydate","accessionnum",
);
// validate study search columns
$STUDY_SEARCH_COLUMNS = array(
    "uuid","id","patientid","accessionnum","modalities","referringphysician","description",
    "readingphysician","admittingdiagnoses","sourceae","requestingphysician",
);
// worklist sortable columns
$WORKLIST_SORT_COLUMNS = array(
    "patientname","patientid","accessionnum","modality","scheduledps.startdate","referringphysician",
    "description","scheduledps.aetitle",
);
// default parallel processing thread pool size
$DEFAULT_PARALLEL_THREAD_POOL_SIZE = 6;

?>
