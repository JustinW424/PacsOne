<?php
//
// dicom.php
//
// Module for client-side applications using DICOM 3.0 protocols
//
// CopyRight (c) 2003-2020 RainbowFish Software
//
require_once "sharedData.php";
require_once "xferSyntax.php";
require_once "locale.php";

// constant definitions
// abstract syntax definitions
$C_ECHO = '1.2.840.10008.1.1';
$C_FIND = '1.2.840.10008.5.1.4.1.2.1.1';
$C_FIND_STUDYROOT = '1.2.840.10008.5.1.4.1.2.2.1';
$C_MOVE = '1.2.840.10008.5.1.4.1.2.1.2';
$C_MOVE_STUDYROOT = '1.2.840.10008.5.1.4.1.2.2.2';
$WORKLIST_FIND = '1.2.840.10008.5.1.4.31';
$BASIC_PRINT = '1.2.840.10008.5.1.1.9';
// read/write buffer size
$MAX_PDU_LEN = 16 * 1024;
// un-defined length
$UNDEF_LEN = -1;
$temp = unpack("Vtest", "\xff\xff\xff\xff");
// on some 64-bit platforms PHP does not unpack 0xFFFFFFFF to -1
if ($temp["test"] != -1)
    $UNDEF_LEN = 4294967295;
// customized display texts
global $CUSTOMIZE_PATIENT_ID;
global $CUSTOMIZE_PATIENT_NAME;
global $CUSTOMIZE_PATIENT_SEX;
global $CUSTOMIZE_PATIENT_DOB;
global $CUSTOMIZE_PATIENT_AGE;
global $CUSTOMIZE_REFERRING_DOC;
global $CUSTOMIZE_REQUESTING_DOC;
global $CUSTOMIZE_PERFORMING_DOC;
global $CUSTOMIZE_READING_DOC;

// attribute tag to (name, type) table
$ATTR_TBL = array(
    0x00020001 => new CNameType("File Meta Information Version", "c2", "OB"),
    0x00020002 => new CNameType("Media Storage SOP Class UID", "a", "UI"),
    0x00020003 => new CNameType("Media Storage SOP Instance UID", "a", "UI"),
    0x00020010 => new CNameType("Transfer Syntax UID", "a", "UI"),
    0x00020012 => new CNameType("Implementation Class UID", "a", "UI"),
    0x00020013 => new CNameType("Implementation Version Name", "A", "SH"),
    0x00020016 => new CNameType("Source Application Entity Title", "A", "AE"),
    0x00020100 => new CNameType("Private Information Creator UID", "a", "UI"),
    0x00020102 => new CNameType("Private Information", "a", "OB"),
	0x00080005 => new CNameType("Specific Character Set", "A", "CS"),
	0x00080008 => new CNameType("Image Type", "A", "CS"),
	0x00080012 => new CNameType("Instance Creation Date", "A", "DA"),
	0x00080013 => new CNameType("Instance Creation Time", "A", "TM"),
	0x00080014 => new CNameType("Instance Creator UID", "a", "UI"),
	0x00080016 => new CNameType("SOP Class UID", "a", "UI"),
	0x00080018 => new CNameType("SOP Instance UID", "a", "UI"),
	0x00080020 => new CNameType("Study Date", "A", "DA"),
	0x00080021 => new CNameType("Series Date", "A", "DA"),
	0x00080022 => new CNameType("Acquisition Date", "A", "DA"),
	0x00080023 => new CNameType("Content Date", "A", "DA"),
	0x00080024 => new CNameType("Overlay Date", "A", "DA"),
	0x00080025 => new CNameType("Curve Date", "A", "DA"),
	0x0008002A => new CNameType("Acquisition Datetime", "A", "DT"),
	0x00080030 => new CNameType("Study Time", "A", "TM"),
	0x00080031 => new CNameType("Series Time", "A", "TM"),
	0x00080032 => new CNameType("Acquisition Time", "A", "TM"),
	0x00080033 => new CNameType("Content Time", "A", "TM"),
	0x00080034 => new CNameType("Overlay Time", "A", "TM"),
	0x00080035 => new CNameType("Curve Time", "A", "TM"),
	0x00080040 => new CNameType("Data Set Type (Retired)", "A", "US"),
	0x00080041 => new CNameType("Data Set Subtype (Retired)", "A", "LO"),
	0x00080050 => new CNameType("Accession Number", "A", "SH"),
	0x00080052 => new CNameType("Query/Retrieve Level", "A", "CS"),
    0x00080054 => new CNameType("Retrieve AE Title", "A", "AE"),
	0x00080060 => new CNameType("Modality", "A", "CS"),
	0x00080061 => new CNameType("Modalities in Study", "A", "CS"),
	0x00080062 => new CNameType("SOP Classes in Study", "a", "UI"),
	0x00080064 => new CNameType("Conversion Type", "A", "CS"),
	0x00080068 => new CNameType("Presentation Intent Type", "A", "CS"),
	0x00080070 => new CNameType("Manufacturer", "A", "LO"),
	0x00080080 => new CNameType("Institution Name", "A", "LO"),
	0x00080081 => new CNameType("Institution Address", "A", "ST"),
	0x00080082 => new CNameType("Institution Code Sequence", "S", "SQ"),
	0x00080090 => new CNameType($CUSTOMIZE_REFERRING_DOC, "A", "PN"),
	0x00080092 => new CNameType("Referring Physician's Address", "A", "ST"),
	0x00080094 => new CNameType("Referring Physician's Telephone Numbers", "A", "SH"),
	0x00080096 => new CNameType("Referring Physician Identification Sequence", "S", "SQ"),
	0x00080100 => new CNameType("Code Value", "A", "SH"),
	0x00080102 => new CNameType("Code Scheme Designator", "A", "SH"),
	0x00080103 => new CNameType("Code Scheme Version", "A", "SH"),
	0x00080104 => new CNameType("Code Meaning", "A", "LO"),
	0x00080105 => new CNameType("Mapping Resource", "A", "CS"),
	0x00080106 => new CNameType("Context Group Version", "A", "DT"),
	0x00080107 => new CNameType("Context Group Local Version", "A", "DT"),
	0x0008010b => new CNameType("Context Group Extension Flag", "A", "CS"),
	0x0008010c => new CNameType("Coding Scheme UID", "a", "UI"),
	0x0008010d => new CNameType("Context Group Extension Creator UID", "a", "UI"),
	0x0008010f => new CNameType("Context Identifier", "A", "CS"),
	0x00080110 => new CNameType("Coding Scheme Identification Sequence", "S", "SQ"),
	0x00080112 => new CNameType("Coding Scheme Registry", "A", "LO"),
	0x00080114 => new CNameType("Coding Scheme External ID", "A", "ST"),
	0x00080115 => new CNameType("Coding Scheme Name", "A", "ST"),
	0x00080116 => new CNameType("Coding Scheme Responsible Organization", "A", "ST"),
	0x00080117 => new CNameType("COntext UID", "a", "UI"),
	0x00080201 => new CNameType("Timezone Offset From UTC", "A", "SH"),
    0x00081010 => new CNameType("Station Name", "A", "SH"),
    0x00081030 => new CNameType("Study Description", "A", "LO"),
	0x0008103E => new CNameType("Series Description", "A", "LO"),
	0x00081040 => new CNameType("Institution Department Name", "A", "LO"),
	0x00081048 => new CNameType("Physician(s) of Record", "A", "PN"),
	0x00081050 => new CNameType($CUSTOMIZE_PERFORMING_DOC, "A", "PN"),
	0x00081060 => new CNameType($CUSTOMIZE_READING_DOC, "A", "PN"),
	0x00081070 => new CNameType("Operator's Name", "A", "PN"),
	0x00081080 => new CNameType("Admitting Diagnoses Description", "A", "LO"),
	0x00081090 => new CNameType("Manufacturer's Model Name", "A", "LO"),
	0x00081110 => new CNameType("Referenced Study Sequence", "S", "SQ"),
	0x00081111 => new CNameType("Referenced Performed Procedure Step Sequence", "S", "SQ"),
	0x00081115 => new CNameType("Referenced Series Sequence", "S", "SQ"),
	0x00081120 => new CNameType("Referenced Patient Sequence", "S", "SQ"),
	0x00081125 => new CNameType("Referenced Visit Sequence", "S", "SQ"),
	0x00081130 => new CNameType("Referenced Overlay Sequence", "S", "SQ"),
	0x0008113A => new CNameType("Referenced Waveform Sequence", "S", "SQ"),
	0x00081140 => new CNameType("Referenced Image Sequence", "S", "SQ"),
	0x00081145 => new CNameType("Referenced Curve Sequence", "S", "SQ"),
	0x0008114A => new CNameType("Referenced Instance Sequence", "S", "SQ"),
	0x00081150 => new CNameType("Referenced SOP Class UID", "a", "UI"),
	0x00081155 => new CNameType("Referenced SOP Instance UID", "a", "UI"),
	0x00081160 => new CNameType("Referenced Frame Number", "A", "IS"),
    0x00081167 => new CNameType("Multi-Frame Source SOP Instance UID", "a", "UI"),
    0x00081195 => new CNameType("Transaction UID", "a", "UI"),
    0x00081197 => new CNameType("Failed Reason", "v", "US"),
    0x00081198 => new CNameType("Failed SOP Sequence", "S", "SQ"),
	0x00081199 => new CNameType("Referenced SOP Sequence", "S", "SQ"),
	0x00081200 => new CNameType("Studies Containing Other Referenced Instances Sequence", "S", "SQ"),
	0x00081250 => new CNameType("Related Series Sequence", "S", "SQ"),
	0x00082110 => new CNameType("Lossy Image Compression", "A", "CS"),
	0x00089007 => new CNameType("Frame Type", "A", "CS"),
	0x00089123 => new CNameType("Creator-Version UID", "a", "UI"),
	0x00089205 => new CNameType("Pixel Presentation", "A", "CS"),
	0x00089206 => new CNameType("Volumetric Properties", "A", "CS"),
	0x00089207 => new CNameType("Volume Based Calculation Technique", "A", "CS"),
	0x00089208 => new CNameType("Complex Image Component", "A", "CS"),
	0x00089209 => new CNameType("Acquisition Contrast", "A", "CS"),
	0x00100010 => new CNameType($CUSTOMIZE_PATIENT_NAME, "A", "PN"),
	0x00100020 => new CNameType($CUSTOMIZE_PATIENT_ID, "A", "LO"),
	0x00100021 => new CNameType("Issuer of Patient ID", "A", "LO"),
	0x00100022 => new CNameType("Type of Patient ID", "A", "CS"),
	0x00100030 => new CNameType($CUSTOMIZE_PATIENT_DOB, "A", "DA"),
	0x00100032 => new CNameType("Patient's Birth Time", "A", "TM"),
	0x00100040 => new CNameType($CUSTOMIZE_PATIENT_SEX, "A", "CS"),
	0x00101000 => new CNameType("Other Patient IDs", "A", "LO"),
	0x00101001 => new CNameType("Other Patient Names", "A", "PN"),
	0x00101005 => new CNameType("Patient's Birth Name", "A", "PN"),
	0x00101010 => new CNameType($CUSTOMIZE_PATIENT_AGE, "A", "AS"),
	0x00101020 => new CNameType("Patient's Size", "A", "DS"),
	0x00101030 => new CNameType("Patient's Weight", "A", "DS"),
	0x00101040 => new CNameType("Patient's Address", "A", "LO"),
	0x00101050 => new CNameType("Insurance Plan Identification", "A", "LO"),
	0x00101060 => new CNameType("Patient's Mother's Birth Name", "A", "PN"),
	0x00101080 => new CNameType("Military Rank", "A", "LO"),
	0x00101090 => new CNameType("Medical Record Locator", "A", "LO"),
	0x00102000 => new CNameType("Medical Alerts", "A", "LO"),
	0x00102110 => new CNameType("Allergies", "A", "LO"),
	0x00102150 => new CNameType("Country of Residence", "A", "LO"),
	0x00102152 => new CNameType("Region of Residence", "A", "LO"),
	0x00102154 => new CNameType("Patient's Telephone Numbers", "A", "SH"),
	0x00102160 => new CNameType("Ethnic Group", "A", "SH"),
	0x00102180 => new CNameType("Occupation", "A", "SH"),
	0x001021A0 => new CNameType("Smoking Status", "A", "CS"),
	0x001021B0 => new CNameType("Additional Patient History", "A", "LT"),
    0x001021C0 => new CNameType("Pregnancy Status", "v", "US"),
    0x001021D0 => new CNameType("Last Menstrual Date", "A", "DA"),
    0x001021F0 => new CNameType("Patient's Religious Preference", "A", "LO"),
    0x00102201 => new CNameType("Patient Species Description", "A", "LO"),
    0x00102203 => new CNameType("Patient's Sex Neutered", "A", "CS"),
    0x00102210 => new CNameType("Anatomical Orientation Type", "A", "CS"),
    0x00102292 => new CNameType("Patient Breed Description", "A", "LO"),
    0x00102295 => new CNameType("Breed Registration Number", "A", "LO"),
    0x00102297 => new CNameType("Responsible Person", "A", "PN"),
    0x00102298 => new CNameType("Responsible Person Role", "A", "CS"),
    0x00102299 => new CNameType("Responsible Organization", "A", "LO"),
    0x00104000 => new CNameType("Patient Comments", "A", "LT"),
    0x00120010 => new CNameType("Clinical Trial Sponsor Name", "A", "LO"),
    0x00120020 => new CNameType("Clinical Trial Protocol ID", "A", "LO"),
    0x00120021 => new CNameType("Clinical Trial Protocol Name", "A", "LO"),
    0x00120030 => new CNameType("Clinical Trial Site ID", "A", "LO"),
    0x00120031 => new CNameType("Clinical Trial Site Name", "A", "LO"),
    0x00120040 => new CNameType("Clinical Trial Subject ID", "A", "LO"),
    0x00120042 => new CNameType("Clinical Trial Subject Reading ID", "A", "LO"),
    0x00120050 => new CNameType("Clinical Trial Time Point ID", "A", "LO"),
    0x00120051 => new CNameType("Clinical Trial Time Point Description", "A", "ST"),
    0x00120060 => new CNameType("Clinical Trial Coordinating Center Name", "A", "LO"),
    0x00120062 => new CNameType("Patient Identity Removed", "A", "CS"),
    0x00120063 => new CNameType("De0identification Method", "A", "LO"),
    0x00120071 => new CNameType("Clinical Trial Series ID", "A", "LO"),
    0x00120072 => new CNameType("Clinical Trial Series Description", "A", "LO"),
    0x00120084 => new CNameType("Distribution Type", "A", "CS"),
    0x00120085 => new CNameType("Consent for Distribution Flag", "A", "CS"),
	0x00180010 => new CNameType("Contrast/Bolus Agent", "A", "LO"),
	0x00180015 => new CNameType("Body Part Examined", "A", "CS"),
	0x00180020 => new CNameType("Scanning Sequence", "A", "CS"),
	0x00180021 => new CNameType("Sequence Variant", "A", "CS"),
	0x00180022 => new CNameType("Scan Options", "A", "CS"),
	0x00180023 => new CNameType("MR Acquisition Type", "A", "CS"),
	0x00180024 => new CNameType("Sequence Name", "A", "SH"),
	0x00180025 => new CNameType("Angio Flag", "A", "CS"),
	0x00180030 => new CNameType("Radionuclide", "A", "LO"),
	0x00180031 => new CNameType("Radiopharmaceutical", "A", "LO"),
	0x00180034 => new CNameType("Intervention Drug Name", "A", "LO"),
	0x00180035 => new CNameType("Intervention Drug Start Time", "A", "TM"),
	0x00180036 => new CNameType("Intervention Therapy Sequence", "S", "SQ"),
	0x0018003A => new CNameType("Intervention Description", "A", "ST"),
	0x00180040 => new CNameType("Cine Rate", "A", "IS"),
	0x00180042 => new CNameType("Initial Cine Run State", "A", "CS"),
	0x00180050 => new CNameType("Slice Thickness", "A", "DS"),
	0x00180060 => new CNameType("KVP", "A", "DS"),
	0x00180070 => new CNameType("Counts Accumulated", "A", "IS"),
	0x00180071 => new CNameType("Acquisition Termination Condition", "A", "CS"),
	0x00180072 => new CNameType("Effective Duration", "A", "DS"),
	0x00180073 => new CNameType("Acquisition Start Condition", "A", "CS"),
	0x00180074 => new CNameType("Acquisition Start Condition Data", "A", "IS"),
	0x00180075 => new CNameType("Acquisition Termination Condition Data", "A", "IS"),
	0x00180080 => new CNameType("Repetition Time", "A", "DS"),
	0x00180081 => new CNameType("Echo Time", "A", "DS"),
	0x00180082 => new CNameType("Inversion Time", "A", "DS"),
	0x00180083 => new CNameType("Number of Averages", "A", "DS"),
	0x00180084 => new CNameType("Imaging Frequency", "A", "DS"),
	0x00180085 => new CNameType("Imaged Nucleus", "A", "SH"),
	0x00180086 => new CNameType("Echo Number(s)", "A", "IS"),
	0x00180087 => new CNameType("Magnetic Field Strength", "A", "DS"),
	0x00180088 => new CNameType("Spacing Between Slices", "A", "DS"),
	0x00180089 => new CNameType("Number of Phase Encoding Steps", "A", "IS"),
	0x00180090 => new CNameType("Data Collection Diameter", "A", "DS"),
	0x00180091 => new CNameType("Echo Train Length", "A", "IS"),
	0x00180093 => new CNameType("Percent Sampling", "A", "DS"),
	0x00180094 => new CNameType("Percent Phase Field of View", "A", "DS"),
	0x00180095 => new CNameType("Pixel Bandwidth", "A", "DS"),
	0x00181000 => new CNameType("Device Serial Number", "A", "LO"),
	0x00181002 => new CNameType("Device UID", "a", "UI"),
	0x00181003 => new CNameType("Device ID", "A", "LO"),
	0x00181004 => new CNameType("Plate ID", "A", "LO"),
	0x00181005 => new CNameType("Generator ID", "A", "LO"),
	0x00181006 => new CNameType("Grid ID", "A", "LO"),
	0x00181007 => new CNameType("Cassett ID", "A", "LO"),
	0x00181008 => new CNameType("Gantry ID", "A", "LO"),
	0x00181010 => new CNameType("Secondary Capture Device ID", "A", "LO"),
	0x00181011 => new CNameType("Hardcopy Creation Device ID", "A", "LO"),
	0x00181012 => new CNameType("Date of Secondary Capture", "A", "DA"),
	0x00181014 => new CNameType("Time of Secondary Capture", "A", "TM"),
	0x00181016 => new CNameType("Secondary Capture Device Manufacturer", "A", "LO"),
	0x00181017 => new CNameType("Hardcopy Device Manufacturer", "A", "LO"),
	0x00181018 => new CNameType("Secondary Capture Device Manufacturer's Model Name", "A", "LO"),
	0x00181019 => new CNameType("Secondary Capture Device Software Version(s)", "A", "LO"),
	0x0018101A => new CNameType("Hardcopy Device Software Version", "A", "LO"),
	0x0018101B => new CNameType("Hardcopy Device Manufacturer's Model Name", "A", "LO"),
	0x00181020 => new CNameType("Software Version(s)", "A", "LO"),
	0x00181022 => new CNameType("Video Image Format Acquired", "A", "SH"),
	0x00181023 => new CNameType("Digital Image Format Acquired", "A", "LO"),
	0x00181030 => new CNameType("Protocol Name", "A", "LO"),
	0x00181040 => new CNameType("Contrast/Bolus Route", "A", "LO"),
	0x00181041 => new CNameType("Contrast/Bolus Volume", "A", "DS"),
	0x00181042 => new CNameType("Contrast/Bolus Start Time", "A", "TM"),
	0x00181043 => new CNameType("Contrast/Bolus Stop Time", "A", "TM"),
	0x00181044 => new CNameType("Contrast/Bolus Total Dose", "A", "DS"),
	0x00181045 => new CNameType("Syringe Counts", "A", "IS"),
	0x00181046 => new CNameType("Contrast Flow Rate(s)", "A", "DS"),
	0x00181047 => new CNameType("Contrast Flow Duration(s)", "A", "DS"),
	0x00181048 => new CNameType("Contrast/Bolus Ingredient", "A", "DS"),
	0x00181049 => new CNameType("Contrast/Bolus Ingredient Concentration", "A", "DS"),
	0x00181050 => new CNameType("Spatial Resolution", "A", "DS"),
	0x00181060 => new CNameType("Trigger Time", "A", "DS"),
	0x00181061 => new CNameType("Trigger Source or Type", "A", "LO"),
	0x00181062 => new CNameType("Nominal Interval", "A", "IS"),
	0x00181063 => new CNameType("Frame Time", "A", "DS"),
	0x00181064 => new CNameType("Cardiac Frame Type", "A", "LO"),
	0x00181065 => new CNameType("Frame Time Vector", "A", "DS"),
	0x00181066 => new CNameType("Frame Delay", "A", "DS"),
	0x00181067 => new CNameType("Image Trigger Delay", "A", "DS"),
	0x00181068 => new CNameType("Multiplex Group Time Offset", "A", "DS"),
	0x00181069 => new CNameType("Trigger Time Offset", "A", "DS"),
	0x0018106A => new CNameType("Synchronization Trigger", "A", "CS"),
	0x0018106C => new CNameType("Synchronization Channel", "v", "US"),
	0x0018106E => new CNameType("Trigger Sample Position", "V", "UL"),
	0x00181070 => new CNameType("Radiopharmaceutical Route", "A", "LO"),
	0x00181071 => new CNameType("Radiopharmaceutical Volume", "A", "DS"),
	0x00181072 => new CNameType("Radiopharmaceutical Start Time", "A", "TM"),
	0x00181073 => new CNameType("Radiopharmaceutical Stop Time", "A", "TM"),
	0x00181074 => new CNameType("Radionuclide Total Dose", "A", "DS"),
	0x00181075 => new CNameType("Radionuclide Half Life", "A", "DS"),
	0x00181076 => new CNameType("Radionuclide Position Fraction", "A", "DS"),
	0x00181077 => new CNameType("Radiopharmaceutical Specific Activity", "A", "DS"),
	0x00181080 => new CNameType("Beat Rejection Flag", "A", "CS"),
	0x00181081 => new CNameType("Low R-R Value", "A", "IS"),
	0x00181082 => new CNameType("High R-R Value", "A", "IS"),
	0x00181083 => new CNameType("Intervals Acquired", "A", "IS"),
	0x00181084 => new CNameType("Intervals Rejected", "A", "IS"),
	0x00181085 => new CNameType("PVC Rejection", "A", "LO"),
	0x00181086 => new CNameType("Skip Beats", "A", "IS"),
	0x00181088 => new CNameType("Heart Rate", "A", "IS"),
	0x00181090 => new CNameType("Cardiac Number of Images", "A", "IS"),
	0x00181094 => new CNameType("Trigger Window", "A", "IS"),
	0x00181100 => new CNameType("Reconstruction Diameter", "A", "DS"),
	0x00181110 => new CNameType("Distance Source to Detector", "A", "DS"),
	0x00181111 => new CNameType("Distance Source to Patient", "A", "DS"),
	0x00181114 => new CNameType("Estimated Radiographic Magnification Factor", "A", "DS"),
	0x00181120 => new CNameType("Gantry/Detector Tilt", "A", "DS"),
	0x00181121 => new CNameType("Gantry/Detector Slew", "A", "DS"),
	0x00181130 => new CNameType("Table Height", "A", "DS"),
	0x00181131 => new CNameType("Table Traverse", "A", "DS"),
	0x00181134 => new CNameType("Table Motion", "A", "CS"),
	0x00181135 => new CNameType("Table Vertical Increment", "A", "DS"),
	0x00181136 => new CNameType("Table Lateral Increment", "A", "DS"),
	0x00181137 => new CNameType("Table Longitudinal Increment", "A", "DS"),
	0x00181138 => new CNameType("Table Angle", "A", "DS"),
	0x0018113A => new CNameType("Table Type", "A", "CS"),
	0x00181140 => new CNameType("Rotation Direction", "A", "CS"),
	0x00181141 => new CNameType("Angular Position", "A", "DS"),
	0x00181142 => new CNameType("Radial Position", "A", "DS"),
	0x00181143 => new CNameType("Scan Arc", "A", "DS"),
	0x00181144 => new CNameType("Angular Step", "A", "DS"),
	0x00181145 => new CNameType("Center of Rotation Offset", "A", "DS"),
	0x00181146 => new CNameType("Rotation Offset", "A", "DS"),
	0x00181147 => new CNameType("Field of View Shape", "A", "CS"),
	0x00181149 => new CNameType("Field of View Dimension(s)", "A", "IS"),
	0x00181150 => new CNameType("Exposure Time", "A", "IS"),
	0x00181151 => new CNameType("X-ray Tube Current", "A", "IS"),
	0x00181152 => new CNameType("Exposure", "A", "IS"),
	0x00181153 => new CNameType("Exposure in uAs", "A", "IS"),
	0x00181154 => new CNameType("Average Pulse Width", "A", "DS"),
	0x00181155 => new CNameType("Radiation Setting", "A", "CS"),
	0x00181156 => new CNameType("Rectification Type", "A", "CS"),
	0x0018115A => new CNameType("Radiation Mode", "A", "CS"),
	0x0018115E => new CNameType("Image Area Dose Product", "A", "DS"),
	0x00181160 => new CNameType("Filter Type", "A", "SH"),
	0x00181161 => new CNameType("Type of Filters", "A", "LO"),
	0x00181162 => new CNameType("Intensifier Size", "A", "DS"),
	0x00181164 => new CNameType("Imager Pixel Spacing", "A", "DS"),
	0x00181166 => new CNameType("Grid", "A", "CS"),
	0x00181170 => new CNameType("Generator Power", "A", "IS"),
	0x00181180 => new CNameType("Collimator/grid Name", "A", "SH"),
	0x00181181 => new CNameType("Collimator Type", "A", "CS"),
	0x00181182 => new CNameType("Focal Distance", "A", "IS"),
	0x00181183 => new CNameType("X Focus Center", "A", "DS"),
	0x00181184 => new CNameType("Y Focus Center", "A", "DS"),
	0x00181190 => new CNameType("Focal Spot(s)", "A", "DS"),
	0x00181191 => new CNameType("Anode Target Material", "A", "CS"),
	0x001811A0 => new CNameType("Boday Part Thickness", "A", "DS"),
	0x001811A2 => new CNameType("Compression Force", "A", "DS"),
	0x00181200 => new CNameType("Date of Last Calibration", "A", "DA"),
	0x00181201 => new CNameType("Time of Last Calibration", "A", "TM"),
	0x00181202 => new CNameType("DateTime of Last Calibration", "A", "DT"),
	0x00181210 => new CNameType("Convolution Kernel", "A", "SH"),
	0x00181240 => new CNameType("Upper/Lower Pixel Values", "A", "IS"),
	0x00181242 => new CNameType("Actual Frame Duration", "A", "IS"),
	0x00181243 => new CNameType("Count Rate", "A", "IS"),
	0x00181244 => new CNameType("Preferred Playback Sequence", "v", "US"),
	0x00181250 => new CNameType("Receive Coil Name", "A", "SH"),
	0x00181251 => new CNameType("Transmit Coil Name", "A", "SH"),
	0x00181260 => new CNameType("Plate Type", "A", "SH"),
	0x00181261 => new CNameType("Phosphor Type", "A", "LO"),
	0x00181300 => new CNameType("Scan Velocity", "A", "DS"),
	0x00181301 => new CNameType("Whole Body Technique", "A", "CS"),
	0x00181302 => new CNameType("Scan Length", "A", "IS"),
	0x00181310 => new CNameType("Acquisition Matrix", "v", "US"),
	0x00181312 => new CNameType("In-plane Phase Encoding Direction", "A", "CS"),
	0x00181314 => new CNameType("Flip Angle", "A", "DS"),
	0x00181315 => new CNameType("Variable Flip Angle Flag", "A", "CS"),
	0x00181316 => new CNameType("SAR", "A", "DS"),
	0x00181318 => new CNameType("dB/dt", "A", "DS"),
	0x00181400 => new CNameType("Acquisition Device Processing Description", "A", "LO"),
	0x00181401 => new CNameType("Acquisition Device Processing Code", "A", "LO"),
	0x00181402 => new CNameType("Cassette Orientation", "A", "CS"),
	0x00181403 => new CNameType("Cassette Size", "A", "CS"),
	0x00181404 => new CNameType("Exposures on Plate", "v", "US"),
	0x00181405 => new CNameType("Relative X-Ray Exposure", "A", "IS"),
	0x00181411 => new CNameType("Exposure Index", "A", "DS"),
	0x00181412 => new CNameType("Target Exposure Index", "A", "DS"),
	0x00181413 => new CNameType("Deviation Index", "A", "DS"),
	0x00181450 => new CNameType("Column Angulation", "A", "DS"),
	0x00181460 => new CNameType("Tomo Layer Height", "A", "DS"),
	0x00181470 => new CNameType("Tomo Angle", "A", "DS"),
	0x00181480 => new CNameType("Tomo Time", "A", "DS"),
	0x00181490 => new CNameType("Tomo Type", "A", "CS"),
	0x00181491 => new CNameType("Tomo Class", "A", "CS"),
	0x00181495 => new CNameType("Number of Tomosynthesis Source Images", "A", "IS"),
	0x00181500 => new CNameType("Positioner Motion", "A", "CS"),
	0x00181508 => new CNameType("Positioner Type", "A", "CS"),
	0x00181510 => new CNameType("Positioner Primary Angle", "A", "DS"),
	0x00181511 => new CNameType("Positioner Secondary Angle", "A", "DS"),
	0x00181520 => new CNameType("Positioner Primary Angle Increment", "A", "DS"),
	0x00181521 => new CNameType("Positioner Secondary Angle Increment", "A", "DS"),
	0x00181530 => new CNameType("Detector Primary Angle", "A", "DS"),
	0x00181531 => new CNameType("Detector Secondary Angle", "A", "DS"),
	0x00181600 => new CNameType("Shutter Shape", "A", "CS"),
	0x00181602 => new CNameType("Shutter Left Vertical Edge", "A", "IS"),
	0x00181604 => new CNameType("Shutter Right Vertical Edge", "A", "IS"),
	0x00181606 => new CNameType("Shutter Upper Horizontal Edge", "A", "IS"),
	0x00181608 => new CNameType("Shutter Lower Horizontal Edge", "A", "IS"),
	0x00181610 => new CNameType("Center of Circular Shutter", "A", "IS"),
	0x00181612 => new CNameType("Radius of Circular Shutter", "A", "IS"),
	0x00181620 => new CNameType("Vertices of the Polygon Shutter", "A", "IS"),
	0x00181622 => new CNameType("Shutter Presentation Value", "v", "US"),
	0x00181623 => new CNameType("Shutter Overlay Group", "v", "US"),
	0x00181624 => new CNameType("Shutter Presentation Color CIELab Value", "v", "US"),
	0x00181700 => new CNameType("Collimator Shape", "A", "IS"),
	0x00181702 => new CNameType("Collimator Left Vertical Edge", "A", "IS"),
	0x00181704 => new CNameType("Collimator Right Vertical Edde", "A", "IS"),
	0x00181706 => new CNameType("Collimator Upper Horizontal Edge", "A", "IS"),
	0x00181708 => new CNameType("Collimator Lower Horizontal Edge", "A", "IS"),
	0x00181710 => new CNameType("Center of Circular Collimator", "A", "IS"),
	0x00181712 => new CNameType("Radius of Circular Collimator", "A", "IS"),
	0x00181720 => new CNameType("Vertices of the polygon Collimator", "A", "IS"),
	0x00181800 => new CNameType("Acquisition Time Synchronized", "A", "CS"),
	0x00181801 => new CNameType("Time Source", "A", "SH"),
	0x00181802 => new CNameType("Time Distribution Protocol", "A", "CS"),
	0x00181803 => new CNameType("NTP Source Address", "A", "LO"),
	0x00182001 => new CNameType("Page Number Vector", "A", "IS"),
	0x00182002 => new CNameType("Frame Label Vector", "A", "SH"),
	0x00182003 => new CNameType("Frame Primary Angle Vector", "A", "DS"),
	0x00182004 => new CNameType("Frame Secondary Angle Vector", "A", "DS"),
	0x00182005 => new CNameType("Slice Location Vector", "A", "DS"),
	0x00182006 => new CNameType("Display Window Label Vector", "A", "SH"),
	0x00182010 => new CNameType("Nominal Scanned Pixel Spacing", "A", "DS"),
	0x00182020 => new CNameType("Digitizing Device Transport Direction", "A", "CS"),
	0x00182030 => new CNameType("Rotation of Scanned Film", "A", "DS"),
	0x00185000 => new CNameType("Output Power", "A", "SH"),
	0x00185010 => new CNameType("Transducer Data", "A", "LO"),
	0x00185012 => new CNameType("Focus Depth", "A", "DS"),
	0x00185020 => new CNameType("Processing Function", "A", "LO"),
	0x00185021 => new CNameType("Postprocessing Function", "A", "LO"),
	0x00185022 => new CNameType("Mechanical Index", "A", "DS"),
	0x00185024 => new CNameType("Bone Thermal Index", "A", "DS"),
	0x00185026 => new CNameType("Cranial Thermal Index", "A", "DS"),
	0x00185027 => new CNameType("Soft Tissue Thermal Index", "A", "DS"),
	0x00185028 => new CNameType("Soft Tissue-focus Thermal Index", "A", "DS"),
	0x00185029 => new CNameType("Soft Tissue-surface Thermal Index", "A", "DS"),
	0x00185050 => new CNameType("Depth of Scan Field", "A", "IS"),
	0x00185100 => new CNameType("Patient Position", "A", "CS"),
	0x00185101 => new CNameType("View Position", "A", "CS"),
	0x0020000d => new CNameType("Study Instance UID", "a", "UI"),
	0x0020000e => new CNameType("Series Instance UID", "a", "UI"),
	0x00200010 => new CNameType("Study ID", "A", "SH"),
	0x00200011 => new CNameType("Series Number", "A", "IS"),
	0x00200012 => new CNameType("Acquisition Number", "A", "IS"),
	0x00200013 => new CNameType("Instance Number", "A", "IS"),
	0x00200019 => new CNameType("Item Number", "A", "IS"),
	0x00200020 => new CNameType("Patient Orientation", "A", "CS"),
	0x00200022 => new CNameType("Overlay Number", "A", "IS"),
	0x00200024 => new CNameType("Curve Number", "A", "IS"),
	0x00200026 => new CNameType("Lookup Table Number", "A", "IS"),
	0x00200030 => new CNameType("Image Position", "A", "DS"),
	0x00200032 => new CNameType("Image Position(Patient)", "A", "DS"),
	0x00200035 => new CNameType("Image Orientation", "A", "DS"),
	0x00200037 => new CNameType("Image Orientation(Patient)", "A", "DS"),
	0x00200050 => new CNameType("Location", "A", "DS"),
	0x00200052 => new CNameType("Frame of Reference UID", "a", "UI"),
	0x00200060 => new CNameType("Laterality", "A", "CS"),
	0x00200062 => new CNameType("Image Laterality", "A", "CS"),
	0x00200100 => new CNameType("Temporal Position Identifier", "A", "IS"),
	0x00200105 => new CNameType("Number of Temporal Positions", "A", "IS"),
	0x00200110 => new CNameType("Temporal Resolution", "A", "DS"),
	0x00200200 => new CNameType("Synchronization Frame of Reference UID", "a", "UI"),
	0x00200242 => new CNameType("SOP Instance UID of Concatenation Source", "a", "UI"),
	0x00201000 => new CNameType("Series in Study", "A", "IS"),
	0x00201001 => new CNameType("Acquisition in Series", "A", "IS"),
	0x00201002 => new CNameType("Images in Acquisition", "A", "IS"),
	0x00201003 => new CNameType("Images in Series", "A", "IS"),
	0x00201004 => new CNameType("Acquisition in Study", "A", "IS"),
	0x00201005 => new CNameType("Images in Study", "A", "IS"),
	0x00201020 => new CNameType("Reference", "A", "LO"),
	0x00201040 => new CNameType("Position Reference Indicator", "A", "LO"),
	0x00201041 => new CNameType("Slice Location", "A", "DS"),
	0x00201070 => new CNameType("Other Study Numbers", "A", "IS"),
	0x00201200 => new CNameType("Number of Patient Related Studies", "A", "IS"),
	0x00201202 => new CNameType("Number of Patient Related Series", "A", "IS"),
	0x00201204 => new CNameType("Number of Patient Related Instances", "A", "IS"),
	0x00201206 => new CNameType("Number of Study Related Series", "A", "IS"),
	0x00201208 => new CNameType("Number of Study Related Instances", "A", "IS"),
	0x00201209 => new CNameType("Number of Series Related Instances", "A", "IS"),
	0x00204000 => new CNameType("Image Comments", "A", "LT"),
	0x00205000 => new CNameType("Original Image Identification(Retired)", "A", "AT"),
	0x00205002 => new CNameType("Original Image Identification Nomenclature(Retired)", "A", "LO"),
	0x00280002 => new CNameType("Samples Per Pixel", "v", "US"),
	0x00280003 => new CNameType("Samples Per Pixel Used", "v", "US"),
	0x00280004 => new CNameType("Photometric Interpretation", "A", "CS"),
	0x00280006 => new CNameType("Planar Configuration", "v", "US"),
	0x00280008 => new CNameType("Number of Frames", "A", "IS"),
	0x00280009 => new CNameType("Frame Increment Pointer", "V", "AT"),
	0x0028000A => new CNameType("Frame Dimension Pointer", "V", "AT"),
	0x00280010 => new CNameType("Rows", "v", "US"),
	0x00280011 => new CNameType("Columns", "v", "US"),
	0x00280012 => new CNameType("Planes", "v", "US"),
	0x00280014 => new CNameType("Ultrasound Color Data Present", "v", "US"),
	0x00280030 => new CNameType("Pixel Spacing", "A", "DS"),
	0x00280031 => new CNameType("Zoom Factor", "A", "DS"),
	0x00280032 => new CNameType("Zoom Center", "A", "DS"),
    0x00280034 => new CNameType("Pixel Aspect Ratio", "A", "IS"),
	0x00280100 => new CNameType("Bits Allocated", "v", "US"),
	0x00280101 => new CNameType("Bits Stored", "v", "US"),
	0x00280102 => new CNameType("High Bit", "v", "US"),
	0x00280103 => new CNameType("Pixel Representation", "v", "US"),
	0x00280106 => new CNameType("Smallest Image Pixel Value", "v", "US"),
	0x00280107 => new CNameType("Largest Image Pixel Value", "v", "US"),
	0x00280108 => new CNameType("Smallest Pixel Value in Series", "v", "US"),
	0x00280109 => new CNameType("Largest Pixel Value in Series", "v", "US"),
	0x00280120 => new CNameType("Pixel Padding Value", "v", "US"),
	0x00280121 => new CNameType("Pixel Padding Range Limit", "v", "US"),
	0x00280200 => new CNameType("Image Location (Retired)", "A", "US"),
	0x00280300 => new CNameType("Quality Control Image", "A", "CS"),
	0x00280301 => new CNameType("Burned In Annotation", "A", "CS"),
	0x00280A02 => new CNameType("Pixel Spacing Calibration Type", "A", "CS"),
	0x00280A04 => new CNameType("Pixel Spacing Calibration Description", "A", "LO"),
	0x00281040 => new CNameType("Pixel Intensity Relationship", "A", "CS"),
	0x00281041 => new CNameType("Pixel Intensity Relationship Sign", "v", "SS"),
	0x00281050 => new CNameType("Window Center", "A", "DS"),
	0x00281051 => new CNameType("Window Width", "A", "DS"),
	0x00281052 => new CNameType("Rescale Intercept", "A", "DS"),
	0x00281053 => new CNameType("Rescale Slope", "A", "DS"),
	0x00281054 => new CNameType("Rescale Type", "A", "LO"),
	0x00281055 => new CNameType("Window Center & Width Explanation", "A", "LO"),
	0x00281056 => new CNameType("VOI LUT Function", "A", "CS"),
	0x00281090 => new CNameType("Recommended Viewing Mode", "A", "CS"),
    0x00282110 => new CNameType("Lossy Image Compression", "A", "CS"),
    0x00282112 => new CNameType("Lossy Image Compression Ratio", "A", "DS"),
    0x00282114 => new CNameType("Lossy Image Compression Method", "A", "CS"),
	0x0032000A => new CNameType("Study Status ID", "A", "CS"),
	0x0032000C => new CNameType("Study Priority ID", "A", "CS"),
	0x00320012 => new CNameType("Study ID Issuer", "A", "LO"),
	0x00320032 => new CNameType("Study Verified Date", "A", "DA"),
	0x00320033 => new CNameType("Study Verified Time", "A", "TM"),
	0x00320034 => new CNameType("Study Read Date", "A", "DA"),
	0x00320035 => new CNameType("Study Read Time", "A", "TM"),
	0x00321000 => new CNameType("Scheduled Study Start Date", "A", "DA"),
	0x00321001 => new CNameType("Scheduled Study Start Time", "A", "TM"),
	0x00321010 => new CNameType("Scheduled Study Stop Date", "A", "DA"),
	0x00321011 => new CNameType("Scheduled Study Stop Time", "A", "TM"),
	0x00321020 => new CNameType("Scheduled Study Location", "A", "LO"),
	0x00321021 => new CNameType("Scheduled Study Location AE Titles(s)", "A", "AE"),
	0x00321030 => new CNameType("Reason for Study", "A", "LO"),
	0x00321032 => new CNameType($CUSTOMIZE_REQUESTING_DOC, "A", "PN"),
	0x00321033 => new CNameType("Requesting Service", "A", "LO"),
	0x00321040 => new CNameType("Study Arrival Date", "A", "DA"),
	0x00321041 => new CNameType("Study Arrival Time", "A", "TM"),
	0x00321050 => new CNameType("Study Completion Date", "A", "DA"),
	0x00321051 => new CNameType("Study Completion Time", "A", "TM"),
	0x00321055 => new CNameType("Study Component Status ID", "A", "CS"),
	0x00321060 => new CNameType("Requested Procedure Description", "A", "LO"),
	0x00321064 => new CNameType("Requested Procedure Code Sequence", "S", "SQ"),
	0x00321070 => new CNameType("Requested Contrast Agent", "A", "LO"),
	0x00324000 => new CNameType("Study Comments", "A", "LT"),
    0x00380008 => new CNameType("Visit Status ID", "A", "CS"),
    0x00380010 => new CNameType("Admission ID", "A", "LO"),
    0x00380016 => new CNameType("Route of Admissions", "A", "LO"),
    0x00380020 => new CNameType("Admitting Date", "A", "DA"),
    0x00380021 => new CNameType("Admitting Time", "A", "TM"),
    0x00380030 => new CNameType("Discharge Date", "A", "DA"),
    0x00380032 => new CNameType("Discharge Time", "A", "TM"),
    0x00380031 => new CNameType("Discharge Time", "A", "TM"),
    0x00380050 => new CNameType("Special Needs", "A", "LO"),
    0x00380060 => new CNameType("Service Episode ID", "A", "LO"),
    0x00380062 => new CNameType("Service Episode Description", "A", "LO"),
    0x00380300 => new CNameType("Current Patient Location", "A", "LO"),
    0x00380400 => new CNameType("Patient's Institution Residence", "A", "LO"),
    0x00380500 => new CNameType("Patient State", "A", "LO"),
    0x00384000 => new CNameType("Visit Comments", "A", "LT"),
    0x00400001 => new CNameType("Scheduled Station AE Title", "A", "AE"),
    0x00400002 => new CNameType("Scheduled Procedure Step Start Date", "A", "DA"),
    0x00400003 => new CNameType("Scheduled Procedure Step Start Time", "A", "TM"),
    0x00400004 => new CNameType("Scheduled Procedure Step End Date", "A", "DA"),
    0x00400005 => new CNameType("Scheduled Procedure End Time", "A", "TM"),
    0x00400006 => new CNameType("Scheduled Performing Physician's Name", "A", "PN"),
    0x00400007 => new CNameType("Scheduled Procedure Step Description", "A", "LO"),
    0x00400008 => new CNameType("Scheduled Protocol Code Sequence", "S", "SQ"),
    0x00400009 => new CNameType("Scheduled Procedure Step ID", "A", "SH"),
    0x0040000A => new CNameType("Stage Code Sequence", "S", "SQ"),
    0x0040000B => new CNameType("Scheduled Performing Physician Identification Sequence", "S", "SQ"),
    0x00400010 => new CNameType("Scheduled Station Name", "A", "SH"),
    0x00400011 => new CNameType("Scheduled Procedure Step Location", "A", "SH"),
    0x00400012 => new CNameType("Pre-Medication", "A", "LO"),
    0x00400020 => new CNameType("Scheduled Procedure Step Status", "A", "CS"),
    0x00400026 => new CNameType("Order Placer Identifier Sequence", "S", "SQ"),
    0x00400027 => new CNameType("Order Filler Identifier Sequence", "S", "SQ"),
    0x00400031 => new CNameType("Local Namespace Entity ID", "A", "UT"),
    0x00400032 => new CNameType("Universal Entity ID", "A", "UT"),
    0x00400033 => new CNameType("Universal Entity ID Type", "A", "CS"),
    0x00400035 => new CNameType("Identifier Type Code", "A", "CS"),
    0x00400036 => new CNameType("Assigning Facility Sequence", "S", "SQ"),
    0x00400039 => new CNameType("Assigning Jurisdiction Code Sequence", "S", "SQ"),
    0x0040003A => new CNameType("Assigning Agency or Department Code Sequence", "S", "SQ"),
	0x00400100 => new CNameType("Scheduled Procedure Step Sequence", "S", "SQ"),
    0x00400241 => new CNameType("Performed Station AE Title", "A", "AE"),
    0x00400242 => new CNameType("Performed Station Name", "A", "SH"),
    0x00400243 => new CNameType("Performed Location", "A", "SH"),
    0x00400244 => new CNameType("Performed Procedure Step Start Date", "A", "DA"),
    0x00400245 => new CNameType("Performed Procedure Step Start Time", "A", "TM"),
    0x00400250 => new CNameType("Performed Procedure Step End Date", "A", "DA"),
    0x00400251 => new CNameType("Performed Procedure Step End Time", "A", "TM"),
    0x00400252 => new CNameType("Performed Procedure Step Status", "A", "CS"),
    0x00400253 => new CNameType("Performed Procedure Step ID", "A", "SH"),
    0x00400254 => new CNameType("Performed Procedure Step Description", "A", "LO"),
    0x00400255 => new CNameType("Performed Procedure Type Description", "A", "LO"),
    0x00400260 => new CNameType("Performed Protocol Code Sequence", "S", "SQ"),
    0x00400261 => new CNameType("Performed Protocol Type", "A", "CS"),
    0x00400270 => new CNameType("Scheduled Step Attributes Sequence", "S", "SQ"),
    0x00400275 => new CNameType("Request Attributes Sequence", "S", "SQ"),
    0x00400280 => new CNameType("Comments on the Performed Procedure", "A", "ST"),
    0x00400281 => new CNameType("Performed Procedure Step Discontinuation Reason Code Sequence", "S", "SQ"),
	0x004008EA => new CNameType("Measurement Units Code Sequence", "S", "SQ"),
	0x00401001 => new CNameType("Requested Procedure ID", "A", "SH"),
	0x00401002 => new CNameType("Reason for the Requested Procedure", "A", "LO"),
	0x00401003 => new CNameType("Requested Procedure Priority", "A", "SH"),
	0x00401004 => new CNameType("Patient Transport Arrangements", "A", "LO"),
	0x00401005 => new CNameType("Requested Procedure Location", "A", "LO"),
	0x00401008 => new CNameType("Confidentiality Code", "A", "LO"),
	0x00401009 => new CNameType("Reporting Priority", "A", "SH"),
	0x00401101 => new CNameType("Person Identification Code Sequence", "S", "SQ"),
	0x00401102 => new CNameType("Person's Address", "A", "ST"),
	0x00401103 => new CNameType("Person's Telephone Number", "A", "LO"),
	0x00401400 => new CNameType("Requested Proecure Comments", "A", "LT"),
    0x00402004 => new CNameType("Issue Date of Imaging Service Request", "A", "DA"),
    0x00402005 => new CNameType("Issue Time of Imaging Service Request", "A", "TM"),
    0x00402008 => new CNameType("Order Entered By", "A", "PN"),
    0x00402009 => new CNameType("Order Enterer's Location", "A", "SH"),
    0x00402010 => new CNameType("Order Callback Phone Number", "A", "SH"),
    0x00402016 => new CNameType("Placer Order Number/Imaging Service Request", "A", "LO"),
    0x00402017 => new CNameType("Filler Order Number/Imaging Service Request", "A", "LO"),
    0x00402400 => new CNameType("Imaging Service Request Comments", "A", "LT"),
	0x0040A010 => new CNameType("Relationship Type", "A", "CS"),
	0x0040A027 => new CNameType("Verifying Organization", "A", "LO"),
	0x0040A030 => new CNameType("Verification DateTime", "A", "DT"),
	0x0040A032 => new CNameType("Observation DateTime", "A", "DT"),
	0x0040A040 => new CNameType("Value Type", "A", "CS"),
	0x0040A043 => new CNameType("Concept Name Code Sequence", "S", "SQ"),
	0x0040A050 => new CNameType("Continuity of Content", "A", "CS"),
	0x0040A073 => new CNameType("Verifying Observer Sequence", "S", "SQ"),
	0x0040A075 => new CNameType("Verifying Observer Name", "A", "PN"),
	0x0040A078 => new CNameType("Author Observer Sequence", "S", "SQ"),
	0x0040A07A => new CNameType("Participant Sequence", "S", "SQ"),
	0x0040A07C => new CNameType("Custodial Organization Sequence", "S", "SQ"),
	0x0040A080 => new CNameType("Participation Type", "A", "CS"),
	0x0040A082 => new CNameType("Participation DateTime", "A", "DT"),
	0x0040A084 => new CNameType("Observer Type", "A", "CS"),
	0x0040A088 => new CNameType("Verifying Observer Identification Code Sequence", "S", "SQ"),
	0x0040A0B0 => new CNameType("Referenced Waveform Channels", "n", "US"),
	0x0040A120 => new CNameType("DateTime", "A", "DT"),
	0x0040A121 => new CNameType("Date", "A", "DA"),
	0x0040A122 => new CNameType("Time", "A", "TM"),
	0x0040A123 => new CNameType("PersonName", "A", "PN"),
	0x0040A124 => new CNameType("UID", "a", "UI"),
	0x0040A130 => new CNameType("Temporal Range Type", "A", "CS"),
	0x0040A132 => new CNameType("Referenced Sample Positions", "V", "UL"),
	0x0040A136 => new CNameType("Referenced Frame Numbers", "v", "US"),
	0x0040A138 => new CNameType("Referenced Time Offsets", "A", "DS"),
	0x0040A13A => new CNameType("Referenced DateTime", "A", "DT"),
	0x0040A160 => new CNameType("Text Value", "A", "UT"),
	0x0040A168 => new CNameType("Concept Code Sequence", "S", "SQ"),
	0x0040A170 => new CNameType("Purpose of Reference Code Sequence", "S", "SQ"),
	0x0040A180 => new CNameType("Annotation Group Number", "v", "US"),
	0x0040A195 => new CNameType("Modifier Code Sequence", "S", "SQ"),
	0x0040A300 => new CNameType("Measured Value Sequence", "S", "SQ"),
	0x0040A301 => new CNameType("Numeric Value Qualifier Code Sequence", "S", "SQ"),
	0x0040A30A => new CNameType("Numeric Value", "A", "DS"),
	0x0040A360 => new CNameType("Predecessor Documents Sequence", "S", "SQ"),
	0x0040A370 => new CNameType("Referenced Request Sequence", "S", "SQ"),
	0x0040A372 => new CNameType("Performed Procedure Code Sequence", "S", "SQ"),
	0x0040A375 => new CNameType("Current Requested Procedure Evidence Sequence", "S", "SQ"),
	0x0040A385 => new CNameType("Patient Other Evidence Sequence", "S", "SQ"),
	0x0040A390 => new CNameType("HL7 Structured Document Reference Sequence", "S", "SQ"),
	0x0040A491 => new CNameType("Completion Flag", "A", "CS"),
	0x0040A492 => new CNameType("Completion Flag Description", "A", "LO"),
	0x0040A493 => new CNameType("Verification Flag", "A", "CS"),
	0x0040A494 => new CNameType("Archive Requested", "A", "CS"),
	0x0040A496 => new CNameType("Preliminary Flag", "A", "CS"),
	0x0040A504 => new CNameType("Content Template Sequence", "S", "SQ"),
	0x0040A525 => new CNameType("Identical Documents Sequence", "S", "SQ"),
	0x0040A730 => new CNameType("Content Sequence", "S", "SQ"),
	0x0040B020 => new CNameType("Waveform Annotation Sequence", "S", "SQ"),
	0x00540010 => new CNameType("Energy Window Vector", "v", "US"),
	0x00540011 => new CNameType("Number of Energy Windows", "v", "US"),
	0x00540014 => new CNameType("Energy Window Lower Limit", "A", "DS"),
	0x00540015 => new CNameType("Energy Window Upper Limit", "A", "DS"),
	0x00540017 => new CNameType("Residual Syringe Counts", "A", "IS"),
	0x00540018 => new CNameType("Energy Window Name", "A", "SH"),
	0x00540020 => new CNameType("Detector Vector", "v", "US"),
	0x00540021 => new CNameType("Number of Detectors", "v", "US"),
	0x00540030 => new CNameType("Phase Vector", "v", "US"),
	0x00540031 => new CNameType("Number of Phases", "v", "US"),
	0x00540033 => new CNameType("Number of Frames in Phase", "v", "US"),
	0x00540036 => new CNameType("Phase Delay", "A", "IS"),
	0x00540038 => new CNameType("Pause Between Frames", "A", "IS"),
	0x00540050 => new CNameType("Rotation Vector", "v", "US"),
	0x00540051 => new CNameType("Number of Rotations", "v", "US"),
	0x00540053 => new CNameType("Number of Frames in Rotation", "v", "US"),
	0x00540060 => new CNameType("R-R Interval Vector", "v", "US"),
	0x00540061 => new CNameType("Number of R-R Intervals", "v", "US"),
	0x00540070 => new CNameType("Time Slot Vector", "v", "US"),
	0x00540071 => new CNameType("Number of Time Slots", "v", "US"),
	0x00540080 => new CNameType("Slice Vector", "v", "US"),
	0x00540081 => new CNameType("Number of Slices", "v", "US"),
	0x00540090 => new CNameType("Angular View Vector", "v", "US"),
	0x00540100 => new CNameType("Time Slice Vector", "v", "US"),
	0x00540101 => new CNameType("Number of Time Slices", "v", "US"),
	0x00540200 => new CNameType("Start Angle", "A", "DS"),
	0x00540202 => new CNameType("Type of Detector Motion", "A", "CS"),
	0x00540210 => new CNameType("Trigger Vector", "A", "IS"),
	0x00540211 => new CNameType("Number of Triggers in Phase", "v", "US"),
	0x00540400 => new CNameType("Image ID", "A", "SH"),
	0x00541000 => new CNameType("Series Type", "A", "CS"),
	0x00541001 => new CNameType("Units", "A", "CS"),
	0x00541002 => new CNameType("Counts Source", "A", "CS"),
	0x00541004 => new CNameType("Reprojection Method", "A", "CS"),
	0x00541100 => new CNameType("Randoms Correction Method", "A", "CS"),
	0x00541101 => new CNameType("Attenuation Correction Method", "A", "LO"),
	0x00541102 => new CNameType("Decay Correction", "A", "CS"),
	0x00541103 => new CNameType("Reconstruction Method", "A", "LO"),
	0x00541104 => new CNameType("Detector Lines of Response Used", "A", "LO"),
	0x00541105 => new CNameType("Scatter Correction Method", "A", "LO"),
	0x21100010 => new CNameType("Printer Status", "A", "CS"),
	0x21100020 => new CNameType("Printer Status Info", "A", "CS"),
	0x21100030 => new CNameType("Printer Name", "A", "LO"),
);

// explicit VR exception table
$EXPLICIT_VR_TBL = array (
    "OB"    => 2,
    "OW"    => 2,
    "OF"    => 2,
    "SQ"    => 2,
    "UT"    => 2,
    "UN"    => 2,
);

// gobal variables
$sequence = 0;

// utility functions
function pacsone_dump(&$data) {
	$length = strlen($data);
    print "<table width=100% cellpadding=0 cellspacing=0 border=1>\n";
	for ($i = 0; $i < $length; $i++) {
		if ($i % 16 == 0)
			print "<tr>";
		print "<td>" . dechex(ord($data[$i])) . "</td>";  // 2021.11.02  by rina
		if ($i % 16 == 15)
			print "</tr>";
	}
	if ($i % 16 != 15)
		print "</tr>";
	print "</table>";
}

function readSequenceImplicit(&$handle, $bigEnd, $fileSize)
{
    global $UNDEF_LEN;
    $body = "";
    while ($fileSize > 0) {
        $header = fread($handle, 8);
        $body .= $header;
        $fileSize -= 8;
        $format = $bigEnd? 'ngroup/nelement/Nlength' : 'vgroup/velement/Vlength';
        $array = unpack($format, $header);
        $group = $array['group'];
        $element = $array['element'];
        $length = $array['length'];
        if (($group == 0xFFFE) && ($element == 0xE0DD)) {
            // found end of sequence delimeter
            if ($length != 0)
                die("readSequenceImplicit(): length of SQ Delimeter must be 0!");
            break;
        } else if (($group == 0xFFFE) && ($element == 0xE000) && ($length == $UNDEF_LEN)) {
            // sequence item with undefined length
            $item = readItemImplicit($handle, $bigEnd, $fileSize);
            $body .= $item;
            $fileSize -= strlen($item);
        } else {
            // attributes with explicit length
            if ($length == 0)
                continue;
            $body .= fread($handle, $length);
            $fileSize -= $length;
        }
    }
    return $body;
}

function readItemImplicit(&$handle, $bigEnd, $fileSize)
{
    global $UNDEF_LEN;
    $body = "";
    while ($fileSize > 0) {
        $header = fread($handle, 8);
        $body .= $header;
        $fileSize -= 8;
        $format = $bigEnd? 'ngroup/nelement/Nlength' : 'vgroup/velement/Vlength';
        $array = unpack($format, $header);
        $group = $array['group'];
        $element = $array['element'];
        $length = $array['length'];
        if (($group == 0xFFFE) && ($element == 0xE00D)) {
            // found end of item delimeter
            if ($length != 0)
                die("readItemImplicit(): length of SQ Item Delimeter must be 0!");
            break;
        } else if ($length == $UNDEF_LEN) {
            // embedded sequence
            $seq = readSequenceImplicit($handle, $bigEnd, $fileSize);
            $body .= $seq;
            $fileSize -= strlen($seq);
        } else {
            // attributes with explicit length
            if ($length == 0)
                continue;
            $body .= fread($handle, $length);
            $fileSize -= $length;
        }
    }
    return $body;
}

function readSequenceExplicit(&$handle, $bigEnd, $fileSize)
{
    global $UNDEF_LEN;
    $body = "";
    while ($fileSize > 0) {
        $header = fread($handle, 8);
        $body .= $header;
        $fileSize -= 8;
        $format = $bigEnd? 'ngroup/nelement/Nlength' : 'vgroup/velement/Vlength';
        $array = unpack($format, $header);
        $group = $array['group'];
        $element = $array['element'];
        $length = $array['length'];
        $key = $group << 16 | $element;
        //print "SQ Key = " . dechex($key) . " Length = $length Format = $format<br>";
        if (($group == 0xFFFE) && ($element == 0xE0DD)) {
            // found end of sequence delimeter
            if ($length != 0)
                die("readSequenceExplicit(): length of SQ Delimeter must be 0!");
            break;
        } else if (($group == 0xFFFE) && ($element == 0xE000) && ($length == $UNDEF_LEN)) {
            // sequence item with undefined length
            $item = readItemExplicit($handle, $bigEnd, $fileSize);
            $body .= $item;
            $fileSize -= strlen($item);
        } else {
            // attributes with explicit length
            if ($length == 0)
                continue;
            $body .= fread($handle, $length);
            $fileSize -= $length;
        }
    }
    return $body;
}

function readItemExplicit(&$handle, $bigEnd, $fileSize)
{
    global $UNDEF_LEN;
    global $EXPLICIT_VR_TBL;
    $body = "";
    while ($fileSize > 0) {
        $header = fread($handle, 6);
        $body .= $header;
        $fileSize -= 6;
        $format = $bigEnd? 'ngroup/nelement/A2vr' : 'vgroup/velement/A2vr';
        $array = unpack($format, $header);
        $group = $array['group'];
        $element = $array['element'];
        $vr = $array['vr'];
        if (isset($EXPLICIT_VR_TBL[$vr])) {
            $format = $bigEnd? "nreserved/Nlength" : "vreserved/Vlength";
            $offset = 6;
        } else {
            $format = $bigEnd? "nlength" : "vlength";
            $offset = 2;
        }
        $header = fread($handle, $offset);
        $body .= $header;
        $fileSize -= $offset;
        $array = unpack($format, $header);
        $length = $array['length'];
        $key = $group << 16 | $element;
        //print "Item Key = " . dechex($key) . " Length = $length Format = $format<br>";
        if (($group == 0xFFFE) && ($element == 0xE00D)) {
            // found end of item delimeter
            if ($length != 0)
                die("readItemExplicit(): length of SQ Item Delimeter must be 0!");
            break;
        } else if ($length == $UNDEF_LEN) {
            // embedded sequence
            $seq = readSequenceExplicit($handle, $bigEnd, $fileSize);
            $body .= $seq;
            $fileSize -= strlen($seq);
        } else {
            // attributes with explicit length
            if ($length == 0)
                continue;
            $body .= fread($handle, $length);
            $fileSize -= $length;
        }
    }
    return $body;
}

function dicomValueToJson($vr, &$attrName, &$value)
{
    $json = array();
    // JSON requires UTF-8 encoding
    if (function_exists('mb_convert_encoding'))
        $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    switch (strtoupper($vr)) {
    case "PN":
        $tokens = explode("=", $value);
        if (isset($tokens[0]))
            $json["Alphabetic"] = $tokens[0];
        if (isset($tokens[1]))
            $json["Ideographic"] = $tokens[1];
        if (isset($tokens[2]))
            $json["Phonetic"] = $tokens[2];
        break;
    case "OB":
    case "OD":
    case "OF":
    case "OL":
    case "OW":
    case "UN":
    case "":
        $attrName = "InlineBinary";
        $json[] = base64_encode($value);
        break;
    case "SQ":
        $err = "invalid to call dicomValueToJson() function for SQ elements!";
        die ($err);
        //$json[] = $err;
        break;
    default:
        $json[] = $value;
        break;
    }
    return $json;
}

function dicomValueToXml($key, $vr, &$value)
{
    $xml = sprintf("<DicomAttribute tag=%08X vr=\"%s\">", $key, $vr);
    switch (strtoupper($vr)) {
    case "PN":
        $xml .= "<PersonName number=\"1\">";
        $groups = array("Alphabetic", "Ideographic", "Phonetic");
        $tokens = explode("=", $value);
        for ($i = 0; $i < count($tokens) && $i < count($groups); $i++) {
            if (isset($tokens[$i]) && strlen($tokens[$i])) {
                $xml .= "<" . $groups[$i] . ">";
                $comps = explode("^", $tokens[$i]);
                if (isset($comps[0]))
                    $xml .= "<FamilyName>" . $comps[0] . "</FamilyName>";
                if (isset($comps[1]))
                    $xml .= "<GivenName>" . $comps[1] . "</GivenName>";
                if (isset($comps[2]))
                    $xml .= "<MiddleName>" . $comps[2] . "</MiddleName>";
                if (isset($comps[3]))
                    $xml .= "<NamePrefix>" . $comps[3] . "</NamePrefix>";
                if (isset($comps[4]))
                    $xml .= "<NameSuffix>" . $comps[4] . "</NameSuffix>";
                $xml .= "</" . $groups[$i] . ">";
            }
        }
        $xml .= "</PersonName>";
        break;
    case "OB":
    case "OD":
    case "OF":
    case "OL":
    case "OW":
    case "UN":
    case "":
        $xml .= "<InlineBinary>";
        $xml .= base64_encode($value);
        $xml .= "</InlineBinary>";
        break;
    case "SQ":
        $err = "invalid to call dicomValueToXml() function for SQ elements!";
        die ($err);
        //$xml .= "<Value number=\"1\">" . $err . "</Value>";
        break;
    default:
        $xml .= "<Value number=\"1\">";
        $xml .= $value;
        $xml .= "</Value>";
        break;
    }
    $xml .= "</DicomAttribute>";
    return $xml;
}

class BaseObject {

    function BaseObject() {
        //if(version_compare(PHP_VERSION,"5.0.0","<")) {
            $args = func_get_args();
            register_shutdown_function( array( &$this, '__destruct' ) );
            call_user_func_array( array( &$this, '__construct' ), $args );
        //}
    }
    function __construct() { }
    function __destruct() { }
	function getAttributeName($attr) {
		global $ATTR_TBL;
		return $ATTR_TBL[$attr]->name;
	}
}

class CNameType extends BaseObject {
	var $name;
	var $type;
	var $vr;

    function __construct($name, $type, $vr) {
		$this->name = $name;
		$this->type = $type;
		$this->vr = $vr;
	}
    function __destruct() { }
}

class ApplicationContext extends BaseObject {
    var $data;
    // constant definitions
    var $CONTEXT = '1.2.840.10008.3.1.1.1';

    function __construct() {
        $this->data = pack('a' . strlen($this->CONTEXT), $this->CONTEXT);
    }
    function __destruct() { }
    function getDataBuffer() {
        $header = pack('CCn', 0x10, 0, strlen($this->data));
        return ($header . $this->data);
    }
}

class AbstractSyntax extends BaseObject {
    var $uid;
    var $data;

    function __construct($uid) {
        $this->uid = $uid;
        $this->data = pack('a' . strlen($this->uid), $this->uid);
    }
    function __destruct() { }
    function getDataBuffer() {
        $header = pack('CCn', 0x30, 0, strlen($this->data));
        return ($header . $this->data);
    }
}

class TransferSyntax extends BaseObject {
    var $data;
    // constant definitions
    var $SYNTAX = '1.2.840.10008.1.2';

    function __construct() {
        $this->data = pack('a' . strlen($this->SYNTAX), $this->SYNTAX);
    }
    function __destruct() { }
    function getDataBuffer() {
        $header = pack('CCn', 0x40, 0, strlen($this->data));
        return ($header . $this->data);
    }
}

class PresentationContext extends BaseObject {
    var $ctxId;
    var $abstract;
    var $data;

    function __construct($abs) {
        global $sequence;
        // presentation context ID
        $this->ctxId = ($sequence++ * 2 + 1) % 256;
        $this->data = pack('C4', $this->ctxId, 0, 0, 0);
        // abstract syntax
        $this->abstract = $abs;
        $absSyntax = new AbstractSyntax($this->abstract);
        $this->data .= $absSyntax->getDataBuffer();
        // transfer syntax
        $xferSyntax = new TransferSyntax();
        $this->data .= $xferSyntax->getDataBuffer();
    }
    function __destruct() { }
    function getDataBuffer() {
        $header = pack('CCn', 0x20, 0, strlen($this->data));
        return ($header . $this->data);
    }
    function getContextId() {
        return $this->ctxId;
    }
}

class MaximumPduLength extends BaseObject {
    var $maxPduLen;
    var $data;

    function __construct() {
        global $MAX_PDU_LEN;
        $this->maxPduLen = $MAX_PDU_LEN;
        $this->data = pack('N', $this->maxPduLen);
    }
    function __destruct() { }
    function getDataBuffer() {
        $header = pack('CCn', 0x51, 0, strlen($this->data));
        return ($header . $this->data);
    }
}

class ImpClassUid extends BaseObject {
    var $data;
	// constant definitions
	var $PACSONE_UID = '1.2.826.0.1.3680043.2.737';

    function __construct() {
        $this->data = pack('a' . strlen($this->PACSONE_UID), $this->PACSONE_UID);
    }
    function __destruct() { }
    function getDataBuffer() {
        $header = pack('CCn', 0x52, 0, strlen($this->data));
        return ($header . $this->data);
    }
}

class UserInformation extends BaseObject {
    var $data;

    function __construct() {
        $maxLen = new MaximumPduLength();
        $this->data = $maxLen->getDataBuffer();
		$uid = new ImpClassUid();
		$this->data .= $uid->getDataBuffer();
    }
    function __destruct() { }
    function getDataBuffer() {
        $header = pack('CCn', 0x50, 0, strlen($this->data));
        return ($header . $this->data);
    }
}

class AssociateRequestPdu extends BaseObject {
    var $calledAe;
    var $callingAe;
    var $abstract = array();
    var $accepted = array();
    var $data;

    function __construct($sopClass, $called, $calling) {
        $this->calledAe = $called;
        $this->callingAe = $calling;
        // build content of ASSOCIATE-RQ pdu
        $this->data = pack('nn', 0x1, 0);
        // add called AE title
        $this->data .= pack('A16', $this->calledAe);
        // add calling AE title
        $this->data .= pack('A16', $this->callingAe);
        // add reserved 32-bytes
        $this->data .= pack('N8', 0, 0, 0, 0, 0, 0, 0, 0);
        // add Application Context
        $applContext = new ApplicationContext();
        $this->data .= $applContext->getDataBuffer();
        // add Presentation Context
        foreach ($sopClass as $abs) {
            $presContext = new PresentationContext($abs);
            $this->abstract[$abs] = $presContext->getContextId();
            $this->data .= $presContext->getDataBuffer();
        }
        // add User Information
        $userInfo = new UserInformation();
        $this->data .= $userInfo->getDataBuffer();
    }
    function __destruct() { }
    function getDataBuffer() {
        $header = pack('CCN', 0x1, 0, strlen($this->data));
        return ($header . $this->data);
    }
    function getPresentContextId() {
        return $this->accepted[0];
    }
    function getSopClass($ctxId) {
        return array_search($ctxId, $this->abstract);
    }
    function isAccepted(&$data, &$error) {
        $result = false;
        // skip all the way to Application Context
        $skip = 68;
        $appl = substr($data, $skip);
		#pacsone_dump($appl);
        // skip the Application Context
        $header = substr($appl, 0, 4);
		$length = 4;
        $array = unpack('Ctype/Cdummy/nlength', $header);
        $length += $array['length'];
        $present = substr($appl, $length);
		#pacsone_dump($present);
        // check the Presentation Context item
        do {
            $itemType = ord($present[0]);
            $ctxId = ord($present[4]);
            $reason = ord($present[6]);
            if ( ($itemType == 0x21) && in_array($ctxId, $this->abstract) &&
                 ($reason == 0) ) {
                // use the first accepted presentation context
                $this->accepted[] = $ctxId;
                $result = true;
                break;
            }
            $header = substr($present, 0, 4);
            $array = unpack('Ctype/Cdummy/nlength', $header);
            $length = $array['length'];
            $present = substr($present, 4+$length);
        } while ($itemType == 0x21);
        if (!$result) {
            $error = 'Association request rejected, reason = ' . $reason;
        }
        return $result;
    }
}

class CEchoPdv extends BaseObject {
    var $msgId;
    var $data;

    function __construct() {
        global $C_ECHO;
        global $sequence;
        // write Affected SOP Class UID
        $length = strlen($C_ECHO);
        if ($length & 0x1)
            $length++;
        $this->data = pack('v2Va' . $length, 0, 2, $length, $C_ECHO);
        // write Command Field
        $this->data .= pack('v2Vv', 0, 0x100, 2, 0x30);
        // write Message ID
        $id = $sequence++ & 0xffffffff;
        $this->msgId = $id;
        $this->data .= pack('v2Vv', 0, 0x110, 2, $id);
        // write Data Set Type
        $this->data .= pack('v2Vv', 0, 0x800, 2, 0x101);
        #pacsone_dump($this->data);
    }
    function __destruct() { }
    function getDataBuffer() {
        // write Group Length
        $header = pack('v2V2', 0, 0, 4, strlen($this->data));
        return ($header . $this->data);
    }
    function recvResponse(&$data, &$complete, &$error) {
        $result = false;
        // skip to Group Length
        $data = substr($data, 6);
        // skip Group Length
        $data = substr($data, 12);
        // some AE (GE AW) likes to include retire data element (0000,0001) here
        $header = substr($data, 0, 8);
        $array = unpack('vgroup/velement/Vlength', $header);
        $group = $array['group'];
        $element = $array['element'];
        if (($group == 0) && ($element == 1)) {
            $data = substr($data, 12);
            $header = substr($data, 0, 8);
            $array = unpack('vgroup/velement/Vlength', $header);
        }
        // skip Affected SOP Class UID
        $length = $array['length'];
        $data = substr($data, strlen($header) + $length);
        // check Command Field
        $header = substr($data, 0, 10);
        $array = unpack('vgroup/velement/Vsize/vcommand', $header);
        $command = $array['command'];
        if ($command != 0x8030) {
            $error = 'Invalid response received: command = ' . $command;
            return false;
        }
        $data = substr($data, strlen($header));
        // check Message ID
        $header = substr($data, 0, 10);
        $array = unpack('vgroup/velement/Vsize/vid', $header);
        $id = $array['id'];
        if ($id != $this->msgId) {
            $error = 'Message ID mismatch: received = ' . $id . ', expected = ' . $this->msgId;
            return false;
        }
        $data = substr($data, strlen($header));
        // skip Data Set type
        $data = substr($data, 10);
        // check Status
        $array = unpack('vgroup/velement/Vsize/vstatus', $data);
        $status = $array['status'];
        if ($status != 0) {
            $error = 'Command failed, response status = ' . $status;
        }
        else {
            $complete = true;
            $result = true;
        }
        return $result;
    }
}

class CMatchResult extends BaseObject {
	var $attrs = array();

    function __construct($data) {
        global $ATTR_TBL;
        $total = strlen($data);
        while ($total > 0) {
            $header = substr($data, 0, 8);
            $array = unpack('vgroup/velement/Vlength', $header);
            $group = $array['group'];
            $element = $array['element'];
            $key = $group << 16 | $element;
            $length = $array['length'];
            if (isset($ATTR_TBL[$key])) {
                $body = substr($data, strlen($header), $length);
                $format = $ATTR_TBL[$key]->type;
                if (strcasecmp($format[0], "A") == 0)
                    $format .= $length;
                $format .= 'value';
                $array = unpack($format, $body);
                $value = isset($array['value'])? $array['value'] : "";
                // save this attribute
                $this->attrs[$key] = trim($value);
            }
            // next attribute
            $bytes = strlen($header) + $length;
            $data = substr($data, $bytes);
            $total -= $bytes;
        }
    }
    function __destruct() { }
	function hasKey($key) {
		return isset($this->attrs[$key]);
	}
	function getQueryLevel() {
		return $this->attrs[0x00080052];
	}
	function getPatientId() {
		return $this->attrs[0x00100020];
	}
	function getStudyUid() {
		return $this->attrs[0x0020000d];
	}
	function getSeriesUid() {
		return $this->attrs[0x0020000e];
	}
	function getSopInstanceUid() {
		return $this->attrs[0x00080018];
	}
}

class CFailedSopInstances extends BaseObject {
	var $list = array();

    function __construct($data) {
        $header = substr($data, 0, 8);
        $array = unpack('vgroup/velement/Vlength', $header);
        $length = $array['length'];
		$body = substr($data, strlen($header), $length);
		$array = unpack('a' . $length . 'value', $body);
		$value = isset($array['value'])? $array['value'] : "";
		// split the '\' separated UIDs
		$this->list = explode("\\", $value);
	}
    function __destruct() { }
}

class DicomObject extends BaseObject {
    var $bigEnd = false;
    // Little-Endian to Big-Endian conversion
    var $convertEndTbl = array(
        "v" => "n",
        "V" => "N",
    );
    function __construct() { }
    function __destruct() { }
    function convertEndian($format) {
        if ($this->bigEnd && isset($this->convertEndTbl[$format]))
            $format = $this->convertEndTbl[$format] ;
        return $format;
    }
}

class Fragment extends DicomObject {
    var $data;
    var $length;
    var $bigEndian;

    function __construct($data, $length, $bigEndian) {
        $this->data = substr($data, 0, $length);
        $this->length = $length;
        $this->bigEndian = $bigEndian;
    }
    function __destruct() { }
}

class PixelData extends DicomObject {
	var $fragments = array();
	var $length;
	var $encaped = false;

    function __construct($total, $data, $bigEndian) {
        global $UNDEF_LEN;
        $count = 0;
        $this->bigEnd = $bigEndian;
        if ($total != $UNDEF_LEN) { // un-encapsulated
            $this->length = $total;
            $fragment = new Fragment($data, $total, $bigEndian);
            $this->fragments[] = $fragment;
            return;
        }
        $this->encaped = true;
		$endSequence = 0;
        while (!$endSequence) {
            $header = substr($data, 0, 8);
            $format = $this->bigEnd? 'ngroup/nelement/Nlength' : 'vgroup/velement/Vlength';
            $array = unpack($format, $header);
            $group = $array['group'];
            $element = $array['element'];
            $length = $array['length'];
            //print "Pixel Data item: Group = " . dechex($group) . " Element = " . dechex($element) . " Length = $length Format = $format<br>";
            $body = substr($data, strlen($header));
			if (($group == 0xFFFE) && ($element == 0xE000)) {			// item
                if (!$count && $length) {   // parse the Basic Offset Table
                    $numFrags = $length / 4;
                    $tbl = substr($body, 0, $length);
                    $format = sprintf("V%doffset", $numFrags);
                    $offsets = unpack($format, $tbl);
                    if (count($offsets) != $numFrags)
                        die("Pixel Data: failed to parse Basic Offset Table");
                    $bytes = strlen($header) + $length;
                    $start = substr($data, $bytes);
			        $count += $bytes;
                    // parse the fragments by Basic Offset Table
                    for ($i = 0; $i < $numFrags; $i++) {
                        $index = sprintf("offset%d", $i+1);
                        $data = substr($start, $offsets[$index]);
                        $header = substr($data, 0, 8);
                        $array = unpack('vgroup/velement/Vlength', $header);
                        $group = $array['group'];
                        $element = $array['element'];
			            if (($group != 0xFFFE) || ($element != 0xE000)) // sanity check
                            die("Pixel Data: corrupted Basic Offet Table!");
                        $length = $array['length'];
                        //print "Basic Offset Table: Group = " . dechex($group) . " Element = " . dechex($element) . " Length = $length Format = $format<br>";
                        $body = substr($data, strlen($header));
                        // add this fragment
                        $fragment = new Fragment($body, $length, $bigEndian);
				        $this->fragments[] = $fragment;
                        $bytes = strlen($header) + $length;
			            $count += $bytes;
                    }
		            $this->length = $count;
                    return;
                } else if ($length) {
                    $fragment = new Fragment($body, $length, $bigEndian);
				    $this->fragments[] = $fragment;
                }
			} else if (($group == 0xFFFE) && ($element == 0xE0DD)) {	// sequence delimeter
				$endSequence = 1;
				// length should be 4 and the value should be 0
				if ($length != 0)
					die ("Protocol error: pixel data sequence delimeter length is not zero!");
			}
            // next item
            $bytes = strlen($header) + $length;
            $data = substr($data, $bytes);
            $total -= $bytes;
			$count += $bytes;
        }
		$this->length = $count;
	}
    function __destruct() { }
    function isEncapsulated() {
		return $this->encaped;
	}
    function getTag() {
		return 0x7FE00010;
	}
    function getLength() {
		return $this->length;
	}
	function showDebug() {
        $endian = $this->fragments[0]->bigEndian? "Big" : "Little";
		printf("<b>Pixel Data Attribute: %d bytes %s Endian<br></b>", $this->length, $endian);
        if ($this->encaped) {
            $count = 1;
            foreach ($this->fragments as $fragment) {
                $endian = $fragment->bigEndian? "Big" : "Little";
			    printf("Fragment %d: %d bytes %s Endian<br>", $count, $fragment->length, $endian);
                $count++;
		    }
		    print "<b>End of Pixel Data Attribute.<br></b>";
        }
	}
    function numberOfFragments() {
        return count($this->fragments);
    }
    function getFragment($index) {  // 1-based index
        $fragment = false;
        if ($index <= count($this->fragments))
            $fragment = $this->fragments[$index - 1];
        return $fragment;
    }
    function getUncompressedFrameData($index, $rows, $columns, $samplesPerPixel, $bitsAlloc) {  // 1-based index
        $data = $this->fragments[0]->data;
        $bytesPerPixel = $bitsAlloc / 8;
        $page = $rows * $columns * $samplesPerPixel * $bytesPerPixel;
        return substr($data, ($index - 1) * $page, $page);
    }
}

class Sequence extends DicomObject {
	var $items = array();
	var $tag;
	var $length;

    function __construct($key, $total, &$data, $explicit, $bigEndian) {
        global $UNDEF_LEN;
        $count = 0;
		$this->tag = $key;
        $this->bigEnd = $bigEndian;
		$endSequence = 0;
        while (!$endSequence && ($total > 0)) {
            $header = substr($data, 0, 8);
            $format = $this->bigEnd? 'ngroup/nelement/Nlength' : 'vgroup/velement/Vlength';
            $array = unpack($format, $header);
            $group = $array['group'];
            $element = $array['element'];
            $length = $array['length'];
            //print "Sequence item: Group = " . dechex($group) . " Element = " . dechex($element) . " Length = $length Format = $format<br>";
            $body = substr($data, strlen($header));
			if (($group == 0xFFFE) && ($element == 0xE000)) {			// item
				if ($length == $UNDEF_LEN)
					$length = strlen($body);
                $item = new Item($body, $length, $explicit, $bigEndian);
				$length = $item->getLength();
				$this->items[] = $item;
			} else if (($group == 0xFFFE) && ($element == 0xE0DD)) {	// sequence delimeter
				$endSequence = 1;
				// length should be 4 and the value should be 0
				if ($length != 0)
					die ("Protocol error: sequence delimeter length is not zero!");
			}
            // next item
            $bytes = strlen($header) + $length;
            $data = substr($data, $bytes);
            $total -= $bytes;
			$count += $bytes;
        }
		$this->length = $count;
	}
    function __destruct() { }
    function getTag() {
		return $this->tag;
	}
    function getLength() {
		return $this->length;
	}
	function hasKey($key) {
        foreach ($this->items as $item) {
            if ($item->hasKey($key))
                return true;
        }
        return false;
    }
    function getAttr($key) {
        $value = "";
        foreach ($this->items as $item) {
            if ($item->hasKey($key)) {
                $value = $item->getAttr($key);
                break;
            }
        }
        return $value;
    }
    function getItem($key) {
        $value = "";
        foreach ($this->items as $item) {
            if ($item->hasKey($key)) {
                $value = $item->getItem($key);
                break;
            }
        }
        return $value;
    }
	function showDebug() {
		print "<b>Sequence Attribute (" . dechex($this->tag) . "):<br></b>";
        foreach ($this->items as $item) {
			print "Sequence Item:<br>";
			$item->showDebug();
			print "End of Sequence Item<br>";
		}
		print "<b>End of Sequence Attribute (" . dechex($this->tag) . ").<br></b>";
	}
    function isEmpty() {
        return count($this->items)? false : true;
    }
    function toJson() {
        $json = array(
            "vr"        => "SQ",
            "Value"     => array(),
        );
        foreach ($this->items as $item) {
            $json["Value"][] = $item->toJson();
        }
        return $json;
    }
    function toXml() {
        $xml = sprintf("<DicomAttribute tag=%08X vr=\"SQ\">", $this->tag);
        $index = 1;
        foreach ($this->items as $item) {
            $xml .= sprintf("<Item number=\"%d\"", $index);
            $xml .= $item->toXml();
            $index++;
            $xml .= "</Item>";
        }
        $xml .= "</DicomAttribute>";
        return $xml;
    }
}

class Item extends DicomObject {
	var $attrs = array();
	var $length;

    function __construct(&$data, $total, $explicit, $bigEndian) {
		global $ATTR_TBL;
		global $EXPLICIT_VR_TBL;
        global $UNDEF_LEN;
        $this->bigEnd = $bigEndian;
		$count = 0;
		$endItem = 0;
        while (!$endItem && ($total > 0)) {
            $header = substr($data, 0, 4);
            $format = $this->bigEnd? 'ngroup/nelement' : 'vgroup/velement';
            $array = unpack($format, $header);
            $group = $array['group'];
            $element = $array['element'];
			$key = ($group << 16) + $element;
			if (($group == 0xFFFE) && ($element == 0xE00D)) {	// item delimeter
                $value = substr($data, strlen($header), 4);
                $format = $this->bigEnd? 'Nlength' : 'Vlength';
                $array = unpack($format, $value);
                $length = $array['length'];
                $header .= $value;
				$endItem = 1;
				// length should be 4 and the value should be 0
				if ($length != 0)
					die ("Protocol error: item delimeter length is not zero!");
			} else {
                if ($explicit) {
                    $value = substr($data, strlen($header), 2);
        	        $array = unpack('A2vr', $value);
			        $vr = $array['vr'];
                    $header .= $value;
                    if (isset($EXPLICIT_VR_TBL[$vr])) {
                        $format = $this->bigEnd? "nreserved/Nlength" : "vreserved/Vlength";
                        $offset = 6;
                    } else {
                        $format = $this->bigEnd? "nlength" : "vlength";
                        $offset = 2;
                    }
                    $value = substr($data, strlen($header), $offset);
                    $array = unpack($format, $value);
                    $length = $array['length'];
                    $header .= $value;
                } else {
                    $value = substr($data, strlen($header), 4);
                    $format = $this->bigEnd? 'Nlength' : 'Vlength';
                    $array = unpack($format, $value);
                    $length = $array['length'];
                    $header .= $value;
                }
				if (isset($ATTR_TBL[$key]))
					$format = $this->convertEndian($ATTR_TBL[$key]->type);
				else
					$format = ($element == 0)? $this->convertEndian("V") : "A";
				//print "Item Key = " . dechex($key) . " Length = $length Format = $format<br>";
				$body = substr($data, strlen($header));
				if ( ($length == $UNDEF_LEN) || (isset($vr) && !strcasecmp($vr, "SQ")) ||
                     (strcasecmp($format[0], "S") == 0) ) {		// sequence
					if ($length == $UNDEF_LEN)
						$length = strlen($body);
					$this->attrs[$key] = new Sequence($key, $length, $body, $explicit, $bigEndian);
					$length = $this->attrs[$key]->getLength();
				} else {
					if (strcasecmp($format[0], "A") == 0)
						$format .= $length;
					$format .= 'value';
					$array = unpack($format, $body);
			        $value = isset($array['value'])? trim($array['value']) : "";
					// save this attribute
                    if (!isset($vr))
                        $vr = isset($ATTR_TBL[$key])? $ATTR_TBL[$key]->vr : "";
					$this->attrs[$key] = array("vr" => $vr, "value" => $value);
				}
			}
            // next attribute
            $bytes = strlen($header) + $length;
            $data = substr($data, $bytes);
            $total -= $bytes;
            $count += $bytes;
        }
		$this->length = $count;
	}
    function __destruct() { }
	function hasKey($key) {
		if (isset($this->attrs[$key]))
            return true;
        foreach ($this->attrs as $attr) {
            if (is_a($attr, 'Sequence') && $attr->hasKey($key))
                return true;
        }
        return false;
	}
    function getLength() {
		return $this->length;
	}
    function getAttr($key) {
        if (!isset($this->attrs[$key]))
            return "";
        $obj = $this->attrs[$key];
        if (is_a($obj, 'Sequence')) {
            $value = $obj->getAttr($key);
        } else {
            $value = $obj["value"];
        }
		return $value;
	}
    function getItem($key) {
		if (isset($this->attrs[$key]))
		    return $this->attrs[$key];
        foreach ($this->attrs as $attr) {
            if (is_a($attr, 'Sequence') && $attr->hasKey($key))
                return $attr->getItem($key);
        }
        return false;
	}
	function showDebug() {
        foreach ($this->attrs as $key => $attr) {
            if (is_a($attr, 'Sequence'))
				$attr->showDebug();
			else
				print "Attribute (" . dechex($key) . "): Value = " . $attr["value"] . "<br>";
		}
	}
    function toJson() {
        $json = array();
        foreach ($this->attrs as $key => $attr) {
            if (is_a($attr, 'Sequence')) {
                $json[ sprintf("%08X", $key) ] =  $attr->toJson();
            } else {
                $vr = $attr["vr"];
                $attrName = "Value";
                $value = dicomValueToJson($vr, $attrName, $attr["value"]);
                $json[ sprintf("%08X", $key) ] = array(
                    "vr"        => $vr,
                    $attrName   => $value,
                );
            }
        }
        return $json;
    }
    function toXml() {
        $xml = "";
        foreach ($this->attrs as $key => $attr) {
            if (is_a($attr, 'Sequence')) {
                $xml .= $attr->toXml();
            } else {
                $xml .= dicomValueToXml($key, $attr["vr"], $attr["value"]);
            }
        }
        return $xml;
    }
}

class CAttributeList extends BaseObject {
	var $attrs = array();

    function __construct($data) {
		global $ATTR_TBL;
        $total = strlen($data);
        while ($total > 0) {
            $header = substr($data, 0, 8);
            $array = unpack('vgroup/velement/Vlength', $header);
            $group = $array['group'];
            $element = $array['element'];
			$key = $group << 16 | $element;
            $length = $array['length'];
			$body = substr($data, strlen($header), $length);
			$format = $ATTR_TBL[$key]->type;
			if (strcasecmp($format[0], "A") == 0)
				$format .= $length;
			$format .= 'value';
			$array = unpack($format, $body);
			$value = isset($array['value'])? $array['value'] : "";
			// save this attribute
			$this->attrs[$key] = trim($value);
            // next attribute
            $bytes = strlen($header) + $length;
            $data = substr($data, $bytes);
            $total -= $bytes;
        }
	}
    function __destruct() { }
}

class CFindPdv extends BaseObject {
    var $finalStatus;
    var $msgId;
    var $data;
	// C-FIND response status table
	var $STATUS_TBL = array (
		0xA700 => "Refused: Out of Resources",
		0xA900 => "Failed: Identifier does not match SOP class",
		0xFE00 => "Cancel: Matching terminated due to Cancel request",
	);

    function __construct($sopClass) {
        global $sequence;
        // write Affected SOP Class UID
        $length = strlen($sopClass);
        if ($length & 0x1)
            $length++;
        $this->data = pack('v2Va' . $length, 0, 2, $length, $sopClass);
        // write Command Field
        $this->data .= pack('v2Vv', 0, 0x100, 2, 0x20);
        // write Message ID
        $id = $sequence++ & 0xffffffff;
        $this->msgId = $id;
        $this->data .= pack('v2Vv', 0, 0x110, 2, $id);
        // write Priority
        $this->data .= pack('v2Vv', 0, 0x700, 2, 0);
        // write Data Set Type
        $this->data .= pack('v2Vv', 0, 0x800, 2, 0);
        #pacsone_dump($this->data);
		$this->finalStatus = 0xFF00;
    }
    function __destruct() { }
    function getDataBuffer() {
        // write Group Length
        $header = pack('v2V2', 0, 0, 4, strlen($this->data));
        return ($header . $this->data);
    }
    function recvResponse(&$data, &$complete, &$error) {
		// check PDV header to see if it's a Command or Dataset
		$msgHdr = ord($data[5]);
        // skip to Group Length
        $data = substr($data, 6);
		if ($msgHdr & 0x1) {
			$result = $this->recvCmdResponse($data, $error);
		} else {
			$result = $this->recvDataset($data, $error);
		}
		if (($this->finalStatus & 0xFFFE) != 0xFF00) {
			if ($this->finalStatus)
				$error = "C-FIND Response: " . $this->STATUS_TBL[ $this->finalStatus ];
			$complete = true;
		}
		return $result;
	}
    function recvCmdResponse(&$data, &$error) {
        $result = true;
        // skip Group Length
        $data = substr($data, 12);
        // some AE (GE AW) likes to include retire data element (0000,0001) here
        $header = substr($data, 0, 8);
        $array = unpack('vgroup/velement/Vlength', $header);
        $group = $array['group'];
        $element = $array['element'];
        if (($group == 0) && ($element == 1)) {
            $data = substr($data, 12);
            $header = substr($data, 0, 8);
            $array = unpack('vgroup/velement/Vlength', $header);
        }
        // skip Affected SOP Class UID
        $length = $array['length'];
        $data = substr($data, strlen($header) + $length);
        // check Command Field
        $header = substr($data, 0, 10);
        $array = unpack('vgroup/velement/Vsize/vcommand', $header);
        $command = $array['command'];
        if ($command != 0x8020) {
            $error = 'Invalid response received: command = ' . $command;
            return false;
        }
        $data = substr($data, strlen($header));
        // check Message ID Being Responded to
        $header = substr($data, 0, 10);
        $array = unpack('vgroup/velement/Vsize/vid', $header);
        // some applications (eFilm) inserts the non-conforming Message ID here
        $group = $array['group'];
        $element = $array['element'];
        if (($group == 0) && ($element == 0x110)) {
            $data = substr($data, strlen($header));
            $header = substr($data, 0, 10);
            $array = unpack('vgroup/velement/Vsize/vid', $header);
        }
        $id = $array['id'];
        if ($id != $this->msgId) {
            $error = 'Message ID mismatch: received = ' . $id . ', expected = ' . $this->msgId;
            return false;
        }
        $data = substr($data, strlen($header));
        // check Data Set type
        $header = substr($data, 0, 10);
        $array = unpack('vgroup/velement/Vsize/vtype', $header);
        $group = $array['group'];
        $element = $array['element'];
        // some applications (eFilm) inserts the non-conforming Priority here
        if (($group == 0) && ($element == 0x700)) {
            $data = substr($data, strlen($header));
            $header = substr($data, 0, 10);
            $array = unpack('vgroup/velement/Vsize/vtype', $header);
            $group = $array['group'];
            $element = $array['element'];
        }
		if ($group != 0 or $element != 0x800) {
            $error = "Invalid Dataset Type: group = " . dechex($group);
			$error .= " element = " . dechex($element);
            return false;
		}
        $type = $array['type'];
        $data = substr($data, strlen($header));
        // check Status
        $header = substr($data, 0, 10);
        $array = unpack('vgroup/velement/Vsize/vstatus', $header);
        $status = $array['status'];
        if (($status & 0xFFFE) != 0xFF00) {
			$this->finalStatus = $status;
            $result = false;
        }
        // parse returned Identifier
        if (($type != 0x101) && (strlen($data) > strlen($header))) {   // dataset present
            $data = substr($data, strlen($header));
        	// skip the Data Set PDV header
        	$data = substr($data, 6);
			$result = $this->recvDataset($data, $error);
        }
        return $result;
    }
    function recvDataset(&$data, &$error) {
        if (!strlen($data))
            return false;
		$result = new CMatchResult($data);
		return $result;
	}
}

class WorklistFindPdv extends BaseObject {
    var $finalStatus;
    var $msgId;
    var $data;
	// Modality Worklist-FIND response status table
	var $STATUS_TBL = array (
		0xA700 => "Refused: Out of Resources",
		0xA900 => "Failed: Identifier does not match SOP class",
		0xFE00 => "Cancel: Matching terminated due to Cancel request",
	);

    function __construct() {
        global $WORKLIST_FIND;
        global $sequence;
        // write Affected SOP Class UID
        $length = strlen($WORKLIST_FIND);
        if ($length & 0x1)
            $length++;
        $this->data = pack('v2Va' . $length, 0, 2, $length, $WORKLIST_FIND);
        // write Command Field
        $this->data .= pack('v2Vv', 0, 0x100, 2, 0x20);
        // write Message ID
        $id = $sequence++ & 0xffffffff;
        $this->msgId = $id;
        $this->data .= pack('v2Vv', 0, 0x110, 2, $id);
        // write Priority
        $this->data .= pack('v2Vv', 0, 0x700, 2, 0);
        // write Data Set Type
        $this->data .= pack('v2Vv', 0, 0x800, 2, 0);
        #pacsone_dump($this->data);
		$this->finalStatus = 0xFF00;
    }
    function __destruct() { }
    function getDataBuffer() {
        // write Group Length
        $header = pack('v2V2', 0, 0, 4, strlen($this->data));
        return ($header . $this->data);
    }
    function recvResponse(&$data, &$complete, &$error) {
		// check PDV header to see if it's a Command or Dataset
		$msgHdr = ord($data[5]);
        // skip to Group Length
        $data = substr($data, 6);
		if ($msgHdr & 0x1) {
			$result = $this->recvCmdResponse($data, $error);
		} else {
			$result = $this->recvDataset($data, $error);
		}
		if (($this->finalStatus & 0xFFFE) != 0xFF00) {
			if ($this->finalStatus)
				$error = "ModalityWorklist-FIND Response: " . $this->STATUS_TBL[ $this->finalStatus ];
			$complete = true;
		}
		return $result;
	}
    function recvCmdResponse(&$data, &$error) {
        $result = false;
        // skip Group Length
        $data = substr($data, 12);
        // some AE (GE AW) likes to include retire data element (0000,0001) here
        $header = substr($data, 0, 8);
        $array = unpack('vgroup/velement/Vlength', $header);
        $group = $array['group'];
        $element = $array['element'];
        if (($group == 0) && ($element == 1)) {
            $data = substr($data, 12);
            $header = substr($data, 0, 8);
            $array = unpack('vgroup/velement/Vlength', $header);
        }
        // skip Affected SOP Class UID
        $length = $array['length'];
        $data = substr($data, strlen($header) + $length);
        // check Command Field
        $header = substr($data, 0, 10);
        $array = unpack('vgroup/velement/Vsize/vcommand', $header);
        $command = $array['command'];
        if ($command != 0x8020) {
            $error = 'Invalid response received: command = ' . $command;
            return false;
        }
        $data = substr($data, strlen($header));
        // check Message ID
        $header = substr($data, 0, 10);
        $array = unpack('vgroup/velement/Vsize/vid', $header);
        $id = $array['id'];
        if ($id != $this->msgId) {
            $error = 'Message ID mismatch: received = ' . $id . ', expected = ' . $this->msgId;
            return false;
        }
        $data = substr($data, strlen($header));
        // check Data Set type
        $header = substr($data, 0, 10);
        $array = unpack('vgroup/velement/Vsize/vtype', $header);
        $group = $array['group'];
        $element = $array['element'];
		if ($group != 0 or $element != 0x800) {
            $error = "Invalid Dataset Type: group = " . dechex($group);
			$error .= " element = " . dechex($element);
            return false;
		}
        $type = $array['type'];
        $data = substr($data, strlen($header));
        // check Status
        $header = substr($data, 0, 10);
        $array = unpack('vgroup/velement/Vsize/vstatus', $header);
        $status = $array['status'];
        if (($status & 0xFFFE) != 0xFF00) {
			$this->finalStatus = $status;
            $result = false;
        }
        $data = substr($data, strlen($header));
        // parse returned Identifier
        if (($type != 0x101) && strlen($data)) {   // dataset present
        	// skip the Data Set PDV header
        	$data = substr($data, 6);
			$result = $this->recvDataset($data, $error);
        }
        return $result;
    }
    function recvDataset(&$data, &$error) {
		$result = new Item($data, strlen($data), false, false);
		return $result;
	}
}

class GetPrinterPdv extends BaseObject {
    var $msgId;
    var $data;

    function __construct() {
        global $sequence;
        // write Affected SOP Class UID
        $uid = "1.2.840.10008.5.1.1.16";
        $length = strlen($uid);
        if ($length & 0x1)
            $length++;
        $this->data = pack('v2Va' . $length, 0, 3, $length, $uid);
        // write Command Field
        $this->data .= pack('v2Vv', 0, 0x100, 2, 0x0110);
        // write Message ID
        $id = $sequence++ & 0xffffffff;
        $this->msgId = $id;
        $this->data .= pack('v2Vv', 0, 0x110, 2, $id);
        // write Data Set Type
        $this->data .= pack('v2Vv', 0, 0x800, 2, 0x0101);
        // write Requested SOP Instance UID
        $uid = "1.2.840.10008.5.1.1.17";
        $length = strlen($uid);
        if ($length & 0x1)
            $length++;
        $this->data .= pack('v2Va' . $length, 0, 0x1001, $length, $uid);
        #pacsone_dump($this->data);
    }
    function __destruct() { }
    function getDataBuffer() {
        // write Group Length
        $header = pack('v2V2', 0, 0, 4, strlen($this->data));
        return ($header . $this->data);
    }
    function recvResponse(&$data, &$complete, &$error) {
		// check PDV header to see if it's a Command or Dataset
		$msgHdr = ord($data[5]);
        // skip to Group Length
        $data = substr($data, 6);
		if ($msgHdr & 0x1) {
			$result = $this->recvCmdResponse($data, $error, $complete);
		} else {
			$result = $this->recvDataset($data, $error);
			$complete = true;
		}
		return $result;
	}
    function recvCmdResponse(&$data, &$error, &$complete) {
        $result = false;
        // skip Group Length
        $data = substr($data, 12);
        // some AE (GE AW) likes to include retire data element (0000,0001) here
        $header = substr($data, 0, 8);
        $array = unpack('vgroup/velement/Vlength', $header);
        $group = $array['group'];
        $element = $array['element'];
        if (($group == 0) && ($element == 1)) {
            $data = substr($data, 12);
            $header = substr($data, 0, 8);
            $array = unpack('vgroup/velement/Vlength', $header);
        }
        // skip Affected SOP Class UID
        $length = $array['length'];
        $data = substr($data, strlen($header) + $length);
        // check Command Field
        $header = substr($data, 0, 10);
        $array = unpack('vgroup/velement/Vsize/vcommand', $header);
        $command = $array['command'];
        if ($command != 0x8110) {
            $error = 'Invalid response received: command = ' . $command;
            return false;
        }
        $data = substr($data, strlen($header));
        // check Message ID
        $header = substr($data, 0, 10);
        $array = unpack('vgroup/velement/Vsize/vid', $header);
        $id = $array['id'];
        if ($id != $this->msgId) {
            $error = 'Message ID mismatch: received = ' . $id . ', expected = ' . $this->msgId;
            return false;
        }
        $data = substr($data, strlen($header));
        // check Data Set type
        $header = substr($data, 0, 10);
        $array = unpack('vgroup/velement/Vsize/vtype', $header);
        $group = $array['group'];
        $element = $array['element'];
		if ($group != 0 or $element != 0x800) {
            $error = "Invalid Dataset Type: group = " . dechex($group);
			$error .= " element = " . dechex($element);
            return false;
		}
        $type = $array['type'];
        if ($type == 0x101) {
            $complete = true;
        }
        $data = substr($data, strlen($header));
        // check Status
        $header = substr($data, 0, 10);
        $array = unpack('vgroup/velement/Vsize/vstatus', $header);
        $status = $array['status'];
        if ($status != 0) {
			$error = "N-Get Response: $status";
            $result = false;
        }
        $data = substr($data, strlen($header));
        // skip Affected SOP Instance UID
        $header = substr($data, 0, 8);
        $array = unpack('vgroup/velement/Vlength', $header);
        $length = $array['length'];
        $data = substr($data, strlen($header) + $length);
        // parse returned Identifier
        if (($type != 0x101) && strlen($data)) {   // dataset present
        	// skip the Data Set PDV header
        	$data = substr($data, 6);
			$result = $this->recvDataset($data, $error);
        }
        return $result;
    }
    function recvDataset(&$data, &$error) {
		$result = new CAttributeList($data);
		return $result;
	}
}

class CMovePdv extends BaseObject {
    var $msgId;
    var $finalStatus;
    var $numRemaining;
    var $numCompleted;
    var $numFailed;
    var $numWarning;
    var $data;
	// C-MOVE response status table
	var $STATUS_TBL = array (
		0xA701 => "Refused: Out of Resources - Unable to calculate number of matches",
		0xA702 => "Refused: Out of Resources - Unable to perform sub-operations",
		0xA801 => "Refused: Move Destination Unknown",
		0xA900 => "Failed: Identifier does not match SOP class",
		0xFE00 => "Cancel: Sub-operations terminated due to Cancel indication",
		0xB000 => "Warning: Sub-operations Complete - One or more failures",
	);

    function __construct($sopClass, $dest) {
        global $sequence;
        // write Affected SOP Class UID
        $length = strlen($sopClass);
        if ($length & 0x1)
            $length++;
        $this->data = pack('v2Va' . $length, 0, 2, $length, $sopClass);
        // write Command Field
        $this->data .= pack('v2Vv', 0, 0x100, 2, 0x21);
        // write Message ID
        $id = $sequence++ & 0xffffffff;
        $this->msgId = $id;
        $this->data .= pack('v2Vv', 0, 0x110, 2, $id);
		// write Move Destination
		$length = strlen($dest);
		if ($length & 0x1)
			$length++;
		$this->data .= pack('v2VA' . $length, 0, 0x600, $length, $dest);
        // write Priority
        $this->data .= pack('v2Vv', 0, 0x700, 2, 0);
        // write Data Set Type
        $this->data .= pack('v2Vv', 0, 0x800, 2, 0);
        #pacsone_dump($this->data);
		$this->finalStatus = 0xFF00;
		$this->numRemaining = 0;
		$this->numCompleted = 0;
		$this->numFailed = 0;
		$this->numWarning = 0;
    }
    function __destruct() { }
    function getDataBuffer() {
        // write Group Length
        $header = pack('v2V2', 0, 0, 4, strlen($this->data));
        return ($header . $this->data);
    }
    function recvResponse(&$data, &$complete, &$error) {
		// check PDV header to see if it's a Command or Dataset
		$msgHdr = ord($data[5]);
        // skip to Group Length
        $data = substr($data, 6);
		if ($msgHdr & 0x1) {
			$result = $this->recvCmdResponse($data, $error);
		} else {
			$result = $this->recvDataset($data, $error);
		}
		if ($this->finalStatus != 0xFF00) {
			if ($this->finalStatus) {
				$error = "C-MOVE Response: ";
				if (($this->finalStatus & 0xC000) == 0xC000)
					$error .= "Failed: Unable to Process";
				else
					$error .= $this->STATUS_TBL[ $this->finalStatus ];
			}
			$complete = true;
		}
		return $result;
	}
    function recvCmdResponse(&$data, &$error) {
        $result = true;
        // skip Group Length
        $data = substr($data, 12);
        // some AE (GE AW) likes to include retire data element (0000,0001) here
        $header = substr($data, 0, 8);
        $array = unpack('vgroup/velement/Vlength', $header);
        $group = $array['group'];
        $element = $array['element'];
        if (($group == 0) && ($element == 1)) {
            $data = substr($data, 12);
            $header = substr($data, 0, 8);
            $array = unpack('vgroup/velement/Vlength', $header);
        }
        // skip Affected SOP Class UID
        $length = $array['length'];
        $data = substr($data, strlen($header) + $length);
        // check Command Field
        $header = substr($data, 0, 10);
        $array = unpack('vgroup/velement/Vsize/vcommand', $header);
        $command = $array['command'];
        if ($command != 0x8021) {
            $error = 'Invalid response received: command = ' . $command;
            return false;
        }
        $data = substr($data, strlen($header));
        // check Message ID
        $header = substr($data, 0, 10);
        $array = unpack('vgroup/velement/Vsize/vid', $header);
        $id = $array['id'];
        if ($id != $this->msgId) {
            $error = 'Message ID mismatch: received = ' . $id . ', expected = ' . $this->msgId;
            return false;
        }
        $data = substr($data, strlen($header));
        // check Data Set type
        $header = substr($data, 0, 10);
        $array = unpack('vgroup/velement/Vsize/vtype', $header);
        $type = $array['type'];
        $data = substr($data, strlen($header));
        // check Status
        $header = substr($data, 0, 10);
        $array = unpack('vgroup/velement/Vsize/vstatus', $header);
        $status = $array['status'];
        $data = substr($data, strlen($header));
		// check Number of Remaining Sub-operations
		// check Number of Completed Sub-operations
		// check Number of Failed Sub-operations
		// check Number of Warning Sub-operations
        while (strlen($data)) {
            $header = substr($data, 0, 10);
            $array = unpack('vgroup/velement/Vsize/vnumber', $header);
            $group = $array['group'];
            $element = $array['element'];
            switch ($element) {
            case 0x1020:
                $this->numRemaining = $array['number'];
                break;
            case 0x1021:
                $this->numCompleted = $array['number'];
                break;
            case 0x1022:
                $this->numFailed = $array['number'];
                break;
            case 0x1023:
                $this->numWarning = $array['number'];
                break;
            default:
                $group = 1;
                break;
            }
            if ($group != 0x0)
                break;
            $data = substr($data, strlen($header));
        }
		// check final status
        if ($status != 0xFF00) {
			$this->finalStatus = $status;
            $result = false;
        }
        // parse returned Identifier
        if ($type != 0x101) {   // dataset present
        	// skip the Data Set PDV header
        	$data = substr($data, 6);
            // skip empty dataset returned by Siemens MagicView
            if (strlen($data))
			    $result = $this->recvDataset($data, $error);
            else
                $result = false;
        }
        return $result;
    }
    function recvDataset(&$data, &$error) {
		// the Dataset should contain a list of failed SOP instances
		$result = new CFailedSopInstances($data);
		return $result;
	}
}

class CFindIdentifierRoot extends BaseObject {
    var $attrs = array();
    var $data;
    var $id;
    var $last;
    var $first;
    var $instName;
    var $optionalKeys;

    function __construct($key) {
        $this->id = $key["Patient ID"];
        $this->last = $key["Last Name"];
        $this->first = $key["First Name"];
        $this->instName = isset($key["Institution Name"])? $key["Institution Name"] : "";
        $this->optionalKeys = true;
        // add Query/Retrieve Level
        $level = 'PATIENT';
        $length = strlen($level);
        if ($length & 0x1)
            $length++;
        $this->data = pack('v2VA' . $length, 0x8, 0x52, $length, $level);
		$this->attrs[] = 0x00080052;
        // add Insittution Name
		$length = strlen($this->instName);
		if ($length & 0x1)
            $length++;
        $this->data .= pack('v2V', 0x8, 0x80, $length);
		if ($length)
            $this->data .= pack('A' . $length, $this->instName);
		$this->attrs[] = 0x00080080;
        // add Patient Name
		$fullname = "";
		if (strlen($this->last) || strlen($this->first))
			$fullname = $this->last . "^" . $this->first;
		$length = strlen($fullname);
		if ($length & 0x1)
			$length++;
        $this->data .= pack('v2V', 0x10, 0x10, $length);
		if ($length)
			$this->data .= pack('A' . $length, $fullname);
		$this->attrs[] = 0x00100010;
        // add Patient ID
		$length = strlen($this->id);
		if ($length & 0x1)
			$length++;
        $this->data .= pack('v2V', 0x10, 0x20, $length);
		if ($length)
			$this->data .= pack('A' . $length, $this->id);
		$this->attrs[] = 0x00100020;
        // add Patient Birth Date
        $this->data .= pack('v2V', 0x10, 0x30, 0);
		$this->attrs[] = 0x00100030;
        // add Patient Sex
        $this->data .= pack('v2V', 0x10, 0x40, 0);
		$this->attrs[] = 0x00100040;
        // add optional keys
        if ($this->optionalKeys) {
            // add Number of Patient Related Studies
            $this->data .= pack('v2V', 0x20, 0x1200, 0);
		    $this->attrs[] = 0x00201200;
            // add Number of Patient Related Series
            $this->data .= pack('v2V', 0x20, 0x1202, 0);
		    $this->attrs[] = 0x00201202;
            // add Number of Patient Related Instances
            $this->data .= pack('v2V', 0x20, 0x1204, 0);
		    $this->attrs[] = 0x00201204;
        }
    }
    function __destruct() { }
    function getDataBuffer() {
        return $this->data;
    }
    function studyRoot() {  // change into Study-Root Informational Model
        // add Study Date
        $this->data = pack('v2V', 0x8, 0x20, 0);
		$this->attrs[] = 0x00080020;
        // add Study Time
        $this->data = pack('v2V', 0x8, 0x30, 0);
		$this->attrs[] = 0x00080030;
        // add Accession Number
        $this->data .= pack('v2V', 0x8, 0x50, 0);
		$this->attrs[] = 0x00080050;
        // add Query/Retrieve Level
        $level = 'STUDY';
        $length = strlen($level);
        if ($length & 0x1)
            $length++;
        $this->data = pack('v2VA' . $length, 0x8, 0x52, $length, $level);
		$this->attrs[] = 0x00080052;
        // add Insittution Name
		$length = strlen($this->instName);
		if ($length & 0x1)
            $length++;
        $this->data .= pack('v2V', 0x8, 0x80, $length);
		if ($length)
            $this->data .= pack('A' . $length, $this->instName);
		$this->attrs[] = 0x00080080;
        // add Patient Name
		$fullname = "";
		if (strlen($this->last) || strlen($this->first))
			$fullname = $this->last . "^" . $this->first;
		$length = strlen($fullname);
		if ($length & 0x1)
			$length++;
        $this->data .= pack('v2V', 0x10, 0x10, $length);
		if ($length)
			$this->data .= pack('A' . $length, $fullname);
		$this->attrs[] = 0x00100010;
        // add Patient ID
		$length = strlen($this->id);
		if ($length & 0x1)
			$length++;
        $this->data .= pack('v2V', 0x10, 0x20, $length);
		if ($length)
			$this->data .= pack('A' . $length, $this->id);
		$this->attrs[] = 0x00100020;
        // add Patient Birth Date
        $this->data .= pack('v2V', 0x10, 0x30, 0);
		$this->attrs[] = 0x00100030;
        // add Patient Sex
        $this->data .= pack('v2V', 0x10, 0x40, 0);
		$this->attrs[] = 0x00100040;
        // add Study Instance UID
        $this->data .= pack('v2V', 0x20, 0xd, 0);
		$this->attrs[] = 0x0020000d;
        // add Study ID
        $this->data .= pack('v2V', 0x20, 0x10, 0);
		$this->attrs[] = 0x00200010;
        // add optional keys
        if ($this->optionalKeys) {
            // add Number of Study Related Series
            $this->data .= pack('v2V', 0x20, 0x1206, 0);
		    $this->attrs[] = 0x00201206;
            // add Number of Study Related Instances
            $this->data .= pack('v2V', 0x20, 0x1208, 0);
		    $this->attrs[] = 0x00201208;
        }
    }
}

class CFindIdentifierPatient extends BaseObject {
    var $attrs = array();
    var $data;
    var $optionalKeys;

    function __construct($key) {
        $this->optionalKeys = true;
		// add Study Date
        $date = $key["Study Date"];
		$length = strlen($date);
		if ($length & 0x1)
			$length++;
        $this->data = pack('v2V', 0x8, 0x20, $length);
		if ($length)
        	$this->data .= pack('A' . $length, $date);
		$this->attrs[] = 0x00080020;
		// add Accession Number
        $accession = $key["Accession Number"];
		$length = strlen($accession);
		if ($length & 0x1)
			$length++;
        $this->data .= pack('v2V', 0x8, 0x50, $length);
		if ($length)
        	$this->data .= pack('A' . $length, $accession);
		$this->attrs[] = 0x00080050;
        // add Query/Retrieve Level
        $level = 'STUDY';
        $length = strlen($level);
        if ($length & 0x1)
            $length++;
        $this->data .= pack('v2VA' . $length, 0x8, 0x52, $length, $level);
		$this->attrs[] = 0x00080052;
        // add Insittution Name
        $instName = $key["Institution Name"];
		$length = strlen($instName);
		if ($length & 0x1)
            $length++;
        $this->data .= pack('v2V', 0x8, 0x80, $length);
		if ($length)
            $this->data .= pack('A' . $length, $instName);
		$this->attrs[] = 0x00080080;
		// add Referring Physician's Name
        $referdoc = $key["Referring Physician"];
		$length = strlen($referdoc);
		if ($length & 0x1)
			$length++;
        $this->data .= pack('v2V', 0x8, 0x90, $length);
		if ($length)
        	$this->data .= pack('A' . $length, $referdoc);
		$this->attrs[] = 0x00080090;
		// add Study Description
        $this->data .= pack('v2V', 0x8, 0x1030, 0);
		$this->attrs[] = 0x00081030;
        // add Patient ID
        $patientid = $key["Patient ID"];
		$length = strlen($patientid);
		if ($length & 0x1)
			$length++;
        $this->data .= pack('v2V', 0x10, 0x20, $length);
		if ($length)
        	$this->data .= pack('A' . $length, $patientid);
		$this->attrs[] = 0x00100020;
		// add Study Instance UID
        $this->data .= pack('v2V', 0x20, 0xd, 0);
		$this->attrs[] = 0x0020000d;
		// add Study ID
        $studyid = $key["Study ID"];
		$length = strlen($studyid);
		if ($length & 0x1)
			$length++;
        $this->data .= pack('v2V', 0x20, 0x10, $length);
		if ($length)
        	$this->data .= pack('A' . $length, $studyid);
		$this->attrs[] = 0x00200010;
        // add optional keys
        if ($this->optionalKeys) {
            // add Number of Study Related Series
            $this->data .= pack('v2V', 0x20, 0x1206, 0);
		    $this->attrs[] = 0x00201206;
            // add Number of Study Related Instances
            $this->data .= pack('v2V', 0x20, 0x1208, 0);
		    $this->attrs[] = 0x00201208;
        }
    }
    function __destruct() { }
    function getDataBuffer() {
        return $this->data;
    }
}

class CFindIdentifierStudyRoot extends BaseObject {
    var $attrs = array();
    var $data;
    var $optionalKeys;

    function __construct($key) {
        $this->optionalKeys = true;
		// add Study Date
        $date = $key["Study Date"];
		$length = strlen($date);
		if ($length & 0x1)
			$length++;
        $this->data = pack('v2V', 0x8, 0x20, $length);
		if ($length)
        	$this->data .= pack('A' . $length, $date);
		$this->attrs[] = 0x00080020;
		// add Accession Number
        $accession = $key["Accession Number"];
		$length = strlen($accession);
		if ($length & 0x1)
			$length++;
        $this->data .= pack('v2V', 0x8, 0x50, $length);
		if ($length)
        	$this->data .= pack('A' . $length, $accession);
		$this->attrs[] = 0x00080050;
        // add Query/Retrieve Level
        $level = 'STUDY';
        $length = strlen($level);
        if ($length & 0x1)
            $length++;
        $this->data .= pack('v2VA' . $length, 0x8, 0x52, $length, $level);
		$this->attrs[] = 0x00080052;
        // add Insittution Name
        $instName = $key["Institution Name"];
		$length = strlen($instName);
		if ($length & 0x1)
            $length++;
        $this->data .= pack('v2V', 0x8, 0x80, $length);
		if ($length)
            $this->data .= pack('A' . $length, $instName);
		$this->attrs[] = 0x00080080;
		// add Referring Physician's Name
        $referdoc = $key["Referring Physician"];
		$length = strlen($referdoc);
		if ($length & 0x1)
			$length++;
        $this->data .= pack('v2V', 0x8, 0x90, $length);
		if ($length)
        	$this->data .= pack('A' . $length, $referdoc);
		$this->attrs[] = 0x00080090;
		// add Study Description
        $this->data .= pack('v2V', 0x8, 0x1030, 0);
		$this->attrs[] = 0x00081030;
        // add Patient Name
        $this->data .= pack('v2V', 0x10, 0x10, 0);
		$this->attrs[] = 0x00100010;
        // add Patient ID
        $this->data .= pack('v2V', 0x10, 0x20, 0);
		$this->attrs[] = 0x00100020;
		// add Study Instance UID
        $this->data .= pack('v2V', 0x20, 0xd, 0);
		$this->attrs[] = 0x0020000d;
		// add Study ID
        $studyid = $key["Study ID"];
		$length = strlen($studyid);
		if ($length & 0x1)
			$length++;
        $this->data .= pack('v2V', 0x20, 0x10, $length);
		if ($length)
        	$this->data .= pack('A' . $length, $studyid);
		$this->attrs[] = 0x00200010;
        // add optional keys
        if ($this->optionalKeys) {
            // add Number of Study Related Series
            $this->data .= pack('v2V', 0x20, 0x1206, 0);
		    $this->attrs[] = 0x00201206;
            // add Number of Study Related Instances
            $this->data .= pack('v2V', 0x20, 0x1208, 0);
		    $this->attrs[] = 0x00201208;
        }
    }
    function __destruct() { }
    function getDataBuffer() {
        return $this->data;
    }
}

class CFindIdentifierStudy extends BaseObject {
    var $attrs = array();
    var $data;
    var $optionalKeys;

    function __construct($key) {
        $this->optionalKeys = true;
		// add Series Date
        $date = $key["Series Date"];
		$length = strlen($date);
		if ($length & 0x1)
			$length++;
        $this->data = pack('v2V', 0x8, 0x21, $length);
		if ($length)
        	$this->data .= pack('A' . $length, $date);
		$this->attrs[] = 0x00080021;
        // add Query/Retrieve Level
        $level = 'SERIES';
        $length = strlen($level);
        if ($length & 0x1)
            $length++;
        $this->data .= pack('v2VA' . $length, 0x8, 0x52, $length, $level);
		$this->attrs[] = 0x00080052;
        // add Modality
        $modality = $key["Modality"];
		$length = strlen($modality);
		if ($length & 0x1)
			$length++;
        $this->data .= pack('v2V', 0x8, 0x60, $length);
		if ($length)
        	$this->data .= pack('A' . $length, $modality);
		$this->attrs[] = 0x00080060;
        // add Insittution Name
        $instName = $key["Institution Name"];
		$length = strlen($instName);
		if ($length & 0x1)
            $length++;
        $this->data .= pack('v2V', 0x8, 0x80, $length);
		if ($length)
            $this->data .= pack('A' . $length, $instName);
		$this->attrs[] = 0x00080080;
        // add Patient ID
        $patientid = $key["Patient ID"];
        $length = strlen($patientid);
        if ($length) {
            if ($length & 0x1)
                $length++;
            $this->data .= pack('v2VA' . $length, 0x10, 0x20, $length, $patientid);
		    $this->attrs[] = 0x00100020;
        }
        // add Body Part Examined
        $this->data .= pack('v2V', 0x18, 0x15, 0);
		$this->attrs[] = 0x00180015;
		// add Study Instance UID
        $uid = $key["Study UID"];
        $length = strlen($uid);
        if ($length & 0x1)
            $length++;
        $this->data .= pack('v2V', 0x20, 0xd, $length);
		if ($length)
        	$this->data .= pack('A' . $length, $uid);
		$this->attrs[] = 0x0020000d;
		// add Series Instance UID
        $this->data .= pack('v2V', 0x20, 0xe, 0);
		$this->attrs[] = 0x0020000e;
		// add Series Number
        $this->data .= pack('v2V', 0x20, 0x11, 0);
		$this->attrs[] = 0x00200011;
        if ($this->optionalKeys) {
		    // add Number of Series Related Instances
            $this->data .= pack('v2V', 0x20, 0x1209, 0);
		    $this->attrs[] = 0x00201209;
        }
    }
    function __destruct() { }
    function getDataBuffer() {
        return $this->data;
    }
	function getDisplayAttrs() {
		$attrs = array();
		foreach ($this->attrs as $attr) {
			// skip displaying Study UID
			if ($attr != 0x0020000d)
				$attrs[] = $attr;
		}
		return $attrs;
	}
}

class CFindIdentifierSeries extends BaseObject {
    var $attrs = array();
    var $data;

    function __construct($key) {
		// add SOP Instance UID
        $this->data = pack('v2V', 0x8, 0x18, 0);
		$this->attrs[] = 0x00080018;
        // add Query/Retrieve Level
        $level = 'IMAGE';
        $length = strlen($level);
        if ($length & 0x1)
            $length++;
        $this->data .= pack('v2VA' . $length, 0x8, 0x52, $length, $level);
		$this->attrs[] = 0x00080052;
        // add Insittution Name
        $instName = $key["Institution Name"];
		$length = strlen($instName);
		if ($length & 0x1)
            $length++;
        $this->data .= pack('v2V', 0x8, 0x80, $length);
		if ($length)
            $this->data .= pack('A' . $length, $instName);
		$this->attrs[] = 0x00080080;
        // add Patient ID
        $patientid = $key["Patient ID"];
        $length = strlen($patientid);
        if ($length) {
            if ($length & 0x1)
                $length++;
            $this->data .= pack('v2VA' . $length, 0x10, 0x20, $length, $patientid);
		    $this->attrs[] = 0x00100020;
        }
		// add Study Instance UID
        $studyuid = $key["Study UID"];
        $length = strlen($studyuid);
        if ($length) {
            if ($length & 0x1)
                $length++;
            $this->data .= pack('v2Va' . $length, 0x20, 0xd, $length, $studyuid);
		    $this->attrs[] = 0x0020000d;
        }
		// add Series Instance UID
        $uid = $key["Series UID"];
        $length = strlen($uid);
        if ($length & 0x1)
            $length++;
        $this->data .= pack('v2Va' . $length, 0x20, 0xe, $length, $uid);
		$this->attrs[] = 0x0020000e;
		// add Instance Number
        $this->data .= pack('v2V', 0x20, 0x13, 0);
		$this->attrs[] = 0x00200013;
    }
    function __destruct() { }
    function getDataBuffer() {
        return $this->data;
    }
	function getDisplayAttrs() {
		$attrs = array();
		foreach ($this->attrs as $attr) {
			// skip displaying Study UID and Series UID
			if ($attr != 0x0020000d && $attr != 0x0020000e)
				$attrs[] = $attr;
		}
		return $attrs;
	}
}

class CFindIdentifierImage extends BaseObject {
    var $attrs = array();
    var $data;

    function __construct($key) {
		// add SOP Instance UID
        $uid = $key["Instance UID"];
        $length = strlen($uid);
        if ($length & 0x1)
            $length++;
        $this->data = pack('v2Va' . $length, 0x8, 0x18, $length, $uid);
		$this->attrs[] = 0x00080018;
        // add Query/Retrieve Level
        $level = 'IMAGE';
        $length = strlen($level);
        if ($length & 0x1)
            $length++;
        $this->data .= pack('v2VA' . $length, 0x8, 0x52, $length, $level);
		$this->attrs[] = 0x00080052;
        // add Patient ID
        $patientid = $key["Patient ID"];
        $length = strlen($patientid);
        if ($length) {
            if ($length & 0x1)
                $length++;
            $this->data .= pack('v2VA' . $length, 0x10, 0x20, $length, $patientid);
		    $this->attrs[] = 0x00100020;
        }
		// add Study Instance UID
        $studyuid = $key["Study UID"];
        $length = strlen($studyuid);
        if ($length) {
            if ($length & 0x1)
                $length++;
            $this->data .= pack('v2Va' . $length, 0x20, 0xd, $length, $studyuid);
		    $this->attrs[] = 0x0020000d;
        }
		// add Series Instance UID
        $seriesuid = $key["Series UID"];
        $length = strlen($seriesuid);
        if ($length & 0x1)
            $length++;
        $this->data .= pack('v2Va' . $length, 0x20, 0xe, $length, $seriesuid);
        $this->attrs[] = 0x0020000e;
		// add Instance Number
        $this->data .= pack('v2V', 0x20, 0x13, 0);
		$this->attrs[] = 0x00200013;
		// add Samples Per Pixel
        $this->data .= pack('v2V', 0x28, 0x2, 0);
		$this->attrs[] = 0x00280002;
		// add Rows
        $this->data .= pack('v2V', 0x28, 0x10, 0);
		$this->attrs[] = 0x00280010;
		// add Columns
        $this->data .= pack('v2V', 0x28, 0x11, 0);
		$this->attrs[] = 0x00280011;
		// add Bits Allocated
        $this->data .= pack('v2V', 0x28, 0x100, 0);
		$this->attrs[] = 0x00280100;
		// add Bits Stored
        $this->data .= pack('v2V', 0x28, 0x101, 0);
		$this->attrs[] = 0x00280101;
    }
    function __destruct() { }
    function getDataBuffer() {
        return $this->data;
    }
	function getDisplayAttrs() {
		$attrs = array();
		foreach ($this->attrs as $attr) {
			// skip displaying Study UID and Series UID
			if ($attr != 0x0020000d && $attr != 0x0020000e)
				$attrs[] = $attr;
		}
		return $attrs;
	}
}

class CMoveIdentifierPatient extends BaseObject {
    var $attrs = array();
    var $data;

    function __construct($patientid) {
        // add Query/Retrieve Level
        $level = 'PATIENT';
        $length = strlen($level);
        if ($length & 0x1)
            $length++;
        $this->data .= pack('v2VA' . $length, 0x8, 0x52, $length, $level);
		$this->attrs[] = 0x00080052;
        // add Patient ID
        $length = strlen($patientid);
        if ($length & 0x1)
            $length++;
        $this->data .= pack('v2VA' . $length, 0x10, 0x20, $length, $patientid);
		$this->attrs[] = 0x00100020;
    }
    function __destruct() { }
    function getDataBuffer() {
        return $this->data;
    }
}

class CMoveIdentifierStudy extends BaseObject {
    var $attrs = array();
    var $data;

    function __construct($patientid, $uid) {
        // add Query/Retrieve Level
        $level = 'STUDY';
        $length = strlen($level);
        if ($length & 0x1)
            $length++;
        $this->data .= pack('v2VA' . $length, 0x8, 0x52, $length, $level);
		$this->attrs[] = 0x00080052;
        // add Patient ID
        $length = strlen($patientid);
        if ($length & 0x1)
            $length++;
        $this->data .= pack('v2VA' . $length, 0x10, 0x20, $length, $patientid);
		$this->attrs[] = 0x00100020;
		// add Study Instance UID
        $length = strlen($uid);
        if ($length & 0x1)
            $length++;
        $this->data .= pack('v2Va' . $length, 0x20, 0xd, $length, $uid);
		$this->attrs[] = 0x0020000d;
    }
    function __destruct() { }
    function getDataBuffer() {
        return $this->data;
    }
}

class CMoveIdentifierSeries extends BaseObject {
    var $attrs = array();
    var $data;

    function __construct($patientid, $studyuid, $uid) {
        // add Query/Retrieve Level
        $level = 'SERIES';
        $length = strlen($level);
        if ($length & 0x1)
            $length++;
        $this->data .= pack('v2VA' . $length, 0x8, 0x52, $length, $level);
		$this->attrs[] = 0x00080052;
        // add Patient ID
        $length = strlen($patientid);
        if ($length & 0x1)
            $length++;
        $this->data .= pack('v2VA' . $length, 0x10, 0x20, $length, $patientid);
		$this->attrs[] = 0x00100020;
		// add Study Instance UID
        $length = strlen($studyuid);
        if ($length & 0x1)
            $length++;
        $this->data .= pack('v2Va' . $length, 0x20, 0xd, $length, $studyuid);
		$this->attrs[] = 0x0020000d;
		// add Series Instance UID
        $length = strlen($uid);
        if ($length & 0x1)
            $length++;
        $this->data .= pack('v2Va' . $length, 0x20, 0xe, $length, $uid);
		$this->attrs[] = 0x0020000e;
    }
    function __destruct() { }
    function getDataBuffer() {
        return $this->data;
    }
}

class CMoveIdentifierImage extends BaseObject {
    var $attrs = array();
    var $data;

    function __construct($patientid, $studyuid, $seriesuid, $uid) {
		// add SOP Instance UID
        $length = strlen($uid);
        if ($length & 0x1)
            $length++;
        $this->data = pack('v2Va' . $length, 0x8, 0x18, $length, $uid);
		$this->attrs[] = 0x00080018;
        // add Query/Retrieve Level
        $level = 'IMAGE';
        $length = strlen($level);
        if ($length & 0x1)
            $length++;
        $this->data .= pack('v2VA' . $length, 0x8, 0x52, $length, $level);
		$this->attrs[] = 0x00080052;
        // add Patient ID
        $length = strlen($patientid);
        if ($length & 0x1)
            $length++;
        $this->data .= pack('v2VA' . $length, 0x10, 0x20, $length, $patientid);
		$this->attrs[] = 0x00100020;
		// add Study Instance UID
        $length = strlen($studyuid);
        if ($length & 0x1)
            $length++;
        $this->data .= pack('v2Va' . $length, 0x20, 0xd, $length, $studyuid);
		$this->attrs[] = 0x0020000d;
		// add Series Instance UID
        $length = strlen($seriesuid);
        if ($length & 0x1)
            $length++;
        $this->data .= pack('v2Va' . $length, 0x20, 0xe, $length, $seriesuid);
		$this->attrs[] = 0x0020000e;
    }
    function __destruct() { }
    function getDataBuffer() {
        return $this->data;
    }
}

class WorklistFindIdentifier extends BaseObject {
    var $attrs = array();
    var $data;
    var $optionalKeys;

    function __construct($patientid,
	                      $patientname,
						  $station,
						  $startdate,
						  $starttime,
						  $modality,
						  $referdoc) {
        $this->optionalKeys = true;
        // add Accession Number
        $this->data = pack('v2V', 0x8, 0x50, 0);
		$this->attrs[] = 0x00080050;
        if ($this->optionalKeys) {
            // add Institution Name
            $this->data .= pack('v2V', 0x8, 0x80, 0);
		    $this->attrs[] = 0x00080080;
        }
        // add Referring Physician's Name
        $this->data .= pack('v2V', 0x8, 0x90, 0);
		$this->attrs[] = 0x00080090;
        if ($this->optionalKeys) {
            // add Admitting Diagnosis
            $this->data .= pack('v2V', 0x8, 0x1080, 0);
		    $this->attrs[] = 0x00081080;
        }
        // add Referenced Study Sequence
        $this->data .= pack('v2V', 0x8, 0x1110, 0);
		$this->attrs[] = 0x00081110;
        // add Referenced Patient Sequence
        $this->data .= pack('v2V', 0x8, 0x1120, 0);
		$this->attrs[] = 0x00081120;
		// add Patient Name
		$length = strlen($patientname);
		if ($length & 0x1)
			$length++;
        $this->data .= pack('v2V', 0x10, 0x10, $length);
		if ($length)
        	$this->data .= pack('A' . $length, $patientname);
		$this->attrs[] = 0x00100010;
        // add Patient ID
		$length = strlen($patientid);
		if ($length & 0x1)
			$length++;
        $this->data .= pack('v2V', 0x10, 0x20, $length);
		if ($length)
        	$this->data .= pack('A' . $length, $patientid);
		$this->attrs[] = 0x00100020;
        // add Patient's Birth Date
        $this->data .= pack('v2V', 0x10, 0x30, 0);
		$this->attrs[] = 0x00100030;
        // add Patient's Sex
        $this->data .= pack('v2V', 0x10, 0x40, 0);
		$this->attrs[] = 0x00100040;
        // add Patient's Age
        $this->data .= pack('v2V', 0x10, 0x1010, 0);
		$this->attrs[] = 0x00101010;
        // add Patient's Size
        $this->data .= pack('v2V', 0x10, 0x1020, 0);
		$this->attrs[] = 0x00101020;
        // add Patient's Weight
        $this->data .= pack('v2V', 0x10, 0x1030, 0);
		$this->attrs[] = 0x00101030;
        if ($this->optionalKeys) {
            // add Additional Patient History
            $this->data .= pack('v2V', 0x10, 0x21b0, 0);
		    $this->attrs[] = 0x001021b0;
        }
        // add Study Instance UID
        $this->data .= pack('v2V', 0x20, 0xd, 0);
		$this->attrs[] = 0x0020000d;
        // add Requesting Physician's Name
        $this->data .= pack('v2V', 0x32, 0x1032, 0);
		$this->attrs[] = 0x00321032;
        // add Requested Procedure Description
        $this->data .= pack('v2V', 0x32, 0x1060, 0);
		$this->attrs[] = 0x00321060;
		// build Requested Procedure Code Sequence
        // add Code Value
        $data = pack('v2V', 0x8, 0x100, 0);
		$this->attrs[] = 0x00080100;
        // add Coding Scheme Designator
        $data .= pack('v2V', 0x8, 0x102, 0);
		$this->attrs[] = 0x00080102;
        // add Coding Scheme Version
        $data .= pack('v2V', 0x8, 0x103, 0);
		$this->attrs[] = 0x00080103;
        // add Code Meaning
        $data .= pack('v2V', 0x8, 0x104, 0);
		$this->attrs[] = 0x00080104;
		// add Requested Procedure Code Sequence
        $item = pack('v2V', 0xFFFE, 0xE000, strlen($data));
		$item .= $data;
        $this->data .= pack('v2V', 0x32, 0x1064, strlen($item));
		$this->data .= $item;
		$this->attrs[] = 0x00321064;
		// build Scheduled Procedure Step Sequence
        // add Modality 
		$length = strlen($modality);
		if ($length & 0x1)
			$length++;
        $data = pack('v2V', 0x8, 0x60, $length);
		if ($length)
        	$data .= pack('A' . $length, $modality);
		$this->attrs[] = 0x00080060;
        // add Requested Contrast Agent
        $data .= pack('v2V', 0x32, 0x1070, 0);
		$this->attrs[] = 0x00321070;
		// add Scheduled Station AE Title 
		$length = strlen($station);
		if ($length & 0x1)
			$length++;
        $data .= pack('v2V', 0x40, 0x1, $length);
		if ($length)
        	$data .= pack('A' . $length, $station);
		$this->attrs[] = 0x00400001;
		// add Scheduled Procedure Step Start Date 
		$length = strlen($startdate);
		if ($length & 0x1)
			$length++;
        $data .= pack('v2V', 0x40, 0x2, $length);
		if ($length)
        	$data .= pack('A' . $length, $startdate);
		$this->attrs[] = 0x00400002;
		// add Scheduled Procedure Step Start Time 
		$length = strlen($starttime);
		if ($length & 0x1)
			$length++;
        $data .= pack('v2V', 0x40, 0x3, $length);
		if ($length)
        	$data .= pack('A' . $length, $starttime);
		$this->attrs[] = 0x00400003;
		// add Scheduled Performing Physician's Name
        $data .= pack('v2V', 0x40, 0x6, 0);
		$this->attrs[] = 0x00400006;
		// add Scheduled Protocol Code Sequence
		$this->attrs[] = 0x00400008;
		// add Scheduled Procedure Step Sequence
        $item = pack('v2V', 0xFFFE, 0xE000, strlen($data));
		$item .= $data;
        $this->data .= pack('v2V', 0x40, 0x100, strlen($item));
		$this->data .= $item;
		$this->attrs[] = 0x00400100;
        // add Requested Procedure ID
        $this->data .= pack('v2V', 0x40, 0x1001, 0);
		$this->attrs[] = 0x00401001;
        // add Requested Procedure Priority
        $this->data .= pack('v2V', 0x40, 0x1003, 0);
		$this->attrs[] = 0x00401003;
    }
    function __destruct() { }
    function getDataBuffer() {
        return $this->data;
    }
}

class ProtocolDataTfPdu extends BaseObject {
    var $ctxId;
    var $pdv;
    var $data;

    function __construct($ctxId) {
        $this->ctxId = $ctxId;
    }
    function __destruct() { }
    function getDataBuffer() {
        $header = pack('CCN', 0x4, 0, strlen($this->data));
        return ($header . $this->data);
    }
    function sendCommandEcho() {
        $this->pdv = new CEchoPdv();
        $data = $this->pdv->getDataBuffer();
        $this->data = pack('N', strlen($data)+2);
        $this->data .= pack('C2', $this->ctxId, 0x3);
        $this->data .= $data;
        #pacsone_dump($this->data);
    }
    function sendCommandFind($sopClass) {
        $this->pdv = new CFindPdv($sopClass);
        $data = $this->pdv->getDataBuffer();
        $this->data = pack('N', strlen($data)+2);
        $this->data .= pack('C2', $this->ctxId, 0x3);
        $this->data .= $data;
        #pacsone_dump($this->data);
    }
    function sendCommandMove($sopClass, $dest) {
        $this->pdv = new CMovePdv($sopClass, $dest);
        $data = $this->pdv->getDataBuffer();
        $this->data = pack('N', strlen($data)+2);
        $this->data .= pack('C2', $this->ctxId, 0x3);
        $this->data .= $data;
        #pacsone_dump($this->data);
    }
    function sendCommandWorklistFind() {
        $this->pdv = new WorklistFindPdv();
        $data = $this->pdv->getDataBuffer();
        $this->data = pack('N', strlen($data)+2);
        $this->data .= pack('C2', $this->ctxId, 0x3);
        $this->data .= $data;
        #pacsone_dump($this->data);
    }
    function sendCommandGetPrinter() {
        $this->pdv = new GetPrinterPdv();
        $data = $this->pdv->getDataBuffer();
        $this->data = pack('N', strlen($data)+2);
        $this->data .= pack('C2', $this->ctxId, 0x3);
        $this->data .= $data;
        #pacsone_dump($this->data);
    }
    function sendDataSet(&$dataset) {
        $data = $dataset->getDataBuffer();
        $this->data = pack('N', strlen($data)+2);
        $this->data .= pack('C2', $this->ctxId, 0x2);
        $this->data .= $data;
    }
    function recvResponse(&$data, &$complete, &$error) {
        return $this->pdv->recvResponse($data, $complete, $error);
    }
}

class AssociateReleasePdu extends BaseObject {
    var $data;

    function __construct() {
        $this->data = pack('N', 0);
    }
    function __destruct() { }
    function getDataBuffer() {
        $header = pack('CCN', 0x5, 0, strlen($this->data));
        return ($header . $this->data);
    }
}

class Association extends BaseObject {
    var $socket;
    var $ipAddr;
    var $hostName;
    var $tcpPort;
    var $calledAe;
    var $callingAe;
    var $accepted;
	var $connected = false;
    // constant definitions
    var $TIMEOUT = 5;

    function getSSLContextOptions($aetitle) {
        $options = array();
        $dir = dirname($_SERVER['SCRIPT_FILENAME']);
        $dir = substr($dir, 0, strlen($dir) - 3);
        $ini = $dir . $aetitle . ".ini";
        if (file_exists($ini)) {
            $parsed = parseIniFile($ini);
            if (count($parsed) && isset($parsed['SSLCertificate']) &&
                isset($parsed['SSLPrivateKey'])) {
                $cert = $parsed['SSLCertificate'];
                $pkey = $parsed['SSLPrivateKey'];
                if (file_exists($cert) && file_exists($pkey)) {
                    $options['ssl'] = array(
                        'local_cert'        => $cert,
                        'local_pk'          => $pkey,
                        'verify_peer'       => false,
                        'verify_peer_name'  => false,
                    );
                }
            }
        }
        return $options;
    }

    function __construct($ip, $host, $port, $called, $calling, $tls = 0) {
        $address = (strlen($ip))? $ip : $host;
        $errno = 0;
        $errstr = '';
        if ($tls) {
            $options = $this->getSSLContextOptions($calling);
            if (empty($options))
                die ('<font color=red>' . pacsone_gettext("Failed to load SSL Context Options") . '</font>');
            $context = stream_context_create($options);
            $remote = "ssl://$address:$port";
            $this->socket = stream_socket_client($remote, $errno, $errstr, $this->TIMEOUT, STREAM_CLIENT_CONNECT, $context);
        } else
            $this->socket = fsockopen($address, $port, $errno, $errstr, $this->TIMEOUT);
        if (!$this->socket)
            die ('<font color=red>Failed to open Dicom association: ' . $errstr . '(' . $errno . ')</font>');
        stream_set_timeout($this->socket, $this->TIMEOUT);
        $this->ipAddr = $ip;
        $this->hostName = $host;
        $this->tcpPort = $port;
        $this->calledAe = $called;
        $this->callingAe = $calling;
		$this->connected = true;
		$this->accepted = false;
    }
    function __destruct() {
		if ($this->connected) {
			// close the association
        	fclose($this->socket);
		}
    }
	// establish association
	function associate($sopClass, &$error) {
	    $request = new AssociateRequestPdu($sopClass, $this->calledAe, $this->callingAe);
        $data = $request->getDataBuffer();
        fwrite($this->socket, $data, strlen($data));
        // check if request is accepted
        $data = fread($this->socket, 6);
		#pacsone_dump($data);
        // check the PDU type
        if ($data && ord($data[0]) == 0x2) {
            $array = unpack('Ctype/Cdummy/Nlength', $data);
			$pduLen = $array['length'];
        	$data = fread($this->socket, $pduLen);
			while (strlen($data) < $pduLen) {
        		$data .= fread($this->socket, $pduLen);
			}
			#pacsone_dump($data);
           	$this->accepted = $request->isAccepted($data, $error);
        }
        else {
            $error = 'Association request rejected';
            return false;
        }
    	return $request;
	}
	// release association
	function release(&$error) {
		if ($this->accepted) {
			// send ASSOCIATE_RELEASE_RQ PDU
	       	$request = new AssociateReleasePdu();
       		$data = $request->getDataBuffer();
		    fwrite($this->socket, $data, strlen($data));
			// check received ASSOCIATE_RELEASE_RP PDU
   		    $data = fread($this->socket, 10);
            if (strlen($data)) {
			    $pduType = ord($data[0]);
                /*
   	     	    if ($pduType != 0x6)
           		    $error .= "Invalid ASSOCIATE_RELEASE_RSP PDU received: pduType = " . $pduType;
                */
            }
		}
	}
    // send C-ECHO request
    function verify(&$error) {
        $result = false;
        global $C_ECHO;
        $sopClass = array($C_ECHO);
		$request = $this->associate($sopClass, $error);
		if (!$request)
			return false;
        // send the C-ECHO command PDV
        $dataPdu = new ProtocolDataTfPdu( $request->getPresentContextId() );
        $dataPdu->sendCommandEcho();
        $data = $dataPdu->getDataBuffer();
        fwrite($this->socket, $data, strlen($data));
        // read the response for the C-ECHO command
        $data = fread($this->socket, 6);
        // check the PDU type
		$respComplete = false;
		do {
        	if ($data && ord($data[0]) == 0x4) {
            	$array = unpack('Ctype/Cdummy/Nlength', $data);
		    	$pduLen = $array['length'];
            	$data = fread($this->socket, $pduLen);
		    	while (strlen($data) < $pduLen) {
            		$data .= fread($this->socket, $pduLen);
		    	}
       	    	$result = $dataPdu->recvResponse($data, $respComplete, $error);
        	}
        } while (!$respComplete && !strlen($error));
		// release the association
		$this->release($error);
        return $result;
    }
    // send C-FIND request
    function find(&$identifier, &$error) {
        $result = array();
        global $C_FIND;
        global $C_FIND_STUDYROOT;
        $sopClass = array($C_FIND, $C_FIND_STUDYROOT);
        $request = $this->associate($sopClass, $error);
        if (!$request)
            return false;
        // send the C-FIND command PDV
        $ctxId = $request->getPresentContextId();
        // remote AE has chosen the Study-Root Informational Model
        if (is_a($identifier, 'CFindIdentifierRoot') &&
            !strcasecmp($request->getSopClass($ctxId), $C_FIND_STUDYROOT)) {
            $identifier->studyRoot();
        }
        $dataPdu = new ProtocolDataTfPdu($ctxId);
        $dataPdu->sendCommandFind( $request->getSopClass($ctxId) );
        $data = $dataPdu->getDataBuffer();
        fwrite($this->socket, $data, strlen($data));
        // send the Identifier in a Data Set PDV 
        $dataPdu->sendDataSet($identifier);
        $data = $dataPdu->getDataBuffer();
        fwrite($this->socket, $data, strlen($data));
        // read the response for the C-FIND command
		$respComplete = false;
        do {
            $data = fread($this->socket, 6);
            // check the PDU type
            if ($data && ord($data[0]) == 0x4) {
                $array = unpack('Ctype/Cdummy/Nlength', $data);
		        $pduLen = $array['length'];
                $data = fread($this->socket, $pduLen);
		        while (strlen($data) < $pduLen) {
                	$data .= fread($this->socket, $pduLen);
		        }
       	        $match = $dataPdu->recvResponse($data, $respComplete, $error);
				if (!$match)
					break;
				if (is_a($match, 'CMatchResult'))
				    $result[] = $match;
            }
        } while (!$respComplete && !strlen($error));
		// release the association
		$this->release($error);
        return $result;
    }
    // send C-MOVE request
    function move($dest, &$identifier, &$error) {
        $result = array();
        global $C_MOVE;
        global $C_MOVE_STUDYROOT;
        $sopClass = array($C_MOVE, $C_MOVE_STUDYROOT);
		$request = $this->associate($sopClass, $error);
        if (!$request)
			return false;
        // send the C-MOVE command PDV
        $ctxId = $request->getPresentContextId();
        $dataPdu = new ProtocolDataTfPdu($ctxId);
        $dataPdu->sendCommandMove($request->getSopClass($ctxId), $dest);
        $data = $dataPdu->getDataBuffer();
        fwrite($this->socket, $data, strlen($data));
        // send the Identifier in a Data Set PDV 
        $dataPdu->sendDataSet($identifier);
        $data = $dataPdu->getDataBuffer();
        fwrite($this->socket, $data, strlen($data));
        // read the response for the C-MOVE command
		$respComplete = false;
        do {
            $data = fread($this->socket, 6);
            // check the PDU type
            if ($data && ord($data[0]) == 0x4) {
                $array = unpack('Ctype/Cdummy/Nlength', $data);
		        $pduLen = $array['length'];
                $data = fread($this->socket, $pduLen);
		        while (strlen($data) < $pduLen) {
                	$data .= fread($this->socket, $pduLen);
		        }
       	        $match = $dataPdu->recvResponse($data, $respComplete, $error);
				if (!$match)
					break;
				if (is_a($match, 'CFailedSopInstances'))
					$result[] = $match;
            }
        } while (!$respComplete && !strlen($error));
		// release the association
		$this->release($error);
        return $result;
    }
    // send Modality Worklist-FIND request
    function findWorklist(&$identifier, &$error) {
        $result = array();
        global $WORKLIST_FIND;
        $sopClass = array($WORKLIST_FIND);
		$request = $this->associate($sopClass, $error);
        if (!$request)
			return false;
        // send the Modality Worklist-FIND command PDV
        $dataPdu = new ProtocolDataTfPdu( $request->getPresentContextId() );
        $dataPdu->sendCommandWorklistFind();
        $data = $dataPdu->getDataBuffer();
        fwrite($this->socket, $data, strlen($data));
        // send the Identifier in a Data Set PDV 
        $dataPdu->sendDataSet($identifier);
        $data = $dataPdu->getDataBuffer();
        fwrite($this->socket, $data, strlen($data));
        // read the response for the Modality Worklist-FIND command
		$respComplete = false;
        do {
            $data = fread($this->socket, 6);
            // check the PDU type
            if ($data && ord($data[0]) == 0x4) {
                $array = unpack('Ctype/Cdummy/Nlength', $data);
		        $pduLen = $array['length'];
                $data = fread($this->socket, $pduLen);
		        while (strlen($data) < $pduLen) {
                	$data .= fread($this->socket, $pduLen);
		        }
       	        $match = $dataPdu->recvResponse($data, $respComplete, $error);
				if (is_a($match, 'Item'))
				    $result[] = $match;
            }
        } while (!$respComplete && !strlen($error));
		// release the association
		$this->release($error);
        return $result;
    }
    // send N-GET command to get printer properties
    function getPrinter(&$error) {
        $result = array();
        global $BASIC_PRINT;
        $sopClass = array($BASIC_PRINT);
		$request = $this->associate($sopClass, $error);
        if (!$request)
			return false;
        // send the N-GET command PDV
        $dataPdu = new ProtocolDataTfPdu( $request->getPresentContextId() );
        $dataPdu->sendCommandGetPrinter();
        $data = $dataPdu->getDataBuffer();
        fwrite($this->socket, $data, strlen($data));
        // read the response for the Modality Worklist-FIND command
		$respComplete = false;
        do {
            $data = fread($this->socket, 6);
            // check the PDU type
            if ($data && ord($data[0]) == 0x4) {
                $array = unpack('Ctype/Cdummy/Nlength', $data);
		        $pduLen = $array['length'];
                $data = fread($this->socket, $pduLen);
		        while (strlen($data) < $pduLen) {
                	$data .= fread($this->socket, $pduLen);
		        }
       	        $match = $dataPdu->recvResponse($data, $respComplete, $error);
				if (is_a($match, 'CAttributeList'))
				    $result[] = $match;
            }
        } while (!$respComplete && !strlen($error));
		// release the association
		$this->release($error);
        return count($result)? $result[0] : false;
    }
}

class StructuredReport extends DicomObject {
    var $attrs = array();
    var $root;
    var $explicit = false;
    var $keyObj = false;

    function __construct($path) {
		global $ATTR_TBL;
		global $EXPLICIT_VR_TBL;
        global $XFER_SYNTAX_TBL;
        global $UNDEF_LEN;
		$handle = fopen($path, "rb");
		$data = fread($handle, filesize($path));
		fclose($handle);
		// check if Part 10 format
		$signature = substr($data, 128, 4);
		if (strcmp($signature, "DICM") == 0) {
			// skip the Part 10 headers
			$data = substr($data, 128+4);
        	$array = unpack('vgroup/velement/A2vr/vvalue/Vlength', $data);
			$group = $array['group'];
			$element = $array['element'];
			$vr = $array['vr'];
			$value = isset($array['value'])? $array['value'] : "";
			$length = $array['length'];
            $metaLen = 2+2+2+2+4;
            $meta = substr($data, $metaLen, $length);
            $data = substr($data, $metaLen+$length);
            // check the transfer syntax
            while (strlen($meta) > 0) {
  	            $array = unpack('vgroup/velement/A2vr', $meta);
                $group = $array['group'];
                $element = $array['element'];
                $vr = $array['vr'];
                $meta = substr($meta, 6);
                if (isset($EXPLICIT_VR_TBL[ $vr ])) {
                    $format = "vreserved/Vlength";
                    $offset = 6;
                } else {
                    $format = "vlength";
                    $offset = 2;
                }
      	        $array = unpack($format, $meta);
                $length = $array['length'];
                // check transfer syntax to see if it's supported
                if (($group == 0x0002) && ($element == 0x0010)) {
                    $uid = substr($meta, $offset, $length);
                    $format = "a" . $length . "uid";
                    $array = unpack($format, $uid);
                    $uid = trim($array['uid']);
                    #print "Dicom Transfer Syntax: <b>$uid</b><br>";
                    if (isset($XFER_SYNTAX_TBL[$uid])) {
                        $this->explicit = $XFER_SYNTAX_TBL[$uid][0];
                        $this->bigEnd = $XFER_SYNTAX_TBL[$uid][1];
                    }
                }
                $meta = substr($meta, $offset+$length);
            }
		}
		while (strlen($data) > 0) {
            $header = substr($data, 0, ($this->explicit)? 6 : 8);
            $headerLen = strlen($header);
            if ($this->explicit) {
                $format = $this->bigEnd? 'ngroup/nelement/A2vr' : 'vgroup/velement/A2vr';
            } else {
                $format = $this->bigEnd? 'ngroup/nelement/Nlength' : 'vgroup/velement/Vlength';
            }
        	$array = unpack($format, $header);
			$group = $array['group'];
			$element = $array['element'];
            $body = substr($data, $headerLen);
            $vr = "";
            if ($this->explicit) {
                $vr = $array['vr'];
                if (isset($EXPLICIT_VR_TBL[$vr])) {
                    $format = $this->bigEnd? "nreserved/Nlength" : "vreserved/Vlength";
                    $offset = 6;
                } else {
                    $format = $this->bigEnd? "nlength" : "vlength";
                    $offset = 2;
                }
                $header = substr($body, 0, $offset);
                $body = substr($body, $offset);
                $headerLen += $offset;
      	        $array = unpack($format, $header);
                $length = $array['length'];
            } else {
                $length = $array['length'];
            }
			$key = $group << 16 | $element;
			if (($length == $UNDEF_LEN) || (strcmp($vr, "SQ") == 0)) {	// sequence elements
                if ($length == $UNDEF_LEN)
                    $length = strlen($body);
				$this->attrs[$key] = new Sequence($key, $length, $body, $this->explicit, $this->bigEnd);
				$length = $this->attrs[$key]->getLength();
			} else {
				if (isset($ATTR_TBL[$key]))
					$format = $this->convertEndian($ATTR_TBL[$key]->type);
				else
					$format = "A";
				if (strcasecmp($format[0], "A") == 0)
					$format .= $length;
				$format .= 'value';
				$array = unpack($format, $body);
			    $value = isset($array['value'])? trim($array['value']) : "";
				// save this attribute
				$this->attrs[$key] = $value;
			}
			$data = substr($data, $headerLen+$length);
		}
		$this->classify();
	}
    function __destruct() {}
    function showDebug() {
		foreach ($this->attrs as $key => $attr) {
        	if (is_a($attr, 'Sequence'))
				$attr->showDebug();
			else
				print "Attribute (" . dechex($key) . "): Value = $attr<br>";
		}
	}
	function classify() {
		// find root content item
		if (!isset($this->attrs[0x0040A040]) ||
			strcasecmp($this->attrs[0x0040A040], "Container")) {
            print "<font color=red>Failed to find Root Content Item:<br>";
			$this->showDebug();
			print "</font>";
			return;
		}
		$concept = $this->attrs[0x0040A043];
		$continuity = isset($this->attrs[0x0040A050])? $this->attrs[0x0040A050] : "";
		$this->root = new ContainerItem($concept, $continuity);
		// build the rest of the content tree
		$this->root->buildContentTree($this->attrs[0x0040A730]);
        if (array_key_exists(0x0040A375, $this->attrs) &&
            !$this->attrs[0x0040A375]->isEmpty())
            $this->keyObj = new KeyObjectSelectionItem($this->attrs[0x0040A375]);
	}
	function showHtml() {
		$this->root->showHtml();
        if ($this->keyObj) {
            print "<h3>";
            print pacsone_gettext("Key Object Selection Module Attributes");
            print "</h3>";
            $this->keyObj->showHtml();
        }
	}
}

// key object selection classes
class ReferencedSeriesItem extends BaseObject {
    var $seriesUid;
    var $retAetitle;
    var $refInstances = array();

    function __construct(&$attr) {
        $this->seriesUid = trim($attr->getAttr(0x0020000E));
        $this->retAeTitle = $attr->getAttr(0x00080054);
        $instances = $attr->getItem(0x00081199);
        foreach ($instances->items as $item) {
            $this->refInstances[] = array("class"       => $item->getAttr(0x00081150),
                                          "instance"    => trim($item->getAttr(0x00081155)));
        }
    }
    function __destruct() {}
	function showHtml() {
        global $dbcon;
        $uid = $this->seriesUid;
		$url = $dbcon->findRefSeries($uid);
		if (strlen($url))
			$uid = $url;
        print pacsone_gettext("Series Instance UID: ") . $uid . "<br>";
        print pacsone_gettext("Retrieve AE Title: ") . $this->retAeTitle . "<br>";
        print "<ul>";
        foreach ($this->refInstances as $instance) {
            print "<li>";
            print pacsone_gettext("Referenced SOP Class UID: ") . $instance["class"] . "<br>";
            $uid = $instance["instance"];
		    $url = $dbcon->findRefImage($uid);
		    if (strlen($url))
			    $uid = $url;
            print pacsone_gettext("Referenced SOP Instance UID: ") . $uid . "<br>";
            print "</li>";
        }
        print "</ul>";
    }
}

class KeyObjectSelectionItem extends BaseObject {
    var $studyUid;
    var $refSeries = array();

    function __construct(&$attr) {
        $this->studyUid = trim($attr->getAttr(0x0020000D));
        $series = $attr->getItem(0x00081115);
        foreach ($series->items as $item) {
            $this->refSeries[] = new ReferencedSeriesItem($item);
        }
    }
    function __destruct() {}
	function showHtml() {
        global $dbcon;
        $uid = $this->studyUid;
        print "<p><b>";
		$url = $dbcon->findRefStudy($uid);
		if (strlen($url))
			$uid = $url;
        print pacsone_gettext("Study Instance UID: ") . $uid;
        print "</b><br>";
        print "<ul>";
        foreach ($this->refSeries as $series) {
            print "<li>";
            $series->showHtml();
            print "</li>";
        }
        print "</ul>";
    }
}

class ContentItem extends BaseObject {
	var $type;
	var $code;
	var $conceptNameCode;
	var $value;
	var $children;
	var $observationDatetime;
	// relationship types
	var $contains = array();
	var $obsContexts = array();
	var $conceptMods = array();
	var $properties = array();
	var $acqContexts = array();
	var $inferred = array();
	var $selected = array();

    function __construct(&$concept) {
		if (isset($concept) && $concept->hasKey(0x00080104)) {
			$this->conceptNameCode = $concept->getAttr(0x00080104);
            if ($concept->hasKey(0x00080100))
                $this->code = $concept->getAttr(0x00080100);
        } else
			$this->conceptNameCode = "Content Item";
		$this->children = 0;
		$this->observationDatetime = "";
	}
    function __destruct() {}
	function buildContentTree(&$content) {
		$this->children = count($content->items);
		foreach ($content->items as $item) {
			if ($item->hasKey(0x0040A010) && $item->hasKey(0x0040A040)) {
				$relation = $item->getAttr(0x0040A010);
				$type = $item->getAttr(0x0040A040);
				if (strcasecmp($type, "CONTAINER") == 0) {
                    $heading = null;
					if (isset($item->attrs[0x0040A043]))
						$heading = $item->attrs[0x0040A043];
					$contentItem = new ContainerItem($heading, $item->getAttr(0x0040A050));
				}
				else if (isset($item->attrs[0x0040A043])) {
					$concept = $item->attrs[0x0040A043];
					switch ($type) {
					case "TEXT":
						$value = $item->getAttr(0x0040A160);
						$contentItem = new TextValueItem($concept, $value);
						break;
					case "DATETIME":
						$value = $item->getAttr(0x0040A120);
						$contentItem = new DateTimeItem($concept, $value);
					break;
					case "DATE":
						$value = $item->getAttr(0x0040A121);
						$contentItem = new DateItem($concept, $value);
						break;
					case "TIME":
						$value = $item->getAttr(0x0040A122);
						$contentItem = new TimeItem($concept, $value);
						break;
					case "PNAME":
						$value = $item->getAttr(0x0040A123);
						$contentItem = new PersonNameItem($concept, $value);
						break;
					case "UIDREF":
						$value = $item->getAttr(0x0040A124);
						$contentItem = new UidItem($concept, $value);
						break;
					case "NUM":
						$value = $item->attrs[0x0040A300];
						$contentItem = new NumericItem($concept, $value);
						break;
					case "CODE":
						$value = $item->attrs[0x0040A168];
						$contentItem = new CodeItem($concept, $value);
						break;
					case "COMPOSITE":
						$value = $item->attrs[0x00081199];
						$contentItem = new CompositeItem($concept, $value);
						break;
					case "IMAGE":
						if (isset($item->attrs[0x00081199]))
							$value = $item->attrs[0x00081199];
						$contentItem = new ImageItem($concept, $value);
						break;
					case "WAVEFORM":
						if (isset($item->attrs[0x00081199]))
							$value = $item->attrs[0x00081199];
						$contentItem = new WaveformItem($concept, $value);
						break;
					default:
						break;
					}
				}
				if (isset($contentItem)) {
					// set observation datetime if present
					if ($item->hasKey(0x0040A032))
						$contentItem->observationDatetime = $item->getAttr(0x0040A032);
					// recursively build the sub-content tree
					if (isset($item->attrs[0x0040A730])) {
						$contentItem->buildContentTree($item->attrs[0x0040A730]);
					}
					// categorize to each relationship list
					switch ($relation) {
					case "CONTAINS":
						$this->contains[] = $contentItem;
						break;
					case "HAS OBS CONTEXT":
						$this->obsContexts[] = $contentItem;
						break;
					case "HAS CONCEPT MOD":
						$this->conceptMods[] = $contentItem;
						break;
					case "HAS PROPERTIES":
						$this->properties[] = $contentItem;
						break;
					case "HAS ACQ CONTEXT":
						$this->acqContexts[] = $contentItem;
						break;
					case "INFERRED FROM":
						$this->inferred[] = $contentItem;
						break;
					case "SELECTED FROM":
						$this->selected[] = $contentItem;
						break;
					default:
						break;
					}
				}
			}
		}
	}
	function showHtml() {
		$this->showMeHtml();
		$lists = array (
			"Has Observation Context"		=> $this->obsContexts,
			"Contains"						=> $this->contains,
			"Has Concept Modifier"			=> $this->conceptMods,
			"Has Properties"				=> $this->properties,
			"Has Acquisition Context"		=> $this->acqContexts,
			"Inferred From"				=> $this->inferred,
			"Selected From"				=> $this->selected
		);
		foreach ($lists as $name => $list) {
			if (!count($list))
				continue;
			print "<ul><b><u>$name</u></b><br>";
			foreach ($list as $node) {
				print "<li>";
				$node->showHtml();
				print "</li>";
			}
			print "</ul>";
		}
	}
	function showMeHtml() {
		print "<b>" . $this->conceptNameCode;
		if (strlen($this->observationDatetime))
			print " (<i>Observation DateTime</i>: " . $this->observationDatetime . ")";
		print "</b><br>" . $this->value . "<br>";
	}
}

class ContainerItem extends ContentItem {
	var $continuity;

    function __construct(&$concept, &$continuity) {
		ContentItem::__construct($concept);
		$this->type = "Container";
		$this->continuity = $continuity;
	}
    function __destruct() {}
	function showMeHtml() {
		print "<h2>" . $this->conceptNameCode . "</h2>";
	}
}

class TextValueItem extends ContentItem {
    function __construct(&$concept, &$value) {
		ContentItem::__construct($concept);
		$this->type = "TextValue";
		$this->value = $value;
	}
    function __destruct() {}
	function showMeHtml() {
		print "<b>" . $this->conceptNameCode;
        // convert '\r', '\n' or "\r\n" into HTML <br> tag
        if (strstr($this->value, "\r") || strstr($this->value, "\n")) {
            $this->value = str_replace("\r\n", "<br>", $this->value);
            $this->value = str_replace("\r", "<br>", $this->value);
            $this->value = str_replace("\n", "<br>", $this->value);
        }
		print "</b><br>" . $this->value . "<br>";
    }
}

class DateTimeItem extends ContentItem {
    function __construct(&$concept, &$value) {
		ContentItem::__construct($concept);
		$this->type = "DateTime";
		$this->value = $value;
	}
    function __destruct() {}
}

class DateItem extends ContentItem {
    function __construct(&$concept, &$value) {
		ContentItem::__construct($concept);
		$this->type = "Date";
		$this->value = $value;
	}
    function __destruct() {}
}

class TimeItem extends ContentItem {
    function __construct(&$concept, &$value) {
		ContentItem::__construct($concept);
		$this->type = "Time";
		$this->value = $value;
	}
    function __destruct() {}
}

class PersonNameItem extends ContentItem {
    function __construct(&$concept, &$value) {
		ContentItem::__construct($concept);
		$this->type = "PersonName";
		$this->value = $value;
	}
    function __destruct() {}
}

class UidItem extends ContentItem {
    function __construct(&$concept, &$value) {
		ContentItem::__construct($concept, $value);
		$this->type = "Uid";
		$this->value = $value;
	}
    function __destruct() {}
}

class NumericItem extends ContentItem {
    function __construct(&$concept, &$value) {
		ContentItem::__construct($concept, $value);
		$this->type = "Numeric";
		$this->value = $value;
	}
    function __destruct() {}
	function showMeHtml() {
		print "<b>" . $this->conceptNameCode . "</b><br>";
		$numeric = $this->value->getAttr(0x0040A30A);
		$code = $this->value->getItem(0x004008EA);
		$numeric .= " " . $code->getAttr(0x00080100);
		$numeric .= " (" . $code->getAttr(0x00080104) . ")";
		print "$numeric<br>";
	}
}

class CodeItem extends ContentItem {
    function __construct(&$concept, &$value) {
		ContentItem::__construct($concept, $value);
		$this->type = "Code";
		$this->value = $value;
	}
    function __destruct() {}
	function showMeHtml() {
		print "<b>" . $this->conceptNameCode . "</b><br>";
		$code = $this->value->getAttr(0x00080104);
		print "$code<br>";
	}
}

class CompositeItem extends ContentItem {
    function __construct(&$concept, &$value) {
		ContentItem::__construct($concept, $value);
		$this->type = "Composite";
		$this->value = $value;
	}
    function __destruct() {}
	function showMeHtml() {
        global $dbcon;
		print "<b>" . $this->conceptNameCode . "</b><br>";
		print "<table>";
		$uid = $this->value->getAttr(0x00081150);
		print "<tr><td>Referenced SOP Class: </td>";
		print "<td>" . getSopClassName($uid) . "</td></tr>";
		$uid = $this->value->getAttr(0x00081155);
		print "<tr><td>Referenced SOP Instance: </td>";
		$url = $dbcon->findRefImage($uid);
		if (strlen($url))
			$uid = $url;
		print "<td>" . $uid . "</td></tr>";
		print "</table><br>";
	}
}

class ImageItem extends ContentItem {
    function __construct(&$concept, &$value) {
		ContentItem::__construct($concept, $value);
		$this->type = "Image";
		$this->value = $value;
	}
    function __destruct() {}
	function showMeHtml() {
        global $dbcon;
		print "<b>" . $this->conceptNameCode . "</b><br>";
		print "<table>";
		$uid = $this->value->getAttr(0x00081150);
		print "<tr><td>Referenced SOP Class: </td>";
		print "<td>" . getSopClassName($uid) . "</td></tr>";
		$uid = $this->value->getAttr(0x00081155);
		print "<tr><td>Referenced SOP Instance: </td>";
		$url = $dbcon->findRefImage($uid);
		if (strlen($url))
			$uid = $url;
		print "<td>" . $uid . "</td></tr>";
		// optional reference frame number
		if ($this->value->hasKey(0x00081160)) {
			print "<tr><td>Referenced Frame Number: </td>";
			$refFrame = $this->value->getAttr(0x00081160);
			print "<td>" . $refFrame . "</td></tr>";
		}
		// optional reference to softcopy presentation statement SOP pair
		if ($this->value->hasKey(0x00081199)) {
			$prenState = $this->value->getItem(0x00081199);
			$uid = $prenState->getAttr(0x00081150);
			print "<tr><td>Referenced Softcopy Prensentation State SOP Class: </td>";
			print "<td>" . getSopClassName($uid) . "</td></tr>";
			$uid = $prenState->getAttr(0x00081155);
			print "<tr><td>Referenced Softcopy Prensentation State SOP Instance: </td>";
			$url = $dbcon->findRefImage($uid);
			if (strlen($url))
				$uid = $url;
			print "<td>" . $uid . "</td></tr>";
		}
		print "</table><br>";
	}
}

class WaveformItem extends ContentItem {
    function __construct(&$concept, &$value) {
		ContentItem::__construct($concept, $value);
		$this->type = "Waveform";
		$this->value = $value;
	}
    	function __destruct() {}
    	function showMeHtml() {
    	global $dbcon;
	print "<b>" . $this->conceptNameCode . "</b><br>";
	print "<table>";
	$uid = $this->value->getAttr(0x00081150);
	print "<tr><td>Referenced SOP Class: </td>";
	print "<td>" . getSopClassName($uid) . "</td></tr>";
	$uid = $this->value->getAttr(0x00081155);
	print "<tr><td>Referenced SOP Instance: </td>";
	$url = $dbcon->findRefImage($uid);
	if (strlen($url))
		$uid = $url;
	print "<td>" . $uid . "</td></tr>";
	print "<tr><td>Referenced Waveform Channels: </td>";
	if ($this->value->hasKey(0x0040A0B0))
		$channel = $this->value->getAttr(0x0040A0B0);
	else
		$channel = "N/A";
	print "<td>" . $channel . "</td></tr>";
	print "</table><br>";
	}
}

class RawTags extends DicomObject {
    var $attrs = array();
    var $explicit = false;
    var $handle;
    var $fileSize;
    var $syntax;
    var $skipPixelData;

    function __construct($path, $skipPixelData = true) {
        global $XFER_SYNTAX_TBL;
        global $EXPLICIT_VR_TBL;
        global $ATTR_TBL;
        $this->skipPixelData = $skipPixelData;
        $this->handle = fopen($path, "rb");
        $this->fileSize = filesize($path);
        $data = fread($this->handle, 132);
        // check if Part 10 format
        $signature = substr($data, 128, 4);
        if (strcmp($signature, "DICM") == 0) {
            $this->fileSize -= 132;
            // skip the Part 10 headers
            $data = fread($this->handle, 12);
            $this->fileSize -= 12;
            $array = unpack('vgroup/velement/A2vr/vvalue/Vlength', $data);
            $group = $array['group'];
            $element = $array['element'];
            $vr = $array['vr'];
            $value = isset($array['value'])? $array['value'] : "";
            $length = $array['length'];
            $headers = fread($this->handle, $length);
            $this->fileSize -= $length;
            while (strlen($headers) > 0) {
        	    $array = unpack('vgroup/velement/A2vr', $headers);
                $group = $array['group'];
                $element = $array['element'];
                $key = $group << 16 | $element;
                $vr = $array['vr'];
                $headers = substr($headers, 6);
                if (isset($EXPLICIT_VR_TBL[ $vr ])) {
                    $format = "vreserved/Vlength";
                    $offset = 6;
                } else {
                    $format = "vlength";
                    $offset = 2;
                }
            	$array = unpack($format, $headers);
                $length = $array['length'];
                if (isset($ATTR_TBL[$key]))
                    $format = $this->convertEndian($ATTR_TBL[$key]->type);
                else
                    $format = ($element == 0)? $this->convertEndian("V") : "A";
                if (strcasecmp($format[0], "A") == 0)
                    $format .= $length;
                $format .= 'value';
                $body = substr($headers, $offset, $length);
                $array = unpack($format, $body);
                $value = isset($array['value'])? trim($array['value']) : "";
                // check transfer syntax to see if it's supported
                if (($group == 0x0002) && ($element == 0x0010)) {
                    $uid = trim($value);
                    $syntax = $XFER_SYNTAX_TBL[$uid][2];
                    $this->syntax = "$syntax - $uid";
                    if (isset($XFER_SYNTAX_TBL[$uid])) {
                        $this->explicit = $XFER_SYNTAX_TBL[$uid][0];
                        $this->bigEnd = $XFER_SYNTAX_TBL[$uid][1];
                    }
                } else if (($group == 0x0002) && ($element == 0x0001)) {
                    $value = sprintf("%'02x\\%'02x", $array["value1"], $array["value2"]);
                }
                // save this attribute
                $this->attrs[$key] = array("vr" => $vr, "value" => $value);
                $headers = substr($headers, $offset+$length);
            }
        } else {
            fseek($this->handle, 0, SEEK_SET);
            // try to guess if the transfer syntax is explicit or implicit vr
            $vr = substr($data, 4, 2);
            if (ctype_alnum($vr))
                $this->explicit = true;
        }
        if ($this->explicit) {
            $this->parseDataExplicit();
        } else {
            $this->parseDataImplicit();
        }
        fclose($this->handle);
    }
    function __destruct() { }
    function parseDataImplicit() {
        global $ATTR_TBL;
        global $UNDEF_LEN;
        while ($this->fileSize > 0) {
            $header = fread($this->handle, 8);
            $this->fileSize -= 8;
            $format = $this->bigEnd? 'ngroup/nelement/Nlength' : 'vgroup/velement/Vlength';
        	$array = unpack($format, $header);
            $group = $array['group'];
            $element = $array['element'];
            $length = $array['length'];
            if ($length == 0)
                continue;
            $key = $group << 16 | $element;
            if ($this->skipPixelData && $key > 0x7FE00000)
                break;
            if ($length != $UNDEF_LEN) {
                $body = fread($this->handle, $length);
            } else {
                // must be a sequence
                $body = readSequenceImplicit($this->handle, $this->bigEnd, $this->fileSize);
            }
            if ($key == 0x7FE00010) {
                $pixelData = new PixelData($length, $body, $this->bigEnd);
                $this->attrs[$key] = $pixelData;
                $length = $pixelData->getLength();
            } else {
                if (isset($ATTR_TBL[$key]))
                    $format = $this->convertEndian($ATTR_TBL[$key]->type);
                else
                    $format = ($element == 0)? $this->convertEndian("V") : "A";
                //print "Key = " . dechex($key) . " Length = $length Format = $format<br>";
                if ( ($length == $UNDEF_LEN) ||
                     (strcasecmp($format[0], "S") == 0) ) {     // sequence elements
                    $this->attrs[$key] = new Sequence($key, strlen($body), $body, false, $this->bigEnd);
                    $length = $this->attrs[$key]->getLength();
                } else {
                    if (strcasecmp($format[0], "A") == 0)
                        $format .= $length;
                    $format .= 'value';
                    $array = unpack($format, $body);
                    $value = isset($array['value'])? trim($array['value']) : "";
                    // save this attribute
                    $vr = isset($ATTR_TBL[$key])? $ATTR_TBL[$key]->vr : "";
                    $this->attrs[$key] = array("vr" => $vr, "value" => $value);
                }
            }
            $this->fileSize -= $length;
        }
    }
    function parseDataExplicit() {
        global $ATTR_TBL;
        global $EXPLICIT_VR_TBL;
        global $UNDEF_LEN;
        while ($this->fileSize > 0) {
            $header = fread($this->handle, 6);
            $this->fileSize -= 6;
            $format = $this->bigEnd? 'ngroup/nelement/A2vr' : 'vgroup/velement/A2vr';
        	$array = unpack($format, $header);
            $group = $array['group'];
            $element = $array['element'];
            $key = $group << 16 | $element;  
            if ($this->skipPixelData && $key > 0x7FE00000)
                break;
            $vr = $array['vr'];
            if (isset($EXPLICIT_VR_TBL[$vr])) {
                $format = $this->bigEnd? "nreserved/Nlength" : "vreserved/Vlength";
                $offset = 6;
            } else {
                $format = $this->bigEnd? "nlength" : "vlength";
                $offset = 2;
            }
            $data = fread($this->handle, $offset);
            $this->fileSize -= $offset;
            $array = unpack($format, $data);
            $length = $array['length'];
            if ($length == 0)
                continue;
            if ($length != $UNDEF_LEN) {
                $body = fread($this->handle, $length);
            } else {
                // must be a sequence
                $body = readSequenceExplicit($this->handle, $this->bigEnd, $this->fileSize);
            }
            if ($key == 0x7FE00010) {
                $pixelData = new PixelData($length, $body, $this->bigEnd);
                $this->attrs[$key] = $pixelData;
                $length = $pixelData->getLength();
            } else {
                if (isset($ATTR_TBL[$key]))
                    $format = $this->convertEndian($ATTR_TBL[$key]->type);
                else
                    $format = ($element == 0)? $this->convertEndian("V") : "A";
                //print "Key = " . dechex($key) . " Length = $length Format = $format<br>";
                if ( ($length == $UNDEF_LEN) || strcasecmp($vr, "SQ") == 0 ||
                     (strcasecmp($format[0], "S") == 0) ) {     // sequence elements
                    if ($length == $UNDEF_LEN)
                        $length = strlen($body);
                    $this->attrs[$key] = new Sequence($key, $length, $body, true, $this->bigEnd);
                    $length = $this->attrs[$key]->getLength();
                } else {
                    if (strcasecmp($format[0], "A") == 0)
                        $format .= $length;
                    $format .= 'value';
                    $array = unpack($format, $body);
                    $value = isset($array['value'])? trim($array['value']) : "";
                    // save this attribute
                    $this->attrs[$key] = array("vr" => $vr, "value" => $value);
                }
            }
            $this->fileSize -= $length;
        }
    }
    function showDebug() {
        foreach ($this->attrs as $key => $attr) {
        	if (is_a($attr, 'Sequence') || is_a($attr, 'PixelData'))
                $attr->showDebug();
            else
                print "Attribute (" . dechex($key) . "): Value = " . $attr["value"] . "<br>";
        }
    }
    function showHtml() {
        global $ATTR_TBL;
        if (strlen($this->syntax))
            print "<p>Dicom Transfer Syntax: <b>" . $this->syntax . "</b><br>";
        print "<p><table width=100% cellpadding=3 cellspacing=0 border=1>\n";
        $columns = array("Tag", "Description", "Value");
        print "<tr align=center>";
        foreach ($columns as $key) {
            print "<td><b>$key</b></td>\n";
        }
        print "</tr>\n";
        foreach ($this->attrs as $key => $attr) {
            $group = ($key >> 16);
            $element = ($key & 0xffff);
            if (isset($ATTR_TBL[$key])) {
                $name = $ATTR_TBL[$key]->name;
                $format = $ATTR_TBL[$key]->type;
            } else {
                $name = ($element == 0)? "Group Length" : "Unknown";
                $format = ($element == 0)? "V" : "A";
            }
            $key = sprintf("%04x,%04x", $group, $element);
        	if (is_a($attr, 'Sequence'))
                $value = "Sequence";
            else {
                $value = trim($attr["value"]);
                if ($format[0] == 'A')
                    $value = str_replace("^", " ", $value);
            }
            print "<tr><td>$key</td>";
            print "<td>$name</td>\n";
            // do not display private tags
            if ($group & 1)
                $value = "Private Tag";
            if (!strlen($value))
                $value = "&nbsp;";
            if (strlen($value) > 0x100)
                $value =  substr($value, 0, 0x100) . "<br><b>(Only first 256 characters is shown)</b>";
            print "<td>" . htmlentities($value, ENT_SUBSTITUTE) . "</td></tr>\n";
        }
        print "</table>\n";
    }
    function getSequence($key) {
        $seq = false;
        if (array_key_exists($key, $this->attrs)) {
            $attr = $this->attrs[$key];
            if (is_a($attr, 'Sequence'))
                return $attr;
        }
        return $seq;
    }
    function getAttr($key) {
        $value = false;
        if (array_key_exists($key, $this->attrs)) {
            $attr = $this->attrs[$key];
            if (is_a($attr, 'Sequence'))
                $value = $attr->getAttr($key);
            else
                $value = $attr["value"];
        }
        return $value;
    }
    function getPixelData() {
        $pixel = false;
        if (array_key_exists(0x7FE00010, $this->attrs))
            $pixel = $this->attrs[0x7FE00010];
        return $pixel;
    }
}

?>
