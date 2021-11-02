<script type="text/javascript" src="jquery/1.4.0/jquery.min.js"></script>
<script language="JavaScript" type="text/javascript">
<!--        
//
// jpegLossy.js
//
// JavaScript for displaying lossy compression settings
//
// CopyRight (c) 2013 RainbowFish Software
//
$(document).ready(function() {
  var current = $("#txsyntax").val();
  if (current.indexOf("1.2.840.10008.1.2.4.91") != -1 ||
      current.indexOf("1.2.840.10008.1.2.4.81") != -1 ||
      current.indexOf("1.2.840.10008.1.2.4.50") != -1 ||
      current.indexOf("1.2.840.10008.1.2.4.51") != -1 ) {
      $("#lossyquality").show();
      if (current.indexOf("1.2.840.10008.1.2.4.50") == -1 &&
          current.indexOf("1.2.840.10008.1.2.4.51") == -1) {
          $("#lossyratio").show();
      } else {
          $("#lossyratio").hide();
      }
  } else {
      $("#lossyquality").hide();
  }
  $("#txsyntax").change(function() {
    var selected = $(this).val();
    if (selected.indexOf("1.2.840.10008.1.2.4.91") != -1 ||
        selected.indexOf("1.2.840.10008.1.2.4.81") != -1 ||
        selected.indexOf("1.2.840.10008.1.2.4.50") != -1 ||
        selected.indexOf("1.2.840.10008.1.2.4.51") != -1 ) {
        $("#lossyquality").show();
        if (selected.indexOf("1.2.840.10008.1.2.4.50") == -1 &&
            selected.indexOf("1.2.840.10008.1.2.4.51") == -1) {
            $("#lossyratio").show();
        } else {
            $("#lossyratio").hide();
        }
    } else {
        $("#lossyquality").hide();
    }
  });
});

//-->
</script>

