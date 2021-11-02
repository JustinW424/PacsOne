<script language="JavaScript" type="text/javascript">
<!--        
//
// checkDate.js
//
// JavaScript for checking valid date format
//
// CopyRight (c) 2003-2004 Xiaohui Li
//
function checkDate(date)
{
	var error = "";
	var dashes = 0;
	var tokens = 1;
	var seenToken = false;
	var length = date.value.length;

	if (length == 0)
		return true;

	for (var i = 0; i < length; i++) {
		var char = date.value.charAt(i);
		if (char == '-') {
			dashes++;
			seenToken = true;
		}
		else if (char < '0' || char > '9')
			break;
		else if (seenToken) {
			tokens++;
			seenToken = false;
		}
	}
	if (dashes != 2 || tokens != 3) {
		error = "Date: " + date.value + " is invalid.";
		alert(error);
		return false;
	}
	return true;
} 

function checkTime(time)
{
	var error = "";
	var seps = 0;
	var tokens = 1;
	var seenToken = false;
	var length = time.value.length;

	if (length == 0)
		return true;

	for (var i = 0; i < length; i++) {
		var char = time.value.charAt(i);
		if (char == ':') {
			seps++;
			seenToken = true;
		}
		else if (char < '0' || char > '9')
			break;
		else if (seenToken) {
			tokens++;
			seenToken = false;
		}
	}
	if (seps != 1 || tokens != 2) {
		error = "Time: " + time.value + " is invalid.";
		alert(error);
		return false;
	}
	return true;
} 

//-->
</script>

