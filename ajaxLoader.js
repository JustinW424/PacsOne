<script type="text/javascript" src="jquery/1.4.0/jquery.min.js"></script>
<script language="JavaScript" type="text/javascript">
<!--        
//
// ajaxLoader.js
//
// JavaScript for displaying a pre-loader image via AJAX
//
// CopyRight (c) 2013-2021 RainbowFish Software
//
$(document).ready(function() {
  $("[class^='ajaxbutton']").click(function() {
    var cl = $(this).attr('class');
    if (cl == "ajaxbuttonDelete") {
        var msg = $('input[name="confirm"]').val();
        if (!confirm(msg))
            return false;
    }
    var buttons = $("." + cl);
    $.each(buttons, function(index, el) {
        el.disabled = true;
    });
    $("#preloader").show();
    var what = $('input[name="option"]').val();
    var postData = {
        option: what,
        action: $('input[name="actionvalue"]').val(),
        entry: []
    };
    var inputElements = $('input[name^=entry]:checked');
    $.each(inputElements, function(index, el) {
        postData.entry.push(el.value);
    });
    if (postData.entry.length == 0) {
        alert("No " + what + " is selected");
        $("#preloader").hide();
        $.each(buttons, function(index, el) {
            el.disabled = false;
        });
        return false;
    }
    $.ajax({
        url : 'actionItem.php',
        cache: false,
        data : postData,
        type : 'POST',
        dataType : 'json',
        complete : function(xhr, status) {
            $("#preloader").hide();
            $.each(buttons, function(index, el) {
                el.disabled = false;
            });
            if (status.toLowerCase() != 'success') {
                //console.log(xhr.responseText);
                alert("Error: " + xhr.responseText);
            }
        },
        error : function(xhr, status, error) {
            alert(status + " Error: " + error);
        },
        success : function(resp, status, xhr) {
            //console.log(resp);
            $("#preloader").hide();
            if (cl == "ajaxbuttonDelete")
                window.location = resp.url;
            else
                window.open("downloadZip.php?seq=" + encodeURI(resp.seq));
        }
    });
    return false;
  });
});

//-->
</script>

