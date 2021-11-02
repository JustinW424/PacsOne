<?php
//
// applet.php
//
// Module for displaying images through the Cornerstone HTML5/JS viewer
//
// CopyRight (c) 2004-2015 RainbowFish Software
//

function appletExists()
{
	$dir = dirname($_SERVER['SCRIPT_FILENAME']);
	$dir .= "/cornerstone/cornerstone.min.js";
	return file_exists($dir);
}

//
// uids - array of SOP instance UIDs to display
//
// studies - array of study objects to be converted to JSON and displayed
//
function appletViewer(&$uids, &$studies)
{
	print "<html>\n";
    print "<head>\n";
    print "<!-- support for mobile touch devices -->\n";
    print "<meta name=\"apple-mobile-web-app-capable\" content=\"yes\">\n";
    print "<meta name=\"viewport\" content=\"user-scalable=no, width=device-width, initial-scale=1, maximum-scale=1, minimal-ui\">\n";
    print "<link href=\"cornerstone/bootstrap.min.css\" rel=\"stylesheet\">\n";
    print "<link href=\"font-awesome.min.css\" rel=\"stylesheet\">\n";
    print "<link href=\"cornerstoneViewer.css\" rel=\"stylesheet\">\n";
    print "<link href=\"cornerstone/cornerstone.min.css\" rel=\"stylesheet\">\n";
    print "</head>\n";
	print "<body leftmargin=\"0\" topmargin=\"0\" bgcolor=\"#cccccc\">\n";
	require_once 'header.php';
    // integration script for CornerStone viewer
	require_once 'cornerstoneViewer.js';
?>
<div id="wrap">
    <div style="width:100%;height:2px"></div>
    <div class='main'>
        <ul id="tabs" class="nav nav-tabs" >
        </ul>

        <div id="tabContent" class="tab-content">
            <div id="studyList" class="tab-pane active">
                <div class="row">
                </div>
            </div>
        </div>

        <div id="studyViewerTemplate" class="tab-pane active hidden" style="height:100%">
            <div class="studyContainer" style="height:100%">
                <div class="studyRow row" style="height:100%">
                    <div class="thumbnailSelector">
                        <div class="thumbnails list-group">
                        </div>
                    </div>
                    <div class="viewer">
                        <div class="text-center" >
                            <div class="btn-group">
                                <button type="button" class="btn btn-sm btn-default" data-container='body' data-toggle="tooltip" data-placement="bottom" title="WW/WC"><span class="fa fa-sun-o"></span></button>
                                <button type="button" class="btn btn-sm btn-default" data-container='body' data-toggle="tooltip" data-placement="bottom" title="Invert"><span class="fa fa-adjust"></span></button>
                                <button type="button" class="btn btn-sm btn-default" data-container='body' data-toggle="tooltip" data-placement="bottom" title="Zoom"><span class="fa fa-search"></span></button>
                                <button type="button" class="btn btn-sm btn-default" data-container='body' data-toggle="tooltip" data-placement="bottom" title="Pan"><span class="fa fa-arrows"></span></button>
                                <button type="button" class="btn btn-sm btn-default" data-container='body' data-toggle="tooltip" data-placement="bottom" title="Stack Scroll"><span class="fa fa-bars"></span></button>
                                <button type="button" class="btn btn-sm btn-default" data-container='body' data-toggle="tooltip" data-placement="bottom" title="Length Measurement"><span class="fa fa-arrows-v"></span></button>
                                <button type="button" class="btn btn-sm btn-default" data-container='body' data-toggle="tooltip" data-placement="bottom" title="Pixel Probe"><span class="fa fa-dot-circle-o"></span></button>
                                <button type="button" class="btn btn-sm btn-default" data-container='body' data-toggle="tooltip" data-placement="bottom" title="Elliptical ROI"><span class="fa fa-circle-o"></span></button>
                                <button type="button" class="btn btn-sm btn-default" data-container='body' data-toggle="tooltip" data-placement="bottom" title="Rectangle ROI"><span class="fa fa-square-o"></span></button>
                                <button type="button" class="btn btn-sm btn-default" data-container='body' data-toggle="tooltip" data-placement="bottom" title="Play Clip"><span class="fa fa-play"></span></button>
                                <button type="button" class="btn btn-sm btn-default" data-container='body' data-toggle="tooltip" data-placement="bottom" title="Stop Clip"><span class="fa fa-stop"></span></button>
                            </div>
                        </div>
                        <div class="imageViewer">
                            <div class="viewportWrapper" style="width:100%;height:100%;position:relative;color: white;display:inline-block;background-color:black;"
                                 oncontextmenu="return false"
                                 class='cornerstone-enabled-image'
                                 unselectable='on'
                                 onselectstart='return false;'
                                 onmousedown='return false;'>
                                <div class="viewport">
                                </div>
                                <div class="overlay" style="top:0px; left:0px">
                                    <div>Patient Name</div>
                                    <div>Patient Id</div>
                                </div>
                                <div class="overlay" style="top:0px; right:0px">
                                    <div>Study Description</div>
                                    <div>Study Date</div>
                                </div>

                                <div class="overlay" style="bottom:0px; left:0px">
                                    <div class="fps">FPS:</div>
                                    <div class="renderTime">Render Time:</div>
                                    <div class="currentImageAndTotalImages">Image #:</div>
                                </div>
                                <div class="overlay" style="bottom:0px; right:0px">
                                    <div>Zoom:</div>
                                    <div>WW/WC:</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
             </div>
        </div>
    </div>
</div>
<?php
    // load only the 1st study in the list for now
    $values = array_values($studies);
    $encoded = json_encode($values[0]);
    if (!$encoded)
        die("json_encode() error: " . json_last_error_msg());
    // convert to JSON object
    print "<script>\n";
    print "var study = $encoded;\n";
    print "cornerstoneLoadStudy(study);";
    print "</script>\n";
    // call the CornerStone Javascript viewer function
	require_once 'footer.php';
	print "</body>\n";
	print "</html>\n";
}

?>
