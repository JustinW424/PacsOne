<script language="JavaScript" type="text/javascript">
<!--        
//
// checkInput.js
//
// JavaScript for input validation
//
// CopyRight (c) 2003-2005 Xiaohui Li
//
function checkHeadline(line)
{
    var error = "";
    var length = line.value.length;

    if (length == 0) {
        error = "No headline is entered!";
        alert(error);
        return false;
    }
    return true;
}

function checkPassword(form)
{
    if (form.oldPassword.value.length == 0) {
        alert("Please enter your existing password!");
        return false;
    }
    if (form.newPassword.value.length == 0) {
        alert("Please enter your new password!");
        return false;
    }
    if (form.newPassword2.value.length == 0) {
        alert("Please re-enter your new password!");
        return false;
    }
    if (form.oldPassword.value == form.newPassword.value) {
        alert("New password and old password cannot be the same!");
        return false;
    }
    if (form.newPassword.value != form.newPassword2.value) {
        alert("New password and re-entered new password must be the same!");
        return false;
    }
    return true;
}

function getViewerDir(form)
{
    var intPos;
    var strFile = form.viewerfile.value;
    var strDirectory;

    if (strFile.length) {
        // change to Unix-style path
        strFile = strFile.replace(/\\/g, "/");
        intPos = strFile.lastIndexOf("/");
        strDirectory = strFile.substring(0, intPos);
        form.viewerdir.value = strDirectory;
    }
    return false;
}

//-->
</script>

