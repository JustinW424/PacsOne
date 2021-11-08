<script language="JavaScript" type="text/javascript">
var last = "false";
        
function checkAll(form, field, check, uncheck)
{
	var len = form.elements.length;
    if (last == "false") {
        ret = uncheck;
	    for (var i = 0; i < len; i++) {
	        if (form.elements[i].name == field+'[]')
		        form.elements[i].checked = "true";
	        if (form.elements[i].name == "checkUncheck")
		        form.elements[i].value = ret;
        }
        last = "true";
    } else {
        ret = check;
	    for (var i = 0; i < len; i++) {
	        if (form.elements[i].name == field+'[]')
		        form.elements[i].checked = "";
	        if (form.elements[i].name == "checkUncheck")
		        form.elements[i].value = ret;
	    }
        last = "false";
	}
    return ret;
}

function switchText(form, field, text)
{
    var msg = "Field = " + field + " Text = " + text + "\n";
	var len = form.elements.length;
	for (var i = 0; i < len; i++) {
	    if (form.elements[i].name == field) {
            form.elements[i].value = text;
        }
    }
}

function toggleFilter(form, show, hide) {
	var element = document.getElementById("filterSettings").style;
	if (element.display == "inline-block") {
		element.display = "none";
		element.height = "0pt";
	    var len = form.elements.length;
	    for (var i = 0; i < len; i++) {
	        if (form.elements[i].id == "filterButton")
                form.elements[i].value = show;
        }
	} else {
		element.display = "inline-block";
		element.height = "auto"; //"210pt";  // corrected by rina 2021.11.06
	    var len = form.elements.length;
	    for (var i = 0; i < len; i++) {
	        if (form.elements[i].id == "filterButton")
                form.elements[i].value = hide;
        }
	}
}

</script>
